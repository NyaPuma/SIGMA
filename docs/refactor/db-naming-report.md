# Database Schema Naming Report

> **Status:** Report Only (Out of Scope for Automated Renaming per §3)
> **Generated:** 2026-08-17
> **Audited Files:** All 32 migrations under `database/migrations/` and all 23 Eloquent models under `app/Models/`

---

## 1. Executive Summary

A comprehensive inspection of the database schema was conducted across all migration files and Eloquent models.

**Key Finding:**
The core database tables and columns are already consistently named in **English** (e.g., `user_profiles`, `tickets`, `stock_movements`, `maintenance_plans`, `suppliers`, `rooms`, `equipments`).

However, there are specific legacy lookup codes, seed values, and minor metadata fields containing Portuguese identifiers or values that should be noted for future schema migration planning.

---

## 2. Table and Column Inspection

### 2.1 Table Names
All 31 application and framework tables use English names:
- `users`, `user_profiles`, `user_preferences`, `password_reset_tokens`, `sessions`
- `rooms`, `equipment_categories`, `equipments`
- `ticket_types`, `ticket_statuses`, `tickets`, `ticket_workflow_history`, `ticket_attachments`, `ticket_comments`
- `part_categories`, `tax_rates`, `parts`, `suppliers`, `part_supplier`, `stock_movements`
- `maintenance_plans`, `maintenance_plan_part`
- `audits`, `notifications`, `theme_settings`, `system_settings`
- `jobs`, `job_batches`, `failed_jobs`, `cache`, `cache_locks`

### 2.2 Column Names
All column definitions across migrations use English nomenclature (e.g., `id`, `name`, `sku`, `brand`, `manufacturer_ref`, `unit_of_measure`, `cost_price`, `sale_price`, `current_stock`, `min_stock`, `max_stock`, `location`, `photo`, `active`, `technical_notes`, `avg_lead_time_days`, `movement_type`, `quantity`, `reason`, `unit_price_snapshot`, `stock_after`, `interval_type`, `interval_value`, `expected_quantity`, `locale`, `language`, `currency`, `date_format`, `number_format`, `time_format`, etc.).

---

## 3. Flagged Items for Future Deliberate Migration

While column and table identifiers are in English, the following data values and lookup codes contain Portuguese strings:

1. **`ticket_statuses.code` lookup code:**
   - Database value: `'PENDENTE_ORCAMENTO'`
   - Recommended future value: `'PENDING_BUDGET'`
   - *Note:* Referenced in `TicketStatusEnum`, `TicketStatusService`, `TicketLookupSeeder`, and test suites. Renaming requires a data migration if executed against production databases.

2. **Default Locale in `users.locale`:**
   - Default column value in migration `2026_08_10_000001_add_locale_to_users_table.php`: `'pt-PT'`
   - *Note:* Kept as the default system locale for current Portuguese deployments.

3. **Seeded Type / Status Names in `TicketLookupSeeder`:**
   - Seeded display names now use `TicketStatusEnum` backing values (e.g. `TicketStatusEnum::PendingBudget->value` → `'pendente orçamento'`, `'avaria'`).
   - *Note:* Display labels are managed via i18n (`lang/` files); database codes should eventually be unified to English enum representations.

---

## 4. Conclusion & Recommendation

No immediate disruptive database schema alterations are required for Phase 1 code normalization. All table and column names already adhere to English conventions.

When a dedicated database schema migration project is scheduled, the lookup code `PENDENTE_ORCAMENTO` → `PENDING_BUDGET` should be migrated along with updating corresponding seeder definitions and lookup queries.
