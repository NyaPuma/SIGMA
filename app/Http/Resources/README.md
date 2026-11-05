# app/Http/Resources

API resource (transformer) classes for converting Eloquent models to JSON responses.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The Translation Desk" that converts internal database data into a clean format for the outside world.

## Purpose

Each resource class defines how a model is serialized for API output, following Laravel's API Resource pattern. They provide consistent JSON structure across all endpoints. Resources are returned as either a single instance (`new XxxResource($model)`) or a collection (`XxxResource::collection($collection)`).

Resources also use Laravel's `whenLoaded()` guard to conditionally embed nested relations **only when they have been eager-loaded** by the controller — keeping responses lean but capable of nesting related data on demand.

## Structure

Resources map directly to their corresponding models:

| Resource | Model it formats |
|----------|------------------|
| `AuditResource` | `Audit` |
| `UserResource` | `User` |
| `UserProfileResource` | `UserProfile` |
| `EquipmentResource` | `Equipment` |
| `RoomResource` | `Room` |
| `PartResource` | `Part` |
| `PartCategoryResource` | `PartCategory` |
| `SupplierResource` | `Supplier` |
| `StockMovementResource` | `StockMovement` |
| `TicketResource` | `Ticket` |
| `TicketCommentResource` | `TicketComment` |
| `TicketAttachmentResource` | `TicketAttachment` |
| `TaxRateResource` | `TaxRate` |
| `MaintenancePlanResource` | `MaintenancePlan` |
| `NotificationResource` | `Notification` |

---

## Detailed per-resource reference

### `AuditResource`

**File:** [`app/Http/Resources/AuditResource.php`](AuditResource.php)

**Formats:** `App\Models\Audit` — audit-trail log entries.

**Fields exposed (`toArray()`):**
`id`, `user_id`, `auditable_type`, `auditable_id`, `event`, `old_values`, `new_values`, `url`, `ip_address`, `user_agent`, `created_at` (ISO 8601), `updated_at` (ISO 8601).

**`whenLoaded()` conditions:**
- `user` — embeds `{ id, name, email }` when the `user` relation is loaded.

**WHO returns it:**
- **List:** `AuditController::index()` at `app/Http/Controllers/AuditController.php:43` — `AuditResource::collection(...)->response()->getData(true)`.

---

### `UserResource`

**File:** [`app/Http/Resources/UserResource.php`](UserResource.php)

**Formats:** `App\Models\User`.

**Fields exposed:**
`id`, `name`, `email`, `active`, `profile_id`, `avatar_path`, `avatar_disk`, `email_verified_at` (ISO), `last_login_at` (ISO), `created_at` (ISO), `updated_at` (ISO).

**`whenLoaded()` conditions:**
- `profile` — embeds `{ id, name, description }` when the `profile` relation is loaded.

**WHO returns it:**
- **List:** `AdminUserController::index()` at `app/Http/Controllers/AdminUserController.php:44` — `UserResource::collection(...)`.
- **Create:** `AdminUserController::store()` at `app/Http/Controllers/AdminUserController.php:64`.
- **Update:** `AdminUserController::update()` at `app/Http/Controllers/AdminUserController.php:84`.
- **Show:** `AdminUserController::show()` at `app/Http/Controllers/AdminUserController.php:110`.
- **Profile update:** `ProfileController::updateProfile()` at `app/Http/Controllers/ProfileController.php:58` — `new UserResource($user)`.
- Nested inside `StockMovementResource::user` when `user` is loaded.

---

### `UserProfileResource`

**File:** [`app/Http/Resources/UserProfileResource.php`](UserProfileResource.php)

**Formats:** `App\Models\UserProfile`.

**Fields exposed:**
`id`, `name`, `description`, `active`, `created_at` (ISO), `updated_at` (ISO).

**`whenCounted()` / `whenLoaded()`:**
- `users_count` — via `whenCounted('users')`, only present when the controller runs `withCount('users')`.

**WHO returns it:**
- **List:** `AdminUserController::profiles()` at `app/Http/Controllers/AdminUserController.php:148` — `UserProfileResource::collection(...)` (with `UserProfile::withCount('users')`).

---

### `EquipmentResource`

**File:** [`app/Http/Resources/EquipmentResource.php`](EquipmentResource.php)

**Formats:** `App\Models\Equipment`.

**Fields exposed:**
`id`, `name`, `serial`, `asset_tag`, `brand`, `model`, `manufacturer`, `purchase_date` (ISO), `warranty_until` (ISO), `status`, `active`, `notes`, `room_id`, `category_id`, `created_at` (ISO), `updated_at` (ISO).

**`whenLoaded()` conditions:**
- `room` — embeds full `RoomResource` when `room` is loaded.
- `category` — embeds `{ id, name }` when `category` is loaded.

**WHO returns it:**
- **List:** `AdminEquipmentController::index()` at `app/Http/Controllers/AdminEquipmentController.php:42` — `EquipmentResource::collection(...)`.
- **Create:** `AdminEquipmentController::store()` at `app/Http/Controllers/AdminEquipmentController.php:62`.
- **Update:** `AdminEquipmentController::update()` at `app/Http/Controllers/AdminEquipmentController.php:82`.
- Nested in `StockMovementResource::equipment` and `MaintenancePlanResource::equipment`.

---

### `RoomResource`

**File:** [`app/Http/Resources/RoomResource.php`](RoomResource.php)

**Formats:** `App\Models\Room`.

**Fields exposed:**
`id`, `name`, `code`, `building`, `floor`, `location`, `capacity`, `description`, `notes`, `active`, `created_at` (ISO), `updated_at` (ISO).

**`whenCounted()` / `whenLoaded()`:**
- `equipments_count` — via `whenCounted('equipments')`, only present when the controller uses `withCount('equipments')`.

**WHO returns it:**
- **List:** `RoomController::index()` at `app/Http/Controllers/RoomController.php:37` — `RoomResource::collection(...)`.
- **Create:** `RoomController::storeRoom()` at `app/Http/Controllers/RoomController.php:61`.
- **Update:** `RoomController::updateRoom()` at `app/Http/Controllers/RoomController.php:79`.
- Nested in `EquipmentResource::room`.

---

### `PartResource`

**File:** [`app/Http/Resources/PartResource.php`](PartResource.php)

**Formats:** `App\Models\Part`.

**Fields exposed:**
`id`, `sku`, `name`, `description`, `brand`, `manufacturer_ref`, `unit_of_measure`, `cost_price`, `price_with_vat` (computed via model `priceWithVat()`), `sale_price`, `tax_rate_id`, `current_stock`, `min_stock`, `max_stock`, `is_low_stock` (computed via model `isLowStock()`), `stock_value` (computed via model `stockValue()`), `location`, `photo`, `active`, `technical_notes`, `created_at`, `updated_at`.

**`whenLoaded()` conditions:**
- `taxRate` — embeds `TaxRateResource` when `taxRate` is loaded.
- `category` — embeds `PartCategoryResource` when `category` is loaded.
- `suppliers` — embeds `SupplierResource::collection(...)` when `suppliers` is loaded.
- Also reads the loaded `part.taxRate`, `part.category`, `part.suppliers` when present.

**WHO returns it:**
- **List:** `PartController::index()` at `app/Http/Controllers/PartController.php:62` — `PartResource::collection(...)`.
- **Show:** `PartController::show()` at `app/Http/Controllers/PartController.php:92` — `new PartResource($part->load(['category', 'taxRate', 'suppliers']))`.
- **Create:** `PartController::store()` at `app/Http/Controllers/PartController.php:144`.
- **Update:** `PartController::update()` at `app/Http/Controllers/PartController.php:197`.
- Nested in `StockMovementResource::part` and `SupplierResource::parts`.

---

### `PartCategoryResource`

**File:** [`app/Http/Resources/PartCategoryResource.php`](PartCategoryResource.php)

**Formats:** `App\Models\PartCategory`.

**Fields exposed:**
`id`, `name`, `active`, `created_at`, `updated_at`.

**`whenLoaded()` conditions:** None.

**WHO returns it:**
- **List:** `PartCategoryController::index()` at `app/Http/Controllers/PartCategoryController.php:40` — `PartCategoryResource::collection(...)`.
- **Create:** `PartCategoryController::store()` at `app/Http/Controllers/PartCategoryController.php:78`.
- **Update:** `PartCategoryController::update()` at `app/Http/Controllers/PartCategoryController.php:120`.
- Nested in `PartResource::category`.

---

### `SupplierResource`

**File:** [`app/Http/Resources/SupplierResource.php`](SupplierResource.php)

**Formats:** `App\Models\Supplier`.

**Fields exposed:**
`id`, `name`, `nif` (Portuguese tax ID column), `contact`, `email`, `address`, `avg_lead_time_days`, `created_at`, `updated_at`.

**`whenLoaded()` conditions:**
- `parts` — embeds `PartResource::collection(...)` when `parts` is loaded (used on `show`).

**WHO returns it:**
- **List:** `SupplierController::index()` at `app/Http/Controllers/SupplierController.php:61` — `SupplierResource::collection(...)`.
- **Show:** `SupplierController::show()` at `app/Http/Controllers/SupplierController.php:91` — `new SupplierResource($supplier->load('parts.taxRate'))`.
- **Create:** `SupplierController::store()` at `app/Http/Controllers/SupplierController.php:131`.
- **Update:** `SupplierController::update()` at `app/Http/Controllers/SupplierController.php:174`.
- Nested in `PartResource::suppliers`.

---

### `TaxRateResource`

**File:** [`app/Http/Resources/TaxRateResource.php`](TaxRateResource.php)

**Formats:** `App\Models\TaxRate`.

**Fields exposed:**
`id`, `name`, `percent`, `is_default`, `active`, `created_at`, `updated_at`.

**`whenLoaded()` conditions:** None.

**WHO returns it:**
- **List:** `TaxRateController::index()` at `app/Http/Controllers/TaxRateController.php:40` — `TaxRateResource::collection(...)`.
- **Create:** `TaxRateController::store()` at `app/Http/Controllers/TaxRateController.php:82`.
- **Update:** `TaxRateController::update()` at `app/Http/Controllers/TaxRateController.php:128`.
- Nested in `PartResource::taxRate`.

---

### `StockMovementResource`

**File:** [`app/Http/Resources/StockMovementResource.php`](StockMovementResource.php)

**Formats:** `App\Models\StockMovement`.

**Fields exposed:**
`id`, `part_id`, `ticket_id`, `equipment_id`, `user_id`, `movement_type`, `movement_type_label` (via `StockMovementTypeEnum::normalize(...)->label()`), `movement_type_icon` (via `->icon()`), `delta` (computed via model `delta()`), `quantity`, `reason`, `unit_price_snapshot`, `stock_after`, `created_at`, `updated_at`.

**`whenLoaded()` conditions:**
- `part` — embeds `PartResource`.
- `ticket` — embeds `TicketResource`.
- `equipment` — embeds `EquipmentResource`.
- `user` — embeds `UserResource`.

**WHO returns it:**
- **List:** `StockMovementController::index()` at `app/Http/Controllers/StockMovementController.php:66` — `StockMovementResource::collection(...)`.
- **Show:** `StockMovementController::show()` at `app/Http/Controllers/StockMovementController.php:145` — `new StockMovementResource($movement->load(['part.taxRate', 'user']))`.

---

### `TicketResource`

**File:** [`app/Http/Resources/TicketResource.php`](TicketResource.php)

**Formats:** `App\Models\Ticket` — the most complex resource; represents the full ticket domain object.

**Fields exposed:**
`id`, `reference`, `title`, `description`, `priority`, `priority_label` (via `TicketPriorityEnum::normalize(...)->label()`), `urgent`, `user_id`, `reporter_name`, `reporter_contact`, `source`, `assigned_to`, `equipment_id`, `room_id`, `status_id`, `status`, `status_label` (via `TicketStatusEnum::normalize($this->status?->name)?->label()`), `status_name` (`$this->status?->name`), `opened_at`, `in_progress_at`, `closed_at`, `scheduled_at`, `scheduled_end`, `scheduled`, `due_at`, `budget_requested`, `budget_status`, `budget_amount`, `budget_requested_at`, `resolution`, `minutes_spent`, `sla_breached`, `created_at`, `updated_at` — all dates as ISO 8601.

**`whenLoaded()` conditions:**
- `user` — embeds `{ id, name, email }` (or `null` if user is null) when loaded.
- `technician` — embeds `{ id, name }` when loaded.
- `equipment` — embeds `{ id, name }` when loaded.
- `room` — embeds `{ id, name, code }` when loaded.

**WHO returns it:**
- **List / paginated:** `TicketController::index()` at line 51, `TicketController::myTickets()` at line 129, `TicketController::adminIndex()` at line 175 — all `TicketResource::collection(...)`.
- **Create:** `TicketController::store()` at line 76.
- **Show:** `TicketController::show()` at line 152.
- **Budget estimate:** `TicketBudgetController::submitEstimate()` at line 144.
- **Budget authorization:** `TicketBudgetController::requestAuthorization()` at line 174.
- **Admin approve budget:** `AdminController::approveBudget()` at lines 49, 76.
- **Schedule:** `TicketScheduleController::__invoke()` at line 46.
- **Start:** `TicketStartController::__invoke()` at line 89.
- **Assign:** `TicketAssignmentController::__invoke()` at line 69.
- **Close:** `TicketCloseController::simpleClose()` at line 59, `closeFinal()` at line 129.
- **Lifecycle:** `TicketLifecycleController` at lines 49, 83.
- **Calendar:** `CalendarController::scheduleMaintenance()` at line 69.
- Nested in `StockMovementResource::ticket`.

---

### `TicketCommentResource`

**File:** [`app/Http/Resources/TicketCommentResource.php`](TicketCommentResource.php)

**Formats:** `App\Models\TicketComment`.

**Fields exposed:**
`id`, `ticket_id`, `user_id`, `parent_id`, `comment`, `is_internal`, `edited_at` (ISO), `created_at` (ISO), `updated_at` (ISO).

**`whenLoaded()` conditions:**
- `user` — embeds `{ id, name }` when loaded.
- `replies` — embeds the nested `TicketCommentResource::collection($this->replies)` when the `replies` relation is loaded (supports threaded comments).

**WHO returns it:**
- **Create:** `TicketCommentController::store()` at `app/Http/Controllers/TicketCommentController.php:35`.
- **List:** `TicketCommentController::index()` at `app/Http/Controllers/TicketCommentController.php:55` — `TicketCommentResource::collection(...)`.

---

### `TicketAttachmentResource`

**File:** [`app/Http/Resources/TicketAttachmentResource.php`](TicketAttachmentResource.php)

**Formats:** `App\Models\TicketAttachment`.

**Fields exposed:**
`id`, `ticket_id`, `user_id`, `original_name`, `file_name`, `path`, `disk`, `extension`, `mime_type`, `size`, `checksum`, `description`, `created_at` (ISO), `updated_at` (ISO).

**`whenLoaded()` conditions:**
- `user` — embeds `{ id, name }` when loaded.

**WHO returns it:**
- **Upload:** `TicketAttachmentController::store()` at `app/Http/Controllers/TicketAttachmentController.php:64`.
- **List:** `TicketAttachmentController::index()` at `app/Http/Controllers/TicketAttachmentController.php:79` — `TicketAttachmentResource::collection($ticket->attachments)`.

---

### `MaintenancePlanResource`

**File:** [`app/Http/Resources/MaintenancePlanResource.php`](MaintenancePlanResource.php)

**Formats:** `App\Models\MaintenancePlan`.

**Fields exposed:**
`id`, `equipment_id`, `name`, `interval_type`, `interval_type_label` (via `MaintenancePlanIntervalTypeEnum::normalize(...)->label()`), `interval_value`, `description`, `active`, `created_at`, `updated_at`.

**`whenLoaded()` conditions:**
- `equipment` — embeds `EquipmentResource` when loaded.
- `parts` — embeds a mapped array of `{ id, sku, name, expected_quantity (from pivot) }` when loaded.

**WHO returns it:**
- **List:** `MaintenancePlanController::index()` at `app/Http/Controllers/MaintenancePlanController.php:52` — `MaintenancePlanResource::collection(...)`.
- **Show:** `MaintenancePlanController::show()` at `app/Http/Controllers/MaintenancePlanController.php:82` — `new MaintenancePlanResource($plan->load(['equipment', 'parts']))`.
- **Create:** `MaintenancePlanController::store()` at `app/Http/Controllers/MaintenancePlanController.php:148`.
- **Update:** `MaintenancePlanController::update()` at `app/Http/Controllers/MaintenancePlanController.php:214`.

---

### `NotificationResource`

**File:** [`app/Http/Resources/NotificationResource.php`](NotificationResource.php)

**Formats:** `App\Models\Notification`.

**Fields exposed:**
`id`, `user_id`, `title`, `message`, `type`, `priority`, `is_read`, `read_at` (ISO), `link`, `notifiable_type`, `notifiable_id`, `data`, `expires_at` (ISO), `created_at` (ISO), `updated_at` (ISO).

**`whenLoaded()` conditions:** None.

**WHO returns it:**
- **List:** `NotificationController::index()` at `app/Http/Controllers/NotificationController.php:39` — `NotificationResource::collection(...)->response()->getData(true)`.
- **Show:** `NotificationController::show()` at `app/Http/Controllers/NotificationController.php:77` — `new NotificationResource($notification)`.

---

## Notes

- Enum `label()` values are user-facing — part of the i18n domain
- `nif` (SupplierResource) is a database column name (Portuguese tax ID acronym) — DB-level naming, reported separately
- Several resources rely on **computed model methods** (`Part::priceWithVat()`, `Part::isLowStock()`, `Part::stockValue()`, `StockMovement::delta()`) — the resource plugs the model's computed values into the JSON.
