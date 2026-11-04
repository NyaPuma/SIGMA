<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ExportCsvJob;
use App\Jobs\ExportExcelJob;
use App\Jobs\ExportPdfJob;
use App\Jobs\SendTestEmailJob;
use App\Mail\TestMail;
use App\Models\Notification;
use App\Services\AnalyticsService;
use App\Services\TicketStatusService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;
use Tests\Concerns\CreatesTickets;

class ExportJobsTest extends FeatureTestCase
{
    use CreatesTickets;

    protected function setUp(): void
    {
        parent::setUp();

        app(TicketStatusService::class)->flush();
    }

    #[Test]
    public function export_csv_job_creates_a_notification_for_the_user(): void
    {
        Storage::fake('local');
        $user = $this->createAdmin();
        $this->createTicket(['title' => 'Relatório CSV']);

        (new ExportCsvJob($user->id))->handle(app(AnalyticsService::class));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'title' => 'Exportação CSV concluída',
            'type' => 'system',
        ]);

        $this->assertNotEmpty(Storage::disk('local')->files('exports/'.$user->id));
    }

    #[Test]
    public function export_pdf_job_creates_a_notification_for_the_user(): void
    {
        Storage::fake('local');
        $user = $this->createAdmin();
        $this->createTicket(['title' => 'Relatório PDF']);

        (new ExportPdfJob($user->id))->handle(app(AnalyticsService::class));

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'title' => 'Exportação PDF concluída',
            'type' => 'system',
        ]);

        $this->assertNotEmpty(Storage::disk('local')->files('exports/'.$user->id));
    }

    #[Test]
    public function export_excel_job_creates_a_notification_for_the_user(): void
    {
        Storage::fake('local');
        $user = $this->createAdmin();
        $this->createTicket(['title' => 'Relatório Excel']);

        (new ExportExcelJob($user->id))->handle();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'title' => 'Exportação Excel concluída',
            'type' => 'system',
        ]);

        $files = Storage::disk('local')->files('exports/'.$user->id);
        $this->assertNotEmpty($files);
        $this->assertStringEndsWith('.xlsx', $files[0]);
    }

    #[Test]
    public function export_links_point_to_the_authorized_route_and_not_the_public_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');
        $user = $this->createAdmin();
        $this->createTicket(['title' => 'Relatório CSV']);

        (new ExportCsvJob($user->id))->handle(app(AnalyticsService::class));

        $link = Notification::where('user_id', $user->id)->latest('id')->value('link');

        $this->assertStringStartsWith('/exports/'.$user->id.'/', $link);
        $this->assertStringNotContainsString('/storage/', $link);
        $this->assertFalse(Storage::disk('public')->exists('exports'));
    }

    #[Test]
    public function send_test_email_job_dispatches_the_test_mailable(): void
    {
        Mail::fake();

        (new SendTestEmailJob('test@example.com', 'Teste'))->handle();

        Mail::assertSent(TestMail::class, function (TestMail $mail): bool {
            return $mail->hasTo('test@example.com');
        });
    }
}
