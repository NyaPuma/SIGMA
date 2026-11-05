<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `recommended_technician_id` is the only user reference on `tickets` without a foreign
 * key: `assigned_to`, `closed_by`, `resolved_by` and `budget_approved_by` all constrain
 * `users`. The value comes from an AI answer, so a model that invents an id used to be
 * stored as-is and the ticket then pointed at a user who does not exist (the relation
 * silently returned null and the assignment panel showed nothing).
 *
 * The statement that clears the dangling ids runs first because the constraint would
 * otherwise refuse to be added on a database that already has such rows.
 */
return new class extends Migration
{
    private const INDEX_NAME = 'tickets_recommended_technician_id_foreign';

    public function up(): void
    {
        DB::statement(<<<'SQL'
            update tickets
               set recommended_technician_id = null
             where recommended_technician_id is not null
               and not exists (
                   select 1 from users where users.id = tickets.recommended_technician_id
               )
        SQL);

        if ($this->hasForeignKey()) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->foreign('recommended_technician_id', self::INDEX_NAME)
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! $this->hasForeignKey()) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(self::INDEX_NAME);
        });
    }

    /**
     * SQLite silently ignores a foreign key added to an existing table, so on that driver
     * the check always reports false. On MySQL/PostgreSQL it keeps a re-run from failing
     * because the constraint is already there.
     */
    private function hasForeignKey(): bool
    {
        foreach (Schema::getForeignKeys('tickets') as $foreignKey) {
            if (($foreignKey['name'] ?? null) === self::INDEX_NAME) {
                return true;
            }
        }

        return false;
    }
};
