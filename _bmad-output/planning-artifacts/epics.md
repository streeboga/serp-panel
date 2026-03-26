---
stepsCompleted: ['step-01-validate-prerequisites', 'step-02-design-epics', 'step-03-create-stories', 'step-04-final-validation']
workflowType: 'epics'
status: 'complete'
completedAt: '2026-03-26'
project_name: 'serp-panel'
inputDocuments:
  - _bmad-output/planning-artifacts/prd.md
  - _bmad-output/planning-artifacts/architecture.md
  - _bmad-output/planning-artifacts/ux-design-specification.md
epicCount: 8
storyCount: 32
---

# Epics & Stories — SERP Panel

## Requirements Coverage

**PRD:** 44 Functional Requirements, 23 Non-Functional Requirements
**Architecture:** Controller → Service → Repository → QueryBuilder, JSON:API v1.1
**UX:** shadcn/ui + Tailwind, data-first design, 2-click rule

### Brownfield Status

Проект частично реализован. Эпики учитывают текущее состояние:
- ✅ = уже реализовано (рефакторинг к target architecture)
- ❌ = не реализовано (новая разработка)

---

## Epic List

| # | Epic | User Value | FRs | Status |
|---|------|-----------|-----|--------|
| 1 | Architecture Refactoring | Стабильная, тестируемая, расширяемая база | NFR | ✅ рефакторинг |
| 2 | Auth & Organizations | Безопасный доступ, командная работа | FR1-5 | ✅ рефакторинг |
| 3 | Projects & Keywords | Организация данных, импорт ключевиков | FR6-11 | ✅ рефакторинг |
| 4 | SERP Monitoring | Отслеживание позиций, история, дашборд | FR12-17, FR36-37 | ✅ рефакторинг |
| 5 | Wordstat Analytics | Анализ частотности и сезонности | FR18-22 | ✅ рефакторинг |
| 6 | Classification System | Автоклассификация сайтов в выдаче | FR23-27 | ✅ рефакторинг |
| 7 | Scrapers & Schedules | Управление провайдерами, расписания | FR28-35 | ✅ рефакторинг |
| 8 | Alerts, Export & Billing | Уведомления, экспорт, тарифы | FR38-44 | ❌ новое |

---

## Epic 1: Architecture Refactoring

**Цель:** Привести существующий код к целевой архитектуре (Controller → Service → Repository → QueryBuilder, JSON:API v1.1, API v1 prefix, DTOs, тесты).

**Covers:** NFR1-23, Technical Debt

### Story 1.1: API Versioning & JSON:API Middleware

As a API consumer,
I want API endpoints versioned under `/api/v1/` with JSON:API content type,
So that I have a predictable, standards-compliant API.

**Acceptance Criteria:**

**Given** existing routes in `routes/api.php`
**When** routes are restructured
**Then** all endpoints are prefixed with `/api/v1/`
**And** `ForceJsonApiContentType` middleware returns `application/vnd.api+json`
**And** all existing tests updated to use new routes
**And** controllers moved to `Http/Controllers/Api/V1/`

### Story 1.2: Repository Layer Introduction

As a developer,
I want data access isolated in Repository classes,
So that business logic is decoupled from database queries.

**Acceptance Criteria:**

**Given** controllers currently access Models directly
**When** Repository layer is introduced
**Then** each entity has `{Entity}RepositoryInterface` in `Contracts/Repositories/`
**And** each entity has `{Entity}Repository` in `Repositories/Eloquent/`
**And** Services inject Repositories (not Models)
**And** Repository interfaces are bound in ServiceProvider
**And** PHPStan level 8 passes

### Story 1.3: QueryBuilder Layer & Spatie Integration

As a developer,
I want filtering/sorting/pagination via Spatie QueryBuilder,
So that all list endpoints follow consistent patterns.

**Acceptance Criteria:**

**Given** Repository layer from Story 1.2
**When** QueryBuilder classes are created
**Then** each list endpoint uses `{Entity}QueryBuilder` extending Spatie
**And** filters follow `?filter[field]=value` pattern
**And** sorting follows `?sort=-created_at` pattern
**And** pagination follows `?page[number]=1&page[size]=20` pattern
**And** eager loading via `?include=relation`

### Story 1.4: DTO Layer (Spatie Data)

As a developer,
I want type-safe DTOs for all create/update operations,
So that data contracts between layers are explicit.

**Acceptance Criteria:**

**Given** `spatie/laravel-data` installed
**When** DTOs are created
**Then** each entity has `Create{Entity}Data` and `Update{Entity}Data`
**And** FormRequests have `toDto()` method returning typed DTO
**And** Services accept DTOs (not arrays)
**And** `declare(strict_types=1)` on all new files
**And** all classes marked `final readonly`

### Story 1.5: JSON:API Resources & Policies

As a API consumer,
I want all responses in JSON:API v1.1 format with proper authorization,
So that responses are predictable and access is controlled.

**Acceptance Criteria:**

**Given** `timacdonald/json-api` installed
**When** Resources are created
**Then** each entity has `{Entity}Resource extends JsonApiResource`
**And** each resource implements `toId()`, `toType()`, `toAttributes()`, `toRelationships()`, `toLinks()`
**And** Policy created for each entity (ProjectPolicy, KeywordPolicy, etc.)
**And** PATCH used for updates (not PUT)
**And** DELETE returns 204 No Content
**And** CREATE returns 201 + Location header

### Story 1.6: Test Infrastructure & Coverage

As a developer,
I want comprehensive test coverage for critical paths,
So that refactoring doesn't break existing functionality.

**Acceptance Criteria:**

**Given** Pest testing framework
**When** tests are written
**Then** Feature tests exist for: Auth, Projects CRUD, Keywords CRUD, SERP pipeline, Organization middleware
**And** Unit tests exist for: Services, Repositories, QueryBuilders
**And** `ApiTestCase` base class with JSON:API helpers
**And** test coverage ≥ 60% for critical paths
**And** PHPStan level 8 passes with zero errors
**And** Laravel Pint passes

---

## Epic 2: Auth & Organizations

**Цель:** Безопасная аутентификация, мульти-тенантная изоляция, ролевая модель.

**Covers:** FR1-5

### Story 2.1: Auth API (Register/Login/Logout)

As a user,
I want to register, login, and logout via API,
So that I can access my data securely.

**Acceptance Criteria:**

**Given** Sanctum auth
**When** user calls `/api/v1/auth/register`, `/login`, `/logout`
**Then** registration creates user + personal organization
**And** login returns Sanctum token
**And** logout invalidates token
**And** responses follow JSON:API format
**And** tests cover happy path + validation errors

### Story 2.2: Organization Management & Member Invites

As an admin,
I want to manage my organization and invite team members,
So that my team can collaborate on projects.

**Acceptance Criteria:**

**Given** authenticated admin user
**When** admin manages organization
**Then** admin can update org name/slug
**And** admin can invite members by email
**And** admin can assign roles (admin/manager/analyst/viewer)
**And** admin can change member roles
**And** admin can remove members
**And** RBAC matrix from PRD is enforced via Policies

### Story 2.3: Tenant Data Isolation

As an organization member,
I want to only see data belonging to my organization,
So that data is secure between tenants.

**Acceptance Criteria:**

**Given** `EnsureOrganization` middleware
**When** any API request is made
**Then** all queries are scoped to current organization
**And** user cannot access data from other organizations
**And** tests verify cross-tenant isolation (negative tests)

---

## Epic 3: Projects & Keywords

**Цель:** Создание проектов, иерархия категорий/кластеров, управление ключевиками.

**Covers:** FR6-11

### Story 3.1: Projects CRUD

As a manager,
I want to create and manage projects within my organization,
So that I can organize my SEO monitoring work.

**Acceptance Criteria:**

**Given** authenticated manager
**When** CRUD operations on projects
**Then** projects are organization-scoped
**And** JSON:API responses with relationships (domains, categories)
**And** QueryBuilder supports `?filter[search]=`, `?sort=-created_at`

### Story 3.2: Domains, Categories & Clusters

As a manager,
I want to add domains (own + competitors) and organize keywords in categories/clusters,
So that I have structured hierarchy for my SEO data.

**Acceptance Criteria:**

**Given** project exists
**When** manager manages domains, categories, clusters
**Then** domains have `is_own` flag
**And** categories support tree structure (parent_id)
**And** clusters belong to categories
**And** all CRUD with JSON:API format and proper Policies

### Story 3.3: Keywords Management & CSV Import

As a manager,
I want to add keywords manually and import from CSV,
So that I can quickly set up monitoring for thousands of keywords.

**Acceptance Criteria:**

**Given** cluster exists
**When** keywords are added/imported
**Then** single keyword creation with engine, region, device
**And** CSV bulk import up to 10k keywords in < 30 seconds
**And** import validates required fields, skips duplicates
**And** progress feedback during import
**And** keywords filterable by engine, device, region, cluster

---

## Epic 4: SERP Monitoring

**Цель:** Сбор, хранение и отображение позиций в поисковой выдаче.

**Covers:** FR12-17, FR36-37

### Story 4.1: SERP Data API & History

As a SEO specialist,
I want to view SERP results and position history for any keyword,
So that I can track ranking changes over time.

**Acceptance Criteria:**

**Given** SERP snapshots exist for keyword
**When** user queries SERP API
**Then** `GET /api/v1/keywords/{id}/serp?filter[from]=&filter[to]=&filter[limit]=20` returns results
**And** `GET /api/v1/keywords/{id}/serp/history` returns position over time
**And** own domain (`is_own`) is flagged in response
**And** domain extracted from URL at save time
**And** responses include site_type classification

### Story 4.2: Dashboard Summary API

As a user,
I want a dashboard showing key metrics for my projects,
So that I can quickly assess my SEO performance.

**Acceptance Criteria:**

**Given** project with keywords and snapshots
**When** `GET /api/v1/dashboard/summary?filter[project_id]=`
**Then** returns: keywords count by engine, TOP-3/10/20/100 counts
**And** position changes vs previous period (improved/declined/stable)
**And** visibility score
**And** response cached for 5 minutes, invalidated on new snapshot
**And** P95 < 2s for 10k keywords

### Story 4.3: Competitors View

As a user,
I want to see which competitors appear in SERP for my keywords,
So that I understand my competitive landscape.

**Acceptance Criteria:**

**Given** project with keywords and SERP data
**When** user views competitors
**Then** aggregate table: domain, site_type, TOP-3/10/20 counts, trend
**And** own domains highlighted
**And** filterable by keyword group

### Story 4.4: Frontend — Dashboard & Keywords

As a user,
I want to interact with dashboard and keywords via web interface,
So that I can monitor positions without API knowledge.

**Acceptance Criteria:**

**Given** React frontend
**When** user navigates app
**Then** Dashboard shows SummaryCards (TOP-3/10/20/100), PositionChangeSummary, VisibilityChart
**And** Keywords table shows position + delta (color-coded), engine badge, Wordstat frequency
**And** Click keyword → detail page with SERP/History/Wordstat/Suggestions tabs
**And** Filters: engine, region, category, search
**And** Pagination (server-side, 20 per page)
**And** CSV import dialog with drag & drop

---

## Epic 5: Wordstat Analytics

**Цель:** Сбор и отображение Wordstat-данных (частотность, тренды, подсказки).

**Covers:** FR18-22

### Story 5.1: Wordstat API Endpoints

As a user,
I want to view Wordstat data alongside keyword positions,
So that I can correlate search demand with rankings.

**Acceptance Criteria:**

**Given** Wordstat data collected for keyword
**When** user queries Wordstat API
**Then** `GET /api/v1/keywords/{id}/wordstat` returns frequency (exact, phrase, broad)
**And** `GET /api/v1/keywords/{id}/wordstat/trends` returns monthly dynamics
**And** suggestions/associations available
**And** Wordstat data shown in keyword detail tabs (FrequencyCards + TrendChart)

### Story 5.2: Wordstat Collection & Schedules

As a manager,
I want to schedule Wordstat data collection separately from SERP,
So that I collect demand data at appropriate intervals.

**Acceptance Criteria:**

**Given** Wordstat adapter configured
**When** schedule triggers
**Then** `CollectWordstatJob` dispatched to `wordstat` queue
**And** collects frequency_exact, frequency_broad, frequency_phrase
**And** collects monthly trends and suggestions
**And** schedule supports project/cluster/keyword cascade
**And** default frequency: 30 days
**And** job has 3 retries with backoff

---

## Epic 6: Classification System

**Цель:** Автоматическая и ручная классификация сайтов в поисковой выдаче.

**Covers:** FR23-27

### Story 6.1: Classification Rules CRUD

As a manager,
I want to create and manage classification rules,
So that domains in SERP are automatically categorized by type.

**Acceptance Criteria:**

**Given** authenticated manager
**When** managing classification rules
**Then** CRUD for rules with: rule_type (domain_exact/contains/regex/url_regex/title_contains), pattern, site_type, priority
**And** system rules (is_system=true) cannot be edited
**And** rules ordered by priority
**And** organization-scoped

### Story 6.2: Auto-Classification Engine & Manual Override

As a user,
I want domains classified automatically with ability to manually correct,
So that I see accurate site type information in SERP.

**Acceptance Criteria:**

**Given** classification rules exist
**When** SERP data is saved
**Then** `ClassifyDomainsJob` runs on `classification` queue
**And** each domain checked against rules in priority order
**And** first match → classified_by=rule
**And** manual corrections → classified_by=manual (not overwritten by rules)
**And** SiteTypeBadge displayed in SERP table
**And** frontend allows manual type change on domains page

---

## Epic 7: Scrapers & Schedules

**Цель:** Управление скрейпер-провайдерами и расписаниями сбора данных.

**Covers:** FR28-35

### Story 7.1: Scrapers CRUD & Health Check

As a manager,
I want to manage scraper providers and test their connectivity,
So that I can ensure data collection works reliably.

**Acceptance Criteria:**

**Given** authenticated manager
**When** managing scrapers
**Then** CRUD with: type, name, base_url, credentials (encrypted), supported_engines, rate_limit, is_active
**And** `POST /api/v1/scrapers/{id}/test` runs health check via adapter
**And** frontend shows scraper cards with status badge and Test button
**And** adapter pattern: `SerpScraperAdapter` interface

### Story 7.2: Schedules & Job Pipeline

As a manager,
I want to configure scraping schedules and trigger immediate collection,
So that data is collected at the right frequency.

**Acceptance Criteria:**

**Given** scraper configured and active
**When** schedule triggers
**Then** `CheckSchedulesCommand` creates `scrape_jobs` in batches
**And** `DispatchScrapeJobsCommand` dispatches with rate_limit throttling
**And** cascade: project/category/cluster/keyword (specific overrides general)
**And** `POST /api/v1/schedules/{id}/run-now` triggers immediate collection
**And** scheduler is idempotent (no duplicate jobs on re-run)
**And** frontend: schedules table with cascade selector, frequency, toggle

### Story 7.3: Data Retention & Cleanup

As an admin,
I want old SERP data automatically cleaned up,
So that database storage remains manageable.

**Acceptance Criteria:**

**Given** monthly partitioned tables (serp_snapshots, serp_results)
**When** `CleanupPartitionsCommand` runs daily
**Then** drops partitions older than configured retention (default: 12 months)
**And** cleans `raw_response` from completed scrape_jobs
**And** creates future partitions (next 2 months ahead)
**And** logs partition operations

---

## Epic 8: Alerts, Export & Billing

**Цель:** Уведомления при падении позиций, экспорт данных, базовый биллинг.

**Covers:** FR38-44

### Story 8.1: Position Alerts (Telegram + Email)

As a user,
I want to receive notifications when my positions drop below a threshold,
So that I can react quickly to ranking changes.

**Acceptance Criteria:**

**Given** alert rules configured by user
**When** new SERP snapshot shows position drop
**Then** `PositionAlertTriggered` event fired
**And** `SendPositionAlertJob` dispatched to `default` queue
**And** notification sent via Telegram Bot API and/or Email
**And** user can configure: keyword/cluster/project scope, threshold, channels
**And** alert history viewable in settings

### Story 8.2: Data Export (CSV/Excel)

As a user,
I want to export keyword positions and SERP data,
So that I can use data in external tools and reports.

**Acceptance Criteria:**

**Given** keywords with position data
**When** user clicks Export button
**Then** CSV download with columns: keyword, engine, region, position, delta, frequency, url
**And** SERP export: position, domain, site_type, title, url
**And** Export respects current filters
**And** DataExportButton component on keywords table and SERP table

### Story 8.3: Basic Billing & Tier Limits

As an admin,
I want subscription tiers with keyword limits,
So that usage is controlled and monetizable.

**Acceptance Criteria:**

**Given** tier configuration in config/serp-panel.php
**When** user reaches tier limit
**Then** keyword import blocked with clear message
**And** admin can view current usage vs limits
**And** tiers: Free (100 keywords), Basic (5k), Pro (50k), Enterprise (unlimited)
**And** scrape frequency limits per tier

---

## Validation Summary

### FR Coverage

| FR Range | Epic | Status |
|---|---|---|
| FR1-5 | Epic 2 | ✅ |
| FR6-11 | Epic 3 | ✅ |
| FR12-17 | Epic 4 | ✅ |
| FR18-22 | Epic 5 | ✅ |
| FR23-27 | Epic 6 | ✅ |
| FR28-35 | Epic 7 | ✅ |
| FR36-37 | Epic 4 | ✅ |
| FR38-40 | Epic 8 | ✅ |
| FR41-42 | Epic 8 | ✅ |
| FR43-44 | Epic 8 | ✅ |

### NFR Coverage

| NFR | Addressed In |
|---|---|
| Performance (NFR1-5) | Epic 1 (QueryBuilder), Epic 4 (caching) |
| Security (NFR6-11) | Epic 1 (middleware), Epic 2 (auth/RBAC) |
| Scalability (NFR12-16) | Epic 1 (architecture), Epic 7 (retention) |
| Reliability (NFR17-20) | Epic 7 (retries, idempotency) |
| Observability (NFR21-23) | Epic 7 (job logging), Epic 1 (Horizon) |

### Epic Dependencies

```
Epic 1 (Architecture) → Foundation for all
  ↓
Epic 2 (Auth) → Required by all user-facing epics
  ↓
Epic 3 (Projects/Keywords) → Required by SERP/Wordstat/Classification
  ↓
Epic 4 (SERP) ─────────┐
Epic 5 (Wordstat) ──────┤ Independent of each other
Epic 6 (Classification) ┘
  ↓
Epic 7 (Scrapers/Schedules) → Uses SERP pipeline
  ↓
Epic 8 (Alerts/Export/Billing) → Uses all previous
```

### Implementation Priority

1. **Epic 1** — Architecture refactoring (foundation)
2. **Epic 2** — Auth & Organizations (auth is prerequisite)
3. **Epic 3** — Projects & Keywords (core data model)
4. **Epic 4** — SERP Monitoring (core value)
5. **Epic 5-6** — Wordstat + Classification (parallel)
6. **Epic 7** — Scrapers & Schedules (pipeline management)
7. **Epic 8** — Alerts, Export, Billing (MVP completion)
