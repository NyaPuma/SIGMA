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
- [ ] Models (22) — fillable, casts, relations, scopes
- [ ] Services (33) — lógica, N+1, validação
- [ ] Controllers (41) — autorização, validação, mass assignment
- [ ] Repositories (4 + contratos em `Contracts/`) — queries, índices
- [ ] Requests (38) — regras de validação
- [ ] Resources (15) — exposição de dados
- [ ] Policies (12) — autorização
- [ ] Middleware (8, em `app/Http/Middleware`) — segurança, rate limit
- [ ] Jobs (8 + `Concerns/SetsRequesterContext`) — filas, retry
- [ ] Observers (3) — side effects

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
  password cinco vezes (DoS de lockout). As colunas `users.login_attempts` e
  `users.locked_until` não eram lidas por nada e foram removidas
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

## Notas da 3.ª ronda (novembro 2026) — deriva entre schema, código e documentação

O critério foi sempre o mesmo: o que o ficheiro *diz* contra o que o código *faz*.
Quatro destes defeitos passaram despercebidos ao suite porque o SQLite aceita coisas
que MySQL/PostgreSQL rejeitam; cada correção tem teste ou prova de execução.

- **CSV de stock com nome de coluna em vez do valor** (`StockReportController`): os
  `select()` dos relatórios de stock baixo e de inventário pediam `category_id`, coluna
  que não existe — o nome real é `part_category_id`. Em SQLite um identificador
  entre aspas duplas inexistente vira *literal de texto*, por isso o CSV exportava a
  string `category_id` em todas as linhas e os testes ficavam verdes; em MySQL/PostgreSQL
  seria um 500. Corrigido nas duas queries e apanhado por
  `StockReportDownloadFeatureTest::test_low_stock_csv_writes_the_category_name_not_the_column_label`.
- **Notificações nunca chegavam ao painel** (`NewTicketNotification`,
  `TicketStatusChanged`): as duas declaravam o canal `database`, mas o `DatabaseChannel`
  do framework escreve um `id` UUID e não preenche `user_id`/`title`/`message`, que nesta
  tabela são `bigint` auto-incremento e NOT NULL — o `INSERT` falhava sempre (2 datatype
  mismatches) e a exceção era engolida pelo `try` do chamador. Passaram a `['mail']`, que
  era o único canal que efetivamente entregava; o painel continua a ser escrito por
  `NotificationCreatorService`. O `toArray()` morto (nunca lido, porque o canal nunca
  correu) foi removido. `TicketNotificationChannelTest` prova que notificar deixa de
  escrever na tabela.
- **Restauros invisíveis na auditoria** (`Auditable`): o trait gravava `created`,
  `updated` e `deleted`, mas a enumeração `audits.event` aceita quatro valores e o
  `restored` nunca era registado — um soft delete desfeito não aparecia no histórico.
  Adicionado o oitavo evento + `AuditableTraitTest::it_creates_audit_log_on_ticket_restore`.
- **Fechar um ticket apagava o trabalho registado** (`TicketCloseController::simpleClose`):
  `float()` e `integer()` devolvem `0` quando a chave não vem no pedido, por isso um
  fecho sem campo de custo zerava o custo e os minutos que o técnico já tinha gravado.
  Agora ausência de chave = não tocar na coluna (igual ao relatório), e
  `minutes_spent` passou a ser validado em `CloseTicketRequest`. Teste de preservação
  em `TicketWorkflowFeatureTest`.
- **Página pública do QR expunha inventário**: `machine_id` é sequencial, e o ecrã
  anónimo de avaria mostrava `asset_tag`, marca/modelo e sala — percorrer 1..N dava um
  censo interno. A view ficou com o nome do equipamento e a categoria (o suficiente para
  o reportante se reconhecer) e o controlador só faz eager load de `category`.
- **Endpoint documentado e inexistente**: a especificação OpenAPI descrevia
  `GET /api/admin/maintenance-plans/{plan}` sem haver rota; a rota passou a existir
  (`routes/api.php:186`) em vez de se apagar a documentação.
- **Referências de linhas nos docs**: o `app/Http/Controllers/README.md` apontava para
  ~160 linhas de `routes/web.php`/`routes/api.php` que tinham derivado com as mudanças
  de rotas desta auditoria. Um verificador que casa método + URI re-derivou todos os
  números; as duas linhas de prosa que o corretor automático danificou foram recompostas
  à mão contra o grep das rotas reais.
- **8 foreign keys sem índice** (`part_supplier.supplier_id`,
  `maintenance_plan_part.part_id`, `parts.tax_rate_id`, `ticket_attachments.user_id`,
  `ticket_comments.user_id`, `tickets.resolved_by`/`closed_by`/`budget_approved_by`):
  migração `2026_11_05_000003_index_remaining_foreign_keys.php`. Em PostgreSQL a
  ausência de índice obriga a varrer a tabela filha em cada delete do pai. Verificado
  com `PRAGMA foreign_key_list` + `PRAGMA index_list` antes (8 em falta) e depois (0), e
  o ciclo `up`/`down`/`up` correu em base de dados provisória.
- **Rollback que não desfaz o que fez**:
  `2026_08_07_000001_add_reporter_fields_to_tickets_table.php` tornava `user_id`
  anulável no `up()` e o `down()` limitava-se a apagar colunas — depois de um rollback
  o schema ficava diferente do que as migrations anteriores descrevem. O `down()`
  restaura o `NOT NULL` e **aborta** se existir algum ticket sem dono (seria o caso dos
  tickets anónimos do QR: reconstruir a tabela descartá-los-ia). Prova: com uma linha
  sem `user_id` levanta `RuntimeException` e a coluna fica como estava; sem linhas sem
  dono, `PRAGMA table_info` volta a mostrar `notnull=1` e o `ON DELETE CASCADE`
  permanece intacto.
- **Modelo morto + tabela órfã**: `App\Models\ThemeSetting` não era usado por ninguém
  (nem `use`, nem `ThemeSetting::`, nem testes) e o tema real vive em `users.theme` com
  presets em `ThemePresetService`. O modelo foi eliminado e as descrições que
  afirmavam o contrário corrigidas: `app/Models/README.md` (secção inteira que dizia que
  `ThemePresetService` fazia `updateOrCreate` nesta tabela), o docblock de
  `ThemePresetService`, `database/migrations/README.md` e `resources/docs/design-notes.md`
  (que dizia que `switchTheme` gravava cores + `theme_name`; grava apenas o id do preset,
  e `theme_name` é uma chave de `localStorage` do cliente). **A tabela `theme_settings`
  ficou órfã e não foi eliminada**: `DROP TABLE` é destrutivo e não se faz por
  iniciativa de uma auditoria — é decisão explícita de quem opera o projeto.
- **Documentação que media errado**: os counts da Fase 2 desta checklist (24→22 models,
  10→4 repositórios, 39→38 requests, 16→15 resources, 13→12 policies, 10→8 middleware,
  9→8 jobs, 4→3 observers), os nomes das colunas de lockout removidas
  (`login_attempts`/`locked_until`, não `lockout_until`/`failed_attempts`), e a nota de
  `database/migrations/README.md` que garantia que as migrações de dados eram reversíveis
  (o `down()` de `populate_user_preferences` é vazio de propósito) e que todos os
  comentários das migrações estavam em inglês (os banners das tabelas originais
  `0001_01_01_*` continuam em português).
- **Falta de `use` numa migração**: `2026_08_12_000002_populate_user_preferences.php`
  usava `DB::table(...)` sem importar o facade — funcionava apenas pelo alias global
  registrado pelo bootstrap, e partia numa consola sem ele.
- **Etiquetas de interface fora do i18n**: `resources/js/api-client.js`,
  `resources/js/auth/login.js` e `resources/js/components/item-selector-modal.js`
  mostravam texto fixo em inglês ("Session expired", "Authenticating...", "Hide"/"Show",
  "No material in stock.") em páginas que já servem `window.SGM_UI_I18N` /
  `window.SGM_AUTH_I18N`. Passaram todas a `window.SGM_*_I18N?.chave || 'fallback'`, a
  ponte `locale-config.blade.php` leva as chaves novas e os locales `pt-PT`/`en-GB`/`en-US`
  têm-nas (15 em `ui.php`, 6 em `auth.php`; os títulos de formulário reutilizam chaves que
  já existiam em `common`/`equipment`/`maintenance_plan`) — o `TranslationKeyParityTest`
  garante a paridade. O texto do spinner e os estados do seletor de itens vão por
  `escapeHtml()` antes de `innerHTML`.
  Na mesma varredura apareceram mais oito etiquetas presas em módulos de página: os títulos
  de formulário de `pages/stock/{categories,plans,tax-rates}.js` ('New category', 'Edit
  maintenance plan', …), 'A registar movimento...' em `pages/stock/movements.js`,
  'Saved'/'Unsaved changes' e 'Active'/'Inactive' em `pages/definicoes-sistema.js`, e duas
  mensagens de validação de palavra-passe em `pages/profile.js` — todas bridged.
- **JS a sobrescrever uma etiqueta traduzida**: `pages/ticket-create/autocomplete.js`
  punha `placeholder = 'Click to select equipment...'` por cima do placeholder traduzido
  de `ui/ticket-create.blade.php`, em português fixo. Passou a ser a view a dono do texto
  (`equipment.Clique para selecionar equipamento...`, a chave que as páginas de stock já
  usam) e o módulo só define o comportamento read-only/`cursor`. Em falta ficou
  `#equipmentSuggestions` nessa view: markup do autocomplete antigo que já ninguém
  preenche (o `equipmentSuggestions` vivo é o de `calendar.blade.php`), removido.
- **Botão que não fazia nada** (`ui/stock/plans.blade.php` + `pages/stock/plans.js`): a
  view dava `id="plAddPartBtn"` ao botão "Adicionar peça" e o JS ligava o `click` a
  `getElementById('plAddPart')` — o `?.` silenciava o desencontro e o botão nunca abria
  linha de peça. A view passou para `id="plAddPart"`, o nome que o código já usava; nenhum
  teste depende do outro. Prova no browser com a sessão de `admin@example.com`: um clique
  fez `#plPartsContainer [data-part-row]` passar de 4 para 5 linhas, e o clique em
  `[data-plan-edit]` dentro de `#plansTableBody` deixou o título do formulário em
  'Editar plano de manutenção' com `data-plan-form-mode=edit`. No mesmo bloco estava
  `#plSelectedParts` — markup que nenhum JS lê nem escreve (as linhas vivem em
  `#plPartsContainer`), removido.
- **Bloco morto com texto inglês** (`pages/calendar.js`): `init()` atribuía
  `'Event Details'` a `#modalTitle` quando o elemento estivesse vazio — mas
  `calendar.blade.php:139` renderiza sempre `__('common.Detalhes do Evento')`, por isso a
  condição nunca era verdadeira e essa string nunca chegava a ser vista nem traduzida.
  Removido; a linha 18 do mesmo módulo continua a escrever o título dinâmico do evento.
- **Emails e atributos fora do locale**: os `<title>` de `emails/passwordReset.blade.php`
  e `emails/ticketCreated.blade.php` e o `<html lang="pt">` de ambos ficaram presos ao
  português do template; passam agora por `app()->getLocale()` e por chaves
  existentes (`notifications.mail_password_reset_subject`, `common.Notificação de
  Incidente`). O `alt` da fotografia em `ui/users-edit.blade.php` estava em português
  literal; foi para `ticket_media.Fotografia do Utilizador`.
- **Documentação que descrevia strings que já não existem**: `docs/refactor/session-notes`
  e `docs/refactor/progress.md` listam `core/auth.js`, `core/theme.js` e
  `components/input/password-strength.js` como módulos com utilizador-facing em português
  hardcoded. Verificado ficheiro a ficheiro: os três já não têm nenhuma etiqueta — só
  chaves de `localStorage`, slugs de papel e classes CSS (`weak`/`bg-danger`). O que
  efectivamente precisava de ponte estava noutros sítios e foi corrigido acima.

Ainda em aberto (registado, não corrigido nesta ronda):

- **Fase 3**: os índices novos foram adicionados por inspeção das queries, não por
  `EXPLAIN`/Debugbar/Telescope; nenhum `EXPLAIN` foi corrido em base real.
- **Cobertura de locales**: ~50 locales não têm os grupos `activity`, `exports` e
  `notifications` e caem em `pt-PT`; existe `lang/ms-MY` sem entrada em `config/locales.php`.
- **Corpo dos emails de teste**: `resources/views/emails/test-mail.blade.php` mantém
  prosa hardcoded — só o subject/passagem por `__()` estão no locale do destinatário.

