# Refactor Glossary: Canonical PT → EN Mapping

This glossary serves as the single canonical source of truth for all Portuguese → English translations across the codebase refactoring effort. It has been verified against `lang/en-US/` and existing domain services to ensure 100% naming consistency.

> **Rule:** This table is append-only. Never edit an existing row without an explicit logged reason in `action-log.md`.

## Canonical Domain Glossary

| Portuguese | English | Scope | Notes |
|---|---|---|---|
| Avaria | Fault / Incident | domain | Core entity; represented in code as `Ticket` with types/categories. User-facing domain term is Fault/Incident. |
| Manutenção | Maintenance | domain | Covers preventive and corrective maintenance workflows (`MaintenancePlan`, `ScheduleMaintenanceAction`). |
| Ordem de Trabalho | Work Order | domain | Corresponds to actionable tickets/tasks in maintenance domain. |
| Cliente | Customer | domain | Matches `lang/en-US/` conventions. |
| Fornecedor / Fornecedores | Supplier / Suppliers | domain | Matches `Supplier` model, `SupplierController`, `lang/en-US/stock.php`. |
| Utilizador / Utilizadores | User / Users | domain | Standard entity across Laravel auth, models, and UI. |
| Perfil / Perfis | Profile / Role | domain | `UserProfile` model for permissions; `UserRoleEnum` (`user`, `technician`, `admin`). |
| Equipamento / Equipamentos | Equipment | domain | `Equipment` model, `EquipmentCategory` model. Note: "Equipment" is uncountable in English. |
| Peça / Peças | Part / Parts | domain | Matches `Part`, `PartCategory`, `StockMovementService`, and `lang/en-US/stock.php`. |
| Fatura / Faturas | Invoice / Invoices | domain | Financial and purchase documentation. |
| Orçamento | Budget | domain | Disambiguation: Codebase uses `budget` consistently (`approveBudget`, `budget_limit`, `waiting_budget`, `PENDENTE_ORCAMENTO`). |
| Sala / Salas | Room / Rooms | domain | `Room` model, physical location for equipment and tickets. |
| Movimento de Stock | Stock Movement | domain | `StockMovement` model, `StockMovementService`. |
| Taxa / IVA | Tax Rate / VAT | domain | `TaxRate` model for financial calculations. |
| Categoria de Peça | Part Category | domain | `PartCategory` model. |
| Categoria de Equipamento | Equipment Category | domain | `EquipmentCategory` model. |
| Plano de Manutenção | Maintenance Plan | domain | `MaintenancePlan` model and service. |
| Auditoria / Auditorias | Audit / Audits | domain | `Audit` model, `AuditController`. |
| Notificação / Notificações | Notification / Notifications | domain | `Notification` model, database notifications, mail notifications. |
| Preferências | Preferences | domain / UI | Corresponds to `UserPreference` model, `PreferencesController`, `PreferencesService`. (Renaming from `PreferenciasController`/`PreferenciasService`). |
| Definições | Settings | domain / UI | Corresponds to system and application settings (`SystemSetting`, `ThemeSetting`). Views in `resources/views/ui/definicoes/` -> `resources/views/ui/settings/`. |
| Aparência | Appearance | UI | Visual themes and dark/light customization (`appearance.blade.php`, `appearance-settings.js`). |
| Sistema | System | domain / UI | System configuration (`SystemSettingsController`, `system.blade.php`). |
| Atribuir / Atribuição | Assign / Assignment | domain | `TicketAssignmentController`, `AssignTechnicianAction`. Route `/atribuir` -> `/assign`. |
| Iniciar | Start | domain | `TicketStartController`. |
| Fechar / Encerramento | Close / Closure | domain | `TicketCloseController`. |
| Agendar / Agendamento | Schedule / Scheduling | domain | `TicketScheduleController`, `ScheduleMaintenanceAction`. |
| Relatório / Relatórios | Report / Reports | domain | CSV/PDF exports (`StockReportController`, `AnalyticsController`). |
| Anexo / Anexos | Attachment / Attachments | domain | `TicketAttachment` model, file uploads. |
| Comentário / Comentários | Comment / Comments | domain | `TicketComment` model. |
| Histórico | History | domain | `TicketWorkflowHistory` model. |
| Estado / Estados | Status / Statuses | domain | `TicketStatus` (DB lookup rows), `TicketStatusEnum` (Portuguese backing values). |
| Prioridade | Priority | domain | `TicketPriorityEnum` (`baixa`, `média`, `alta`, `crítica`). |
| Causa | Cause | domain | Incident cause analysis. |
| Solução | Solution | domain | Resolution notes on ticket closing. |
| Custo Real | Actual Cost | domain | Final cost of repair. |
| Custo Estimado | Estimated Cost | domain | Initial estimated cost. |
| Tempo Despendido | Time Spent | domain | Duration in minutes spent by technician. |
| Telemetria | Telemetry | domain | Background simulation and IoT telemetry (`TelemetryCommand`). |
| Ativo / Inativo | Active / Inactive | domain | Boolean status flags across models (`active`). |
| Técnico | Technician | domain | User with technician privileges (`UserRoleEnum::Technician`). |
| Administrador | Administrator / Admin | domain | User with admin privileges (`UserRoleEnum::Admin`). |

## NEEDS REVIEW (Pending Human Clarification)

*(No terms currently ambiguous. If any unmapped Portuguese terms arise during folder-by-folder processing, they will be logged here before execution.)*
