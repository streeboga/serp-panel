import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'

export function useCategories(domainId: string) {
  return useQuery({
    queryKey: queryKeys.categories.byDomain(domainId),
    queryFn: () =>
      api.get(`/domains/${domainId}/categories`).then((r) => r.data),
    enabled: !!domainId,
    staleTime: 30_000,
  })
}

export function useProjectCategories(projectId: string) {
  return useQuery({
    queryKey: queryKeys.categories.byProject(projectId),
    queryFn: () =>
      api.get(`/projects/${projectId}/categories`).then((r) => r.data),
    enabled: !!projectId,
    staleTime: 30_000,
  })
}

export function useCategory(id: string) {
  return useQuery({
    queryKey: queryKeys.categories.detail(id),
    queryFn: () => api.get(`/categories/${id}`).then((r) => r.data),
    enabled: !!id,
    staleTime: 30_000,
  })
}

export function useCreateCategory() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: {
      domain_id: number
      name: string
      parent_id?: number | null
      sort_order?: number
    }) => api.post('/categories', data).then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.categories.all }),
  })
}

export function useUpdateCategory() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      ...data
    }: {
      id: number
      name?: string
      parent_id?: number | null
      sort_order?: number
    }) => api.patch(`/categories/${id}`, data).then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.categories.all }),
  })
}

export function useDeleteCategory() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) =>
      api.delete(`/categories/${id}`).then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.categories.all }),
  })
}
