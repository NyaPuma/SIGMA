<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Notification;
use App\Services\StockDashboardService;
use App\Support\ExportArtifact;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ExportStockCostsPdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The maximum number of attempts before the job fails.
     */
    public int $tries = 2;

    /**
     * The maximum number of seconds the job may run.
     */
    public int $timeout = 180;

    public function __construct(
        public readonly int $userId,
        public readonly ?string $from = null,
        public readonly ?string $to = null,
    ) {}

    public function handle(StockDashboardService $dashboardService): void
    {
        $items = $dashboardService->costByEquipment(
            from: $this->from,
            to: $this->to,
        );

        $total = $items->sum('total_value');

        $disk = Storage::disk(ExportArtifact::DISK);
        $relative = ExportArtifact::path($this->userId, 'cost-parts-per-equipment', 'pdf');

        $disk->makeDirectory('exports/'.$this->userId);

        $path = $disk->path($relative);

        $pdf = Pdf::loadView('reports.stock-costs-by-equipment', [
            'items' => $items,
            'total' => $total,
            'from' => $this->from,
            'to' => $this->to,
        ]);

        $pdf->save($path);

        Notification::create([
            'user_id' => $this->userId,
            'title' => __('exports.pdf_completed'),
            'message' => __('exports.file_ready', ['file' => ExportArtifact::filename($relative)]),
            'type' => 'system',
            'is_read' => false,
            'link' => ExportArtifact::link($relative),
        ]);
    }

    /**
     * Notifies the user when a failure occurs during PDF rendering.
     */
    public function failed(?Throwable $exception): void
    {
        Notification::create([
            'user_id' => $this->userId,
            'title' => __('exports.pdf_failed'),
            'message' => __('exports.costs_failed'),
            'type' => 'system',
            'is_read' => false,
            'link' => null,
        ]);
    }
}
