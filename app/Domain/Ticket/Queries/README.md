# app/Domain/Ticket/Queries

Read-only query classes that fetch ticket-related data for dashboards and analytics. Each query is a self-contained, testable unit that accepts its dependencies via constructor.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../../../NON_TECHNICAL_PROJECT_GUIDE.md) -- these are "The Knowledge Specialists" whose only job is to fetch the right data for dashboards and reports without ever changing anything.

## The Big Picture

Think of Queries as **accountants pulling numbers for a monthly report**. They open the filing cabinets, gather the right figures, format them neatly, and hand them over -- but they never move or change any files. This separation keeps your dashboard logic clean and your data safe.

Every Query follows the same pattern:
- It is a small, focused class with a single `execute()` method (or a few named methods for related data).
- It receives everything it needs through its constructor (status IDs, date ranges, base query builders).
- It returns a plain array or Laravel Collection -- no special objects, no side effects.
- It never writes to the database. It only reads.

## Who calls these queries

All five query classes are consumed by **`AnalyticsDashboardService`** (`app/Services/AnalyticsDashboardService.php`) and **`CalendarService`** (`app/Services/CalendarService.php`). These services are in turn consumed by controllers:

```
AnalyticsController::index()
  └─> AnalyticsService::getDashboardPayload()
        └─> AnalyticsDashboardService::buildPayload()
              ├─> TicketKpiQuery::execute()
              ├─> TicketPriorityQuery::execute()
              ├─> MonthlyTicketsQuery::execute()
              └─> TopEntitiesQuery::getTopEquipments() / getTopRooms() / getTopTechnicians()

CalendarController::events()
  └─> CalendarService::getScheduledEvents()
        └─> ScheduledEventsQuery::execute()
```

---

## `MonthlyTicketsQuery.php`

**File:** `app/Domain/Ticket/Queries/MonthlyTicketsQuery.php`
**What it is:** Returns ticket counts (open, in-progress, closed) and total costs, grouped by month, for the last 6 months.

**Dependencies (constructor):**
- `int $openStatusId` -- database ID for the Open status
- `int $inProgressStatusId` -- database ID for the In Progress status
- `int $closedStatusId` -- database ID for the Closed status
- `Carbon $now` -- current timestamp (used to calculate the 6-month window)

**Public methods:**

### `execute(): array`

Returns `array{labels: string[], open: int[], in_progress: int[], closed: int[], cost_data: float[]}`.

1. Calls private `generateMonthKeys()` to produce 6 month keys (e.g., `['2026-04', '2026-05', ..., '2026-09']`). Uses `$this->now->copy()->startOfMonth()->subMonths($offset)->format('Y-m')` for offsets 5 down to 0.
2. Calls private `buildQuery($monthKeys)`:
   - Computes `$startMonth` (5 months ago, start of month) and `$endMonth` (end of current month).
   - Calls private `monthExpression('opened_at')`, which delegates to `App\Support\DateSql::month()` and returns driver-specific SQL: `strftime('%Y-%m', col)` for SQLite, `to_char(col, 'YYYY-MM')` for PostgreSQL, `DATE_FORMAT(col, '%Y-%m')` for MySQL.
   - Runs a single SQL query with `SUM(CASE WHEN status_id = ? THEN 1 ELSE 0 END)` for each status and `SUM(CASE WHEN status_id = ? AND closed_at IS NOT NULL AND actual_cost IS NOT NULL THEN actual_cost ELSE 0 END)` for cost.
   - Filters: `whereNull('tickets.deleted_at')`, `whereNotNull('opened_at')`, `whereBetween('opened_at', ...)`.
   - Groups by the month expression.
   - Keys the result collection by `month`.
3. Calls private `formatResults($monthKeys, $rows)`:
   - Iterates each month key; for missing months, fills with zeros.
   - Returns the structured array with `labels`, `open`, `in_progress`, `closed`, `cost_data`.

**Who calls it and when:**
- `AnalyticsDashboardService::buildPayload()` at `app/Services/AnalyticsDashboardService.php:63`.
- Ultimately triggered by `AnalyticsController::index()` via `AnalyticsService`.
- Tests: `tests/Feature/Domain/TicketQueriesTest.php:51`.

---

## `ScheduledEventsQuery.php`

**File:** `app/Domain/Ticket/Queries/ScheduledEventsQuery.php`
**What it is:** Returns scheduled tickets within an optional date range, formatted for display on a calendar widget (compatible with FullCalendar's ISO 8601 format).

**Dependencies (constructor):**
- `?string $from = null` -- optional start date filter (any key date >= from)
- `?string $to = null` -- optional end date filter (any key date <= to)

**Public methods:**

### `execute(): Collection`

Returns a `Collection` of calendar event arrays.

1. Queries `Ticket::query()` selecting `id`, `title`, `opened_at`, `resolved_at`, `scheduled_at`, `scheduled_end`.
2. Excludes soft-deleted tickets: `whereNull('deleted_at')`.
3. **If `$this->from` is set:** Adds a where-group filtering tickets where *any* of `scheduled_at`, `opened_at`, or `resolved_at` is `>= $from`.
4. **If `$this->to` is set:** Adds a where-group filtering tickets where *any* of `scheduled_at`, `opened_at`, or `resolved_at` is `<= $to`.
5. Maps each ticket to a calendar event array:
   - `id` -- ticket ID.
   - `title` -- format `'#' + id + ' - ' + title`.
   - `start` -- best available date (`scheduled_at ?? opened_at ?? resolved_at`), formatted as ISO 8601.
   - `end` -- `scheduled_end` formatted as ISO 8601, or `null`.
6. Filters out `null` entries (tickets with no usable date) and re-indexes.

**Who calls it and when:**
- `CalendarService::getScheduledEvents()` at `app/Services/CalendarService.php:83`.
- Ultimately triggered by `CalendarController::events()` at `app/Http/Controllers/CalendarController.php:42`.
- Tests: `tests/Feature/Domain/TicketQueriesTest.php:71`, `:89`.

---

## `TicketKpiQuery.php`

**File:** `app/Domain/Ticket/Queries/TicketKpiQuery.php`
**What it is:** Returns key performance indicators (KPIs) for the dashboard: counts of open, in-progress, budget-pending, and closed tickets, plus average resolution time, average waiting time, and SLA compliance rate.

**Dependencies (constructor):**
- `int $openStatusId` -- database ID for the Open status
- `int $inProgressStatusId` -- database ID for the In Progress status
- `int $closedStatusId` -- database ID for the Closed status
- `int $slaTargetMinutes = 480` -- SLA target in minutes (default: 8 hours)

**Public methods:**

### `execute(): array`

Returns `array{open_tickets: int, in_progress_tickets: int, budget_pending_tickets: int, closed_tickets: int, avg_resolution: float, avg_waiting: float, sla_met: int}`.

1. Creates base query: `Ticket::query()->whereNull('tickets.deleted_at')`.
2. Calls private `buildKpiQuery($baseQuery)`:
   - Computes `$diffExpr = $this->diffMinutesExpression('opened_at', 'closed_at')`, which delegates to `App\Support\DateSql::minutesBetween()` for driver-specific minute arithmetic:
     - SQLite: `(julianday(closed_at) - julianday(opened_at)) * 1440`
     - PostgreSQL: `EXTRACT(EPOCH FROM closed_at - opened_at) / 60`
     - MySQL: `TIMESTAMPDIFF(MINUTE, opened_at, closed_at)`
   - Computes `$diffWaitExpr = $this->diffMinutesExpression('opened_at', 'assigned_at')`.
   - Builds a single raw SQL with:
     - `SUM(CASE WHEN status_id = ? THEN 1 ELSE 0 END)` for open, in-progress counts.
     - `SUM(CASE WHEN budget_status = ? THEN 1 ELSE 0 END)` for budget-pending count.
     - `SUM(CASE WHEN status_id = ? AND opened_at IS NOT NULL AND closed_at IS NOT NULL THEN 1 ELSE 0 END)` for closed count.
     - `AVG(CASE WHEN ... THEN diffExpr END)` for average resolution time.
     - `AVG(CASE WHEN ... THEN diffWaitExpr END)` for average waiting time.
     - `SUM(CASE WHEN ... AND diffExpr <= ? THEN 1 ELSE 0 END)` for SLA met count.
   - Passes all status IDs and `slaTargetMinutes` as bound parameters.
3. Executes the query and casts all results to the appropriate types.

**Who calls it and when:**
- `AnalyticsDashboardService::buildPayload()` at `app/Services/AnalyticsDashboardService.php:61`.
- Ultimately triggered by `AnalyticsController::index()` via `AnalyticsService`.
- Tests: `tests/Feature/Domain/TicketQueriesTest.php:118`.

---

## `TicketPriorityQuery.php`

**File:** `app/Domain/Ticket/Queries/TicketPriorityQuery.php`
**What it is:** Returns ticket counts grouped by priority level -- how many tickets are Low, Medium, or High priority.

**Dependencies (constructor):**
- `Builder $baseQuery` -- a pre-built Eloquent query builder (allows the caller to apply filters before passing)

**Public methods:**

### `execute(): array`

Returns `array{low: int, medium: int, high: int}`.

1. **Clones** `$this->baseQuery` to avoid mutating the original.
2. Runs a single SQL query with `SUM(CASE WHEN priority = ? THEN 1 ELSE 0 END)` for each priority level.
   - `Low` maps to `TicketPriorityEnum::Low->value`.
   - `Medium` maps to `TicketPriorityEnum::Medium->value`.
   - `High` combines both `TicketPriorityEnum::High->value` and `TicketPriorityEnum::Critical->value` into a single count using `IN (?, ?)`.
3. Executes and casts to `int`.

**Who calls it and when:**
- `AnalyticsDashboardService::buildPayload()` at `app/Services/AnalyticsDashboardService.php:62` -- receives `$baseQuery = Ticket::query()->whereNull('tickets.deleted_at')`.
- Ultimately triggered by `AnalyticsController::index()` via `AnalyticsService`.
- Tests: `tests/Feature/Domain/TicketQueriesTest.php:140`.

---

## `TopEntitiesQuery.php`

**File:** `app/Domain/Ticket/Queries/TopEntitiesQuery.php`
**What it is:** Returns the top 5 equipment items, rooms, and technicians by ticket count -- used for the "Top Entities" widgets on the dashboard.

**Dependencies (constructor):**
- `Builder $baseQuery` -- a pre-built Eloquent query builder

**Public methods:**

### `getTopEquipments(): Collection`

Returns `Collection<int, array{name: string, total: int, subtitle: 'interventions'}>`.

1. **Clones** the base query.
2. Joins `equipments` on `tickets.equipment_id = equipments.id`.
3. Selects `equipments.id`, `equipments.name`, `COUNT(*) as total`.
4. Filters: `whereNotNull('tickets.equipment_id')`.
5. Groups by `equipments.id`, `equipments.name`.
6. Orders by `total` descending, limits to 5.
7. Maps each row to `['name' => ..., 'total' => ..., 'subtitle' => 'interventions']`.

### `getTopRooms(): Collection`

Returns `Collection<int, array{name: string, total: int, subtitle: 'tickets'}>`.

1. **Clones** the base query.
2. Joins `rooms` on `tickets.room_id = rooms.id`.
3. Selects `rooms.id`, `rooms.name`, `COUNT(*) as total`.
4. Filters: `whereNotNull('tickets.room_id')`.
5. Groups by `rooms.id`, `rooms.name`.
6. Orders by `total` descending, limits to 5.
7. Maps each row to `['name' => ..., 'total' => ..., 'subtitle' => 'tickets']`.

### `getTopTechnicians(): Collection`

Returns `Collection<int, array{name: string, total: int, subtitle: 'actions'}>`.

1. **Clones** the base query.
2. Joins `users` on `tickets.assigned_to = users.id`.
3. Selects `users.id`, `users.name`, `COUNT(*) as total`.
4. Filters: `whereNotNull('tickets.assigned_to')`.
5. Groups by `users.id`, `users.name`.
6. Orders by `total` descending, limits to 5.
7. Maps each row to `['name' => ..., 'total' => ..., 'subtitle' => 'actions']`.

**Who calls it and when:**
- `AnalyticsDashboardService::buildPayload()` at `app/Services/AnalyticsDashboardService.php:64` (instantiation), `:130-132` (method calls).
- Ultimately triggered by `AnalyticsController::index()` via `AnalyticsService`.
- Tests: `tests/Feature/Domain/TicketQueriesTest.php:164-168`.

---

## Notes for developers / AI

- Queries accept pre-built Eloquent `Builder` instances or status IDs via constructor -- they don't resolve status names themselves.
- `MonthlyTicketsQuery` and `TicketKpiQuery` use raw SQL expressions with driver-specific branching (SQLite vs MySQL/PostgreSQL).
- `TicketPriorityQuery` and `TopEntitiesQuery` clone the base query before modifying it to avoid side effects.
- The `subtitle` values in `TopEntitiesQuery` are user-facing display strings (Portuguese) -- these are handled by the i18n system, not this refactoring.
- All queries are `final readonly` and return plain arrays or Collections -- no model instances or special DTOs.
- Queries are instantiated with `new` directly (not via service container), as they have no complex dependency graphs.
