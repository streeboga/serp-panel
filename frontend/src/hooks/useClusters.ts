import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'

export function useClustersByCategory(categoryId: string) {
  return useQuery({
    queryKey: queryKeys.clusters.byCategory(categoryId),
    queryFn: () =>
      api.get(`/categories/${categoryId}/clusters`).then((r) => r.data),
    enabled: !!categoryId,
    staleTime: 30_000,
  })
}

export function useCluster(id: string) {
  return useQuery({
    queryKey: queryKeys.clusters.detail(id),
    queryFn: () => api.get(`/clusters/${id}`).then((r) => r.data),
    enabled: !!id,
    staleTime: 30_000,
  })
}

export function useCreateCluster() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: {
      category_id: number
      name: string
      sort_order?: number
    }) => api.post('/clusters', data).then((r) => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.clusters.all })
      qc.invalidateQueries({ queryKey: queryKeys.categories.all })
    },
  })
}

export function useUpdateCluster() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      ...data
    }: {
      id: number
      name?: string
      category_id?: number
      sort_order?: number
    }) => api.patch(`/clusters/${id}`, data).then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.clusters.all }),
  })
}

export function useDeleteCluster() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) =>
      api.delete(`/clusters/${id}`).then((r) => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.clusters.all })
      qc.invalidateQueries({ queryKey: queryKeys.categories.all })
    },
  })
}
