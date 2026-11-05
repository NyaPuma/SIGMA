# Memória Local do Projeto SGM

## Visão geral
SGM = Integrated Maintenance Management System. Laravel 13, PHP 8.5, para digitalizar o ciclo completo de manutenção/avarias. Três papéis: Operador, Técnico, Administrador.

## Stack
- PHP 8.5+, Laravel 13
- BD: SQLite (dev/testes), PostgreSQL/MySQL (prod)
- Frontend: Blade + Tailwind + Alpine.js + Vite
- Auth: sessão + API token (Sanctum-like), RBAC via UserProfile
- Testes: PHPUnit 12, 8 suites (≈1774 testes em 255 ficheiros), SQLite in-memory
- Estático: PHPStan level max (Larastan), Pint

## Padrões
- Actions readonly (single-purpose), Form Requests + DTOs, Eloquent com fillable/casts/scopes, Policies, middleware role:admin.
- i18n: `__('messages....')`, `__('common....')`, `__('auth....')` (pt-PT). Testes podem forçar locale en-GB.
- Auth token: `User::hashToken` = HMAC SHA256 com APP_KEY. Em testing, tokens raw também são aceites (fallback).
- Testes armazenam `api_token` raw na BD (conveniência de teste) — funciona pelo fallback de testing.
- SQL dinâmico (expressões por driver) usa `App\ValueObjects\SqlFragment`: no Laravel 13 `selectRaw`/`groupByRaw`/`whereRaw` e `DB::raw` exigem `literal-string`, e PHPStan nível max recusa strings interpoladas.
- `withoutMiddleware()` referencia `PreventRequestForgery::class`, não `ValidateCsrfToken::class`: no Laravel 13 o primeiro é o middleware real e excluir o filho depreciado não remove nada (invisível em testes, porque a validação anti-falsificação é ignorada em `runningUnitTests()`).

## Bugs conhecidos / corrigidos
- `hashToken` fail-closed se APP_KEY ausente.
- `StockDashboardService::stockRunoutForecast` docblock alinhado.
- Performance suite bloqueia em Windows (TicketEndpointPerformanceTest).
- APP_KEY deve ter 32 bytes (AES-256-CBC).

## Convenções de teste
- `RefreshDatabase` + seed lookup data.
- Usuários criados com `api_token` raw (não hashed) em testes.
- Middleware de auth aceita token raw em env testing.

## Ficheiros-chave
- `app/Models/User.php` — hashToken, scopes, relations.
- `app/Http/Controllers/ProfileController.php` — changePassword.
- `app/Http/Requests/ChangePasswordRequest.php` — regras de força.
- `app/Services/UserService.php` — createToken, hashToken.
- `app/Services/StockDashboardService.php` — queries de stock.
- `app/Services/TechnicianAssignmentService.php` — orderByRaw com enum (seguro).
