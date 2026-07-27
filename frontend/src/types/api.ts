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
  is_public?: boolean
  public_slug?: string | null
  public_url?: string | null
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
  effective_target_url?: string | null
  target_url_source?: 'keyword' | 'cluster' | 'category' | null
  target_match_status?: 'top3' | 'top10' | 'cannibalization' | 'missing' | 'unset' | null
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
  project_id: number
  name: string
  is_own: boolean
  type: 'own' | 'competitor' | 'satellite'
  parent_id: number | null
  parent?: Domain
  indexed_pages_count: number | null
  tags: Array<{ id: number; name: string; type: string }>
  created_at: string
  updated_at: string
}

export interface DomainIndexResult {
  id: number
  domain_id: number
  url: string
  title: string | null
  description: string | null
  snippet_links: Array<{ title: string; url: string }> | null
  position: number
  engine: string
  collected_at: string
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
  siteType?: SiteType
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
  supported_engines?: string[]
  engines?: string[]
  rate_limit: number
  is_active: boolean
}

export interface Schedule {
  id: number
  project_id?: number
  scraper_id?: number
  frequency_days: number
  frequency?: string
  last_run_at: string | null
  next_run_at: string | null
  is_active: boolean
  project?: { id: number; name: string }
  scraper?: { id: number; name: string; type: string }
  // legacy fields (may still appear)
  schedulable_type?: string
  schedulable_name?: string
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
  role?: string
}

export interface ApiToken {
  id: string
  name: string
  abilities: string[]
  last_used_at: string | null
  expires_at: string | null
  created_at: string
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

export interface Category {
  id: number
  domain_id: number
  name: string
  parent_id: number | null
  sort_order: number
  children?: Category[]
  clusters?: Cluster[]
}

export interface Cluster {
  id: number
  category_id: number
  name: string
  sort_order: number
  category?: { name: string }
}

export interface Region {
  id: number
  name: string
  engine?: string
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
  cluster?: Cluster
  region?: Region
}

export interface PositionAlert {
  id: number
  organization_id: number
  keyword_id: number
  threshold_position: number
  direction: 'drops_below' | 'rises_above'
  channel: 'email' | 'telegram'
  recipient: string
  is_active: boolean
  last_triggered_at: string | null
  keyword?: Keyword
}

export interface WordstatSchedule {
  id: number
  project_id: number
  cluster_id?: number | null
  keyword_id?: number | null
  frequency_days: number
  collect_trends: boolean
  collect_suggestions: boolean
  regions: number[]
  adapter_type: 'yandex' | 'xmlriver' | null
  last_run_at: string | null
  next_run_at: string | null
  is_active: boolean
}

export interface BillingUsage {
  keywords_used: number
  keywords_limit: number
  projects_used: number
  projects_limit: number
  scrapers_used: number
  scrapers_limit: number
  tier: string
}

export interface Page {
  id: number
  project_id: number
  domain_id: number | null
  domain?: Domain
  url: string
  path: string
  title: string | null
  page_type: 'commercial' | 'informational' | 'navigational' | 'transactional' | null
  notes: string | null
  tags: Array<{ id: number; name: string; type: string }>
  created_at: string
  updated_at: string
}

export interface Pageable {
  id: number
  page_id: number
  page?: Page
  pageable_type: string
  pageable_id: number
  engine: 'google' | 'yandex' | null
  device: 'desktop' | 'mobile' | null
  priority: number
  is_target: boolean
  created_at: string
}

export interface TargetReportRow {
  keyword_id: number
  keyword: string
  engine: string
  device: string
  cluster: string
  category: string
  target_url: string | null
  target_source: 'keyword' | 'cluster' | 'category' | null
  actual_url: string | null
  actual_position: number | null
  match: boolean
}

export interface CompetitorPage {
  domain: string
  url: string
  title: string | null
  position: number
  keyword_id: number
  keyword: string
  engine: string
  is_own: boolean
}
