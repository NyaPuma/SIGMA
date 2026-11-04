# app/Exports

Excel export classes using Maatwebsite/Excel for generating spreadsheet reports.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The Report Printer" that generates downloadable spreadsheets from system data.

## Overview

This folder contains a single export class (`TicketsExport`) used by the background **Excel export job** to produce a formatted `.xlsx` spreadsheet of tickets. The export is optimized for large datasets (chunked reading), formatted for readability (zebra striping, frozen header, native currency/date formats), and supports an optional injected query for filtered exports.

## Files

### `TicketsExport.php`

**File:** [`./TicketsExport.php`](TicketsExport.php)

**Class:** `App\Exports\TicketsExport` — `final class`

**Implements 9 Maatwebsite/Excel concern interfaces:** `FromQuery`, `ShouldAutoSize`, `WithChunkReading`, `WithColumnFormatting`, `WithEvents`, `WithHeadings`, `WithMapping`, `WithStyles`, `WithTitle`.

**What it exports:** Ticket records (one row per ticket) ordered by `created_at DESC`.

**Constructor:** `__construct(private readonly ?Builder $customQuery = null)` — allows a controller/job to inject a pre-filtered Eloquent `Builder` (e.g., already scoped by a date range or user). When `null`, it defaults to a fresh `Ticket::query()`.

**Source query** (`query()` method):
```php
($this->customQuery ?? Ticket::query())
    ->with('status')
    ->select([
        'id', 'reference', 'title', 'status_id', 'priority', 'urgent',
        'opened_at', 'in_progress_at', 'closed_at',
        'minutes_spent', 'actual_cost', 'budget_status', 'budget_amount', 'created_at',
    ])
    ->orderBy('created_at', 'desc');
```
- Eager-loads the `status` relation (to resolve status display names).
- Only selects the columns needed for the export.

**Chunk size** (`chunkSize(): int`): `1000` records per chunk — keeps memory usage bounded on large datasets.

**Columns / Headings** (`headings(): array` — all localized via `__('exports.*')`):

| Col | Heading (translation key) | Data source (from `map()`) |
|-----|---------------------------|------------------------------|
| A | `csv_id` (ID) | `$ticket->id` |
| B | `csv_code` (Code) | `$ticket->reference ?? "#{$ticket->id}"` |
| C | `csv_title` (Title) | `$ticket->title` |
| D | `csv_status` (Status) | `$ticket->status->name ?? 'N/A'` |
| E | `csv_priority` (Priority) | `TicketPriorityEnum::normalize($ticket->priority)?->label() ?? $ticket->priority` |
| F | `csv_urgent` (Urgent) | `'yes'/'no'` via `__('exports.yes'/'no')` |
| G | `csv_opened` (Opened) | `opened_at` → `d/m/Y H:i` or `-` |
| H | `csv_in_progress` (In progress) | `in_progress_at` → `d/m/Y H:i` or `-` |
| I | `csv_closed` (Closed) | `closed_at` → `d/m/Y H:i` or `-` |
| J | `csv_duration_min` (Duration mins) | `minutes_spent ?? 0` |
| K | `csv_cost` (Cost) | `(float)($actual_cost ?? 0)` |
| L | `csv_budget_status` (Budget status) | `budget_status ?? 'N/A'` |
| M | `csv_budget_amount` (Budget amount) | `(float)($budget_amount ?? 0)` |

**Native column formatting** (`columnFormats(): array`):
- `J` — number format (minutes spent, enables Excel summation).
- `K`, `M` — currency format `#,##0.00 "€"` (enables Excel calculations and sums on cost/budget).

**Sheet title** (`title(): string`): `__('exports.sheet_tickets')` (localized sheet tab name).

**Post-sheet events** (`registerEvents()`, `AfterSheet`):
- Freezes the header row at `A2` (`$sheet->freezePane('A2')`).
- Enables the Excel auto-filter over range `A1:M{lastRow}`.
- Applies **zebra striping** via a conditional-format rule (`MOD(ROW(),2)=0`) that fills alternating rows with a light color `FFF1F5F9` — only when there is more than one data row.

**Styles** (`styles(Worksheet $sheet)`):
- Row 1 (header): bold white font, dark-blue fill (`FF1E3A5F`), center-aligned.

**WHO triggers download / WHEN:**
- **Background job:** `App\Jobs\ExportExcelJob` instantiates `new TicketsExport` and calls `Excel::store(..., $relative, ExportArtifact::DISK, Excel::XLSX)` — `$relative` comes from `App\Support\ExportArtifact::path($userId, 'tickets_report', 'xlsx')`, so the workbook lands on the private disk under `exports/{userId}/` with an unguessable name — generating and storing the file asynchronously, then creating a success/failure notification for the requesting user. Downloading it goes through the authorized `exports.download` route, never `/storage/...`.
- **Controller dispatch:** `AnalyticsController::exportExcel()` at `app/Http/Controllers/AnalyticsController.php:117` dispatches `ExportExcelJob::dispatch($user->id)` (queueable) — triggered when an admin selects "Export Excel" on the analytics/export screen.

**Dependency notes:**
- Uses `App\Models\Ticket` and `App\Enums\TicketPriorityEnum`.
- The priority label comes from `TicketPriorityEnum::normalize(...)->label()` (localized Portuguese).
- Heading labels and sheet title are user-facing Portuguese — managed by the i18n project (`resources/lang/**/exports.php`).
