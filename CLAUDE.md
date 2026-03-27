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
- `default`: SendPositionAlertJob — sends Telegram/Email alerts on position changes
- Run: `php artisan queue:work --queue=serp-scrape,indexing,wordstat,classification,default`

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
