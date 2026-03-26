# Database

PostgreSQL 16. 28 migrations.

## Seeder

```bash
php artisan migrate:fresh --seed
# Login: admin@serp.test / password
```

Creates:
- 2 organizations (SEO Agency Pro + Freelance Projects)
- 2 users (admin + analyst)
- 2 projects with domains, categories, clusters
- 23 keywords with 14 days of SERP data (266 snapshots, 790 results)
- WordstatFrequency for each keyword (exact, broad, phrase)
- 10 classification rules (system)
- 1 XMLRiver scraper with real credentials
- 1 schedule + 1 alert

## Key Tables

| Table | Partitioned | Notes |
|-------|-------------|-------|
| serp_snapshots | YES (monthly by collected_at) | One per keyword per collection |
| serp_results | YES (monthly) | Position 1-100 per snapshot |
| keywords | No | Has engine, device, region_id |
| connected_accounts | No | Encrypted credentials |
| scrapers | No | type: xmlriver/yandex_xml/webhook |

## Relationships Chain

```
Organization
 └── Project
      └── Domain (is_own: bool)
           └── Category (hierarchical, parent_id)
                └── Cluster
                     └── Keyword (engine, device, region_id)
                          ├── SerpSnapshot → SerpResult
                          ├── WordstatFrequency (exact, broad, phrase)
                          ├── WordstatTrend
                          └── WordstatSuggestion
```

## Computed Fields on Keyword

NOT stored in DB — computed via model accessors:
- `latest_position`: best position for own domain from latest snapshot
- `position_change`: delta between last 2 snapshots
- `frequency`: frequency_exact from latest WordstatFrequency
- `our_url`: URL of own domain from latest snapshot

These traverse `keyword → cluster → category → domain (is_own)` — MUST eager load.
