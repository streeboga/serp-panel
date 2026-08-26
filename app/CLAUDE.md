# Backend (Laravel)

## Layer Architecture

```
Controller → Service → Repository → QueryBuilder → Model
```

- **Controller**: validation via FormRequest, returns Resource, thin
- **Service**: business logic, transactions, dispatches jobs
- **Repository**: implements interface from `Contracts/Repositories/`
- **Model**: relationships, casts, NO scopes, NO business logic

## Controllers (`Http/Controllers/Api/V1/`)

23 controllers. Key ones:
- `AuthController` — register, login, logout, me, updateProfile
- `KeywordController` — index, show, import, bulkStore, update, bulkDestroy
- `PositionMatrixController` — keyword × date × engine position grid
- `SerpController` — SERP results per keyword, history
- `ConnectedAccountController` — multi-account management (Yandex, XMLRiver)
- `WebhookController` — incoming SERP data from external services
- `YandexOAuthController` — OAuth flow with verification_code support
- `PageController` — CRUD pages, attach/detach to entities, match-or-create, target report, tags sync
- `AuditController` — start/cancel audit runs, list runs and per-page results, synchronous single-URL check

## Resources (`Http/Resources/`)

All extend `Support/JsonApiResource` — output `{ type, id, attributes, relationships, links }`.
`whenLoaded()` for relationships, `whenPivotLoaded()` for pivot data.

## Models

- **Keyword**: has computed accessors `latest_position`, `position_change`, `frequency`, `our_url` — these traverse `cluster.category.domain` chain, MUST eager load
- **Organization**: `yandex_token` encrypted, has `connected_accounts`
- **SerpSnapshot/SerpResult**: partitioned by `collected_at` month
- **Page**: has polymorphic morphedByMany (keywords, clusters, categories), HasTags trait, auto-normalizes path on save
- **Pageable**: polymorphic pivot model (page ↔ keyword/cluster/category) with engine/device/priority/is_target
- **PageSerpMatch**: denormalized SERP matching (auto-populated by listener)

## Scrapers (`Services/Scrapers/`)

```
SerpScraperAdapter (interface)
├── XmlRiverAdapter   — xmlriver.com/search/xml (XML response)
├── YandexXmlAdapter  — yandex.ru/search/xml (XML response)
└── WebhookAdapter    — passive, receives data via POST webhook
```

`ScraperFactory::make(Scraper)` creates correct adapter by `$scraper->type`.

## Jobs

- `ScrapeSerpJob(scrapeJobId)` — loads ScrapeJob → adapter → snapshot + results → fires `SerpSnapshotCollected`
- `CollectWordstatJob(keywordId, scheduleId, regionIds)` — Wordstat API → frequencies
- `ClassifyDomainsJob(snapshotId, collectedAt, organizationId)` — classifies domains from SERP
- `SendPositionAlertJob(alertId, keywordId, oldPosition, newPosition)` — sends Telegram/Email alerts
- `AuditSiteJob(auditId)` — site-level checks → resolves URL list → dispatches batch of `AuditPageJob`
- `AuditPageJob(auditId, url, pageId)` — fetches one page, runs `PageAuditor`, stores `PageAuditResult`
- `FinalizeSiteAuditJob(auditId)` — aggregates score and counters, closes the run

## Events & Listeners

- `SerpSnapshotCollected` — fired after SERP snapshot saved in `SerpSnapshotService::scrape()`
- `PositionAlertTriggered` — fired when alert condition met
- `CheckPositionAlertsListener` — listens to `SerpSnapshotCollected`, checks active alerts for keyword, dispatches `SendPositionAlertJob`
- `SerpSnapshotCollected` → `MatchPagesFromSerpListener` → `PageMatchService::matchSnapshot()`

## QueryBuilders (`Builders/`)

10 QueryBuilder classes using Spatie QueryBuilder:
- `KeywordQueryBuilder`, `ProjectQueryBuilder`, `ClassificationRuleQueryBuilder`, `ScrapeJobQueryBuilder` (existing)
- `DomainQueryBuilder`, `SerpSnapshotQueryBuilder`, `ScrapeScheduleQueryBuilder`, `ScraperQueryBuilder`, `PositionAlertQueryBuilder`, `ClusterQueryBuilder` (new)

## Enums

- `Engine`: google, yandex
- `Device`: desktop, mobile
- `OrganizationRole`: admin, manager, analyst, viewer
- `ClassificationRuleType`: domain_exact, domain_contains, domain_regex, url_regex, title_contains
- `PageType`: commercial, informational, navigational, transactional
- `AuditScope`: site, pages, url
- `AuditStatus`: pending, running, completed, failed, cancelled
- `CheckGroup`: technical, meta, content, links, images
- `Severity`: critical, warning, notice (несёт вес штрафа к оценке через `penalty()`)

## Common Pitfalls

- Lazy loading is DISABLED — always `->with([...])` or `->load([...])`
- UTF-8 sanitize before PostgreSQL insert: `mb_convert_encoding($s, 'UTF-8', 'UTF-8')`
- Credentials on Scraper model are `encrypted:array` cast
- `runNow()` must dispatch actual jobs, not just update `next_run_at`
