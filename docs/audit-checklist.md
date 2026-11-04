# Checklist de Auditoria SGM

## Fase 1 — Ambiente
- [x] PHP 8.5 instalado
- [x] Composer instalado
- [x] Dependências instaladas (vendor/)
- [x] .env criado e APP_KEY gerada
- [x] DB real (SQLite) criada e migrada
- [x] phpstan level max → 0 erros
- [x] pint → passed
- [x] phpunit suites verdes

## Fase 2 — Ficheiro a ficheiro (app/)
- [ ] Models (24) — fillable, casts, relations, scopes
- [ ] Services (33) — lógica, N+1, validação
- [ ] Controllers (36) — autorização, validação, mass assignment
- [ ] Repositories (10) — queries, índices
- [ ] Requests (39) — regras de validação
- [ ] Resources (16) — exposição de dados
- [ ] Policies (13) — autorização
- [ ] Middleware (10) — segurança, rate limit
- [ ] Jobs (9) — filas, retry
- [ ] Observers (4) — side effects

## Fase 3 — Performance
- [ ] Identificar N+1 (Debugbar/telescope)
- [ ] Índices DB (EXPLAIN)
- [ ] Cache de queries pesadas
- [ ] Lazy loading de relações

## Fase 4 — Segurança
- [ ] CSRF em todos os forms
- [ ] XSS: Blade {!! !!} auditados
- [ ] SQL injection: whereRaw auditado
- [ ] Uploads: validação de MIME/tamanho
- [ ] Rate limiting em auth

## Fase 5 — Acessibilidade
- [ ] axe-core em rotas principais
- [ ] Contraste de tokens
- [ ] Foco visível
- [ ] Navegação por teclado

## Fase 6 — Correções
- [ ] Commit por correção
- [x] Push para branch
- [ ] PR para main

## Notas da auditoria de novembro 2026

Verificado com evidência nesta ronda (gate = `vendor/bin/pint --test`,
`vendor/bin/phpstan analyse`, `npm run build`, `php artisan test`):

- **Fase 2**: as categorias revistadas resultaram em correções concretas — autorização
  em `Controllers`/`Policies` (detalhe de ticket, `submitBudget`/`start`/`close` vs
  `assigned_to`), exposição de dados em `Resources`/rotas `/api/*`, listeners duplicados
  em `Observers`, locale por requisitante nos `Jobs` de exportação. As restantes
  sub-alíneas (por exemplo "mass assignment" em todos os 39 Requests) não foram
  cobertas ficheiro a ficheiro e continuam por marcar.
- **Fase 3**: índices `tickets_deleted_at_opened_idx` e
  `tickets_deleted_at_resolved_idx` (migração `2026_11_04_000001`) para filtros de
  intervalo de datas sem predicado de estado. Não foi corrido `EXPLAIN` nem
  Debugbar/Telescope, por isso os restantes itens da fase continuam em aberto.
- **Fase 4**: `whereRaw` auditado (as expressões das migrações/triggers passam por
  nowdoc com aspas simples por driver, compatível com `sql_mode=ANSI_QUOTES`);
  `innerHTML` dos renderizadores JS escapado via `utils/escape.js`; token de API
  reemitido na mudança de password. Contraste/foco axe-core (Fase 5) não corrido;
  apenas foi adicionado `role="dialog"`/`aria-modal`/`aria-labelledby` ao modal de
  prioridade.
- **Conhecido e deliberadamente não corrigido**: `LocaleService::userDateFormat()` e
  `LocalizationService::preferences()` caem em valores hardcoded quando não existe
  `request` bound (contexto de worker). Corrigi-lo exigiria estado estático de
  override que fugiria entre jobs; o baseline de locale é reposto por job em
  `AppServiceProvider::resetLocaleForQueuedJobs()`.
- **Reportado sem alteração de código**: `recommended_technician_id` sem FK verificada.
