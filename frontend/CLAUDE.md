# Frontend (React 19 + TanStack)

## Stack

- React 19, TypeScript, Vite 8
- TanStack Router (file-based), TanStack Query (server state)
- shadcn/ui (base-ui), Tailwind CSS 4
- Recharts (position/trend charts)
- Axios (API client with JSON:API interceptor)
- i18next (RU + EN)

## Project Structure

```
src/
├── routes/              # Pages (file-based routing)
│   ├── index.tsx        # Dashboard
│   ├── projects/        # Project detail + tabs
│   │   └── $projectId/
│   │       ├── keywords.tsx      # Position matrix table (main view)
│   │       ├── keywords/$keywordId.tsx  # Keyword detail (SERP, History, Wordstat)
│   │       ├── domains.tsx
│   │       ├── competitors.tsx
│   │       └── categories.tsx
│   ├── classification/  # Rules + domain classifications
│   ├── scrapers/        # Scraper CRUD
│   ├── schedules/       # SERP schedule CRUD
│   ├── wordstat-schedules/
│   ├── alerts/
│   └── settings/        # Org, billing, members, accounts
├── hooks/               # React Query hooks (1 file per resource)
├── components/
│   ├── charts/          # PositionChart, TrendChart, TopDistributionChart
│   ├── ui/              # shadcn/ui components
│   ├── AppLayout.tsx    # Sidebar layout
│   └── OrgSwitcher.tsx  # Organization dropdown
├── lib/
│   ├── api.ts           # Axios instance + JSON:API flatten interceptor
│   └── query-keys.ts    # Centralized cache keys
├── types/api.ts         # All TypeScript interfaces
└── i18n/                # en.json, ru.json
```

## API Layer

- `api.ts` interceptor auto-flattens JSON:API responses: `{ type, id, attributes }` → `{ id, ...attributes }`
- Relationships flattened into parent object
- Auth: Bearer token + X-Organization-Id header from localStorage
- 401 → redirect to /login

## Keywords Page (Position Matrix)

Main monitoring table at `/projects/$projectId/keywords`:
- Rows: keywords (merged by engine)
- Columns: dates × engines (G/Я)
- Features: multi-select filters, multi-field grouping, sortable columns, saved presets
- Data from `GET /projects/{id}/positions?days=14`
- Bulk operations: delete, move to cluster

## Key Patterns

### Select Components (base-ui)
ALWAYS add `label` prop to SelectItem — without it, trigger shows `value` (often an ID):
```tsx
<SelectItem value={String(id)} label={displayText}>{displayText}</SelectItem>
```

### Route with children
Keywords route renders matrix table OR Outlet for keyword detail:
```tsx
const matches = useMatches()
if (matches.some(m => m.id.includes('keywordId'))) return <Outlet />
return <KeywordsTable />
```

### Data parsing from API
Always handle both flat array and `{ data: [...] }` envelope:
```tsx
const items = useMemo(() => {
  const d = data?.data ?? data
  return Array.isArray(d) ? d : []
}, [data])
```

### Frequency formatting
```tsx
function fmtFreq(n: number | null): string {
  if (n == null) return '—'
  if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}кк`
  if (n >= 1_000) return `${(n / 1_000).toFixed(0)}к`
  return String(n)
}
```

## Common Pitfalls

- `useMatches` not `useMatch` for child route detection
- Regions from API can be flat array or grouped object — handle both
- JSON:API flattening: relationship key is camelCase (`siteType` not `site_type`)
- Import dialog: checkboxes for engine/device, creates keywords for each combination
- Presets saved to localStorage under `serp-filter-presets` key
