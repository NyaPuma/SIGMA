<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->enum('type', [
                'ticket_created',
                'ticket_updated',
                'ticket_assigned',
                'ticket_closed',
                'comment_added',
                'attachment_added',
                'budget_requested',
                'budget_request',
                'budget_submitted',
                'budget_approved',
                'budget_rejected',
                'budget_auto_approved',
                'priority_override',
                'low_stock',
                'system',
            ])->default('system')->change();
        });
    }

    public function down(): void
    {
        // Deliberately keeps 'low_stock' in the enum. Rebuilding the table without it
        // while low-stock notifications already exist makes those rows invalid — MySQL
        // rejects the rebuild and SQLite keeps values the check constraint no longer
        // allows. Removing a value is a data migration, not a schema rollback.
    }
};
