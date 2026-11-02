<?php

namespace App\Http\Controllers;

use App\Jobs\ExportEquipmentQrPdfJob;
use App\Models\Equipment;
use App\Services\QrCodeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

final class QrCodeController extends Controller
{
    public function __construct(
        private readonly QrCodeService $qrCodeService,
    ) {}

    /**
     * Page with a piece of equipment's QR Code (ready to print).
     */
    public function show(Request $request, Equipment $equipment): View
    {
        return view('ui.equipments.qr', [
            'user' => $request->user(),
            'equipment' => $equipment,
            'qrDataUri' => $this->qrCodeService->pngDataUri($equipment),
            'ticketUrl' => $this->qrCodeService->urlFor($equipment),
        ]);
    }

    /**
     * Download a piece of equipment's QR Code as PNG.
     */
    public function download(Equipment $equipment): Response
    {
        $filename = 'qr-'.($equipment->asset_tag ?? $equipment->id).'.png';

        return response($this->qrCodeService->png($equipment), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Dispatches asynchronous generation of QR Codes for all active equipment (PDF).
     */
    public function exportPdf(Request $request): JsonResponse
    {
        $user = $this->authenticatedUser($request);

        ExportEquipmentQrPdfJob::dispatch($user->id);

        return response()->json([
            'message' => __('common.Exportação PDF em processamento. Receberá uma notificação quando estiver pronta.'),
        ]);
    }
}
