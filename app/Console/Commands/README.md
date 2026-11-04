# `app/Console/Commands`

Artisan console commands for database maintenance, data repair, feature-flag overrides, currency updates, export-artifact retention, and telemetry simulation.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The Night Shift Crew" -- automated tasks that run on schedule without human help.

## File table

| File | Captured by schedule? | Signature | Description |
|---|---|---|---|
| `DatabaseBackup.php` | ✔ | `db:backup` (alias `backup:run`) | Native-tool DB backups (mysqldump/sqlite3) with gzip, storage-archiving, off-site upload, retention cleanup |
| `FeatureFlagCommand.php` | ✗ (manual only) | `feature {action} {name}` | Enable/disable/clear a runtime feature-flag cache override |
| `FixTicketEncoding.php` | ✗ (manual/data-repair) | `tickets:fix-encoding` | Byte-precise repair of Mojibake (double-encoded latin1/utf8mb4) ticket text |
| `PartitionAudits.php` | ✔ | `audit:partition` | Creates/drops RANGE partitions on the `audits` table |
| `PruneExports.php` | ✔ | `exports:prune` | Removes generated report artifacts older than the retention window |
| `SimulateTelemetry.php` | ✔ | `telemetry:simulate` | Simulates equipment telemetry and auto-creates preventive tickets on anomalies |
| `UpdateCurrencyRates.php` | ✔ | `currency:update-rates` | Fetches ECB-backed exchange rates into the `currency_rates` table |

All commands extend Laravel's `Illuminate\Console\Command` (or final subclasses of it). Scheduling is defined in `routes/console.php`.

---

## Schedule reference — `routes/console.php`

| Command & options | Cadence | Notes |
|---|---|---|
| `telemetry:simulate --equipments=5 --probability=25` | hourly | `->withoutOverlapping()->runInBackground()->appendOutputTo(storage_path('logs/telemetry.log'))` |
| `backup:run --clean` (alias of `db:backup`) | daily at `02:00` | `->withoutOverlapping()`; `onFailure` logs `backup.scheduled_failure` metric and `Sentry\captureMessage('Scheduled backup failed')`; appends to `storage/logs/backup.log` |
| `exports:prune` | daily at `03:00` | `->withoutOverlapping()`; drops report artifacts older than `filesystems.exports.retention_days`; appends to `storage/logs/exports_prune.log` |
| `audit:partition --months=12` | monthly | `->withoutOverlapping()`; appends to `storage/logs/audit_partitions.log` |
| `currency:update-rates` | twice daily | `->withoutOverlapping()`; appends to `storage/logs/currency_rates.log` |

The scheduler itself requires the host cron entry, documented in `routes/console.php`:
```
* * * * * php /path-to-project/artisan schedule:run >> /dev/null 2>&1
```

---

## `DatabaseBackup.php`

**File:** `app/Console/Commands/DatabaseBackup.php`

- **Signature (real):**
  ```
  db:backup
    {--connection= : The database connection to use}
    {--path= : Custom path for the backups}
    {--no-compress : Skip gzip compression}
    {--offsite : Upload database and storage artifacts to the configured off-site disk}
    {--clean : Remove backups older than the retention period}
  ```
- **Alias:** `backup:run`
- **Description:** "Creates a database backup using native tools (mysqldump/sqlite3)"

### What `handle()` does (step by step)
1. Resolves the connection: `--connection` option, else `config('backup.database.connection')`, else the app default DB connection. Rejects with `FAILURE` if the connection does not exist in `config/database.php`.
2. Resolves the destination dir: `--path` option, else `config('backup.database.destination.path', storage_path('app/backups'))`; creates it (`0755`, recursive, force).
3. Builds a timestamped filename `backup_YYYY-mm-dd_HHMMSS.sql`.
4. `match ($config['driver'])`:
   - `mysql` → `backupMysql()`: runs `mysqldump -h ... -P ... -u ... <db> <--ignore-table=db.table...> --routines --triggers --single-transaction --result-file=<path>`. The password is injected **not on the CLI** but via the `MYSQL_PWD` process env var to avoid exposing it in the process table. Honours `config('backup.database.exclude_tables', [])` for `--ignore-table`. 10-minute process timeout.
   - `sqlite` → `backupSqlite()`: verifies the SQLite file exists, then pipes `sqlite3 <db> .dump > <path>`. 10-minute timeout.
   - otherwise → throws `RuntimeException("Unsupported driver: ...")` → `FAILURE`.
5. If gzip is enabled (`!--no-compress` and `config('backup.database.compression', true)`), compresses with **PHP zlib** (`gzopen(...,'wb9')`, streaming 512KB chunks) → `file.sql.gz`, deleting the raw `.sql`.
6. If `config('backup.storage.enabled', true)`, calls `createStorageArchive()` — zips the application storage dir (`config('backup.storage.path', storage_path('app'))`) into `storage_<timestamp>.zip`, skipping the backup dir itself.
7. If `config('backup.offsite.enabled', false)` **or** `--offsite`, calls `uploadOffsite()` per artifact — streams each file to `config('backup.offsite.disk', 's3')` under `config('backup.offsite.path', 'application-backups')/<basename>` with private visibility.
8. If `--clean`, calls `cleanOldBackups()` — deletes `backup_*` files older than `config('backup.retention.days', 30)` days.
9. On success returns `SUCCESS`; on any `Throwable` logs `backup.failure` metric, `Sentry\captureException`, **deletes all incomplete artifacts**, and returns `FAILURE`.

### Helper methods
| Method | Purpose |
|---|---|
| `backupMysql(array $config, string $filepath): void` | Runs `mysqldump` via `Process` with `MYSQL_PWD` env and exclude-table args |
| `backupSqlite(array $config, string $filepath): void` | Runs `sqlite3 .dump` via `Process` |
| `compressBackup(string $filepath): string` | Gzips a file with PHP zlib, deletes the original, returns the `.gz` path (or the original if compression failed) |
| `cleanOldBackups(string $backupDir): void` | Removes `backup_*` files older than the retention cutoff, reports count |
| `createStorageArchive(string $backupDir, string $timestamp): string` | Zips the app storage dir excluding the backup dir itself |
| `uploadOffsite(string $filepath): void` | Streams an artifact to the configured off-site storage disk, private visibility |

### WHEN it runs
- **Scheduled** daily at `02:00` as `backup:run --clean` (see schedule table).
- **Manual:** `php artisan db:backup --offsite` or `php artisan backup:run`.

---

## `FeatureFlagCommand.php`

**File:** `app/Console/Commands/FeatureFlagCommand.php`

- **Signature (real):**
  ```
  feature {action : The action to perform: enable, disable, or clear} {name : The feature flag name}
  ```
- **Description:** "Manage a runtime feature flag override"

### What `handle()` does
Resolves `App\Services\FeatureFlagService` via dependency injection, then dispatches on the `action` argument:
- `enable` → `$featureFlags->enable($name)` (writes `Cache::forever('feature-flag:<name>', true)`),
- `disable` → `$featureFlags->disable($name)` (writes `Cache::forever('feature-flag:<name>', false)`),
- `clear` → `$featureFlags->clear($name)` (`Cache::forget('feature-flag:<name>')`),
- anything else → prints "Action must be enable, disable, or clear." and returns `self::INVALID`.
For the three valid actions it prints a completion message and returns `SUCCESS`.

### WHEN it runs
- **Manual only** — not present in `routes/console.php`. Used to flip runtime feature flags on the fly (e.g. `php artisan feature enable new_dashboard`). The service itself is also consumed by `app/Services/CurrencyRateService.php:27,192` and `app/Services/AIService.php:19,29`.

---

## `FixTicketEncoding.php`

**File:** `app/Console/Commands/FixTicketEncoding.php`

- **Signature (real):**
  ```
  tickets:fix-encoding {--dry-run : Only lists affected records without altering data}
  ```
- **Description:** "Fixes ticket records with Mojibake (double latin1/utf8mb4 encoding)"

### What `handle()` does
1. **MySQL-only guard:** if the default connection driver is not `mysql`, errors "This command only works on MySQL connections." → `FAILURE`. Also fails if the `tickets` table does not exist.
2. Reads `--dry-run` flag.
3. Iterates the candidate columns: `title`, `description`, `resolution_summary`, `resolution`, `technical_report`, `budget_feedback` (skipping any column absent from the schema).
4. For each column calls `affectedIds($column)`; if any IDs are found, and not in dry-run mode, runs
   `UPDATE tickets SET <col> = CONVERT(CAST(CONVERT(<col> USING latin1) AS BINARY) USING utf8mb4) WHERE id IN (...)`.
5. Reports "No mojibake records", or `[dry-run] N record(s) would be fixed.` / `Fix completed: N record(s) fixed.`.

### Helper methods
| Method | Purpose |
|---|---|
| `affectedIds(string $column): array` | **Byte-precise** mojibake detection to avoid false positives: first narrows candidates with a raw `LIKE BINARY` on encoded bytes `0xC383` ("Ã") or `0xC282` ("Â"), then validates each candidate with the regex `/\xC3\x83[\xC2\x80-\xC2\xBF]|\xC2\x82[\xC2\x80-\xC2\xBF]/`, then reverse-converts `mb_convert_encoding($value, 'ISO-8859-1', 'UTF-8')` and only keeps the ID if the result is valid UTF-8 (`mb_check_encoding`). | 

### WHEN it runs
- **Manual data-repair only** — not scheduled. Use `--dry-run` first to preview, then run without it to apply.

---

## `PartitionAudits.php`

**File:** `app/Console/Commands/PartitionAudits.php`

- **Signature (real):**
  ```
  audit:partition
    {--months=12 : Number of months of old partitions to retain}
    {--months-ahead=3 : Number of future months to create ahead of time}
    {--dry-run : Shows the SQL operations without executing them}
  ```
- **Description:** "Creates and drops real data partitions (ALTER TABLE) on the audits table"

### What `handle()` does
1. **MySQL/MariaDB-only guard:** if the driver is not `mysql`, warns and returns `FAILURE`.
2. Reads `--months-ahead`, `--months`, `--dry-run`.
3. `createFuturePartitions($monthsAhead, $dryRun)` — for `$i = 0..$monthsAhead`, computes an exact month start (`now()->startOfMonth()->addMonths($i)`), naming each partition `p_YYYY_MM`. Skips already-existing partitions; otherwise runs
   `ALTER TABLE audits REORGANIZE PARTITION p_future INTO (PARTITION <p_YYYY_MM> VALUES LESS THAN ('<next-month-start>'), PARTITION p_future VALUES LESS THAN (MAXVALUE))`. The `LESS THAN` boundary is the first day of the **next** month at `00:00:00`. Respects `--dry-run` (prints the SQL without executing).
4. `dropOldPartitions($monthsToKeep, $dryRun)` — builds `cutoffPartitionName = p_<cutoff YYYY_MM>` from `now()->startOfMonth()->subMonths($monthsToKeep)`; for every existing partition matching `^p_(\d{4})_(\d{2})$` whose name sorts strictly before the cutoff, runs `ALTER TABLE audits DROP PARTITION <name>` (or prints it in dry-run).
5. Wraps everything in `try/catch`, returning `FAILURE` on error.
- Helper `getExistingPartitions(): array` queries `information_schema.partitions` for the current DB/table to fetch active partition names.

### WHEN it runs
- **Scheduled monthly** as `audit:partition --months=12` (see schedule table) — keeps 12 months of data, creates ~3 months ahead, prunes older partitions. Also runnable manually with custom `--months` / `--months-ahead`.

---

## `PruneExports.php`

**File:** `app/Console/Commands/PruneExports.php`

- **Signature (real):**
  ```
  exports:prune
    {--days= : Retention window in days (defaults to filesystems.exports.retention_days)}
    {--dry-run : List the files that would be removed without deleting anything}
  ```
- **Description:** "Removes generated export artifacts older than the retention window"

### Why it exists
`ExportCsvJob`, `ExportPdfJob`, `ExportExcelJob`, `ExportEquipmentQrPdfJob` and `ExportStockCostsPdfJob` each write one file per run under `exports/{userId}` on the private disk (see `App\Support\ExportArtifact`). Nothing ever removed them, so the volume grew without a bound even though a report is regenerated on demand.

### What `handle()` does
1. Resolves the window: `--days` when numeric, otherwise `filesystems.exports.retention_days` (default `7`, env `EXPORTS_RETENTION_DAYS`). A value below `1` aborts with `FAILURE` — a typo would otherwise delete every pending report.
2. Lists `allFiles('exports')` (recursive: the artifacts sit one level down, inside the per-user directories) and deletes those whose `lastModified()` predates `now()->subDays($days)`.
3. A per-file delete failure is reported and counted but does not stop the sweep; the exit code becomes `FAILURE` if any happened.
4. `--dry-run` prints `[DRY-RUN] Would remove <path>` and deletes nothing.
5. After a real run, per-user directories left empty are removed.

**Scheduled:** daily at `03:00` with `->withoutOverlapping()`, output appended to `storage/logs/exports_prune.log`.

---

## `SimulateTelemetry.php`

**File:** `app/Console/Commands/SimulateTelemetry.php`

- **Signature (real):**
  ```
  telemetry:simulate
    {--equipments=3 : Maximum number of equipment to check per run}
    {--probability=30 : Probability percentage of an anomaly (0-100)}
    {--dry-run : Runs the simulation without persisting tickets to the database}
  ```
- **Description:** "Simulates equipment telemetry and automatically generates preventive-maintenance tickets when anomalies are detected." (Note: the code docblock and title text also use "preventive"; the singular "preventive" is kept as-is.)

### Data — `$anomalyTypes` (5 hardcoded anomaly presets)
| Title | Priority |
|---|---|
| "Temperature above operational limit" | High |
| "Abnormal vibration detected" | Medium |
| "High energy consumption" | Medium |
| "Pressure outside safety limits" | High |
| "Scheduled preventive maintenance alert" | Low |

### What `handle(TicketStatusService $statusService)` does
1. Reads `--equipments`, `--probability`, `--dry-run`.
2. Locates the **system admin** via `User::whereHas('profile', name = UserRoleEnum::Admin->value)`; fails if none found.
3. Resolves the `Open` status ID once via `$statusService->getByName(TicketStatusEnum::Open)` (avoids N+1).
4. Loads up to `--equipments` **active** equipment in random order, eager-loading `has_open_ticket` (a `withExists` subquery checking `tickets.status_id = openStatusId`) to precompute whether each already has an open ticket.
5. Scrubs the equipments:
   - any already having an open ticket is skipped (prevents duplicate open tickets),
   - any failing the `random_int(1,100) > $probability` test is skipped (no anomaly),
   - otherwise picks a random anomaly from `$anomalyTypes`.
6. In `--dry-run`, prints `[DRY-RUN] Would create Ticket for equip. #id ...` and increments a counter without DB writes.
7. Otherwise, inside a single `DB::transaction`, creates a `Ticket` with: `user_id = systemUser->id`, `equipment_id`, `room_id` (from equipment, nullable), `title = "[TELEMETRIA] <title> — <equipment> "`, `description` (anomaly description + equipment context + timestamp + "Automatically generated by the telemetry system."), `priority` (from the anomaly), `status_id = openStatusId`, `opened_at = now()`.
8. Prints a summary: `Simulation completed. N maintenance ticket(s) generated.` (or `[DRY-RUN] ...`).

### WHEN it runs
- **Scheduled hourly** as `telemetry:simulate --equipments=5 --probability=25` (see schedule table), in background without overlapping, appended to `storage/logs/telemetry.log`. Also runnable manually with custom equipment count / probability and `--dry-run`.

---

## `UpdateCurrencyRates.php`

**File:** `app/Console/Commands/UpdateCurrencyRates.php`

- **Signature (real):** `currency:update-rates`
- **Description:** "Fetches the latest exchange rates and stores them in the database."
- **Class docblock:** pulls rates from the ECB-backed **Frankfurter API** and stores them in the `currency_rates` table.

### What `handle(CurrencyRateService $currencyService)` does
1. Prints "Updating exchange rates...".
2. Calls `$currencyService->updateRates()`.
3. If `$stored === 0` → errors "Could not obtain exchange rates from the provider." and returns `FAILURE`.
4. Otherwise prints `Exchange rates updated: <n> currency pair(s) stored.` and returns `SUCCESS`.

### WHEN it runs
- **Scheduled twice daily** as `currency:update-rates` (see schedule table), without overlapping, appended to `storage/logs/currency_rates.log`. Also runnable manually. Backs per-user currency preferences with reasonably fresh conversion data.

---

## Notes for developers / AI

- All commands extend Laravel's `Illuminate\Console\Command`.
- `DatabaseBackup` supports MySQL and SQLite drivers; others are rejected at runtime with `FAILURE`.
- `PartitionAudits` and `FixTicketEncoding` are MySQL-specific and will fail gracefully on other drivers.
- `SimulateTelemetry` uses `TicketStatusService` to resolve status IDs and creates tickets in a DB transaction, and skips equipment that already has an open ticket to prevent duplicates.
- **Scheduled (4 commands)** via `routes/console.php`: `telemetry:simulate` (hourly), `backup:run --clean` (daily 02:00), `audit:partition --months=12` (monthly), `currency:update-rates` (twice daily). The scheduled subsystem requires the `artisan schedule:run` cron (documented in `routes/console.php`).
- **Manual-only (2 commands):** `feature {action} {name}` and `tickets:fix-encoding` (with `--dry-run` support).
- CLI option descriptions and output strings are user-facing (i18n domain) and remain in Portuguese pending the i18n migration.
- All commands support `--dry-run` where applicable for safe testing (`db:backup --clean`, `tickets:fix-encoding --dry-run`, `audit:partition --dry-run`, `telemetry:simulate --dry-run`).

## Related Folders

| Path | Relationship |
|---|---|
| `app/Services/TicketStatusService` | Status name resolution used by SimulateTelemetry |
| `app/Services/FeatureFlagService` | Cache-backed flag service manipulated by FeatureFlagCommand |
| `app/Services/CurrencyRateService` | Rate fetching used by UpdateCurrencyRates |
| `app/Models/Equipment` | Equipment model queried by SimulateTelemetry |
| `app/Models/Ticket` | Ticket model created by SimulateTelemetry |
| `app/Enums/TicketStatusEnum` | Status enum used by SimulateTelemetry |
| `app/Enums/TicketPriorityEnum` | Priority enum used by SimulateTelemetry's anomaly presets |
| `app/Enums/UserRoleEnum` | Admin role lookup used by SimulateTelemetry |
| `routes/console.php` | Scheduling definitions for the four scheduled commands |