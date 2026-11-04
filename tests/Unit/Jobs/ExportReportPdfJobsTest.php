<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ExportEquipmentQrPdfJob;
use App\Jobs\ExportStockCostsPdfJob;
use App\Services\QrCodeService;
use App\Services\StockDashboardService;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;
use Tests\Concerns\CreatesEquipment;

class ExportReportPdfJobsTest extends FeatureTestCase
{
    use CreatesEquipment;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    #[Test]
    public function export_stock_costs_pdf_job_creates_a_notification_and_file(): void
    {
        $admin = $this->createAdmin();

        (new ExportStockCostsPdfJob($admin->id))->handle(app(StockDashboardService::class));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'title' => 'Exportação PDF concluída',
            'type' => 'system',
        ]);

        $files = Storage::disk('local')->files('exports/'.$admin->id);
        $this->assertNotEmpty($files);
        $this->assertStringEndsWith('.pdf', $files[0]);
    }

    #[Test]
    public function export_stock_costs_pdf_job_accepts_a_date_range(): void
    {
        $admin = $this->createAdmin();

        (new ExportStockCostsPdfJob($admin->id, '2026-01-01', '2026-12-31'))->handle(app(StockDashboardService::class));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'title' => 'Exportação PDF concluída',
        ]);
    }

    #[Test]
    public function export_equipment_qr_pdf_job_creates_a_notification_and_file(): void
    {
        $admin = $this->createAdmin();
        $this->createEquipment(['name' => 'Prensa Hidráulica']);

        (new ExportEquipmentQrPdfJob($admin->id))->handle(app(QrCodeService::class));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'title' => 'Exportação PDF concluída',
            'type' => 'system',
        ]);

        $files = Storage::disk('local')->files('exports/'.$admin->id);
        $this->assertNotEmpty($files);
        $this->assertStringEndsWith('.pdf', $files[0]);
    }

    #[Test]
    public function export_equipment_qr_pdf_job_handles_no_active_equipment(): void
    {
        $admin = $this->createAdmin();

        (new ExportEquipmentQrPdfJob($admin->id))->handle(app(QrCodeService::class));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'title' => 'Exportação PDF concluída',
        ]);
    }

    #[Test]
    public function failed_notifies_the_user(): void
    {
        $admin = $this->createAdmin();

        (new ExportStockCostsPdfJob($admin->id))->failed(new \RuntimeException('DomPDF indisponível'));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'title' => 'Falha na exportação PDF',
            'type' => 'system',
        ]);
    }
}
