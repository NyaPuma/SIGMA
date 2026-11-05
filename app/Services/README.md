# app/Services

Business logic service layer for the SGM maintenance management platform.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The Department Managers" who handle complex business logic involving multiple steps and decisions.

This document is written at **method level**: for every service class it lists the file, its purpose, its constructor dependencies, and — for every public method — what it does, the real step-by-step logic, and exactly who calls it / when (with `file:line` references).

Services are grouped by functional area. Where a method has no in-production caller its "Who calls it" is stated explicitly so it is not mistaken for dead code.

---

## AI Services

### AIService

- **File:** `app/Services/AIService.php`
- **What it is:** Asks an OpenAI assistant to recommend the best technician for a fault ticket, balancing technical affinity with current team workload.
- **Dependencies (constructor):**
  - `TicketStatusService $statusService` — to resolve the "Closed" and "Cancelled" status IDs (needed to count a technician's active tickets).
  - `?CircuitBreaker $circuitBreaker = null` — optional; if null a fresh instance is created per call (protects against OpenAI outages).
  - `?FeatureFlagService $featureFlags = null` — optional; if null a fresh instance is created. Gates the whole feature on the `ai_recommendations` flag.

#### `recommendTechnician(Ticket $ticket): array{technician_id: int|null, justification: string}`
- **What it does:** Returns an AI-chosen technician ID plus a human-readable justification, or a graceful "no recommendation" when the feature is off, the API fails, the answer is malformed, or the model names a technician that was never offered.
- **How it works / steps:**
  1. Reads the `ai_recommendations` feature flag; if disabled returns `technician_id = null` with a "manually" justification immediately.
  2. Loads all active technicians (profile name = `Technician`) with a sub-query count of their active tickets — tickets whose status is **not** Closed and not Cancelled (`app/Services/AIService.php:40-51`).
  3. If no active technician exists, returns `null` + "no active operational technicians" message.
  4. Builds a detailed prompt: ticket problem description, equipment name, equipment category, and the list of technicians (ID, name, a statically-assigned specialty based on `id % 3`, and current workload) (`:72-96`). While building it, the offered IDs are collected in `$offeredIds` (`:69`, `:82`).
  5. Sends the prompt to OpenAI via the circuit breaker (`$breaker->run('openai', ...)`), reading the model and temperature from `config('services.custom.ai.model' / '.temperature')` (`:99-108`).
  6. Strips possible markdown fences (```` ```json ```` / ```` ``` ````) from the response, `json_decode`s it, and extracts `technician_id` and `justification` (`:114-124`).
  7. Rejects an ID that is not in `$offeredIds`: the model can invent a user who was never on the team, and the value would otherwise be stored and rendered as a valid recommendation (`:126-133`).
  8. Any exception (open circuit, malformed JSON, missing `technician_id`) is caught and turned into `technician_id = null` with a "select manually" message (`:142-147`).
- **Who calls it / when:**
  - `app/Jobs/GenerateAiRecommendationJob.php:63,66` — `handle(AIService $aiService)` calls `recommendTechnician($this->ticket)`. This job is dispatched asynchronously (typically right after a ticket is created) to pre-compute a recommendation in the background.
  - `AIService` is registered as a singleton in `app/Providers/AppServiceProvider.php:79`.
- **Data integrity:** the `$offeredIds` clamp is the last trusted check on the value. `tickets.recommended_technician_id` is also constrained to `users.id` by `database/migrations/2026_11_05_000001_constrain_recommended_technician_to_users.php`, but that constraint only exists on MySQL/PostgreSQL — SQLite ignores a foreign key added to an existing table.

---

### TicketStatusService

- **File:** `app/Services/TicketStatusService.php`
- **What it is:** Caches and resolves the numeric database ID of each ticket status (Open, In Progress, Closed, Cancelled, Pending Budget, Rejected) using its enum name.
- **Dependencies (constructor):** None. It is a stateless service relying on a static in-memory array and the Laravel cache.
- **Note:** Registered as a **singleton** by `app/Providers/AppServiceProvider.php:76`, and injected into `AIService`, `AnalyticsDashboardService`, `TechnicianAssignmentService`, `TicketSearchService`, `TicketWorkflowService`, `ApproveBudgetAction`, `TicketBudgetController`, and the ticket action classes.

#### `getByName(TicketStatusEnum $status): int`
- **What it does:** Returns the DB `id` of the given status enum.
- **How it works / steps:**
  1. Checks the static in-memory `$statusIdCache`; returns immediately if present (`app/Services/TicketStatusService.php:27-29`).
  2. Checks the persistent Laravel cache key `ticket_status:{name}`; if cached, verifies the row still exists, else forgets it (`:32-43`).
  3. Queries `TicketStatus::where('name', $name)`; if missing, `firstOrCreate`s the row with the matching status `code` (ABERTA / EM_CURSO / FECHADA / CANCELADA / PENDENTE_ORCAMENTO / RECUSADA) (`:46-59`).
  4. Stores the ID in both the in-memory and persistent caches (TTL 3600s) and returns it (`:61-64`).
- **Who calls it / when:** Very widely — indirectly via almost every ticket service/action, and directly by:
  - `app/Actions/ApproveBudgetAction.php:31`, `app/Actions/CreatePreventiveTicketAction.php`, `app/Actions/CreatePublicTicketAction.php`, `app/Actions/CreateTicketAction.php`, `app/Actions/ScheduleMaintenanceAction.php`
  - `app/Domain/Ticket/Actions/*` (StartTicketAction, CloseTicketAction, ReopenTicketAction, CancelTicketAction, CheckHigherPriorityAction)
  - `app/Http/Controllers/TicketBudgetController.php:145,175`, `app/Http/Controllers/UiController.php`, `app/Console/Commands/SimulateTelemetry.php`
  - `AIService.php:43-44`, `AnalyticsDashboardService.php:55-57,166`, `TechnicianAssignmentService.php:24,68`, `TicketSearchService.php:43`, `TicketWorkflowService.php:69`, and `app/Models/Ticket.php`.

#### `flush(): void`
- **What it does:** Clears both the static in-memory cache and all persistent `ticket_status:*` keys.
- **How it works / steps:** Resets the static array; iterates `TicketStatusEnum::cases()` forgetting each key; also plumbs every distinct status name from the DB and forgets those keys (`app/Services/TicketStatusService.php:70-84`).
- **Who calls it / when:** **No in-production caller found.** It is a public utility intended to be invoked after statuses change; currently only referenced by tests.

---

## Analytics Services

### AnalyticsService

- **File:** `app/Services/AnalyticsService.php`
- **What it is:** A single front door that delegates analytics dashboard and export work to the two specialist services.
- **Dependencies (constructor):**
  - `AnalyticsDashboardService $dashboardService`
  - `AnalyticsExportService $exportService`
- **Note:** Registered as a **singleton** in `app/Providers/AppServiceProvider.php:77`.

#### `getDashboardPayload(): array<string, mixed>`
- **What it does:** Returns the full analytics dashboard payload.
- **How it works:** Forwards to `$this->dashboardService->getDashboardPayload()` (`app/Services/AnalyticsService.php:19-22`).
- **Who calls it / when:** `app/Http/Controllers/AnalyticsController.php:39` — in a method that returns the dashboard payload (with locale-cache flushing via the TicketObserver).

#### `exportCsv(): void`
- **What it does:** Exports ticket data as CSV to standard output.
- **How it works:** Forwards to `$this->exportService->exportCsv()` (`:27-30`).
- **Who calls it / when:** No direct production caller found; provided as the synchronous stdout variant (the async path calls `exportCsvToFile` instead).

#### `exportCsvToFile(string $path): void`
- **What it does:** Exports ticket data to a CSV file at the given path.
- **How it works:** Forwards to `$this->exportService->exportCsvToFile($path)` (`:35-38`).
- **Who calls it / when:** `app/Jobs/ExportCsvJob.php:47` — `handle()` calls `exportCsvToFile($path)` in the queued background job (dispatched from `AnalyticsController::exportCsv`, `app/Http/Controllers/AnalyticsController.php:55-70`).

#### `exportPdfToFile(string $path): void`
- **What it does:** Exports a PDF report to the given path.
- **How it works:** Forwards to `$this->exportService->exportPdfToFile($path)` (`:43-46`).
- **Who calls it / when:** `app/Jobs/ExportPdfJob.php:47` — `handle()` calls `exportPdfToFile($path)` in the queued background job.

---

### AnalyticsDashboardService

- **File:** `app/Services/AnalyticsDashboardService.php`
- **What it is:** Gathers all the KPIs, charts and breakdowns shown on the analytics dashboard into one cached payload.
- **Dependencies (constructor):**
  - `TicketStatusService $statusService`
- **Note:** Uses domain query classes `TicketKpiQuery`, `TicketPriorityQuery`, `MonthlyTicketsQuery`, `TopEntitiesQuery` from `app/Domain/Ticket/Queries/`.

#### `getDashboardPayload(): array<string, mixed>`
- **What it does:** Returns the complete dashboard payload, cached.
- **How it works / steps:** Wraps `buildPayload()` in `Cache::remember('analytics_dashboard_payload:{locale}', 60, ...)` so the payload is rebuilt at most once per minute per locale (`app/Services/AnalyticsDashboardService.php:39-46`).
- **Who calls it / when:** `app/Http/Controllers/AnalyticsController.php:39` (dashboard view). The cache key is also invalidated by `app/Observers/TicketObserver.php:98`.

*(All other methods below are private helpers of `buildPayload()` and are listed for completeness.)*

- `private buildPayload(): array` — resolves Open/InProgress/Closed IDs, runs the four domain queries, computes `sla_success` as `sla_met / closed_tickets` **or `null` when nothing has been closed** (a period with no closures has no SLA evidence, and 100 % rewarded precisely that), then assembles the entire payload: average resolution/waiting minutes, open/in_progress/waiting_budget/closed counts, system availability, SLA %, priority breakdown, status breakdown, monthly tickets/cost/SLA/MTTR, urgency/room/budget-status/source breakdowns, cost by equipment, stock monthly in/out, low-stock parts, notifications by type, users by role, top equipment/rooms/technicians, and recent activity. The `null` reaches `resources/js/pages/analytics/kpi.js:86`, which renders it as an em dash instead of `0 %`. It delegates to the private helpers:
  - `monthlyPerformanceData(array $monthLabels): array{sla, mttr}` — runs a driver-aware SQL query built through `App\Support\DateSql` (SQLite `strftime`/`julianday`, PostgreSQL `to_char`/`EXTRACT`, MySQL `DATE_FORMAT`/`TIMESTAMPDIFF`) over the last months to compute monthly SLA % and MTTR minutes; a month with no closures is `null`, the same convention as the headline figure (`:154-205`).
  - `urgencyBreakdown(Builder $baseQuery): array` — counts `urgent` true vs false/null tickets (`:213-225`).
  - `roomBreakdown(Builder $baseQuery): array` — counts tickets grouped by room, top 8 by volume (`:233-252`).
  - `budgetBreakdown(Builder $baseQuery): array` — counts by `budget_status` (Pending/Approved/Rejected) (`:260-279`).
  - `sourceBreakdown(Builder $baseQuery): array` — counts by `source` (web/qr/api/mobile/telefone), defaulting empty to `web` (`:287-313`).
  - `costByEquipment(): array` — sums `actual_cost` per equipment, top 8 (`:320-341`).
  - `stockMonthlyData(array $monthLabels): array` — sums stock In/Return vs Out per month, using `StockMovementTypeEnum` (`:349-388`).
  - `lowStockParts(): array` — low-stock parts, top 8 by criticality ratio (`:395-412`).
  - `notificationsByType(): array` — counts notifications per type, top 8, with localized labels (`:419-445`).
  - `usersByRole(): array` — counts users per profile name (`:452-470`).
  - `getRecentActivity(): Collection` — last 6 audits with user and a readable description (`:477-495`).
  - `getAuditDescription(string $event): string` — maps audit event to human text (`:500-508`).

---

### AnalyticsExportService

- **File:** `app/Services/AnalyticsExportService.php`
- **What it is:** Exports ticket data locally as a downloadable CSV file or as a PDF report via DomPDF.
- **Dependencies (constructor):**
  - `?LocalizationService $localization = null` — optional; if null it resolves `app(LocalizationService::class)` lazily used to format dates and decimals with the user's locale.

#### `exportCsv(): void`
- **What it does:** Exports ticket data as CSV to `php://output`.
- **How it works / steps:** Opens `php://output`; writes CSV via `writeCsvRows()`; closes the handle in a `finally` (throws `RuntimeException` if the stream cannot be opened) (`app/Services/AnalyticsExportService.php:70-83`).
- **Who calls it / when:** No direct caller found; the synchronous stdout variant of the export.

#### `exportCsvToFile(string $path): void`
- **What it does:** Exports ticket data to a CSV file at `$path`.
- **How it works / steps:** Creates the parent directory if needed (`ensureDirectoryExists`), opens the file, writes UTF-8 BOM + localized headers + data rows via `writeCsvRows()` (`:90-105`).
- **Who calls it / when:** Via `AnalyticsService::exportCsvToFile` from `app/Jobs/ExportCsvJob.php:47`.

#### `exportPdfToFile(string $path): void`
- **What it does:** Exports a PDF report of the most recent (non-deleted) tickets to `$path`, capped at `config('services.analytics.pdf_export_limit')` (default 2000).
- **How it works / steps:** Ensures the directory; reads the cap (`PDF_RECORD_LIMIT_DEFAULT` guards a non-numeric config); selects ticket fields + `status` with `limit(cap + 1)` so hitting the cap is detectable without counting every row; when the extra row exists the collection is trimmed and `truncated`/`maxRecords` are passed to the Blade template `reports.tickets`, which prints a warning banner on page 1 (`:111-140`). Rendering an unbounded dataset previously exhausted the worker's memory.
- **Who calls it / when:** Via `AnalyticsService::exportPdfToFile` from `app/Jobs/ExportPdfJob.php:47`.

*(Private helpers: `csvHeaders()`, `writeCsvRows()` — chunks 500 tickets at a time —, `csvRow()` — formats each field via `LocalizationService`, `statusLabel()`, `priorityLabel()`, `budgetLabel()`, `ensureDirectoryExists()`, `localization()`.)*

---

## Budget Services

### BudgetCalculatorService

- **File:** `app/Services/BudgetCalculatorService.php`
- **What it is:** Pure, side-effect-free arithmetic that totals the material and labour costs stored in a ticket's `budget_details` JSON.
- **Dependencies (constructor):** None. Stateless.

#### `calculateTotalMaterialCost(Ticket $ticket): float`
- **What it does:** Sums the cost of all `material` items.
- **How it works:** Calls `calculateByType($ticket, 'material')` — for each item where `type == 'material'`, adds `quantity * unit_price` (`app/Services/BudgetCalculatorService.php:14-17,79-97`).
- **Who calls it / when:** No in-production caller found (only tests: `tests/Unit/Services/BudgetCalculatorServiceTest.php`).

#### `calculateTotalLaborCost(Ticket $ticket): float`
- **What it does:** Sums the cost of all `labor` items.
- **How it works:** Calls `calculateByType($ticket, 'labor')` — for each item where `type == 'labor'`, adds `hours * hourly_rate` (`:22-25`).
- **Who calls it / when:** No in-production caller found (only tests).

#### `calculateBudgetTotal(Ticket $ticket): float`
- **What it does:** Grand total = material + labour.
- **How it works:** `calculateTotalMaterialCost($ticket) + calculateTotalLaborCost($ticket)` (`:30-33`).
- **Who calls it / when:** No in-production caller found (only tests).

#### `getBreakdown(Ticket $ticket): array`
- **What it does:** Returns the full breakdown of budget items with subtotals and totals.
- **How it works / steps:** Iterates `$ticket->budget_details`, classifies each item as `labor` (subtotal = `hours * hourly_rate`) or material (subtotal = `quantity * unit_price`), appends the `subtotal` to the item, then returns `materials`, `labor`, `material_total`, `labor_total`, `grand_total` (`:46-74`).
- **Who calls it / when:** No in-production caller found (used by tests `tests/Unit/Services/BudgetCalculatorServiceTest.php` and referenced in `tests/Database/Constraints/BudgetCalculationTest.php`).

---

### BudgetNotificationService

- **File:** `app/Services/BudgetNotificationService.php`
- **What it is:** Sends in-app notifications to admins, the assigned technician, and the ticket creator for budget lifecycle events.
- **Dependencies (constructor):**
  - `NotificationCreatorService $creator`

#### `notifyBudgetSubmitted(Ticket $ticket, string $message): void`
- **What it does:** Notifies admins ("Pending Budget") and the ticket creator ("Budget Submitted"), linked back to the ticket.
- **How it works / steps:** Calls `createForAdmins(...)` with type `BudgetRequest`, then (if the ticket has a `user_id`) `createForUser(...)` for the creator with type `BudgetSubmitted` — both linking `/ui/tickets/{id}` (`app/Services/BudgetNotificationService.php:19-37`).
- **Who calls it / when:** Via `NotificationService::notifyBudgetSubmitted` from `app/Http/Controllers/TicketBudgetController.php:153`.

#### `notifyBudgetAutoApproved(Ticket $ticket, string $message): void`
- **What it does:** Notifies the assigned technician and creator that a budget was auto-approved.
- **How it works / steps:** If `assigned_to` set, notifies the technician; if `user_id` set, notifies the creator — both type `BudgetAutoApproved`, linking the ticket (`:42-63`).
- **Who calls it / when:** Via `NotificationService::notifyBudgetAutoApproved` from `app/Http/Controllers/TicketBudgetController.php:183`.

#### `notifyBudgetDecision(Ticket $ticket, string $decision, string $message): void`
- **What it does:** Notifies the technician and creator that a budget was approved or rejected.
- **How it works / steps:** Picks `BudgetApproved` or `BudgetRejected` based on `$decision === 'approve'`; notifies the assigned technician (title "Budget Approved"/"Budget Rejected") and the creator (title "Budget Decision"), both linking the ticket (`:68-92`).
- **Who calls it / when:** Via `NotificationService::notifyBudgetDecision` from `app/Actions/ApproveBudgetAction.php:69` (inside `notifyDecision()`).

#### `notifyTicketCreated(Ticket $ticket): void`
- **What it does:** Notifies all admins that a new ticket was created.
- **How it works / steps:** `createForAdmins(...)` with type `TicketCreated`, message "New ticket created: {title}", linking the ticket (`:97-105`).
- **Who calls it / when:** Via `NotificationService::notifyTicketCreated`.

---

## Notification Services

### NotificationService

- **File:** `app/Services/NotificationService.php`
- **What it is:** A central dispatcher so callers only remember one service name instead of choosing between budget and ticket notifications.
- **Dependencies (constructor):**
  - `BudgetNotificationService $budgetService`
  - `TicketNotificationService $ticketService`
- **Note:** Registered as a **singleton** in `app/Providers/AppServiceProvider.php:78`.

#### `notifyBudgetSubmitted(Ticket $ticket, string $message): void`
- **What it does:** Forwards to `budgetService->notifyBudgetSubmitted`.
- **Who calls it / when:** Via `app/Http/Controllers/TicketBudgetController.php:153`.

#### `notifyBudgetAutoApproved(Ticket $ticket, string $message): void`
- **What it does:** Forwards to `budgetService->notifyBudgetAutoApproved`.
- **Who calls it / when:** Via `app/Http/Controllers/TicketBudgetController.php:183`.

#### `notifyBudgetDecision(Ticket $ticket, string $decision, string $message): void`
- **What it does:** Forwards to `budgetService->notifyBudgetDecision`.
- **Who calls it / when:** Via `app/Actions/ApproveBudgetAction.php:69`.

#### `notifyTicketClosed(Ticket $ticket, string $message): void`
- **What it does:** Forwards to `ticketService->notifyTicketClosed`.
- **Who calls it / when:** Via `app/Http/Controllers/Ticket/TicketCloseController.php:119`.

#### `notifyPriorityOverride(Ticket $ticket, User $technician, int $urgentCount): void`
- **What it does:** Forwards to `ticketService->notifyPriorityOverride`, passing the technician's name.
- **Who calls it / when:** Via `app/Http/Controllers/Ticket/TicketCloseController.php:110` and `app/Http/Controllers/Ticket/TicketStartController.php:74` (when a technician works a lower-priority ticket while more urgent ones are open).

#### `notifyTicketCreated(Ticket $ticket): void`
- **What it does:** Forwards to `budgetService->notifyTicketCreated`.
- **Who calls it / when:** Via `app/Listeners/SendTicketCreatedNotification.php:36` — the listener attached to the ticket-created event (`TicketCreatedEvent`).

---

### NotificationCreatorService

- **File:** `app/Services/NotificationCreatorService.php`
- **What it is:** Actually persists `Notification` rows, for a single user or bulk for all admins, swallowing/logging DB errors so a failed notification never breaks the caller.
- **Dependencies (constructor):** None.

#### `createForUser(int $userId, string $title, string $message, string $type, string $link): void`
- **What it does:** Inserts one notification for a specific user.
- **How it works / steps:** Wraps `Notification::create(...)` in try/catch; logs a warning on failure (`app/Services/NotificationCreatorService.php:18-34`).
- **Who calls it / when:** `BudgetNotificationService.php` (submitted/auto-approved/decision to creator & technician) and `TicketNotificationService.php:22` (ticket closed).

#### `createForAdmins(string $title, string $message, string $type, string $link): void`
- **What it does:** Inserts a notification for every Administrator for a single entry.
- **How it works / steps:** Delegates to `createForAdminsMany` with a single-entry array (`:39-44`).
- **Who calls it / when:** `BudgetNotificationService.php` (submitted, ticket created), `TicketNotificationService.php:40` (priority override), `app/Http/Controllers/PublicTicketController.php:133`.

#### `createForAdminsMany(array $entries): int`
- **What it does:** Bulk-creates notifications for every admin, one insert per payload entry.
- **How it works / steps:**
  1. Returns 0 on empty input (`:56-58`).
  2. Resolves all admin user IDs once via `whereHas('profile', name = Admin)->pluck('id')` (`:61-70`); returns 0 if none or on error.
  3. For each entry, builds a rows array with `now()` timestamps and issues one `Notification::insert($rows)` per entry (instead of per admin) to reduce queries; counts and returns created rows; failures are logged as warnings (`:79-104`).
- **Who calls it / when:** `createForAdmins` (above) and `app/Services/LowStockAlertService.php:65`.

---

### TicketNotificationService

- **File:** `app/Services/TicketNotificationService.php`
- **What it is:** Sends in-app notifications for ticket lifecycle events (closed, and priority override).
- **Dependencies (constructor):**
  - `NotificationCreatorService $creator`

#### `notifyTicketClosed(Ticket $ticket, string $message): void`
- **What it does:** Notifies the ticket creator that their ticket was closed.
- **How it works / steps:** If the ticket has a `user_id`, `createForUser` with type `TicketClosed`, title "Ticket Closed - #{id}", linking `/ui/tickets/{id}` (`app/Services/TicketNotificationService.php:19-30`).
- **Who calls it / when:** Via `NotificationService::notifyTicketClosed` from `app/Http/Controllers/Ticket/TicketCloseController.php:119`.

#### `notifyPriorityOverride(Ticket $ticket, string $technicianName, int $urgentCount): void`
- **What it does:** Alerts all admins when a technician starts a ticket while ignoring more-urgent open ones.
- **How it works / steps:** Builds a title "Non-Priority Ticket" and a descriptive message naming the technician, ticket, priority and how many more-urgent tickets were ignored; sends via `createForAdmins(...)` with type `PriorityOverride`, linking the ticket (`:35-46`).
- **Who calls it / when:** Via `NotificationService::notifyPriorityOverride` from `app/Http/Controllers/Ticket/TicketCloseController.php:110` and `app/Http/Controllers/Ticket/TicketStartController.php:74`.

---

### LowStockAlertService

- **File:** `app/Services/LowStockAlertService.php`
- **What it is:** Finds parts below their minimum stock and creates in-app low-stock alerts for admins.
- **Dependencies (constructor):**
  - `NotificationCreatorService $notificationCreatorService`

#### `partsInAlert(): array<int, Part>`
- **What it does:** Returns all parts currently in the low-stock alert state, most urgent first.
- **How it works / steps:** Queries parts via the `lowStock()` scope, eager-loads `category` and `taxRate`, and orders by the `current_stock / min_stock` ratio ascending (min_stock of 0 → ratio 0), i.e. lowest ratio (most urgent) first (`app/Services/LowStockAlertService.php:28-36`).
- **Who calls it / when:** `app/Http/Controllers/StockDashboardController.php:44` (to render the "parts in alert" block on the stock dashboard).

#### `notifyAdminsForLowStock(): int`
- **What it does:** Creates one notification per alert part for all admins; returns the number created.
- **How it works / steps:** Calls `partsInAlert()`; returns 0 if none; loads the `LowStock` notifications created within the last `config('services.stock.low_stock_alert_cooldown_hours')` (default 24 h) keyed by `link|message`, skips parts whose alert text is unchanged, builds the remaining entries (localized title "Low Stock", message with part name/current/min, link to part page) and passes them in one batch to `createForAdminsMany()`; returns 0 when every part was already alerted (`:46-92`). A change in the stock level changes the message, so a still-low part re-alerts only once its situation moves.
- **Who calls it / when:** `app/Jobs/CheckLowStockJob.php:25` — the queued `handle(LowStockAlertService $alertService)` that runs on a schedule (so low-stock alerts are not recomputed on every page load).

---

## User & Auth Services

### UserService

- **File:** `app/Services/UserService.php`
- **What it is:** User-account helpers: role listing, default profile assignment, API-token generation/hashing, and building auth/logout responses with cookies.
- **Dependencies (constructor):** None.

#### `getAvailableRoles(): array<int, string>`
- **What it does:** Returns the list of available user role values.
- **How it works:** Delegates to `UserRoleEnum::values()` (`app/Services/UserService.php:21-24`).
- **Who calls it / when:** **No in-production caller found** (the enum is normally enumerated via `User::getAvailableRoles()` in `app/Models/User.php:81` instead).

#### `ensureDefaultProfile(User $user): void`
- **What it does:** Guarantees the user has a default "User" profile assigned.
- **How it works / steps:** Returns early if `profile_id` is already set; otherwise `firstOrCreate` a `UserProfile` named `User` and assigns its id to the user (`:29-38`).
- **Who calls it / when:** `app/Http/Controllers/AuthController.php:68` (during login, before token creation) and `app/Actions/CreateUserAction.php:28` (when creating a new user).

#### `hashToken(string $token): string`
- **What it does:** Returns the HMAC-SHA256 hash of a token using the app key.
- **How it works:** `hash_hmac('sha256', $token, config('app.key'))` (`:43-46`).
- **Who calls it / when:** Internally by `createToken()`. (The same utility is replicated as `User::hashToken()`, used by `app/Http/Middleware/CustomAuthMiddleware.php:88`, `app/Services/AuthUserResolver.php:43`, `app/Http/Controllers/ThemeController.php:107`, `app/Traits/Auditable.php:136`.)

#### `createToken(User $user, Request $request, bool $withSession = true): string`
- **What it does:** Generates, hashes and persists a new API token for the user, stamps the last-login columns, and optionally ties the token to the current session.
- **How it works / steps:**
  1. Reads token length from `config('services.custom.tokens.length', 60)` and generates a random plain string (`:59-61`).
  2. Stores `api_token = hashToken(plain)`, `token_created_at = now()`, `last_login_at = now()` and `last_login_ip = $request->ip()` on the user and saves (`:62-69`). This is the only place a verified credential reaches the database, so it is the only place `last_login_*` can be written — before this they stayed null while `UserResource` served them to the admin API.
  3. If `$withSession` and the request has a session: regenerates the session, stores the **plain** token as `api_token`, and saves the session (`:71-75`). (When `false` — e.g. admin creating a user — the new user's session/cookies are not assumed.)
  4. Loads the `profile` relation and returns the plain token (`:76-78`).
- **Who calls it / when:** `app/Http/Controllers/AuthController.php:78` (login, `$withSession = true`) and `app/Http/Controllers/RegisterController.php:41` (registration, `$withSession = false`).

#### `buildAuthResponse(User $user, string $plainToken, Request $request, int $statusCode = 200): JsonResponse`
- **What it does:** Builds the authentication JSON response with the user/token and sets the `api_token` and `auth_token` cookies.
- **How it works / steps:** Returns a JSON `{user, token}` payload with the given status, plus two HttpOnly, SameSite=Lax cookies (`api_token` and `auth_token`) holding the plain token for 30 days, `Secure` when the request is over HTTPS (`:86-90`).
- **Who calls it / when:** `app/Http/Controllers/AuthController.php:80` (after a successful login).

#### `buildLogoutResponse(Request $request): JsonResponse`
- **What it does:** Invalidates the session and removes both auth cookies.
- **How it works / steps:** Calls `session()->invalidate()` and `session()->regenerateToken()`; returns a success JSON message with expired (`-1` lifetime) `api_token` and `auth_token` cookies (`:94-104`).
- **Who calls it / when:** `app/Http/Controllers/AuthController.php:85` (logout).

---

### PasswordResetService

- **File:** `app/Services/PasswordResetService.php`
- **What it is:** The full password-reset flow: create token, validate token, apply new password.
- **Dependencies (constructor):** None.

#### `createResetToken(string $email): string`
- **What it does:** Creates and stores a secure reset token for an email.
- **How it works / steps:** Normalizes the email (lower/trim); generates a 64-char random token; `updateOrInsert`s into `password_reset_tokens` a row keyed by email with the **hashed** token and `created_at = now()`; returns the plain token (`app/Services/PasswordResetService.php:18-29`).
- **Who calls it / when:** `app/Http/Controllers/PasswordResetController.php:25` (forgot-password flow).

#### `validateToken(string $email, string $token): ?User`
- **What it does:** Validates a reset token against the latest record and its 60-minute expiry; returns the matching user or null.
- **How it works / steps:** Normalizes the email; fetches the latest `password_reset_tokens` row; returns null if absent or `Hash::check` fails; if older than 60 minutes, deletes the record and returns null; else returns the `User` for that email (`:34-58`).
- **Who calls it / when:** `app/Http/Controllers/PasswordResetController.php:46` (when submitting the new password with the token).

#### `resetPassword(User $user, string $password): void`
- **What it does:** Sets a new hashed password, revokes the API token, and removes the reset record.
- **How it works / steps:** Saves `password = Hash::make($password)` and `api_token = null` (logging out all sessions); deletes the `password_reset_tokens` row for the email (`:63-70`).
- **Who calls it / when:** `app/Http/Controllers/PasswordResetController.php:57` (completing the reset).

---

### AuthUserResolver

- **File:** `app/Services/AuthUserResolver.php`
- **What it is:** Resolves the authenticated user from any token present in the request (header, bearer, cookies, session), memoized per request.
- **Dependencies (constructor):** None. All methods are `static`.

#### `static fromRequest(Request $request): ?User`
- **What it does:** Returns the active user, or null.
- **How it works / steps:**
  1. Returns the memoized value from `$request->attributes->get('_auth_user_resolved')` if already set (`app/Services/AuthUserResolver.php:27-30`).
  2. Collects the first non-empty token from: `X-Auth-Token` header, bearer token, `api_token` cookie, `auth_token` cookie, then the session's `api_token` (`:32-38`).
  3. For each token, looks up a user by `User::hashToken($token)`, `active = true`, `deleted_at` null; in `testing` env also retries with the raw token (`:42-53`).
  4. Stores the resolved user on the request and returns it (`:61-63`).
- **Who calls it / when:** `app/Http/Controllers/LocaleController.php:36`, `app/Http/Controllers/PreferencesController.php:58`, `app/Http/Middleware/SetLocaleMiddleware.php:122`, and `app/Services/PreferencesService.php:137`. Its docblock notes it replicates `CustomAuthMiddleware` for contexts where that middleware does not run (e.g. the public language-switch route).

---

## Localization Services

### LocaleService

- **File:** `app/Services/LocaleService.php`
- **What it is:** The central reference for all supported languages and currencies: knows what exists, formats numbers/dates/currency/percent, converts units, and resolves the active language from request/session/browser.
- **Dependencies (constructor):** None. Almost all methods are `static` (a stateless utility service).
- **Source of truth:** `config('locales')` (`languages` list), and currency lists delegated to `PreferencesService`.

#### `static currencySymbol(string $currency): string`
- Returns the plain symbol (€, £, $, …) for an ISO 4217 code, or the code itself if unknown (`app/Services/LocaleService.php:86-89`).

#### `static grouped(): array`
- Returns the languages array from `config('locales.languages')` filtered to arrays (`:96-114`).

#### `static groupedByContinent(): array`
- Groups supported languages by their `continent` metadata (`:139-152`).

#### `static currenciesByContinent(): array`
- Groups supported currencies by continent using a built-in map (`:159-217`).

#### `static supportedCurrencies(): list<string>`
- Delegates to `PreferencesService::supportedCurrencies()` (`:227-230`).

#### `static currencyName(string $currency): string`
- Returns the full localized-looking name of a currency code, or the code (`:235-286`).

#### `static all(): array<string, array>`
- Flat (code => metadata) list of all languages sorted alphabetically by name (with intl Collator when available) (`:293-304`).

#### `static resolveLocale(?string $locale = null): array{code, locale}`
- Maps a short code to the default formatting locale (e.g. `pt` → `pt-PT`) using the `default_locale` metadata (`:335-350`).

#### `static default(): string`
- Returns the system default locale (`pt-PT` unless `config('locales.default')` says otherwise) (`:355-366`).

#### `static isSupported(string $locale): bool`
- True if the exact code exists or its base language prefix exists (`:371-385`).

#### `static meta(string $locale): ?array`
- Returns the language metadata array or null (`:392-395`).

#### `static taxIdentifierLabel(?string $locale = null): string`
- Returns the region-specific tax label (NIF, CNPJ, EIN, SIRET, …) for the locale (`:400-422`).

#### `static indirectTaxLabel(?string $locale = null): string`
- Returns the region-specific indirect-tax label (IVA, VAT, TVA, MwSt, …) (`:427-446`).

#### `static flagEmoji(?string $countryCode): string`
- Converts an ISO-3166 alpha-2 code to its flag emoji, or a neutral flag for invalid input (`:451-463`).

#### `static isRtl(string $locale): bool`
- True if the language metadata marks RTL (`:468-473`).

#### `static currency(?string $locale = null): string`
- Returns the first ISO 4217 currency for the (or current) locale, defaulting to EUR (`:478-484`).

#### `static unitSystem(?string $locale = null): string`
- Returns `metric`/`imperial_us`/`imperial_uk` for the locale (`:489-495`).

#### `static formatNumber(int|float $value, int $decimals = 0, ?string $locale = null): string`
- Formats a number using `intl` `NumberFormatter` when available, else `number_format` with the locale's separators (`:500-522`).

#### `static formatCurrency(int|float $value, ?string $currency = null, ?string $locale = null): string`
- Formats money with `NumberFormatter::CURRENCY`, with a manual symbol placement fallback (`:527-549`).

#### `static formatPercent(int|float $value, int $decimals = 1, ?string $locale = null): string`
- Formats a percentage (value is the actual percent, e.g. 55 → "55%") (`:554-573`).

#### `static formatDate(mixed $value, ?string $locale = null): string`
- Short date formatting (or `d/m/Y` fallback) (`:578-605`).

#### `static formatDateTime(mixed $value, ?string $locale = null): string`
- Short date-time formatting (or `d/m/Y H:i` fallback) (`:610-637`).

#### `static convertUnit(float|int $value, string $type, string $fromUnit = '', ?string $locale = null): array`
- Converts `temperature`/`distance`/`length`/`weight`/`volume` between metric and imperial based on the locale's unit system; returns `{value, unit, formatted}` (`:644-734`).

#### `static sanitize(string $locale): string`
- Normalizes a locale to a supported code, falling back to `default()` if invalid (`:763-789`).

#### `static resolveFromRequest(Request $request): string`
- Resolves the language from session, then cookie, then browser `Accept-Language`, then default — each sanitized (`:794-801`).

#### `static fromBrowser(Request $request): ?string`
- Matches the browser's language list against supported codes/base prefixes (`:806-833`).

#### `static userCurrency(?Request $request = null): string`
- Resolves the active currency from `PreferencesService` for the request, or the auth user's prefs, or `EUR` (`:856-870`).

#### `static userDateFormat(?Request $request = null): string`
- Resolves the active date format similarly, defaulting to `d/m/Y` (`:875-889`).

- **Who calls it / when:** Broadly across the app and specifically:
  - `app/Http/Controllers/LocaleController.php:27,31` (`isSupported`, `sanitize`),
  - `app/Http/Controllers/PreferencesController.php:41` (`all()`),
  - `app/Http/Middleware/SetLocaleMiddleware.php:46,50,53,64,80,83,129,135` (`sanitize`, `resolveFromRequest`, `isSupported`),
  - `app/Http/Requests/StoreSupplierRequest.php` and `UpdateSupplierRequest.php` (validation),
  - and internally by `LocalizationService`, `SystemSettingsService::localeOptions()`, `ThemeController`, and `resources/views/ui/partials/theme-meta.blade.php`.

---

### LocalizationService

- **File:** `app/Services/LocalizationService.php`
- **What it is:** Formats numbers, decimals, currency, dates, datetimes, percent and units honouring the logged-in user's preference profile (resolved via `PreferencesService`, or from `PreferenceContext` inside a queued job), with a locale-based fallback when no explicit choice exists.
- **Dependencies (constructor):** None (stateless; resolves preferences from `PreferenceContext`, then the current request, and uses `app(CurrencyRateService::class)` and `LocaleService` internally). Not bound in the container, so every `app(LocalizationService::class)` is a new instance — which is why the job-scoped preferences live in the `PreferenceContext` singleton instead of here.

#### `formatNumber(float $value, ?string $locale = null): string`
- Formats an integer-style number with the user's separators, or `LocaleService::formatNumber` when no explicit separator choice (`app/Services/LocalizationService.php:27-37`).

#### `formatDecimal(float $value, int $decimals = 2, ?string $locale = null): string`
- Formats a decimal with the user's separators (or locale fallback) (`:39-49`).

#### `formatCurrency(float $value, ?string $locale = null): string`
- Converts the stored EUR value to the user's preferred currency via `CurrencyRateService::convert` when they differ, then formats with the user's separators (or locale), using `LocaleService::currencySymbol`; en-US/en-GB get symbol-prefixed output (`:59-90`).

#### `formatDate(mixed $date, string $format = 'short', ?string $locale = null): string`
- Honors the user's exact `date_format` string when set (e.g. `d-m-Y` stays `dd-mm-yyyy`), else `formatDateFallback()` (`:97-121`).

#### `formatDateTime(mixed $date, ?string $locale = null): string`
- Combines the user's `date_format` + `time_format` (default `H:i`) into one format, else `formatDateTimeFallback()` (`:126-152`).

#### `formatPercent(float $value, ?string $locale = null): string`
- Formats with user separators + `%`, or `LocaleService::formatPercent` (`:143-153`).

#### `convertUnit(float $value, string $type, string $fromUnit = '', ?string $locale = null): array`
- Delegates to `LocaleService::convertUnit` (`:158-161`).

- **Who calls it / when:**
  - `app/Http/Controllers/StockReportController.php:116-119` (`formatDecimal` for cost/price/stock values),
  - `app/Http/Controllers/Ticket/TicketCloseController.php:118` (`formatDecimal` for the close cost),
  - internally by `AnalyticsExportService` (via `localization()`), and registered/injected in `app/Providers/AppServiceProvider.php`.

---

### PreferenceContext

- **File:** `app/Services/PreferenceContext.php`
- **What it is:** Holds the preferences a queued job must format with, because a worker has no request, session or authenticated user for `PreferencesService::current()` to read. Registered as a singleton in `AppServiceProvider::register()` so every `LocalizationService` instance inside the same job sees it.
- **API:** `set(array $preferences)`, `clear()`, `get(): ?array`.
- **Who writes it:** `App\Jobs\Concerns\SetsRequesterContext::applyRequesterContext()`, used by the five export jobs.
- **Who clears it:** `AppServiceProvider::resetRequesterContextForQueuedJobs()`, whose `Queue::before` listener runs before every job — including on the `sync` driver — so one requester's currency cannot leak into the next job.
- **Who reads it:** `LocalizationService::preferences()` (`app/Services/LocalizationService.php:201-226`), which checks this context before falling back to the request and to the hardcoded defaults.

---

### PreferencesService

- **File:** `app/Services/PreferencesService.php`
- **What it is:** Manages a user's language, currency, date/time/number-format preferences — stored in the DB for logged-in users, in the session for guests — with whitelist validation.
- **Dependencies (constructor):** None. All methods are `static`.
- **Supported whitelists:** 33 currencies, 7 date formats, 4 time formats, 8 number formats.

#### `static forUser(Authenticatable $user): array`
- Returns a user's stored preferences, or the defaults if no row, with `users.locale` preferred for `language` (`app/Services/PreferencesService.php:98-104`). The column is read from the loaded model, so no extra query.

#### `static forUserId(int $userId): array`
- Same result for a user id only — the path a queued job uses, where there is no authenticated model, request or guard (`:113-116`). Reads `user_preferences` and `users.locale` (`storedForUserId()` `:121-136`, `withColumnLocale()` `:146-153`).

#### `static fromSession(Request $request): array`
- Returns session-stored preferences (guests), or defaults (`:160-177`).

#### `static current(Request $request): array`
- Resolves the user via `$request->user()` → `Auth::guard('api')->user()` → `AuthUserResolver::fromRequest`; if found uses `forUser`, else `fromSession` (`:205-216`).

#### `static saveForUser(Authenticatable $user, array $preferences): UserPreference`
- Validates then `updateOrCreate`s a `user_preferences` row for the user (`:223-231`).

#### `static saveToSession(Request $request, array $preferences): void`
- Validates then stores preferences in the session (`:238-242`).

#### `static validatePreferences(array $preferences): array`
- Returns a normalized array after validating language, currency, date_format, time_format, number_format (`:250-259`).

#### `static validateLanguage(string $language): string`
- Keeps the language if `LocaleService::isSupported`, else default (`:264-271`).

#### `static validateCurrency(string $currency): string`
- Keeps uppercase currency if in the supported list, else default (`:276-285`).

#### `static validateDateFormat(string $format): string`
- Keeps it if in `SUPPORTED_DATE_FORMATS`, else default (`:290-297`).

#### `static validateTimeFormat(string $format): string`
- Keeps it if in `SUPPORTED_TIME_FORMATS`, else default (`:302-309`).

#### `static supportedTimeFormats(): array`
- Returns the supported time formats map (`:316-319`).

#### `static supportedCurrencies(): list<string>`
- Returns the supported currency list (`:327-330`).

#### `static supportedDateFormats(): array`
- Returns the supported date formats (`:337-340`).

#### `static groupedDateFormats(): array<string, list<string>>`
- Groups date formats by separator symbol (`/`, `-`, `.`, `other`) (`:347-365`).

#### `static getCurrency(Request $request): string`
- Returns `current($request)['currency']` (`:370-373`).

#### `static getDateFormat(Request $request): string`
- Returns `current($request)['date_format']` (`:378-381`).

#### `static getTimeFormat(Request $request): string`
- Returns `current($request)['time_format']` or default (`:386-389`).

#### `static getLanguage(Request $request): string`
- Returns `current($request)['language']` (`:394-397`).

#### `static getNumberFormat(Request $request): string`
- Returns `current($request)['number_format']` or default (`:402-405`).

#### `static getNumberSeparators(Request $request): array`
- JSON-decodes the number format and returns `{decimal, thousand}`, else `{. ,}` (`:412-429`).

#### `static supportedNumberFormats(): array`
- Returns the supported number formats map (`:436-439`).

#### `static groupedNumberFormats(): array`
- Groups numeric formats by decimal separator (`:446-456`).

#### `static formatNumber(Request $request, float $number): string`
- Formats a number with the user's separators, 2 decimals (`:461-471`).

#### `static validateNumberFormat(string $format): string`
- Keeps the format if it decodes to `{decimal, thousand}`, else default (`:476-485`).

- **Who calls it / when:**
  - `app/Http/Controllers/LocaleController.php:41-46` (`saveForUser`, `getCurrency`, `getDateFormat`, `getTimeFormat`, `getNumberFormat`),
  - `app/Http/Controllers/PreferencesController.php:33,40-45,58,68-69,79,84` (`current`, `forUser`, `fromSession`, `saveForUser`, `saveToSession`, `supported*`),
  - `app/Http/Middleware/SetUserPreferencesMiddleware.php:34` (`current`),
  - and internally by `LocalizationService::preferences()` and `LocaleService::userCurrency/userDateFormat`.

---

## Stock Services

### StockDashboardService

- **File:** `app/Services/StockDashboardService.php`
- **What it is:** Stock-module statistics and reports: warehouse value, part counts, low-stock, slow movers, top consumers, cost by equipment/ticket, and stockout forecast.
- **Dependencies (constructor):** None.

#### `totalStockValue(): float`
- **What it does:** Sum of `current_stock * cost_price` for active parts (`app/Services/StockDashboardService.php:22-30`).
- **Who calls it / when:** `app/Http/Controllers/StockDashboardController.php:41`.

#### `totalParts(): int`
- **What it does:** Count of active parts (`:35-38`).
- **Who calls it / when:** `StockDashboardController.php:42`.

#### `lowStockCount(): int`
- **What it does:** Count of parts in the `lowStock()` scope (`:43-46`).
- **Who calls it / when:** `StockDashboardController.php:43`.

#### `slowMovingParts(int $inactiveDays = 90, int $limit = 20): Collection`
- **What it does:** Parts with positive stock and no movement for N days (tied-up capital).
- **How it works / steps:** Active parts, `current_stock > 0`, no movement since the cutoff, eager-loaded `category`, ordered by stock desc, limited (`:53-65`).
- **Who calls it / when:** `StockDashboardController.php:101`.

#### `topConsumed(?string $from = null, ?string $to = null, int $limit = 10): Collection`
- **What it does:** Top parts consumed (outbound) in a period by quantity.
- **How it works / steps:** Uses `consumptionQuery()` ordered by `total_quantity` desc, limited, mapped to `{part_id, part_name, sku, total_quantity, total_value}` (`:72-87`).
- **Who calls it / when:** `StockDashboardController.php:71`.

#### `costByEquipment(?string $from = null, ?string $to = null, int $limit = 20): Collection`
- **What it does:** Cost of consumed parts grouped by equipment.
- **How it works / steps:** `consumptionQuery()` filtered to rows with an `equipment_id`, grouped by equipment, ordered by value desc, mapped to `{equipment_id, equipment_name, total_quantity, total_value}` (`:94-116`).
- **Who calls it / when:** `StockDashboardController.php:129`.

#### `costByTicket(?string $from = null, ?string $to = null, int $limit = 20): Collection`
- **What it does:** Cost of consumed parts grouped by ticket/intervention.
- **How it works / steps:** Same pattern but grouped by ticket (`:123-145`).
- **Who calls it / when:** `StockDashboardController.php:157`.

#### `stockRunoutForecast(int $months = 3, int $limit = 50): Collection`
- **What it does:** Predicts months of stock remaining based on average monthly consumption.
- **How it works / steps:** Computes total outbound quantity per part over the last N months, divides by N for average monthly usage; for each active part with stock and usage, `monthsOfStock = current_stock / avgMonthly`; sorts ascending and limits (`:152-192`).
- **Who calls it / when:** `StockDashboardController.php:188`.

*(Private helper: `consumptionQuery()` — joins parts/equipments/tickets, filters to `Out` and negative-`Adjust` movements with negative quantity, within date range, selects part info and sums `total_quantity` (ABS) and `total_value` (ABS × unit price snapshot), grouped by part.)*

---

### StockMovementService

- **File:** `app/Services/StockMovementService.php`
- **What it is:** Centralizes and atomicizes every change to a part's stock level, enforcing full traceability through `stock_movements`.
- **Dependencies (constructor):** None.

#### `record(Part $part, StockMovementTypeEnum $movementType, int $quantity, ?string $reason = null, ?int $ticketId = null, ?int $equipmentId = null, ?User $user = null, ?float $unitPriceSnapshot = null): StockMovement`
- **What it does:** Records a movement and atomically updates `current_stock`.
- **How it works / steps:**
  1. Throws `InvalidArgumentException` if quantity is zero (`app/Services/StockMovementService.php:40-42`).
  2. Runs inside a DB transaction: locks the part row `lockForUpdate()` to prevent race conditions; throws `RuntimeException` if not found (`:44-49`).
  3. Computes delta via `delta()` — `In`/`Return` → `+abs(q)`, `Out` → `-abs(q)`, `Adjust` → the signed quantity (`:51,85-93`).
  4. Rejects if the new stock would be negative (`:53-59`).
  5. Uses the supplied price snapshot, else the part's `cost_price` (`:61-63`).
  6. Updates `current_stock` and creates the `StockMovement` record (part/ticket/equipment/user, type, quantity, reason, unit price snapshot, `stock_after`) (`:64-78`).
- **Who calls it / when:** `app/Http/Controllers/StockMovementController.php:117` (user-facing receiving/withdrawal/return/adjust actions) and `app/Actions/CreatePartAction.php:43` (creates the initial stock movement when a part is created).

---

## Equipment & Parts Services

### EquipmentService

- **File:** `app/Services/EquipmentService.php`
- **What it is:** Returns a searchable, filterable, paginated list of equipment.
- **Dependencies (constructor):** None.

#### `listPaginated(?string $search = null, ?string $status = null): LengthAwarePaginator`
- **What it does:** Paginated equipment listing (15 per page, by name).
- **How it works / steps:** Loads `room`; wildcard-safe search via `App\Support\SqlPattern::whereContains()` (`LIKE ? ESCAPE '!'`) across `name`, `serial`, `brand`, `model`; optional exact `status` filter; `orderBy('name')->paginate(15)` (`app/Services/EquipmentService.php:18-32`).
- **Who calls it / when:** `app/Http/Controllers/UiController.php:294`.

---

### PartService

- **File:** `app/Services/PartService.php`
- **What it is:** Returns a searchable, filterable, paginated list of spare parts.
- **Dependencies (constructor):** None.

#### `listPaginated(?string $search = null, ?string $status = null, ?int $categoryId = null, int $perPage = 15): LengthAwarePaginator`
- **What it does:** Paginated parts listing with search and filters.
- **How it works / steps:** Loads `category` and `taxRate`; wildcard-safe `LIKE ? ESCAPE '!'` search via `App\Support\SqlPattern` across `name`, `sku`, `brand`, `manufacturer_ref`; applies `lowStock()` if `status == 'low'`, `outOfStock()` if `'out'`; optional `part_category_id` filter; sorted by name and paginated (`app/Services/PartService.php:22-45`).
- **Who calls it / when:** `app/Http/Controllers/PartController.php:54`.

---

### PartPriceCalculator

- **File:** `app/Services/PartPriceCalculator.php`
- **What it is:** Standalone pure calculator producing VAT-inclusive prices, VAT amounts and sale prices from a `Part` and its `TaxRate`. Prices are always computed dynamically, never hardcoded.
- **Dependencies (constructor):** None. Stateless.

#### `priceWithVat(Part $part, ?TaxRate $taxRate = null): float`
- **What it does:** Cost price including VAT: `cost_price * (1 + percent/100)`, rounded to 2 (`app/Services/PartPriceCalculator.php:21-27`).

#### `vatAmount(Part $part, ?TaxRate $taxRate = null): float`
- **What it does:** VAT amount = price-with-VAT minus cost price (`:32-35`).

#### `salePriceWithVat(Part $part, ?TaxRate $taxRate = null): ?float`
- **What it does:** Sale price including VAT, or `null` if the part has no `sale_price` (`:40-50`).

- **Who calls it / when:** **No in-production caller found.** (Only `tests/Unit/Services/PartPriceCalculatorTest.php` and `tests/Unit/Services/StockServicesTest.php`.) Note: the actual production price logic lives in `App\Models\Part::priceWithVat()` (`app/Models/Part.php:117`), which is used by `app/Http/Resources/PartResource.php:25` and `app/Http/Controllers/StockReportController.php:118`; the service class is a separate, currently test-only implementation.

---

## Workflow Services

### TicketWorkflowService

- **File:** `app/Services/TicketWorkflowService.php`
- **What it is:** The ticket lifecycle manager — start, reopen, cancel, close, auto-close, and higher-priority checks — delegating to dedicated "action" classes.
- **Dependencies (constructor):**
  - `TicketStatusService $statusService`
  - `StartTicketAction $startAction`
  - `CloseTicketAction $closeAction`
  - `ReopenTicketAction $reopenAction`
  - `CancelTicketAction $cancelAction`
  - `CheckHigherPriorityAction $checkHigherPriorityAction`
  - (All actions are `app/Domain/Ticket/Actions/*`.)

#### `startRepair(Ticket $ticket, ?User $user = null): bool`
- **What it does:** Moves the ticket into "in progress".
- **How it works:** Delegates to `$this->startAction->execute($ticket, $user)` (`app/Services/TicketWorkflowService.php:31-34`).
- **Who calls it / when:** `app/Http/Controllers/Ticket/TicketStartController.php:70` (and via `app/Actions/AssignTechnicianAction.php` / `app/Http/Controllers/Ticket/TicketAssignmentController.php:56`).

#### `reopen(Ticket $ticket): bool`
- **What it does:** Reopens a previously closed or cancelled ticket.
- **How it works:** Delegates to `$this->reopenAction->execute($ticket)` (`:39-42`).
- **Who calls it / when:** `app/Http/Controllers/Ticket/TicketLifecycleController.php:33`.

#### `cancel(Ticket $ticket): bool`
- **What it does:** Cancels a ticket.
- **How it works:** Delegates to `$this->cancelAction->execute($ticket)` (`:47-50`).
- **Who calls it / when:** `app/Http/Controllers/Ticket/TicketLifecycleController.php:71`.

#### `close(Ticket $ticket, ?float $cost = null, ?string $report = null, ?int $minutesSpent = null): bool`
- **What it does:** Closes a ticket with cost, report, and time-spent data.
- **How it works:** Wraps `$this->closeAction->execute(...)` in a DB transaction so the closure is atomic (`:55-58`).
- **Who calls it / when:** `app/Http/Controllers/Ticket/TicketCloseController.php:45,96`.

#### `checkAutoClose(Ticket $ticket, float $threshold): bool`
- **What it does:** Auto-closes a ticket whose estimated cost is at or below the given threshold.
- **How it works / steps:** Returns `false` if `estimated_cost` is null or above the threshold; otherwise resolves the Closed status ID, sets `status_id`, `closed_at = now()` and `resolved_at` (kept if already set, because the calendar ranges read it), and saves (`:63-80`).
- **Who calls it / when:** **No in-production caller found** (only referenced in tests).

#### `findHigherPriorityTickets(Ticket $ticket): array{total, assigned_to_user, has_higher}`
- **What it does:** Checks whether more urgent open tickets exist in the same context.
- **How it works:** Delegates to `$this->checkHigherPriorityAction->execute($ticket)` (`:83-86`).
- **Who calls it / when:**
  - `app/Http/Controllers/Ticket/TicketStartController.php:43`,
  - `app/Http/Controllers/Ticket/TicketCloseController.php:77,105` (used to decide whether to warn admins via a priority-override notification).

---

### TicketSearchService

- **File:** `app/Services/TicketSearchService.php`
- **What it is:** Executes the searchable, filterable, paginated ticket listing with a `TicketFilters` DTO.
- **Dependencies (constructor):**
  - `TicketStatusService $statusService`

#### `search(TicketFilters $filters): LengthAwarePaginator`
- **What it does:** Returns filtered tickets, newest first, paginated.
- **How it works / steps:**
  1. Loads `equipment`, `room`, `user`, `status`, `technician` (`app/Services/TicketSearchService.php:26`).
  2. Wildcard-safe text search across `title` and `description` via `App\Support\SqlPattern::whereContains()` (`:28-30`).
  3. Filters by `priority` enum value, `status` (normalized then resolved to a numeric ID via `TicketStatusService`), `userId`, `technicianId`, `equipmentId`, `roomId` (`:36-62`).
  4. Throws `InvalidArgumentException` if the date range start is after the end (`:64-66`).
  5. Applies date filters: `whereBetween('created_at', ...)`, `whereDate >=`, or `whereDate <=` accordingly (`:68-72,83-92`).
  6. Uses `config('services.custom.pagination.default_per_page', 15)` and returns `latest()->paginate(...)` (`:74-77`).
- **Who calls it / when:** `app/Http/Controllers/TicketController.php:126`.

---

## UI Services

### ThemePresetService

- **File:** `app/Services/ThemePresetService.php`
- **What it is:** Single source of truth for the 28 visual themes (14 colour families × light/dark), mapping preset colours to CSS tokens and persisting the user's selection.
- **Dependencies (constructor):** None.
- **Constants:** `DEFAULT_THEME = 'claro-laranja'` (applied to guests and users with no saved theme) and `TOKENS` (the 10 CSS variables each preset fills).

#### `all(): array<string, array>`
- **What it does:** Returns the full map of theme id → preset (label, mode, family, and the 10 colours) (`app/Services/ThemePresetService.php:40-464`).
- **Who calls it / when:** `app/Http/Controllers/ThemeController.php:48` (validation of allowed themes) and `app/Http/Controllers/UiController.php:361,375`; also `resources/views/ui/partials/theme-meta.blade.php:27` (embedded as JSON).

#### `find(string $id): ?array`
- **What it does:** Returns a single preset by id (with its `id` injected), or null.
- **How it works / steps:** Lookup in `all()` and adds `preset['id'] = $id` (`:467-478`).
- **Who calls it / when:** Internally by many methods. No direct external callers found outside the service.

#### `paired(string $id): ?array`
- **What it does:** Returns the opposite-mode (light ↔ dark) theme of the same family.
- **How it works / steps:** Finds the preset; scans `all()` for a candidate with the same `family` and different `mode`; returns it or null (`:484-499`).
- **Who calls it / when:** **No in-production caller found**; a public utility for the light/dark toggle.

#### `valuesFor(string $id): array`
- **What it does:** Maps a preset's colours to their CSS tokens (`--color-primary`, etc.).
- **How it works / steps:** For each `TOKENS` entry, outputs `token => preset[field]`; empty array if the id is unknown (`:506-521`).
- **Who calls it / when:** `app/Http/Controllers/ThemeController.php:23,57,136` and `app/Http/Controllers/UiController.php` theme rendering.

#### `effectiveThemeId(?string $preference = null): string`
- **What it does:** Resolves the effective theme id from a user preference, falling back to the default.
- **How it works / steps:** Returns the preference if it maps to a known preset, else `DEFAULT_THEME` (`:529-536`).
- **Who calls it / when:** `app/Http/Controllers/ThemeController.php:66` and `resources/views/ui/partials/theme-meta.blade.php:13`.

#### `active(?string $preference = null): array`
- **What it does:** Returns the active preset for the user (or the default for guests).
- **How it works / steps:** `find(effectiveThemeId($preference))` (`:544-552`).
- **Who calls it / when:** `app/Http/Controllers/UiController.php:362`.

#### `mode(?string $preference = null): string`
- **What it does:** Returns `dark` or `light` for the active theme (`:557-562`).
- **Who calls it / when:** `resources/views/ui/partials/theme-meta.blade.php:17`.

#### `applyForUser(User $user, string $id): array`
- **What it does:** Persists a preset as the user's personal theme.
- **How it works / steps:** Validates the preset (throws on unknown); sets `$user->theme = $id` and saves; returns the preset (`:569-582`).
- **Who calls it / when:** `app/Http/Controllers/ThemeController.php:51` and `app/Http/Controllers/UiController.php:379` (theme-save endpoints).

---

### CalendarService

- **File:** `app/Services/CalendarService.php`
- **What it is:** Provides scheduled maintenance events for the calendar view, scoped to what each user may see.
- **Dependencies (constructor):** None.
- **Constant:** `WINDOW_DAYS = 365` bounds the unbounded date query.

#### `getScheduledEventsForUser(User $user): Collection`
- **What it does:** Returns a formatted list of calendar events for a specific user.
- **How it works / steps:**
  1. Loads tickets with `equipment`, `equipment.category`, `technician` (`app/Services/CalendarService.php:28-29`).
  2. Scopes: technicians see only their assigned tickets (`assigned_to = user.id`); non-admins see only their own (`user_id = user.id`); admins see everything (`:31-35`).
  3. Adds a date window of ±365 days around now matching `scheduled_at`, `opened_at`, or `resolved_at` (`:37-46`).
  4. Maps each ticket to an event (`{id, title, start, end, extendedProps{url, scheduled, equipment, technician, description}, editable}`), where `editable` is true only for admins or the technician assigned to that specific ticket (`:50-72`).
- **Who calls it / when:** `app/Http/Controllers/CalendarController.php:31,42` (both the user-scoped and calendar index endpoints).

#### `getScheduledEvents(?string $from = null, ?string $to = null): Collection`
- **What it does:** Returns global scheduled events within an optional date range.
- **How it works:** Missing bounds default to `now ± WINDOW_DAYS` (365 days) before delegating to `(new ScheduledEventsQuery($from, $to))->execute()` from `app/Domain/Ticket/Queries/` (`:83-89`). Without that defaulting the query would scan every scheduled ticket ever recorded.
- **Who calls it / when:** No in-production caller found besides a potential generic calendar endpoint; the user-facing calendar uses `getScheduledEventsForUser`.

---

### SystemSettingsService

- **File:** `app/Services/SystemSettingsService.php`
- **What it is:** Describes, reads, updates, resets and auto-applies the curated set of system settings shown on the admin settings page.
- **Dependencies (constructor):** None.
- **Note:** Registered as a **singleton** in `app/Providers/AppServiceProvider.php:80`.

#### `groups(): array`
- **What it does:** Returns the ordered configuration groups (app, auth, budget, ai, analytics, pagination, tokens, notification, backup) with their fields (`label`, `type` = text/number/float/select/bool, min/max/step/unit/default/help). Only curated, non-secret keys are exposed (`app/Services/SystemSettingsService.php:41-225`).
- **Who calls it / when:** `app/Http/Controllers/SystemSettingsController.php:26` (render the settings form).

#### `values(): array`
- **What it does:** Returns each setting's effective value.
- **How it works / steps:** Loads DB overrides (`SystemSetting::pluck('value','key')`); for each field uses the override (cast) if present, else the current `config()` value, else the field default (`:236-241`, through `resolveValues()` `:309-319`).
- **Who calls it / when:** `app/Http/Controllers/SystemSettingsController.php:27` (and internally by `reset`).

#### `update(array $updates): array`
- **What it does:** Saves one or more settings (validated/normalized) and applies them to the running config immediately.
- **How it works / steps:** For each key: finds the field (skipping unknown keys); normalizes the value (clamping number/float, casting bool, validating select, trimming text); `updateOrCreate`s a `system_settings` row; calls `config([$key => $value])`; and collects the saved values (`:258-271`).
- **Who calls it / when:** `app/Http/Controllers/SystemSettingsController.php:58` (settings save endpoint).

#### `reset(string $groupId): array`
- **What it does:** Deletes saved overrides for a whole group, restoring config-file values.
- **How it works / steps:** Finds the group (empty if unknown); deletes each stored key for that group; recomputes effective values for the group and returns them (`:284-297`).
- **Who calls it / when:** `app/Http/Controllers/SystemSettingsController.php:45` (reset endpoint).

#### `applyOverrides(): void`
- **What it does:** At application boot, loads all saved settings into `config()` so they take effect.
- **How it works / steps:** Guarded by `Schema::hasTable('system_settings')` (returns silently during install/maintenance); loads all overrides, applies each known field to `config()` (cast), then sets the PHP timezone if `app.timezone` is valid (`:331-356`).
- **Who calls it / when:** `app/Providers/AppServiceProvider.php:97` — `$this->app->make(SystemSettingsService::class)->applyOverrides()` in `boot()`.

*(Private helpers: `findGroup`, `findField`, `cast`, `normalize`, `normalizeSelect`, `localeOptions` (via `LocaleService::all()`), `mailers` (from `config('mail.mailers')`), `timezones` (curated list with UTC first).)*

---

### TechnicianAssignmentService

- **File:** `app/Services/TechnicianAssignmentService.php`
- **What it is:** Assigns a technician to a ticket — specifically, or automatically the least busy available.
- **Dependencies (constructor):**
  - `TicketStatusService $statusService` (to resolve the In-Progress and Open status IDs)

#### `getLeastBusyTechnician(): ?User`
- **What it does:** Finds the active technician with the lowest workload (fewest in-progress tickets).
- **How it works / steps:** Loads active users whose profile name is `Technician`, counts their in-progress (`status_id = InProgress`) assigned tickets, orders by that count asc, returns the first (`app/Services/TechnicianAssignmentService.php:22-31`).
- **Who calls it / when:** Internally by `assignToTicket` (auto-assign branch). No direct external caller.

#### `assignToTicket(Ticket $ticket, ?int $technicianId): ?User`
- **What it does:** Assigns a technician to a ticket, by ID or auto.
- **How it works / steps:** If `technicianId` given, verifies the user exists and `isTechnician()` (else returns null), sets `assigned_to`, saves; otherwise finds `getLeastBusyTechnician()` and assigns it (returns null if none) (`:36-61`).
- **Who calls it / when:**
  - `app/Actions/AssignTechnicianAction.php:22` (the domain action used during ticket creation/assignment),
  - `app/Http/Controllers/Ticket/TicketAssignmentController.php:44` (manual assignment endpoint).

#### `findMostUrgentOpenTicket(?int $excludeId = null): ?Ticket`
- **What it does:** Finds the most urgent open ticket, optionally excluding one.
- **How it works / steps:** Queries open (`status_id = Open`) tickets; builds a `CASE priority` expression from the reversed priority enum so High is first; ties broken by oldest `created_at`; returns the first (`:66-86`).
- **Who calls it / when:** `app/Http/Controllers/TicketController.php:194` — used to warn which other ticket should have been picked first.

---

## Supporting Services

### CircuitBreaker

- **File:** `app/Services/CircuitBreaker.php`
- **What it is:** Protects the app from hammering an unhealthy external dependency (OpenAI, Frankfurter) by opening the circuit after repeated failures and returning a safe fallback.
- **Dependencies (constructor):** None.

#### `run(string $name, Closure $operation, mixed $fallback = null): mixed`
- **What it does:** Executes the operation unless the circuit is open; manages failure counting and cooldown.
- **How it works / steps:**
  1. Reads cached state for `circuit-breaker:{name}` (`app/Services/CircuitBreaker.php:19-30`).
  2. If `open_until > now`, logs a notice and returns the fallback (`:22-30`).
  3. Otherwise runs `$operation()`; on success resets (forgets) the state and returns the result (`:32-36`).
  4. On failure, increments the failure count; if it reaches `config('observability.circuit_breaker.failure_threshold', 3)`, sets `open_until` to now + `cooldown_seconds` (default 60); saves state; logs a warning; returns the fallback (`:37-58`).
  - Fallback resolution: if `$fallback` is a `Closure` it is invoked with the exception, else returned as-is by `resolveFallback()` (`:118-121`).
- **Who calls it / when:** `AIService.php:100` (`run('openai', ...)`) and `CurrencyRateService.php:211` (`run('frankfurter', ...)`).

---

### CurrencyRateService

- **File:** `app/Services/CurrencyRateService.php`
- **What it is:** Fetches EUR-based exchange rates from the Frankfurter provider, stores them in `currency_rates`, and converts an amount between two currencies. Not bound in the container, so each `app(CurrencyRateService::class)` builds a new instance; the rate cache lives in the `Cache` store, not on the object.
- **Dependencies (constructor):**
  - `?CircuitBreaker $circuitBreaker = null` — optional; a fresh instance is created when null. Wraps the HTTP call as the `frankfurter` dependency (`:211`).
  - `?FeatureFlagService $featureFlags = null` — optional; gates the outbound call on `external_currency_rates` (`:204`).

#### `updateRates(): int`
- **What it does:** Fetches the latest rates, upserts one row per target currency against `BASE_CURRENCY` (`EUR`), and returns how many pairs were stored.
- **How it works / steps:** Skips an empty payload; ignores rates that are not `> 0`; `updateOrCreate`s `currency_rates`; forgets both cache directions for the pair (`:91-92`) because a rate that just changed may be cached as a value **or** as the miss sentinel; counts and returns (`:64-100`).
- **Who calls it / when:** `app/Console/Commands/UpdateCurrencyRates.php:21` (artisan `currency:update-rates`), scheduled twice a day by `routes/console.php`.

#### `storedRate(string $from, string $to): ?float`
- **What it does:** Returns the stored rate for one pair, or `null` when nothing is stored for it.
- **How it works / steps:** Upper-cases both codes; returns `1.0` for same-currency (`:111-113`); reads `currency_rate:{FROM}:{TO}` through `Cache::remember()` with the `RATE_CACHE_TTL` of 6 hours (`:50`) and caches a pair that is not stored as the `NO_RATE_CACHE_VALUE` sentinel (`:57`), because `Cache::remember()` treats a stored `null` as a miss and would otherwise re-query the table on every conversion; a non-positive stored rate is also reported as a miss (`queryStoredRate()`, `:127-142`).
- **Who calls it / when:** Internal to the class — `getRate()` (`:157`, `:192`) is the only caller; it is public because it is the read API for a single pair.

#### `getRate(string $from, string $to): float`
- **What it does:** Conversion multiplier (1 FROM = N TO) for a pair, falling back to `1.0` when no stored rate is available, so rendering never fails before the first fetch.
- **How it works / steps:** Same-currency → `1.0`; exact pair stored → that rate; otherwise the cross rate `rate(BASE→TO) / rate(BASE→FROM)` through `baseUnitRate()` (`:186`), and `1.0` when either leg is unknown or non-positive (`:148-171`).
- **Who calls it / when:** `convert()` (`:180`) — no other service resolves this class, so the cross-rate path is reached only through the localization layer below.

#### `convert(float $amount, string $from, string $to): float`
- **What it does:** Returns `$amount * getRate($from, $to)`.
- **Who calls it / when:** `LocalizationService.php:69` (`formatCurrency()` converts the stored EUR value to the user's preferred currency).

---

### FeatureFlagService

- **File:** `app/Services/FeatureFlagService.php`
- **What it is:** Cache-backed on/off switches for features (AI recommendations, external currency rates), with a configuration fallback.
- **Dependencies (constructor):** None.

#### `enabled(string $feature, ?bool $default = null): bool`
- **What it does:** Returns whether a feature is enabled.
- **How it works / steps:** Fallback defaults to `config("features.flags.{$feature}")` (or the passed `$default`, else `false`); reads the cache override `feature-flag:{feature}`; returns the bool override or the fallback; cache errors fall back with a warning (`app/Services/FeatureFlagService.php:18-35`).
- **Who calls it / when:** `AIService.php:33` (`ai_recommendations`) and `CurrencyRateService.php:204` (`external_currency_rates`).

#### `enable(string $feature): void`
- **What it does:** Stores the flag as enabled (forever) (`:37-40`).
- **Who calls it / when:** `app/Console/Commands/FeatureFlagCommand.php:24` (artisan `feature-flag:enable`).

#### `disable(string $feature): void`
- **What it does:** Stores the flag as disabled (forever) (`:42-45`).
- **Who calls it / when:** `FeatureFlagCommand.php:25`.

#### `clear(string $feature): void`
- **What it does:** Forgets the cache override, restoring the config fallback (`:47-57`).
- **Who calls it / when:** `FeatureFlagCommand.php:26`.

---

### QrCodeService

- **File:** `app/Services/QrCodeService.php`
- **What it is:** Generates QR codes for equipment that link to the public ticket creation page.
- **Dependencies (constructor):** None. Uses the Endroid QR-code library (`PngWriter`).

#### `urlFor(Equipment $equipment): string`
- **What it does:** Builds the public ticket-creation URL pre-filled with the equipment id.
- **How it works:** `route('ticket.public.create', ['machine_id' => $equipment->id])` (`app/Services/QrCodeService.php:17-20`).
- **Who calls it / when:** `app/Http/Controllers/QrCodeController.php:28` (returned alongside the data URI) and internally by `png`/`pngDataUri`.

#### `png(Equipment $equipment): string`
- **What it does:** Renders the QR code as PNG binary data (for download).
- **How it works:** `(new PngWriter)->write(new QrCode($urlFor(...)))->getString()` (`:25-30`).
- **Who calls it / when:** `app/Http/Controllers/QrCodeController.php:39` (binary PNG download endpoint).

#### `pngDataUri(Equipment $equipment): string`
- **What it does:** Renders the QR code as a PNG data URI for `<img>`/PDF embedding.
- **How it works:** Same PNG write, `getDataUri()`; throws `RuntimeException` if empty (`:35-46`).
- **Who calls it / when:** `app/Http/Controllers/QrCodeController.php:27` and `app/Jobs/ExportEquipmentQrPdfJob.php:48`.

---

## Conventions

- All docblocks and comments in English
- Static methods preferred for stateless services
- `final` classes only
- Type declarations required on all parameters and return types
- Exception messages in English
