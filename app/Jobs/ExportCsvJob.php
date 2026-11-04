<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Jobs\Concerns\SetsRequesterLocale;
use App\Models\Notification;
use App\Services\AnalyticsService;
use App\Support\ExportArtifact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ExportCsvJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use SetsRequesterLocale;

    /**
     * The maximum number of attempts before the job fails.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job may run.
     */
    public int $timeout = 120;

    public function __construct(
        public readonly int $userId,
    ) {}

    public function handle(AnalyticsService $analyticsService): void
    {
        $this->applyRequesterLocale($this->userId);

        $disk = Storage::disk(ExportArtifact::DISK);
        $relative = ExportArtifact::path($this->userId, 'tickets_report', 'csv');

        $disk->makeDirectory('exports/'.$this->userId);

        $analyticsService->exportCsvToFile($disk->path($relative));

        Notification::create([
            'user_id' => $this->userId,
            'title' => __('exports.csv_completed'),
            'message' => __('exports.file_ready', ['file' => ExportArtifact::filename($relative)]),
            'type' => 'system',
            'is_read' => false,
            'link' => ExportArtifact::link($relative),
        ]);
    }

    /**
     * Notifies the user when a fatal error occurs during file generation.
     */
    public function failed(?Throwable $exception): void
    {
        $this->applyRequesterLocale($this->userId);

        Notification::create([
            'user_id' => $this->userId,
            'title' => __('exports.csv_failed'),
            'message' => __('exports.report_failed_generic'),
            'type' => 'system',
            'is_read' => false,
            'link' => null,
        ]);
    }
}
