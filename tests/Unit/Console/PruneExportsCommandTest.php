<?php

declare(strict_types=1);

namespace Tests\Unit\Console;

use App\Support\ExportArtifact;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;

class PruneExportsCommandTest extends FeatureTestCase
{
    /**
     * @param  array<string, int>  $ages  relative path => age in days
     * @return list<string>
     */
    private function seedArtifacts(array $ages): array
    {
        $disk = Storage::disk(ExportArtifact::DISK);
        $paths = [];

        foreach ($ages as $relative => $daysOld) {
            $disk->put($relative, str_repeat('x', 12));
            // Flysystem reads the modification time from the filesystem, so the
            // age has to be applied to the real file behind the fake disk.
            touch($disk->path($relative), now()->subDays($daysOld)->getTimestamp());
            $paths[] = $relative;
        }

        return $paths;
    }

    #[Test]
    public function it_removes_only_artifacts_past_the_retention_window(): void
    {
        Storage::fake('local');
        [$fresh, $stale] = $this->seedArtifacts([
            ExportArtifact::path(5, 'tickets_report', 'csv') => 1,
            ExportArtifact::path(5, 'tickets_report', 'pdf') => 30,
        ]);

        $this->artisan('exports:prune', ['--days' => 7])
            ->expectsOutputToContain('Removed 1 artifact(s) older than 7 day(s)')
            ->assertExitCode(0);

        $disk = Storage::disk(ExportArtifact::DISK);
        $this->assertTrue($disk->exists($fresh));
        $this->assertFalse($disk->exists($stale));
    }

    #[Test]
    public function it_uses_the_configured_window_when_no_option_is_given(): void
    {
        Storage::fake('local');
        config(['filesystems.exports.retention_days' => 3]);
        [$fresh, $stale] = $this->seedArtifacts([
            ExportArtifact::path(6, 'tickets_report', 'csv') => 2,
            ExportArtifact::path(6, 'tickets_report', 'xlsx') => 4,
        ]);

        $this->artisan('exports:prune')->assertExitCode(0);

        $disk = Storage::disk(ExportArtifact::DISK);
        $this->assertTrue($disk->exists($fresh));
        $this->assertFalse($disk->exists($stale));
    }

    #[Test]
    public function dry_run_reports_without_deleting(): void
    {
        Storage::fake('local');
        [$stale] = $this->seedArtifacts([
            ExportArtifact::path(7, 'cost-parts-per-equipment', 'pdf') => 20,
        ]);

        $this->artisan('exports:prune', ['--days' => 7, '--dry-run' => true])
            ->expectsOutputToContain('[DRY-RUN] Would remove')
            ->assertExitCode(0);

        $this->assertTrue(Storage::disk(ExportArtifact::DISK)->exists($stale));
    }

    #[Test]
    public function it_removes_the_per_user_directory_once_it_is_empty(): void
    {
        Storage::fake('local');
        $this->seedArtifacts([
            ExportArtifact::path(8, 'tickets_report', 'csv') => 40,
        ]);

        $this->artisan('exports:prune', ['--days' => 7])->assertExitCode(0);

        $disk = Storage::disk(ExportArtifact::DISK);
        $this->assertSame([], $disk->directories('exports'));
    }

    #[Test]
    public function it_rejects_a_retention_window_below_one_day(): void
    {
        Storage::fake('local');

        // Deleting everything because of a typo would destroy every pending report.
        $this->artisan('exports:prune', ['--days' => 0])
            ->expectsOutputToContain('at least one day')
            ->assertExitCode(1);
    }

    #[Test]
    public function it_is_a_no_op_when_nothing_has_been_generated(): void
    {
        Storage::fake('local');

        $this->artisan('exports:prune')
            ->expectsOutputToContain('No export artifacts to prune.')
            ->assertExitCode(0);
    }
}
