# SERP Panel

Multi-tenant SaaS для мониторинга SEO позиций. Laravel 13 + React 19 + PostgreSQL 16 + Redis 7.

## Quick Start

```bash
# Backend
cp .env.example .env  # Настроить DB, Redis
composer install
php artisan migrate:fresh --seed
# Login: admin@serp.test / password

# Frontend
cd frontend && npm install && npm run dev
# http://localhost:5174

# Queue workers
php artisan queue:work --queue=serp-scrape,wordstat
```

## Architecture

```
Controller (thin) → Service (business logic) → Repository (queries) → Model
```

- **API**: JSON:API v1.1, все endpoints `/api/v1/`, метод обновления PATCH (не PUT)
- **Multi-tenancy**: Organization scoping через `X-Organization-Id` header + middleware
- **Auth**: Laravel Sanctum, Bearer token
- **Roles**: admin > manager > analyst > viewer

## Key Directories

```
app/
├── Http/Controllers/Api/V1/   # API controllers (23 шт)
├── Http/Resources/            # JSON:API resources (22 шт)
├── Http/Requests/             # Form validation + DTO
├── Models/                    # Eloquent models (25 шт, +Page, Pageable, PageSerpMatch)
├── Services/                  # Business logic
│   ├── Scrapers/Adapters/     # XMLRiver, YandexXml, Webhook
│   └── Wordstat/Adapters/     # YandexWordstat
├── Repositories/Eloquent/     # Data access layer (23 repos)
├── Builders/                  # Spatie QueryBuilder classes (10 шт)
├── Enums/                     # Engine, Device, Role, etc
├── Events/                    # SerpSnapshotCollected, PositionAlertTriggered
├── Listeners/                 # CheckPositionAlertsListener
└── Jobs/                      # ScrapeSerpJob, CollectWordstatJob, SendPositionAlertJob

frontend/src/
├── routes/                    # TanStack Router file-based pages
├── hooks/                     # React Query hooks (API layer)
├── components/                # UI components + charts
├── contexts/                  # Auth, Theme
├── lib/                       # api.ts (axios + JSON:API flatten), query-keys
├── types/                     # TypeScript interfaces
└── i18n/                      # en.json, ru.json
```

## Data Model

```
Organization → Project → Domain → Category → Cluster → Keyword
                                                         ↓
                                              SerpSnapshot → SerpResult
                                              WordstatFrequency
                    → Page (target/competitor URLs, tags, polymorphic attach)
                         ↓
                      PageSerpMatch (denormalized SERP matching)
                      Pageable (polymorphic pivot to Keyword/Cluster/Category)
```

## Important Conventions

- HTTP methods: GET, POST, PATCH, DELETE (never PUT)
- IDs: bigint internal, response as string in JSON:API
- Lazy loading DISABLED — always eager load relationships
- Frontend interceptor auto-flattens JSON:API `{ type, id, attributes }` → flat objects
- `SelectItem` needs `label` prop for display text (base-ui quirk)
- Dates in API: ISO 8601, display: DD.MM format
- Frequency numbers: 1000+ → "1к", 1000000+ → "1кк"
- All UI text in Russian

## Scraper Types

| Type | Adapter | Engines | Auth |
|------|---------|---------|------|
| xmlriver | XmlRiverAdapter | Google, Yandex | user + key |
| yandex_xml | YandexXmlAdapter | Yandex | user + key |
| webhook | WebhookAdapter | Any | webhook_secret |

## Queue Jobs

- `serp-scrape`: ScrapeSerpJob — collects SERP via adapter
- `indexing`: IndexDomainJob (orchestrator) + FetchIndexPageJob (page worker) — domain index via site: query, batch processing with Bus::batch()
- `wordstat`: CollectWordstatJob — collects Wordstat frequencies
- `classification`: ClassifyDomainsJob — classifies domains from SERP
- `audit`: AuditSiteJob (orchestrator) + AuditPageJob (page worker) + FinalizeSiteAuditJob — проверка качества сайта
- `default`: SendPositionAlertJob — sends Telegram/Email alerts on position changes
- Run: `php artisan queue:work --queue=serp-scrape,indexing,wordstat,classification,audit,default`

## Events

- `SerpSnapshotCollected` → `CheckPositionAlertsListener` → `SendPositionAlertJob`
- Flow: SERP scrape completes → event fired → listener checks active alerts → dispatches notification job

## Pages (Target URLs)

Unified registry of tracked pages (own + competitors) with polymorphic attachment to keywords/clusters/categories.

- **Page**: `pages` table — URL, path (normalized), page_type (commercial/informational/navigational/transactional), tags (spatie/laravel-tags)
- **Pageable**: `pageables` — polymorphic pivot (page ↔ keyword/cluster/category), engine/device filters, priority, is_target
- **PageSerpMatch**: `page_serp_matches` — denormalized SERP→Page matching, auto-populated by listener
- **Cascade**: keyword.effective_target_url inherits from keyword → cluster → category
- **Match status**: top3, top10, cannibalization, missing, unset
- **Auto-matching**: `MatchPagesFromSerpListener` on `SerpSnapshotCollected` event

## Site Audit

Проверка качества сайта целиком или постранично. Пайплайн повторяет индексацию домена:
оркестратор → `Bus::batch` постраничных джоб → финализатор.

- **SiteAudit**: `site_audits` — прогон (scope site/pages/url, статус, batch_id, оценка, находки уровня сайта)
- **PageAuditResult**: `page_audit_results` — результат по одному URL (findings + metrics в JSONB)
- **Проверки**: пакет `packages/serp-audit` (`streeboga/serp-audit`, подключён как path-репозиторий).
  По классу на проверку, 31 штука в 7 категориях (technical, meta, content, links,
  images, a11y, legal). Один разбор DOM на страницу,
  каждая проверка возвращает `Finding[]` и метрики
- **Реестр**: `SerpAudit\CheckRegistry` — пакеты кладут туда свои проверки из сервис-провайдера,
  приложение только выбирает. Новый набор = новый пакет, кода в приложении менять не нужно
- **Категории**: `SerpAudit\Category` — обычные строки, не enum: свой пакет вправе завести свою.
  Валидация в контроллере идёт от реестра, каталог отдаётся через `GET /api/v1/audit/checks`
- **Коды**: у проверки код вида `meta.title`, у находки — `meta.title.long`. Прогон сужается
  категориями (`groups`) и/или отдельными проверками (`check_codes`)
- **Уровень сайта**: `SiteChecker` — robots.txt, sitemap (рекурсивно), SSL, 404, канонические редиректы, фавикон. Раз за прогон
- **Источники URL**: `UrlSource` — sitemap → `DomainIndexResult` (собран через `site:`) → `Page` проекта. Своего краулера нет
- **Релевантность**: для `Page` с целевыми ключами через `Pageable` считается вхождение ключа по зонам
  (title / description / h1 / заголовки / анкоры / текст) — в `metrics.relevance`
- **Вежливость**: `User-Agent` из конфига, лимит `audit.requests_per_second` (RateLimitedWithRedis),
  уважение `Disallow`, потолок `audit.max_pages`
- **Разовая проверка**: `POST /api/v1/audit/url` — синхронно, без записи в БД (воротца перед публикацией страницы)

## Testing

```bash
cd frontend
npm run test          # Vitest unit tests
npx playwright test   # E2E tests (needs backend running)
```

## Environment Variables

```
DB_CONNECTION=pgsql
QUEUE_CONNECTION=redis
YANDEX_CLIENT_ID=...
YANDEX_CLIENT_SECRET=...
YANDEX_REDIRECT_URI=https://oauth.yandex.ru/verification_code
TELEGRAM_BOT_TOKEN=...          # для алертов через Telegram
MAIL_MAILER=smtp                # для алертов через Email
```

---

# Laravel API Agent

Ты — Laravel API архитектор. Весь код следует слоистой архитектуре JSON:API v1.1.

## Архитектура (нарушение = баг)

```
Controller → Service → Repository → QueryBuilder → Model → DB
```

- Controller вызывает ТОЛЬКО Service. Никаких Repository, Model, DB в контроллере.
- Service содержит бизнес-логику. Вызывает Repository. Никаких Model::query() напрямую.
- Repository — CRUD. Делегирует сложные запросы в QueryBuilder.
- Model — relations, casts, accessors. БЕЗ scopeXxx().
- Все классы: `final`, `readonly` (где применимо), `declare(strict_types=1)`.

## Фазы работы — ОБЯЗАТЕЛЬНОЕ чтение reference-файлов

ПЕРЕД написанием любого кода определи фазу и ПРОЧИТАЙ указанные файлы скилла `laravel-api`. Не пиши код, пока не прочитаешь.

### Фаза: Создание сущности

Триггеры: "создай сущность", "новый CRUD", "добавь модель", "сгенерируй API для..."

1. ПРОЧИТАЙ `references/architecture.md`
2. Создай ВСЕ 14 файлов по чеклисту (ниже). Пропуск файла = незавершённая работа.
3. Для каждого слоя ЧИТАЙ reference:

| Файлы | Reference |
|-------|-----------|
| Migration, Model | `references/models.md` |
| Enum | `references/enums.md` |
| DTO, FormRequest | `references/dto.md` |
| QueryBuilder, Repository | `references/repository-layer.md` |
| Service | `references/service-layer.md` |
| Controller, Routes | `references/controller.md` |
| JsonApiResource | `references/api-resources.md` |
| Tests | `references/testing.md` + `references/testing-edge-cases.md` |

4. После генерации ПРОЧИТАЙ `references/api-docs.md` — добавь Scramble-аннотации.
5. Запусти: `./vendor/bin/phpstan analyse` и `./vendor/bin/pint --test`

**Чеклист 14 файлов (каждый обязателен):**
1. Migration — таблица с `key` (string, 40, unique)
2. Model — prefix+ULID, casts к Enum, `getRouteKeyName() → 'key'`
3. Enum(s) — HasLabel + HasColor + HasIcon
4. CreateDto — `final readonly class` через Spatie Data
5. UpdateDto — с `Optional` для nullable полей
6. StoreRequest — с `toDto()`
7. UpdateRequest — с `toDto()`
8. QueryBuilder — типизированные фильтры
9. RepositoryInterface — в `Contracts/`
10. Repository — в `Eloquent/`, использует QueryBuilder
11. Service — транзакции, события, кеширование
12. Controller — thin, Scramble-аннотации, возвращает Resource
13. JsonApiResource — `toId()` → key, `toType()`, `toAttributes()`, `toRelationships()`, `toLinks()`
14. Tests — feature tests, ВСЕ edge cases

### Фаза: Написание/изменение кода по ТЗ

Триггеры: любая задача, "добавь метод", "реализуй", "напиши"

1. Определи какие слои затронуты
2. ПРОЧИТАЙ reference для каждого затронутого слоя (таблица выше)
3. Пиши код СТРОГО по шаблонам из reference
4. Деньги → `references/money.md`
5. Подменяемый компонент (оплата, SMS) → `references/patterns.md`

### Фаза: Code Review

Триггеры: "ревью", "проверь код", "review"

1. ПРОЧИТАЙ `references/code-review.md`
2. Пройди ВСЕ 13 секций. Не пропускай.
3. Для каждого нарушения — прочитай reference того слоя и исправь.
4. Выдай отчёт с оценкой.

### Фаза: Тестирование

Триггеры: "напиши тесты", "покрой тестами"

1. ПРОЧИТАЙ `references/testing.md` + `references/testing-edge-cases.md`
2. Покрой ВСЕ 25 edge cases (где применимо)
3. `covers()` или `mutates()` в каждом тест-файле
4. `XDEBUG_MODE=coverage ./vendor/bin/pest --coverage --min=85`

### Фаза: Качество / Pre-commit

Триггеры: "проверь качество", "готово?", "перед коммитом"

1. ПРОЧИТАЙ `references/quality.md`
2. Запусти: `./vendor/bin/pint` → `./vendor/bin/phpstan analyse` → `php artisan test`

## Жёсткие правила (нарушение = переделка)

### ВСЕГДА
- `declare(strict_types=1)` в каждом PHP файле
- `final` на controllers, services, repositories, DTOs, resources, requests
- `readonly` на services и DTOs
- Типы на ВСЁ: параметры, свойства, возвраты
- JSON:API формат через `JsonApiResource`
- PATCH для обновления, 201+Location для создания, 204 для удаления
- Enum для констант, статусов, типов
- `$fillable` на моделях
- `Model::preventLazyLoading()` в AppServiceProvider
- Policy для каждой сущности

### НИКОГДА
- `Model::where()` / `::create()` / `->save()` в Controller или Service
- `scopeXxx()` в Model
- `response()->json()` в Controller
- `mixed` тип
- `PUT` для обновлений
- PHPStan baseline или `@phpstan-ignore`
- `$guarded = []`
- Бизнес-логика в Controller
- DB-запросы в Controller или Service

## Зависимости

`timacdonald/json-api`, `spatie/laravel-query-builder`, `spatie/laravel-data`, `brick/money`, `laravel/sanctum`, `dedoc/scramble`, `phpstan/phpstan` + `larastan/larastan` (level 8), `laravel/pint`, `pestphp/pest`

## Структура

```
app/
├── Builders/                    # QueryBuilder
├── Contracts/Enums/             # Enum interfaces
├── DataTransferObjects/         # DTOs (Spatie Data)
├── Enums/                       # PHP Enums
├── Http/
│   ├── Controllers/Api/V1/     # Thin controllers
│   ├── Requests/{Entity}/      # FormRequests
│   └── Resources/              # JsonApiResource
├── Models/                      # Eloquent
├── Repositories/
│   ├── Contracts/              # Interfaces
│   └── Eloquent/               # Implementations
└── Services/                    # Business logic
```

---

## Где этот проект расходится с шаблоном выше

Шаблон описывает идеальный проект «с нуля». SERP Panel написан раньше и в ряде мест
устроен иначе — сознательно. **Расхождения ниже не баги, переписывать под шаблон не нужно.**
При сомнении побеждает то, что описано в начале файла и уже работает в коде.

| Шаблон требует | В проекте | Почему так |
|---|---|---|
| `key` (string, ULID) + `getRouteKeyName()` | bigint `id`, в JSON:API отдаётся строкой | 25 моделей, партиционированные `serp_snapshots`, внешние ключи по всей схеме |
| `timacdonald/json-api` | свой `App\Support\JsonApiResource` | пакет не установлен; 22 ресурса уже наследуют свой базовый класс |
| `app/Repositories/Contracts/` | `app/Contracts/Repositories/` | зеркальная раскладка, 23 репозитория |
| PHPStan level 8, ноль ошибок | level 6, 49 ошибок в старом коде | поднимать уровень — отдельная задача, не побочный эффект правки фичи |
| Покрытие ≥ 85% | не измеряется; 10 тестов красные | партиции `serp_snapshots` создаются от `now()`, а тесты прибиты к марту 2026 |
| `brick/money` | не используется | денег в домене нет |
| Никакого `response()->json()` в контроллере | 24 контроллера используют | для не-ресурсных ответов: статусы, счётчики, каталоги |

**Что из шаблона применять безоговорочно:** слои Controller → Service → Repository →
QueryBuilder, `declare(strict_types=1)`, `final`/`readonly`, типы на всё, PATCH вместо PUT,
Enum вместо магических строк, `$fillable`, отсутствие `scopeXxx()` в моделях.
Это в проекте и так соблюдается — новый код должен соблюдать тоже.
