<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Exports\TicketsExport;
use App\Models\Notification;
use App\Support\ExportArtifact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Throwable;

final class ExportExcelJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The maximum number of attempts before the job fails.
     */
    public int $tries = 3;

    /**
     * The maximum number of seconds the job may run.
     */
    public int $timeout = 300;

    public function __construct(
        public readonly int $userId,
    ) {}

    public function handle(): void
    {
        $relative = ExportArtifact::path($this->userId, 'tickets_report', 'xlsx');

        Storage::disk(ExportArtifact::DISK)->makeDirectory('exports/'.$this->userId);

        Excel::store(
            new TicketsExport,
            $relative,
            ExportArtifact::DISK,
            \Maatwebsite\Excel\Excel::XLSX,
        );

        Notification::create([
            'user_id' => $this->userId,
            'title' => __('exports.excel_completed'),
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
        Notification::create([
            'user_id' => $this->userId,
            'title' => __('exports.excel_failed'),
            'message' => __('exports.report_failed_generic'),
            'type' => 'system',
            'is_read' => false,
            'link' => null,
        ]);
    }
}
