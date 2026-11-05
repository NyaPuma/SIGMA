<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Jobs\Concerns\SetsRequesterContext;
use App\Models\Equipment;
use App\Models\Notification;
use App\Services\QrCodeService;
use App\Support\ExportArtifact;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class ExportEquipmentQrPdfJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use SetsRequesterContext;

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
    ) {}

    public function handle(QrCodeService $qrCodeService): void
    {
        $this->applyRequesterContext($this->userId);

        $items = Equipment::query()
            // The template prints room?->name on every card, so the relation is loaded up front
            // instead of one query per equipment.
            ->with('room')
            ->where('active', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Equipment $equipment) => [
                'equipment' => $equipment,
                'qrDataUri' => $qrCodeService->pngDataUri($equipment),
            ]);

        $disk = Storage::disk(ExportArtifact::DISK);
        $relative = ExportArtifact::path($this->userId, 'qrcodes-equipment', 'pdf');

        $disk->makeDirectory('exports/'.$this->userId);

        $path = $disk->path($relative);

        $pdf = Pdf::loadView('reports.equipments-qr', ['items' => $items]);
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
        $this->applyRequesterContext($this->userId);

        Notification::create([
            'user_id' => $this->userId,
            'title' => __('exports.pdf_failed'),
            'message' => __('exports.qr_failed'),
            'type' => 'system',
            'is_read' => false,
            'link' => null,
        ]);
    }
}
