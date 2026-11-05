<?php

namespace Tests\Unit\Jobs;

use App\Jobs\ExportCsvJob;
use App\Jobs\ExportExcelJob;
use App\Jobs\ExportPdfJob;
use App\Jobs\SendTestEmailJob;
use App\Mail\TestMail;
use App\Models\Notification;
use App\Services\AnalyticsService;
use App\Services\LocalizationService;
use App\Services\PreferenceContext;
use App\Services\PreferencesService;
use App\Services\TicketStatusService;
use Illuminate\Support\Facades\App;
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
    public function export_job_notifies_the_user_in_their_own_locale(): void
    {
        Storage::fake('local');
        $user = $this->createAdmin(['locale' => 'en-GB']);
        $this->createTicket(['title' => 'Relatório Excel']);

        (new ExportExcelJob($user->id))->handle();

        $this->assertDatabaseHas('notifications', [
            'user_id' => $user->id,
            'title' => 'Excel export completed',
        ]);
    }

    #[Test]
    public function the_locale_an_export_job_applies_does_not_leak_into_the_next_job(): void
    {
        Storage::fake('local');
        $default = config('app.locale');
        $user = $this->createAdmin(['locale' => 'en-GB']);
        $this->createTicket(['title' => 'Relatório Excel']);

        ExportExcelJob::dispatchSync($user->id);
        $this->assertSame('en-GB', App::getLocale());

        Mail::fake();
        SendTestEmailJob::dispatchSync('test@example.com', 'Teste');

        $this->assertSame($default, App::getLocale());
    }

    #[Test]
    public function an_export_job_formats_with_the_preferences_of_the_requester(): void
    {
        Storage::fake('local');
        $user = $this->createAdmin(['locale' => 'en-GB']);
        PreferencesService::saveForUser($user, [
            'language' => 'en-GB',
            'currency' => 'USD',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'number_format' => '{"decimal":",","thousand":"."}',
        ]);
        $this->createTicket(['title' => 'Relatório Excel']);

        ExportExcelJob::dispatchSync($user->id);

        // A worker has no request, session or guard, so without the job storing the
        // requester's row the formatters would fall back to pt-PT defaults here.
        $this->assertSame('2026-01-31', app(LocalizationService::class)->formatDate('2026-01-31'));
        $this->assertSame('1.234,56', app(LocalizationService::class)->formatDecimal(1234.56));
    }

    #[Test]
    public function the_preferences_an_export_job_applies_do_not_leak_into_the_next_job(): void
    {
        Storage::fake('local');
        $user = $this->createAdmin();
        PreferencesService::saveForUser($user, [
            'language' => 'en-GB',
            'currency' => 'USD',
            'date_format' => 'Y-m-d',
            'time_format' => 'H:i',
            'number_format' => '{"decimal":",","thousand":"."}',
        ]);
        $this->createTicket(['title' => 'Relatório Excel']);

        ExportExcelJob::dispatchSync($user->id);
        $this->assertSame('2026-01-31', app(LocalizationService::class)->formatDate('2026-01-31'));

        Mail::fake();
        SendTestEmailJob::dispatchSync('test@example.com', 'Teste');

        $this->assertNull(app(PreferenceContext::class)->get());
        $this->assertSame('31/01/2026', app(LocalizationService::class)->formatDate('2026-01-31'));
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
