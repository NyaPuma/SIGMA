# Otimização de Performance e Deploy de Produção

Documento consolidado das técnicas de performance aplicadas (ou recomendadas) ao
Sistema Integrado de Gestão de Manutenção (Laravel). Complementa o `AGENTS.md` e o
`ARCHITECTURE.md` focando-se em ganhos de runtime, caching, filas e observabilidade.

O projeto corre em **Laravel 12 / PHP 8.3 / MySQL / FrankenPHP / Redis**, com deploy
por **Docker Compose** (`compose.yaml`).

---

## 1. Runtime de Aplicação Persistente — Laravel Octane ✅ aplicado

O PHP tradicional arranca a aplicação de raiz em **cada pedido HTTP** e encerra no fim.
O **Laravel Octane** mantém a aplicação carregada em memória, transformando tempos de
resposta típicos de **80–150ms em 5–15ms** (ganho de 5x a 10x).

O projeto usa **FrankenPHP** — servidor de aplicação escrito em **Go**, o mais simples
de configurar no ecossistema Laravel (também funciona com Swoole/RoadRunner).

| Onde | O quê |
|------|-------|
| `Dockerfile` | `ENTRYPOINT` octane-entrypoint + `CMD octane:frankenphp` |
| `infra/entrypoint.sh` | gera caches (`optimize`) antes de arrancar |
| `config/octane.php` | servidor `frankenphp` (via `OCTANE_SERVER`) |
| `compose.yaml` | serviço `app` executa o Octane |

> 🚨 **Octane exige atenção a estado por request.** Singleton no container que guarde
> estado entre pedidos deve ser marcado para `flush` em `config/octane.php` ou usar
> bindings próprios do Octane. Ver a secção de listeners em `config/octane.php`.

---

## 2. Caching em Memória — Redis + phpredis ✅ configurado

O **Redis** substitui o driver `file`/`database` para sessões e cache, mantendo tudo em
RAM. Para máxima performance instala-se o módulo compilado **phpredis** (extensão em C)
em vez do `predis` (PHP puro).

No projeto:

| Variável (`.env.production`) | Valor |
|------------------------------|-------|
| `SESSION_DRIVER` | `redis` |
| `CACHE_STORE` | `redis` |
| `QUEUE_CONNECTION` | `redis` |
| `REDIS_CLIENT` | `phpredis` |

> ⚠️ **Pré-requisito:** a extensão `phpredis` tem de estar instalada no PHP do servidor
> (`install-php-extensions pdo_mysql` afeta apenas DB; verificar `php -m` → `redis`).
> O FrankenPHP já inclui `redis` no `dunglas/frankenphp`? Confirmar, senão
> `RUN install-php-extensions redis`.

Serviço **Redis** declarado em `compose.yaml` (porta `6379`, persistência AOF, política
de evição `allkeys-lru` a 256 MB).

---

## 3. Motores de Pesquisa Externa — Meilisearch / Typesense 📄 documentado (não instalado)

Se as buscas usam `LIKE '%termo%'` / `whereHas()` em tabelas com **milhares de registos**,
a base de dados SQL fica bloqueada. **Meilisearch** (Rust) e **Typesense** (C++) entregam
resultados <10ms e integram-se via **Laravel Scout**.

**Quando vale a pena:** tabelas com **>10k–50k linhas** pesquisadas por palavra parcial.
Para volumes pequenos o custo (serviço externo + índice a manter) supera o ganho.

**Como ativar (guia, não aplicado):**
```bash
# Meilisearch (ex.: Docker)
docker run -p 7700:7700 getmeili/meilisearch
composer require laravel/scout
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
# config/scout.php → 'driver' => 'meilisearch'
# .env: SCOUT_DRIVER=meilisearch, MEILISEARCH_HOST=http://127.0.0.1:7700, MEILISEARCH_KEY=<master>
php artisan make:migration --create=...  # p/ `meilisearch_indexes` se necessário
```

No model pesquisado:
```php
use Laravel\Scout\Searchable;

class Part extends Model { use Searchable; }
```

Depois: `php artisan scout:import "App\Models\Part"` e substituir queries `LIKE` por
`Part::search('termo')->get()`.

> **Decisão:** não instalado de propósito — apenas documentado. O volume atual não o
> justifica e as queries de stock/equipamentos já estão otimizadas (eager load + índices).

---

## 4. CDN / Edge — Cloudflare 📄 documentado (requer domínio)

O **Cloudflare** pode servir ficheiros estáticos (JS/CSS/imagens) e **guardar em cache
páginas HTML completas** nos nós de rede mais próximos do utilizador — o pedido nem chega
ao teu servidor se a resposta já estiver no Edge.

Ordem de implementação:
1. Apontar o domínio para o Cloudflare (nameservers).
2. **Cache Rules** (Dashboard) — cache `everything` para URLs estáticos.
3. **APO (Automatic Platform Optimization)** para cache de páginas HTML dinâmicas (Laravel).
4. Forçar HTTPS e `minify`.

**No Caddyfile já preparamos:**
- Assets `/build/*` → `Cache-Control: public, max-age=31536000, immutable` (amigável a CDN).
- HTML/API → `no-store` (para não servir sessões/estado de utilizador em cache).
- Headers de segurança (`X-Content-Type-Options`, `X-Frame-Options`, etc.).

> ⚠️ **Cuidado:** páginas autenticadas não devem ser cacheadas no Edge (risco de vazar
> dados de utilizador). Usar Cloudflare apenas para **assets** e páginas públicas/guest
> (ex.: landing, login).

---

## 5. Profiling / Diagnóstico — Laravel Pulse ✅ aplicado + APM externo 📄

**Laravel Pulse** (nativo, gratuito) monitoriza em tempo real:
- endpoints **lentos** (`SlowRequests`)
- queries SQL pesadas (`SlowQueries`)
- filas e jobs lentos (`SlowJobs`, `Queues`)
- consumo de CPU/RAM do servidor (`Servers`)
- churn de cache e exceções

**Instalado e configurado:**
- Pacote `laravel/pulse` (v1.8) + `livewire` v4.
- Migração `create_pulse_tables` aplicada.
- `config/pulse.php` publicado.
- Rota `/pulse` (auto-descoberta), protegida via ability `viewPulse` → **apenas admins**.
- `.env` de produção: `PULSE_INGEST_DRIVER=redis` (ingest via Redis), retenção 14 dias.

**Painel:** aceder `/pulse` autenticado como admin.

> O diagnóstico externo (Blackfire.io / Tideways) faz **profiling por chamada** que o
> Pulse não faz. Só compensa em análises pontuais de regressões confirmadas.

---

## 6. Filas Assíncronas — Laravel Horizon 📄 recomendado

**Laravel Horizon** é o painel e gestor de processos para **Redis Queues**. Permite mover
para segundo plano: envio de e-mails, geração de PDFs, processamento de imagens e chamadas
a APIs externas, devolvendo a resposta HTTP **imediatamente** ao utilizador.

**Estado atual:**
- Fila `database` em dev, `redis` em produção.
- Worker de fila `queue` declarado em `compose.yaml`.
- Já existem jobs: `CheckLowStockJob`, PDF exports (`ExportReportPdfJobs`, timeout 180s).

**Guia para ativar Horizon (não aplicado — requer decisão):**
```bash
composer require laravel/horizon
php artisan horizon:install
# config/horizon.php; ajustar o scheduler de workers (ex.: email/pdf/default)
```

No `compose.yaml`, trocar o worker genérico por:
```yaml
command: php artisan horizon
```
e proteger a rota `/horizon` à semelhança do Pulse (`Gate::define('viewHorizon', ...)`).

> **Recomendação:** Horizon simplifica bastante a gestão multi-fila e supervisão, mas
> exige Redis de produção estável. Enquanto a fila for pequena, o `queue:work` atual basta.

---

## Resumo / Estado

| Técnica | Estado | Impacto |
|---------|--------|---------|
| 1. Octane/FrankenPHP | ✅ aplicado | 5x–10x latency |
| 2. Redis + phpredis | ✅ configurado (falta ext no servidor) | cache/sessão/fila em RAM |
| 3. Meilisearch/Typesense | 📄 documentado (não instalado) | só com volume grande |
| 4. Cloudflare CDN | 📄 documentado (requer domínio) | redução de carga/edge |
| 5. Pulse / APM | ✅ Pulse aplicado | observabilidade |
| 6. Horizon | 📄 recomendado | gestão de filas |

**Checklist de deploy final:**
1. `php artisan optimize` (via entrypoint) — caches config/route/view/event.
2. `php artisan migrate --force` + `php artisan pulse:check` para validar Pulse.
3. Confirmar extensão `phpredis` no servidor (`php -m | grep redis`).
4. Workers: `php artisan queue:work` (ou Horizon).
5. `php artisan schedule:work` (tarefas agendadas — ex.: `currency:update-rates`, `CheckLowStockJob`).
6. Confirmar `.env` de produção com `SESSION_DRIVER/CACHE_STORE/QUEUE_CONNECTION=redis`.