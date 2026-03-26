/**
 * Centralized query key factory for consistent cache management.
 * Each entity has: all(), list(filters), detail(id) pattern.
 */
export const queryKeys = {
  projects: {
    all: ['projects'] as const,
    list: () => [...queryKeys.projects.all, 'list'] as const,
    detail: (id: string) => [...queryKeys.projects.all, id] as const,
  },

  keywords: {
    all: ['keywords'] as const,
    list: (projectId: string, filters?: Record<string, unknown>) =>
      [...queryKeys.keywords.all, projectId, filters] as const,
    detail: (projectId: string, keywordId: string) =>
      [...queryKeys.keywords.all, projectId, keywordId] as const,
  },

  serp: {
    all: ['serp'] as const,
    results: (keywordId: string, params?: Record<string, unknown>) =>
      [...queryKeys.serp.all, 'results', keywordId, params] as const,
    dates: (keywordId: string) =>
      [...queryKeys.serp.all, 'dates', keywordId] as const,
    history: (keywordId: string) =>
      [...queryKeys.serp.all, 'history', keywordId] as const,
  },

  domains: {
    all: ['domains'] as const,
    list: (projectId: string) =>
      [...queryKeys.domains.all, projectId] as const,
  },

  categories: {
    all: ['categories'] as const,
    byDomain: (domainId: string) =>
      [...queryKeys.categories.all, 'domain', domainId] as const,
    detail: (id: string) =>
      [...queryKeys.categories.all, id] as const,
  },

  clusters: {
    all: ['clusters'] as const,
    byProject: (projectId: string) =>
      [...queryKeys.clusters.all, 'project', projectId] as const,
    byCategory: (categoryId: string) =>
      [...queryKeys.clusters.all, 'category', categoryId] as const,
    detail: (id: string) =>
      [...queryKeys.clusters.all, id] as const,
  },

  competitors: {
    all: ['competitors'] as const,
    list: (projectId: string) =>
      [...queryKeys.competitors.all, projectId] as const,
  },

  classification: {
    rules: ['classification-rules'] as const,
    siteTypes: ['site-types'] as const,
    domains: ['domain-classifications'] as const,
  },

  scrapers: {
    all: ['scrapers'] as const,
  },

  schedules: {
    all: ['schedules'] as const,
  },

  regions: ['regions'] as const,

  wordstat: {
    data: (keywordId: string) => ['wordstat', keywordId] as const,
    trends: (keywordId: string) => ['wordstat-trends', keywordId] as const,
    suggestions: (keywordId: string) =>
      ['wordstat-suggestions', keywordId] as const,
  },

  wordstatSchedules: {
    all: ['wordstat-schedules'] as const,
  },

  alerts: {
    all: ['alerts'] as const,
    detail: (id: string) => ['alerts', id] as const,
  },

  billing: {
    usage: ['billing', 'usage'] as const,
  },

  dashboard: {
    summary: (projectId?: number) =>
      ['dashboard-summary', projectId] as const,
  },

  organization: {
    all: ['organization'] as const,
    members: ['organization', 'members'] as const,
  },

  organizations: {
    all: ['organizations'] as const,
  },

  positionMatrix: {
    all: ['position-matrix'] as const,
    list: (projectId: string, days?: number) =>
      [...queryKeys.positionMatrix.all, projectId, days] as const,
  },

  yandex: {
    all: ['yandex'] as const,
    status: () => [...queryKeys.yandex.all, 'status'] as const,
  },

  accounts: {
    all: ['accounts'] as const,
  },
} as const
