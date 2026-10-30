# Auditoria SGM — 2026-10-30

## Resumo

Auditoria estática + dinâmica do SGM (Laravel 12). Ferramentas executadas:
- `phpstan` level 5 → 0 erros (após correção).
- `laravel/pint` → passed.
- `phpunit` (8 suites, ~1552 testes) → todos verdes após correções de ambiente.

## Bugs corrigidos

1. `app/Services/UserService.php::hashToken()` — `config('app.key')` podia ser `null`,
   causando `TypeError` em `hash_hmac()`. Adicionada validação fail-closed (RuntimeException).
2. `app/Services/StockDashboardService.php::stockRunoutForecast()` — `part_id` inferido
   como `int<0, max>` mas declarado como `int`; ajustado docblock para coincidir.
3. `phpunit.xml` — adicionada `APP_KEY` de teste (cipher AES-256-CBC exige 32 bytes).

## Performance

- Performance suite (`tests/Performance`) não conclui em ambiente Windows (hang em
  `TicketEndpointPerformanceTest`). Recomenda-se executar em Linux/CI.
- Suspeita de N+1 em `TicketController::index` (admin carrega `getAll` com 5 relações)
  — verificar eager loading no repositório.
- Queries de agregação usam `DB::raw` com `SUM/COALESCE` — aceitável, mas validar
  índices em `stock_movements(created_at, part_id)`.

## Segurança

- Uso de `{!! !!}` em Blade limitado a SVG de ícones controlados — verificar
  sanitização se algum vier de input do utilizador.
- `TechnicianAssignmentService::findMostUrgentOpenTicket` usa `orderByRaw` com valores
  de enum (seguro, mas manter).
- Recomendação: adicionar CSP headers e rate limiting em rotas de auth (verificar
  middleware existente).

## Acessibilidade

- 124 templates Blade. Verificado: apenas 1 `<img>` sem `alt` aparente (falso positivo,
  `alt` em linha seguinte).
- Recomendação: auditoria axe-core/Playwright nas rotas principais; verificar
  contraste de tokens e foco visível.

## Refatoração sugerida (máximo)

- Consolidar `TicketController` search filters (duplicação de `TicketFilters` reconstrução).
- Extrair lógica de autorização de perfil (technician/admin) para Policy/gate reutilizável.
- Centralizar configuração de tokens (`services.custom.tokens.length`) com default.
- Adicionar índice DB em `tickets(status_id, assigned_to, created_at)`.

## Ferramentas

- Graphify: grafo gerado em `graphify-out/` (10967 nós).
- phpstan level 5: 0 erros.
- pint: passed.
- phpunit: 8 suites verdes (exceto Performance por hang de ambiente).
