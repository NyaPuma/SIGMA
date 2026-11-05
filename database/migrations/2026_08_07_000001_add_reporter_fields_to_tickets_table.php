<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->change();

            $table->string('reporter_name', 150)
                ->nullable()
                ->after('user_id');

            $table->string('reporter_contact', 150)
                ->nullable()
                ->after('reporter_name');

            $table->string('source', 20)
                ->default('web')
                ->after('reporter_contact');
        });

        Schema::table('ticket_attachments', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->change();
        });
    }

    /**
     * The `up()` loosened two constraints, so a `down()` that only removed the new columns
     * would leave `user_id` nullable forever and the rollback would not do what it says.
     */
    public function down(): void
    {
        $this->restoreOwningUser('tickets');
        $this->restoreOwningUser('ticket_attachments');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['reporter_name', 'reporter_contact', 'source']);
        });
    }

    /**
     * Restore the NOT NULL on `user_id`, which is only possible while every row has an owner.
     * Tickets reported through the public QR page (and the attachments they upload) have
     * none: rebuilding the table as NOT NULL would silently discard those rows, so the
     * rollback aborts and names what has to be resolved first.
     */
    private function restoreOwningUser(string $table): void
    {
        if (! Schema::hasColumn($table, 'user_id')) {
            return;
        }

        $ownerless = DB::table($table)->whereNull('user_id')->count();

        if ($ownerless > 0) {
            throw new RuntimeException(
                "Rollback interrupted: {$ownerless} rows in '{$table}' have no user_id. "
                .'Assign an owner to them (or delete them) before rolling this migration back; '
                .'the alternative would be to drop those rows.'
            );
        }

        Schema::table($table, function (Blueprint $blueprint) {
            $blueprint->foreignId('user_id')
                ->nullable(false)
                ->change();
        });
    }
};
