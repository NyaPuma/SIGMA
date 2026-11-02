<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class PartitionAudits extends Command
{
    protected $signature = 'audit:partition
                            {--months=12 : Number of months of old partitions to retain}
                            {--months-ahead=3 : Number of future months to create ahead of time}
                            {--dry-run : Shows the SQL operations without executing them}';

    protected $description = 'Creates and drops real data partitions (ALTER TABLE) on the audits table';

    public function handle(): int
    {
        $driver = DB::connection()->getDriverName();

        if ($driver !== 'mysql') {
            $this->warn("Native RANGE partitioning in this implementation only supports MySQL/MariaDB. Current driver: {$driver}");

            return self::FAILURE;
        }

        $monthsAhead = (int) $this->option('months-ahead');
        $monthsToKeep = (int) $this->option('months');
        $dryRun = (bool) $this->option('dry-run');

        $this->info('Checking partitions on the audits table...');

        try {
            $this->createFuturePartitions($monthsAhead, $dryRun);
            $this->dropOldPartitions($monthsToKeep, $dryRun);

            $this->info('Partition management completed successfully.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Error managing partitions: {$e->getMessage()}");

            return self::FAILURE;
        }
    }

    private function createFuturePartitions(int $monthsAhead, bool $dryRun): void
    {
        $existingPartitions = $this->getExistingPartitions();

        for ($i = 0; $i <= $monthsAhead; $i++) {
            // Ensure exact month start to avoid bugs with 28/30/31-day months
            $date = now()->startOfMonth()->addMonths($i);
            $partitionName = 'p_'.$date->format('Y_m');

            if (in_array($partitionName, $existingPartitions, true)) {
                continue;
            }

            // The LESS THAN boundary must be the first day of the NEXT month at 00:00:00
            $upperBound = $date->copy()->addMonth()->startOfMonth()->format('Y-m-d H:i:s');

            $sql = sprintf(
                "ALTER TABLE audits REORGANIZE PARTITION p_future INTO (
                    PARTITION %s VALUES LESS THAN ('%s'),
                    PARTITION p_future VALUES LESS THAN (MAXVALUE)
                );",
                $partitionName,
                $upperBound
            );

            if ($dryRun) {
                $this->info("[DRY-RUN] Would execute SQL: {$sql}");
            } else {
                DB::statement($sql);
                $this->info("Partition created on MySQL: {$partitionName} (data < '{$upperBound}')");
            }
        }
    }

    private function dropOldPartitions(int $monthsToKeep, bool $dryRun): void
    {
        $cutoffDate = now()->startOfMonth()->subMonths($monthsToKeep);
        $cutoffPartitionName = 'p_'.$cutoffDate->format('Y_m');

        $existingPartitions = $this->getExistingPartitions();

        foreach ($existingPartitions as $partition) {
            // Filter partitions that follow the naming pattern (p_YYYY_MM)
            if (! preg_match('/^p_(\d{4})_(\d{2})$/', $partition, $matches)) {
                continue;
            }

            // If the partition name is strictly less than the retention limit partition
            if ($partition < $cutoffPartitionName) {
                $sql = "ALTER TABLE audits DROP PARTITION {$partition};";

                if ($dryRun) {
                    $this->info("[DRY-RUN] Would execute SQL to drop: {$sql}");
                } else {
                    DB::statement($sql);
                    $this->warn("Old partition removed successfully (DROP PARTITION): {$partition}");
                }
            }
        }
    }

    /**
     * Fetches active partitions directly from the MySQL information schema.
     *
     * @return list<string>
     */
    private function getExistingPartitions(): array
    {
        $databaseName = DB::connection()->getDatabaseName();

        $names = DB::table('information_schema.partitions')
            ->where('table_schema', $databaseName)
            ->where('table_name', 'audits')
            ->whereNotNull('partition_name')
            ->pluck('partition_name')
            ->toArray();

        $partitions = [];

        foreach ($names as $name) {
            if (is_string($name)) {
                $partitions[] = $name;
            }
        }

        return $partitions;
    }
}
