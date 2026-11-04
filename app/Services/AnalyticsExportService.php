<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BudgetStatusEnum;
use App\Enums\TicketPriorityEnum;
use App\Enums\TicketStatusEnum;
use App\Models\Ticket;
use App\Support\SpreadsheetCell;
use Barryvdh\DomPDF\Facade\Pdf;
use RuntimeException;

final class AnalyticsExportService
{
    public function __construct(
        private readonly ?LocalizationService $localization = null,
    ) {}

    private const CSV_DELIMITER = ';';

    private const PDF_RECORD_LIMIT_DEFAULT = 2000;

    /**
     * Localized header labels for the ticket analytics CSV.
     *
     * @return list<string>
     */
    private function csvHeaders(): array
    {
        return [
            __('exports.csv_id'),
            __('exports.csv_code'),
            __('exports.csv_title'),
            __('exports.csv_status'),
            __('exports.csv_priority'),
            __('exports.csv_urgent'),
            __('exports.csv_opened'),
            __('exports.csv_in_progress'),
            __('exports.csv_closed'),
            __('exports.csv_duration_min'),
            __('exports.csv_cost'),
            __('exports.csv_budget_status'),
            __('exports.csv_budget_amount'),
        ];
    }

    /** @var list<string> */
    private const PDF_SELECT = [
        'id',
        'reference',
        'title',
        'priority',
        'urgent',
        'status_id',
        'opened_at',
        'in_progress_at',
        'closed_at',
        'minutes_spent',
        'actual_cost',
        'budget_status',
        'budget_amount',
    ];

    /**
     * Exports ticket data directly to standard output (CSV).
     *
     * @throws RuntimeException
     */
    public function exportCsv(): void
    {
        $handle = fopen('php://output', 'w');

        if ($handle === false) {
            throw new RuntimeException('Could not open standard output stream.');
        }

        try {
            $this->writeCsvRows($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Exports ticket data to a CSV file at the specified path.
     *
     * @throws RuntimeException
     */
    public function exportCsvToFile(string $path): void
    {
        $this->ensureDirectoryExists($path);

        $handle = fopen($path, 'w');

        if ($handle === false) {
            throw new RuntimeException("Could not open file for writing: {$path}");
        }

        try {
            $this->writeCsvRows($handle);
        } finally {
            fclose($handle);
        }
    }

    /**
     * Exports the analytics report in PDF format to the specified path.
     */
    public function exportPdfToFile(string $path): void
    {
        $this->ensureDirectoryExists($path);

        $configuredLimit = config('services.analytics.pdf_export_limit', self::PDF_RECORD_LIMIT_DEFAULT);
        $maxRecords = max(1, is_numeric($configuredLimit) ? (int) $configuredLimit : self::PDF_RECORD_LIMIT_DEFAULT);

        // Rendering every ticket into one DomPDF document exhausts the worker's memory on
        // large datasets, so the report is capped and the truncation is printed on page 1.
        // Fetching one extra row reveals the cap was hit without counting every ticket.
        $tickets = Ticket::select(self::PDF_SELECT)
            ->with('status')
            ->whereNull('tickets.deleted_at')
            ->latest()
            ->limit($maxRecords + 1)
            ->get();

        $truncated = $tickets->count() > $maxRecords;

        if ($truncated) {
            $tickets = $tickets->take($maxRecords);
        }

        $pdf = Pdf::loadView('reports.tickets', [
            'tickets' => $tickets,
            'truncated' => $truncated,
            'maxRecords' => $maxRecords,
        ]);
        $pdf->save($path);
    }

    /**
     * Ensures the parent directory of the file path exists.
     *
     * @throws RuntimeException
     */
    private function ensureDirectoryExists(string $path): void
    {
        $dir = dirname($path);

        if (! is_dir($dir) && ! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            throw new RuntimeException("Could not create directory: {$dir}");
        }
    }

    /**
     * Writes CSV header and data rows to the provided file pointer.
     *
     * @param  resource  $handle
     */
    private function writeCsvRows($handle): void
    {
        fwrite($handle, "\xEF\xBB\xBF");

        fputcsv($handle, $this->csvHeaders(), self::CSV_DELIMITER);

        Ticket::select(self::PDF_SELECT)
            ->with('status')
            ->whereNull('tickets.deleted_at')
            ->chunk(500, function ($tickets) use ($handle): void {
                foreach ($tickets as $ticket) {
                    fputcsv($handle, $this->csvRow($ticket), self::CSV_DELIMITER);
                }
            });
    }

    /**
     * Converts a ticket into a readable CSV row.
     *
     * @return list<string>
     */
    private function csvRow(Ticket $ticket): array
    {
        return [
            (string) $ticket->id,
            SpreadsheetCell::guard($ticket->reference ?? '#'.$ticket->id),
            SpreadsheetCell::guard($ticket->title),
            SpreadsheetCell::guard($this->statusLabel($ticket)),
            SpreadsheetCell::guard($this->priorityLabel($ticket->priority)),
            $ticket->urgent ? __('exports.yes') : __('exports.no'),
            $this->localization()->formatDateTime($ticket->opened_at),
            $this->localization()->formatDateTime($ticket->in_progress_at),
            $this->localization()->formatDateTime($ticket->closed_at),
            (string) ($ticket->minutes_spent ?? ''),
            $this->localization()->formatDecimal((float) $ticket->actual_cost),
            SpreadsheetCell::guard($this->budgetLabel($ticket->budget_status)),
            $ticket->budget_amount !== null ? $this->localization()->formatDecimal((float) $ticket->budget_amount) : '',
        ];
    }

    private function statusLabel(Ticket $ticket): string
    {
        $name = $ticket->status?->name;

        return TicketStatusEnum::tryFrom((string) $name)?->label()
            ?? (ucfirst((string) $name) ?: '—');
    }

    private function priorityLabel(?string $priority): string
    {
        return TicketPriorityEnum::tryFrom((string) $priority)?->label()
            ?? ucfirst((string) $priority);
    }

    private function budgetLabel(?string $status): string
    {
        return BudgetStatusEnum::tryFrom((string) $status)?->label() ?? '';
    }

    private function localization(): LocalizationService
    {
        return $this->localization ?? app(LocalizationService::class);
    }
}
