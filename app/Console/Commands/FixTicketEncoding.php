<?php

namespace App\Console\Commands;

use App\ValueObjects\SqlFragment;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixTicketEncoding extends Command
{
    protected $signature = 'tickets:fix-encoding
                    {--dry-run : Only lists affected records without altering data}';

    protected $description = 'Fixes ticket records with Mojibake (double latin1/utf8mb4 encoding)';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->error('This command only works on MySQL connections.');

            return self::FAILURE;
        }

        if (! Schema::hasTable('tickets')) {
            $this->error('The tickets table does not exist.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        $columns = [
            'title',
            'description',
            'resolution_summary',
            'resolution',
            'technical_report',
            'budget_feedback',
        ];

        $total = 0;

        foreach ($columns as $column) {
            if (! Schema::hasColumn('tickets', $column)) {
                continue;
            }

            $ids = $this->affectedIds($column);

            if ($ids === []) {
                continue;
            }

            $this->line("  tickets.{$column}: ".count($ids).' affected record(s).');

            if (! $dryRun) {
                $idList = implode(',', $ids);

                DB::statement(
                    "UPDATE `tickets` SET `{$column}` = CONVERT(CAST(CONVERT(`{$column}` USING latin1) AS BINARY) USING utf8mb4) WHERE `id` IN ({$idList});"
                );
            }

            $total += count($ids);
        }

        if ($total === 0) {
            $this->info('No mojibake records in the tickets table.');

            return self::SUCCESS;
        }

        if ($dryRun) {
            $this->info("[dry-run] {$total} record(s) would be fixed.");

            return self::SUCCESS;
        }

        $this->info("Fix completed: {$total} record(s) fixed.");

        return self::SUCCESS;
    }

    /**
     * Returns the IDs whose text is genuinely double-encoded.
     *
     * Detection is byte-precise: only occurrences of "Ã" (0xC3 0x83) or
     * "Â" (0xC2 0x82) followed by a continuation byte (0xC2 0x80-0xBF).
     * A simple LIKE with accent-insensitive collation would catch any accented
     * vowel, causing false positives and corruption of clean data.
     */
    /** @return array<int, int> */
    private function affectedIds(string $column): array
    {
        $candidates = DB::table('tickets')
            ->select('id', $column)
            ->whereRaw(
                new SqlFragment("`{$column}` LIKE BINARY CONCAT('%', CONVERT(0xC383 USING utf8mb4), '%') OR `{$column}` LIKE BINARY CONCAT('%', CONVERT(0xC282 USING utf8mb4), '%')")
            )
            ->get();

        $pattern = '/\xC3\x83[\xC2\x80-\xC2\xBF]|\xC2\x82[\xC2\x80-\xC2\xBF]/';
        $ids = [];

        foreach ($candidates as $row) {
            $value = $row->{$column};

            if (! is_string($value) || $value === '') {
                continue;
            }

            if (! preg_match($pattern, $value)) {
                continue;
            }

            // The latin1 -> UTF-8 reversal is only safe if it produces valid text.
            $fixed = mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8');

            if (! mb_check_encoding($fixed, 'UTF-8')) {
                continue;
            }

            $id = $row->id;

            if (is_numeric($id)) {
                $ids[] = (int) $id;
            }
        }

        return $ids;
    }
}
