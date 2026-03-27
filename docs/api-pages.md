# Pages API Reference

## Overview

Pages are tracked URLs (own + competitor) with polymorphic attachment to keywords, clusters, and categories. Used for target URL monitoring and SERP enrichment.

## Endpoints

### List pages
`GET /api/v1/projects/{project}/pages`

Query params:
- `filter[url]` — partial URL match
- `filter[domain_id]` — exact domain ID
- `filter[page_type]` — exact: commercial, informational, navigational, transactional
- `filter[tags]` — comma-separated tag names
- `sort` — url, title, page_type, created_at (prefix - for DESC)
- `page[number]`, `page[size]` — pagination

### Create page
`POST /api/v1/projects/{project}/pages`

Body:
```json
{
  "url": "https://example.com/page",
  "title": "Page title",
  "page_type": "informational",
  "domain_id": 1,
  "notes": "Optional notes",
  "tags": ["competitor", "direct"]
}
```

### Show page
`GET /api/v1/pages/{page}`

### Update page
`PATCH /api/v1/pages/{page}`

### Delete page
`DELETE /api/v1/pages/{page}` → 204

### Sync tags
`PATCH /api/v1/pages/{page}/tags`

Body: `{ "tags": ["tag1", "tag2"] }`

### Attach to entity
`POST /api/v1/pages/{page}/attach`

Body:
```json
{
  "pageable_type": "keyword",
  "pageable_id": 39,
  "engine": "google",
  "device": null,
  "priority": 0,
  "is_target": true
}
```

pageable_type: keyword | cluster | category
engine: google | yandex | null (null = all)
device: desktop | mobile | null (null = all)

### Bulk attach
`POST /api/v1/pages/{page}/bulk-attach`

Body:
```json
{
  "pageable_type": "keyword",
  "pageable_ids": [39, 42, 44],
  "is_target": true,
  "priority": 0
}
```

### Detach
`DELETE /api/v1/pageables/{pageable}` → 204

### Match or create
`POST /api/v1/projects/{project}/pages/match-or-create`

Finds existing page by URL or creates new one. Optionally attaches to entity.

Body:
```json
{
  "url": "https://competitor.com/page",
  "title": "From SERP",
  "page_type": "commercial",
  "tags": ["competitor"],
  "attach_to": {
    "type": "keyword",
    "id": 39,
    "is_target": false
  }
}
```

### Target report
`GET /api/v1/projects/{project}/pages/target-report`

Returns per-keyword target vs actual comparison:

```json
{
  "data": [
    {
      "keyword_id": 39,
      "keyword": "семейный бюджет",
      "engine": "google",
      "device": "desktop",
      "region": "Москва",
      "cluster": "Семейный бюджет",
      "category": "Семейный бюджет",
      "target_url": "https://equity.su/budget/family",
      "target_source": "cluster",
      "actual_url": "https://equity.su/other-page",
      "actual_position": 15,
      "match_status": "cannibalization",
      "match": false
    }
  ]
}
```

match_status values:
- `top3` — target URL found in positions 1-3
- `top10` — target URL found in positions 4-10
- `cannibalization` — our domain found but different URL than target
- `missing` — target URL not found in SERP
- `unset` — no target URL assigned

## Data Model

```
pages (project_id, domain_id, url, path, title, page_type, notes)
  ├── pageables (page_id, pageable_type, pageable_id, engine, device, priority, is_target)
  ├── page_serp_matches (page_id, serp_result_id, snapshot_id, keyword_id, position, collected_at)
  └── tags (via spatie/laravel-tags)
```

## Cascade Inheritance

Keyword inherits target pages: own → cluster → category.
Accessor: `keyword.effective_target_url`, `keyword.target_url_source`, `keyword.target_match_status`.
