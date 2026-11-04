# Analytical Center Dashboard — Seed Data & Fixes

Report on the work of populating the analytical dashboard with realistic and
internally coherent data, and on bug fixes for issues that produced
KPIs at zero (SLA, costs) and an empty "Recent Activity" section.

## Problem

- `Average Resolution Time`, `SLA` and `Monthly Cost` showed up as zero.
- "Recent Activity" never displayed events.

### Confirmed root causes

1. **Data**, not queries: the analytical queries (`TicketKpiQuery`,
   `MonthlyTicketsQuery`, `costByEquipment`) only sum tickets with non-null
   `closed_at` + `actual_cost` and SLA with `opened_at→closed_at`
   ≤ 480 min. The previous seeders did not write these fields, so everything
   stayed at zero.
2. **Recent Activity**: the `activity-timeline-card.blade.php`
   component used the `/api/activities` endpoint (non-existent route → 404) and the
   raw seeders did not create audits (the `Auditable` trait only fires via
   Eloquent), so `audits` stayed empty.

## Coherence rules applied (approved)

- **Volume**: ~60 tickets over the last 6 months, with a lighter
  trend (a handful per month).
- **Statuses**: closed 62%, in progress 18%, open 12%, pending
  budget 5%, cancelled 2%, rejected 1%.
- **Priorities**: low 30%, medium 40%, high 25%, critical 5%.
- **Origin**: web 55%, QR 25%, phone 10%, API 7%, mobile 3%.
- **SLA**: ~80% of closed tickets meet the 480-min SLA
  (average MTTR ≈ 350 min).
- **Costs**: only on closed tickets; `actual_cost` ≥ `estimated_cost`;
  labor at €35/h × `minutes_spent` + parts.
- **Schedule**: 90% of tickets between 8 a.m. and 6 p.m., Monday to Friday.
- **Pareto**: the equipment catalog has a breakdown `weight`; rooms
  follow a decreasing weight (the first 40 with the most occurrences).
- **Stock**: movements with chained `stock_after` and ~12–18 parts in
  low stock.

## Seeders

| File | Description |
| --- | --- |
| `database/seeders/Data/OperationalData.php` | PT industrial domain: 40 rooms, 30 pieces of equipment (weights/descriptions), breakdown scenarios by category, parts with `manufacturer_ref` and cost ranges, technician/reporter names. |
| `database/seeders/Data/TicketDataset.php` | Deterministic engine (`mt_srand(20260701)`) that generates 60 tickets following the rules above. |
| `database/seeders/TicketsSeeder.php` | Load via `DB::insertOrIgnore` in chunks of 500; aborts in production. |
| `database/seeders/RoomsSeeder.php` | 3 manual rooms + 40 from the catalog + generic zones (45 rooms). |
| `database/seeders/EquipmentsSeeder.php` | 4 manual + catalog entries with `notes` + generic `EQ-NNN-NNNN` (40). |
| `database/seeders/UsersSeeder.php` | Technicians with real PT names; admin/technician/user profiles. |
| `database/seeders/ActivityFeedSeeder.php` | ~40 audits within the last ~22h (`url='seed:operational'` marker), idempotent. |
| `database/seeders/NotificationSeeder.php` | 60 notifications weighted by type/priority. |
| `database/seeders/StockDataSeeder.php` | Parts by category (no lorem), chained `stock_after` movements, coherent low-stock, maintenance plans. |

Order in `DatabaseSeeder`:
`TicketLookupSeeder` → `BulkOperationalDataSeeder`
(→ `UserProfilesSeeder`, `UsersSeeder`, `RoomsSeeder`,
`EquipmentCategoriesSeeder`, `EquipmentsSeeder`, `TicketsSeeder`)
→ `StockDataSeeder` → `ActivityFeedSeeder` → `NotificationSeeder`.

## Code fixes

- **`routes/api.php`**: registered the `GET /api/activities` route
  (`ActivityFeedController@index`), which feeds the
  `activity-timeline-card` component.
- **`app/Http/Controllers/ActivityFeedController.php`**: new — JSON feed
  (`title`, `description`, `time_ago`, `icon_bg`, `dot_color`) built from
  `audits`.
- **`app/Domain/Ticket/Queries/TicketKpiQuery.php`**: `avg_waiting` now uses
  `diff(opened_at→assigned_at)` with non-null `assigned_at` (it was
  `diff(opened_at→NOW)`, skewed by 6 months of data); removed the now-unused
  `nowExpression`.
- **`app/Services/AnalyticsDashboardService.php`**:
  - `system_availability` read from `services.custom.analytics.*` — the key
    `config/services.custom.php` actually defines and the one SystemSettingsService
    saves when an admin edits "Disponibilidade do sistema". An earlier note here
    claimed `services.custom.analytics.*` did not exist; that was wrong, and
    reading `services.analytics.*` instead left the Settings field inert. There is
    now a single source of truth (the duplicate was dropped from
    `config/services.php`);
  - `sla_target_minutes` from config passed to `TicketKpiQuery` and used in
    `monthlyPerformanceData`.
- **`database/seeders/NotificationSeeder.php`**: priority `urgent` →
  `critical` (value accepted by the enum of the `notifications.priority` column).

## Design improvements

- **`resources/js/pages/analytics/charts.js`**: "No data to display"
  empty states in all charts when datasets are empty.
- **`resources/js/pages/analytics/kpi.js`**: pt-PT formatting
  (`Intl.NumberFormat`) and readable minutes (`5h 50m`, `3d 4h`).
- **`resources/views/components/ui/analytics/equipment-distribution-card.blade.php`**:
  title/description corrected to reflect the actual content (ticket
  priorities) instead of "Equipment".

## Validation

Environment without executable `php`/`composer`/`npm` — validation was static:

- Cross-checked every column used by the seeders against the migrations
  (`tickets`, `audits`, `notifications`, `parts`, `stock_movements`,
  `equipments`, `rooms`).
- Cross-checked the enums (statuses, priorities, budget, stock, notifications)
  against the generated values.
- Brace/parenthesis balancing of the changed files.
- The `/api/activities` route was added outside the `custom.auth` group
  (the component sends `authHeader()` when the store exists).

Commands to validate locally:

```bash
php artisan migrate:fresh --seed
php artisan route:list --path=api
```
