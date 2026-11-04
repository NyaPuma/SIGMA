<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The month/KPI/calendar queries range tickets by `opened_at` and, since the close
 * action stamps it, by `resolved_at`, but both columns were only ever indexed behind
 * `status_id` (`status_id, opened_at`) — unusable when the report does not constrain
 * the status. `deleted_at` leads because Eloquent always appends `deleted_at IS NULL`,
 * which is an equality the range can then follow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->index(['deleted_at', 'opened_at'], 'tickets_deleted_at_opened_idx');
            $table->index(['deleted_at', 'resolved_at'], 'tickets_deleted_at_resolved_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropIndex('tickets_deleted_at_opened_idx');
            $table->dropIndex('tickets_deleted_at_resolved_idx');
        });
    }
};
