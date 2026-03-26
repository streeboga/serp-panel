// ============================================================
// Shared API response types
// ============================================================

/** Paginated list envelope returned by Laravel */
export interface PaginatedResponse<T> {
  data: T[]
  meta: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
  links?: {
    first?: string
    last?: string
    prev?: string | null
    next?: string | null
  }
}

// ============================================================
// Domain entities
// ============================================================

export interface Project {
  id: number
  name: string
  description?: string
  domains_count?: number
  keywords_count?: number
  created_at?: string
  updated_at?: string
}

export interface Keyword {
  id: number
  keyword: string
  engine: 'google' | 'yandex'
  device: 'desktop' | 'mobile'
  latest_position: number | null
  position_change: number | null
  frequency: number | null
  our_url: string | null
}

export interface SiteType {
  id: number
  name: string
  color: string
}

export interface SerpResult {
  position: number
  domain: string
  site_type?: SiteType | null
  title: string
  url: string
  is_own?: boolean
}

export interface SerpHistoryItem {
  date: string
  position: number | null
  url?: string
}

export interface Domain {
  id: number
  name: string
  is_own: boolean
}

export interface Competitor {
  domain: string
  site_type: string | null
  site_type_color: string | null
  is_own: boolean
  top3: number
  top10: number
  top20: number
  total_keywords: number
}

export interface ClassificationRule {
  id: number
  rule_type: string
  pattern: string
  site_type?: SiteType
  priority: number
  is_system: boolean
}

export interface DomainClassification {
  id: number
  domain: string
  site_type?: SiteType | null
  classified_by?: string
}

export interface Scraper {
  id: number
  name: string
  type: string
  base_url: string
  engines: string[]
  rate_limit: number
  is_active: boolean
}

export interface Schedule {
  id: number
  schedulable_type: string
  schedulable_name?: string
  frequency: string
  last_run_at: string | null
  next_run_at: string | null
  is_active: boolean
}

export interface Member {
  id: number
  name: string
  email: string
  role: string
}

export interface Organization {
  id: number
  name: string
  slug: string
  pivot: { role: string }
}

export interface User {
  id: number
  name: string
  email: string
  organizations: Organization[]
}

export interface DashboardSummary {
  top3: number
  top10: number
  top20: number
  top100: number
  total_keywords: number
  google_keywords: number
  yandex_keywords: number
}

export interface WordstatData {
  exact: number | null
  broad: number | null
  phrase: number | null
}

export interface WordstatTrend {
  month: string
  value: number
}

export interface WordstatSuggestion {
  suggestion: string
  frequency: number
  type?: string
}

export interface Cluster {
  id: number
  name: string
  category?: { name: string }
}

export interface Region {
  id: number
  name: string
}

export type RegionsByEngine = Record<string, Region[]>

export interface KeywordDetail {
  id: number
  keyword: string
  engine: 'google' | 'yandex'
  device: 'desktop' | 'mobile'
  latest_position: number | null
  position_change: number | null
  frequency: number | null
  our_url: string | null
}
