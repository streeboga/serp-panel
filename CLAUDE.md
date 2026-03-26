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
├── Http/Controllers/Api/V1/   # API controllers (19 шт)
├── Http/Resources/            # JSON:API resources (20 шт)
├── Http/Requests/             # Form validation + DTO
├── Models/                    # Eloquent models (21 шт)
├── Services/                  # Business logic
│   ├── Scrapers/Adapters/     # XMLRiver, YandexXml, Webhook
│   └── Wordstat/Adapters/     # YandexWordstat
├── Repositories/Eloquent/     # Data access layer
├── Enums/                     # Engine, Device, Role, etc
└── Jobs/                      # ScrapeSerpJob, CollectWordstatJob

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
- `wordstat`: CollectWordstatJob — collects Wordstat frequencies
- Run: `php artisan queue:work --queue=serp-scrape,wordstat`

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
```
