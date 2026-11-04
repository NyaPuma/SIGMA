<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\ExportArtifact;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Applies a retention window to the generated report artifacts.
 *
 * The export jobs keep writing one file per run under `exports/{user}/` on the
 * private disk. Nothing ever removed them, so the volume grew without a bound
 * even though a report is regenerated on demand and only has to stay available
 * long enough to be downloaded.
 */
class PruneExports extends Command
{
    protected $signature = 'exports:prune
                            {--days= : Retention window in days (defaults to filesystems.exports.retention_days)}
                            {--dry-run : List the files that would be removed without deleting anything}';

    protected $description = 'Removes generated export artifacts older than the retention window';

    public function handle(): int
    {
        $configured = config('filesystems.exports.retention_days', 7);
        $option = $this->option('days');
        $days = is_numeric($option)
            ? (int) $option
            : (is_numeric($configured) ? (int) $configured : 7);

        if ($days < 1) {
            $this->error('The retention window must be at least one day.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk(ExportArtifact::DISK);
        $cutoff = now()->subDays($days)->getTimestamp();

        if (! $disk->exists('exports')) {
            $this->info('No export artifacts to prune.');

            return self::SUCCESS;
        }

        $removed = 0;
        $bytes = 0;
        $errors = 0;

        // `files()` is not recursive and the artifacts live one level down, in
        // the per-user directories, so the sweep has to list the whole tree.
        foreach ($disk->allFiles('exports') as $file) {
            if ($disk->lastModified($file) >= $cutoff) {
                continue;
            }

            $size = $disk->size($file);

            if ($dryRun) {
                $this->line("[DRY-RUN] Would remove {$file}");
            } else {
                try {
                    $disk->delete($file);
                } catch (Throwable $e) {
                    // A single unreadable file must not stop the rest of the sweep.
                    $this->error("Could not remove {$file}: {$e->getMessage()}");
                    $errors++;

                    continue;
                }
            }

            $removed++;
            $bytes += $size;
        }

        if (! $dryRun) {
            $this->pruneEmptyDirectories($disk);
        }

        $verb = $dryRun ? 'Would remove' : 'Removed';
        $this->info(sprintf(
            '%s %d artifact(s) older than %d day(s) (%s).',
            $verb,
            $removed,
            $days,
            $this->humanBytes($bytes),
        ));

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Drops the per-user directories left without files.
     */
    private function pruneEmptyDirectories(Filesystem $disk): void
    {
        foreach ($disk->directories('exports') as $directory) {
            if ($disk->files($directory) === []) {
                $disk->deleteDirectory($directory);
            }
        }
    }

    private function humanBytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1048576 => sprintf('%.1f MB', $bytes / 1048576),
            $bytes >= 1024 => sprintf('%.1f KB', $bytes / 1024),
            default => $bytes.' B',
        };
    }
}
