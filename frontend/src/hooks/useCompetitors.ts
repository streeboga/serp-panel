import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'

export function useCompetitors(projectId: string) {
  return useQuery({
    queryKey: ['competitors', projectId],
    queryFn: () => api.get('/serp/competitors', { params: { project_id: projectId } }).then(r => r.data),
    enabled: !!projectId,
  })
}
