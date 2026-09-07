import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'

export type AuditScope = 'site' | 'pages' | 'url'
export type Severity = 'critical' | 'warning' | 'notice'

export interface Finding {
  /** Код проверки — им же её включают и выключают. */
  check: string
  /** Код конкретного дефекта: код проверки плюс суффикс. */
  code: string
  category: string
  severity: Severity
  message: string
  value: unknown
  expected: unknown
  /** «Как исправить» — подбирается на сервере по коду находки, есть не у всех. */
  fix?: string
}

export interface CheckCatalogEntry {
  category: string
  title: string
  checks: Array<{ code: string; title: string }>
}

export interface SiteAudit {
  id: number
  scope: AuditScope
  status: 'pending' | 'running' | 'completed' | 'failed' | 'cancelled'
  progress: number
  /** Чем сузили прогон; null — запускали всё. Нужно, чтобы понять, какие проверки пройдены. */
  groups: string[] | null
  check_codes: string[] | null
  pages_total: number
  pages_done: number
  score: number | null
  issues_critical: number
  issues_warning: number
  issues_notice: number
  findings: Finding[]
  metrics: Record<string, unknown>
  error: string | null
  started_at: string | null
  finished_at: string | null
  created_at: string
}

export interface PageAuditResult {
  id: number
  url: string
  path: string
  page_id: string | null
  http_status: number | null
  response_time_ms: number | null
  score: number | null
  issues_critical: number
  issues_warning: number
  issues_notice: number
  findings: Finding[]
  metrics: Record<string, unknown>
  error: string | null
}

const RUNNING = ['pending', 'running']

export function useAudits(projectId: string) {
  return useQuery({
    queryKey: ['audits', projectId],
    queryFn: () => api.get(`/projects/${projectId}/audits`).then((r) => r.data),
    enabled: !!projectId,
    // Пока прогон идёт, список обновляем сам — прогресс меняется на глазах.
    refetchInterval: (query) => {
      const audits = (query.state.data as { data?: SiteAudit[] } | undefined)?.data ?? []
      return audits.some((a) => RUNNING.includes(a.status)) ? 3000 : false
    },
  })
}

export function useAudit(auditId: number | null) {
  return useQuery({
    queryKey: ['audits', 'detail', auditId],
    queryFn: () => api.get(`/audits/${auditId}`).then((r) => r.data),
    enabled: !!auditId,
    refetchInterval: (query) => {
      const audit = (query.state.data as { data?: SiteAudit } | undefined)?.data
      return audit && RUNNING.includes(audit.status) ? 3000 : false
    },
  })
}

export interface PageMeta {
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export const RESULTS_PER_PAGE = 50

export function useAuditResults(
  auditId: number | null,
  filters: { severity?: Severity | ''; search?: string } = {},
  page = 1,
) {
  return useQuery<{ data: PageAuditResult[]; meta?: PageMeta }>({
    queryKey: ['audits', 'results', auditId, filters, page],
    queryFn: () =>
      api
        .get(`/audits/${auditId}/results`, {
          params: {
            severity: filters.severity || undefined,
            search: filters.search || undefined,
            page,
            per_page: RESULTS_PER_PAGE,
          },
        })
        .then((r) => r.data),
    enabled: !!auditId,
    // Прошлая страница остаётся на экране, пока грузится следующая — без мигания.
    placeholderData: (previous) => previous,
  })
}

/** Наборы выгрузки — те же, что отдаёт API. */
export const EXPORT_DATASETS = [
  { key: 'pages', label: 'Страницы с кодами ответов' },
  { key: 'meta', label: 'Title, Description и заголовки' },
  { key: 'broken', label: 'Битые ссылки и файлы' },
  { key: 'findings', label: 'Находки построчно' },
  { key: 'findings', label: 'Находки + пройденные проверки', includePassed: true },
] as const

/**
 * Скачивание CSV: ссылка <a href> не понесёт Bearer-токен, поэтому файл тянем
 * через axios и отдаём браузеру как blob.
 */
export async function downloadAuditExport(
  auditId: number,
  dataset: string,
  includePassed = false,
): Promise<void> {
  const response = await api.get(`/audits/${auditId}/export/${dataset}`, {
    params: includePassed ? { include_passed: 1 } : undefined,
    responseType: 'blob',
  })
  const url = URL.createObjectURL(response.data as Blob)
  const a = document.createElement('a')
  a.href = url
  a.download = `audit-${auditId}-${dataset}${includePassed ? '-with-passed' : ''}.csv`
  a.click()
  URL.revokeObjectURL(url)
}

export function useStartAudit(projectId: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: {
      scope: AuditScope
      domain_id?: number | null
      url?: string
      page_ids?: number[]
      groups?: string[]
      check_codes?: string[]
    }) => api.post(`/projects/${projectId}/audits`, data).then((r) => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['audits', projectId] }),
  })
}

export function useCancelAudit() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (auditId: number) => api.delete(`/audits/${auditId}`).then((r) => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['audits'] }),
  })
}

export function usePageAudit(pageId: number | null) {
  return useQuery({
    queryKey: ['audits', 'page', pageId],
    queryFn: () => api.get(`/pages/${pageId}/audit`).then((r) => r.data),
    enabled: !!pageId,
    retry: false,
  })
}

/**
 * Каталог проверок: категории и их проверки. Наполняется установленными пакетами,
 * поэтому список приходит с сервера, а не хардкодится здесь.
 */
export function useCheckCatalog() {
  return useQuery({
    queryKey: ['audits', 'catalog'],
    queryFn: () => api.get('/audit/checks').then((r) => r.data),
    staleTime: Infinity,
  })
}
