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

export function useAuditResults(
  auditId: number | null,
  filters: { severity?: Severity | ''; search?: string } = {},
) {
  return useQuery({
    queryKey: ['audits', 'results', auditId, filters],
    queryFn: () =>
      api
        .get(`/audits/${auditId}/results`, {
          params: {
            severity: filters.severity || undefined,
            search: filters.search || undefined,
          },
        })
        .then((r) => r.data),
    enabled: !!auditId,
  })
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
