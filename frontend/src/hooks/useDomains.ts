import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'

export function useDomains(projectId: string) {
  return useQuery({
    queryKey: ['domains', projectId],
    queryFn: () => api.get(`/projects/${projectId}/domains`).then(r => r.data),
    enabled: !!projectId,
  })
}

export function useCreateDomain(projectId: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: { name: string; is_own: boolean }) =>
      api.post(`/projects/${projectId}/domains`, data).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['domains', projectId] }),
  })
}

export function useUpdateDomain() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, ...data }: { id: number; name?: string; is_own?: boolean }) =>
      api.put(`/domains/${id}`, data).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['domains'] }),
  })
}

export function useDeleteDomain() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api.delete(`/domains/${id}`).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['domains'] }),
  })
}
