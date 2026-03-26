import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'
export function useDomains(projectId: string) {
  return useQuery({
    queryKey: queryKeys.domains.list(projectId),
    queryFn: () =>
      api.get(`/projects/${projectId}/domains`).then((r) => r.data),
    enabled: !!projectId,
    staleTime: 30_000,
  })
}

export function useCreateDomain(projectId: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: { name: string; is_own: boolean }) =>
      api
        .post(`/projects/${projectId}/domains`, data)
        .then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.domains.list(projectId) }),
  })
}

export function useUpdateDomain() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      ...data
    }: {
      id: number
      name?: string
      is_own?: boolean
    }) => api.put(`/domains/${id}`, data).then((r) => r.data),
    onMutate: async (variables) => {
      // Optimistic update: we don't know which project, so just cancel all domain queries
      await qc.cancelQueries({ queryKey: queryKeys.domains.all })
      return { variables }
    },
    onSettled: () =>
      qc.invalidateQueries({ queryKey: queryKeys.domains.all }),
  })
}

export function useDeleteDomain() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) =>
      api.delete(`/domains/${id}`).then((r) => r.data),
    onSettled: () =>
      qc.invalidateQueries({ queryKey: queryKeys.domains.all }),
  })
}
