# app/Jobs

Queued jobs for background processing of exports, alerts, AI recommendations, and email delivery.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The Assembly Line" where tasks are queued up to be processed in the background while the user keeps working.

## Overview

This folder contains **8 job classes** that implement `ShouldQueue`, meaning they are pushed onto a Laravel queue worker instead of running synchronously in the HTTP request. This keeps the user-facing API fast by offloading heavy work (PDF generation, AI calls, email sending) to background processes.

All export jobs follow the same pattern:
1. Generate a file through `App\Support\ExportArtifact`, which names it `<slug>-<Ymd_His>-<24 random chars>.<ext>` under `exports/{userId}/` on the **private** (`local`) disk — `storage/app/private/exports/`.
2. Create an `App\Models\Notification` record to tell the user the file is ready, with `link` pointing at the authorized route `/exports/{userId}/{filename}` (`GET /exports/{userId}/{filename}`, name `exports.download`).
3. If the job fails after all retries, create a failure notification instead.

Files are never written to the `public` disk: `Dockerfile` runs `storage:link`, so anything under `storage/app/public` is served by nginx with no authentication, and a clock-only name such as `tickets_report_20260902_143000.csv` was guessable to the minute. The download route aborts `403` unless `{userId}` matches the authenticated user, and `php artisan exports:prune` (scheduled daily at 03:00) enforces `filesystems.exports.retention_days`.

---

## Files

### `CheckLowStockJob.php`

**What it is:** A scheduled-only job that runs daily to check for parts whose stock level has fallen below the minimum threshold and creates in-app notifications for all administrators.

**Class:** `App\Jobs\CheckLowStockJob`

**Dependencies:**
- `App\Services\LowStockAlertService` (injected into `handle()`)

**`handle()` logic:**
1. Calls `$alertService->notifyAdminsForLowStock()` which queries all parts where `current_stock < min_stock`, sorted by criticality (lowest stock-to-min ratio first).
2. For each low-stock part, creates an in-app `Notification` record for every admin user via `NotificationCreatorService::createForAdminsMany()`.
3. Logs the number of notifications created.
4. If the service throws, catches the `Throwable` and logs the error (does **not** re-throw — the job silently succeeds even on failure).

**WHO dispatches it and WHEN:**
- **Scheduler only** — `bootstrap/app.php:53`:
  ```php
  $schedule->job(new CheckLowStockJob)->dailyAt('06:00');
  ```
  This runs every day at 06:00 (server time) via Laravel's task scheduler. It is **never** dispatched from any controller or other code path.

**Queue / retries / timeout:**
- Uses `Queueable` only (does **not** use `Dispatchable` — it is scheduler-dispatched via `new`, not `::dispatch()`).
- `$tries`: not set (defaults to 25 — Laravel's default).
- `$timeout`: not set (defaults to 60 seconds — Laravel's default).

**`failed()` method:** None. Errors are caught internally in `handle()`.

---

### `ExportCsvJob.php`

**What it is:** Generates a CSV file containing all tickets data and creates a download notification for the requesting user.

**Class:** `App\Jobs\ExportCsvJob`

**Dependencies:**
- `App\Services\AnalyticsService` (injected into `handle()`)

**Constructor parameters:**
- `public readonly int $userId` — the ID of the user who requested the export.

**`handle()` logic:**
1. Builds the artifact path with `ExportArtifact::path($userId, 'tickets_report', 'csv')`, e.g. `exports/12/tickets_report-20260902_143000-Kx9fTb7QpZ2mNrW4dVsLpQ8c.csv`, and ensures `exports/{userId}/` exists on the private disk.
2. Calls `$analyticsService->exportCsvToFile($disk->path($relative))` to write the CSV data to disk.
3. Creates an `App\Models\Notification` record for the user with:
   - `title`: `__('exports.csv_completed')` (Portuguese: "Exportação CSV concluída")
   - `message`: `__('exports.file_ready', ['file' => $filename])` (Portuguese: "Ficheiro pronto para download")
   - `type`: `'system'`
   - `link`: `/exports/{userId}/{filename}` (authorized download route; the owner only)

**WHO dispatches it and WHEN:**
- `app/Http/Controllers/AnalyticsController.php:63` — `exportCsv()` method:
  ```php
  ExportCsvJob::dispatch($user->id);
  ```
  Triggered when an authenticated user with `exportAnalytics` permission on `Ticket` calls `GET /analytics/export/csv`.

**Queue / retries / timeout:**
- `$tries = 3`
- `$timeout = 120` (2 minutes)
- Default queue (`$queue` not overridden — uses `config('queue.default')`).

**`failed()` logic:**
- Creates a failure notification for the user:
  - `title`: `__('exports.csv_failed')`
  - `message`: `__('exports.report_failed_generic')`
  - `link`: `null`

---

### `ExportExcelJob.php`

**What it is:** Generates an Excel (.xlsx) file containing all tickets data using the Maatwebsite/Excel package and creates a download notification.

**Class:** `App\Jobs\ExportExcelJob`

**Dependencies:**
- `App\Exports\TicketsExport` — the Maatwebsite export class that defines query, headings, mapping, column formats, and sheet styling.
- `Maatwebsite\Excel\Facades\Excel` — used to store the export on disk.

**Constructor parameters:**
- `public readonly int $userId` — the ID of the user who requested the export.

**`handle()` logic:**
1. Builds the artifact path with `ExportArtifact::path($userId, 'tickets_report', 'xlsx')`.
2. Ensures `exports/{userId}/` exists on the private disk.
3. Calls `Excel::store(new TicketsExport, $relative, ExportArtifact::DISK, Excel::XLSX)` which:
   - Runs `TicketsExport::query()` (all tickets ordered by `created_at DESC`).
   - Maps each ticket to 13 columns (ID, reference, title, status, priority, urgent, opened/in-progress/closed dates, minutes, cost, budget status, budget amount).
   - Applies header styling (bold white on dark blue), zebra striping, auto-filter, and frozen header row.
   - Reads in chunks of 1000 rows for memory efficiency.
4. Creates a success `Notification` record for the user with `type: 'system'` and the download link.

**WHO dispatches it and WHEN:**
- `app/Http/Controllers/AnalyticsController.php:117` — `exportExcel()` method:
  ```php
  ExportExcelJob::dispatch($user->id);
  ```
  Triggered when an authenticated user with `exportAnalytics` permission on `Ticket` calls `GET /analytics/export/excel`.

**Queue / retries / timeout:**
- `$tries = 3`
- `$timeout = 300` (5 minutes — longest of all export jobs due to Excel generation overhead).

**`failed()` logic:**
- Creates a failure notification with `title: __('exports.excel_failed')`, `message: __('exports.report_failed_generic')`, `link: null`.

---

### `ExportPdfJob.php`

**What it is:** Generates a PDF report containing all tickets data via `AnalyticsService` and creates a download notification.

**Class:** `App\Jobs\ExportPdfJob`

**Dependencies:**
- `App\Services\AnalyticsService` (injected into `handle()`)

**Constructor parameters:**
- `public readonly int $userId`

**`handle()` logic:**
1. Builds the artifact path: `exports/{userId}/tickets_report-20260902_143000-<24 random chars>.pdf`.
2. Ensures `exports/{userId}/` exists on the private disk.
3. Calls `$analyticsService->exportPdfToFile($path)` to generate the PDF.
4. Creates a success `Notification` record with the download link.

**WHO dispatches it and WHEN:**
- `app/Http/Controllers/AnalyticsController.php:90` — `exportPdf()` method:
  ```php
  ExportPdfJob::dispatch($user->id);
  ```
  Triggered when an authenticated user with `exportAnalytics` permission on `Ticket` calls `GET /analytics/export/pdf`.

**Queue / retries / timeout:**
- `$tries = 2`
- `$timeout = 180` (3 minutes)

**`failed()` logic:**
- Creates a failure notification with `title: __('exports.pdf_failed')`, `message: __('exports.report_pdf_failed')`, `link: null`.

---

### `ExportEquipmentQrPdfJob.php`

**What it is:** Generates a PDF containing QR codes for all active equipment, where each QR code links to the public ticket-reporting form for that piece of equipment.

**Class:** `App\Jobs\ExportEquipmentQrPdfJob`

**Dependencies:**
- `App\Services\QrCodeService` (injected into `handle()`)
- `Barryvdh\DomPDF\Facade\Pdf` — used to render the Blade view into a PDF.
- `App\Models\Equipment` — queried directly for all active equipment.

**Constructor parameters:**
- `public readonly int $userId`

**`handle()` logic:**
1. Queries all equipment where `active = true`, ordered by name.
2. For each equipment record, generates a PNG data-URI QR code via `$qrCodeService->pngDataUri($equipment)`.
3. Builds the artifact path: `exports/{userId}/qrcodes-equipment-20260902_143000-<24 random chars>.pdf`.
4. Ensures `exports/{userId}/` exists on the private disk.
5. Renders the Blade view `reports.equipments-qr` with the `$items` collection (each item contains `equipment` + `qrDataUri`).
6. Saves the rendered PDF to disk.
7. Creates a success `Notification` record with the download link.

**WHO dispatches it and WHEN:**
- `app/Http/Controllers/QrCodeController.php:52` — `exportPdf()` method:
  ```php
  ExportEquipmentQrPdfJob::dispatch($user->id);
  ```
  Triggered when any authenticated user calls the QR code export endpoint.

**Queue / retries / timeout:**
- `$tries = 2`
- `$timeout = 180` (3 minutes)

**`failed()` logic:**
- Creates a failure notification with `title: __('exports.pdf_failed')`, `message: __('exports.qr_failed')`, `link: null`.

---

### `ExportStockCostsPdfJob.php`

**What it is:** Generates a PDF report showing stock costs grouped by equipment, with optional date range filtering.

**Class:** `App\Jobs\ExportStockCostsPdfJob`

**Dependencies:**
- `App\Services\StockDashboardService` (injected into `handle()`)
- `Barryvdh\DomPDF\Facade\Pdf` — used to render the Blade view into a PDF.

**Constructor parameters:**
- `public readonly int $userId`
- `public readonly ?string $from = null` — optional start date (e.g. `'2026-01-01'`).
- `public readonly ?string $to = null` — optional end date (e.g. `'2026-12-31'`).

**`handle()` logic:**
1. Calls `$dashboardService->costByEquipment(from: $this->from, to: $this->to)` to get aggregated cost data per equipment.
2. Computes `$total = $items->sum('total_value')`.
3. Builds the artifact path: `exports/{userId}/cost-parts-per-equipment-20260902_143000-<24 random chars>.pdf`.
4. Ensures `exports/{userId}/` exists on the private disk.
5. Renders the Blade view `reports.stock-costs-by-equipment` with `$items`, `$total`, `$from`, and `$to`.
6. Saves the rendered PDF to disk.
7. Creates a success `Notification` record with the download link.

**WHO dispatches it and WHEN:**
- `app/Http/Controllers/StockReportController.php:150` — `costsByEquipmentPdf()` method:
  ```php
  ExportStockCostsPdfJob::dispatch(
      userId: $user->id,
      from: $request->query('from'),
      to: $request->query('to'),
  );
  ```
  Triggered when an authenticated user with `viewAny` permission on `Part` calls `GET /stock/reports/costs-by-equipment.pdf` (with optional `from` and `to` query parameters).

**Queue / retries / timeout:**
- `$tries = 2`
- `$timeout = 180` (3 minutes)

**`failed()` logic:**
- Creates a failure notification with `title: __('exports.pdf_failed')`, `message: __('exports.costs_failed')`, `link: null`.

---

### `GenerateAiRecommendationJob.php`

**What it is:** Calls an external AI service to recommend the best technician for a given ticket based on the ticket's details, then persists the recommendation directly on the `Ticket` model.

**Class:** `App\Jobs\GenerateAiRecommendationJob`

**Implements:** `ShouldBeUnique, ShouldQueue` — ensures only one recommendation job runs per ticket at a time.

**Dependencies:**
- `App\Services\AIService` (injected into `handle()`)

**Constructor parameters:**
- `public readonly Ticket $ticket` — the full Eloquent Ticket model (serialized/deserialized by `SerializesModels`).

**Uniqueness:**
- `uniqueId()` returns `(string) $this->ticket->id` — the lock key is the ticket's ID.
- `uniqueFor()` returns `120` — the uniqueness lock persists for 2 minutes after job completion, preventing duplicate AI calls for the same ticket.

**`handle()` logic:**
1. Calls `$aiService->recommendTechnician($this->ticket)` which returns an array with `technician_id` (nullable) and `justification` (string).
2. Updates the `Ticket` model with:
   - `recommended_technician_id` — the AI-suggested technician (or `null`).
   - `ai_recommendation_reason` — the justification text from the AI.
   - `ai_processed_at` — timestamp of when the recommendation was generated.
3. If the update fails, logs a warning (does **not** re-throw — the job succeeds even if persistence fails).

**WHO dispatches it and WHEN:**
- `app/Http/Controllers/TicketController.php:70` — `store()` method:
  ```php
  GenerateAiRecommendationJob::dispatch($ticket)->afterCommit();
  ```
  Dispatched **after the database transaction commits** when a new ticket is created via the authenticated API (`POST /tickets`).

- `app/Http/Controllers/PublicTicketController.php:77` — `store()` method:
  ```php
  GenerateAiRecommendationJob::dispatch($ticket)->afterCommit();
  ```
  Dispatched **after the database transaction commits** when a new public ticket is created via the QR code public form.

**Queue / retries / timeout:**
- `$tries = 3`
- `$backoff = [10, 30, 60]` — exponential backoff: 10s after 1st failure, 30s after 2nd, 60s after 3rd.
- `$timeout = 60` (1 minute — the AI API call must complete within this window).

**`failed()` logic:**
1. Logs the failure via `logger()->error()`.
2. Attempts to update the ticket anyway:
   - Sets `ai_processed_at = now()` so the UI knows the AI was attempted.
   - Sets `ai_recommendation_reason = 'Could not obtain an automatic recommendation at this time.'`.
3. If the update also fails, logs a warning.

---

### `SendTestEmailJob.php`

**What it is:** Sends a test email to a user via the configured mailer, used by the system settings page to verify that email delivery is working correctly.

**Class:** `App\Jobs\SendTestEmailJob`

**Dependencies:**
- `App\Mail\TestMail` — the Mailable class rendered in `handle()`.
- `Illuminate\Support\Facades\Mail` — used to resolve the mailer.

**Constructor parameters:**
- `public readonly string $email` — recipient email address.
- `public readonly string $name` — recipient display name.

**`handle()` logic:**
1. Reads `config('services.custom.notification.mailer')` to get the configured mailer name.
2. If a custom mailer is configured, uses `Mail::mailer($mailerConfig)`; otherwise falls back to `Mail::mailer()` (default mailer).
3. Sends `new TestMail($this->name)` to `$this->email`.
4. Logs success with the email and mailer used.

**WHO dispatches it and WHEN:**
- `app/Http/Controllers/NotificationController.php:97` — `sendTestEmail()` method:
  ```php
  SendTestEmailJob::dispatch($user->email, $user->name);
  ```
  Triggered when an authenticated user calls `POST /notifications/test-email`. The controller immediately returns a response saying the email is being processed via the queue.

**Queue / retries / timeout:**
- `$tries = 3`
- `$backoff = [5, 15, 30]`
- `$timeout = 30` (30 seconds — email sending is typically fast).

**`failed()` logic:**
- Logs the failure with the email address and exception message via `Log::error()`.

---

## Notes for developers / AI

- All export jobs create an `App\Models\Notification` record for the user upon completion/failure. These are **not** Laravel Notification-channel notifications — they are direct `Notification::create()` calls to the custom `notifications` table used by the in-app notification panel.
- User-facing notification strings (title/message) are in Portuguese — managed by the i18n project, not this refactor.
- `GenerateAiRecommendationJob` implements `ShouldBeUnique` with a 2-minute uniqueness window (`uniqueFor() = 120`) and uses `->afterCommit()` to avoid dispatching before the database transaction is committed.
- `CheckLowStockJob` uses `Queueable` only (no `Dispatchable`) as it is scheduler-dispatched via `new CheckLowStockJob` in `bootstrap/app.php`.
- All jobs that generate files write under `exports/{userId}` on the private (`local`) disk through `App\Support\ExportArtifact`, which also guarantees unique names (`makeDirectory` is called before writing). `php artisan exports:prune` (daily, `filesystems.exports.retention_days`) keeps that tree bounded.
- The queue driver is configured via `QUEUE_CONNECTION` in `.env` (defaults to `database` in Laravel).
