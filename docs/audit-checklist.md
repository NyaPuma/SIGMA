# Checklist de Auditoria SGM

## Fase 1 — Ambiente
- [x] PHP 8.3 instalado
- [x] Composer instalado
- [x] Dependências instaladas (vendor/)
- [x] .env criado e APP_KEY gerada
- [x] DB real (SQLite) criada e migrada
- [x] phpstan level 5 → 0 erros
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
- [ ] Push para branch
- [ ] PR para main
