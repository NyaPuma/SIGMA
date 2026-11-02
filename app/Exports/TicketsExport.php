<?php

namespace App\Exports;

use App\Enums\TicketPriorityEnum;
use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Excel export class for Tickets.
 * Supports dynamic filtering, native currency/date formatting, and chunked reading.
 */
/**
 * @implements \Maatwebsite\Excel\Concerns\WithMapping<\App\Models\Ticket>
 */
final class TicketsExport implements FromQuery, ShouldAutoSize, WithChunkReading, WithColumnFormatting, WithEvents, WithHeadings, WithMapping, WithStyles, WithTitle
{
    /**
     * Allows injecting a custom/filtered query from the Controller.
     */
    /**
     * @param \Illuminate\Database\Eloquent\Builder<\App\Models\Ticket>|null $customQuery
     */
    public function __construct(
        private readonly ?Builder $customQuery = null
    ) {}

    /**
     * Base query for the export.
     */
    /**
     * @return \Illuminate\Database\Eloquent\Builder<\App\Models\Ticket>
     */
    public function query(): Builder
    {
        return ($this->customQuery ?? Ticket::query())
            ->with('status')
            ->select([
                'id',
                'reference',
                'title',
                'status_id',
                'priority',
                'urgent',
                'opened_at',
                'in_progress_at',
                'closed_at',
                'minutes_spent',
                'actual_cost',
                'budget_status',
                'budget_amount',
                'created_at',
            ])
            ->orderBy('created_at', 'desc');
    }

    /**
     * Chunk size for database reading to optimize memory usage.
     */
    public function chunkSize(): int
    {
        return 1000;
    }

    /**
     * Spreadsheet header row.
     */
    /** @return array<int, string> */
    public function headings(): array
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

    /**
     * Maps each Eloquent record to an Excel row.
     *
     * @param  Ticket  $ticket
     */
    /** @return array<int, mixed> */
    public function map(mixed $ticket): array
    {
        /** @var Ticket $ticket */
        $statusName = $ticket->status?->name;
        $statusLabel = is_string($statusName) ? $statusName : 'N/A';

        $priorityLabel = TicketPriorityEnum::normalize($ticket->priority)?->label()
            ?? (string) ($ticket->priority ?? 'N/A');

        return [
            $ticket->id,
            $ticket->reference ?? "#{$ticket->id}",
            $ticket->title,
            $statusLabel,
            $priorityLabel,
            $ticket->urgent ? __('exports.yes') : __('exports.no'),
            $ticket->opened_at?->format('d/m/Y H:i') ?? '-',
            $ticket->in_progress_at?->format('d/m/Y H:i') ?? '-',
            $ticket->closed_at?->format('d/m/Y H:i') ?? '-',
            $ticket->minutes_spent ?? 0,
            (float) ($ticket->actual_cost ?? 0),
            $ticket->budget_status ?? 'N/A',
            (float) ($ticket->budget_amount ?? 0),
        ];
    }

    /**
     * Native column formatting in Excel (enables calculations and sums in cells).
     */
    /** @return array<string, string> */
    public function columnFormats(): array
    {
        return [
            'J' => NumberFormat::FORMAT_NUMBER,
            'K' => '#,##0.00 "€"',
            'M' => '#,##0.00 "€"',
        ];
    }

    /**
     * Excel sheet tab title.
     */
    public function title(): string
    {
        return __('exports.sheet_tickets');
    }

    /**
     * Sheet events: freezes the header, enables autofilter,
     * and applies zebra striping via conditional formatting.
     */
    /** @return array<class-string, callable> */
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet;
                $highestRow = $sheet->getHighestDataRow();
                $range = "A1:M{$highestRow}";

                $sheet->freezePane('A2');
                $sheet->setAutoFilter($range);

                if ($highestRow > 1) {
                    $conditional = new Conditional;
                    $conditional->setConditionType(Conditional::CONDITION_EXPRESSION);
                    $conditional->setOperatorType(Conditional::OPERATOR_EQUAL);
                    $conditional->addCondition('MOD(ROW(),2)=0');
                    $conditional->getStyle()
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->setStartColor(new Color('FFF1F5F9'));

                    $dataRange = $sheet->getStyle("A2:M{$highestRow}");
                    $conditionalStyles = $dataRange->getConditionalStyles();
                    $conditionalStyles[] = $conditional;
                    $dataRange->setConditionalStyles($conditionalStyles);
                }
            },
        ];
    }

    /**
     * Visual styling for the sheet (header row).
     */
    /** @return array<int, array<string, mixed>> */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['argb' => 'FF1E3A5F']],
                'alignment' => ['horizontal' => 'center'],
            ],
        ];
    }
}
