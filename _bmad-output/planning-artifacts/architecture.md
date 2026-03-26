---
stepsCompleted: [1, 2, 3, 4, 5, 6, 7, 8]
workflowType: 'architecture'
lastStep: 8
status: 'complete'
completedAt: '2026-03-26'
project_name: 'serp-panel'
user_name: 'K.mazurov'
date: '2026-03-26'
inputDocuments:
  - _bmad-output/planning-artifacts/prd.md
  - docs/plans/2026-03-25-seo-monitor-design.md
  - docs/plans/2026-03-25-seo-monitor-implementation.md
---

# Architecture Decision Document — SERP Panel

_Техническая архитектура для мульти-тенантной SaaS-платформы мониторинга SEO-позиций._

## Project Context Analysis

### Requirements Overview

**Functional Requirements:** 44 FR по 9 capability areas — аутентификация, управление проектами, SERP-мониторинг, Wordstat-аналитика, автоклассификация, скрейперы, расписания, алерты, экспорт.

**Non-Functional Requirements:** 23 NFR по 5 категориям — performance (P95 < 500ms), security (Sanctum + tenant isolation), scalability (100k ключевиков/день, 300M записей/месяц), reliability (3 retry, idempotent scheduler), observability (job metrics, Horizon dashboard).

**Scale & Complexity:**
- Primary domain: Full-stack web application (API + SPA)
- Complexity level: High (масштаб данных, внешние зависимости, мульти-тенантность)
- Estimated architectural components: 15+ (auth, projects, keywords, SERP, Wordstat, classification, scrapers, schedules, jobs, alerts, export, dashboard, billing, admin, frontend)

### Technical Constraints & Dependencies

- PostgreSQL 16 — партиционирование serp_snapshots и serp_results помесячно
- Redis 7 — очереди (Horizon), кеш, сессии
- Внешние API: XMLRiver, Yandex Wordstat, Telegram Bot API
- Rate limiting скрейперов: каждый провайдер имеет свои лимиты
- Anti-bot защита Yandex (captcha/block) при скрейпинге

### Cross-Cutting Concerns

- **Multi-tenancy:** Organization-scoped data isolation на всех уровнях
- **RBAC:** 4 роли с разными permissions на каждый endpoint
- **Rate limiting:** API rate limiting (60 req/min) + scraper rate limiting (per-adapter)
- **Data retention:** Автоочистка старых партиций
- **Error handling:** Unified JSON:API error format
- **Logging:** Structured logging без PII

## Starter Template Evaluation

### Primary Technology Domain

**Backend:** Laravel 13 (PHP 8.4) — проект уже scaffolded и имеет 19 коммитов. Не нужен стартер.
**Frontend:** React 18 + Vite — проект уже scaffolded с TanStack Router/Query/Table + shadcn/ui.

### Selected Approach: Brownfield Refactoring

Проект существует. Стартер не применим. Архитектура определяет целевую структуру, к которой код должен быть приведён.

**Текущие зависимости (установлены):**
- `laravel/framework: ^13.0`
- `laravel/horizon`
- `laravel/sanctum`
- `spatie/laravel-query-builder`
- `timacdonald/json-api`

**Нужно добавить (по Laravel API skill):**
- `spatie/laravel-data` — DTOs
- `dedoc/scramble` — Auto OpenAPI docs
- `larastan/larastan` — Static analysis level 8
- `laravel/pint` — PSR-12 code style
- `pestphp/pest` — Testing framework (уже есть)

## Core Architectural Decisions

### Decision Priority Analysis

**Critical Decisions (Block Implementation):**
1. Layered architecture: Controller → Service → Repository → QueryBuilder
2. API format: JSON:API v1.1 via timacdonald/json-api
3. API versioning: `/api/v1/` prefix
4. Multi-tenancy: Organization middleware scoping
5. Data partitioning: Monthly partitions on serp_snapshots, serp_results

**Important Decisions (Shape Architecture):**
1. DTOs via Spatie Data for all create/update operations
2. FormRequests with `toDto()` method
3. Public keys (ULID) vs auto-increment IDs
4. Enum-driven constants (PHP 8.4 backed enums)
5. Queue separation: 4 queues via Horizon

**Deferred Decisions (Post-MVP):**
1. ClickHouse migration for serp_results (if >500k keywords)
2. WebSocket/SSE для live updates
3. Microservice extraction (scraper workers)
4. CDN for static assets

### Data Architecture

| Decision | Choice | Rationale |
|---|---|---|
| RDBMS | PostgreSQL 16 | Партиционирование, jsonb, отличная производительность |
| Partitioning | Monthly RANGE на collected_at | Оптимальный баланс между количеством партиций и объёмом данных |
| Caching | Redis 7 (Laravel Cache) | Уже используется для очередей, добавить кеш |
| Migrations | Laravel migrations | Стандарт, уже 25 миграций |
| Seeding | Seeders для regions, site_types | Справочные данные |
| IDs | Bigint auto-increment (internal) + ULID public keys | Безопасность (не раскрываем sequence), performance (bigint FK) |

**Кеш-стратегия:**
- Dashboard summary: cache 5 min, invalidate on new snapshot
- Regions list: cache 24h (static data)
- Site types: cache 24h (static data)
- SERP data: no cache (always fresh from DB)

### Authentication & Security

| Decision | Choice | Rationale |
|---|---|---|
| Auth | Laravel Sanctum (API tokens) | Уже реализовано, SPA + API auth |
| Authorization | Policies per entity | Laravel native, per-model authorization |
| Tenant isolation | `EnsureOrganization` middleware | Global scope на все organization-owned queries |
| Encryption | `credentials` jsonb encrypted | Laravel's built-in encryption for scraper credentials |
| Rate limiting | 60 req/min (throttle middleware) | Предотвращение abuse |
| CORS | Specific origins only | Не wildcard для authenticated routes |

### API & Communication Patterns

| Decision | Choice | Rationale |
|---|---|---|
| API format | JSON:API v1.1 | Стандарт, timacdonald/json-api |
| Versioning | URI prefix `/api/v1/` | Простая, явная версионность |
| Filtering | Spatie QueryBuilder `?filter[x]=y` | Стандарт, декларативный |
| Sorting | `?sort=-created_at,name` | JSON:API spec |
| Pagination | `?page[number]=1&page[size]=20` | JSON:API spec, cursor не нужен для MVP |
| Documentation | Scramble (auto OpenAPI from code) | Zero-maintenance docs |
| Updates | PATCH (not PUT) | JSON:API spec |
| Deletion | 204 No Content | JSON:API spec |
| Creation | 201 Created + Location header | JSON:API spec |
| Error format | `{errors: [{status, code, title, detail}]}` | JSON:API spec |

### Frontend Architecture

| Decision | Choice | Rationale |
|---|---|---|
| Framework | React 18 + TypeScript | Уже реализовано |
| Router | TanStack Router | File-based routing, type-safe |
| Data fetching | TanStack Query | Caching, invalidation, optimistic updates |
| Tables | TanStack Table | Server-side pagination, sorting, filtering |
| Styling | Tailwind CSS + shadcn/ui | Utility-first, component library |
| State management | TanStack Query (server state) + React Context (UI state) | Нет Redux — server state через Query |
| API client | Axios instance with interceptors | Token injection, error handling |
| Charts | Recharts | Lightweight, React-native charts |
| Build | Vite | Fast HMR, optimized builds |

### Infrastructure & Deployment

| Decision | Choice | Rationale |
|---|---|---|
| Containers | Docker Compose | Уже настроен: app, nginx, postgres, redis, scheduler, horizon |
| Queue system | Redis + Horizon | 4 очереди с auto-scaling workers |
| Scheduler | Laravel Scheduler (cron каждую минуту) | Стандарт Laravel |
| Monitoring | Horizon dashboard + custom metrics | Built-in queue monitoring |
| CI/CD | GitHub Actions (deferred) | Стандарт |
| Environments | .env per environment | Laravel standard |

## Implementation Patterns & Consistency Rules

### Naming Patterns

**Database Naming (Laravel conventions):**
- Tables: `snake_case`, plural (`serp_snapshots`, `classification_rules`)
- Columns: `snake_case` (`keyword_id`, `collected_at`)
- Foreign keys: `{table_singular}_id` (`organization_id`, `keyword_id`)
- Pivot tables: alphabetical singular (`organization_user`)
- Indexes: `{table}_{columns}_index` (Laravel auto-naming)

**API Naming:**
- Endpoints: `/api/v1/{resource}` plural (`/api/v1/keywords`, `/api/v1/projects`)
- Route parameters: `{keyword}` (Laravel convention, resolved via `getRouteKeyName()`)
- Query parameters: `filter[status]`, `sort`, `page[number]` (JSON:API spec)
- Headers: standard HTTP headers only

**PHP Code Naming (PSR-12 + Laravel):**
- Classes: `PascalCase` (`KeywordService`, `SerpSnapshotRepository`)
- Methods: `camelCase` (`getByProject`, `createFromDto`)
- Properties: `camelCase`
- Constants/Enums: `PascalCase` cases (`Engine::Google`, `Device::Desktop`)
- Files: match class name (`KeywordService.php`)

**Frontend Code Naming:**
- Components: `PascalCase` files and exports (`KeywordsTable.tsx`)
- Hooks: `camelCase` with `use` prefix (`useKeywords.ts`)
- Utils: `camelCase` (`formatPosition.ts`)
- Routes: `kebab-case` directories, `index.tsx` / `$param.tsx` files

### Structure Patterns

**Backend Layer Structure:**

```
Controller (thin)
  ↓ injects Service only
Service (business logic)
  ↓ injects Repository + other Services
Repository (data access)
  ↓ uses QueryBuilder + Model
QueryBuilder (filters, sorts, includes)
  ↓ extends Spatie AllowedFilter
Model (relations, casts, NO scopes)
```

**Strict boundaries:**
- Controller NEVER accesses Repository or Model directly
- Service NEVER calls `Model::query()`, `::create()`, `->save()`, `->delete()`
- Repository NEVER contains business logic
- QueryBuilder NEVER contains CRUD operations
- Model has NO `scopeXxx()` methods — use QueryBuilder

### Format Patterns

**API Response (JSON:API v1.1):**

```json
// Single resource
{"data": {"type": "keywords", "id": "01HX...", "attributes": {"keyword": "купить квартиру", "engine": "yandex"}, "relationships": {"cluster": {"data": {"type": "clusters", "id": "01HY..."}}}, "links": {"self": "/api/v1/keywords/01HX..."}}}

// Collection
{"data": [...], "meta": {"current_page": 1, "per_page": 20, "total": 150, "last_page": 8}, "links": {"first": "...", "last": "...", "prev": null, "next": "..."}}

// Error
{"errors": [{"status": "422", "code": "validation_error", "title": "Validation Failed", "detail": "The keyword field is required.", "source": {"pointer": "/data/attributes/keyword"}}]}
```

**Date format:** ISO 8601 (`2026-03-26T12:00:00Z`) в JSON, `Carbon` в PHP.

### Communication Patterns

**Events (Laravel Events):**
- Naming: `{Entity}{PastTenseAction}` (`SerpSnapshotCollected`, `KeywordImported`, `PositionAlertTriggered`)
- Payload: event class с typed properties
- Async: dispatch to queue via `ShouldQueue`

**Jobs:**
- Naming: `{Action}{Entity}Job` (`ScrapeSerpJob`, `CollectWordstatJob`, `ClassifyDomainsJob`)
- Queue assignment: explicit `$queue` property
- Retry: `$tries = 3`, `$backoff = [10, 60, 300]`

### Process Patterns

**Error Handling:**
- API errors: JSON:API format via exception handler
- Job errors: log + update `scrape_jobs.error_message` + retry
- Frontend: TanStack Query error boundaries + toast notifications
- Validation: FormRequest → 422 JSON:API error

**Loading States:**
- Frontend: TanStack Query `isLoading` / `isFetching` / `isError`
- Skeleton loaders for tables
- Toast for mutations (success/error)

### Enforcement Guidelines

**All AI Agents MUST:**
1. Follow Controller → Service → Repository → QueryBuilder layering strictly
2. Use JSON:API v1.1 format for ALL API responses
3. Use DTOs (Spatie Data) for all create/update data transfer
4. Never access Model directly from Controller or Service
5. Use PHP 8.4 backed enums for all constants
6. Add `declare(strict_types=1)` to every PHP file
7. Mark classes `final` and services/DTOs `readonly`
8. Write Pest tests for every new endpoint
9. Run PHPStan level 8 before committing
10. Run Laravel Pint before committing

## Project Structure & Boundaries

### Complete Backend Directory Structure

```
serp-panel/
├── app/
│   ├── Builders/                          # QueryBuilder classes
│   │   ├── KeywordQueryBuilder.php
│   │   ├── SerpSnapshotQueryBuilder.php
│   │   ├── ProjectQueryBuilder.php
│   │   ├── DomainQueryBuilder.php
│   │   ├── ClassificationRuleQueryBuilder.php
│   │   └── ScrapeJobQueryBuilder.php
│   ├── Contracts/
│   │   └── Repositories/                  # Repository interfaces
│   │       ├── KeywordRepositoryInterface.php
│   │       ├── ProjectRepositoryInterface.php
│   │       ├── DomainRepositoryInterface.php
│   │       ├── SerpSnapshotRepositoryInterface.php
│   │       ├── ClassificationRuleRepositoryInterface.php
│   │       ├── ScraperRepositoryInterface.php
│   │       └── ScrapeScheduleRepositoryInterface.php
│   ├── DataTransferObjects/               # DTOs via Spatie Data
│   │   ├── Keyword/
│   │   │   ├── CreateKeywordData.php
│   │   │   ├── UpdateKeywordData.php
│   │   │   └── BulkImportKeywordData.php
│   │   ├── Project/
│   │   │   ├── CreateProjectData.php
│   │   │   └── UpdateProjectData.php
│   │   ├── Domain/
│   │   ├── Scraper/
│   │   ├── Schedule/
│   │   └── Classification/
│   ├── Enums/
│   │   ├── Engine.php
│   │   ├── Device.php
│   │   ├── OrganizationRole.php
│   │   ├── ScrapeJobStatus.php
│   │   ├── ClassifiedBy.php
│   │   ├── ClassificationRuleType.php
│   │   └── WordstatSuggestionType.php
│   ├── Events/
│   │   ├── SerpSnapshotCollected.php
│   │   ├── KeywordImported.php
│   │   ├── PositionAlertTriggered.php
│   │   └── ClassificationCompleted.php
│   ├── Http/
│   │   ├── Controllers/Api/V1/            # Versioned controllers
│   │   │   ├── AuthController.php
│   │   │   ├── ProjectController.php
│   │   │   ├── DomainController.php
│   │   │   ├── CategoryController.php
│   │   │   ├── ClusterController.php
│   │   │   ├── KeywordController.php
│   │   │   ├── RegionController.php
│   │   │   ├── SerpController.php
│   │   │   ├── WordstatController.php
│   │   │   ├── ClassificationController.php
│   │   │   ├── ScraperController.php
│   │   │   ├── ScheduleController.php
│   │   │   ├── CompetitorController.php
│   │   │   ├── DashboardController.php
│   │   │   └── OrganizationController.php
│   │   ├── Middleware/
│   │   │   ├── EnsureOrganization.php
│   │   │   ├── ForceJsonApiContentType.php
│   │   │   └── CheckRole.php
│   │   ├── Requests/                      # FormRequests with toDto()
│   │   │   ├── Keyword/
│   │   │   │   ├── StoreKeywordRequest.php
│   │   │   │   ├── UpdateKeywordRequest.php
│   │   │   │   └── BulkImportKeywordRequest.php
│   │   │   ├── Project/
│   │   │   ├── Domain/
│   │   │   ├── Scraper/
│   │   │   └── Schedule/
│   │   └── Resources/                     # JSON:API Resources
│   │       ├── KeywordResource.php
│   │       ├── ProjectResource.php
│   │       ├── DomainResource.php
│   │       ├── SerpSnapshotResource.php
│   │       ├── SerpResultResource.php
│   │       ├── ClassificationRuleResource.php
│   │       ├── ScraperResource.php
│   │       ├── ScheduleResource.php
│   │       └── OrganizationResource.php
│   ├── Jobs/
│   │   ├── ScrapeSerpJob.php
│   │   ├── CollectWordstatJob.php
│   │   ├── ClassifyDomainsJob.php
│   │   └── SendPositionAlertJob.php
│   ├── Models/                            # 20 models (existing)
│   ├── Policies/
│   │   ├── ProjectPolicy.php
│   │   ├── KeywordPolicy.php
│   │   ├── DomainPolicy.php
│   │   ├── ScraperPolicy.php
│   │   └── OrganizationPolicy.php
│   ├── Repositories/
│   │   └── Eloquent/                      # Repository implementations
│   │       ├── KeywordRepository.php
│   │       ├── ProjectRepository.php
│   │       ├── DomainRepository.php
│   │       ├── SerpSnapshotRepository.php
│   │       ├── ClassificationRuleRepository.php
│   │       ├── ScraperRepository.php
│   │       └── ScrapeScheduleRepository.php
│   ├── Services/
│   │   ├── Keyword/
│   │   │   ├── KeywordService.php         # CRUD
│   │   │   └── KeywordImportService.php   # CSV import
│   │   ├── Serp/
│   │   │   ├── SerpService.php            # SERP data access
│   │   │   └── SerpSnapshotService.php    # Snapshot processing
│   │   ├── Classification/
│   │   │   └── ClassificationService.php
│   │   ├── Wordstat/
│   │   │   └── WordstatService.php
│   │   ├── Scrapers/
│   │   │   ├── Adapters/
│   │   │   │   └── XmlRiverAdapter.php
│   │   │   ├── Contracts/
│   │   │   │   └── SerpScraperAdapter.php
│   │   │   ├── DTO/
│   │   │   │   ├── ScrapeRequest.php
│   │   │   │   ├── ScrapeResponse.php
│   │   │   │   └── SerpResultItem.php
│   │   │   └── ScraperFactory.php
│   │   ├── Dashboard/
│   │   │   └── DashboardService.php
│   │   ├── Alert/
│   │   │   └── AlertService.php
│   │   └── Export/
│   │       └── ExportService.php
│   └── Console/
│       └── Commands/
│           ├── CheckSchedulesCommand.php
│           ├── DispatchScrapeJobsCommand.php
│           ├── CleanupPartitionsCommand.php
│           └── CollectWordstatCommand.php
├── config/
│   ├── horizon.php
│   └── serp-panel.php                    # App-specific config
├── database/
│   ├── migrations/                        # 25 existing migrations
│   └── seeders/
│       ├── RegionSeeder.php
│       └── SiteTypeSeeder.php
├── routes/
│   ├── api.php                            # → api/v1/ routes
│   └── console.php
├── tests/
│   ├── Feature/
│   │   ├── Auth/
│   │   ├── Keywords/
│   │   ├── Serp/
│   │   ├── Classification/
│   │   ├── Scrapers/
│   │   └── Organization/
│   ├── Unit/
│   │   ├── Services/
│   │   ├── Repositories/
│   │   └── Builders/
│   ├── Pest.php
│   └── TestCase.php
├── docker/
│   ├── app/Dockerfile
│   ├── nginx/default.conf
│   └── scheduler/entrypoint.sh
├── docker-compose.yml
└── frontend/                              # React SPA
    ├── src/
    │   ├── main.tsx
    │   ├── routes/                        # TanStack Router (file-based)
    │   │   ├── __root.tsx
    │   │   ├── index.tsx                  # Dashboard
    │   │   ├── login.tsx
    │   │   ├── register.tsx
    │   │   ├── projects/
    │   │   │   ├── index.tsx
    │   │   │   └── $projectId/
    │   │   │       ├── index.tsx          # Project overview
    │   │   │       ├── keywords.tsx
    │   │   │       ├── keywords/$keywordId.tsx
    │   │   │       ├── domains.tsx
    │   │   │       └── competitors.tsx
    │   │   ├── classification/
    │   │   │   ├── index.tsx              # Rules
    │   │   │   └── domains.tsx
    │   │   ├── scrapers/index.tsx
    │   │   ├── schedules/index.tsx
    │   │   └── settings/index.tsx         # Org + members
    │   ├── components/
    │   │   ├── ui/                        # shadcn/ui components
    │   │   ├── keywords/
    │   │   ├── serp/
    │   │   ├── wordstat/
    │   │   ├── dashboard/
    │   │   └── layout/
    │   ├── hooks/
    │   │   ├── useAuth.ts
    │   │   ├── useKeywords.ts
    │   │   ├── useSerp.ts
    │   │   └── useOrganization.ts
    │   ├── lib/
    │   │   ├── api.ts                     # Axios instance
    │   │   ├── queryClient.ts
    │   │   └── utils.ts
    │   ├── contexts/
    │   │   ├── AuthContext.tsx
    │   │   └── OrganizationContext.tsx
    │   └── assets/
    ├── package.json
    ├── vite.config.ts
    ├── tailwind.config.ts
    └── tsconfig.json
```

### Architectural Boundaries

**API Boundaries:**
- Public API: `/api/v1/*` — all endpoints behind Sanctum auth (except login/register)
- Tenant boundary: `EnsureOrganization` middleware scopes ALL queries
- Role boundary: Policies check RBAC per entity per action

**Service Boundaries:**
- Each service owns one domain area
- Services can call other services (no circular deps)
- Services NEVER bypass Repository to access DB

**Data Boundaries:**
- Organization scoping: ALL tenant data filtered by `organization_id`
- Partitioned tables: serp_snapshots, serp_results — accessed only via Repository
- Encrypted fields: `scrapers.credentials` — decrypted only in ScraperFactory

### Requirements to Structure Mapping

| FR Category | Backend Location | Frontend Location |
|---|---|---|
| Auth (FR1-5) | Controllers/Api/V1/Auth*, Middleware/* | routes/login, contexts/Auth |
| Projects (FR6-11) | Controllers/Api/V1/Project*, Keyword* | routes/projects/* |
| SERP (FR12-17) | Controllers/Api/V1/Serp*, Services/Serp/ | components/serp/, keywords/$keywordId |
| Wordstat (FR18-22) | Controllers/Api/V1/Wordstat*, Services/Wordstat/ | components/wordstat/ |
| Classification (FR23-27) | Controllers/Api/V1/Classification*, Services/Classification/ | routes/classification/ |
| Scrapers (FR28-31) | Controllers/Api/V1/Scraper*, Services/Scrapers/ | routes/scrapers/ |
| Schedules (FR32-35) | Controllers/Api/V1/Schedule*, Console/Commands/ | routes/schedules/ |
| Alerts (FR38-40) | Services/Alert/, Jobs/SendPositionAlertJob | (settings page) |
| Export (FR41-42) | Services/Export/ | (button on tables) |

## Architecture Validation Results

### Coherence Validation ✅

**Decision Compatibility:** Все технологии совместимы. Laravel 13 + PostgreSQL 16 + Redis 7 + Horizon — проверенный стек. Spatie QueryBuilder + timacdonald/json-api + Spatie Data — взаимодополняющие пакеты.

**Pattern Consistency:** Naming conventions следуют Laravel + PSR-12 стандартам. JSON:API формат единообразен для всех endpoints. Layered architecture применяется ко всем entity.

**Structure Alignment:** Структура проекта поддерживает все архитектурные решения. Каждый FR имеет конкретное место в файловой структуре.

### Requirements Coverage ✅

**Functional Requirements:** Все 44 FR покрыты архитектурно:
- FR1-5 (Auth): Sanctum + EnsureOrganization + Policies
- FR6-11 (Projects): CRUD через layered architecture
- FR12-17 (SERP): ScrapeSerpJob → SerpSnapshotService → Repository → Partitioned tables
- FR18-22 (Wordstat): CollectWordstatJob → WordstatService → Repository
- FR23-27 (Classification): ClassifyDomainsJob → ClassificationService → Rules engine
- FR28-35 (Scrapers/Schedules): Adapter pattern + Scheduler commands
- FR38-42 (Alerts/Export): Services + Jobs (MVP gaps — need implementation)
- FR43-44 (Billing): Deferred — basic tier limits via config

**Non-Functional Requirements:** Все 23 NFR адресованы:
- Performance: QueryBuilder пагинация, Redis кеш, партиционирование
- Security: Sanctum, tenant isolation, encrypted credentials, rate limiting
- Scalability: Horizon workers, partitioning, adapter pattern
- Reliability: Job retries, idempotent scheduler
- Observability: Horizon dashboard, structured logging

### Implementation Readiness ✅

**Architecture Readiness: HIGH**

**Key Strengths:**
- Layered architecture чётко определена (Controller → Service → Repository → QueryBuilder)
- JSON:API v1.1 обеспечивает предсказуемый, стандартный API
- Adapter pattern для скрейперов позволяет легко добавлять провайдеров
- Multi-tenancy на уровне middleware — безопасно и прозрачно
- Partitioning strategy готова к масштабу 300M записей/месяц

**Areas for Future Enhancement:**
- Переход serp_results на ClickHouse при >500k ключевиков
- WebSocket/SSE для real-time позиций
- Microservice extraction для scraper workers
- GraphQL для гибких frontend-запросов

### Implementation Handoff

**AI Agent Guidelines:**
1. Follow Controller → Service → Repository → QueryBuilder layering strictly
2. Use JSON:API v1.1 format (timacdonald/json-api) for ALL API responses
3. Use DTOs (Spatie Data) for all create/update operations
4. Use Spatie QueryBuilder for all list/filter/sort endpoints
5. Add `declare(strict_types=1)` and `final` to every new class
6. Write Pest feature tests for every endpoint
7. Run PHPStan level 8 + Pint before every commit
8. Respect tenant isolation — NEVER query without organization scope

**First Implementation Priority:**
1. Add `/api/v1/` prefix to all routes
2. Introduce Repository layer for all existing controllers
3. Add DTOs for all create/update operations
4. Add Policies for authorization
5. Implement missing MVP features (alerts, export, retention)
