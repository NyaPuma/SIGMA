# app/Enums

Enumeration classes (backed string enums) representing standard domain state machines, role hierarchies, event types, priorities, and workflow categories across the SGM application.

> **New to programming?** See [NON_TECHNICAL_PROJECT_GUIDE.md](../../NON_TECHNICAL_PROJECT_GUIDE.md) -- this folder is explained as "The Rulebook" with predefined lists of allowed values (roles, statuses, priorities) that keep everyone on the same page.

## Overview

All 14 enums are **backed string enums** (`enum X: string`). Each exposes a `label()` method that returns a localized (Portuguese) human-readable string via Laravel's `__()` translation helper, keeping the PHP identifiers 100% in English while the display text follows the active locale.

Most enums also expose a `normalize(mixed $value): ?self` static method designed to safely coerce raw input (accents, case, aliases, or already-instantiated enum) into a valid enum instance — returning `null` for unrecognized input instead of throwing.

## Directory

| # | Enum | Backing values | Key purpose |
|---|------|---------------|-------------|
| 1 | [`UserRoleEnum`](#userroleenum) | user, technician, admin | RBAC role hierarchy |
| 2 | [`TicketPriorityEnum`](#ticketpriorityenum) | baixa, média, alta, crítica | Ticket urgency + SLA |
| 3 | [`TicketStatusEnum`](#ticketstatusenum) | aberta, em curso, fechada, cancelada, pendente orçamento, recusada | Ticket operational status |
| 4 | [`TicketWorkflowStatusEnum`](#ticketworkflowstatusenum) | open, in_progress, waiting_budget, approved, rejected, closed, cancelled | Explicit workflow state machine |
| 5 | [`BudgetStatusEnum`](#budgetstatusenum) | pending, approved, rejected | Budget lifecycle |
| 6 | [`BudgetDecisionEnum`](#budgetdecisionenum) | approve, reject | Budget approval action |
| 7 | [`StockMovementTypeEnum`](#stockmovementtypeenum) | in, out, adjust, return | Stock inventory direction |
| 8 | [`PartUnitOfMeasureEnum`](#partunitofmeasureenum) | unit, meter, liter, kg, pair, set, roll, other | Spare-part measurement |
| 9 | [`PublicTicketProblemTypeEnum`](#publicticketproblemtypeenum) | avaria, manutencao_preventiva, falta_consumiveis, outro | QR public-form incident category |
| 10 | [`NotificationTypeEnum`](#notificationtypeenum) | budget_request … low_stock | Notification channel trigger |
| 11 | [`NotificationPriorityEnum`](#notificationpriorityenum) | low, normal, high, urgent | Notification urgency |
| 12 | [`MaintenancePlanIntervalTypeEnum`](#maintenanceplanintervaltypeenum) | days, usage_hours, cycles | Recurrence unit for plans |
| 13 | [`FileTypeEnum`](#filetypeenum) | image, document, video, audio, other | Attachment MIME classification |
| 14 | [`AuditEventEnum`](#auditeventenum) | created, updated, deleted, login, logout, password_changed | Audit trail event action |

## Detailed per-enum reference

### `UserRoleEnum`

**File:** [`app/Enums/UserRoleEnum.php`](UserRoleEnum.php)

Represents the application's RBAC hierarchy. Backing values mirror the `users.role` column.

| Case | Value | label() |
|------|-------|---------|
| `User` | `user` | Utilizador |
| `Technician` | `technician` | Técnico |
| `Admin` | `admin` | Administrador |

**Helper methods:**
- `label(): string` — localized display name.
- `color(): string` — Tailwind badge class (`gray`, `info`, `purple`).
- `icon(): string` — Heroicons outline name.
- `weight(): int` — hierarchy rank (User=1, Technician=2, Admin=3).
- `isAdmin()`, `isTechnician()`, `isUser(): bool` — direct role checks.
- `hasElevatedPrivileges(): bool` — true for Technician/Admin.
- `hasAtLeastRole(self $requiredRole): bool` — hierarchy comparison via `weight()`.
- `normalize(mixed): ?self` — accepts `'admin'/'administrador'`, `'technician'/'técnico'/'tecnico'`, `'user'/'utilizador'/'usuário'/'usuario'`, or exact enum value.

**Where it's used (real usages):**
- `app/Models/User.php:7` — cast on the model.
- `app/Repositories/UserRepository.php:7` — role filtering in queries.
- `app/Http/Controllers/RegisterController.php:5` — sets default role on registration.
- `app/Http/Controllers/UiController.php:8` — exposes role options to the UI.
- `app/Services/UserService.php:7`, `app/Services/TechnicianAssignmentService.php:9`, `app/Observers/UserObserver.php:7`, `app/Console/Commands/SimulateTelemetry.php:7`.

---

### `TicketPriorityEnum`

**File:** [`app/Enums/TicketPriorityEnum.php`](TicketPriorityEnum.php)

Represents ticket urgency levels. **Backing values are Portuguese and match the legacy `tickets.priority` DB column.** Do not rename without a schema/data migration.

| Case | Value | label() |
|------|-------|---------|
| `Low` | `baixa` | Baixa |
| `Medium` | `média` | Média |
| `High` | `alta` | Alta |
| `Critical` | `crítica` | Crítica |

**Helper methods:**
- `values(): array` — raw values.
- `acceptedValues(): array` — includes unaccented aliases (`'media'`, `'critica'`).
- `label(): string` — localized.
- `color()` / `icon()` — badge/icon helpers.
- `weight(): int` — Low=1, Medium=2, High=3, Critical=4 (for sorting).
- `slaHours(): int` — Low=48, Medium=24, High=8, Critical=2 (response SLA in hours).
- `requiresImmediateAttention(): bool` — true for High/Critical.
- `normalize(mixed): ?self` — accepts English (`'low'`, `'medium'`, `'high'`, `'critical'`, `'urgent'`) and Portuguese aliases, with/without accents.

**Where it's used (real usages):**
- `app/DTOs/CreateTicketData.php:5` and `app/DTOs/TicketFilters.php:5` — DTO normalization.
- `app/Http/Controllers/TicketController.php:8` — validates the `priority` query param.
- `app/Exports/TicketsExport.php:5` — resolves labels in the Excel export.
- `app/Services/TechnicianAssignmentService.php:7`, `app/Services/AnalyticsDashboardService.php:14`, `app/Services/AnalyticsExportService.php:8`.
- `app/Events/TicketCreated.php:5`, `app/Domain/Ticket/Actions/CheckHigherPriorityAction.php:5`, `app/Actions/CreatePreventiveTicketAction.php:5`,
- `app/Http/Resources/TicketResource.php:7`, `app/Http/Requests/StoreTicketRequest.php:7`, `app/Console/Commands/SimulateTelemetry.php:5`.

---

### `TicketStatusEnum`

**File:** [`app/Enums/TicketStatusEnum.php`](TicketStatusEnum.php)

Represents the operational status of a ticket, mapped to the legacy `ticket_statuses` DB table via `$ticket->status->name`. **Backing values are Portuguese and should not be renamed without migration.**

| Case | Value | label() |
|------|-------|---------|
| `Open` | `aberta` | Aberta |
| `InProgress` | `em curso` | Em Curso |
| `Closed` | `fechada` | Fechada |
| `Cancelled` | `cancelada` | Cancelada |
| `PendingBudget` | `pendente orçamento` | Pendente Orçamento |
| `Rejected` | `recusada` | Recusada |

**Helper methods:**
- `values(): array` — raw values.
- `acceptedValues(): array` — includes unaccented alias `'pendente orcamento'`.
- `label()`, `color()`, `icon()` — display helpers.
- `isFinal(): bool` — true for Closed/Cancelled/Rejected (terminal states).
- `isActive(): bool` — `! $this->isFinal()`.
- `normalize(mixed): ?self` — accepts English/Portuguese aliases with/without spaces/accents.

**Where it's used (real usages — the most widely used enum in the codebase):**
- `app/Models/Ticket.php:7` — model integration.
- `app/Http/Resources/TicketResource.php:8` — resolves the `status_label`.
- `app/Http/Controllers/TicketController.php:8`, `app/Http/Controllers/TicketBudgetController.php:8`.
- `app/Http/Controllers/Ticket/TicketStartController.php:6`, `TicketCloseController.php:6`, `TicketLifecycleController.php:6`, `TicketAssignmentController.php:6`.
- `app/Services/TicketWorkflowService.php:12`, `TicketStatusService.php:7`, `TicketSearchService.php:8`, `TechnicianAssignmentService.php:8`, `AIService.php:7`.
- `app/Observers/TicketObserver.php:7`, `app/Concerns/BroadcastsTicketStatus.php:5`.
- Actions: `CreateTicketAction.php:6`, `CreatePublicTicketAction.php:6`, `CreatePreventiveTicketAction.php:6`, `ScheduleTicketAction.php:6`, `ScheduleMaintenanceAction.php:8`, `SubmitBudgetAction.php:7`, `ApproveBudgetAction.php:7`.
- `app/Domain/Ticket/Actions/*` — `CancelTicketAction`, `CloseTicketAction`, `ReopenTicketAction`, `StartTicketAction`, `TicketStatusChecker`.
- `app/Notifications/TicketStatusChanged.php:7`, `app/Events/TicketStatusChanged.php:5`, `app/Events/TicketStatusUpdatedBroadcast.php:5`, `app/Events/TicketCreated.php:6`.

---

### `TicketWorkflowStatusEnum`

**File:** [`app/Enums/TicketWorkflowStatusEnum.php`](TicketWorkflowStatusEnum.php)

An **explicit workflow state machine** (English backing values) that complements the DB-facing `TicketStatusEnum`. Describes legal transitions between lifecycle states.

| Case | Value | label() |
|------|-------|---------|
| `Open` | `open` | Aberto |
| `InProgress` | `in_progress` | Em Curso |
| `WaitingBudget` | `waiting_budget` | Pendente de Orçamento |
| `Approved` | `approved` | Aprovado |
| `Rejected` | `rejected` | Recusado |
| `Closed` | `closed` | Fechado |
| `Cancelled` | `cancelled` | Cancelado |

**Helper methods:**
- `label()`, `color()`, `icon()` — display helpers.
- `isFinal(): bool` — true for Closed/Cancelled/Rejected.
- `isActive(): bool` — `! $this->isFinal()`.
- `allowedTransitions(): array<self>` — explicit transition graph:
  - `Open` → InProgress, WaitingBudget, Cancelled
  - `InProgress` → WaitingBudget, Approved, Closed, Cancelled
  - `WaitingBudget` → Approved, Rejected, Cancelled
  - `Approved` → InProgress, Closed, Cancelled
  - `Rejected` → WaitingBudget, Cancelled
  - `Closed`, `Cancelled` → (none — terminal)
- `canTransitionTo(self $target): bool` — via `allowedTransitions()`.
- `normalize(mixed): ?self` — exact value matching, lowercased/trimmed.

**Where it's used (real usages):**
- `app/Domain/Ticket/Services/TicketStatusChecker.php` — used within the ticket domain for transition checks (see `app/Domain/Ticket/`).

---

### `BudgetStatusEnum`

**File:** [`app/Enums/BudgetStatusEnum.php`](BudgetStatusEnum.php)

Represents the lifecycle state of a ticket's repair budget approval.

| Case | Value | label() |
|------|-------|---------|
| `Pending` | `pending` | Pendente |
| `Approved` | `approved` | Aprovado |
| `Rejected` | `rejected` | Rejeitado |

**Helper methods:**
- `values(): array` — raw values (usable directly in validation `in:` rules).
- `label()`, `color()`, `icon()` — display helpers.
- `isFinal(): bool` — true for Approved/Rejected.
- `canTransitionTo(self $target): bool` — `Pending` can move anywhere; Approved/Rejected are locked.
- `normalize(mixed): ?self` — exact value matching.

**Where it's used (real usages):**
- `app/Actions/ApproveBudgetAction.php:6`, `app/Actions/SubmitBudgetAction.php:6` — sets `pending`/`approved`/`rejected` on tickets.
- `app/Http/Controllers/AdminController.php:8` — reads `budget_status` after the action.
- `app/Http/Controllers/TicketBudgetController.php:7` — budget endpoints.
- `app/Services/AnalyticsDashboardService.php:11`, `app/Services/AnalyticsExportService.php:7` — budget KPIs in dashboards/reports.
- `app/Domain/Ticket/Queries/TicketKpiQuery.php:5` — KPI aggregation.

---

### `BudgetDecisionEnum`

**File:** [`app/Enums/BudgetDecisionEnum.php`](BudgetDecisionEnum.php)

The action taken by an admin on a pending budget request.

| Case | Value | label() |
|------|-------|---------|
| `Approve` | `approve` | Aprovar |
| `Reject` | `reject` | Rejeitar |

**Helper methods:**
- `label(): string` — localized.
- `isFinal(): bool` — always `true` (both outcomes are terminal for the decision).

**Where it's used (real usages):**
- `app/DTOs/BudgetDecisionData.php:5` — backed by this enum in the `BudgetDecisionData` DTO.

---

### `StockMovementTypeEnum`

**File:** [`app/Enums/StockMovementTypeEnum.php`](StockMovementTypeEnum.php)

Direction of a stock inventory transaction.

| Case | Value | label() |
|------|-------|---------|
| `In` | `in` | Entrada |
| `Out` | `out` | Saída |
| `Adjust` | `adjust` | Ajuste |
| `Return` | `return` | Devolução |

**Helper methods:** `values()`, `label()`, `color()`, `icon()`, `normalize()`.

**Where it's used (real usages):**
- `app/Models/StockMovement.php:7` — model integration.
- `app/Actions/CreatePartAction.php:8` — creates initial `in` movements.
- `app/Services/StockMovementService.php:7`, `app/Services/StockDashboardService.php:7` — movement processing and dashboard stats.
- `app/Http/Controllers/StockMovementController.php:7` — create/list endpoints.
- `app/Http/Requests/StoreStockMovementRequest.php:7` — validates `movement_type` against `values()`.
- `app/Http/Resources/StockMovementResource.php:7` — resolves labels/icons in JSON.
- `app/Services/AnalyticsDashboardService.php:13`.

---

### `PartUnitOfMeasureEnum`

**File:** [`app/Enums/PartUnitOfMeasureEnum.php`](PartUnitOfMeasureEnum.php)

Measurement units for spare parts stock quantities.

| Case | Value | label() |
|------|-------|---------|
| `Unit` | `unit` | Unidade |
| `Meter` | `meter` | Metro |
| `Liter` | `liter` | Litro |
| `Kg` | `kg` | Quilograma (kg) |
| `Pair` | `pair` | Par |
| `Set` | `set` | Kit / Conjunto |
| `Roll` | `roll` | Rolo |
| `Other` | `other` | Outro |

**Helper methods:** `values()`, `label()`, `normalize()`.

**Where it's used (real usages):**
- `app/Models/Part.php:7` — model.
- `app/Http/Requests/StorePartRequest.php:7` and `app/Http/Requests/UpdatePartRequest.php:7` — validates `unit_of_measure` against `values()`.

---

### `PublicTicketProblemTypeEnum`

**File:** [`app/Enums/PublicTicketProblemTypeEnum.php`](PublicTicketProblemTypeEnum.php)

Incident category selected on the **public QR-code ticket form** (used by non-authenticated reporters).

| Case | Value | label() |
|------|-------|---------|
| `Breakdown` | `avaria` | Avaria |
| `Preventive` | `manutencao_preventiva` | Manutenção Preventiva |
| `Consumables` | `falta_consumiveis` | Falta de Consumíveis |
| `Other` | `outro` | Outro |

**Helper methods:**
- `values(): array` — raw values.
- `label()`, `icon()` — display helpers.
- `priority(): TicketPriorityEnum` — maps problem type to a default ticket priority: Breakdown→High, Preventive→Low, Consumables→Medium, Other→Medium.
- `normalize(mixed): ?self` — accepts English/Portuguese aliases with and without accents (e.g., `'manutenção preventiva'`, `'falta de consumíveis'`).

**Where it's used (real usages):**
- `app/Http/Requests/PublicStoreTicketRequest.php:7` — validates `problem_type` against `values()`.
- `app/Http/Controllers/PublicTicketController.php:7` — handles the public ticket submission.
- `app/Actions/CreatePublicTicketAction.php:5` — maps problem type to priority via `->priority()`.

---

### `NotificationTypeEnum`

**File:** [`app/Enums/NotificationTypeEnum.php`](NotificationTypeEnum.php)

Channel event triggers that cause a user notification to be created.

| Case | Value | label() |
|------|-------|---------|
| `BudgetRequest` | `budget_request` | Pedido de Orçamento |
| `BudgetSubmitted` | `budget_submitted` | Orçamento Submetido |
| `BudgetApproved` | `budget_approved` | Orçamento Aprovado |
| `BudgetRejected` | `budget_rejected` | Orçamento Rejeitado |
| `BudgetAutoApproved` | `budget_auto_approved` | Orçamento Aprovado Automaticamente |
| `TicketClosed` | `ticket_closed` | Ticket Encerrado |
| `TicketCreated` | `ticket_created` | Novo Ticket Criado |
| `PriorityOverride` | `priority_override` | Alteração Manual de Prioridade |
| `LowStock` | `low_stock` | Stock Baixo |

**Helper methods:**
- `values(): array` — raw values.
- `label()`, `icon()`, `color()` — display helpers.
- `defaultPriority(): NotificationPriorityEnum` — maps each type to its default priority (e.g., PriorityOverride and BudgetRejected are Urgent).
- `isBudgetRelated(): bool` — true for the 5 budget types.
- `normalize(mixed): ?self` — exact match.

**Where it's used (real usages):**
- `app/Services/TicketNotificationService.php:7` — creates ticket-status notifications.
- `app/Services/BudgetNotificationService.php:7` — creates budget-workflow notifications.
- `app/Services/LowStockAlertService.php:7` — creates `low_stock` alerts.
- `app/Services/AnalyticsDashboardService.php:12` — notification stats.
- `app/Http/Controllers/PublicTicketController.php:6`.

---

### `NotificationPriorityEnum`

**File:** [`app/Enums/NotificationPriorityEnum.php`](NotificationPriorityEnum.php)

Urgency ranking for system and email notifications.

| Case | Value | label() |
|------|-------|---------|
| `Low` | `low` | Baixa |
| `Normal` | `normal` | Normal |
| `High` | `high` | Alta |
| `Urgent` | `urgent` | Urgente |

**Helper methods:**
- `values()`, `label()`, `color()`, `icon()` — display helpers.
- `weight(): int` — Low=1 … Urgent=4 (sorting).
- `isHighPriority(): bool` — true for High/Urgent.
- `normalize(mixed): ?self` — exact match.

**Where it's used (real usages):**
- `app/Enums/NotificationTypeEnum.php` — `defaultPriority()` returns values of this enum.

---

### `MaintenancePlanIntervalTypeEnum`

**File:** [`app/Enums/MaintenancePlanIntervalTypeEnum.php`](MaintenancePlanIntervalTypeEnum.php)

Recurrence interval unit for a preventive maintenance plan.

| Case | Value | label() |
|------|-------|---------|
| `Days` | `days` | Dias |
| `UsageHours` | `usage_hours` | Horas de uso |
| `Cycles` | `cycles` | Ciclos |

**Helper methods:** `values()`, `label()`, `normalize()`.

**Where it's used (real usages):**
- `app/Http/Requests/StoreMaintenancePlanRequest.php:7` and `app/Http/Requests/UpdateMaintenancePlanRequest.php:7` — validate `interval_type`.
- `app/Http/Controllers/MaintenancePlanController.php:8` — plan endpoints.
- `app/Actions/MaintenancePlanActions.php:7` — plan creation/update logic.
- `app/Http/Resources/MaintenancePlanResource.php:7` — resolves `interval_type_label`.

---

### `FileTypeEnum`

**File:** [`app/Enums/FileTypeEnum.php`](FileTypeEnum.php)

MIME/media classification for ticket attachments (photos, documents, etc.).

| Case | Value | label() |
|------|-------|---------|
| `Image` | `image` | Imagem |
| `Document` | `document` | Documento |
| `Video` | `video` | Vídeo |
| `Audio` | `audio` | Áudio |
| `Other` | `other` | Outro |

**Helper methods:**
- `values(): array` — raw values.
- `label()`, `icon()` — display helpers.
- `fromMimeType(string $mimeType): self` — classifies a MIME string: `image/*`→Image, `video/*`→Video, `audio/*`→Audio, known document MIMEs (PDF, DOCX, XLSX, PPTX, ODF, RTF, CSV, JSON, XML, text/*)→Document, else→Other.
- `normalize(mixed): ?self` — exact match.

**Where it's used (real usages):**
- Used internally by `TicketAttachmentController` / attachment upload logic to classify and store uploaded files.

---

### `AuditEventEnum`

**File:** [`app/Enums/AuditEventEnum.php`](AuditEventEnum.php)

Event actions recorded in the audit trail.

| Case | Value | label() |
|------|-------|---------|
| `Created` | `created` | Registo Criado |
| `Updated` | `updated` | Registo Atualizado |
| `Deleted` | `deleted` | Registo Eliminado |
| `Login` | `login` | Início de Sessão |
| `Logout` | `logout` | Fim de Sessão |
| `PasswordChanged` | `password_changed` | Palavra-passe Alterada |

**Helper methods:**
- `values(): array` — raw values.
- `label()`, `color()` — display helpers.
- `isAuthEvent(): bool` — true for Login/Logout/PasswordChanged (security-related).
- `normalize(mixed): ?self` — exact match.

**Where it's used (real usages):**
- Referenced conceptually by the audit system; used with the `Audit` model and `AuditResource`.

---

## Notes for Developers & AI

- **Backed Types:** All enums are backed by `string`. Use `->value` when storing in database tables or passing to external API contracts.
- **Strict Normalization:** Most enums provide a `normalize(mixed $value): ?self` static method designed to safely parse inputs regardless of case, accentuation, or whether the input is already an enum instance.
- **Labels vs Database Values:** `label()` methods leverage Laravel's translation helper `__('...')` to return localized human-readable labels according to the active application locale, keeping PHP identifiers completely in English.
- **Database Alignment:** `TicketPriorityEnum` and `TicketStatusEnum` use backing string values matching the legacy lookup schema in database tables (`tickets`, `ticket_statuses`). Do not alter their string values without a planned database schema and data migration.
- **Workflow vs Status:** `TicketWorkflowStatusEnum` (English values) is the in-application state machine used to enforce legal transitions; `TicketStatusEnum` (Portuguese values) mirrors the persisted DB lookup rows. They serve different layers.
