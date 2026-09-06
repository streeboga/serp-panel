import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'

/** Ресурс, подтверждённый в Яндекс.Вебмастере. */
export interface WebmasterSite {
  host_id: string
  url: string
  verified: boolean
}

/** Ресурс, подтверждённый в Google Search Console. */
export interface ConsoleSite {
  site_url: string
  permission: string
}

/** Список либо причина, почему его нет: «не подключено» и «пусто» — разные вещи. */
export interface SiteList<T> {
  connected: boolean
  reason?: string
  sites?: T[]
}

export interface MappedDomain {
  id: string
  name: string
  project_id: string
  project_name: string | null
  webmaster_host_id: string | null
  search_console_site: string | null
  metrika_counter_id: number | null
  suggested: {
    webmaster_host_id: string | null
    search_console_site: string | null
  }
}

export interface IntegrationSites {
  webmaster: SiteList<WebmasterSite>
  search_console: SiteList<ConsoleSite>
  domains: MappedDomain[]
}

export function useIntegrationSites() {
  return useQuery<IntegrationSites>({
    queryKey: queryKeys.integrations.sites(),
    queryFn: () => api.get('/integrations/sites').then((r) => r.data),
    staleTime: 60_000,
  })
}

export function useGoogleStatus() {
  return useQuery<{ configured: boolean; connected: boolean }>({
    queryKey: queryKeys.integrations.google(),
    queryFn: () => api.get('/organization/google/status').then((r) => r.data),
    staleTime: 60_000,
  })
}

export function useGoogleRedirect() {
  return useMutation({
    mutationFn: () => api.get('/auth/google/redirect').then((r) => r.data as { url: string }),
  })
}

export function useGoogleDisconnect() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => api.delete('/organization/google').then((r) => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.integrations.all }),
  })
}

/** Привязка внешних ресурсов к домену. */
export function useMapDomain() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      ...data
    }: {
      id: string
      webmaster_host_id?: string | null
      search_console_site?: string | null
      metrika_counter_id?: number | null
    }) => api.patch(`/domains/${id}`, data).then((r) => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.integrations.all }),
  })
}
