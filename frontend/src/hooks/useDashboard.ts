import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'

export function useDashboardSummary(projectId?: number) {
  return useQuery({
    queryKey: ['dashboard-summary', projectId],
    queryFn: () =>
      api.get('/dashboard/summary', {
        params: projectId ? { project_id: projectId } : undefined,
      }).then(r => r.data),
  })
}
