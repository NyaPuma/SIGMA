# `database/migrations/`

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../NON_TECHNICAL_PROJECT_GUIDE.md)

## What Is a Migration? (plain English)

A **migration** is a **versioned change to the database structure** — a small, dated set of instructions that says: *"create this table"*, *"add this column"*, or *"change this thing"*. 

Think of the database tables like a set of filing cabinets you build up over time. A migration is the instruction sheet for assembling one cabinet (or adding one drawer). Because every change is saved in its own file with a date in the name, the whole history of how the database grew is preserved. If we ever need to rebuild the database from scratch, the app simply replays all the migrations **in order** from the beginning.

Each migration has an `up()` (applies the change) and a `down()` (undoes the change). Schema changes roll back; a few data migrations deliberately do **not** — see the note at the bottom of the alteration table.

## The Main Tables These Migrations Create

The migrations below build the SGM database, table by table. Here is what each important table stores, in plain English:

| Migration file | Table(s) it builds | What they store (plain English) |
|---|---|---|
| `0001_01_01_000000_create_users_table.php` | `user_profiles`, `users`, `password_reset_tokens`, `sessions` | User **roles/profiles** (admin, technician, user); the **users** themselves (name, email, password, avatar, security info, login tracking); tokens for **resetting passwords**; and **sessions** (who is logged in, on which device, from which IP). |
| `0001_01_01_000001_create_cache_table.php` | `cache`, `cache_locks` | Temporary **cache** data that speeds the app up, and locks to stop data being overwritten when many things run at once. |
| `0001_01_01_000002_create_jobs_table.php` | `jobs`, `job_batches`, `failed_jobs` | The **background jobs** queue (things done later, outside the request) — including a log of any jobs that **failed**. |
| `0001_01_01_000003_create_rooms_table.php` | `rooms` | The physical **rooms** where equipment lives — name, code, building, floor, location, and capacity. |
| `0001_01_01_000004_create_equipments_table.php` | `equipment_categories`, `equipments` | The **categories** of equipment and the **equipment** itself — name, asset tag, serial, brand, model, purchase/warranty dates, and status (operational, in maintenance, broken, withdrawn). |
| `0001_01_01_000005_create_tickets_table.php` | `ticket_types`, `ticket_statuses`, `tickets`, `ticket_workflow_history` | The heart of the app: **ticket types** (Hardware, Software, Network), **ticket statuses** (open, in progress, closed…), the **tickets** themselves (title, description, priority, workflow timestamps, SLA, budget, resolution), and the **workflow history** (a diary of every status change). |
| `0001_01_01_000006_create_audits_table.php` | `audits` | A full **audit trail** — a "black box" recording who created, changed, or deleted what, when, and from where. Supports MySQL RANGE partitioning for very large volumes. |
| `0001_01_01_000008_create_ticket_attachments_table.php` | `ticket_attachments` | The **files/photos** attached to tickets (original name, stored path, type, checksum). |
| `0001_01_01_000009_create_ticket_comments_table.php` | `ticket_comments` | **Comments** on tickets, including internal-only notes and parent/child threading (replies). |
| `2026_07_09_100000_create_notifications_table.php` | `notifications` | **Notifications** sent to users (title, message, type, priority, read/unread, and an expiry date). |
| `2026_08_08_000001_create_stock_catalog_tables.php` | `part_categories`, `tax_rates`, `parts`, `suppliers`, `part_supplier` | The **spare-parts catalogue**: part **categories**, **VAT/tax rates**, the **parts** themselves (SKU, prices, current/min/max stock), **suppliers**, and which parts each supplier can provide. |
| `2026_08_08_000002_create_stock_movements_table.php` | `stock_movements` | Every **in/out/adjust/return** of a part, with quantity, reason, price, and the resulting stock level. |
| `2026_08_08_000003_create_maintenance_plans_table.php` | `maintenance_plans`, `maintenance_plan_part` | **Preventive maintenance plans** (service every X days/hours/cycles) and which parts each plan needs. |
| `2026_08_12_000001_create_user_preferences_table.php` | `user_preferences` | Per-user **preferences** — language, currency, date/time/number formats. |
| `2026_08_30_000001_create_currency_rates_table.php` | `currency_rates` | **Exchange rates** between currencies, with a timestamp of when they were fetched. |
| `2026_08_05_000001/000002` | `theme_settings`, `system_settings` | **Theme** and **system** key-value settings. |

There are also several **alteration** migrations that adjust existing tables over time rather than creating new ones. The full list, in apply order (other than the base `0001_01_01_*` tables above):

| Migration file | What it changes |
|---|---|
| `2026_07_24_152504_create_audits_append_only_trigger.php` | Adds an **append-only trigger** to `audits` (entries can't be updated/deleted) — MySQL, PostgreSQL and SQLite variants. Each `DB::unprepared()` block is a nowdoc (`<<< 'SQL'`) with single-quoted SQL literals: PHP single-quoted strings have no `''` escape, and MySQL under `sql_mode=ANSI_QUOTES` would read `"45000"` as an identifier. `down()` drops the triggers (and the plpgsql function on PostgreSQL). |
| `2026_07_31_000001_convert_ticket_tables_to_utf8mb4.php` | Converts the tickets module tables to `utf8mb4`/`utf8mb4_unicode_ci` (MySQL only; no-op elsewhere). |
| `2026_08_03_000001_add_ai_recommendation_columns_to_tickets_table.php` | Adds AI-recommendation columns to `tickets`: `recommended_technician_id`, `ai_recommendation_reason`, `ai_processed_at`. |
| `2026_08_07_000001_add_reporter_fields_to_tickets_table.php` | Adds `reporter_name`, `reporter_contact`, `source` to `tickets`; makes `tickets.user_id` and `ticket_attachments.user_id` nullable. |
| `2026_08_10_000001_add_locale_to_users_table.php` | Adds `locale` (default `pt-PT`) to `users`. |
| `2026_08_29_000001_add_theme_to_users_table.php` | Adds `theme` (nullable) to `users`. |
| `2026_08_12_000002_populate_user_preferences.php` | Back-fills default `user_preferences` rows for existing users. `down()` is intentionally empty: the `pt`/`EUR`/`d/m/Y` rows it created are indistinguishable from preferences a user saved by hand, so deleting on rollback would destroy real user data. |
| `2026_08_12_000003_add_number_format_to_user_preferences.php` | Adds `number_format` JSON column (default `{"decimal":".","thousand":","}`) to `user_preferences`. |
| `2026_08_12_000004_add_time_format_to_user_preferences.php` | Adds `time_format` (default `H:i`) to `user_preferences`. |
| `2026_09_02_000001_resize_number_format_in_user_preferences_table.php` | Resizes `number_format` from VARCHAR(50) to VARCHAR(191) (previously caused MySQL error 1406 on insert). `down()` deliberately keeps the 191-character column: shrinking it would truncate stored formats and raise error 1406 on MySQL. |
| `2026_08_08_000004_add_low_stock_notification_type.php` | Adds the `low_stock` option to the `notifications.type` enum. `down()` deliberately keeps `low_stock` — removing an enum value is a data migration (existing rows would become invalid), not a schema rollback. |
| `2026_08_28_000001_add_indexes_to_tickets_and_user_profiles_table.php` | Adds indexes on `tickets.recommended_technician_id` and `user_profiles.deleted_at`. |
| `2026_09_02_000003_add_performance_indexes_table.php` | Adds performance indexes on `stock_movements`, `tickets`, `ticket_workflow_history`, and `notifications`. |
| `2026_11_04_000001_index_ticket_dates_without_status_prefix.php` | Adds `(deleted_at, opened_at)` and `(deleted_at, resolved_at)` to `tickets`. Every other `tickets` index is led by `status_id`, so a month/KPI/calendar filter on a date range alone could not use any of them; `deleted_at` leads because Eloquent soft deletes append `deleted_at IS NULL` (equality) before the date range. |

**Note on the `user_preferences` migrations:** the `2026_08_12_000003` (number format) and `2026_08_12_000004` (time format) migrations are both present in this directory and both live on their dated filenames (do **not** rename them). The later `2026_09_02_000001` migration simply widens the `number_format` column that `000003` created.

## Notes for developers / AI

- **Do not rename migration files or classes** — Laravel tracks applied migrations by filename (the `migrations` table). Renaming would break `migrate:status` and rollback history.
- Comments within migrations have been translated to English.
- The `audits` table supports MySQL RANGE partitioning (managed by the `PartitionAudits` command).
- Data migrations (like `populate_user_preferences`) are reversible with sensible rollback logic.

## Related Folders

| Path | Relationship |
|---|---|
| `app/Models/` | Eloquent models mapping to these tables. |
| `database/seeders/` | Seeders populating these tables. |
| `database/factories/` | Factories creating test data for these tables. |
| `app/Console/Commands/PartitionAudits.php` | Manages audit table partitions. |
