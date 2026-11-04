# app/DTOs

Immutable Data Transfer Objects used to carry validated, sanitized input from the HTTP layer (FormRequest or plain arrays) into Actions and Services. All DTOs are `final readonly` PHP classes.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "Sealed Envelopes" that carry validated data from one desk to another.

## Overview

DTOs serve as the **single typed boundary** between the request layer and the business-logic layer. A controller receives a `FormRequest`, passes it to a DTO factory (`::fromRequest()`), and the DTO is then injected into an Action or Service. This means:

1. Controllers never read raw `$request->input()` keys — they rely on typed, validated DTO properties.
2. Actions/Services never depend on Laravel's `FormRequest` class — they accept only plain DTOs.
3. Every DTO validates and sanitizes data in its constructor or factory, so illegal values are rejected **before** they reach any business logic.

## Convention

Every DTO follows this pattern:

| Method | Purpose |
|--------|---------|
| `__construct(...)` | Immutable properties; defensive validation (throws `InvalidArgumentException`) |
| `static fromRequest(FormRequest\|array $data): self` | Accepts either a `FormRequest` (calls `->validated()`) or a plain array (tests/seeders) |
| `toArray(): array` | Re-serializes to an array for Eloquent or service consumption |

### `toArray()` semantics

- **Store* DTOs** return all fields (full creation payload).
- **Update* / partial DTOs** use `array_filter()` to omit `null` values, so only explicitly-provided fields are sent to Eloquent `update()` — preventing accidental column overwrites.

### ID sanitization

Optional integer IDs (e.g., `room_id`, `equipment_id`) pass through `filter_var(FILTER_VALIDATE_INT)` and are set to `null` when the raw value is `""`, `0`, or non-numeric. This prevents invalid foreign keys.

---

## Files

### `AssignTechnicianData.php`

**File:** [`app/DTOs/AssignTechnicianData.php`](AssignTechnicianData.php)

**What it carries:** The optional technician ID to assign to (or unassign from) a ticket.

| Field | Type | Description |
|-------|------|-------------|
| `$technicianId` | `?int` | Positive integer ID of the technician user, or `null` to unassign |

**How it's constructed:** `AssignTechnicianData::fromRequest($request->validated())` — reads `technician_id` from the payload. Empty strings, `null`, and non-numeric values are normalized to `null`.

**Who creates it / When:**
- Created by `TicketAssignmentController::__invoke()` at `app/Http/Controllers/Ticket/TicketAssignmentController.php:27` when an admin reassigns a ticket.
- Consumed by the `TechnicianAssignmentService`.

---

### `BudgetDecisionData.php`

**File:** [`app/DTOs/BudgetDecisionData.php`](BudgetDecisionData.php)

**What it carries:** An approval or rejection decision on a ticket's budget request, with optional feedback text.

| Field | Type | Description |
|-------|------|-------------|
| `$decision` | `BudgetDecisionEnum` | Either `Approve` or `Reject` |
| `$feedback` | `?string` | Optional justification text (trimmed; empty → `null`) |

**How it's constructed:** `BudgetDecisionData::fromRequest($request->validated())` — reads `decision` (falls back to `action` key), normalizes via `BudgetDecisionEnum::from()`. Defaults to `Approve` when no value is provided.

**Extra methods:**
- `isApproved(): bool` — convenience check.
- `isRejected(): bool` — convenience check.

**Who creates it / When:**
- Created by `AdminController::approveBudget()` at `app/Http/Controllers/AdminController.php:36`.
- Consumed by `ApproveBudgetAction::execute()`.

---

### `BudgetSubmissionData.php`

**File:** [`app/DTOs/BudgetSubmissionData.php`](BudgetSubmissionData.php)

**What it carries:** A budget estimate amount and optional line-item details. Supports two distinct creation contexts.

| Field | Type | Description |
|-------|------|-------------|
| `$estimatedBudget` | `float` | Monetary amount (≥ 0) |
| `$budgetDetails` | `?array` | Optional structured line items |
| `$isDetailedRequest` | `bool` | `true` when created via `fromDetailedRequest()` |

**How it's constructed:** Two named factories:
- `BudgetSubmissionData::fromSubmitEstimate($data)` — for the technician's initial budget estimate. Reads `estimatedBudget` or `estimated_budget`.
- `BudgetSubmissionData::fromDetailedRequest($data)` — for a formal budget request with line items. Reads `budget_amount`.

Both use `parseAmount()` which handles comma-decimal strings (e.g., `"150,50"` → `150.50`).

**Who creates it / When:**
- `TicketBudgetController::submitEstimate()` at `app/Http/Controllers/TicketBudgetController.php:43` calls `fromSubmitEstimate()`.
- `TicketBudgetController::requestAuthorization()` at `app/Http/Controllers/TicketBudgetController.php:60` calls `fromDetailedRequest()`.

---

### `CloseTicketData.php`

**File:** [`app/DTOs/CloseTicketData.php`](CloseTicketData.php)

**What it carries:** Data required to close (finalize) a ticket — actual cost, optional technical report, and force-close flag.

| Field | Type | Description |
|-------|------|-------------|
| `$actualCost` | `float` | Final cost (≥ 0), normalized to 2 decimal places |
| `$report` | `?string` | Optional technical report text (trimmed; empty → `null`) |
| `$force` | `bool` | Whether to bypass certain close-time validations |

**How it's constructed:** `CloseTicketData::fromRequest($data)` — parses cost via `parseCost()` (handles comma-decimal), coerces `force` via `filter_var(FILTER_VALIDATE_BOOLEAN)`.

**Who creates it / When:**
- Currently not directly referenced by controllers (may be consumed via Actions). The `TicketCloseController` uses `CloseTicketRequest` and `CloseTicketSimpleRequest` for validation instead.

---

### `CommentData.php`

**File:** [`app/DTOs/CommentData.php`](CommentData.php)

**What it carries:** Sanitized content for a ticket comment.

| Field | Type | Description |
|-------|------|-------------|
| `$content` | `string` | Non-empty trimmed comment text |

**How it's constructed:** `CommentData::fromRequest($data)` — trims whitespace, rejects empty strings in the constructor.

**Who creates it / When:**
- Expected to be consumed by `TicketCommentController` / comment-creation Actions (the controller currently passes validated data directly).

---

### `CreateTicketData.php`

**File:** [`app/DTOs/CreateTicketData.php`](CreateTicketData.php)

**What it carries:** The full payload for creating a new maintenance ticket.

| Field | Type | Description |
|-------|------|-------------|
| `$title` | `string` | Non-empty trimmed ticket title |
| `$description` | `string` | Non-empty trimmed ticket description |
| `$priority` | `TicketPriorityEnum` | Priority level (`Low`, `Medium`, `High`, `Critical`) |
| `$equipmentId` | `?int` | Optional associated equipment ID |
| `$roomId` | `?int` | Optional associated room ID |

**How it's constructed:** `CreateTicketData::fromRequest($data)` — normalizes priority via `TicketPriorityEnum::normalize()`, sanitizes nullable IDs.

**Who creates it / When:**
- Created by `TicketController::store()` at `app/Http/Controllers/TicketController.php:64`.
- Consumed by `CreateTicketAction::execute()`.

---

### `PasswordChangeData.php`

**File:** [`app/DTOs/PasswordChangeData.php`](PasswordChangeData.php)

**What it carries:** Current and new password for an authenticated password-change flow.

| Field | Type | Description |
|-------|------|-------------|
| `$currentPassword` | `string` | The user's current password |
| `$newPassword` | `string` | The desired new password |

**How it's constructed:** `PasswordChangeData::fromRequest($data)` — reads `current_password` and `new_password`. Constructor validates both are non-empty and that new ≠ current.

**Who creates it / When:**
- Expected to be used by `ProfileController::changePassword()` / `ChangePasswordAction`.

---

### `ProfileUpdateData.php`

**File:** [`app/DTOs/ProfileUpdateData.php`](ProfileUpdateData.php)

**What it carries:** A partial profile update — only name and/or email. Used when a user updates their own profile.

| Field | Type | Description |
|-------|------|-------------|
| `$name` | `?string` | New display name, or `null` if unchanged |
| `$email` | `?string` | New email (lowercased, validated format), or `null` if unchanged |

**How it's constructed:** `ProfileUpdateData::fromRequest($data)` — email is lowercased via `mb_strtolower()`, strings are null-safe trimmed.

**Special behavior:**
- `toArray()` returns **only non-null fields** (via `array_filter`) to prevent Eloquent from overwriting existing values with `null`.
- `hasChanges(): bool` — quick check whether any field was provided.

**Who creates it / When:**
- Expected to be used by `ProfileController::updateProfile()` / `UpdateProfileAction`.

---

### `ScheduleMaintenanceData.php`

**File:** [`app/DTOs/ScheduleMaintenanceData.php`](ScheduleMaintenanceData.php)

**What it carries:** Data for scheduling a preventive maintenance intervention on equipment.

| Field | Type | Description |
|-------|------|-------------|
| `$title` | `string` | Non-empty intervention title |
| `$equipmentId` | `?int` | Required equipment ID (> 0) |
| `$scheduledAt` | `string` | Scheduled date/time string |
| `$assignedTo` | `?int` | Optional technician user ID |
| `$description` | `?string` | Optional intervention description |

**How it's constructed:** `ScheduleMaintenanceData::fromRequest($data)` — trims title/description, sanitizes IDs.

**Who creates it / When:**
- Created by `CalendarController::scheduleMaintenance()` at `app/Http/Controllers/CalendarController.php:59`.
- Consumed by `ScheduleMaintenanceAction::execute()`.

---

### `ScheduleTicketData.php`

**File:** [`app/DTOs/ScheduleTicketData.php`](ScheduleTicketData.php)

**What it carries:** A scheduling window (start + optional end) for an existing ticket.

| Field | Type | Description |
|-------|------|-------------|
| `$scheduledAt` | `CarbonImmutable` | Required start date/time |
| `$scheduledEnd` | `?CarbonImmutable` | Optional end date/time (must be ≥ start) |

**How it's constructed:** `ScheduleTicketData::fromRequest($data)` — parses strings via `CarbonImmutable::parse()`, accepts `DateTimeInterface` instances. Throws if end < start.

**Who creates it / When:**
- Created by `TicketScheduleController::__invoke()` at `app/Http/Controllers/Ticket/TicketScheduleController.php:32`.
- Consumed by `ScheduleTicketAction::execute()`.

---

### `StoreEquipmentData.php`

**File:** [`app/DTOs/StoreEquipmentData.php`](StoreEquipmentData.php)

**What it carries:** Full creation payload for a new equipment record.

| Field | Type | Description |
|-------|------|-------------|
| `$name` | `string` | Required equipment name |
| `$serial` | `string` | Required serial number (uppercased) |
| `$roomId` | `?int` | Optional room FK |
| `$categoryId` | `?int` | Optional category FK |
| `$active` | `bool` | Defaults to `true` |
| `$assetTag` | `?string` | Optional asset tag |
| `$brand` | `?string` | Optional brand |
| `$model` | `?string` | Optional model |
| `$manufacturer` | `?string` | Optional manufacturer |
| `$purchaseDate` | `?string` | Optional purchase date |
| `$warrantyUntil` | `?string` | Optional warranty expiry |
| `$status` | `string` | Defaults to `'operacional'`; validated against `STATUSES` |
| `$notes` | `?string` | Optional notes |

**Constant:** `STATUSES = ['operacional', 'manutenção', 'avariado', 'abatido']` — Portuguese database enum values. Must not be renamed until schema migration.

**How it's constructed:** `StoreEquipmentData::fromRequest($data)` — serial is `strtoupper(trim(...))`, status validated against `STATUSES`.

**Who creates it / When:**
- Created by `AdminEquipmentController::store()` at `app/Http/Controllers/AdminEquipmentController.php:55`.
- Consumed by `CreateEquipmentAction::execute()`.

---

### `StorePartData.php`

**File:** [`app/DTOs/StorePartData.php`](StorePartData.php)

**What it carries:** Full creation payload for a spare part record.

| Field | Type | Description |
|-------|------|-------------|
| `$sku` | `string` | Required SKU (uppercased) |
| `$name` | `string` | Required part name |
| `$description` | `?string` | Optional description |
| `$brand` | `?string` | Optional brand |
| `$manufacturerRef` | `?string` | Optional manufacturer reference |
| `$partCategoryId` | `?int` | Optional category FK |
| `$unitOfMeasure` | `string` | Defaults to `'unit'` |
| `$costPrice` | `float` | Required cost price |
| `$taxRateId` | `?int` | Optional VAT rate FK |
| `$salePrice` | `?float` | Optional sale price |
| `$currentStock` | `int` | Required initial stock (≥ 0) |
| `$minStock` | `int` | Required minimum stock |
| `$maxStock` | `?int` | Optional maximum stock |
| `$location` | `?string` | Optional storage location |
| `$photo` | `?string` | Optional photo path |
| `$active` | `bool` | Defaults to `true` |
| `$technicalNotes` | `?string` | Optional technical notes |

**How it's constructed:** `StorePartData::fromRequest($data)` — SKU uppercased, validates `current_stock ≥ 0`.

**Who creates it / When:**
- Created by `PartController::store()` at `app/Http/Controllers/PartController.php:138`.
- Consumed by `CreatePartAction::execute()`.

---

### `StoreRoomData.php`

**File:** [`app/DTOs/StoreRoomData.php`](StoreRoomData.php)

**What it carries:** Full creation payload for a room record.

| Field | Type | Description |
|-------|------|-------------|
| `$name` | `string` | Required non-empty room name |
| `$code` | `?string` | Optional code (uppercased, e.g. `"lab-1"` → `"LAB-1"`) |
| `$location` | `?string` | Optional location |
| `$active` | `bool` | Defaults to `true` |
| `$building` | `?string` | Optional building |
| `$floor` | `?string` | Optional floor |
| `$capacity` | `?int` | Optional capacity (≥ 0) |
| `$description` | `?string` | Optional description |
| `$notes` | `?string` | Optional notes |

**How it's constructed:** `StoreRoomData::fromRequest($data)` — name is trimmed, code is uppercased.

**Who creates it / When:**
- Created by `RoomController::storeRoom()` at `app/Http/Controllers/RoomController.php:56`.
- Consumed by `CreateRoomAction::execute()`.

---

### `StoreSupplierData.php`

**File:** [`app/DTOs/StoreSupplierData.php`](StoreSupplierData.php)

**What it carries:** Full creation payload for a supplier record.

| Field | Type | Description |
|-------|------|-------------|
| `$name` | `string` | Required supplier name |
| `$nif` | `?string` | Optional tax ID (NIF) |
| `$contact` | `?string` | Optional contact info |
| `$email` | `?string` | Optional email |
| `$address` | `?string` | Optional address |
| `$avgLeadTimeDays` | `?int` | Optional average lead time in days |

**How it's constructed:** `StoreSupplierData::fromRequest($data)` — all strings trimmed.

**Who creates it / When:**
- Created by `SupplierController::store()` at `app/Http/Controllers/SupplierController.php:126`.
- Consumed by `CreateSupplierAction::execute()`.

---

### `StoreUserData.php`

**File:** [`app/DTOs/StoreUserData.php`](StoreUserData.php)

**What it carries:** Full creation payload for a new user account.

| Field | Type | Description |
|-------|------|-------------|
| `$name` | `string` | Required non-empty name |
| `$email` | `string` | Required valid email (lowercased) |
| `$password` | `string` | Required non-empty password |
| `$profileId` | `?int` | Optional user profile FK |
| `$active` | `bool` | Defaults to `true` |

**How it's constructed:** `StoreUserData::fromRequest($data)` — email lowercased, name trimmed, password kept as-is.

**Who creates it / When:**
- Created by `AdminUserController::store()` at `app/Http/Controllers/AdminUserController.php:57`.
- Consumed by `CreateUserAction::execute()`.

---

### `TicketFilters.php`

**File:** [`app/DTOs/TicketFilters.php`](TicketFilters.php)

**What it carries:** An optional filter set for querying/searching tickets. All fields are nullable — only active filters are applied to the query.

| Field | Type | Description |
|-------|------|-------------|
| `$query` | `?string` | Free-text search (title/reference) |
| `$priority` | `?TicketPriorityEnum` | Filter by priority level |
| `$status` | `?string` | Filter by status name |
| `$dateFrom` | `?CarbonImmutable` | Start of date range |
| `$dateTo` | `?CarbonImmutable` | End of date range |
| `$userId` | `?int` | Filter by reporter user ID |
| `$technicianId` | `?int` | Filter by assigned technician ID |
| `$equipmentId` | `?int` | Filter by equipment ID |
| `$roomId` | `?int` | Filter by room ID |

**How it's constructed:** `TicketFilters::fromRequest($data)` — reads from `q`/`query`, parses dates via `CarbonImmutable`, normalizes priority.

**Special behavior:**
- `toArray()` returns only non-null filter values for dynamic query building.
- `hasFilters(): bool` — checks if any filter is active.
- Constructor validates `dateFrom ≤ dateTo`.

**Who creates it / When:**
- Created by `TicketController::index()` at `app/Http/Controllers/TicketController.php:95`.
- Consumed by `TicketSearchService::search()`.

---

### `UpdateEquipmentData.php`

**File:** [`app/DTOs/UpdateEquipmentData.php`](UpdateEquipmentData.php)

**What it carries:** Partial update payload for an equipment record. All fields are nullable.

| Field | Type | Description |
|-------|------|-------------|
| `$name` | `?string` | Optional name update |
| `$serial` | `?string` | Optional serial (uppercased) |
| `$roomId` | `?int` | Optional room FK |
| `$categoryId` | `?int` | Optional category FK |
| `$active` | `?bool` | Optional active flag (nullable to distinguish "not provided" from `false`) |
| `$assetTag` | `?string` | Optional asset tag |
| `$brand` | `?string` | Optional brand |
| `$model` | `?string` | Optional model |
| `$manufacturer` | `?string` | Optional manufacturer |
| `$purchaseDate` | `?string` | Optional purchase date |
| `$warrantyUntil` | `?string` | Optional warranty expiry |
| `$status` | `?string` | Optional status (validated against `StoreEquipmentData::STATUSES`) |
| `$notes` | `?string` | Optional notes |

**How it's constructed:** `UpdateEquipmentData::fromRequest($data)` — serial uppercased, status validated against `StoreEquipmentData::STATUSES`.

**Special behavior:**
- `toArray()` returns only non-null fields for partial Eloquent update.
- `hasUpdates(): bool` — quick check.

**Who creates it / When:**
- Created by `AdminEquipmentController::update()` at `app/Http/Controllers/AdminEquipmentController.php:75`.
- Consumed by `UpdateEquipmentAction::execute()`.

---

### `UpdatePartData.php`

**File:** [`app/DTOs/UpdatePartData.php`](UpdatePartData.php)

**What it carries:** Full update payload for a spare part record (all editable fields, no stock manipulation).

| Field | Type | Description |
|-------|------|-------------|
| `$sku` | `string` | Required SKU (uppercased) |
| `$name` | `string` | Required name |
| `$description` | `?string` | Optional description |
| `$brand` | `?string` | Optional brand |
| `$manufacturerRef` | `?string` | Optional manufacturer reference |
| `$partCategoryId` | `?int` | Optional category FK |
| `$unitOfMeasure` | `string` | Required unit of measure |
| `$costPrice` | `float` | Required cost price |
| `$taxRateId` | `?int` | Optional VAT rate FK |
| `$salePrice` | `?float` | Optional sale price |
| `$minStock` | `int` | Required minimum stock |
| `$maxStock` | `?int` | Optional maximum stock |
| `$location` | `?string` | Optional location |
| `$photo` | `?string` | Optional photo path |
| `$active` | `bool` | Required active flag |
| `$technicalNotes` | `?string` | Optional technical notes |

**How it's constructed:** `UpdatePartData::fromRequest($data)` — SKU uppercased, all strings trimmed.

**Who creates it / When:**
- Created by `PartController::update()` at `app/Http/Controllers/PartController.php:191`.
- Consumed by `UpdatePartAction::execute()`.

---

### `UpdateRoomData.php`

**File:** [`app/DTOs/UpdateRoomData.php`](UpdateRoomData.php)

**What it carries:** Partial update payload for a room record. All fields nullable.

| Field | Type | Description |
|-------|------|-------------|
| `$name` | `?string` | Optional name (trimmed) |
| `$code` | `?string` | Optional code (uppercased) |
| `$location` | `?string` | Optional location |
| `$active` | `?bool` | Optional active flag |
| `$building` | `?string` | Optional building |
| `$floor` | `?string` | Optional floor |
| `$capacity` | `?int` | Optional capacity (≥ 0) |
| `$description` | `?string` | Optional description |
| `$notes` | `?string` | Optional notes |

**How it's constructed:** `UpdateRoomData::fromRequest($data)` — code uppercased, nullable strings/bools sanitized.

**Special behavior:**
- `toArray()` returns only non-null fields.
- `hasUpdates(): bool` — quick check.

**Who creates it / When:**
- Created by `RoomController::updateRoom()` at `app/Http/Controllers/RoomController.php:74`.
- Consumed by `UpdateRoomAction::execute()`.

---

### `UpdateSupplierData.php`

**File:** [`app/DTOs/UpdateSupplierData.php`](UpdateSupplierData.php)

**What it carries:** Full update payload for a supplier record.

| Field | Type | Description |
|-------|------|-------------|
| `$name` | `string` | Required supplier name |
| `$nif` | `?string` | Optional tax ID |
| `$contact` | `?string` | Optional contact |
| `$email` | `?string` | Optional email |
| `$address` | `?string` | Optional address |
| `$avgLeadTimeDays` | `?int` | Optional lead time |

**How it's constructed:** `UpdateSupplierData::fromRequest($data)` — all strings trimmed.

**Who creates it / When:**
- Created by `SupplierController::update()` at `app/Http/Controllers/SupplierController.php:169`.
- Consumed by `UpdateSupplierAction::execute()`.

---

### `UpdateUserData.php`

**File:** [`app/DTOs/UpdateUserData.php`](UpdateUserData.php)

**What it carries:** Partial user update. Password is handled separately (never returned by `toArray()`).

| Field | Type | Description |
|-------|------|-------------|
| `$name` | `?string` | Optional name |
| `$email` | `?string` | Optional email (lowercased) |
| `$password` | `?string` | Optional new password (blank → `null`) |
| `$profileId` | `?int` | Optional profile FK |
| `$active` | `?bool` | Optional active flag |

**How it's constructed:** `UpdateUserData::fromRequest($data)` — email lowercased, password preserved as-is (blank → `null`).

**Special behavior:**
- `toArray()` **excludes password** — password is handled via hashing in the service layer.
- `hasPassword(): bool` — checks if a new password was provided.
- `hasUpdates(): bool` — checks password OR any other field.

**Who creates it / When:**
- Created by `AdminUserController::update()` at `app/Http/Controllers/AdminUserController.php:77`.
- Consumed by `UpdateUserAction::execute()`.

---

## Notes for developers / AI

- **Convention:** Every DTO exposes a static `fromRequest(FormRequest|array $data)` factory. Pass either a `FormRequest` (it calls `->validated()`) or a plain array (e.g. in tests or seeders).
- **`toArray()` semantics vary by DTO:** Some (Store*) return all fields; partial-update DTOs (Update*, ProfileUpdateData) filter out `null` values so Eloquent only touches provided fields.
- **`StoreEquipmentData::STATUSES`** contains Portuguese string values (`operacional`, `manutenção`, `avariado`, `abatido`). These match the database `status` column's allowed values and must **not** be renamed until the schema is migrated (tracked in `docs/refactor/db-naming-report.md`).
- **No behavior in DTOs.** DTOs are validation + sanitization containers only. Business logic belongs in Actions and Services.
- **`CloseTicketData`, `CommentData`, `PasswordChangeData`, `ProfileUpdateData`** are defined but may not yet be wired into controllers — they represent planned/intended DTO consumption patterns for those flows.
