import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'

export function useCompetitors(projectId: string) {
  return useQuery({
    queryKey: queryKeys.competitors.list(projectId),
    queryFn: () =>
      api
        .get('/serp/competitors', { params: { project_id: projectId } })
        .then((r) => r.data),
    enabled: !!projectId,
    staleTime: 60_000,
    gcTime: 5 * 60_000,
  })
}

export function useCompetitorPages(projectId: string, domain: string | null) {
  return useQuery({
    queryKey: queryKeys.competitors.pages(projectId, domain ?? ''),
    queryFn: () =>
      api
        .get('/serp/competitors/pages', {
          params: { project_id: projectId, ...(domain ? { domain } : {}) },
        })
        .then((r) => r.data),
    enabled: !!projectId && !!domain,
    staleTime: 60_000,
    gcTime: 5 * 60_000,
  })
}
