# resources/views/reports

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../../NON_TECHNICAL_PROJECT_GUIDE.md)

Print-ready PDF report templates (A4 page size, inline `<style>` blocks).

## Files

| File | Purpose |
|---|---|
| `equipments-qr.blade.php` | QR code sheet for equipment. Renders a 4-column grid of QR images with equipment name, asset tag, room and an "Affix" tag. Includes brand bar and page-footer. |
| `stock-costs-by-equipment.blade.php` | Parts cost report by equipment. Ranked table showing consumed quantity and total cost per equipment, with a grand total footer row. |
| `tickets.blade.php` | Consolidated tickets report (landscape A4). Summary cards (total, duration, cost, budget, closed) followed by a detailed table with status, priority, timestamps, duration, cost and budget columns. |

## Notes for developers / AI

- All three templates use inline `<style>` for email/print client compatibility.
- User-facing strings use `__()` translation calls — do not modify, update `lang/` files instead.
- `tickets.blade.php` has CSS class names derived from Portuguese DB status values (e.g., `.badge-aberta`, `.pri-critica`) via `str_replace` in Blade — these map to `TicketStatusEnum` and `TicketPriorityEnum` cases and should not be renamed without updating the enum values and the transformation logic.
- All three templates render dates, times and money through `App\Services\LocalizationService` (`formatDate` / `formatDateTime` / `formatCurrency`), so a PDF generated from a queue job follows the locale that job applied for its requester.

## Recent Refactorings

### 2026-08-27 — Expression-output fixes + inline style removal

- All report templates keep their self-contained `<style>` block and compact typography (deliberate print/PDF exception — external CSS and theme JS are not available when rendering to PDF).
- Fixed broken Blade expression output (values were rendering as literal text such as `(now())` / `($tickets->sum(...))` / `((int) ...)` because the `{{ }}` delimiters had been stripped):
  - `tickets.blade.php`: `now()`, all `$tickets->sum(...)` summary/totals, per-row `minutes_spent` / `actual_cost` / `budget_amount`.
  - `stock-costs-by-equipment.blade.php`: `now()`, `(int) $item['total_quantity']`, `(float) $item['total_value']`, `(float) $total`.
  - `equipments-qr.blade.php`: `now()`.
- Removed inline `style="..."` attributes by moving the styles into the template `<style>` block:
  - `stock-costs-by-equipment.blade.php`: added `.col-rank`, `.equipment-name`, `.font-mono` classes (the font-mono inline declaration was duplicated into the stylesheet).
  - `tickets.blade.php`: added `.unit-min` (sum duration unit) and `.empty-row` (empty-state padding) classes.

### 2026-11-04 — Report localisation

- Removed the hardcoded English/Portuguese literals that appeared in PDFs regardless of the requester's locale, routing each through `__()` and `LocalizationService`:
  - `tickets.blade.php`: header timestamp (`common.Emitido em:` + `formatDateTime(now())`), `Custo Total`/`Orçamento Total` summary and tfoot values, per-row `actual_cost`/`budget_amount` via `formatCurrency`, empty-state row, footer page label. The closed-tickets summary now compares against `TicketStatusEnum::Closed->value` instead of the literal `'fechada'`.
  - `equipments-qr.blade.php`: subtitle, issue date, equipment count, `alt` text, footer page label.
  - `stock-costs-by-equipment.blade.php`: subtitle, period block (`common.Período:` / `common.até` / `common.Histórico completo`) with `formatDate`, empty state, missing-equipment label, `total_value` and grand total via `formatCurrency`, footer page label.
- New `lang/pt-PT` keys were mirrored in `lang/en-GB`. The other 48 locale directories have no `common.php`/`equipment.php`/`stock.php` entries for these strings and fall back to `fallback_locale` (`pt-PT`); there is no lang-completeness test to catch that.
- `.text-muted` inside these templates is legitimate: each is a self-contained dompdf document with its own stylesheet, not a Tailwind class.
