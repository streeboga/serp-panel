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
