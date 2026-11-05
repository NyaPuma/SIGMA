<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `login_attempts` and `locked_until` were never written by anything. The login flow keeps
 * its counter in the cache (`AuthController::login()`), so the columns stayed at their
 * defaults forever while `UserResource` published them to the admin API as if an account
 * really carried a lockout. Worse, both were mass-assignable: an update that forwarded the
 * raw payload could set a `locked_until` no check ever honoured, showing a user as blocked
 * while they signed in normally — and the reverse could not be trusted either.
 *
 * The index goes first because SQLite refuses to drop a column that one points at.
 */
return new class extends Migration
{
    private const LOCKED_UNTIL_INDEX = 'users_locked_until_index';

    public function up(): void
    {
        if (Schema::hasColumn('users', 'locked_until') && $this->hasIndex(self::LOCKED_UNTIL_INDEX)) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex(self::LOCKED_UNTIL_INDEX);
            });
        }

        foreach (['login_attempts', 'locked_until'] as $column) {
            if (! Schema::hasColumn('users', $column)) {
                continue;
            }

            Schema::table('users', function (Blueprint $table) use ($column) {
                $table->dropColumn($column);
            });
        }
    }

    public function down(): void
    {
        $restoredLockoutColumn = false;

        Schema::table('users', function (Blueprint $table) use (&$restoredLockoutColumn) {
            if (! Schema::hasColumn('users', 'login_attempts')) {
                $table->unsignedInteger('login_attempts')->default(0);
            }

            if (! Schema::hasColumn('users', 'locked_until')) {
                $table->timestamp('locked_until')->nullable();
                $restoredLockoutColumn = true;
            }
        });

        if ($restoredLockoutColumn && ! $this->hasIndex(self::LOCKED_UNTIL_INDEX)) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('locked_until', self::LOCKED_UNTIL_INDEX);
            });
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function hasIndex(string $name): bool
    {
        foreach (Schema::getIndexes('users') as $index) {
            if (($index['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
};
