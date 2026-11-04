# app/Actions

Single-responsibility action classes that encapsulate business operations. Each action performs one use case and is injected via constructor with its dependencies.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The Worker Bees" where each bee does exactly one job and does it well.

---

## `ApproveBudgetAction.php`

**File:** `app/Actions/ApproveBudgetAction.php`
**What it is:** Approves or rejects a pending budget request on a ticket, notifying the requester of the decision.

**Dependencies (constructor):**
- `NotificationService $notificationService` -- sends in-app notifications about budget decisions
- `TicketStatusService $statusService` -- resolves `TicketStatusEnum` values to database status IDs

**Traits:** `LocksTicketRow` (`app/Domain/Ticket/Concerns/LocksTicketRow.php`)

**Public methods:**

### `execute(Ticket $ticket, User $admin, BudgetDecisionData $data): Ticket`

1. Determines approval outcome: `$data->isApproved()`.
2. Resolves the target status: `TicketStatusEnum::InProgress` if approved, `TicketStatusEnum::Rejected` if rejected.
3. Calls `$this->statusService->getByName($targetTicketStatus)` to get the database status ID.
4. Opens `DB::transaction`:
   - Calls `lockTicketRow($ticket)` and only then applies **the guard**: if `$ticket->budget_requested` is falsy OR `$ticket->budget_status !== BudgetStatusEnum::Pending->value`, throws `HttpException(422, 'No pending budget request found for this ticket.')`. Two admins can both have the request open; without the re-read the second one rules on a request that was already decided and the notifications say "approved" about a rejected budget.
   - Sets `budget_approved_by` to the admin's ID.
   - Sets `budget_decided_at` to `now()`.
   - Sets `status_id` to the resolved status.
   - If approved: sets `budget_status` to `BudgetStatusEnum::Approved->value` and stamps `in_progress_at` with `now()` when it is still null — the ticket moves to In Progress, and `in_progress_at` is what the resolution-time KPIs read. `StartTicketAction` already stamps it on the normal start path; without this the approval path left it null and the ticket looked infinitely fast (or was excluded entirely).
   - If rejected: sets `budget_status` to `BudgetStatusEnum::Rejected->value`; if `$data->feedback` is non-empty, sets `budget_feedback`.
   - Calls `$ticket->save()`.
5. Calls private `notifyDecision()` which formats a human-readable message and calls `$this->notificationService->notifyBudgetDecision()`. Runs **after** the transaction commits, so a rolled-back decision never notifies.
6. Returns `$ticket->load(['equipment', 'room', 'technician', 'status'])`.

**Who calls it and when:**
- `AdminController::approveBudget()` at `app/Http/Controllers/AdminController.php:33` -- when an admin approves or rejects a budget via the admin panel.
- Tests: `tests/Database/Constraints/WorkflowPersistenceTest.php:135`, `tests/Unit/Actions/ApproveBudgetActionTest.php` (including the two-admins race), `tests/Unit/Models/TicketWorkflowTest.php:251`.

---

## `AssignTechnicianAction.php`

**File:** `app/Actions/AssignTechnicianAction.php`
**What it is:** Assigns or removes a technician from a ticket by delegating to `TechnicianAssignmentService`.

**Dependencies (constructor):**
- `TechnicianAssignmentService $assignmentService` -- handles the actual assignment logic and availability checks

**Public methods:**

### `execute(Ticket $ticket, User|int|null $technician): Ticket`

1. Normalizes `$technician`: if `User`, extracts `->id`; if `int`, uses directly; if `null`, passes `null`.
2. Calls `$this->assignmentService->assignToTicket($ticket, $technicianId)`.
3. Returns `$ticket->load('technician')`.

**Who calls it and when:**
- No production controller caller found. The `TicketAssignmentController` calls `TechnicianAssignmentService->assignToTicket()` directly (line 44), bypassing this action. Used in tests only: `tests/Unit/Actions/AssignTechnicianActionTest.php`.

---

## `CreateEquipmentAction.php`

**File:** `app/Actions/CreateEquipmentAction.php`
**What it is:** Creates a new equipment record within a database transaction.

**Dependencies (constructor):** None.

**Public methods:**

### `execute(StoreEquipmentData $data): Equipment`

1. Opens `DB::transaction`.
2. Calls `Equipment::create()` with: `name` (trimmed), `serial` (uppercased + trimmed or null), `room_id`, `category_id`, `active`, `asset_tag`, `brand`, `model`, `manufacturer`, `purchase_date`, `warranty_until`, `status`, `notes`.
3. Returns `$equipment->load(['room', 'category'])`.

**Who calls it and when:**
- `AdminEquipmentController::store()` at `app/Http/Controllers/AdminEquipmentController.php:56` -- when an admin creates new equipment via the admin panel.
- Tests: `tests/Unit/Actions/CreateEquipmentActionTest.php`.

---

## `CreatePartAction.php`

**File:** `app/Actions/CreatePartAction.php`
**What it is:** Creates a new part and optionally records an initial stock movement if `currentStock > 0`.

**Dependencies (constructor):**
- `StockMovementService $stockMovementService` -- records stock movements (entries/exits) for parts

**Public methods:**

### `execute(StorePartData $data): Part`

1. Opens `DB::transaction`.
2. Calls `Part::create()` with all fields from `$data`: `sku`, `name`, `description`, `brand`, `manufacturer_ref`, `part_category_id`, `unit_of_measure`, `cost_price`, `tax_rate_id`, `sale_price`, `current_stock` (hardcoded to `0`), `min_stock`, `max_stock`, `location`, `photo`, `active`, `technical_notes`.
3. **Guard clause:** If `$data->currentStock > 0`, calls `$this->stockMovementService->record()` with `StockMovementTypeEnum::In`, quantity from `$data->currentStock`, and reason `__('stock.Stock inicial de catalogação')`.
4. Returns `$part->load(['category', 'taxRate', 'suppliers'])`.

**Who calls it and when:**
- `PartController::store()` at `app/Http/Controllers/PartController.php:139` -- when an admin creates a new part in the stock management module.
- Tests: `tests/Unit/Actions/PartActionsTest.php`.

---

## `CreatePreventiveTicketAction.php`

**File:** `app/Actions/CreatePreventiveTicketAction.php`
**What it is:** Creates a preventive maintenance ticket, optionally assigned to a technician and scheduled.

**Dependencies (constructor):**
- `TicketStatusService $statusService` -- resolves status enum to database ID

**Public methods:**

### `execute(User $admin, string $title, ?string $description = null, User|int|null $technician = null, CarbonInterface|string|null $scheduledAt = null): Ticket`

1. Resolves `$openStatusId` via `$this->statusService->getByName(TicketStatusEnum::Open)`.
2. Calls private `resolveTechnician()` to normalize `$technician` (returns `?User`; validates `isTechnician()`).
3. Opens `DB::transaction`:
   - Creates `Ticket` with: `user_id` (admin), `assigned_to` (resolved technician ID or null), `title` (trimmed), `description` (trimmed or default `'Scheduled preventive maintenance.'`), `priority` (`TicketPriorityEnum::Medium->value`), `status_id`, `opened_at` (`now()`), `scheduled_at` (parsed or `now()`), `scheduled` (`true`).
4. Returns `$ticket->load(['technician', 'status', 'user'])`.

**Who calls it and when:**
- `AdminController::storePreventive()` at `app/Http/Controllers/AdminController.php:64` -- when an admin creates a preventive maintenance ticket.
- Tests: `tests/Unit/Actions/CreatePreventiveTicketActionTest.php`.

---

## `CreatePublicTicketAction.php`

**File:** `app/Actions/CreatePublicTicketAction.php`
**What it is:** Creates a ticket reported publicly via QR code, without requiring a user account.

**Dependencies (constructor):**
- `TicketStatusService $statusService` -- resolves status enum to database ID

**Public methods:**

### `execute(Equipment $equipment, PublicTicketProblemTypeEnum $problemType, string $description, ?string $reporterName = null, ?string $reporterContact = null): Ticket`

1. Resolves `$openStatusId` via `$this->statusService->getByName(TicketStatusEnum::Open)`.
2. Opens `DB::transaction`:
   - Creates `Ticket` with: `reference` (format `'TKT-' + timestamp + '-' + uniqid`), `title` (problem type label + equipment name), `description` (trimmed), `priority` (from `$problemType->priority()->value`), `user_id` (`null` -- no account), `reporter_name`, `reporter_contact`, `source` (`'qr'`), `equipment_id`, `room_id` (from equipment), `status_id`, `opened_at` (`now()`).
3. Returns `$ticket->load(['equipment', 'room', 'status'])`.

**Who calls it and when:**
- `PublicTicketController::store()` at `app/Http/Controllers/PublicTicketController.php:64` -- when a visitor submits a damage report via the public QR-code form.

---

## `CreateRoomAction.php`

**File:** `app/Actions/CreateRoomAction.php`
**What it is:** Creates a new room/physical location record.

**Dependencies (constructor):** None.

**Public methods:**

### `execute(StoreRoomData $data): Room`

1. Opens `DB::transaction`.
2. Calls `Room::create()` with: `name` (trimmed), `code` (uppercased + trimmed, or null if empty), `location` (trimmed or null), `active`, `building`, `floor`, `capacity`, `description`, `notes`.
3. Returns the created `Room`.

**Who calls it and when:**
- `RoomController::storeRoom()` at `app/Http/Controllers/RoomController.php:57` -- when an admin creates a new room.
- Tests: `tests/Unit/Actions/CreateRoomActionTest.php`.

---

## `CreateSupplierAction.php`

**File:** `app/Actions/CreateSupplierAction.php`
**What it is:** Creates a new supplier record.

**Dependencies (constructor):** None.

**Public methods:**

### `execute(StoreSupplierData $data): Supplier`

1. Opens `DB::transaction`.
2. Calls `Supplier::create()` with: `name`, `nif`, `contact`, `email`, `address`, `avg_lead_time_days`.
3. Returns `$supplier->load('parts')`.

**Who calls it and when:**
- `SupplierController::store()` at `app/Http/Controllers/SupplierController.php:127` -- when an admin creates a new supplier.
- Tests: `tests/Unit/Actions/SupplierActionsTest.php`.

---

## `CreateTicketAction.php`

**File:** `app/Actions/CreateTicketAction.php`
**What it is:** Creates a standard incident ticket for an authenticated user.

**Dependencies (constructor):**
- `TicketStatusService $statusService` -- resolves status enum to database ID

**Public methods:**

### `execute(User $user, CreateTicketData $data): Ticket`

1. Resolves `$openStatusId` via `$this->statusService->getByName(TicketStatusEnum::Open)`.
2. Opens `DB::transaction`:
   - Creates `Ticket` with: `reference` (format `'TKT-' + timestamp + '-' + uniqid`), `title` (trimmed), `description` (trimmed), `priority` (`$data->priority->value`), `user_id`, `equipment_id`, `room_id`, `status_id`, `opened_at` (`now()`).
3. Returns `$ticket->load(['user', 'equipment', 'room', 'status'])`.

**Who calls it and when:**
- `TicketController::store()` at `app/Http/Controllers/TicketController.php:66` -- when an authenticated user creates a new incident ticket. After creation, `GenerateAiRecommendationJob` is dispatched.
- Tests: `tests/Feature/Actions/CreateTicketActionTest.php`.

---

## `CreateUserAction.php`

**File:** `app/Actions/CreateUserAction.php`
**What it is:** Creates a new user with a hashed password within a transaction.

**Dependencies (constructor):**
- `UserService $userService` -- ensures the user has a default profile assignment

**Public methods:**

### `execute(StoreUserData $data): User`

1. Opens `DB::transaction`.
2. Calls `User::create()` with: `name` (trimmed), `email` (trimmed + lowercased), `password` (Hash::make of `$data->password`), `profile_id`, `active`.
3. Calls `$this->userService->ensureDefaultProfile($user)`.
4. Returns `$user->load('profile')`.

**Who calls it and when:**
- `AdminUserController::store()` at `app/Http/Controllers/AdminUserController.php:58` -- when an admin creates a new user.
- Tests: `tests/Feature/Actions/CreateUserActionTest.php`.

---

## `MaintenancePlanActions.php`

**File:** `app/Actions/MaintenancePlanActions.php`
**What it is:** CRUD operations for maintenance plans and their associated parts (create, update, sync).

**Dependencies (constructor):** None.

**Public methods:**

### `create(Equipment $equipment, string $name, MaintenancePlanIntervalTypeEnum $intervalType, int $intervalValue, ?string $description = null, bool $active = true, array $parts = []): MaintenancePlan`

1. Opens `DB::transaction`.
2. Creates `MaintenancePlan` with: `equipment_id`, `name` (trimmed), `interval_type`, `interval_value`, `description` (trimmed or null), `active`.
3. Calls private `syncParts($plan, $parts)`.
4. Returns `$plan->load(['equipment', 'parts'])`.

### `update(MaintenancePlan $plan, string $name, MaintenancePlanIntervalTypeEnum $intervalType, int $intervalValue, ?string $description = null, bool $active = true, array $parts = []): MaintenancePlan`

1. Opens `DB::transaction`.
2. Updates the plan with: `name` (trimmed), `interval_type`, `interval_value`, `description` (trimmed or null), `active`.
3. Calls private `syncParts($plan, $parts)`.
4. Returns `$plan->load(['equipment', 'parts'])`.

### `private syncParts(MaintenancePlan $plan, array $parts): void`

1. Iterates `$parts` as `part_id => quantity`.
2. Skips entries where `$partId < 1`.
3. Throws `InvalidArgumentException` if `$quantity < 1`.
4. Builds a payload: `[$partId => ['expected_quantity' => $quantity]]`.
5. Calls `$plan->parts()->sync($payload)`.

**Who calls it and when:**
- `MaintenancePlanController::store()` at `app/Http/Controllers/MaintenancePlanController.php:136` -- creates a plan.
- `MaintenancePlanController::update()` at `app/Http/Controllers/MaintenancePlanController.php:202` -- updates a plan.

---

## `PartCategoryActions.php`

**File:** `app/Actions/PartCategoryActions.php`
**What it is:** CRUD operations for part categories (create, update).

**Dependencies (constructor):** None.

**Public methods:**

### `create(string $name, bool $active = true): PartCategory`

1. Opens `DB::transaction`.
2. Creates `PartCategory` with `name` (trimmed) and `active`.

### `update(PartCategory $category, string $name, bool $active = true): PartCategory`

1. Opens `DB::transaction`.
2. Updates the category with `name` (trimmed) and `active`.
3. Returns the updated category.

**Who calls it and when:**
- `PartCategoryController::store()` at `app/Http/Controllers/PartCategoryController.php:71` -- creates a category.
- `PartCategoryController::update()` at `app/Http/Controllers/PartCategoryController.php:112` -- updates a category.
- Tests: `tests/Unit/Actions/PartCategoryActionsTest.php`.

---

## `ScheduleMaintenanceAction.php`

**File:** `app/Actions/ScheduleMaintenanceAction.php`
**What it is:** Creates a preventive maintenance ticket pre-scheduled on the calendar.

**Dependencies (constructor):**
- `TicketStatusService $statusService` -- resolves status enum to database ID

**Public methods:**

### `execute(User $creator, ScheduleMaintenanceData $data): Ticket`

1. Resolves `$openStatusId` via `$this->statusService->getByName(TicketStatusEnum::Open)`.
2. Parses `$data->scheduledAt` via `Carbon::parse()`.
3. Opens `DB::transaction`:
   - Creates `Ticket` with: `reference` (format `'MNT-' + timestamp + '-' + uniqid`), `title` (trimmed), `description` (trimmed or i18n default `'Manutenção preventiva agendada.'`), `priority` (`'média'` hardcoded), `user_id`, `equipment_id`, `assigned_to`, `status_id`, `opened_at` (`now()`), `scheduled_at`, `scheduled_end` (scheduled_at + 2 hours), `scheduled` (`true`).
4. Returns `$ticket->load(['user', 'equipment', 'room', 'technician', 'status'])`.

**Who calls it and when:**
- `CalendarController::scheduleMaintenance()` at `app/Http/Controllers/CalendarController.php:57` -- when an admin schedules a preventive maintenance event on the calendar.
- Tests: `tests/Unit/Actions/ScheduleMaintenanceActionTest.php`.

---

## `ScheduleTicketAction.php`

**File:** `app/Actions/ScheduleTicketAction.php`
**What it is:** Schedules an existing ticket with start/end timestamps. Prevents scheduling closed tickets.

**Dependencies (constructor):** None.

**Public methods:**

### `execute(Ticket $ticket, ScheduleTicketData $data): Ticket`

1. **Guard clause:** If `$ticket->hasStatus(TicketStatusEnum::Closed)` OR `$ticket->hasStatus(TicketStatusEnum::Cancelled)`, throws `InvalidArgumentException('Cannot schedule a ticket that is already closed.')`.
2. Parses `$data->scheduledAt` and `$data->scheduledEnd` via `Carbon::parse()`.
3. **Guard clause:** If `$scheduledEnd` is before `$scheduledAt`, throws `InvalidArgumentException('Scheduled end time cannot be before the start time.')`.
4. Opens `DB::transaction`:
   - Updates ticket: `scheduled_at`, `scheduled_end`, `scheduled` (`true`).
5. Returns `$ticket->load(['technician', 'status'])`.

**Who calls it and when:**
- `TicketScheduleController::__invoke()` at `app/Http/Controllers/Ticket/TicketScheduleController.php:30` -- when a user schedules an intervention window for a ticket.
- Tests: `tests/Unit/Actions/ScheduleTicketActionTest.php`.

---

## `SubmitBudgetAction.php`

**File:** `app/Actions/SubmitBudgetAction.php`
**What it is:** Submits a budget request on a ticket. Prevents duplicates and submission on closed tickets.

**Dependencies (constructor):** None.

**Traits:** `LocksTicketRow` (`app/Domain/Ticket/Concerns/LocksTicketRow.php`)

**Public methods:**

### `execute(Ticket $ticket, BudgetSubmissionData $data): Ticket`

1. **Guard clause:** If `$data->estimatedBudget <= 0`, throws `InvalidArgumentException('The budget amount must be greater than 0.')`. This one judges the input, so it runs before the transaction.
2. Opens `DB::transaction`:
   - Calls `lockTicketRow($ticket)`, then re-checks the two state rules against the committed row: if `$ticket->hasStatus(TicketStatusEnum::Closed)`, throws `InvalidArgumentException('Cannot submit a budget for a ticket that is already closed.')`; if `$ticket->budget_status === BudgetStatusEnum::Pending->value`, throws `InvalidArgumentException('A pending budget request already exists for this ticket.')`.
   - Updates ticket: `budget_requested` (`true`), `budget_status` (`BudgetStatusEnum::Pending->value`), `budget_amount`, `budget_details` (the array as given -- `Ticket::$casts` encodes it, encoding here too would store JSON inside JSON), `budget_requested_at` (`now()`), `budget_feedback`/`budget_approved_by`/`budget_decided_at` (`null`).
3. Returns `$ticket->load(['technician', 'status', 'user'])`.

**Who calls it and when:**
- No production controller caller found. `TicketBudgetController` owns the live flow because it also has to apply the auto-approval threshold, move the ticket status and notify; it keeps its own `ensureSubmitAllowed()` rules and now shares the same `LocksTicketRow` re-read (`app/Http/Controllers/TicketBudgetController.php:66`). Used in tests only: `tests/Unit/Actions/SubmitBudgetActionTest.php`.
- Keep the two in step when the budget rules change: the Action holds the plain-domain version, the controller the HTTP version.

---

## `TaxRateActions.php`

**File:** `app/Actions/TaxRateActions.php`
**What it is:** CRUD operations for tax rates with default-rate management.

**Dependencies (constructor):** None.

**Public methods:**

### `create(string $name, float $percent, bool $isDefault = false, bool $active = true): TaxRate`

1. Opens `DB::transaction`.
2. **Guard clause:** If `$isDefault` is true, clears all existing defaults: `TaxRate::query()->where('is_default', true)->update(['is_default' => false])`.
3. Creates `TaxRate` with: `name` (trimmed), `percent`, `is_default`, `active`.

### `update(TaxRate $taxRate, string $name, float $percent, bool $isDefault = false, bool $active = true): TaxRate`

1. Opens `DB::transaction`.
2. **Guard clause:** If `$isDefault` is true, clears all existing defaults *except* the current record: `->whereKeyNot($taxRate->getKey())`.
3. Updates the tax rate with: `name` (trimmed), `percent`, `is_default`, `active`.
4. Returns the updated tax rate.

**Who calls it and when:**
- `TaxRateController::store()` at `app/Http/Controllers/TaxRateController.php:73` -- creates a tax rate.
- `TaxRateController::update()` at `app/Http/Controllers/TaxRateController.php:118` -- updates a tax rate.
- Tests: `tests/Unit/Actions/TaxRateActionsTest.php`.

---

## `UpdateEquipmentAction.php`

**File:** `app/Actions/UpdateEquipmentAction.php`
**What it is:** Updates an existing equipment record.

**Dependencies (constructor):** None.

**Public methods:**

### `execute(Equipment $equipment, UpdateEquipmentData $data): Equipment`

1. Opens `DB::transaction`.
2. Updates the equipment, keeping current values for any `null` DTO fields: `name` (trimmed), `serial` (uppercased + trimmed), `room_id`, `category_id`, `active`, `asset_tag`, `brand`, `model`, `manufacturer`, `purchase_date`, `warranty_until`, `status` (default `'operacional'`), `notes`.
3. Returns `$equipment->load(['room', 'category'])`.

**Who calls it and when:**
- `AdminEquipmentController::update()` at `app/Http/Controllers/AdminEquipmentController.php:76` -- when an admin updates equipment data.
- Tests: `tests/Unit/Actions/UpdateEquipmentActionTest.php`.

---

## `UpdatePartAction.php`

**File:** `app/Actions/UpdatePartAction.php`
**What it is:** Updates an existing part record.

**Dependencies (constructor):** None.

**Public methods:**

### `execute(Part $part, UpdatePartData $data): Part`

1. Opens `DB::transaction`.
2. Updates the part with all DTO fields: `sku`, `name`, `description`, `brand`, `manufacturer_ref`, `part_category_id`, `unit_of_measure`, `cost_price`, `tax_rate_id`, `sale_price`, `min_stock`, `max_stock`, `location`, `photo`, `active`, `technical_notes`.
3. Returns `$part->load(['category', 'taxRate', 'suppliers'])`.

**Who calls it and when:**
- `PartController::update()` at `app/Http/Controllers/PartController.php:192` -- when an admin updates part data.
- Tests: `tests/Unit/Actions/PartActionsTest.php`.

---

## `UpdateRoomAction.php`

**File:** `app/Actions/UpdateRoomAction.php`
**What it is:** Updates an existing room record.

**Dependencies (constructor):** None.

**Public methods:**

### `execute(Room $room, UpdateRoomData $data): Room`

1. Opens `DB::transaction`.
2. Updates the room, keeping current values for null DTO fields: `name` (trimmed), `code` (uppercased + trimmed), `location` (trimmed), `active`, `building`, `floor`, `capacity`, `description`, `notes`.
3. Returns the updated room.

**Who calls it and when:**
- `RoomController::updateRoom()` at `app/Http/Controllers/RoomController.php:75` -- when an admin updates room data.
- Tests: `tests/Unit/Actions/UpdateRoomActionTest.php`.

---

## `UpdateSupplierAction.php`

**File:** `app/Actions/UpdateSupplierAction.php`
**What it is:** Updates an existing supplier record.

**Dependencies (constructor):** None.

**Public methods:**

### `execute(Supplier $supplier, UpdateSupplierData $data): Supplier`

1. Opens `DB::transaction`.
2. Updates the supplier with: `name`, `nif`, `contact`, `email`, `address`, `avg_lead_time_days`.
3. Returns `$supplier->load('parts')`.

**Who calls it and when:**
- `SupplierController::update()` at `app/Http/Controllers/SupplierController.php:170` -- when an admin updates supplier data.
- Tests: `tests/Unit/Actions/SupplierActionsTest.php`.

---

## `UpdateUserAction.php`

**File:** `app/Actions/UpdateUserAction.php`
**What it is:** Updates an existing user's profile data.

**Dependencies (constructor):** None.

**Public methods:**

### `execute(User $user, UpdateUserData $data): User`

1. Opens `DB::transaction`.
2. Builds attributes array: `name` (trimmed), `email` (trimmed + lowercased), `active` -- keeping current values for null DTO fields.
3. **Conditional:** If `$data->profileId !== null`, adds `profile_id` to attributes.
4. Calls `$user->update($attributes)`.
5. Returns `$user->load('profile')`.

**Who calls it and when:**
- `AdminUserController::update()` at `app/Http/Controllers/AdminUserController.php:78` -- when an admin updates user data.
- Tests: `tests/Unit/Actions/UpdateUserActionTest.php`.

---

## Notes for developers / AI

- All actions are `final readonly` classes with a single `execute()` method (or named methods for multi-operation classes like `MaintenancePlanActions`).
- Actions use `DB::transaction()` for multi-model operations to ensure atomicity.
- Several actions depend on `TicketStatusService::getByName()` to resolve enum values to database IDs -- this throws `RuntimeException` if the status doesn't exist in the database.
- Guard clauses at the top of methods validate preconditions before proceeding (e.g., cannot schedule a closed ticket, cannot submit duplicate budget requests).
- The `__('...')` calls reference translation keys from `lang/` -- these are intentionally left as-is for i18n purposes.
- DTOs (e.g., `CreateTicketData`, `BudgetDecisionData`) are constructed via `::fromRequest()` static methods that validate and transform request input.
- Each action returns the created/updated model with eager-loaded relationships ready for API resource transformation.
