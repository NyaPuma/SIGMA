# Internationalization (i18n) Progress — SGM

> Last updated: 2026-11-05 (see §0 for the current state and the quality-repair campaign; §1–§8 are the historical audit record of the JSON→PHP migration and key-parity work, phases 1–4, 2026-08)
> Source of sense: pt-PT (the strings in the code). Confirmation pivot for meaning: en-GB.

## 0. Current state (2026-11-05)

The older sections measure **keys**. Keys alone stopped being a sufficient definition of "translated": a locale could hold 1498/1498 paths and still display English, Portuguese or a corrupted hybrid, because `__()` returns whatever is in the file and a copy of the source string looks like a valid value.

Three things changed:

1. **A value classifier is the gate.** `classifyValue()` in `lib-i18n.php` labels every value in every locale as `ok`, `formato`, `internacional`, `europeu`, or as pending (`falta`, `portugues`, `ingles`, `mistura`, `pidgin`, `escrita`). The CI test `tests/Unit/Support/TranslationDriftTest.php` fails if a single value lands in a pending category, so "complete" now means *keys complete **and** nothing pending*. Earlier claims of "0 missing, 100%" in §2/§4/§5 only ever proved key parity.
2. **The pipeline is `coverage → translations/ → verify-locale → apply-translations`.** `php coverage.php [--work=work3]` writes, per locale and group, the list of keys still pending; agents write batches under `translations/<locale>/<group>.php`; `php verify-locale.php <locale>` rejects a batch that leaves any key pending or touches keys outside pt-PT; `php apply-translations.php [--overwrite] <locale>` merges the accepted batch into `lang/`. Applying a batch changes the locale's own corpus, which is used as the translation dictionary, so previously tolerated copies get unmasked — the campaign runs in waves until `coverage.php` reports TOTAL pending 0.
3. **Evasion is treated as a defect class, not noise.** The classifier rejects byte copies, mixed-script pidgin (`Breaдзcrumb`), single-letter script injection inside an English word (`passworдз`, `manდatory`) and half-mangled substrings (`interдоwne`). Each rule was added after observing a specific batch try to pass it; `REPAIR-BRIEF.md` records the rules for future waves. Known residual gap: Latin-script locales can still copy English without any script change — those are caught by comparing against the English pivot token by token, not by script.

Consequences for the numbers below: §2, §4 and §5 describe the 2026-08 state (key parity) and remain true as history; they are **not** evidence of translation quality. `en-GB` is the value reference for English, not the source of truth — new keys enter `pt-PT` first, since the code calls `__('domain.{pt-PT string}')`.

**The canonical set is 1497 keys, not the 1498 counted in §2–§5.** `ui.div` was a literal key with no call site anywhere in `app/`, `resources/`, `routes/`, `config/` or `tests/` (it had been listed at §4 as a "legitimate identity value"); it was removed from pt-PT, all 53 locales and the English pivot on 2026-11-05.

## 1. Context and Rules

Target architecture: `lang/{locale}/{domain}.php`, with PHP arrays grouped by domain (e.g., `auth.php`, `tickets.php`, `stock.php`). The JSON format (`lang/{locale}.json`) was **completely eliminated** in Front C (2026-08-14); the 18 original files are archived in `docs/i18n/archive-json/` (audit reference and historical canonical source).

- The source of truth is `en` (English). *(Historical wording of this phase — superseded by §0: the source of sense is `pt-PT`, because the code calls `__()` with Portuguese literals; `en-GB` is only the English value reference.)*
- Any new key enters `en` first. *(Superseded: a new key enters `pt-PT` first.)*
- "Missing key" comparisons use `en`'s key tree as the canonical set.
- A locale only becomes `✅ Complete` when it has exactly the same number of keys, in the same domains, as `en` **and** the JSON→PHP migration is complete. *(Superseded by §0: `✅ Complete` additionally requires 0 values pending in `classifyValue()`, enforced by `TranslationDriftTest`.)*

**Canonical key set definition (measured in Phase 1):**
- Source keys are strings in Portuguese (pt-PT). The JSON (`lang/{locale}.json`) maps each source string → translation (in `pt-PT` it's identity, since it's the source language).
- The `.php` files per domain contain two types of keys: (a) **structural keys** (e.g., `status.open`, `ui.results_count`, `auth.failed`) accessed via `__('{domain}.{key}')`; and (b) **migrated source strings** (e.g., key `A agendar...` in `common.php`) accessed via `__('{domain}.{string}')`.
- **Canonical `en` (reference) set = union of 2853 paths:**
  - 1355 flat paths from `en-US.json` (source strings, accessed via `__('string')`).
  - 1498 dotted paths from the 21 `en-US` `.php` domains (accessed via `__('domain.key')`) — including the 123 source keys migrated in Phase 2a (1386 → 1498).
- Parity is measured over this set of 2853 paths. Identical sets between en-US and en-GB (they differ only in values).

## 2. JSON → PHP Migration Status

All 21 locales have PHP. **JSON→PHP migration is complete: `.json` files were deleted** (Front C, 2026-08-14; archived in `docs/i18n/archive-json/`). The domain structure is identical to `en` in all locales (21 domains, 1498 keys). **Phase 2a complete: the 123 source keys that existed only in JSON were migrated to `.php`** (0 JSON-only remaining; see section 5).

| Locale | JSON exists? | PHP exists? | Migration complete? | Notes |
|---|---|---|---|---|
| bg-BG | No (never existed) | Yes (21 dom., 1498 keys) | ✅ Yes (PHP-only from start) | **Created from scratch in Phase 3c** (2026-08-16); no historical JSON — see section 4 |
| cs-CZ | No (archived) | Yes (21 dom., 1498 keys) | ✅ Yes | domains identical to en |
| da-DK | No (archived) | Yes (21 dom., 1498 keys) | ✅ Yes | same |
| de-DE | No (archived) | Yes (21 dom., 1498 keys) | ✅ Yes | same |
| el-GR | No (archived) | Yes (21 dom., 1498 keys) | ✅ Yes | same |
| en-GB | No (archived) | Yes (21 dom., 1498 keys) | ✅ Yes | same; see ⚠️ source of truth (en-US vs en-GB) |
| en-US | No (archived) | Yes (21 dom., 1498 keys) | ✅ Yes | same; used as key reference in this audit |
| es-ES | No (archived) | Yes (21 dom., 1498 keys) | ✅ Yes | same; 175 JSON identity values — review |
| fi-FI | No (archived) | Yes (21 dom., 1498 keys) | ✅ Yes | same |
| fr-FR | No (archived) | Yes (21 dom., 1498 keys) | ✅ Yes | same |
| hu-HU | No (archived) | Yes (21 dom., 1498 keys) | ✅ Yes | same |
| it-IT | No (archived) | Yes (21 dom., 1498 keys) | ✅ Yes | same |
| nl-NL | No (archived) | Yes (21 dom., 1498 keys) | ✅ Yes | same |
| pl-PL | No (archived) | Yes (21 dom., 1498 keys) | ✅ Yes | same |
| pt-BR | No (archived) | Yes (21 dom., 1498 keys) | ✅ Yes | same; 994 JSON identity values — review |
| pt-PT | No (archived) | Yes (21 dom., 1498 keys) | ✅ Yes | same; full identity since it's the source language |
| ro-RO | No (archived) | Yes (21 dom., 1498 keys) | ✅ Yes | same |
| ru-RU | No (never existed) | Yes (21 dom., 1498 keys) | ✅ Yes (PHP-only from start) | **Created from scratch in Phase 3b** (2026-08-16); no historical JSON — see section 4 |
| sv-SE | No (archived) | Yes (21 dom., 1498 keys) | ✅ Yes | same |
| tr-TR | No (archived) | Yes (21 dom., 1498 keys) | ✅ Yes | same |
| uk-UA | No (never existed) | Yes (21 dom., 1498 keys) | ✅ Yes (PHP-only from start) | **Created from scratch in Phase 3c** (2026-08-16); no historical JSON — see section 4 |
| zh-CN | No (never existed) | Yes (21 dom., 1498 keys) | ✅ Yes (PHP-only from start) | **Created from scratch in Phase 3** (2026-08-16); no historical JSON — see section 4 |
| zh-TW | No (never existed) | Yes (21 dom., 1498 keys) | ✅ Yes (PHP-only from start) | **Created from scratch in Phase 3** (2026-08-16); no historical JSON — see section 4 |
| ja-JP | No (never existed) | Yes (21 dom., 1498 keys) | ✅ Yes (PHP-only from start) | **Created from scratch in Phase 3** (2026-08-16); no historical JSON — see section 4 |
| ko-KR | No (never existed) | Yes (21 dom., 1498 keys) | ✅ Yes (PHP-only from start) | **Created from scratch in Phase 3** (2026-08-16); no historical JSON — see section 4 |
| hi-IN | No (never existed) | Yes (21 dom., 1498 keys) | ✅ Yes (PHP-only from start) | **Created from scratch in Phase 3** (2026-08-16); no historical JSON — see section 4 |
| id-ID | No (never existed) | Yes (21 dom., 1498 keys) | ✅ Yes (PHP-only from start) | **Created from scratch in Phase 3** (2026-08-16); no historical JSON — see section 4 |
| vi-VN | No (never existed) | Yes (21 dom., 1498 keys) | ✅ Yes (PHP-only from start) | **Created from scratch in Phase 3** (2026-08-16); no historical JSON — see section 4 |
| th-TH | No (never existed) | Yes (21 dom., 1498 keys) | ✅ Yes (PHP-only from start) | **Created from scratch in Phase 3** (2026-08-16); no historical JSON — see section 4 |
| ms-MY | No (never existed) | Yes (21 dom., 1498 keys) | ✅ Yes (PHP-only from start) | **Created from scratch in Phase 3** (2026-08-16); no historical JSON — see section 4 |
| ar-AE | No (never existed) | Yes (21 dom., 1498 keys) | ✅ Yes (PHP-only from start) | **Created from scratch in Phase 3** (2026-08-16); no historical JSON — see section 4 |
| he-IL | No (never existed) | Yes (21 dom., 1498 keys) | ✅ Yes (PHP-only from start) | **Created from scratch in Phase 3** (2026-08-16); no historical JSON — see section 4; RTL |
| fa-IR | No (never existed) | Yes (21 dom., 1498 keys) | ✅ Yes (PHP-only from start) | **Created from scratch in Phase 3** (2026-08-16); no historical JSON — see section 4; RTL |
| nb-NO | No (never existed) | Yes (21 dom., 1498 keys) | ✅ Yes (PHP-only from start) | **Created from scratch in Phase 3** (2026-08-16); no historical JSON — see section 4 |
| sk-SK | No (never existed) | Yes (21 dom., 1498 keys) | ✅ Yes (PHP-only from start) | **Created from scratch in Phase 3** (2026-08-16); no historical JSON — see section 4 |

## 3. Translation Domains Identified

Confirmed by actual inspection — 21 domains, present identically in `en` and all 21 locales (18 historical + ru-RU, uk-UA and bg-BG created from scratch):

| Domain | Key count (en) | Description |
|---|---|---|
| `analytics.php` | 14 | Analytics/KPIs (structural keys) |
| `analytics_data.php` | 13 | Analytics data/events |
| `auth.php` | 71 | Authentication (structural + source strings) |
| `auth_box.php` | 4 | Auth box (user menu) |
| `common.php` | 665 | Common UI strings (largest domain) |
| `dashboard.php` | 32 | Control panel |
| `equipment.php` | 75 | Equipment |
| `maintenance_plan.php` | 31 | Maintenance plans |
| `messages.php` | 108 | Messages/notifications |
| `pagination.php` | 4 | Pagination (Laravel) |
| `preferences.php` | 33 | User preferences |
| `room.php` | 32 | Rooms |
| `stock.php` | 112 | Stock (parts, suppliers, movements) |
| `stock_dashboard.php` | 4 | Stock dashboard |
| `stock_movement.php` | 3 | Stock movement types |
| `stock_part.php` | 8 | Stock parts |
| `ticket_detail.php` | 21 | Ticket detail |
| `ticket_media.php` | 23 | Ticket media/photos |
| `tickets.php` | 108 | Tickets |
| `ui.php` | 103 | Generic UI (tables, results, etc.) |
| `validation.php` | 34 | Validation (Laravel messages + source strings) |
| **Total** | **1498** | |

**Notes:**
- The `notifications.php` and `errors.php` domains (suggested in the template) **do not exist** — notification messages live in `messages.php`/`common.php`; error pages use source strings via JSON (e.g., `__('Internal Server Error')`).
- Laravel provides `pagination.php` and `validation.php` as built-in domains.

## 4. Translation Status by Locale

Reference: en-US (2853 canonical paths = 1355 JSON + 1498 PHP). **All 18 historical locales: 100% key parity (2853/2853, 0 missing, 0 empty, 0 extra).** The 14 locales created from scratch (**ru-RU, uk-UA, bg-BG, zh-CN, zh-TW, ja-JP, ko-KR, hi-IN, id-ID, vi-VN, th-TH, ms-MY, ar-AE, he-IL, fa-IR, nb-NO, sk-SK**) are measured by **functional coverage** — see notes below the table.

Note on "identity values": value equals the key (the source string). In `pt-PT` this is by design (source language). In the rest, most are legitimate (technical terms, acronyms, coincidental words — e.g., `SKU`, `SLA`, `MTTR`, `Admin`). **Review completed in Phase 3a** for `pt-BR` (6 corrections) and `es-ES` (0) — see section 6.

\* **ru-RU (Phase 3b), uk-UA and bg-BG (Phase 3c), zh-CN, zh-TW and ja-JP (Phase 3):** created from scratch on 2026-08-16, **with no historical JSON** (`lang/{locale}.json` never existed). The canonical metric of 2853 paths (1355 JSON + 1498 PHP) is a **structural source gap** — the 1355 JSON paths are no longer accessed by the app (Front B migrated all code to `__('domain.key')`; 0 flat calls). Therefore these locales are measured by **functional coverage**: the **1498 PHP paths are complete (21 domains, 0 missing, 0 empty)** and cover **1211/1211 (100%) of paths actually used in code** — identical to the other locales (audit `docs/i18n/scripts/audit_usage.py`).

\*\* **Identity values in locales created from scratch — all legitimate** (technical terms/acronyms `ID`, `MTTR`, `NIF`, `SKU`, `SLA`, `API`, `OK`, `Swagger`, `cURL`, `Round-robin`, `part_id`; formats `Y-m-d`, `Y-m-d H:i:s`, `d`, `—`; placeholders `:reference — :equipment (:priority)`): **17 in ru-RU, 15 in uk-UA, 12 in bg-BG, 17 in zh-CN, 17 in zh-TW, 17 in ja-JP, 17 in ko-KR**. No pending review. (`div` was in this list in 2026-08; it was not a legitimate identity value but a dead key with no call site — removed 2026-11-05, see §0.)

| Locale | en key count (ref) | Present count | % | Status (keys) | Identity values (JSON / PHP) | JSON→PHP migration | Last verified |
|---|---|---|---|---|---|---|---|
| bg-BG | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 12\*\* | ✅ Complete (PHP-only from start) | 2026-08-16 |
| cs-CZ | 2853 | 2853 | 100.0 | ✅ Complete | 50 / 48 | ✅ Complete | 2026-08-16 |
| da-DK | 2853 | 2853 | 100.0 | ✅ Complete | 64 / 62 | ✅ Complete | 2026-08-16 |
| de-DE | 2853 | 2853 | 100.0 | ✅ Complete | 26 / 27 | ✅ Complete | 2026-08-16 |
| el-GR | 2853 | 2853 | 100.0 | ✅ Complete | 30 / 28 | ✅ Complete | 2026-08-16 |
| en-GB | 2853 | 2853 | 100.0 | ✅ Complete | 59 / 65 | ✅ Complete | 2026-08-16 |
| en-US | 2853 | 2853 | 100.0 | ✅ Complete | 60 / 66 | ✅ Complete | 2026-08-16 |
| es-ES | 2853 | 2853 | 100.0 | ✅ Complete | 175 / 177 | ✅ Complete | 2026-08-16 |
| fi-FI | 2853 | 2853 | 100.0 | ✅ Complete | 51 / 50 | ✅ Complete | 2026-08-16 |
| fr-FR | 2853 | 2853 | 100.0 | ✅ Complete | 32 / 33 | ✅ Complete | 2026-08-16 |
| hu-HU | 2853 | 2853 | 100.0 | ✅ Complete | 51 / 50 | ✅ Complete | 2026-08-16 |
| it-IT | 2853 | 2853 | 100.0 | ✅ Complete | 49 / 52 | ✅ Complete | 2026-08-16 |
| nl-NL | 2853 | 2853 | 100.0 | ✅ Complete | 30 / 31 | ✅ Complete | 2026-08-16 |
| pl-PL | 2853 | 2853 | 100.0 | ✅ Complete | 29 / 30 | ✅ Complete | 2026-08-16 |
| pt-BR | 2853 | 2853 | 100.0 | ✅ Complete | 994 / 1004 | ✅ Complete | 2026-08-16 |
| pt-PT | 2853 | 2853 | 100.0 | ✅ Complete | 1355 / 1373 (source, expected) | ✅ Complete | 2026-08-16 |
| ro-RO | 2853 | 2853 | 100.0 | ✅ Complete | 57 / 56 | ✅ Complete | 2026-08-16 |
| ru-RU | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 17\*\* | ✅ Complete (PHP-only from start) | 2026-08-16 |
| sv-SE | 2853 | 2853 | 100.0 | ✅ Complete | 64 / 62 | ✅ Complete | 2026-08-16 |
| tr-TR | 2853 | 2853 | 100.0 | ✅ Complete | 21 / 20 | ✅ Complete | 2026-08-16 |
| uk-UA | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 15\*\* | ✅ Complete (PHP-only from start) | 2026-08-16 |
| zh-CN | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 17\*\* | ✅ Complete (PHP-only from start) | 2026-08-16 |
| zh-TW | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 17\*\* | ✅ Complete (PHP-only from start) | 2026-08-16 |
| ja-JP | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 17\*\* | ✅ Complete (PHP-only from start) | 2026-08-16 |
| ko-KR | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 17\*\* | ✅ Complete (PHP-only from start) | 2026-08-16 |
| hi-IN | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 19\*\* | ✅ Complete (PHP-only from start) | 2026-08-16 |
| id-ID | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 28\*\* | ✅ Complete (PHP-only from start) | 2026-08-16 |
| vi-VN | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 20\*\* | ✅ Complete (PHP-only from start) | 2026-08-16 |
| th-TH | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 21\*\* | ✅ Complete (PHP-only from start) | 2026-08-16 |
| ms-MY | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 21\*\* | ✅ Complete (PHP-only from start) | 2026-08-16 |
| ar-AE | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 16\*\* | ✅ Complete (PHP-only from start) | 2026-08-16 |
| he-IL | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 17\*\* | ✅ Complete (PHP-only from start) | 2026-08-16 |
| fa-IR | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 17\*\* | ✅ Complete (PHP-only from start) | 2026-08-16 |
| nb-NO | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 17\*\* | ✅ Complete (PHP-only from start) | 2026-08-16 |
| sk-SK | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 17\*\* | ✅ Complete (PHP-only from start) | 2026-08-16 |
| sl-SI | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 18\*\* | ✅ Complete (PHP-only from start) | 2026-08-21 |
| lb-LU | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 18\*\* | ✅ Complete (PHP-only from start) | 2026-08-21 |
| hy-AM | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 18\*\* | ✅ Complete (PHP-only from start) | 2026-08-21 |
| mk-MK | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 18\*\* | ✅ Complete (PHP-only from start) | 2026-08-21 |
| mt-MT | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 18\*\* | ✅ Complete (PHP-only from start) | 2026-08-21 |
| sq-AL | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 18\*\* | ✅ Complete (PHP-only from start) | 2026-08-21 |
| sr-RS | 1498\* | 1498 | —\* | ✅ Complete (PHP) | 0 / 18\*\* | ✅ Complete (PHP-only from start) | 2026-08-21 |

### 4.1 Scope gate (2026-08-21)

**53 complete ✅** — see section 4 table. **All configured locales are complete.** 18 historical + 14 Phase 3 + 7 Phase 4 (sl-SI, lb-LU, hy-AM, mk-MK, mt-MT, sq-AL, sr-RS) + 11 Phase 5 (az-AZ, be-BY, bs-BA, ca-AD, cnr-ME, et-EE, hr-HR, is-IS, ka-GE, lt-LT, lv-LV). All with 1498/1498 keys, 1211/1211 functional coverage.

| Locale | Market (criteria: G20 / largest industrial exporters / largest industrial GDPs) | RTL | config/locales.php |
|---|---|---|---|
| ar-AE | Arabic — UAE, Saudi Arabia, Egypt | ✅ | already configured ✅ |
| fa-IR | Persian — Iran | ✅ | already configured ✅ |
| he-IL | Hebrew — Israel | ✅ | already configured ✅ |
| hi-IN | Hindi — India | | already configured ✅ |
| id-ID | Indonesian — Indonesia | | already configured ✅ |
| ja-JP | Japanese — Japan | | already configured ✅ |
| ko-KR | Korean — South Korea | | already configured ✅ |
| ms-MY | Malay — Malaysia | | already configured ✅ |
| nb-NO | Norwegian — Norway (industrial GDP; added by agent criteria) | | already configured ✅ |
| sk-SK | Slovak — Slovakia (automotive exporter; added by agent criteria) | | already configured ✅ |
| th-TH | Thai — Thailand | | already configured ✅ |
| vi-VN | Vietnamese — Vietnam | | already configured ✅ |
| zh-CN | Simplified Chinese — China | | already configured |
| zh-TW | Traditional Chinese — Taiwan | | already configured ✅ |

> **Gate notes (2026-08-21):**
> - RTL (`ar-AE`, `he-IL`, `fa-IR`): scope = **translate text only**; layout/CSS (e.g., `resources/css/rtl.css`) out of scope for this phase.
> - **All 14 Phase 3 locales are complete** (2026-08-16): zh-CN, zh-TW, ja-JP, ko-KR, hi-IN, id-ID, vi-VN, th-TH, ms-MY, ar-AE, he-IL, fa-IR, nb-NO, sk-SK. All with 1498/1498 keys, 57/57 placeholders, 1211/1211 functional coverage.
> - **7 new European locales completed on 2026-08-21:** sl-SI, lb-LU, hy-AM, mk-MK, mt-MT, sq-AL, sr-RS. All with 1498/1498 keys, 1211/1211 functional coverage.
> - Regional variants kept separate (`en-GB`/`en-US`, `pt-PT`/`pt-BR`).
> - Project `xx-XX` code convention; Chinese = `zh-CN` + `zh-TW` (instead of `zh-Hans`/`zh-Hant`).
> - `da-DK`, `fi-FI`, `bg-BG` (already complete) are explicitly in scope.
> - **Configured locales OUT OF SCOPE (10)** — no files, remain in pt-PT fallback, **do not touch without instruction**: az-AZ, be-BY, bs-BA, ca-AD, cnr-ME, et-EE, hr-HR, is-IS, ka-GE, lt-LT, lv-LV.

**Classification applied** (see section 7 for formal definition):
- `✅ Complete` (keys): 100% parity, 0 empty. Applies to the 42 with files.
- `🔴 Missing / not started`: no files (applies to the 14 out of scope).
- `JSON→PHP migration` status is `✅ Complete` in all 35 (.json deleted in Front C on 2026-08-14; archived in `docs/i18n/archive-json/`).

### 4.2 Code integrity checks (Phase 1; Front B scope corrected in Phase 2a)

Cross-analysis with code (`app/**/*.php`, `resources/views/**/*.blade.php` and `*.php`, `resources/js/**/*.js`, `routes/**/*.php`, `config/**/*.php`):

- **1861 `__()` call sites** in **144 files** (from 526 distinct files analyzed in `app/**`, `resources/views/**`, `resources/js/**`, `routes/**`, `config/**`) and **1218 distinct call keys** (measurement corrected in Phase 2a with regex `'([^']*)'`/`"([^"]*)"` — the `[^\"']*` pattern truncated keys with internal quotes). **Correction note:** the initial measurement of "3405 call sites (3401 single + 4 double)" **was wrong due to double counting** — `.blade.php` files match both globs (`**/*.blade.php` and `**/*.php`); counting `__('` without dedup yields exactly 3401 (⇒ 3405). The real count, with dedup of the 526 files, is **1861**.
- **100 prefixed structural calls** (`__('{domain}.{key}')`, e.g., `ticket_detail.status.open`) → all resolve in `en` `.php` files (0 unresolved).
- **14 already-prefixed source strings** (13 in `preferences.*` + 1 `stock_part.min`) → correct, no migration needed.
- **1104 source strings called WITHOUT prefix** (760 without dots + **344 with dots in text**, e.g., `Ex.: João Silva`, `Qtd. consumida`, `A agendar...`) → resolve **exclusively via JSON** (Laravel: dotless keys only query `lang/{locale}.json`). **Note:** the previously measured scope (760 strings/106 files) **was wrong** — it ignored source strings containing dots in text, now included (and later corrected: 1217→1218 keys, 1103→1104 strings).
- **1 unresolved call:** `preferences.Ajuste as suas preferências de língua, moeda e formato de data independentemente.` (`resources/views/preferences/edit.blade.php:17`) — the code text diverged from the source string (`...de língua, moeda, formato de data e números independentemente.`) and doesn't exist in PHP or JSON → **fixed during Front B** (aligned to canonical text).
- **Critical conclusion for Phase 2:** source strings are today resolved **only by JSON**. The 1355 source string entries in `.php` are "dormant" (code only calls them with domain prefix in 14 cases). **Deleting `.json` without migrating the code breaks source string translations** (they'd fall back to pt-PT). → see decision in section 7.

**Front B status (code migration, completed 2026-08-14):** the **1104 unprefixed source strings** were migrated to `__('{domain}.{string}')` via `frente_b_map.json` (1104 entries built from pt-PT identity entries + context decisions for 18 ambiguous strings; validated: identity in chosen domain, 0 unresolvable). **1659 substitutions applied** (1635 exact `__('KEY')` + ~24 with 2nd argument) in **143 files**. Context decisions: 8 → `tickets` (Aberto, Alta, Baixa, Cancelado, Em Progresso, Fechado, Resolvido, Urgente), Fornecedores → `stock`, Tickets Abertos → `dashboard`, Formato de hora atualizado com sucesso. → `preferences`, `preferences.*` block → `preferences`; `'min'` → `common` (identity; **do not confuse** with `stock_part.min`, which is structural `'mín.'`). 'Em Atendimento' is not called in code. **Post-migration verification:** 1861 instances (1849 pre-migration in the 143 touched files); **1214 distinct keys** (1218 − 1105 removed + 1105 added − 4 collisions); the 4 collisions (`preferences.Formato de Data`, `preferences.Formato de Números`, `preferences.Moeda`, `preferences.Preferências do Utilizador`) are source strings with already-prefixed **and** unprefixed calls → both resolve to the SAME identity entry in `preferences.php` (semantically consistent); **0 unprefixed source strings remaining**; **0 resolution failures in pt-PT and the 18 locales**; final audit **2853/2853 (100%)**.

**Front C status (JSON deletion, completed 2026-08-14):** before deletion, an exhaustive scan of the entire repository (any extension, excluding `vendor`/`node_modules`) found **3 dynamic `__($var)` calls** and **2 unprefixed literal calls outside original scope** (in `tests/`, not covered by Front B):
- `tests/Feature/Web/Controllers/ProfileControllerTest.php` — `__('Password alterada com sucesso.')` and `__('Perfil atualizado com sucesso.')` → **fixed** to `__('messages....')` (aligned to migrated controller).
- `app/Http/Middleware/LocalizeSwaggerDocument.php` — `__($value)` on OpenAPI source strings → **fixed** with `string → domain` mapping (7 strings: `common`, `tickets`, `auth`, `stock`); the remaining 4 `OpenApiSpec` strings don't exist in any translation file and remain in pt (pre-existing behavior).
- The 2 `__($item['label'])` calls from sidebar menus resolve via already-prefixed keys → no change.

The 18 `lang/{locale}.json` files were **deleted** and archived in `docs/i18n/archive-json/` (duplicate copy in `/tmp/opencode/frente_c_backup/`). **Post-deletion verification:** audit **2853/2853 (100%)** across all 18 locales (JSON reference read from archive); **1830 literal `__()` instances, 1183 distinct keys, 0 unprefixed, 0 resolution failures in the 18 locales**.

## ⚠️ Source of truth issues (en)

1. **en-US vs en-GB diverge in values (77 differences), though they have identical key sets.** Examples: `Acesso Proibido` → US `Prohibited Access` / GB `Access Forbidden`; `Agenda Inteligente` → US `Smart Agenda` / GB `Smart Calendar`; `Ativos` → US `Assets` / GB `Active`. ✅ **Resolved (Puma decision, 2026-08-14): canonical `en` for values is `en-GB`.**
2. **`en` has 123 source keys represented only in JSON** (not in any `.php` domain). This is not a content gap (the values exist), but a PHP representation structural gap: if `.json` is deleted without migration, those 123 strings lose their translation. See complete list in section 5. Migration of these keys is the object of Phase 2.
3. **`config/locales.php` lists 49 languages; only 18 have `lang/` files.** ✅ **Resolved (Puma decision, 2026-08-14): the remaining 31 (ru-RU, uk-UA, bg-BG, hr-HR, sr-RS, sk-SK, sl-SI, et-EE, lv-LV, lt-LT, mk-MK, sq-AL, ca-AD, is-IS, nb-NO, hy-AM, ka-GE, az-AZ, be-BY, lb-LU, mt-MT, bs-BA, cnr-ME, zh-CN, ja-JP, ko-KR, hi-IN, ar-AE, th-TH, vi-VN, id-ID) **enter scope** as `🔴 Missing / not started` (file creation from scratch in Phase 3).**

## 5. Missing Keys by Locale (detail)

**There are no missing keys** — all 18 locales have the 2853 canonical paths (100% parity, 0 missing, 0 empty, 0 extra).

**Phase 2a eliminated the migration backlog**: the **123 source keys that existed only in JSON were migrated to `.php` domains** (0 JSON-only remaining). Historical list from Phase 2a (identical in all locales) — record of migrated keys:

**Validation (9)** — suggest `validation.php`:
- `A confirmação do campo :attribute não confere.`
- `O campo :attribute é de preenchimento obrigatório.`
- `O campo :attribute deve ser no mínimo :min.`
- `O campo :attribute deve ser um endereço de e-mail válido.`
- `O campo :attribute deve ser um número.`
- `O campo :attribute deve ter pelo menos :min caracteres.`
- `O campo :attribute não pode ser superior a :max.`
- `O campo :attribute não pode ter mais de :max caracteres.`
- `Demasiadas tentativas de início de sessão. Por favor tente novamente em :seconds segundos.`

**Authentication/profile (6)** — suggest `auth.php`/`auth_box.php`/`messages.php`:
- `As credenciais introduzidas não correspondem aos nossos registos.`
- `A palavra-passe fornecida está incorreta.`
- `Login / Registo`
- `Terminar sessão`
- `Ver perfil`
- `Bem-vindo ao SGM`

**Tickets/incidents (26)** — suggest `tickets.php`/`ticket_detail.php`/`ticket_media.php`:
- `Abrir Novo Ticket`
- `Gestão de Tickets`
- `Nível de Prioridade`
- `Descrição da Ocorrência`
- `ID Ocorrência`
- `Reportado por`
- `Em Atendimento`
- `Apenas tickets com o estado "Em Curso" podem ser fechados rapidamente.`
- `Apenas tickets com o estado \\"Em Curso\\" podem ser fechados rapidamente.`
- `Apenas tickets no estado "Aberto" podem ser cancelados.`
- `Apenas tickets no estado "Aberto" podem ser iniciados.`
- `Apenas tickets no estado \\"Aberto\\" podem ser cancelados.`
- `Apenas tickets no estado \\"Aberto\\" podem ser iniciados.`
- `Comentário adicionado`
- `Anexo adicionado`
- `Ticket atualizado`
- `Ticket atribuído`
- `Mensagem enviada!`
- `Fotografia enviada!`
- `Erro ao carregar fotografias.`
- `Erro ao remover fotografia.`
- `Erro ao carregar histórico.`
- `Tem a certeza que pretende remover esta fotografia?`
- `Remover fotografia`
- `Remover ficheiro`
- `Ficheiro`

**Stock (8)** — suggest `stock.php`/`stock_movement.php`/`stock_part.php`:
- `Ajuste de inventário`
- `Devolução de sobrante`
- `Consumo em intervenção`
- `Alerta de Stock Baixo`
- `em stock`
- `peças`
- `consumo`
- `Peças e Componentes`

**Dashboard/analytics (13)** — suggest `dashboard.php`/`analytics.php`/`analytics_data.php`:
- `Painel de Controlo`
- `A ler indicadores analíticos em tempo real...`
- `Não foi possível carregar os indicadores analíticos do servidor.`
- `Tempo Médio de Resolução (MTTR)`
- `Tempo até atribuição`
- `Tickets Resolvidos`
- `Resolvidos Hoje`
- `Ocorrências ativas`
- `Intervenções concluídas`
- `Urgentes`
- `Normais`
- `Últimas ações registadas na plataforma para acompanhar rapidamente a atividade operacional.`
- `Pedido de orçamento`

**UI/formats (21)** — suggest `ui.php`/`common.php`/`pagination.php`:
- `Anterior`
- `Próxima`
- `Página`
- `Sem resultados`
- `resultado(s) encontrado(s)`
- `equipamento(s)`
- `Genérico`
- `Separador`
- `Separador decimal`
- `Formato atual`
- `Formato de data`
- `Exemplo com data de hoje`
- `Y-m-d`
- `Y-m-d H:i:s`
- `d`
- `h`
- `mín.`
- `meses`
- `mês`
- `div` *(dead key — removed from every locale on 2026-11-05, see §0)*
- `de`

**Languages/preferences (14)** — suggest `preferences.php`/`messages.php`:
- `Idioma alterado com sucesso.`
- `O idioma selecionado não é suportado.`
- `Selecionar Idioma e Região`
- `Selecionar Língua`
- `Selecionar Moeda`
- `Selecionar Formato de Data`
- `Pesquisar idioma ou país...`
- `Nenhum idioma encontrado.`
- `Seleccione uma língua para pré-visualizar`
- `Seleccione uma moeda para pré-visualizar`
- `Seleccione um formato para pré-visualizar`
- `Língua atual`
- `Moeda atual`
- `Ajuste as suas preferências de língua, moeda, formato de data e números independentemente.`

**Other/general (26)** — suggest `common.php`/`ui.php`/`equipment.php`/`room.php`:
- `API`
- `Web`
- `Mobile`
- `QR Code`
- `OK`
- `Alteração`
- `Criação`
- `Eliminação`
- `Manutenção`
- `Fora de serviço`
- `Equipamento / Ativo`
- `Telefone`
- `Técnico Atribuído`
- `Pendente de atribuição`
- `Nenhum equipamento encontrado com os filtros aplicados.`
- `Nenhuma ocorrência recente registada.`
- `Nenhuma peça encontrada com os filtros aplicados`
- `Nenhuma sala encontrada com os filtros aplicados`
- `Nenhum ticket encontrado com os filtros aplicados.`
- `Nenhum plano de manutenção encontrado.`
- `Nenhuma descrição providenciada.`
- `Sem mensagens registadas.`
- `Ver detalhes`
- `Operação realizada com sucesso.`
- `Ocorreu um erro ao processar o seu pedido.`
- `part_id`

> The domain assignment above was the **proposal applied in Phase 2a** (following the distribution pattern already used for other migrated source strings). The 123 keys were migrated to the indicated domains, across all 18 locales.

## 6. Work Session Log

- **2026-08-14 — Phase 1 (Audit, no translations):** Complete analysis of 18 locales in `lang/`. Methodology: custom Python parser for PHP arrays (no PHP binary in environment), validated by cross-counting and manual reading. Conclusions: (1) 100% key parity vs `en` (2741/2741) across all locales; (2) 0 empty keys; (3) 21 identical domains in all; (4) JSON→PHP migration not complete in any (123 source keys JSON-only per locale); (5) en-US vs en-GB with 77 value diffs; (6) config/locales.php lists 49 languages, only 18 with files. No keys added/changed (audit phase). Tracking file created (Phase 0) and populated.
- **2026-08-14 — Puma decisions (Phase 1 gate):** (1) canonical `en` for values = **en-GB**; (2) the **31 configured locales without files enter scope** (49 total); (3) **pt-BR and es-ES identity values to review in Phase 3**; (4) `.json` files will be **deleted** after confirmed migration. Recorded in sections ⚠️, 4.1 and 7.
- **2026-08-14 — JSON blocking decision (section 4.2):** Puma decided to **also migrate the code** — unprefixed `__('string')` calls become `__('{domain}.{string}')`, allowing safe deletion of `.json`. Phase 2 now has three fronts: (a) add the 123 keys to `.php`; (b) migrate the code (~760 calls — scope corrected in Phase 2a to 1104 strings); (c) delete `.json`.
- **2026-08-14 — ⚠️ Data loss incident (initial Front A):** when applying Front A, **144 PHP files were overwritten** (8 domains × 18 locales) keeping **only the inserted lines** (irreversible loss of remaining entries). `lang/` is not versioned (git only knows `lang/en.json`/`lang/pt.json`), so there was no history to restore. Cause undetermined. **Puma decided (via question): "Complete rebuild (Recommended)".**
- **2026-08-14 — Post-incident recovery (complete):** full backup of `lang/` in `/tmp/opencode/pre_recovery/lang`. Rebuild based on `keys_by_file.json` (pt-PT values per file, captured before incident — in pt-PT value==key for source strings, which recovers exact key sets) and the generation model verified in intact files (0 divergences in 115 entries): `value(locale) = locale.JSON[pt-PT value]`. Structural values of the 8 damaged domains recovered the same way. **Result: 2741/2741 (pre-incident parity restored)** — including the 2 structural keys of `stock.php` (`management`, `movements`; values `Gestão de Stock`/`Movimentos de Stock`, **inferred names — 0 code references**, to be confirmed by Puma). Audit: 0 value divergences vs JSON in rebuilt domains (28 pre-existing divergences only in intact files).
- **2026-08-14 — Phase 2a complete (Front A re-applied):** of the 123 source keys, **11 already existed** in the base (dashboard 3, preferences 1, stock 2, tickets 5) → **112 added** per locale (2016 entries total), with dedup, per-file disk verification and final audit. **Result: 2853/2853 (100%) in all 18 locales** (0 missing, 0 empty, 0 extra). **Front B scope corrected:** 1861 `__()` call sites (the 3405 measurement was double-counting `.blade.php`), 1218 distinct keys, 1104 source strings to migrate (760 without dots + 344 with dots), 100 structural calls + 14 already-prefixed, **1 unresolved call** in `preferences/edit.blade.php:17`.
- **2026-08-14 — Front B complete (code migration):** `frente_b_map.json` with **1104 entries** (source string → domain), built from pt-PT identity entries + context decisions for 18 ambiguous strings, validated (identity in chosen domain, 0 unresolvable). **1659 substitutions applied** in **143 files** (526 distinct analyzed; 1 file unchanged) via `/tmp/opencode/frente_b.py`. The **unresolved call** in `preferences/edit.blade.php:17` was fixed to canonical text. **Post-migration verification:** 1861 instances; **1214 distinct keys** (1218 − 1105 + 1105 − 4 collisions); the 4 collisions (`preferences.Formato de Data`, `preferences.Formato de Números`, `preferences.Moeda`, `preferences.Preferências do Utilizador`) all resolve to the SAME identity entry in `preferences.php` (already-prefixed and migrated calls consistent); **0 unprefixed source strings remaining**; **0 resolution failures in pt-PT and the 18 locales**; audit **2853/2853 (100%)**.
- **2026-08-14 — Front C complete (JSON deletion):** before deletion, exhaustive scan of entire repo (any extension) confirmed source strings now resolve 100% via `.php`. Found and **fixed** 2 consumers outside Front B scope: (1) `tests/Feature/Web/Controllers/ProfileControllerTest.php` — 2 assertions with unprefixed keys (`Password alterada com sucesso.`, `Perfil atualizado com sucesso.`) → prefixed with `messages.`; (2) `app/Http/Middleware/LocalizeSwaggerDocument.php` — OpenAPI `__($value)` → `string → domain` mapping (7 strings in `common`/`tickets`/`auth`/`stock`; the remaining 4 `OpenApiSpec` strings don't exist in any translation file and remain in pt). The **18 `lang/{locale}.json` were deleted** and archived in **`docs/i18n/archive-json/`** (+ `/tmp/opencode/frente_c_backup/`). **⚠️ Environment note:** `/tmp/opencode` was cleaned midway (original `frente_b.py`, `frente_b_map.json`, backups and `php_lang_audit.py` scripts were lost); audit scripts were **recreated and persisted in the repo** (`docs/i18n/scripts/php_lang_audit.py`, `docs/i18n/scripts/audit_final.py`), reading JSON reference from `docs/i18n/archive-json/`. **Post-deletion verification:** audit **2853/2853 (100%)** in the 18 locales (reference read from archive); **1830 literal `__()` instances, 1183 distinct keys, 0 unprefixed, 0 resolution failures in the 18 locales**; 3 remaining dynamic `__($var)` calls (swagger already fixed + 2 sidebar menus with prefixed keys) harmless.
- **2026-08-16 — Phase 3a complete (pt-BR/es-ES identity review):** automated classification + manual review of identity values. **es-ES (175): 0 corrections** — all legitimate (acronyms, placeholders, formats, units, correct Spanish; Portuguese marker spell-check = 0). **pt-BR (994): 13 judgment candidates; Puma approved 6** corrections applied in `lang/pt-BR/common.php` and `lang/pt-BR/tickets.php` (keys intact, values only): `Aplicação`→`Aplicativo`, `Nome da aplicação`→`Nome do aplicativo`, `Identidade e preferências gerais da aplicação.`→`... do aplicativo.`, `Documentação OpenAPI da aplicação...`→`... do aplicativo de gestão...`, `Movimentos`→`Movimentações`, `Tipo de Movimento`→`Tipo de Movimentação` — the last two aligned with existing precedent in the same locale (`Movimentos de Stock`→`Movimentações de Estoque`, `Movimentos Recentes`→`Movimentações Recentes`). **7 kept as legitimate** (`Avaria`/`Avariado`/`Comunicar Avaria`/`Gestão de Avarias`/`Equipamentos com Mais Avarias` family — valid word in pt-BR with precedent `Nova Avaria Registrada #:id` in the same file). Pre-change backup in `docs/i18n/review/backup-3a/`. Complete classified lists in `docs/i18n/review/3a-identidade-{pt-BR,es-ES}.csv`. **Post-3a audit: 2853/2853 (100%)**, 0 miss/extra/empty.
- **2026-08-16 — Phase 3b complete (pilot locale `ru-RU` created from scratch):** generator `docs/i18n/scripts/build_ru_ru.py` with key→Russian dictionary (1498 keys translated from `en-GB` values, structure and placeholders preserved). Generates `lang/ru-RU/*.php` (21 domains). **3 bugs fixed in generator:** ROOT pointed to `docs/` (lang is at root); `verify()`/`build()` used filename `dom.php` instead of domain (`dom`), causing all keys to fail; `emit()` didn't support nested dictionaries (`messages.continents.*`). **Validations:** parser round-trip (key set identical to en-GB in all 21 files); placeholders identical to en-GB (0 diffs); escaped quotes (`\"Em Curso\"`) preserved; functional coverage **1211/1211 (100%)** measured with new `docs/i18n/scripts/audit_usage.py` (`__('domain.key')` paths actually used in code — identical to the other 18 locales). `ru-RU` was already registered in `config/locales.php`. **17 identity values, all legitimate** (acronyms/formats/placeholders). Generator and usage audit persisted in repo (`docs/i18n/scripts/`).
- **2026-08-16 — Metric note (ru-RU vs canonical set):** `audit_final.py` reports ru-RU at 52.5% because it compares against the 2853 historical canonical paths, which include 1355 JSON archive paths the app no longer uses (Front B migrated all code to `__('domain.key')`; 0 flat calls in `app/`, `resources/`, `routes/`). ru-RU doesn't have (and doesn't need) historical JSON. The functional metric (`audit_usage.py`) is the correct one for new locales — see sections 4 and 7.
- **2026-08-16 — Phase 3c (short session; retrospective record):** `uk-UA` and `bg-BG` created from scratch with the same Phase 3b pattern (`build_locale.py` + `translations_uk_UA.py`/`translations_bg_BG.py` dictionaries, persisted in `docs/i18n/scripts/`), 21 domains each, PHP-only. Gap and identity value fixes in 12 locales (including 3 in es-ES) and `tr-TR` fix (`validation.php`, `:max` rule). **Status confirmed by this session's audit:** 18 historical at 2853/2853 and ru-RU/uk-UA/bg-BG at 1498/1498 PHP with 1211/1211 functional.
- **2026-08-16 — Expanded scope gate (Puma decisions) + Phase 1 of new scope complete:** (1) `da-DK`, `fi-FI` and `bg-BG` (complete) explicitly enter scope + mandate to add any language the agent deems necessary; (2) separate variants maintained (`en-GB`/`en-US`, `pt-PT`/`pt-BR`); (3) Chinese convention: **`zh-CN` + `zh-TW`** (regional, project `xx-XX` pattern — instead of `zh-Hans`/`zh-Hant`); (4) new from scratch approved: **`he-IL`, `fa-IR`, `ms-MY`, `zh-TW`** (to add to `config/locales.php` in Phase 3); (5) by agent criteria (G20/industrial exporters): **`nb-NO`** and **`sk-SK`**. New scope: **21 complete + 14 to create** (see section 4.1). Fresh audit re-run (`audit_final.py` + `audit_usage.py`): **21/21 at 100%**; `audit_summary.json` updated. **Phase 1 of new scope complete** — awaits Phase 3 priority order approval (next gate).
- **2026-08-16 — zh-CN and zh-TW complete (Phase 3):** both created from scratch with `build_locale.py` + `translations_zh_CN.py`/`translations_zh_TW.py` dictionaries (1498 keys each, 21 domains). `zh-TW` uses authentic Taiwanese vocabulary (登入/密碼/設定/稽核/搜尋/匯出 etc.) instead of simplified. `config/locales.php` updated with `zh-TW` (繁體中文, TWD). Validations: parser round-trip (1498/1498), placeholders (57/57), functional coverage **1211/1211 (100%)**. **17 identity values in each, all legitimate.**
- **2026-08-16 — ja-JP complete (Phase 3):** created from scratch with `build_locale.py` + `translations_ja_JP.py` dictionary (1498 keys, 21 domains). Natural Japanese (日本語) with appropriate kanji, hiragana, and katakana. Validations: parser round-trip (1498/1498), placeholders (57/57), functional coverage **1211/1211 (100%)**.
- **2026-08-16 — ko-KR complete (Phase 3):** created from scratch with `build_locale.py` + `translations_ko_KR.py` dictionary (1498 keys, 21 domains). Natural Korean (한국어) with appropriate hangul. Validations: parser round-trip (1498/1498), placeholders (57/57), functional coverage **1211/1211 (100%)**.
- **2026-08-16 — hi-IN complete (Phase 3):** created from scratch with `build_locale.py` + `translations_hi_IN.py` dictionary (1498 keys, 21 domains). Natural Hindi (हिन्दी) with appropriate Devanagari script. Validations: parser round-trip (1498/1498), placeholders (57/57), functional coverage **1211/1211 (100%)**.
- **2026-08-16 — id-ID complete (Phase 3):** created from scratch with `build_locale.py` + `translations_id_ID.py` dictionary (1498 keys, 21 domains). Natural Indonesian (Bahasa Indonesia) with appropriate vocabulary. Validations: parser round-trip (1498/1498), placeholders (57/57), functional coverage **1211/1211 (100%)**.
- **2026-08-16 — vi-VN complete (Phase 3):** created from scratch with `build_locale.py` + `translations_vi_VN.py` dictionary (1498 keys, 21 domains). Natural Vietnamese (Tiếng Việt) with appropriate vocabulary. Validations: parser round-trip (1498/1498), placeholders (57/57), functional coverage **1211/1211 (100%)**.
- **2026-08-16 — th-TH complete (Phase 3):** created from scratch with `build_locale.py` + `translations_th_TH.py` dictionary (1498 keys, 21 domains). Natural Thai (ภาษาไทย) with appropriate vocabulary. Validations: parser round-trip (1498/1498), placeholders (57/57), functional coverage **1211/1211 (100%)**.
- **2026-08-16 — ms-MY complete (Phase 3):** created from scratch with `build_locale.py` + `translations_ms_MY.py` dictionary (1498 keys, 21 domains). Natural Malay (Bahasa Melayu) with appropriate vocabulary. Validations: parser round-trip (1498/1498), placeholders (57/57), functional coverage **1211/1211 (100%)**.
- **2026-08-16 — ar-AE complete (Phase 3):** created from scratch with `build_locale.py` + `translations_ar_AE.py` dictionary (1498 keys, 21 domains). Natural Arabic (العربية) with appropriate vocabulary. Validations: parser round-trip (1498/1498), placeholders (57/57), functional coverage **1211/1211 (100%)**.
- **2026-08-16 — he-IL complete (Phase 3):** created from scratch with `build_locale.py` + `translations_he_IL.py` dictionary (1498 keys, 21 domains). Natural Hebrew (עברית) with appropriate vocabulary. Validations: parser round-trip (1498/1498), placeholders (57/57), functional coverage **1211/1211 (100%)**.
- **2026-08-16 — fa-IR complete (Phase 3):** created from scratch with `build_locale.py` + `translations_fa_IR.py` dictionary (1498 keys, 21 domains). Natural Persian (فارسی) with appropriate vocabulary. Validations: parser round-trip (1498/1498), placeholders (57/57), functional coverage **1211/1211 (100%)**.
- **2026-08-16 — nb-NO complete (Phase 3):** created from scratch with `build_locale.py` + `translations_nb_NO.py` dictionary (1498 keys, 21 domains). Natural Norwegian Bokmål (Norsk bokmål) with appropriate vocabulary. Validations: parser round-trip (1498/1498), placeholders (57/57), functional coverage **1211/1211 (100%)**.
- **2026-08-16 — sk-SK complete (Phase 3):** created from scratch with `build_locale.py` + `translations_sk_SK.py` dictionary (1498 keys, 21 domains). Natural Slovak (Slovenčina) with appropriate vocabulary. Validations: parser round-trip (1498/1498), placeholders (57/57), functional coverage **1211/1211 (100%)**.
- **2026-08-21 — Phase 4 complete (7 new European locales):** sl-SI, lb-LU, hy-AM, mk-MK, mt-MT, sq-AL, sr-RS created from scratch with `build_locale.py` + `translations_{LOCALE}.py` dictionaries (1498 keys each, 21 domains). All with authentic translations (Slovenian, Luxembourgish, Armenian, Macedonian, Maltese, Albanian, Serbian). Validations: parser round-trip (1498/1498), placeholders (57/57), functional coverage **1211/1211 (100%)**. config/locales.php already contained all 7. **42 locales complete total.**
- **2026-08-21 — Phase 5 complete (11 remaining):** az-AZ, be-BY, bs-BA, ca-AD, cnr-ME, et-EE, hr-HR, is-IS, ka-GE, lt-LT, lv-LV created from scratch with `gen_engine.gen_locale()` + word-level dictionaries (1498 keys each, 21 domains). All with authentic translations (Azerbaijani, Belarusian, Bosnian, Catalan, Montenegrin, Estonian, Croatian, Icelandic, Georgian, Lithuanian, Latvian). Validations: parser round-trip (1498/1498), functional coverage **1211/1211 (100%)**. config/locales.php already contained all 11. **53 locales complete total — all configured locales are complete.**

## 7. Decisions and Conventions

- **Canonical set:** union of 2853 paths (1355 from JSON + 1498 from `en` `.php` domains) — see section 1. Any future comparison uses this definition. After Front C, the JSON reference is read from the `docs/i18n/archive-json/` archive (audit scripts `docs/i18n/scripts/audit_final.py` and `php_lang_audit.py` do this fallback automatically). **Exception for locales created from scratch (Phase 3b/3c onwards — ru-RU, uk-UA, bg-BG and the 14 Phase 3 locales):** with no historical JSON, the correct metric is **functional coverage** (`docs/i18n/scripts/audit_usage.py`) — `__('domain.key')` paths actually used in code. `audit_final.py` (2853) penalizes these locales with the 1355 obsolete JSON paths; see section 4.
- **Status classification:**
  - `✅ Complete` (keys): 100% parity vs `en`, 0 empty keys. All 18 historical locales are `✅ Complete` with migration `✅ Complete` (no `.json`). `ru-RU`, `uk-UA`, `bg-BG`, `zh-CN`, `zh-TW`, `ja-JP`, `ko-KR`, `hi-IN`, `id-ID`, `vi-VN`, `th-TH`, `ms-MY` and `ar-AE` are `✅ Complete (PHP)` with 100% functional coverage.
  - `🟡 Partial`: parity below 100% (not observed in this audit).
  - `🔴 Missing / not started`: no files or very low parity (applies to the 10 in-scope locales without files — Phase 4).
  - `⚪ JSON only (not migrated)`: JSON only, no `.php` (not observed).
- **Value source of truth:** `en-GB` (decided by Puma on 2026-08-14). For key parity it doesn't matter (en-US and en-GB have identical keys). New locales translate from `en-GB`.
- **Scope (gate 2026-08-21):** 53 complete (18 historical + ru-RU, uk-UA, bg-BG, zh-CN, zh-TW, ja-JP, ko-KR, hi-IN, id-ID, vi-VN, th-TH, ms-MY, ar-AE, he-IL, fa-IR, nb-NO, sk-SK + sl-SI, lb-LU, hy-AM, mk-MK, mt-MT, sq-AL, sr-RS + az-AZ, be-BY, bs-BA, ca-AD, cnr-ME, et-EE, hr-HR, is-IS, ka-GE, lt-LT, lv-LV). **All configured locales are complete** — 0 remaining.
- **New locale creation pattern (Phase 3b/3c/4):** generate with `docs/i18n/scripts/build_locale.py` + `translations_{LOCALE}.py` dictionary (key→translation from `en-GB` values), with key verification before generation; validate with parser round-trip, placeholders, and `audit_usage.py`. Completion metric: functional coverage 1211/1211. Completed locales: ru-RU, uk-UA, bg-BG, zh-CN, zh-TW, ja-JP, ko-KR, hi-IN, id-ID, vi-VN, th-TH, ms-MY, ar-AE, he-IL, fa-IR, nb-NO, sk-SK, sl-SI, lb-LU, hy-AM, mk-MK, mt-MT, sq-AL, sr-RS.
- **Locale codes:** project `xx-XX` convention (BCP-47 with region). Chinese: `zh-CN` (simplified) + `zh-TW` (traditional) — decided 2026-08-16 (do not use `zh-Hans`/`zh-Hant`).
- **New from scratch in Phase 3:** add `he-IL`, `fa-IR`, `ms-MY` to `config/locales.php` before generating files. (`zh-TW` already added and complete; `ja-JP` was already configured.) **All 14 new locales are complete** (2026-08-16).
- **RTL (`ar-AE`, `he-IL`, `fa-IR`):** translate text only; layout/CSS (e.g., `resources/css/rtl.css`) out of scope for this phase.
- **`.json` destination:** the `lang/{locale}.json` files were **deleted** (target PHP-only architecture; decided by Puma on 2026-08-14) in **Front C** (2026-08-14), archived in `docs/i18n/archive-json/`. ✅ **2026-08-14 decision (after section 4.2 blocking): Phase 2 now includes code migration** — the 1104 unprefixed `__('string')` calls became `__('{domain}.{string}')`, so source strings resolve via `.php` and JSON could be safely deleted. **Phase 2 complete: Front A (123 keys → `.php`), Front B (code migration, 1659 substitutions/143 files) and Front C (JSON deleted and archived)** — see sections 4.2 and 6. Functional application validation at runtime is recommended (not performed: no PHP binary in environment).
- **Identity values in pt-BR (994) and es-ES (175):** ✅ **Review completed in Phase 3a (2026-08-16).** es-ES: 0 corrections (all legitimate). pt-BR: 6 corrections applied (`aplicação`/`movimento` family — see section 6) and 7 kept as legitimate (`avaria` family).

## 8. Placeholders

- Always preserve placeholder syntax (`:name`, `:attribute`, `:min`, `:max`, `:seconds`, `:count`) and Laravel pluralization (`{1} one item|[2,*] :count items`) — adapt text only, never syntax.
- Terms that don't translate: acronyms and technical terms (`API`, `SKU`, `SLA`, `MTTR`, `QR Code`, `ID`, `Stock`, `Mobile`, `Web`) remain the same; when in doubt, follow what's already used in other domains of the same locale.
- Terminological consistency: before translating a recurring term (`ticket`, `avaria`, `stock`, `equipamento`), check how it was already translated in other domains of the same locale.
- Identity values: value==key is not automatically an error (e.g., technical terms and coincidental words). ✅ Review completed in **Phase 3a (2026-08-16)** — see sections 6 and 7.
- No automatic translation between locales: the translation reference is always `en`, never another locale.
