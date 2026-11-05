<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes the foreign keys that had no index at all.
 *
 * PostgreSQL never indexes a reference column by itself, so enforcing the constraint on a
 * parent delete (`users`, `parts`, `tax_rates`, `suppliers`) — and any join through that
 * column — scans the whole child table. MySQL does create an index for each constraint,
 * which is why a column that already leads an existing index is skipped here: a second
 * identical index would only add write cost.
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string>>
     */
    private const TABLES = [
        'part_supplier' => ['supplier_id'],
        'maintenance_plan_part' => ['part_id'],
        'parts' => ['tax_rate_id'],
        'ticket_attachments' => ['user_id'],
        'ticket_comments' => ['user_id'],
        'tickets' => ['resolved_by', 'closed_by', 'budget_approved_by'],
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table => $columns) {
            foreach ($columns as $column) {
                $name = $this->indexName($table, $column);

                if (Schema::hasIndex($table, $name) || $this->leadingIndexOn($table, $column) !== null) {
                    continue;
                }

                Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($column, $name));
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table => $columns) {
            foreach ($columns as $column) {
                $name = $this->indexName($table, $column);

                // Drop only what this migration created. An auto-generated `*_foreign`
                // index belongs to the constraint and to the migration that added it.
                if (Schema::hasIndex($table, $name)) {
                    Schema::table($table, fn (Blueprint $blueprint) => $blueprint->dropIndex($name));
                }
            }
        }
    }

    private function indexName(string $table, string $column): string
    {
        return sprintf('%s_%s_fk_idx', $table, $column);
    }

    /**
     * Name of an index that already starts with this column, if any. Only the first
     * column of a composite index can serve a lookup on this one alone.
     */
    private function leadingIndexOn(string $table, string $column): ?string
    {
        foreach (Schema::getIndexes($table) as $index) {
            $first = $index['columns'][0] ?? null;

            if (is_string($first) && mb_strtolower($first) === mb_strtolower($column)) {
                return (string) $index['name'];
            }
        }

        return null;
    }
};
