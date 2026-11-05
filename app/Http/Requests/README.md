# app/Http/Requests

Form request classes for input validation and authorization in the SGM maintenance management platform.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The Application Forms" -- standardised forms that validate what the user typed before processing.

## Purpose

Each request class encapsulates validation rules, authorization logic, and input sanitization for a specific API endpoint or controller action. This follows Laravel's Form Request pattern, keeping validation logic out of controllers.

## Structure

- **Store\*Request** — Create operations (e.g., `StoreTicketRequest`, `StoreRoomRequest`)
- **Update\*Request** — Update operations (e.g., `UpdateEquipmentRequest`, `UpdateProfileRequest`)
- **Schedule\*Request** — Scheduling operations (e.g., `ScheduleMaintenanceRequest`)
- **Submit\*Request** — Submission operations with complex validation (e.g., `SubmitBudgetRequest`)
- **Auth requests** — Login, registration, password reset (`LoginRequest`, `RegisterRequest`, `ResetPasswordRequest`)

## Key Patterns

- **`prepareForValidation()`** — Trims whitespace from text fields (and lowercases emails) before validation
- **`rules()`** — Returns validation rules array using Laravel's validation DSL
- **`attributes()`** — Maps field names to human-readable labels for error messages (user-facing, i18n domain)
- **`after()`** — Custom post-validation logic (e.g., `SubmitBudgetRequest` validates budget totals)
- **`authorize()`** — Authorization check (most return `true`; admin-only requests check `isAdmin()`)

## Grouped Feature Reference

The 38 request classes belong to these functional areas:

| Feature area | Requests |
|---|---|
| **Auth & Account** | `LoginRequest`, `RegisterRequest`, `SendResetLinkRequest`, `ResetPasswordRequest`, `ChangePasswordRequest`, `UpdateProfileRequest` |
| **User & Profile admin** | `StoreUserRequest`, `UpdateUserRequest`, `UploadPhotoRequest` |
| **Tickets** | `StoreTicketRequest`, `PublicStoreTicketRequest`, `AssignTechnicianToTicketRequest`, `StartTicketRequest`, `ScheduleTicketRequest`, `CloseTicketRequest`, `CloseTicketSimpleRequest`, `StoreCommentRequest` |
| **Budgets** | `RequestBudgetRequest`, `SubmitBudgetRequest`, `BudgetDecisionRequest` |
| **Calendar / Preventive** | `ScheduleMaintenanceRequest`, `StorePreventiveRequest`, `RescheduleEventRequest` |
| **Equipment & Rooms** | `StoreEquipmentRequest`, `UpdateEquipmentRequest`, `StoreRoomRequest`, `UpdateRoomRequest` |
| **Parts & Categories** | `StorePartRequest`, `UpdatePartRequest`, `StorePartCategoryRequest`, `UpdatePartCategoryRequest`, `StoreMaintenancePlanRequest`, `UpdateMaintenancePlanRequest` |
| **Stock** | `StoreStockMovementRequest` |
| **Suppliers & Tax** | `StoreSupplierRequest`, `UpdateSupplierRequest`, `StoreTaxRateRequest`, `UpdateTaxRateRequest` |

---

## Auth & Account

### `LoginRequest.php`

**File:** [`app/Http/Requests/LoginRequest.php`](LoginRequest.php)

**What HTTP action it validates:** `POST` login — authenticates an existing user.

**`authorize()`:** Always `true` (anyone may attempt login).

**Validation rules:**
- `email` — required, string, must be `lowercase`, must be a valid `email` format.
- `password` — required, string.

**Sanitization (`prepareForValidation`):** Lowercases and trims the email before validation.

**Messages/localization:** No custom `messages()`; relies on `attributes()` not being defined (email/password labels default to field names). The `lowercase` rule rejects mixed-case emails.

**WHICH controller uses it:** `AuthController::login()` at `app/Http/Controllers/AuthController.php:22`.

---

### `RegisterRequest.php`

**File:** [`app/Http/Requests/RegisterRequest.php`](RegisterRequest.php)

**What HTTP action it validates:** `POST` registration — creates a new user account.

**`authorize()`:** Always `true` (guest registration).

**Validation rules:**
- `name` — required, string, max 255.
- `email` — required, string, lowercase, email, max 255, **unique** in `users.email`.
- `password` — uses static `passwordRules()`: required, confirmed (`password_confirmation`), and a password policy rule (min 8, letters, mixed case, numbers, symbols; `uncompromised()` added when not in `testing` env).

**Sanitization (`prepareForValidation`):** Trims name, lowercases+trims email.

**Static helper:** `RegisterRequest::passwordRules(): array` — reusable password rule set (used here and by other requests).

**Messages/localization:** No custom `messages()`; `attributes()` not overridden.

**WHICH controller uses it:** `RegisterController::__invoke()` at `app/Http/Controllers/RegisterController.php:26`.

---

### `SendResetLinkRequest.php`

**File:** [`app/Http/Requests/SendResetLinkRequest.php`](SendResetLinkRequest.php)

**What HTTP action it validates:** `POST` forgot-password — emails a password-reset link.

**`authorize()`:** Always `true`.

**Validation rules:**
- `email` — required, string, lowercase, email. **Intentionally omits `exists:users`** to prevent account enumeration (the comment in-source warns about this).

**Sanitization (`prepareForValidation`):** Lowercases+trims email.

**Messages/localization:** No custom `messages()`.

**WHICH controller uses it:** `PasswordResetController::sendResetLink()` at `app/Http/Controllers/PasswordResetController.php:22`.

---

### `ResetPasswordRequest.php`

**File:** [`app/Http/Requests/ResetPasswordRequest.php`](ResetPasswordRequest.php)

**What HTTP action it validates:** `POST` reset-password (with token) — sets a new password.

**`authorize()`:** Always `true`.

**Validation rules:**
- `token` — required, string.
- `email` — required, string, lowercase, email.
- `password` — required, confirmed, password-policy rule (min 8, letters, mixed case, numbers, symbols; `uncompromised()` when not testing).

**Sanitization (`prepareForValidation`):** Lowercases+trims email.

**Messages/localization:** No custom `messages()`.

**WHICH controller uses it:** `PasswordResetController::resetPassword()` at `app/Http/Controllers/PasswordResetController.php:44`.

---

### `ChangePasswordRequest.php`

**File:** [`app/Http/Requests/ChangePasswordRequest.php`](ChangePasswordRequest.php)

**What HTTP action it validates:** `POST` change-password — for an authenticated user changing their own password.

**`authorize()`:** Always `true` (route is already guarded by auth middleware).

**Validation rules:**
- `current_password` — required, string.
- `new_password` — required, string, `different:current_password`, and the password-policy rule (min 8, letters, mixed case, numbers, symbols; `uncompromised()` when not testing).

**Messages/localization (`messages()`):**
- `current_password.required` — "A palavra-passe atual é obrigatória."
- `current_password.current_password` — custom message (in practice the `current_password` validation rule is enforced in the Service/Action, since the request does not apply it).
- `new_password.required` — "A nova palavra-passe é obrigatória."
- `new_password.different` — "A nova palavra-passe deve ser diferente da palavra-passe atual."

**`attributes()`:** Maps `current_password` → "palavra-passe atual", `new_password` → "nova palavra-passe".

**WHICH controller uses it:** `ProfileController::changePassword()` at `app/Http/Controllers/ProfileController.php:16`.

---

### `UpdateProfileRequest.php`

**File:** [`app/Http/Requests/UpdateProfileRequest.php`](UpdateProfileRequest.php)

**What HTTP action it validates:** `PUT`-style profile update — for an authenticated user editing their own name and/or password.

**`authorize()`:** Always `true`.

**Validation rules:**
- `name` — `sometimes`, string, max 255.
- `current_password` — `required_with:password`, nullable, string, `current_password` (Laravel's built-in rule whitelisted in `config/auth.php`).
- `password` — nullable, string, password-policy rule, `confirmed`. (Changing password requires the current password; changing only the name requires neither.)

**Sanitization (`prepareForValidation`):** Trims the name.

**Messages/localization:** No custom `messages()`; `attributes()` maps name/current_password/password/password_confirmation to friendly English labels.

**WHICH controller uses it:** `ProfileController::updateProfile()` at `app/Http/Controllers/ProfileController.php:37`.

---

## User & Profile admin

### `StoreUserRequest.php`

**File:** [`app/Http/Requests/StoreUserRequest.php`](StoreUserRequest.php)

**What HTTP action it validates:** `POST` create user (admin back-office).

**`authorize()`:** Always `true` (admin-only path enforced by route middleware).

**Validation rules:**
- `name` — required, string, max 255.
- `email` — required, string, lowercase, email, max 255, **unique** in `users.email`.
- `password` — required, string, password-policy rule (inline closure).
- `profile_id` — required, integer, `exists` in `user_profiles.id`.
- `active` — `sometimes`, boolean.

**Sanitization (`prepareForValidation`):** Trims name; lowercases+trims email.

**Messages/localization:** No `messages()`; `attributes()` labels fields in Portuguese (`nome`, `perfil`, `palavra-passe`, `status ativo`).

**WHICH controller uses it:** `AdminUserController::store()` at `app/Http/Controllers/AdminUserController.php:51`.

---

### `UpdateUserRequest.php`

**File:** [`app/Http/Requests/UpdateUserRequest.php`](UpdateUserRequest.php)

**What HTTP action it validates:** `PUT` update user (admin back-office), keyed by `targetUser`/`id` route param.

**`authorize()`:** Always `true` (admin-only path).

**Validation rules:**
- `name` — `sometimes`, string, max 255.
- `email` — `sometimes`, string, email, max 255, **unique** in `users.email` **ignoring** the current route user.
- `password` — nullable, string, password-policy rule (inline).
- `profile_id` — `sometimes`, integer, `exists` in `user_profiles.id`.
- `active` — `sometimes`, boolean.

**Sanitization (`prepareForValidation`):** Trims name; lowercases+trims email (when present).

**Messages/localization:** No `messages()`; `attributes()` uses Portuguese labels (`nome`, `e-mail`, `palavra-passe`, `perfil de utilizador`, `status ativo`).

**WHICH controller uses it:** `AdminUserController::update()` at `app/Http/Controllers/AdminUserController.php:71`.

---

### `UploadPhotoRequest.php`

**File:** [`app/Http/Requests/UploadPhotoRequest.php`](UploadPhotoRequest.php)

**What HTTP action it validates:** Photo/avatar upload (used for ticket attachments).

**`authorize()`:** Always `true`.

**Validation rules (for `photo`):** every limit comes from `App\Support\PhotoUploadPolicy`, the same source the public QR-code form and the storing controllers use, so the two upload paths cannot drift apart.
- required; must be an image via `File::image()` (which also refuses SVG).
- `types()` — from `config('services.custom.upload.allowed_photo_mimes')`, default `jpeg, jpg, png, gif, webp`; an empty or unusable list falls back to those defaults.
- `max()` — from `config('services.custom.upload.max_photo_size_kb')` (default `2048` KB); non-positive or non-numeric values fall back to `2048`.
- `dimensions()` — max width/height from `services.custom.upload.max_photo_width` / `max_photo_height` (default `4096` px each).

**Messages/localization:** No custom `messages()`; `attributes()` maps `photo`.

**WHICH controller uses it:** `TicketAttachmentController::store()` at `app/Http/Controllers/TicketAttachmentController.php:20`.

---

## Tickets

### `StoreTicketRequest.php`

**File:** [`app/Http/Requests/StoreTicketRequest.php`](StoreTicketRequest.php)

**What HTTP action it validates:** `POST` create a new ticket (authenticated users).

**`authorize()`:** Always `true` (ticket authorization is enforced via policy in the controller).

**Validation rules:**
- `title` — required, string, max 255.
- `description` — required, string, max 5000.
- `priority` — required; must be in `TicketPriorityEnum::acceptedValues()` (includes unaccented aliases like `media`, `critica`).
- `equipment_id` — nullable, integer, `exists` in `equipment.id`.
- `room_id` — nullable, integer, `exists` in `rooms.id`.

**Sanitization (`prepareForValidation`):** Trims title and description.

**Messages/localization:** No custom `messages()`; `attributes()` maps fields to English labels.

**WHICH controller uses it:** `TicketController::store()` at `app/Http/Controllers/TicketController.php:58`.

---

### `PublicStoreTicketRequest.php`

**File:** [`app/Http/Requests/PublicStoreTicketRequest.php`](PublicStoreTicketRequest.php)

**What HTTP action it validates:** `POST` public ticket submission via the QR-code form (non-authenticated reporters).

**`authorize()`:** Always `true` (public endpoint).

**Validation rules:**
- `equipment_id` — required, integer, `exists` in `equipment.id`.
- `problem_type` — required; must be in `PublicTicketProblemTypeEnum::values()` (`avaria`, `manutencao_preventiva`, `falta_consumiveis`, `outro`).
- `description` — required, string, max 5000.
- `reporter_name` — nullable, string, max 150.
- `reporter_contact` — nullable, string, max 150.
- `photo` — nullable, image file; the same `PhotoUploadPolicy` limits as `UploadPhotoRequest` (types, `max_photo_size_kb`, max width/height), so a photo the storage layer can keep is never refused and one it cannot keep is reported to the reporter instead of being dropped after the ticket is created.

**Messages/localization:** No custom `messages()`; `attributes()` maps English labels.

**WHICH controller uses it:** `PublicTicketController::store()` at `app/Http/Controllers/PublicTicketController.php:54` (and its private `storePhoto()` helper at line 99); `create()` at line 31 passes the policy's types to the form's `accept` attribute.

---

### `AssignTechnicianToTicketRequest.php`

**File:** [`app/Http/Requests/AssignTechnicianToTicketRequest.php`](AssignTechnicianToTicketRequest.php)

**What HTTP action it validates:** Ticket technician (re)assignment.

**`authorize()`:** Always `true` (checked via policy in controller).

**Validation rules:**
- `technician_id` — nullable, integer, `exists` in `users.id`. A `null` value means "unassign".

**Messages/localization (`messages()`):**
- `technician_id.required` — "O campo técnico é obrigatório."
- `technician_id.integer` — "O identificador do técnico deve ser um número inteiro."
- `technician_id.exists` — "O técnico selecionado é inválido."

**`attributes()`:** Maps `technician_id` → "técnico".

**WHICH controller uses it:** `TicketAssignmentController::__invoke()` at `app/Http/Controllers/Ticket/TicketAssignmentController.php:27`.

---

### `StartTicketRequest.php`

**File:** [`app/Http/Requests/StartTicketRequest.php`](StartTicketRequest.php)

**What HTTP action it validates:** Starts (begins working on) a ticket.

**`authorize()`:** Reads the `ticket` route model and returns `true` only if `$this->user()?->can('update', $ticket)`.

**Validation rules:**
- `force` — `sometimes`, boolean (whether to bypass constraints).

**Extra helper:** `isForced(): bool` — type-safe getter wrapping `$this->boolean('force')`.

**Messages/localization:** None.

**WHICH controller uses it:** `TicketStartController::__invoke()` at `app/Http/Controllers/Ticket/TicketStartController.php:27`.

---

### `ScheduleTicketRequest.php`

**File:** [`app/Http/Requests/ScheduleTicketRequest.php`](ScheduleTicketRequest.php)

**What HTTP action it validates:** Schedules a ticket into a calendar window.

**`authorize()`:** Always `true` (policy enforced in controller).

**Validation rules:**
- `scheduled_at` — required, date, matching `date_format: Y-m-d\TH:i` or `Y-m-d H:i:s` (HTML5 datetime-local or DB datetime), and `after_or_equal:now`.
- `scheduled_end` — nullable, date, matching the same date formats, and `after:scheduled_at`.

**Messages/localization:** None.

**WHICH controller uses it:** `TicketScheduleController::__invoke()` at `app/Http/Controllers/Ticket/TicketScheduleController.php:23`.

---

### `CloseTicketRequest.php`

**File:** [`app/Http/Requests/CloseTicketRequest.php`](CloseTicketRequest.php)

**What HTTP action it validates:** Final closing of a ticket (with cost + report + optional force flag).

**`authorize()`:** Always `true`.

**Validation rules:**
- `actual_cost` — required, numeric, `min:0`.
- `report` — nullable, string, max 5000.
- `force` — nullable, boolean.

**Messages/localization (`messages()`):**
- `actual_cost.required` — custo real obrigatório.
- `actual_cost.numeric` — deve ser numérico.
- `actual_cost.min` — não pode ser negativo.
- `report.max` — relatório não pode exceder 5000 caracteres.
- `force.boolean` — deve ser verdadeiro ou falso.

**`attributes()`:** `actual_cost` → "custo real", `report` → "relatório técnico", `force` → "forçar encerramento".

**WHICH controller uses it:** `TicketCloseController::closeFinal()` at `app/Http/Controllers/Ticket/TicketCloseController.php:66`.

---

### `CloseTicketSimpleRequest.php`

**File:** [`app/Http/Requests/CloseTicketSimpleRequest.php`](CloseTicketSimpleRequest.php)

**What HTTP action it validates:** A simplified "quick close" of a ticket (time + cost + report, all optional).

**`authorize()`:** Always `true`.

**Validation rules:**
- `minutes_spent` — nullable, integer, `min:0`.
- `cost` — nullable, numeric, `min:0`.
- `technical_report` — nullable, string, max 5000.

**Messages/localization (`messages()`):**
- `minutes_spent.integer` / `minutes_spent.min` — "O tempo despendido ...".
- `cost.numeric` / `cost.min` — "O custo ...".
- `technical_report.max` — "O relatório técnico não pode exceder 5000 caracteres."

**`attributes()`:** maps to "minutos despendidos", "custo", "relatório técnico".

**WHICH controller uses it:** `TicketCloseController::simpleClose()` at `app/Http/Controllers/Ticket/TicketCloseController.php:30`.

---

### `StoreCommentRequest.php`

**File:** [`app/Http/Requests/StoreCommentRequest.php`](StoreCommentRequest.php)

**What HTTP action it validates:** Adding a comment to a ticket.

**`authorize()`:** Always `true` (the in-source example for a policy check is commented out; actual authorization relies on route/policy).

**Validation rules:**
- `comment` — required, string, `min:3`, `max:2000`.

**Sanitization (`prepareForValidation`):** Trims the `comment` field.

**Messages/localization:** None.

**WHICH controller uses it:** `TicketCommentController::store()` at `app/Http/Controllers/TicketCommentController.php:17`.

---

## Budgets

### `RequestBudgetRequest.php`

**File:** [`app/Http/Requests/RequestBudgetRequest.php`](RequestBudgetRequest.php)

**What HTTP action it validates:** A formal budget request from a technician (with line-item details) for authorization by an admin.

**`authorize()`:** Always `true`.

**Validation rules:**
- `budget_amount` — required, numeric, `min:0.01`.
- `budget_details` — nullable, array, `min:1` (at least one line item).
- `budget_details.*.description` — required, string, max 255.
- `budget_details.*.quantity` — required, integer, `min:1`.
- `budget_details.*.unit_price` — required, numeric, `min:0`.

**`after()` hook:** If line items are present, sums `quantity × unit_price` for each detail; if the total differs from `budget_amount` by more than `0.01`, adds an error "The total budget amount does not match the sum of the line item details."

**`attributes()`:** Maps nested field names to English labels.

**WHICH controller uses it:** `TicketBudgetController::requestAuthorization()` at `app/Http/Controllers/TicketBudgetController.php:49`.

---

### `SubmitBudgetRequest.php`

**File:** [`app/Http/Requests/SubmitBudgetRequest.php`](SubmitBudgetRequest.php)

**What HTTP action it validates:** A technician's initial budget estimate submission (before formal authorization).

**`authorize()`:** Always `true`.

**Validation rules:**
- `estimated_budget` — required, numeric, `min:0.01`.
- `budget_details` — nullable, array, `min:1`.
- `budget_details.*.description` — required, string, max 255.
- `budget_details.*.type` — required, string, `in:material,labor`.
- For **material** items: `quantity` (`required_if` type=material, nullable, numeric, `min:0.01`) and `unit_price` (`required_if`, nullable, numeric, `min:0`).
- For **labor** items: `hours` (`required_if` type=labor, nullable, numeric, `min:0.1`) and `hourly_rate` (`required_if`, nullable, numeric, `min:0`).

**`after()` hook:** Sums line items — material = quantity × unit_price; labor = hours × hourly_rate — and compares total to `estimated_budget`; if the difference > `0.01`, errors on `estimated_budget`: "The estimated total does not match the sum of the budget details."

**`attributes()`:** Maps flat + nested fields to English labels (`estimated budget`, `item type`, `quantity`, `hours`, `hourly rate`, ...).

**WHICH controller uses it:** `TicketBudgetController::submitEstimate()` at `app/Http/Controllers/TicketBudgetController.php:31`.

---

### `BudgetDecisionRequest.php`

**File:** [`app/Http/Requests/BudgetDecisionRequest.php`](BudgetDecisionRequest.php)

**What HTTP action it validates:** An admin approving or rejecting a pending budget request.

**`authorize()`:** Always `true`.

**Validation rules:**
- `decision` — required, string, `in:approve,reject`.
- `feedback` — nullable, string, max 5000, `required_if:decision,reject` (justification required when rejecting).

**Messages/localization (`messages()`):**
- `decision.required` — "A decisão sobre o orçamento é obrigatória."
- `decision.in` — "A decisão deve ser aprovar (approve) ou rejeitar (reject)."
- `feedback.required_if` — "É obrigatório fornecer um feedback/justificação ao rejeitar o orçamento."
- `feedback.max` — "O feedback não pode exceder 5000 caracteres."

**`attributes()`:** `decision` → "decisão", `feedback` → "feedback/justificação".

**WHICH controller uses it:** `AdminController::approveBudget()` at `app/Http/Controllers/AdminController.php:25`.

---

## Calendar / Preventive

### `ScheduleMaintenanceRequest.php`

**File:** [`app/Http/Requests/ScheduleMaintenanceRequest.php`](ScheduleMaintenanceRequest.php)

**What HTTP action it validates:** Scheduling a preventive maintenance intervention on equipment.

**`authorize()`:** `return $this->user()?->isAdmin() ?? false` — **admin-only**.

**Validation rules:**
- `title` — required, string, max 255.
- `description` — nullable, string, max 5000.
- `equipment_id` — required, integer, `exists` in `equipment.id`.
- `scheduled_at` — required, date.
- `assigned_to` — nullable, integer, `exists` in `users.id`.

**Sanitization (`prepareForValidation`):** Trims title and description (empty description → `null`).

**Messages/localization:** No custom `messages()`; `attributes()` maps English labels.

**WHICH controller uses it:** `CalendarController::scheduleMaintenance()` at `app/Http/Controllers/CalendarController.php:52`.

---

### `StorePreventiveRequest.php`

**File:** [`app/Http/Requests/StorePreventiveRequest.php`](StorePreventiveRequest.php)

**What HTTP action it validates:** Creating a preventive-ticket/event via the admin back-office.

**`authorize()`:** Always `true`.

**Validation rules:**
- `title` — required, string, max 255.
- `description` — nullable, string.
- `scheduled_at` — required, date, matching `date_format: Y-m-d\TH:i, Y-m-d H:i:s, Y-m-d`, and `after_or_equal:today`.
- `technician_id` — nullable, integer, `exists` in `users.id`.

**Sanitization (`prepareForValidation`):** Trims title/description.

**Messages/localization:** No custom `messages()`; `attributes()` maps English labels.

**WHICH controller uses it:** `AdminController::storePreventive()` at `app/Http/Controllers/AdminController.php:56`.

---

### `RescheduleEventRequest.php`

**File:** [`app/Http/Requests/RescheduleEventRequest.php`](RescheduleEventRequest.php)

**What HTTP action it validates:** Rescheduling a calendar event (ticket) to new start/end dates.

**`authorize()`:** `return $this->user() !== null` — requires an authenticated user.

**Validation rules:**
- `start` — required, date.
- `end` — nullable, date.

**Messages/localization:** No custom `messages()`; `attributes()` maps `start`/`end`.

**WHICH controller uses it:** `CalendarController::reschedule()` at `app/Http/Controllers/CalendarController.php:76`.

---

## Equipment & Rooms

### `StoreEquipmentRequest.php`

**File:** [`app/Http/Requests/StoreEquipmentRequest.php`](StoreEquipmentRequest.php)

**What HTTP action it validates:** Creating a new equipment record (admin back-office).

**`authorize()`:** Always `true`.

**Validation rules:**
- `name` — required, string, max 255.
- `serial` — required, string, max 255, **unique** in `equipment.serial`.
- `room_id` — nullable, integer, `exists` in `rooms.id`.
- `category_id` — nullable, integer, `exists` in `equipment_categories.id`.
- `active` — `sometimes`, boolean.
- `asset_tag` — nullable, string, max 100, **unique** in `equipment.asset_tag`.
- `brand`, `model`, `manufacturer` — nullable, string, max 100 each.
- `purchase_date`, `warranty_until` — nullable, date.
- `status` — nullable; must be in `['operacional', 'manutenção', 'avariado', 'abatido']` (Portuguese DB values).
- `notes` — nullable, string.

**Sanitization (`prepareForValidation`):** Trims name and serial.

**Messages/localization:** No custom `messages()`; `attributes()` maps English labels.

**WHICH controller uses it:** `AdminEquipmentController::store()` at `app/Http/Controllers/AdminEquipmentController.php:49`.

---

### `UpdateEquipmentRequest.php`

**File:** [`app/Http/Requests/UpdateEquipmentRequest.php`](UpdateEquipmentRequest.php)

**What HTTP action it validates:** Updating an equipment record (admin back-office), keyed by `equipment`/`id` route param.

**`authorize()`:** Always `true`.

**Validation rules:**
- Follows `StoreEquipmentRequest` but uses `sometimes` on `name` and `serial`; `serial` and `asset_tag` remain **unique but ignore the current route equipment** (`Rule::unique(...)->ignore($this->route('equipment') ?? $this->route('id'))`).

**Sanitization (`prepareForValidation`):** Trims name/serial when present.

**Messages/localization:** No custom `messages()`; `attributes()` matches StoreEquipmentRequest.

**WHICH controller uses it:** `AdminEquipmentController::update()` at `app/Http/Controllers/AdminEquipmentController.php:69`.

---

### `StoreRoomRequest.php`

**File:** [`app/Http/Requests/StoreRoomRequest.php`](StoreRoomRequest.php)

**What HTTP action it validates:** Creating a new room.

**`authorize()`:** Always `true`.

**Validation rules:**
- `name` — required, string, max 255, **unique** in `rooms.name`.
- `location` — nullable, string, max 255.
- `code` — nullable, string, max 50, **unique** in `rooms.code`.
- `building` — nullable, string, max 100.
- `floor` — nullable, string, max 50.
- `capacity` — nullable, integer, `min:0`, `max:65535`.
- `description`, `notes` — nullable, string.
- `active` — `sometimes`, boolean.

**Sanitization (`prepareForValidation`):** Trims name and location.

**Messages/localization:** No custom `messages()`; `attributes()` maps English labels.

**WHICH controller uses it:** `RoomController::storeRoom()` at `app/Http/Controllers/RoomController.php:50`.

---

### `UpdateRoomRequest.php`

**File:** [`app/Http/Requests/UpdateRoomRequest.php`](UpdateRoomRequest.php)

**What HTTP action it validates:** Updating a room, keyed by `room`/`id` route param.

**`authorize()`:** Always `true`.

**Validation rules:** Same field set as `StoreRoomRequest`, but `name` is `sometimes` and both `name` and `code` uniqueness **ignore** the current route room.

**Sanitization (`prepareForValidation`):** Trims name and location when present.

**Messages/localization:** No custom `messages()`; `attributes()` matches StoreRoomRequest.

**WHICH controller uses it:** `RoomController::updateRoom()` at `app/Http/Controllers/RoomController.php:68`.

---

## Parts & Categories

### `StorePartRequest.php`

**File:** [`app/Http/Requests/StorePartRequest.php`](StorePartRequest.php)

**What HTTP action it validates:** Creating a new spare part.

**`authorize()`:** Always `true`.

**Validation rules:**
- `sku` — required, string, max 100, **unique** in `parts.sku`.
- `name` — required, string, max 150.
- `description` — nullable, string.
- `brand` — nullable, string, max 100.
- `manufacturer_ref` — nullable, string, max 100.
- `part_category_id` — nullable, integer, `exists` in `part_categories.id`.
- `unit_of_measure` — required; must be in `PartUnitOfMeasureEnum::values()`.
- `cost_price` — required, numeric, `min:0`, `max:99999999.99`.
- `tax_rate_id` — nullable, integer, `exists` in `tax_rates.id`.
- `sale_price` — nullable, numeric, `min:0`, `max:99999999.99`.
- `current_stock` — required, integer, `min:0`.
- `min_stock` — required, integer, `min:0`.
- `max_stock` — nullable, integer, `gte:min_stock`.
- `location` — nullable, string, max 150.
- `photo` — nullable, string, max 255.
- `active` — `sometimes`, boolean.
- `technical_notes` — nullable, string.

**Sanitization (`prepareForValidation`):** Trims sku and name.

**Messages/localization:** No custom `messages()`; `attributes()` maps English labels (`code (SKU)`, `cost price`, `VAT rate`, etc.).

**WHICH controller uses it:** `PartController::store()` at `app/Http/Controllers/PartController.php:134`.

---

### `UpdatePartRequest.php`

**File:** [`app/Http/Requests/UpdatePartRequest.php`](UpdatePartRequest.php)

**What HTTP action it validates:** Updating a part, keyed by `part` route param.

**`authorize()`:** Always `true`.

**Validation rules:** Same as `StorePartRequest` (all fields required in the same way), except:
- `sku` uniqueness **ignores** the current route part (`Rule::unique(Part::class, 'sku')->ignore($this->route('part'))`).
- **No `current_stock` field** — stock is not edited directly; stock changes go through stock movements.

**Sanitization (`prepareForValidation`):** Trims sku and name.

**Messages/localization:** No custom `messages()`; `attributes()` matches StorePartRequest.

**WHICH controller uses it:** `PartController::update()` at `app/Http/Controllers/PartController.php:187`.

---

### `StorePartCategoryRequest.php`

**File:** [`app/Http/Requests/StorePartCategoryRequest.php`](StorePartCategoryRequest.php)

**What HTTP action it validates:** Creating a part category.

**`authorize()`:** Always `true`.

**Validation rules:**
- `name` — required, string, max 100, **unique** in `part_categories.name`.
- `active` — `sometimes`, boolean.

**Messages/localization:** No `messages()`; `attributes()` uses Portuguese labels (`nome`, `ativo`).

**WHICH controller uses it:** `PartCategoryController::store()` at `app/Http/Controllers/PartCategoryController.php:67`.

---

### `UpdatePartCategoryRequest.php`

**File:** [`app/Http/Requests/UpdatePartCategoryRequest.php`](UpdatePartCategoryRequest.php)

**What HTTP action it validates:** Updating a part category, keyed by `category` route param.

**`authorize()`:** Always `true`.

**Validation rules:**
- `name` — required, string, max 100, unique in `part_categories.name` **ignoring** the current route `category`.
- `active` — `sometimes`, boolean.

**Messages/localization:** `attributes()` uses Portuguese labels (`nome`, `ativo`).

**WHICH controller uses it:** `PartCategoryController::update()` at `app/Http/Controllers/PartCategoryController.php:108`.

---

### `StoreMaintenancePlanRequest.php`

**File:** [`app/Http/Requests/StoreMaintenancePlanRequest.php`](StoreMaintenancePlanRequest.php)

**What HTTP action it validates:** Creating a maintenance plan (recurring preventive schedule).

**`authorize()`:** Always `true`.

**Validation rules:**
- `equipment_id` — required, integer, `exists` in `equipment.id`.
- `name` — required, string, max 150.
- `interval_type` — required; must be in `MaintenancePlanIntervalTypeEnum::values()` (`days`, `usage_hours`, `cycles`).
- `interval_value` — required, integer, `min:1`.
- `description` — nullable, string.
- `active` — `sometimes`, boolean.
- `parts` (optional nested array):
  - `parts.*.part_id` — required, integer, `exists` in `parts.id`.
  - `parts.*.expected_quantity` — `sometimes`, integer, `min:1`.

**Messages/localization:** No custom `messages()`; `attributes()` maps English labels.

**WHICH controller uses it:** `MaintenancePlanController::store()` at `app/Http/Controllers/MaintenancePlanController.php:125`.

---

### `UpdateMaintenancePlanRequest.php`

**File:** [`app/Http/Requests/UpdateMaintenancePlanRequest.php`](UpdateMaintenancePlanRequest.php)

**What HTTP action it validates:** Updating a maintenance plan.

**`authorize()`:** Always `true`.

**Validation rules:** Same as `StoreMaintenancePlanRequest` (same required fields + nested `parts` array), minus `equipment_id`.

**Messages/localization:** No custom `messages()`; `attributes()` maps English labels.

**WHICH controller uses it:** `MaintenancePlanController::update()` at `app/Http/Controllers/MaintenancePlanController.php:193`.

---

## Stock

### `StoreStockMovementRequest.php`

**File:** [`app/Http/Requests/StoreStockMovementRequest.php`](StoreStockMovementRequest.php)

**What HTTP action it validates:** Recording a stock movement (in/out/adjust/return) on a part.

**`authorize()`:** Always `true`.

**Validation rules:**
- `part_id` — required, integer, `exists` in `parts.id`.
- `movement_type` — required; must be in `StockMovementTypeEnum::values()` (`in`, `out`, `adjust`, `return`).
- `quantity` — required, integer (note: sign/direction validation is handled by the service).
- `reason` — nullable, string, max 255.
- `ticket_id` — nullable, integer, `exists` in `tickets.id`.
- `equipment_id` — nullable, integer, `exists` in `equipment.id`.
- `unit_price_snapshot` — nullable, numeric, `min:0`, `max:99999999.99`.

**Messages/localization:** No custom `messages()`; `attributes()` maps English labels.

**WHICH controller uses it:** `StockMovementController::store()` at `app/Http/Controllers/StockMovementController.php:105`.

---

## Suppliers & Tax

### `StoreSupplierRequest.php`

**File:** [`app/Http/Requests/StoreSupplierRequest.php`](StoreSupplierRequest.php)

**What HTTP action it validates:** Creating a supplier.

**`authorize()`:** Always `true`.

**Validation rules:**
- `name` — required, string, max 150.
- `nif` — nullable, string, max 30, **unique** in `suppliers.nif`.
- `contact` — nullable, string, max 100.
- `email` — nullable, email, max 150.
- `address` — nullable, string.
- `avg_lead_time_days` — nullable, integer, `min:0`.

**Sanitization (`prepareForValidation`):** Trims the name.

**Messages/localization:** No custom `messages()`; `attributes()` uses `LocaleService::taxIdentifierLabel()` for the `nif` label (locale-aware tax ID name).

**WHICH controller uses it:** `SupplierController::store()` at `app/Http/Controllers/SupplierController.php:122`.

---

### `UpdateSupplierRequest.php`

**File:** [`app/Http/Requests/UpdateSupplierRequest.php`](UpdateSupplierRequest.php)

**What HTTP action it validates:** Updating a supplier, keyed by `supplier` route param.

**`authorize()`:** Always `true`.

**Validation rules:** Same as `StoreSupplierRequest`, except `nif` uniqueness **ignores** the current route supplier.

**Sanitization (`prepareForValidation`):** Trims the name.

**Messages/localization:** `attributes()` uses `LocaleService::taxIdentifierLabel()` for `nif`.

**WHICH controller uses it:** `SupplierController::update()` at `app/Http/Controllers/SupplierController.php:165`.

---

### `StoreTaxRateRequest.php`

**File:** [`app/Http/Requests/StoreTaxRateRequest.php`](StoreTaxRateRequest.php)

**What HTTP action it validates:** Creating a tax rate (e.g., VAT rate).

**`authorize()`:** Always `true`.

**Validation rules:**
- `name` — required, string, max 100.
- `percent` — required, numeric, `min:0`, `max:100`.
- `is_default` — `sometimes`, boolean.
- `active` — `sometimes`, boolean.

**Messages/localization:** No custom `messages()`; `attributes()` maps English labels.

**WHICH controller uses it:** `TaxRateController::store()` at `app/Http/Controllers/TaxRateController.php:69`.

---

### `UpdateTaxRateRequest.php`

**File:** [`app/Http/Requests/UpdateTaxRateRequest.php`](UpdateTaxRateRequest.php)

**What HTTP action it validates:** Updating a tax rate.

**`authorize()`:** Always `true`.

**Validation rules:** Same as `StoreTaxRateRequest` (same required + optional fields).

**Messages/localization:** No custom `messages()`; `attributes()` maps English labels.

**WHICH controller uses it:** `TaxRateController::update()` at `app/Http/Controllers/TaxRateController.php:114`.

---

## Notes

- `attributes()` labels are user-facing strings — part of the i18n domain, not normalized in this refactor
- Validation messages using `__()` translation keys are also i18n domain
- Database enum values in rules (e.g., `'operacional'`, `'manutenção'`) are DB-level constraints — reported separately
- Admin-only authorization is enforced at the `authorize()` level only in `ScheduleMaintenanceRequest`; the other admin back-office endpoints (`StoreUser`, `UpdateUser`, `StoreEquipment`, `UpdateEquipment`, `StorePreventive`, `BudgetDecision`) rely on route-level auth middleware / policies for admin enforcement.
