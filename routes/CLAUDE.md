# API Routes

All routes: `/api/v1/` prefix, `json-api` middleware.

## Public (no auth)
```
POST /auth/register
POST /auth/login
POST /webhooks/serp              # Incoming SERP data (uses secret, no auth)
```

## Authenticated (Bearer token)
```
POST /auth/logout
GET  /auth/me
PATCH /auth/profile
GET  /organizations
GET  /auth/yandex/redirect
GET  /auth/yandex/callback       # OAuth callback (no json-api middleware)
```

## Organization-scoped (+ X-Organization-Id header)

### Read (viewer+)
```
GET  /organization
GET  /organization/members
GET  /organization/yandex/status
GET  /accounts                    # Connected accounts
GET  /projects
GET  /projects/{project}
GET  /projects/{project}/positions?days=14  # Position matrix
GET  /projects/{project}/domains
GET  /domains/{domain}
GET  /domains/{domain}/categories
GET  /categories/{category}
GET  /categories/{category}/clusters
GET  /clusters/{cluster}
GET  /projects/{project}/clusters
GET  /keywords
GET  /keywords/{keyword}
GET  /keywords/{keyword}/serp
GET  /keywords/{keyword}/serp/history
GET  /keywords/{keyword}/wordstat
GET  /keywords/{keyword}/wordstat/trends
GET  /keywords/{keyword}/wordstat/suggestions
GET  /scrapers
GET  /scraper-types
GET  /schedules
GET  /wordstat-schedules
GET  /alerts
GET  /classification/rules
GET  /site-types
GET  /regions
GET  /billing/usage
GET  /export/keywords
GET  /export/serp
GET  /serp/competitors
GET  /projects/{project}/pages
GET  /projects/{project}/pages/target-report
GET  /pages/{page}
```

### Write (manager+)
```
POST   /projects
PATCH  /projects/{project}
DELETE /projects/{project}
POST   /projects/{project}/domains
PATCH  /domains/{domain}
DELETE /domains/{domain}
POST   /categories
PATCH  /categories/{category}
DELETE /categories/{category}
POST   /clusters
PATCH  /clusters/{cluster}
DELETE /clusters/{cluster}
POST   /keywords/bulk
POST   /keywords/import
PATCH  /keywords/{keyword}
DELETE /keywords/bulk
POST   /scrapers
PATCH  /scrapers/{scraper}
DELETE /scrapers/{scraper}
POST   /scrapers/{scraper}/test
POST   /schedules
PATCH  /schedules/{schedule}
DELETE /schedules/{schedule}
POST   /schedules/{schedule}/run-now
POST   /wordstat-schedules
PATCH  /wordstat-schedules/{ws}
DELETE /wordstat-schedules/{ws}
POST   /wordstat-schedules/{ws}/run-now
POST   /alerts
PATCH  /alerts/{alert}
DELETE /alerts/{alert}
POST   /classification/rules
PATCH  /classification/rules/{rule}
DELETE /classification/rules/{rule}
PATCH  /domains/{domain}/classify
POST   /projects/{project}/pages
POST   /projects/{project}/pages/match-or-create
PATCH  /pages/{page}
DELETE /pages/{page}
PATCH  /pages/{page}/tags
POST   /pages/{page}/attach
POST   /pages/{page}/bulk-attach
DELETE /pageables/{pageable}
```

### Admin only
```
PATCH  /organization
POST   /organization/invite
DELETE /organization/members/{userId}
PATCH  /organization/members/{userId}/role
POST   /accounts
PATCH  /accounts/{account}
DELETE /accounts/{account}
POST   /accounts/{account}/test
POST   /organization/yandex/save-token
DELETE /organization/yandex
PATCH  /billing/tier
```
