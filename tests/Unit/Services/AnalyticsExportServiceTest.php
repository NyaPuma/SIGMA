<?php

namespace Tests\Unit\Services;

use App\Services\AnalyticsExportService;
use App\Services\TicketStatusService;
use PHPUnit\Framework\Attributes\Test;
use Tests\Base\FeatureTestCase;
use Tests\Concerns\CreatesTickets;

class AnalyticsExportServiceTest extends FeatureTestCase
{
    use CreatesTickets;

    private AnalyticsExportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        app(TicketStatusService::class)->flush();

        $this->service = new AnalyticsExportService;
    }

    #[Test]
    public function it_exports_tickets_to_a_csv_file_with_header_and_rows(): void
    {
        $this->createTicket(['title' => 'Ticket Exportado', 'minutes_spent' => 45, 'actual_cost' => 99.50]);

        $path = storage_path('framework/testing/exports/tickets.csv');

        $this->service->exportCsvToFile($path);

        $this->assertFileExists($path);

        $lines = array_map(fn ($line) => str_getcsv($line, ';'), file($path));

        $this->assertEquals('ID', trim($lines[0][0], "\xEF\xBB\xBF"));
        $this->assertEquals('Código', $lines[0][1]);
        $this->assertEquals('Título', $lines[0][2]);
        $this->assertContains('Ticket Exportado', $lines[1]);
        $this->assertEquals('45', $lines[1][9]);
        $this->assertEquals('99,50', $lines[1][10]);

        unlink($path);
    }

    #[Test]
    public function it_creates_the_parent_directory_when_exporting_to_file(): void
    {
        $path = storage_path('framework/testing/exports/nested/deeper/tickets.csv');

        $this->service->exportCsvToFile($path);

        $this->assertFileExists($path);

        unlink($path);
    }

    #[Test]
    public function it_writes_csv_content_to_standard_output(): void
    {
        $this->createTicket(['title' => 'Ticket Stdout']);

        ob_start();
        $this->service->exportCsv();
        $output = ob_get_clean();

        $this->assertStringContainsString('Ticket Stdout', $output);
        $this->assertStringContainsString('ID;Código;Título', $output);
    }

    #[Test]
    public function it_exports_a_pdf_report_to_file(): void
    {
        $this->createTicket(['title' => 'Ticket PDF']);

        $path = storage_path('framework/testing/exports/report.pdf');

        $this->service->exportPdfToFile($path);

        $this->assertFileExists($path);
        $this->assertGreaterThan(0, filesize($path));
        $this->assertStringStartsWith('%PDF', file_get_contents($path));

        unlink($path);
    }

    #[Test]
    public function it_caps_the_pdf_report_to_the_configured_record_limit(): void
    {
        config(['services.analytics.pdf_export_limit' => 2]);

        $this->createTicket(['title' => 'Ticket um']);
        $this->createTicket(['title' => 'Ticket dois']);
        $this->createTicket(['title' => 'Ticket três']);

        $captured = new \ArrayObject;
        $this->app->instance('dompdf.wrapper', new class($captured)
        {
            public function __construct(private readonly \ArrayObject $captured) {}

            /**
             * @param  array<string, mixed>  $data
             */
            public function loadView(string $view, array $data = []): static
            {
                $this->captured['view'] = $view;
                $this->captured['data'] = $data;

                return $this;
            }

            public function save(string $path): void {}
        });

        $this->service->exportPdfToFile(storage_path('framework/testing/exports/capped.pdf'));

        $this->assertSame('reports.tickets', $captured['view']);
        $this->assertCount(2, $captured['data']['tickets']);
        $this->assertTrue($captured['data']['truncated']);
        $this->assertSame(2, $captured['data']['maxRecords']);
    }

    #[Test]
    public function it_marks_the_pdf_report_as_complete_below_the_limit(): void
    {
        config(['services.analytics.pdf_export_limit' => 5]);
        $this->createTicket(['title' => 'Ticket único']);

        $captured = new \ArrayObject;
        $this->app->instance('dompdf.wrapper', new class($captured)
        {
            public function __construct(private readonly \ArrayObject $captured) {}

            /**
             * @param  array<string, mixed>  $data
             */
            public function loadView(string $view, array $data = []): static
            {
                $this->captured['data'] = $data;

                return $this;
            }

            public function save(string $path): void {}
        });

        $this->service->exportPdfToFile(storage_path('framework/testing/exports/complete.pdf'));

        $this->assertCount(1, $captured['data']['tickets']);
        $this->assertFalse($captured['data']['truncated']);
    }

    #[Test]
    public function it_exports_an_empty_csv_when_there_are_no_tickets(): void
    {
        $path = storage_path('framework/testing/exports/empty.csv');

        $this->service->exportCsvToFile($path);

        $lines = array_filter(array_map(fn ($line) => str_getcsv($line, ';'), file($path)), fn ($line) => ! empty($line));

        $this->assertEquals('ID', trim($lines[array_key_first($lines)][0], "\xEF\xBB\xBF"));

        unlink($path);
    }
}
