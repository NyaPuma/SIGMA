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
- **Corrigido nesta ronda**: `LocaleService::userDateFormat()` e
  `LocalizationService::preferences()` já não caem em valores hardcoded quando não
  existe `request` bound. `App\Jobs\Concerns\SetsRequesterContext` aplica o idioma e as
  preferências de apresentação do requisitante através do singleton `PreferenceContext`,
  e `AppServiceProvider::resetRequesterContextForQueuedJobs()` repõe a base de locale e
  limpa o contexto antes de cada job (o `Queue::before` dispara também no driver `sync`,
  por isso o teste de fuga cobre o ambiente de desenvolvimento).
- **Corrigido nesta ronda**: `tickets.recommended_technician_id` passa a referenciar
  `users.id` (migração `2026_11_05_000001_constrain_recommended_technician_to_users.php`,
  que primeiro limpa os ids pendentes). SQLite continua a ignorar a foreign key
  adicionada a uma tabela existente, por isso o valor é também validado em
  `AIService::recommendTechnician()` contra os ids efetivamente oferecidos ao modelo.
- **Corrigido nesta ronda**: `lang/en-GB` e `lang/en-US` ficaram completos e
  `tests/Unit/Support/TranslationKeyParityTest.php` falha se uma chave portuguesa ficar
  sem tradução ou se existir um ficheiro de locale sem original em `pt-PT`.
- **Corrigido nesta ronda (lógica de negócio, cinco defeitos provados com teste)**:
  1. `App\Support\MoneyInput::parse()` deixava a 0 valores com separador de milhares
     repetido (`1.500.000`, `12,345,678`); o contrato ambíguo de `'1.500'` está agora
     documentado em vez de implícito (`tests/Unit/Support/MoneyInputTest.php`).
  2. `SimulateTelemetry` só procurava tickets em estado *Open*, por isso duplicava
     alertas de equipamentos já em intervenção ou à espera de orçamento; passa a
     considerar todos os estados não finais (`TicketStatusEnum::isActive()`).
  3. `CurrencyRateService::storedRate()` não guardava o negativo: um par inexistente
     volta à base de dados em cada conversão. Agora é cacheado um valor-sentinela
     (`NO_RATE_CACHE_VALUE`) e `updateRates()` limpa as duas direções do par.
  4. O formulário público de avaria aceitava fotos até 4096 KB (o limite configurado é
     2048) e o controlador descartava silenciosamente o ficheiro quando o MIME não
     estava na sua tabela privada — o ticket ficava criado sem foto e sem aviso. As
     regras passam para `App\Support\PhotoUploadPolicy`, usadas por ambos os caminhos
     de upload e pelos dois controladores.
  5. `AnalyticsDashboardService` devolvia `sla_success = 100` quando nenhum ticket
     estava fechado; passa a devolver `null` e o painel mostra «—».
- **Removido nesta ronda**: `docker-compose.yml` (ficava oculto pelo `compose.yaml`,
  expunha a porta errada e não era usado por nenhum job de CI) e as variáveis
  `MYSQL_*` do `.env.example` que só existiam para esse ficheiro.
- **Corrigido nesta ronda (bloqueio de login)**: o contador de tentativas falhadas
  passou a ser chaveado por `IP + email` (`AuthController::login`, chave
  `login_attempts:<sha1(ip|email)>`) em vez de apenas pelo email. Com a chave antiga,
  qualquer pessoa sem credenciais podia bloquear a conta de um colega ao errar a
  password cinco vezes (DoS de lockout). As colunas `users.lockout_until` e
  `users.failed_attempts` não eram lidas por nada e foram removidas
  (`2026_11_05_000002_drop_lockout_columns_from_users_table.php`); o estado vive
  apenas na cache, com `Retry-After` na resposta 429.
- **Corrigido nesta ronda (tempo real inerte)**: os eventos de ticket deixaram de
  fingir que existia uma camada WebSocket. `TicketStatusUpdatedBroadcast` passa a
  `App\Events\TicketStatusUpdated`, `BroadcastsTicketStatus::broadcastStatusChange()`
  passa a `NotifiesTicketStatusChange::notifyStatusChange()`, e `TicketCreated`/
  `TicketStatusChanged` deixaram de implementar `ShouldBroadcast*` — não há
  `routes/channels.php` nem cliente Echo, por isso o `broadcastAs()`/`broadcastWith()`
  nunca entregava nada. Removidos `config/broadcasting.php`, `BROADCAST_CONNECTION`,
  `pusher/pusher-php-server`, `laravel-echo` e `pusher-js`. Um único evento passa a
  originar uma única notificação ao requisitante (o `TicketStatusChanged` enviado em
  paralelo pelo trait duplicava o email do listener `SendTicketStatusNotification`).
- **Removido nesta ronda (código morto)**: métodos públicos sem qualquer chamada
  verificada — `LocaleService::codes()`, `::resolveDefaultLocale()`, `::formatMoney()`,
  `::formatUserDate()`, `ThemePresetService::findByValues()`, `::apply()`, `::colorKeys()`
  e o constante `COLOR_KEYS`, `QrCodeService::svg()`, `Part::validateUnitOfMeasure()` e
  `TicketRepository::findWithRelations()` (também saído do interface). Dez parciais
  Blade órfãs em `resources/views/ui/partials/` (`currency-dropdown`, `currency-modal`,
  `date-format-dropdown`, `date-format-modal`, `item-selector-modal`,
  `language-dropdown`, `language-modal`, `locale-modal`, `number-format-dropdown`,
  `preferences-dropdowns-js`) desapareceram porque o `localization-modal.blade.php`
  unificado as substituiu; as linhas de referência dos `README.md` afetados foram
  recalculadas a partir do código real.
