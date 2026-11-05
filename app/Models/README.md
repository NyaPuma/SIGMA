# app/Models

Eloquent ORM models representing the persistence layer and domain entities of the SGM system.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The Filing Cabinet" defining what data is stored and how different records relate to each other.

---

## Quick-Reference Table

| Model | Table | Key Relationships | Purpose |
|---|---|---|---|
| [`Audit.php`](Audit.php) | `audits` | `belongsTo(User)`, `morphTo(auditable)` | Immutable audit trail capturing changes to models, acting user, IP address, and URLs. |
| [`Equipment.php`](Equipment.php) | `equipments` | `belongsTo(EquipmentCategory)`, `belongsTo(Room)`, `hasMany(Ticket)`, `hasMany(MaintenancePlan)` | Hardware assets, serial numbers, warranty tracking, and physical location allocations. |
| [`EquipmentCategory.php`](EquipmentCategory.php) | `equipment_categories` | `hasMany(Equipment)` | Grouping classification for hardware equipment assets. |
| [`ExchangeRate.php`](ExchangeRate.php) | `currency_rates` | None | Stored currency exchange-rate conversion for a single base-to-target pair, fetched twice per day. |
| [`MaintenancePlan.php`](MaintenancePlan.php) | `maintenance_plans` | `belongsTo(Equipment)`, `belongsToMany(Part)` | Preventive maintenance routines, recurrence intervals, and expected part consumption. |
| [`Notification.php`](Notification.php) | `notifications` | `belongsTo(User)`, `morphTo(notifiable)` | In-app, email, and system alert notifications with read/unread tracking. |
| [`Part.php`](Part.php) | `parts` | `belongsTo(PartCategory)`, `belongsTo(TaxRate)`, `belongsToMany(Supplier)`, `hasMany(StockMovement)`, `belongsToMany(MaintenancePlan)` | Inventory stock items, SKU references, pricing, stock levels, and threshold limits. |
| [`PartCategory.php`](PartCategory.php) | `part_categories` | `hasMany(Part)` | Taxonomy grouping for spare parts and consumable stock items. |
| [`Room.php`](Room.php) | `rooms` | `hasMany(Equipment)`, `hasMany(Ticket)` | Physical facility rooms, buildings, and floors where equipment is stationed. |
| [`StockMovement.php`](StockMovement.php) | `stock_movements` | `belongsTo(Part)`, `belongsTo(Ticket)`, `belongsTo(Equipment)`, `belongsTo(User)` | Inventory transactions (stock in, stock out, consumption in ticket interventions, adjustments). |
| [`Supplier.php`](Supplier.php) | `suppliers` | `belongsToMany(Part)` | External vendors and parts suppliers, tracking tax IDs (NIF) and lead times. |
| [`SystemSetting.php`](SystemSetting.php) | `system_settings` | None | Key-value application runtime configuration overrides. |
| [`TaxRate.php`](TaxRate.php) | `tax_rates` | `hasMany(Part)` | VAT / sales tax rates applied to spare parts catalog pricing. |
| [`ThemeSetting.php`](ThemeSetting.php) | `theme_settings` | None | UI appearance settings and CSS customization variables. |
| [`Ticket.php`](Ticket.php) | `tickets` | `belongsTo(User)` (reporter), `belongsTo(User, technician)`, `belongsTo(TicketStatus)`, `belongsTo(Equipment)`, `belongsTo(Room)`, `belongsTo(User, budgetApprovedBy)`, `belongsTo(User, resolvedBy)`, `belongsTo(User, closedBy)`, `belongsTo(User, recommendedTechnician)`, `hasMany(TicketWorkflowHistory)`, `hasMany(TicketComment)`, `hasMany(TicketAttachment)` | Core incident and maintenance ticket entity with lifecycle timestamps, budgets, and SLAs. |
| [`TicketAttachment.php`](TicketAttachment.php) | `ticket_attachments` | `belongsTo(Ticket)`, `belongsTo(User)` | Uploaded evidence, photos, and documents attached to tickets. Automatically deletes files on model deletion. |
| [`TicketComment.php`](TicketComment.php) | `ticket_comments` | `belongsTo(Ticket)`, `belongsTo(User)`, `belongsTo(TicketComment, parent)`, `hasMany(TicketComment, replies)` | Communication thread messages and internal notes on tickets. |
| [`TicketStatus.php`](TicketStatus.php) | `ticket_statuses` | `belongsTo(TicketType)`, `hasMany(Ticket)` | Configurable lookup status entries for ticket workflows. |
| [`TicketType.php`](TicketType.php) | `ticket_types` | `hasMany(TicketStatus)`, `hasManyThrough(Ticket)` | Categorization types for tickets and incident workflows. |
| [`TicketWorkflowHistory.php`](TicketWorkflowHistory.php) | `ticket_workflow_history` | `belongsTo(Ticket)`, `belongsTo(TicketStatus, originStatus)`, `belongsTo(TicketStatus, destinationStatus)`, `belongsTo(User, technician)` | State transition audit log documenting stage changes and acting technician. |
| [`User.php`](User.php) | `users` | `belongsTo(UserProfile)`, `hasMany(Ticket, tickets)`, `hasMany(Ticket, assignedTickets)` | Application authentication and user model with RBAC role helpers and API token support. |
| [`UserPreference.php`](UserPreference.php) | `user_preferences` | `belongsTo(User)` | User-specific localization options (language locale, currency, date/time/number formats). |
| [`UserProfile.php`](UserProfile.php) | `user_profiles` | `hasMany(User)` | Role profile group (Admin, Technician, User) determining access permissions. |

---

## Model Deep Dive -- Grouped by Domain

### CORE ASSETS

---

### Audit

- **File:** `app/Models/Audit.php`
- **What it is:** An immutable, tamper-proof log entry that records every important change made in the system -- who did it, what changed, when, and from where.
- **Database table:** `audits`
- **Key fields (from migration `0001_01_01_000006_create_audits_table.php`):**
  - `id` -- auto-incrementing bigint primary key
  - `user_id` (FK -> `users.id`, nullable) -- the user who performed the action; set to `null` if no authenticated user was present (e.g., system/seeder)
  - `auditable_type` (string, max 150) -- the fully-qualified class name of the model that was changed (polymorphic type)
  - `auditable_id` (unsigned bigint) -- the primary key of the changed model (polymorphic ID)
  - `event` (enum: `created`, `updated`, `deleted`, `restored`) -- which lifecycle event triggered this audit row
  - `old_values` (JSON, nullable) -- snapshot of attribute values **before** the change (null on `created` events)
  - `new_values` (JSON, nullable) -- snapshot of attribute values **after** the change (null on `deleted` events)
  - `url` (string, max 2048, nullable) -- the full URL of the HTTP request that triggered the change
  - `ip_address` (string, max 45, nullable) -- the client's IP address (supports IPv6)
  - `user_agent` (text, nullable) -- the client's browser user-agent string
  - `created_at` / `updated_at` -- timestamps
- **Relationships:**
  - `user(): BelongsTo(User)` -- the user who triggered the audited event
  - `auditable(): MorphTo` -- the polymorphic model that was audited (could be Ticket, Equipment, Part, Room, User, etc.)
- **Traits:**
  - `HasFactory` -- provides the `factory()` method for test data generation
- **Casts:**
  - `old_values` -> `array` (decoded from JSON)
  - `new_values` -> `array` (decoded from JSON)
- **Scopes:** None defined in the model itself.
- **Immutability (booted method + AuditObserver):**
  - The model's own `booted()` registers `updating` and `deleting` listeners that throw `LogicException("Audit records are immutable and cannot be updated/deleted.")`.
  - The `AuditObserver` (registered in `AppServiceProvider::boot()` at line 147) adds the **same** protection plus `forceDeleting` -- all three throw `LogicException`. This provides a double layer: the model's own event hooks and the observer.
  - **Additionally**, a database-level trigger (`migration 2026_07_24_152504_create_audits_append_only_trigger.php`) prevents UPDATE and DELETE at the SQL engine level (both MySQL and SQLite are supported).
- **Events fired:** None.
- **WHERE/WHEN it's used:**
  - **Created by:** The `App\Traits\Auditable` trait (see below) automatically calls `Audit::create(...)` inside `bootAuditable()`. This fires on every `created`, `updated`, and `deleted` event of any model that uses the `Auditable` trait.
  - **Read by:** `AuditController` (paginated listing with filtering), `ActivityFeedController` (activity feed with formatted descriptions), `AnalyticsDashboardService` (audit count queries), and `UiController` (dashboard audit summaries).

---

### Equipment

- **File:** `app/Models/Equipment.php`
- **What it is:** A single piece of physical hardware -- a server, printer, air conditioner, or any other asset -- that the organisation owns and may need to maintain.
- **Database table:** `equipments`
- **Key fields (from migration `0001_01_01_000004_create_equipments_table.php`):**
  - `id` -- auto-incrementing bigint primary key
  - `room_id` (FK -> `rooms.id`, nullable) -- the Room where this equipment is physically located
  - `category_id` (FK -> `equipment_categories.id`, nullable) -- the EquipmentCategory classification
  - `name` (string, max 150) -- display name
  - `asset_tag` (string, max 100, nullable, unique) -- the organisation's internal asset tag identifier
  - `serial` (string, max 100, unique) -- manufacturer serial number
  - `brand` (string, max 100, nullable) -- brand name (e.g., "Dell", "HP")
  - `model` (string, max 100, nullable) -- model number/name
  - `manufacturer` (string, max 100, nullable) -- manufacturer name
  - `purchase_date` (date, nullable) -- when the equipment was purchased
  - `warranty_until` (date, nullable) -- warranty expiry date
  - `status` (enum: `operacional`, `manutenção`, `avariado`, `abatido`; default `operacional`) -- current operational status
  - `active` (boolean, default `true`) -- whether the equipment is active in the system
  - `notes` (text, nullable) -- free-text notes
  - `created_at` / `updated_at` -- timestamps
  - `deleted_at` -- soft delete timestamp
- **Relationships:**
  - `category(): BelongsTo(EquipmentCategory)` -- the classification category
  - `room(): BelongsTo(Room)` -- the physical location
  - `tickets(): HasMany(Ticket)` -- all maintenance tickets linked to this equipment
  - `maintenancePlans(): HasMany(MaintenancePlan)` -- all preventive maintenance plans for this equipment
- **Traits:**
  - `Auditable` -- automatically writes an `Audit` row on every create/update/delete
  - `HasFactory` -- factory support for testing
  - `SoftDeletes` -- soft-deletes (sets `deleted_at` instead of removing the row); excluded from default queries
- **Casts:**
  - `active` -> `boolean`
  - `purchase_date` -> `date` (Carbon instance)
  - `warranty_until` -> `date` (Carbon instance)
- **Scopes:**
  - `scopeActive(Builder $query)` -- filters to only records where `active = true`
- **WHERE/WHEN it's used:**
  - **Created/Updated:** `AdminEquipmentController` (CRUD), `StoreEquipmentRequest`/`UpdateEquipmentRequest` (validation)
  - **Read:** `UiController` (dashboard dropdowns for rooms and categories), `TicketController` (equipment selection when creating tickets), `AnalyticsDashboardService` (equipment counts), `StockMovementController` (equipment selection for stock movements)
  - **Relationship access:** `Ticket->equipment`, `MaintenancePlan->equipment`, `StockMovement->equipment`

---

### EquipmentCategory

- **File:** `app/Models/EquipmentCategory.php`
- **What it is:** A label or grouping used to classify equipment -- for example, "Computers", "HVAC Systems", or "Office Furniture".
- **Database table:** `equipment_categories`
- **Key fields (from migration `0001_01_01_000004_create_equipments_table.php`):**
  - `id` -- auto-incrementing bigint primary key
  - `name` (string, max 100, unique) -- category name
  - `active` (boolean, default `true`) -- whether the category is active
  - `created_at` / `updated_at` -- timestamps
  - `deleted_at` -- soft delete timestamp
- **Relationships:**
  - `equipments(): HasMany(Equipment)` -- all Equipment records in this category (FK: `category_id`)
- **Traits:**
  - `HasFactory` -- factory support
  - `SoftDeletes` -- soft-delete support
- **Casts:**
  - `active` -> `boolean`
- **Scopes:**
  - `scopeActive(Builder $query)` -- filters to only records where `active = true`
- **WHERE/WHEN it's used:**
  - **Read:** `UiController` (populates equipment category dropdown lists for create/edit forms)
  - **Read:** `StoreEquipmentRequest`/`UpdateEquipmentRequest` (validates `category_id` exists)
  - **Relationship access:** `Equipment->category`

---

### Room

- **File:** `app/Models/Room.php`
- **What it is:** A physical space -- a room, office, floor, or building -- where equipment is installed.
- **Database table:** `rooms`
- **Key fields (from migration `0001_01_01_000003_create_rooms_table.php`):**
  - `id` -- auto-incrementing bigint primary key
  - `name` (string, max 100) -- room/office name
  - `code` (string, max 50, unique) -- auto-generated room code (e.g., `RM-<UNIQUEID>`)
  - `building` (string, max 100, nullable) -- building name
  - `floor` (string, max 50, nullable) -- floor number/label
  - `location` (string, max 255, nullable) -- free-text location description
  - `capacity` (unsigned smallint, nullable) -- room capacity (max number of people/equipment)
  - `description` (text, nullable) -- room description
  - `notes` (text, nullable) -- free-text notes
  - `active` (boolean, default `true`) -- whether the room is active
  - `created_at` / `updated_at` -- timestamps
  - `deleted_at` -- soft delete timestamp
- **Relationships:**
  - `equipments(): HasMany(Equipment)` -- all Equipment located in this room
  - `tickets(): HasMany(Ticket)` -- all Tickets associated with this room
- **Traits:**
  - `Auditable` -- automatically writes an `Audit` row on every create/update/delete
  - `HasFactory` -- factory support
  - `SoftDeletes` -- soft-delete support
- **Casts:**
  - `active` -> `boolean`
  - `capacity` -> `integer`
- **Boot behavior (`booted()`):**
  - On `creating`: if `code` is null or blank, auto-generates one as `'RM-' . strtoupper(uniqid())`. This means the code is always set server-side, even if a blank value is passed in.
- **Scopes:**
  - `scopeActive(Builder $query)` -- filters to only records where `active = true`
- **WHERE/WHEN it's used:**
  - **Created:** `RoomController`, `CreateRoomAction`, `StoreRoomRequest` (validation)
  - **Read:** `UiController` (populates room dropdown lists), `TicketController` (room selection when creating tickets), `AnalyticsDashboardService`
  - **Relationship access:** `Equipment->room`, `Ticket->room`

---

### PARTS & STOCK

---

### Part

- **File:** `app/Models/Part.php`
- **What it is:** A spare part, consumable, or supply item kept in inventory -- a filter, a toner cartridge, a circuit board, a bolt.
- **Database table:** `parts`
- **Key fields (from migration `2026_08_08_000001_create_stock_catalog_tables.php`):**
  - `id` -- auto-incrementing bigint primary key
  - `part_category_id` (FK -> `part_categories.id`, nullable) -- the PartCategory classification
  - `tax_rate_id` (FK -> `tax_rates.id`, nullable) -- the TaxRate (VAT) applied to this part
  - `sku` (string, max 100, unique) -- stock-keeping-unit code, unique identifier for ordering
  - `name` (string, max 150) -- display name
  - `description` (text, nullable) -- detailed description
  - `brand` (string, max 100, nullable) -- brand name
  - `manufacturer_ref` (string, max 100, nullable) -- manufacturer's reference/part number
  - `unit_of_measure` (enum: `unit`, `meter`, `liter`, `kg`, `pair`, `set`, `roll`, `other`; default `unit`) -- how this part is measured
  - `cost_price` (decimal 10,2) -- purchase cost per unit
  - `sale_price` (decimal 10,2, nullable) -- sale price per unit (if applicable)
  - `current_stock` (integer, default `0`) -- current stock level
  - `min_stock` (integer, default `0`) -- minimum threshold before triggering low-stock alerts
  - `max_stock` (integer, nullable) -- maximum stock capacity
  - `location` (string, max 150, nullable) -- physical storage location (e.g., "Shelf A3, Warehouse 2")
  - `photo` (string, max 255, nullable) -- path/URL to a photo of the part
  - `active` (boolean, default `true`) -- whether the part is active in the catalog
  - `technical_notes` (text, nullable) -- technical specifications or notes
  - `created_at` / `updated_at` -- timestamps
  - `deleted_at` -- soft delete timestamp
- **Relationships:**
  - `category(): BelongsTo(PartCategory)` -- the classification category (FK: `part_category_id`)
  - `taxRate(): BelongsTo(TaxRate)` -- the applied VAT rate (FK: `tax_rate_id`)
  - `suppliers(): BelongsToMany(Supplier)` -- all Suppliers that supply this part (junction table: `part_supplier`; pivot fields: `price`, `supplier_ref`, `lead_time_days`; timestamps)
  - `movements(): HasMany(StockMovement)` -- all StockMovement records for this part
  - `maintenancePlans(): BelongsToMany(MaintenancePlan)` -- all MaintenancePlans that include this part (junction table: `maintenance_plan_part`; pivot field: `expected_quantity`; timestamps)
- **Traits:**
  - `Auditable` -- automatically writes an `Audit` row on every create/update/delete
  - `HasFactory` -- factory support
  - `SoftDeletes` -- soft-delete support
- **Casts:**
  - `cost_price` -> `decimal:2`
  - `sale_price` -> `decimal:2`
  - `current_stock` -> `integer`
  - `min_stock` -> `integer`
  - `max_stock` -> `integer`
  - `active` -> `boolean`
- **Helper methods (NOT scopes):**
  - `priceWithVat(): float` -- returns `cost_price * (1 + taxRate.percent / 100)`, rounded to 2 decimals. Returns 0 if no tax rate is assigned.
  - `stockValue(): float` -- returns `current_stock * cost_price`, rounded to 2 decimals. Used to calculate total inventory value.
  - `isLowStock(): bool` -- returns `true` when `current_stock <= min_stock`. Used by `LowStockAlertService`.
- **Scopes:**
  - `scopeActive(Builder $query)` -- filters to only records where `active = true`
  - `scopeLowStock(Builder $query)` -- filters to active parts where `current_stock <= min_stock` (via `whereColumn`)
  - `scopeOutOfStock(Builder $query)` -- filters to parts where `current_stock = 0`
- **WHERE/WHEN it's used:**
  - **Created:** `CreatePartAction`, `PartController`, `StorePartRequest`
  - **Read:** `PartService` (listing/search), `StockDashboardService` (dashboard metrics using `active()`, `lowStock()` scopes), `StockReportController` (PDF/Excel exports), `StockUiController` (dropdown lists), `StockMovementController` (selecting a part for a movement), `LowStockAlertService` (low-stock detection), `AnalyticsDashboardService`
  - **Relationship access:** `StockMovement->part`, `MaintenancePlan->parts`, `Supplier->parts`

---

### PartCategory

- **File:** `app/Models/PartCategory.php`
- **What it is:** A grouping for spare parts -- for example, "Electrical Components", "Filters", or "Consumables".
- **Database table:** `part_categories`
- **Key fields (from migration `2026_08_08_000001_create_stock_catalog_tables.php`):**
  - `id` -- auto-incrementing bigint primary key
  - `name` (string, max 100, unique) -- category name
  - `active` (boolean, default `true`) -- whether the category is active
  - `created_at` / `updated_at` -- timestamps
  - `deleted_at` -- soft delete timestamp
- **Relationships:**
  - `parts(): HasMany(Part)` -- all Part records in this category (FK: `part_category_id`)
- **Traits:**
  - `HasFactory` -- factory support
  - `SoftDeletes` -- soft-delete support
- **Casts:**
  - `active` -> `boolean`
- **Scopes:**
  - `scopeActive(Builder $query)` -- filters to only records where `active = true`
- **WHERE/WHEN it's used:**
  - **Created:** `PartCategoryActions`, `PartCategoryController`, `StorePartCategoryRequest`
  - **Read:** `StockUiController` (populates category dropdown lists for create/edit forms), `StorePartRequest`/`UpdatePartRequest` (validates `part_category_id` exists), `PartController` (listing with category filters)
  - **Relationship access:** `Part->category`

---

### Supplier

- **File:** `app/Models/Supplier.php`
- **What it is:** An external company or vendor that sells parts to the organisation.
- **Database table:** `suppliers`
- **Key fields (from migration `2026_08_08_000001_create_stock_catalog_tables.php`):**
  - `id` -- auto-incrementing bigint primary key
  - `name` (string, max 150) -- supplier company name
  - `nif` (string, max 30, nullable, unique) -- Portuguese tax identification number (NIF)
  - `contact` (string, max 100, nullable) -- contact person name or phone
  - `email` (string, max 150, nullable) -- contact email
  - `address` (text, nullable) -- physical address
  - `avg_lead_time_days` (integer, nullable) -- average delivery lead time in days
  - `created_at` / `updated_at` -- timestamps
  - `deleted_at` -- soft delete timestamp
- **Relationships:**
  - `parts(): BelongsToMany(Part)` -- all Parts supplied by this Supplier (junction table: `part_supplier`; pivot fields: `price`, `supplier_ref`, `lead_time_days`; timestamps)
- **Traits:**
  - `HasFactory` -- factory support
  - `SoftDeletes` -- soft-delete support
- **Casts:**
  - `avg_lead_time_days` -> `integer`
- **Scopes:** None defined in the model.
- **WHERE/WHEN it's used:**
  - **Created:** `CreateSupplierAction`, `SupplierController`, `StoreSupplierRequest`
  - **Read:** `SupplierController` (listing with search), `PartController` (listing suppliers for a part), `PartService` (includes suppliers in part queries)
  - **Relationship access:** `Part->suppliers`

---

### TaxRate

- **File:** `app/Models/TaxRate.php`
- **What it is:** A VAT or sales-tax percentage applied to parts pricing.
- **Database table:** `tax_rates`
- **Key fields (from migration `2026_08_08_000001_create_stock_catalog_tables.php`):**
  - `id` -- auto-incrementing bigint primary key
  - `name` (string, max 100) -- display name (e.g., "Standard 23%", "Reduced 13%")
  - `percent` (decimal 5,2) -- the tax percentage value (e.g., 23.00)
  - `is_default` (boolean, default `false`) -- whether this is the default rate for new parts
  - `active` (boolean, default `true`) -- whether the rate is active
  - `created_at` / `updated_at` -- timestamps
  - `deleted_at` -- soft delete timestamp
- **Relationships:**
  - `parts(): HasMany(Part)` -- all Part records using this tax rate (FK: `tax_rate_id`)
- **Traits:**
  - `HasFactory` -- factory support
  - `SoftDeletes` -- soft-delete support
- **Casts:**
  - `percent` -> `decimal:2`
  - `is_default` -> `boolean`
  - `active` -> `boolean`
- **Scopes:**
  - `scopeActive(Builder $query)` -- filters to only records where `active = true`
  - `scopeDefault(Builder $query)` -- filters to only records where `is_default = true`
- **WHERE/WHEN it's used:**
  - **Created/Updated:** `TaxRateActions` (also handles ensuring only one default rate exists at a time), `TaxRateController`, `StorePartRequest`/`UpdatePartRequest` (validates `tax_rate_id` exists)
  - **Read:** `StockUiController` (populates tax rate dropdown lists), `TaxRateController` (listing), `AnalyticsDashboardService`
  - **Relationship access:** `Part->taxRate`, `Part->priceWithVat()`

---

### StockMovement

- **File:** `app/Models/StockMovement.php`
- **What it is:** A single inventory transaction -- a record of parts coming in, going out, being returned, or being manually adjusted.
- **Database table:** `stock_movements`
- **Key fields (from migration `2026_08_08_000002_create_stock_movements_table.php`):**
  - `id` -- auto-incrementing bigint primary key
  - `part_id` (FK -> `parts.id`) -- the Part that was moved
  - `ticket_id` (FK -> `tickets.id`, nullable) -- the Ticket that triggered this movement (if applicable)
  - `equipment_id` (FK -> `equipments.id`, nullable) -- the Equipment involved (if applicable)
  - `user_id` (FK -> `users.id`, nullable) -- the user who performed the movement
  - `movement_type` (enum: `in`, `out`, `adjust`, `return`) -- the type of stock movement
  - `quantity` (integer) -- the quantity moved (always positive; direction determined by `movement_type`)
  - `reason` (string, max 255, nullable) -- human-readable reason for the movement
  - `unit_price_snapshot` (decimal 10,2, nullable) -- a snapshot of the part's `cost_price` at the time of the movement
  - `stock_after` (integer) -- the resulting `current_stock` of the part after this movement was applied
  - `created_at` / `updated_at` -- timestamps
- **Relationships:**
  - `part(): BelongsTo(Part)` -- the Part that was moved
  - `ticket(): BelongsTo(Ticket)` -- the Ticket associated with this movement (nullable)
  - `equipment(): BelongsTo(Equipment)` -- the Equipment involved (nullable)
  - `user(): BelongsTo(User)` -- the user who performed the movement (nullable)
- **Traits:**
  - `HasFactory` -- factory support
- **Casts:**
  - `quantity` -> `integer`
  - `stock_after` -> `integer`
  - `unit_price_snapshot` -> `decimal:2`
- **Helper methods:**
  - `delta(): int` -- calculates the effective change to stock: positive for `In` and `Return`, negative for `Out`, and the raw signed value for `Adjust`. Throws `InvalidArgumentException` for invalid movement types. This is used to reconstruct stock levels or compute running totals.
- **Scopes:** None defined in the model.
- **WHERE/WHEN it's used:**
  - **Created:** `StockMovementService` (the main service that creates stock movements, updates `Part.current_stock`, and enforces stock constraints); `StockMovementController` (API endpoints for listing/creating movements)
  - **Read:** `StockMovementController` (paginated listing with part and user eager-loading), `StockDashboardService` (aggregation queries for dashboard), `AnalyticsDashboardService` (stock movement counts and values)
  - **Relationship access:** `Part->movements`, `Ticket`, `Equipment`, `User`

---

### TICKETS & MAINTENANCE

---

### Ticket

- **File:** `app/Models/Ticket.php`
- **What it is:** The central record of a maintenance request or incident -- someone reports a problem, it gets assigned, a technician works on it, and eventually it is resolved and closed.
- **Database table:** `tickets`
- **Key fields (from migration `0001_01_01_000005_create_tickets_table.php` + later migrations):**
  - `id` -- auto-incrementing bigint primary key
  - `reference` (string, max 30, unique) -- auto-generated ticket reference (e.g., `TKT-20260902153000-ABC12`)
  - `equipment_id` (FK -> `equipments.id`, nullable) -- the Equipment this ticket relates to
  - `user_id` (FK -> `users.id`, nullable) -- the User who reported/created this ticket
  - `assigned_to` (FK -> `users.id`, nullable) -- the User assigned as technician
  - `room_id` (FK -> `rooms.id`, nullable) -- the Room where the issue is located
  - `status_id` (FK -> `ticket_statuses.id`, nullable) -- the current TicketStatus
  - `title` (string, max 150) -- ticket title/summary
  - `description` (text) -- detailed description of the issue
  - `priority` (enum: `baixa`, `média`, `alta`, `crítica`; default `média`) -- priority level
  - `urgent` (boolean, default `false`) -- urgent flag
  - `reporter_name` (string, max 150, nullable) -- name of the person reporting (if not a system user)
  - `reporter_contact` (string, max 150, nullable) -- contact info for the reporter
  - `source` (string, max 20, default `web`) -- how the ticket was submitted
  - `opened_at` (timestamp, nullable) -- when the ticket was opened
  - `assigned_at` (timestamp, nullable) -- when the ticket was assigned to a technician
  - `first_response_at` (timestamp, nullable) -- when the first response was made
  - `in_progress_at` (timestamp, nullable) -- when work started
  - `resolved_at` (timestamp, nullable) -- when the issue was resolved
  - `closed_at` (timestamp, nullable) -- when the ticket was closed
  - `reopened_at` (timestamp, nullable) -- when the ticket was reopened
  - `scheduled` (boolean, default `false`) -- whether this is a scheduled maintenance ticket
  - `scheduled_at` (timestamp, nullable) -- when the work is scheduled to start
  - `scheduled_end` (timestamp, nullable) -- when the work is scheduled to end
  - `due_at` (timestamp, nullable) -- SLA due date
  - `sla_breached` (boolean, default `false`) -- whether the SLA has been breached
  - `resolution_summary` (string, max 255, nullable) -- short summary of the resolution
  - `resolution` (text, nullable) -- detailed resolution description
  - `technical_report` (longText, nullable) -- detailed technical report
  - `estimated_minutes` (unsigned integer, nullable) -- estimated time to resolve
  - `minutes_spent` (unsigned integer, nullable) -- actual time spent
  - `estimated_cost` (decimal 10,2, nullable) -- estimated cost
  - `actual_cost` (decimal 10,2, nullable) -- actual cost
  - `resolved_by` (FK -> `users.id`, nullable) -- who resolved the ticket
  - `closed_by` (FK -> `users.id`, nullable) -- who closed the ticket
  - `budget_requested` (boolean, default `false`) -- whether a budget has been requested
  - `budget_requested_at` (timestamp, nullable) -- when the budget was requested
  - `budget_status` (enum: `pending`, `approved`, `rejected`, nullable) -- current budget approval status
  - `budget_amount` (decimal 10,2, nullable) -- requested budget amount
  - `budget_details` (JSON, nullable) -- detailed budget line items
  - `budget_feedback` (text, nullable) -- feedback from the budget approver
  - `budget_approved_at` (timestamp, nullable) -- when the budget was approved/decided
  - `budget_decided_at` (timestamp, nullable) -- when the budget decision was made
  - `budget_approved_by` (FK -> `users.id`, nullable) -- who approved the budget
  - `recommended_technician_id` (FK -> `users.id`, nullable) -- AI-recommended technician (added without a constraint by `2026_08_03_000001`, constrained by `2026_11_05_000001`; see that migration for why SQLite has no such constraint and `AIService::recommendTechnician()` enforces it instead)
  - `ai_recommendation_reason` (text, nullable) -- explanation of the AI recommendation (added by `2026_08_03_000001` migration)
  - `ai_processed_at` (timestamp, nullable) -- when the AI recommendation was generated (added by `2026_08_03_000001` migration)
  - `created_at` / `updated_at` -- timestamps
  - `deleted_at` -- soft delete timestamp
- **Relationships:**
  - `user(): BelongsTo(User)` -- the reporter (FK: `user_id`)
  - `technician(): BelongsTo(User, 'assigned_to')` -- the assigned technician (FK: `assigned_to`)
  - `status(): BelongsTo(TicketStatus)` -- the current status (FK: `status_id`)
  - `equipment(): BelongsTo(Equipment)` -- the related equipment (FK: `equipment_id`)
  - `room(): BelongsTo(Room)` -- the related room (FK: `room_id`)
  - `budgetApprovedBy(): BelongsTo(User, 'budget_approved_by')` -- the budget approver
  - `resolvedBy(): BelongsTo(User, 'resolved_by')` -- who resolved the ticket
  - `closedBy(): BelongsTo(User, 'closed_by')` -- who closed the ticket
  - `recommendedTechnician(): BelongsTo(User, 'recommended_technician_id')` -- AI-recommended technician
  - `workflowHistory(): HasMany(TicketWorkflowHistory)` -- all status-change history records
  - `comments(): HasMany(TicketComment)` -- all comments on this ticket
  - `attachments(): HasMany(TicketAttachment)` -- all file attachments
- **Traits:**
  - `Auditable` -- automatically writes an `Audit` row on every create/update/delete
  - `HasFactory` -- factory support
  - `SoftDeletes` -- soft-delete support
- **Casts:**
  - `opened_at`, `in_progress_at`, `closed_at`, `reopened_at`, `assigned_at`, `first_response_at`, `resolved_at`, `scheduled_at`, `scheduled_end`, `budget_requested_at`, `budget_decided_at`, `budget_approved_at`, `due_at`, `ai_processed_at` -> `datetime` (Carbon instances)
  - `scheduled` -> `boolean`
  - `budget_requested` -> `boolean`
  - `urgent` -> `boolean`
  - `sla_breached` -> `boolean`
  - `actual_cost` -> `decimal:2`
  - `estimated_cost` -> `decimal:2`
  - `budget_amount` -> `decimal:2`
  - `estimated_minutes` -> `integer`
  - `minutes_spent` -> `integer`
  - `budget_details` -> `array` (decoded from JSON)
- **Boot behavior (`booted()`):**
  - On `creating`: if `reference` is null, auto-generates one as `'TKT-' . now()->format('YmdHis') . '-' . strtoupper(substr(uniqid(), -5))`. This produces a reference like `TKT-20260902153000-ABC12`.
- **Accessors & helper methods:**
  - `budgetPauseMinutes(): Attribute` (protected) -- computed attribute that delegates to the `BudgetPauseMinutes` value object (`App\Domain\Ticket\ValueObjects\BudgetPauseMinutes`) for the minutes between `budget_requested_at` and `budget_decided_at`. Returns `0` if either timestamp is missing **or** the decision predates the request (clock skew / bad backdating), which the raw `diffInMinutes()` would otherwise report as a positive pause.
  - `hasStatus(TicketStatusEnum|string $status): bool` -- checks whether the ticket currently has the given status. Resolves the enum via `TicketStatusService`.
- **Scopes:**
  - `scopeOpen(Builder $query)` -- filters to only tickets whose `status_id` matches the "Open" status (resolved via `TicketStatusService` and `TicketStatusEnum::Open`)
  - `scopeForTechnician(Builder $query, int $technicianId)` -- filters to only tickets assigned to the given technician
- **Observer:** `TicketObserver` (registered by `Ticket::observe()` in `app/Providers/AppServiceProvider.php:152`):
  - `created` -- fires `TicketCreated` event (admins get the new-ticket notification); invalidates analytics cache.
  - `updated` -- if `status_id` changed, fires `TicketStatusChanged` event (workflow history row); invalidates analytics cache.
  - `deleted` / `restored` -- invalidates analytics cache.
- **Events fired (via Observer):**
  - `App\Events\TicketCreated` -- consumed by `SendTicketCreatedNotification`
  - `App\Events\TicketStatusChanged` -- consumed by `LogTicketWorkflowChange`
  Both are plain in-process events; nothing is broadcast over WebSockets.
- **WHERE/WHEN it's used:**
  - **Created:** `CreateTicketAction`, `CreatePublicTicketAction`, `CreatePreventiveTicketAction`, `ScheduleMaintenanceAction`, `SimulateTelemetry` (console command), `TicketController`
  - **Read:** `TicketRepository` (CRUD + search), `TicketSearchService`, `CalendarService` (calendar view), `AnalyticsDashboardService` (KPI calculations), `AnalyticsController` (exports), `TicketKpiQuery`, `MonthlyTicketsQuery`, `ScheduledEventsQuery`, `TechnicianAssignmentService`
  - **Relationship access:** `Equipment->tickets`, `Room->tickets`, `User->tickets`, `User->assignedTickets`, `TicketWorkflowHistory->ticket`, `TicketComment->ticket`, `TicketAttachment->ticket`

---

### TicketType

- **File:** `app/Models/TicketType.php`
- **What it is:** A high-level category for classifying tickets -- for example, "Break-Fix", "Preventive Maintenance", or "Installation".
- **Database table:** `ticket_types`
- **Key fields (from migration `0001_01_01_000005_create_tickets_table.php`):**
  - `id` -- auto-incrementing bigint primary key
  - `code` (string, max 50, unique) -- auto-generated code (e.g., `TYPE_68b5f1234abc`)
  - `name` (string, max 100, unique) -- type name
  - `description` (text, nullable) -- description of this ticket type
  - `notes` (text, nullable) -- additional notes
  - `active` (boolean, default `true`) -- whether the type is active
  - `created_at` / `updated_at` -- timestamps
  - `deleted_at` -- soft delete timestamp
- **Relationships:**
  - `statuses(): HasMany(TicketStatus)` -- all TicketStatus records belonging to this type (FK: `type_id`)
  - `tickets(): HasManyThrough(Ticket, TicketStatus)` -- all Tickets that have a status belonging to this type. The through relationship joins through `TicketStatus` on `type_id` (ticket_statuses.type_id) and `status_id` (tickets.status_id).
- **Traits:**
  - `HasFactory` -- factory support
  - `SoftDeletes` -- soft-delete support
- **Casts:**
  - `active` -> `boolean`
- **Boot behavior (`booted()`):**
  - On `creating`: if `code` is null, auto-generates one as `'TYPE_' . strtoupper(uniqid())`.
- **Scopes:** None defined in the model.
- **WHERE/WHEN it's used:**
  - The `TicketType` model is primarily used as a parent/container for `TicketStatus` records. It is not directly queried by controllers. Its `tickets()` relationship traverses `TicketStatus` -> `Ticket`.
  - **Relationship access:** `TicketStatus->type`

---

### TicketStatus

- **File:** `app/Models/TicketStatus.php`
- **What it is:** A single step or stage in a ticket's workflow -- for example, "Open", "In Progress", "Waiting for Parts", "Resolved", or "Closed".
- **Database table:** `ticket_statuses`
- **Key fields (from migration `0001_01_01_000005_create_tickets_table.php`):**
  - `id` -- auto-incrementing bigint primary key
  - `type_id` (FK -> `ticket_types.id`, nullable) -- the parent TicketType
  - `code` (string, max 50, unique) -- auto-generated code derived from the name (e.g., `OPEN`, `IN_PROGRESS`)
  - `name` (string, max 100, unique) -- status name
  - `description` (text, nullable) -- description of this status
  - `notes` (text, nullable) -- additional notes
  - `active` (boolean, default `true`) -- whether the status is active
  - `created_at` / `updated_at` -- timestamps
  - `deleted_at` -- soft delete timestamp
- **Relationships:**
  - `type(): BelongsTo(TicketType)` -- the parent TicketType (FK: `type_id`)
  - `tickets(): HasMany(Ticket)` -- all Tickets currently at this status (FK: `status_id`)
- **Traits:**
  - `HasFactory` -- factory support
  - `SoftDeletes` -- soft-delete support
- **Casts:**
  - `active` -> `boolean`
- **Boot behavior (`booted()`):**
  - On `creating`: if `code` is null, auto-generates one by sanitizing the name: `strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '_', $name), 0, 20))`. For example, a name of "In Progress" becomes `IN_PROGRESS`.
- **Scopes:** None defined in the model.
- **WHERE/WHEN it's used:**
  - **Created:** `TicketStatusService::getByName()` (creates statuses on-demand if they don't exist yet, using `firstOrCreate`)
  - **Read:** `TicketStatusService` (caches all status IDs by name), `TicketObserver` (looks up old/new status names when status changes), `LogTicketWorkflowChange` listener, `TicketController` (status listing), `AnalyticsDashboardService`
  - **Relationship access:** `Ticket->status`, `TicketType->statuses`, `TicketWorkflowHistory->originStatus`, `TicketWorkflowHistory->destinationStatus`

---

### TicketAttachment

- **File:** `app/Models/TicketAttachment.php`
- **What it is:** A file -- a photo, PDF, screenshot, or document -- that someone uploads to a ticket as evidence or reference.
- **Database table:** `ticket_attachments`
- **Key fields (from migration `0001_01_01_000008_create_ticket_attachments_table.php` + `2026_08_07_000001_add_reporter_fields_to_tickets_table.php`):**
  - `id` -- auto-incrementing bigint primary key
  - `ticket_id` (FK -> `tickets.id`) -- the parent Ticket
  - `user_id` (FK -> `users.id`, nullable) -- the User who uploaded this attachment
  - `original_name` (string, max 255) -- the original filename as uploaded by the user
  - `file_name` (string, max 255) -- the stored filename (renamed to avoid collisions)
  - `path` (string, max 1024) -- the file path on the storage disk
  - `disk` (string, max 50, default `public`) -- which storage disk (e.g., `public`, `local`, `s3`)
  - `extension` (string, max 20, nullable) -- file extension
  - `mime_type` (string, max 100) -- MIME type (e.g., `image/jpeg`, `application/pdf`)
  - `size` (unsigned bigint) -- file size in bytes
  - `checksum` (string, max 64, nullable) -- file checksum for integrity verification
  - `description` (text, nullable) -- optional description
  - `created_at` / `updated_at` -- timestamps
  - `deleted_at` -- soft delete timestamp
- **Relationships:**
  - `ticket(): BelongsTo(Ticket)` -- the parent Ticket
  - `user(): BelongsTo(User)` -- the User who uploaded this attachment
- **Traits:**
  - `HasFactory` -- factory support
  - `SoftDeletes` -- soft-delete support
- **Casts:**
  - `size` -> `integer`
- **Boot behavior (`booted()`):**
  - On `creating`: if `original_name` is null, sets it to `file_name` or `'file_' . uniqid()`.
  - On `deleting`: **automatically deletes the physical file from Storage**. Reads the `disk` and `path` attributes, checks if the file exists on that disk, and deletes it. This ensures no orphaned files remain on disk after a model deletion.
- **Accessors:**
  - `url(): Attribute` -- returns the full URL for the file via `Storage::disk($disk)->url($path)`. Used for download/view links.
  - `formattedSize(): Attribute` -- returns a human-readable file size string (e.g., "2.4 MB", "350 B") using `formatBytes()`.
  - `isImage(): Attribute` -- returns `true` if `mime_type` starts with `image/`.
  - `formatBytes(int $bytes): string` (private static) -- helper to convert byte count to human-readable format (B, KB, MB, GB, etc.).
- **Scopes:** None defined in the model.
- **WHERE/WHEN it's used:**
  - **Created:** `TicketAttachmentController` (handles file upload and creates the record), `PublicTicketController` (allows anonymous ticket creation with attachments)
  - **Read:** `Ticket->attachments` relationship, ticket detail views
  - **Relationship access:** `Ticket->attachments`

---

### TicketComment

- **File:** `app/Models/TicketComment.php`
- **What it is:** A message, note, or reply in the conversation thread attached to a ticket.
- **Database table:** `ticket_comments`
- **Key fields (from migration `0001_01_01_000009_create_ticket_comments_table.php`):**
  - `id` -- auto-incrementing bigint primary key
  - `ticket_id` (FK -> `tickets.id`) -- the parent Ticket
  - `user_id` (FK -> `users.id`) -- the User who wrote the comment
  - `parent_id` (FK -> `ticket_comments.id`, nullable) -- the parent comment for threaded replies
  - `comment` (text) -- the comment text
  - `is_internal` (boolean, default `false`) -- whether the comment is internal (visible only to staff) or public
  - `edited_at` (timestamp, nullable) -- when the comment was last edited
  - `created_at` / `updated_at` -- timestamps
  - `deleted_at` -- soft delete timestamp
- **Relationships:**
  - `ticket(): BelongsTo(Ticket)` -- the parent Ticket
  - `user(): BelongsTo(User)` -- the User who wrote the comment
  - `parent(): BelongsTo(self, 'parent_id')` -- the parent comment (for threaded replies)
  - `replies(): HasMany(self, 'parent_id')` -- child comments (replies to this comment)
- **Traits:**
  - `HasFactory` -- factory support
  - `SoftDeletes` -- soft-delete support
- **Casts:**
  - `is_internal` -> `boolean`
  - `edited_at` -> `datetime`
- **Accessors:**
  - `timeAgo(): Attribute` -- returns a relative time string (e.g., "5 minutes ago") via `$this->created_at->diffForHumans()`.
  - `isEdited(): Attribute` -- returns `true` if `updated_at` is after `created_at`, indicating the comment was modified after initial posting.
- **Scopes:**
  - `scopeChronological(Builder $query)` -- orders comments oldest-first (`created_at asc`) so the conversation reads naturally from top to bottom.
- **WHERE/WHEN it's used:**
  - **Created/Updated:** `TicketCommentController` (CRUD for comments)
  - **Read:** `Ticket->comments` relationship, ticket detail views
  - **Relationship access:** `Ticket->comments`, `TicketComment->parent`, `TicketComment->replies`

---

### TicketWorkflowHistory

- **File:** `app/Models/TicketWorkflowHistory.php`
- **What it is:** A timestamped log entry recording every time a ticket moves from one status to another.
- **Database table:** `ticket_workflow_history`
- **Key fields (from migration `0001_01_01_000005_create_tickets_table.php`):**
  - `id` -- auto-incrementing bigint primary key
  - `ticket_id` (FK -> `tickets.id`) -- the Ticket this transition belongs to
  - `origin_status_id` (FK -> `ticket_statuses.id`, nullable) -- the TicketStatus before the transition
  - `destination_status_id` (FK -> `ticket_statuses.id`, nullable) -- the TicketStatus after the transition
  - `technician_id` (FK -> `users.id`, nullable) -- the User (technician) who made the change
  - `comment` (text, nullable) -- an optional comment explaining why the change was made
  - `created_at` / `updated_at` -- timestamps
- **Relationships:**
  - `ticket(): BelongsTo(Ticket)` -- the parent Ticket (FK: `ticket_id`)
  - `originStatus(): BelongsTo(TicketStatus, 'origin_status_id')` -- the status before the transition
  - `destinationStatus(): BelongsTo(TicketStatus, 'destination_status_id')` -- the status after the transition
  - `technician(): BelongsTo(User, 'technician_id')` -- the User who performed the transition
- **Traits:**
  - `HasFactory` -- factory support
  - **No SoftDeletes** -- workflow history records are never soft-deleted; they persist permanently as an audit trail.
- **Scopes:**
  - `scopeChronological(Builder $query)` -- orders entries newest-first (`created_at desc`) so the most recent change appears at the top.
- **Accessors:**
  - `transitionLabel(): Attribute` -- generates a readable string like `"Open -> In Progress"` by joining the origin and destination status names with `" ➔ "`. Falls back to `"N/A"` if a status is missing.
  - `timeAgo(): Attribute` -- returns a relative time string via `$this->created_at->diffForHumans()`.
- **WHERE/WHEN it's used:**
  - **Created:** `LogTicketWorkflowChange` listener (listens for `TicketStatusChanged`, fired by `TicketObserver`) -- the only writer of workflow history records for a ticket's status changes.
  - **Read:** `Ticket->workflowHistory` relationship, ticket detail views (workflow timeline)
  - **Relationship access:** `Ticket->workflowHistory`

---

### MaintenancePlan

- **File:** `app/Models/MaintenancePlan.php`
- **What it is:** A preventive-maintenance schedule defined for a specific piece of equipment -- for example, "Replace filters every 90 days" or "Inspect wiring monthly".
- **Database table:** `maintenance_plans`
- **Key fields (from migration `2026_08_08_000003_create_maintenance_plans_table.php`):**
  - `id` -- auto-incrementing bigint primary key
  - `equipment_id` (FK -> `equipments.id`) -- the Equipment this plan applies to
  - `name` (string, max 150) -- plan name
  - `interval_type` (enum: `days`, `usage_hours`, `cycles`; default `days`) -- how the recurrence interval is measured
  - `interval_value` (integer) -- the recurrence interval value (e.g., `30` for every 30 days)
  - `description` (text, nullable) -- detailed description of the maintenance plan
  - `active` (boolean, default `true`) -- whether the plan is active
  - `created_at` / `updated_at` -- timestamps
  - `deleted_at` -- soft delete timestamp
- **Pivot table `maintenance_plan_part`:**
  - `maintenance_plan_id` (FK -> `maintenance_plans.id`)
  - `part_id` (FK -> `parts.id`)
  - `expected_quantity` (integer, default `1`) -- how many of this part are expected to be consumed
  - `created_at` / `updated_at` -- timestamps
  - Unique constraint on `(maintenance_plan_id, part_id)`
- **Relationships:**
  - `equipment(): BelongsTo(Equipment)` -- the Equipment this plan is for
  - `parts(): BelongsToMany(Part)` -- all Parts expected to be consumed by this plan (junction table: `maintenance_plan_part`; pivot field: `expected_quantity`; timestamps)
- **Traits:**
  - `HasFactory` -- factory support
  - `SoftDeletes` -- soft-delete support
- **Casts:**
  - `interval_value` -> `integer`
  - `active` -> `boolean`
- **Scopes:**
  - `scopeActive(Builder $query)` -- filters to only records where `active = true`
- **WHERE/WHEN it's used:**
  - **Created:** `MaintenancePlanController`, `MaintenancePlanActions`, `StoreMaintenancePlanRequest`/`UpdateMaintenancePlanRequest`
  - **Read:** `MaintenancePlanController` (listing with eager-loaded equipment), `Equipment->maintenancePlans` relationship
  - **Relationship access:** `Equipment->maintenancePlans`, `Part->maintenancePlans`

---

### PEOPLE

---

### User

- **File:** `app/Models/User.php`
- **What it is:** A person who uses the system -- an admin, a technician, or a regular employee who can report issues.
- **Database table:** `users`
- **Key fields (from migration `0001_01_01_000000_create_users_table.php` + later migrations):**
  - `id` -- auto-incrementing bigint primary key
  - `profile_id` (FK -> `user_profiles.id`, nullable) -- the UserProfile (role) assigned to this user
  - `name` (string, max 150) -- display name
  - `email` (string, max 255, unique) -- email address (used for login)
  - `locale` (string, max 10, nullable, default `pt-PT`) -- language locale preference (added by `2026_08_10` migration)
  - `theme` (string, max 64, nullable) -- theme preference identifier (added by `2026_08_29` migration)
  - `password` (string) -- hashed password (cast to `hashed`)
  - `avatar_path` (string, max 255, nullable) -- path to profile picture
  - `avatar_disk` (string, max 50, default `public`) -- storage disk for the avatar
  - `active` (boolean, default `true`) -- whether the user account is active
  - `api_token` (string, max 80, nullable, unique) -- HMAC SHA-256 hashed API token for programmatic access
  - `token_created_at` (timestamp, nullable) -- when the API token was created
  - `remember_token` (string) -- Laravel remember-me token
  - `email_verified_at` (timestamp, nullable) -- when the email was verified
  - `password_changed_at` (timestamp, nullable) -- when the password was last changed
  - `last_login_at` (timestamp, nullable) -- when the user last logged in
  - `last_login_ip` (string, max 45, nullable) -- IP address of the last login (stamped by `UserService::createToken()`)
  - `created_at` / `updated_at` -- timestamps
  - `deleted_at` -- soft delete timestamp
- **Hidden fields:**
  - `password` -- never serialized to arrays/JSON
  - `remember_token` -- never serialized
  - `api_token` -- never serialized
- **Relationships:**
  - `tickets(): HasMany(Ticket, 'user_id')` -- all Tickets reported/created by this user
  - `assignedTickets(): HasMany(Ticket, 'assigned_to')` -- all Tickets assigned to this user as technician
  - `profile(): BelongsTo(UserProfile, 'profile_id')` -- the role profile (Admin/Technician/User)
- **Traits:**
  - `HasFactory` -- factory support
  - `Notifiable` -- adds the `notify()` method from Laravel's notification system
  - `SoftDeletes` -- soft-delete support
- **Casts:**
  - `email_verified_at` -> `datetime`
  - `token_created_at` -> `datetime`
  - `password_changed_at` -> `datetime`
  - `last_login_at` -> `datetime`
  - `active` -> `boolean`
  - `password` -> `hashed` (automatic hashing on set)
- **No lockout columns:** failed-login counting lives in the cache (`AuthController::login()` keys `login_attempts:sha1(ip|email)` with the lockout TTL). The `users` table used to carry `login_attempts`/`locked_until` that nothing ever wrote while `UserResource` published them; `2026_11_05_000002_drop_lockout_columns_from_users_table` removed them.
- **Observer:** `UserObserver` (registered in `AppServiceProvider::boot()` at line 146):
  - `creating` / `updating` -- calls `ensureValidProfile()`: validates that the assigned `profile_id` maps to a valid `UserRoleEnum` case (Admin, Technician, User). If the profile is missing or invalid, it ensures the default "User" profile exists via `UserProfile::firstOrCreate()` and assigns it.
- **Static helper methods:**
  - `getAvailableRoles(): array` -- returns all valid role names from `UserRoleEnum::cases()`
  - `isValidProfile(string $name): bool` -- checks if a given string is a valid `UserRoleEnum` case
  - `hashToken(string $token): string` -- generates an HMAC SHA-256 hash of the given token using `config('app.key')` as the secret. Used for API token storage and lookup.
- **Role-checking instance methods:**
  - `isAdmin(): bool` -- returns `true` if the user's profile name equals `UserRoleEnum::Admin->value`
  - `isTechnician(): bool` -- returns `true` if the user's profile name equals `UserRoleEnum::Technician->value`
  - `isCommonUser(): bool` -- returns `true` if the user's profile name equals `UserRoleEnum::User->value`
- **Scopes:**
  - `scopeActive(Builder $query)` -- filters to only users where `active = true`
  - `scopeTechnicians(Builder $query)` -- filters to users whose profile name is "Technician"
- **WHERE/WHEN it's used:**
  - **Created:** `CreateUserAction`, `RegisterController`, `AuthController`, `RegisterRequest`
  - **Read:** `UserRepository` (CRUD + search, listing with profile eager-loading), `AdminUserController` (admin management), `TechnicianAssignmentService` (finding available technicians), `AIService` (selecting technician candidates for AI recommendation), `NotificationCreatorService` (finding admins for system notifications), `PasswordResetService` (email lookup), `CustomAuthMiddleware` (API token authentication), `ActivityFeedController`
  - **Relationship access:** `Ticket->user`, `Ticket->technician`, `Audit->user`, `Notification->user`, `StockMovement->user`, `TicketComment->user`, `TicketAttachment->user`, `TicketWorkflowHistory->technician`, `UserPreference->user`, `UserProfile->users`

---

### UserProfile

- **File:** `app/Models/UserProfile.php`
- **What it is:** A role definition -- a named group that controls what a user can see and do in the system.
- **Database table:** `user_profiles`
- **Key fields (from migration `0001_01_01_000000_create_users_table.php`):**
  - `id` -- auto-incrementing bigint primary key
  - `name` (string, max 100, unique) -- role name (must match a `UserRoleEnum` case: `Admin`, `Technician`, or `User`)
  - `description` (text, nullable) -- description of this role's permissions
  - `active` (boolean, default `true`) -- whether the role is active
  - `created_at` / `updated_at` -- timestamps
  - `deleted_at` -- soft delete timestamp
- **Relationships:**
  - `users(): HasMany(User, 'profile_id')` -- all Users assigned this role
- **Traits:**
  - `HasFactory` -- factory support
  - `SoftDeletes` -- soft-delete support
- **Casts:**
  - `active` -> `boolean`
- **Scopes:** None defined in the model.
- **WHERE/WHEN it's used:**
  - **Created:** `UserObserver::ensureValidProfile()` (auto-creates default "User" profile if missing), `RegisterController` (ensures "User" profile exists during registration), `UserService`
  - **Read:** `AdminUserController` (lists profiles with user counts), `User->profile` relationship, `UserObserver` (validates profile assignments), `StoreUserRequest`/`UpdateUserRequest` (validates `profile_id` exists), `AnalyticsDashboardService`
  - **Relationship access:** `User->profile`, `UserProfile->users`

---

### UserPreference

- **File:** `app/Models/UserPreference.php`
- **What it is:** A user's personal display and formatting preferences -- how dates, numbers, currency, and language appear to them.
- **Database table:** `user_preferences`
- **Key fields (from migrations `2026_08_12_000001` through `2026_09_02_000001`):**
  - `id` -- auto-incrementing bigint primary key
  - `user_id` (FK -> `users.id`, unique) -- the User this preference belongs to (one-to-one)
  - `language` (string, max 10, default `pt`) -- language code (e.g., `pt`, `en`, `fr`)
  - `currency` (string, max 3, default `EUR`) -- ISO 4217 currency code (e.g., `EUR`, `USD`, `GBP`)
  - `date_format` (string, max 20, default `d/m/Y`) -- PHP date format string (e.g., `d/m/Y`, `m/d/Y`, `Y-m-d`)
  - `time_format` (string, max 20, nullable, default `H:i`) -- PHP time format string (e.g., `H:i`, `g:i A`)
  - `number_format` (string, max 191, nullable, default `{"decimal":".","thousand":","}`) -- JSON string with `decimal` and `thousand` separator characters
  - `created_at` / `updated_at` -- timestamps
- **Relationships:**
  - `user(): BelongsTo(User)` -- the User who owns these preferences
- **Traits:**
  - `HasFactory` -- factory support
- **Casts:**
  - `user_id` -> `integer`
  - `language` -> `string`
  - `currency` -> `string`
  - `date_format` -> `string`
  - `time_format` -> `string`
  - `number_format` -> `string`
- **Scopes:** None defined in the model.
- **WHERE/WHEN it's used:**
  - **Created/Updated:** `PreferencesService` (fetches and updates preferences via `updateOrCreate`)
  - **Read:** `LocalizationService` (reads preferences to format dates, currency, numbers in views), `CurrencyRateService` (uses currency preference to determine exchange rate), Blade directives (`@money`, `@number`, `@date`, etc.)
  - **Migration:** `2026_08_12_000002_populate_user_preferences.php` seeds default preferences for all existing users on migration run.

---

### SYSTEM

---

### Notification

- **File:** `app/Models/Notification.php`
- **What it is:** An in-app alert or message sent to a specific user -- for example, "Your ticket TKT-123 has been assigned to a technician."
- **Database table:** `notifications`
- **Key fields (from migration `2026_07_09_100000_create_notifications_table.php` + `2026_08_08_000004_add_low_stock_notification_type.php`):**
  - `id` -- auto-incrementing bigint primary key
  - `user_id` (FK -> `users.id`) -- the recipient User
  - `title` (string, max 150) -- notification title/subject
  - `message` (text) -- notification body text
  - `type` (enum: `ticket_created`, `ticket_updated`, `ticket_assigned`, `ticket_closed`, `comment_added`, `attachment_added`, `budget_requested`, `budget_request`, `budget_submitted`, `budget_approved`, `budget_rejected`, `budget_auto_approved`, `priority_override`, `low_stock`, `system`; default `system`) -- the notification category
  - `priority` (enum: `low`, `normal`, `high`, `critical`; default `normal`) -- priority level
  - `is_read` (boolean, default `false`) -- whether the notification has been read
  - `read_at` (timestamp, nullable) -- when the notification was marked as read
  - `link` (string, max 2048, nullable) -- URL to navigate to when the notification is clicked
  - `notifiable_type` (string, max 150, nullable) -- polymorphic type of the related entity (e.g., `App\Models\Ticket`)
  - `notifiable_id` (unsigned bigint, nullable) -- polymorphic ID of the related entity
  - `data` (JSON, nullable) -- additional arbitrary data payload
  - `expires_at` (timestamp, nullable) -- when the notification expires and should no longer be shown
  - `created_at` / `updated_at` -- timestamps
  - `deleted_at` -- soft delete timestamp
- **Relationships:**
  - `user(): BelongsTo(User)` -- the recipient
  - `notifiable(): MorphTo` -- the polymorphic entity this notification is about (could be a Ticket, Equipment, Part, etc.)
- **Traits:**
  - `HasFactory` -- factory support
  - `SoftDeletes` -- soft-delete support
- **Casts:**
  - `is_read` -> `boolean`
  - `read_at` -> `datetime`
  - `expires_at` -> `datetime`
  - `data` -> `array` (decoded from JSON)
- **Helper methods:**
  - `markAsRead(): bool` -- sets `is_read = true` and saves
  - `markAsUnread(): bool` -- sets `is_read = false` and saves
- **Scopes:**
  - `scopeUnread(Builder $query)` -- filters to only notifications where `is_read = false`
  - `scopeRead(Builder $query)` -- filters to only notifications where `is_read = true`
- **WHERE/WHEN it's used:**
  - **Created:** `NotificationCreatorService` (creates individual notifications or batch-inserts via `Notification::insert()`), `ExportPdfJob`/`ExportExcelJob`/`ExportCsvJob`/`ExportStockCostsPdfJob`/`ExportEquipmentQrPdfJob` (create success/failure notifications after export jobs complete), `SendTicketCreatedNotification` listener (creates notification for admins when a ticket is created), `SendTicketStatusNotification` listener (creates notification when ticket status changes), `NotifyAssignedTechnician` listener (creates notification when a ticket is assigned), `LowStockAlertService` (creates `low_stock` notifications)
  - **Read:** `NotificationController` (listing and marking as read), `AnalyticsDashboardService` (notification counts), ticket detail views
  - **Relationship access:** `Ticket`, `User`

---

### SystemSetting

- **File:** `app/Models/SystemSetting.php`
- **What it is:** A global configuration key-value pair for the application -- a setting that administrators can change without touching code.
- **Database table:** `system_settings`
- **Key fields (from migration `2026_08_05_000002_create_system_settings_table.php`):**
  - `id` -- auto-incrementing bigint primary key
  - `key` (string, unique) -- setting name/key (e.g., `company_name`, `items_per_page`)
  - `value` (string) -- the setting value
  - `created_at` / `updated_at` -- timestamps
- **Relationships:** None.
- **Traits:** None (plain Model).
- **Casts:** None.
- **Scopes:** None defined in the model.
- **WHERE/WHEN it's used:**
  - **Read/Updated/Deleted:** `SystemSettingsService` (the central service that manages all system settings; uses `pluck('value', 'key')` to load all settings, `updateOrCreate` to save, `where('key', ...)->delete()` to remove). The service is applied at boot time via `AppServiceProvider::boot()` at line 94: `$this->app->make(SystemSettingsService::class)->applyOverrides()`.
  - **Read:** Blade directives and view helpers that consume settings from the service.

---

### ThemeSetting

- **File:** `app/Models/ThemeSetting.php`
- **What it is:** A UI appearance or theming key-value pair -- controls things like primary colour, border radius, font size, and dark/light mode.
- **Database table:** `theme_settings`
- **Key fields (from migration `2026_08_05_000001_create_theme_settings_table.php`):**
  - `id` -- auto-incrementing bigint primary key
  - `key` (string, unique) -- setting name/key (e.g., `primary_color`, `border_radius`)
  - `value` (string) -- the setting value (e.g., `#3b82f6`, `8px`)
  - `created_at` / `updated_at` -- timestamps
- **Relationships:** None.
- **Traits:** None (plain Model).
- **Casts:** None.
- **Scopes:** None defined in the model.
- **WHERE/WHEN it's used:**
  - **Read/Updated:** `ThemePresetService` (manages theme presets and customizations; uses `updateOrCreate` to save theme values). The `ThemeController` reads and writes these settings via the service.
  - **Read:** Frontend theme application (reads key-value pairs to apply as CSS variables or theme tokens).

---

### ExchangeRate

- **File:** `app/Models/ExchangeRate.php`
- **What it is:** A stored currency conversion rate between two currencies -- for example, 1 EUR = 1.08 USD.
- **Database table:** `currency_rates`
- **Key fields (from migration `2026_08_30_000001_create_currency_rates_table.php`):**
  - `id` -- auto-incrementing bigint primary key
  - `base_currency` (string, max 3) -- ISO 4217 base currency code (e.g., `EUR`)
  - `target_currency` (string, max 3) -- ISO 4217 target currency code (e.g., `USD`)
  - `rate` (decimal 18,8) -- the exchange rate (to 8 decimal places for precision)
  - `fetched_at` (timestamp) -- when the rate was fetched from the external API
  - `created_at` / `updated_at` -- timestamps
- **Relationships:** None.
- **Traits:** None (plain Model).
- **Casts:**
  - `base_currency` -> `string`
  - `target_currency` -> `string`
  - `rate` -> `decimal:8`
  - `fetched_at` -> `datetime`
- **Scopes:** None defined in the model.
- **WHERE/WHEN it's used:**
  - **Created/Updated:** `CurrencyRateService` (fetches fresh rates from an external API via `updateOrCreate`, ensuring one rate per base-target pair)
  - **Read:** `CurrencyRateService::convert()` (reads rates to convert prices between currencies based on user preferences)
  - **Scheduled command:** `currency:update-rates` (an Artisan command that runs twice daily to pull fresh rates and store them)

---

## Technical Notes

### Auditing Integration

The custom `App\Traits\Auditable` trait (`app/Traits/Auditable.php`) is the engine that powers the audit trail. When applied to a model:

1. **Boot listener:** `bootAuditable()` registers closures on the `created`, `updated`, and `deleted` model events.
2. **On each event:** `createAudit()` resolves the current user (via `auth()->id()`, falling back to API token lookup via `User::hashToken()`), captures old/new values, and creates an `Audit` record with the request URL, IP, and user agent.
3. **Caching:** The resolved user ID is cached per-request in a static property (`$resolvedUserId`) to avoid repeated auth lookups. For long-running processes (queues, Octane), call `resetResolvedUserId()`.
4. **Models using Auditable:** `Equipment`, `Part`, `Room`, `Ticket`

### Immutability Protection (Triple Layer)

Audit records are protected against modification/deletion at three levels:
1. **Model-level:** `Audit::booted()` throws `LogicException` on `updating` and `deleting`
2. **Observer-level:** `AuditObserver` (registered in `AppServiceProvider`) throws `LogicException` on `updating`, `deleting`, and `forceDeleting`
3. **Database-level:** SQL triggers (`audits_prevent_update`, `audits_prevent_delete`) throw errors on UPDATE and DELETE at the engine level (supports both MySQL and SQLite)

### Storage Lifecycle Hooks

`TicketAttachment` automatically deletes the associated file from Storage when deleted from the database (via the `deleting` model event in `booted()`). The hook checks if the file exists on the configured disk before attempting deletion.

### Soft Deletes

Most domain entities use Laravel's `SoftDeletes` trait. When a model is soft-deleted, it receives a `deleted_at` timestamp and is excluded from default query results. Check soft-delete interactions before writing raw SQL queries.

**Models with SoftDeletes:** `Equipment`, `EquipmentCategory`, `Part`, `PartCategory`, `Room`, `Supplier`, `TaxRate`, `MaintenancePlan`, `Ticket`, `TicketType`, `TicketStatus`, `TicketAttachment`, `TicketComment`, `User`, `UserProfile`, `Notification`

**Models without SoftDeletes:** `Audit`, `StockMovement`, `TicketWorkflowHistory`, `SystemSetting`, `ThemeSetting`, `ExchangeRate`, `UserPreference`

### Observer Registration

All observers are registered in `AppServiceProvider::boot()` via `registerObservers()`:
- `Ticket::observe(TicketObserver::class)`
- `User::observe(UserObserver::class)`
- `Audit::observe(AuditObserver::class)`

### Event Listeners

Registered in `EventServiceProvider::$listen`:
- `TicketCreated` -> `SendTicketCreatedNotification` (creates in-app notification for admins)
- `TicketStatusChanged` -> `LogTicketWorkflowChange` (creates `TicketWorkflowHistory` record)
- `TicketStatusUpdated` -> `SendTicketStatusNotification`, `NotifyAssignedTechnician`

### Policy Authorization

All major domain models have authorization policies registered in `AppServiceProvider::registerPolicies()`:
`Ticket`, `User`, `Equipment`, `Room`, `UserProfile`, `Part`, `Supplier`, `StockMovement`, `TaxRate`, `PartCategory`, `MaintenancePlan`
