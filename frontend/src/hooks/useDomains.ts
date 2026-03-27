import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'

export function useDomain(domainId: string) {
  return useQuery({
    queryKey: ['domains', 'detail', domainId],
    queryFn: () => api.get(`/domains/${domainId}`).then((r) => r.data),
    enabled: !!domainId,
    staleTime: 30_000,
  })
}

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
    mutationFn: (data: {
      name: string
      is_own: boolean
      type?: string
      parent_id?: number | null
      tags?: string[]
    }) =>
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
      type?: string
      parent_id?: number | null
      tags?: string[]
    }) => api.patch(`/domains/${id}`, data).then((r) => r.data),
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

export function useIndexDomain() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ domainId, engine, limit }: { domainId: string; engine?: string; limit?: number }) =>
      api.post(`/domains/${domainId}/index`, { engine, limit }).then((r) => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: ['domains'] })
    },
  })
}

export function useDomainKeywords(domainId: string) {
  return useQuery({
    queryKey: ['domain-keywords', domainId],
    queryFn: () => api.get(`/domains/${domainId}/keywords`).then((r) => r.data),
    enabled: !!domainId,
    staleTime: 60_000,
  })
}

export function useDomainIndexResults(domainId: string, limit = 100) {
  return useQuery({
    queryKey: ['domain-index', domainId, limit],
    queryFn: () =>
      api.get(`/domains/${domainId}/index-results`, { params: { limit } }).then((r) => r.data),
    enabled: !!domainId,
    staleTime: 60_000,
  })
}
