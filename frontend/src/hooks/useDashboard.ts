import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'
import type { DashboardSummary } from '@/types/api'

export function useDashboardSummary(projectId?: number) {
  return useQuery<DashboardSummary>({
    queryKey: queryKeys.dashboard.summary(projectId),
    queryFn: () =>
      api
        .get('/dashboard/summary', {
          params: projectId ? { project_id: projectId } : undefined,
        })
        .then((r) => r.data),
    staleTime: 30_000,
  })
}
