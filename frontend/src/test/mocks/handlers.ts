import { http, HttpResponse } from 'msw'

const BASE = '/api/v1'

export const mockUser = {
  id: 1,
  name: 'Test User',
  email: 'test@example.com',
  organizations: [
    { id: 1, name: 'Test Org', slug: 'test-org', pivot: { role: 'owner' } },
  ],
}

export const mockProjects = [
  { id: 1, name: 'Project Alpha', description: 'First project', domains_count: 3, keywords_count: 42, created_at: '2025-01-01T00:00:00Z' },
  { id: 2, name: 'Project Beta', description: 'Second project', domains_count: 1, keywords_count: 18, created_at: '2025-02-15T00:00:00Z' },
]

export const mockKeywords = [
  { id: 1, keyword: 'seo tools', engine: 'google', device: 'desktop', latest_position: 5, position_change: 3, frequency: 1200, our_url: 'https://example.com/seo' },
  { id: 2, keyword: 'web analytics', engine: 'yandex', device: 'desktop', latest_position: 12, position_change: -2, frequency: 800, our_url: 'https://example.com/analytics' },
  { id: 3, keyword: 'rank tracker', engine: 'google', device: 'mobile', latest_position: null, position_change: null, frequency: null, our_url: null },
]

export const mockSerpResults = [
  { position: 1, domain: 'competitor.com', site_type: { id: 1, name: 'Aggregator', color: '#3B82F6' }, title: 'Best SEO Tools', url: 'https://competitor.com/seo', is_own: false },
  { position: 2, domain: 'example.com', site_type: { id: 2, name: 'Commercial', color: '#10B981' }, title: 'Our SEO Page', url: 'https://example.com/seo', is_own: true },
  { position: 3, domain: 'another.com', site_type: null, title: 'SEO Guide', url: 'https://another.com/guide', is_own: false },
]

export const mockSerpHistory = [
  { date: '2025-03-01', position: 5, url: 'https://example.com/seo' },
  { date: '2025-03-08', position: 3, url: 'https://example.com/seo' },
  { date: '2025-03-15', position: 2, url: 'https://example.com/seo' },
]

export const mockWordstat = { exact: 1200, broad: 5600, phrase: 3400 }

export const mockWordstatTrends = [
  { month: '2025-01', value: 1100 },
  { month: '2025-02', value: 1250 },
  { month: '2025-03', value: 1200 },
]

export const mockWordstatSuggestions = [
  { suggestion: 'seo tools free', frequency: 800, type: 'related' },
  { suggestion: 'best seo tools 2025', frequency: 600, type: 'related' },
]

export const mockDashboardSummary = {
  top3: 5,
  top10: 18,
  top20: 32,
  top100: 45,
  total_keywords: 60,
  google_keywords: 35,
  yandex_keywords: 25,
}

export const mockMembers = [
  { id: 1, name: 'Test User', email: 'test@example.com', role: 'owner' },
  { id: 2, name: 'Jane Doe', email: 'jane@example.com', role: 'admin' },
  { id: 3, name: 'Bob Smith', email: 'bob@example.com', role: 'member' },
]

export const mockPages = [
  { id: 1, project_id: 1, domain_id: 1, url: 'https://example.com/seo', path: '/seo', title: 'SEO Page', page_type: 'commercial' as const, notes: null, tags: [], created_at: '2025-01-01T00:00:00Z', updated_at: '2025-01-01T00:00:00Z' },
  { id: 2, project_id: 1, domain_id: 1, url: 'https://example.com/blog', path: '/blog', title: 'Blog Page', page_type: 'informational' as const, notes: null, tags: [], created_at: '2025-01-01T00:00:00Z', updated_at: '2025-01-01T00:00:00Z' },
  { id: 3, project_id: 1, domain_id: 2, url: 'https://competitor.com/landing', path: '/landing', title: 'Landing Page', page_type: 'transactional' as const, notes: null, tags: [], created_at: '2025-01-01T00:00:00Z', updated_at: '2025-01-01T00:00:00Z' },
  { id: 4, project_id: 1, domain_id: null, url: 'https://another.com/nav', path: '/nav', title: 'Nav Page', page_type: 'navigational' as const, notes: null, tags: [], created_at: '2025-01-01T00:00:00Z', updated_at: '2025-01-01T00:00:00Z' },
  { id: 5, project_id: 1, domain_id: null, url: 'https://unknown.com/page', path: '/page', title: 'Unknown Type', page_type: null, notes: null, tags: [], created_at: '2025-01-01T00:00:00Z', updated_at: '2025-01-01T00:00:00Z' },
]

export const mockClusters = [
  { id: 1, name: 'SEO Keywords', category: { name: 'Marketing' } },
  { id: 2, name: 'Brand Keywords', category: null },
]

export const mockRegions = {
  google: [
    { id: 1, name: 'Moscow' },
    { id: 2, name: 'Saint Petersburg' },
  ],
  yandex: [
    { id: 3, name: 'Moscow' },
    { id: 4, name: 'Saint Petersburg' },
  ],
}

export const handlers = [
  // Auth
  http.post(`${BASE}/auth/login`, async ({ request }) => {
    const body = await request.json() as Record<string, string>
    if (body.email === 'test@example.com' && body.password === 'password') {
      return HttpResponse.json({ user: mockUser, token: 'mock-token-123' })
    }
    return HttpResponse.json(
      { errors: [{ status: '401', title: 'Unauthorized', detail: 'Invalid credentials' }] },
      { status: 401 },
    )
  }),

  http.post(`${BASE}/auth/register`, async ({ request }) => {
    const body = await request.json() as Record<string, string>
    return HttpResponse.json({
      user: { ...mockUser, name: body.name, email: body.email },
      token: 'mock-token-new',
      organization: { id: 1, name: body.organization_name, slug: 'new-org', pivot: { role: 'owner' } },
    })
  }),

  http.get(`${BASE}/auth/me`, () => {
    return HttpResponse.json({ user: mockUser })
  }),

  http.post(`${BASE}/auth/logout`, () => {
    return HttpResponse.json({ message: 'Logged out' })
  }),

  // Dashboard
  http.get(`${BASE}/dashboard/summary`, () => {
    return HttpResponse.json(mockDashboardSummary)
  }),

  // Projects
  http.get(`${BASE}/projects`, () => {
    return HttpResponse.json({ data: mockProjects })
  }),

  http.get(`${BASE}/projects/:id`, ({ params }) => {
    const project = mockProjects.find((p) => p.id === Number(params.id))
    return project
      ? HttpResponse.json({ data: project })
      : HttpResponse.json({ errors: [{ status: '404', title: 'Not Found' }] }, { status: 404 })
  }),

  // Keywords
  http.get(`${BASE}/keywords`, ({ request }) => {
    const url = new URL(request.url)
    const engine = url.searchParams.get('filter[engine]')
    const search = url.searchParams.get('filter[keyword]')
    let filtered = [...mockKeywords]
    if (engine) filtered = filtered.filter((k) => k.engine === engine)
    if (search) filtered = filtered.filter((k) => k.keyword.toLowerCase().includes(search.toLowerCase()))
    return HttpResponse.json({
      data: filtered,
      meta: { current_page: 1, last_page: 1, per_page: 20, total: filtered.length },
    })
  }),

  http.get(`${BASE}/projects/:projectId/keywords/:keywordId`, ({ params }) => {
    const kw = mockKeywords.find((k) => k.id === Number(params.keywordId))
    return kw
      ? HttpResponse.json({ data: kw })
      : HttpResponse.json({ errors: [{ status: '404', title: 'Not Found' }] }, { status: 404 })
  }),

  http.post(`${BASE}/keywords/import`, () => {
    return HttpResponse.json({ message: 'Imported successfully' })
  }),

  // SERP
  http.get(`${BASE}/keywords/:keywordId/serp`, () => {
    return HttpResponse.json({ data: mockSerpResults })
  }),

  http.get(`${BASE}/keywords/:keywordId/serp/dates`, () => {
    return HttpResponse.json({ data: ['2025-03-15', '2025-03-08', '2025-03-01'] })
  }),

  http.get(`${BASE}/keywords/:keywordId/serp/history`, () => {
    return HttpResponse.json({ data: mockSerpHistory })
  }),

  // Wordstat
  http.get(`${BASE}/keywords/:keywordId/wordstat`, () => {
    return HttpResponse.json({ data: mockWordstat })
  }),

  http.get(`${BASE}/keywords/:keywordId/wordstat/trends`, () => {
    return HttpResponse.json({ data: mockWordstatTrends })
  }),

  http.get(`${BASE}/keywords/:keywordId/wordstat/suggestions`, () => {
    return HttpResponse.json({ data: mockWordstatSuggestions })
  }),

  // Regions & Clusters
  http.get(`${BASE}/regions`, () => {
    return HttpResponse.json(mockRegions)
  }),

  http.get(`${BASE}/projects/:projectId/clusters`, () => {
    return HttpResponse.json(mockClusters)
  }),

  // Pages
  http.get(`${BASE}/projects/:projectId/pages`, () => {
    return HttpResponse.json({ data: mockPages })
  }),

  // Organization
  http.get(`${BASE}/organization/members`, () => {
    return HttpResponse.json({ data: mockMembers })
  }),

  http.post(`${BASE}/organization/invite`, () => {
    return HttpResponse.json({ message: 'Invited' })
  }),

  http.delete(`${BASE}/organization/members/:userId`, () => {
    return HttpResponse.json({ message: 'Removed' })
  }),

  http.put(`${BASE}/organization/members/:userId/role`, () => {
    return HttpResponse.json({ message: 'Updated' })
  }),
]
