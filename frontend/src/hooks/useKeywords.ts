import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'

interface KeywordsParams {
  projectId: string
  page?: number
  per_page?: number
  search?: string
  engine?: string
  device?: string
}

export function useKeywords(params: KeywordsParams) {
  const { projectId, page = 1, per_page = 20, search, engine, device } = params
  const filters = { page, per_page, search, engine, device }

  return useQuery({
    queryKey: queryKeys.keywords.list(projectId, filters),
    queryFn: () =>
      api
        .get('/keywords', {
          params: {
            page,
            per_page,
            'filter[keyword]': search || undefined,
            'filter[engine]': engine || undefined,
            'filter[device]': device || undefined,
          },
        })
        .then((r) => r.data),
    enabled: !!projectId,
    staleTime: 30_000,
    gcTime: 5 * 60_000,
  })
}

export function useKeyword(projectId: string, keywordId: string) {
  return useQuery({
    queryKey: queryKeys.keywords.detail(projectId, keywordId),
    queryFn: () =>
      api
        .get(`/keywords/${keywordId}`)
        .then((r) => r.data),
    enabled: !!projectId && !!keywordId,
    staleTime: 30_000,
  })
}

export function useImportKeywords() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: {
      keywords: string[]
      engine: string
      device?: string
      cluster_id: number
      region_id: number
    }) => api.post('/keywords/import', data).then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.keywords.all }),
  })
}

export function useRegions() {
  return useQuery({
    queryKey: queryKeys.regions,
    queryFn: () => api.get('/regions').then((r) => r.data),
    staleTime: 5 * 60_000, // regions rarely change
    gcTime: 30 * 60_000,
  })
}

export function useProjectClusters(projectId: string) {
  return useQuery({
    queryKey: queryKeys.clusters.byProject(projectId),
    queryFn: () =>
      api.get(`/projects/${projectId}/clusters`).then((r) => r.data),
    enabled: !!projectId,
    staleTime: 60_000,
  })
}

export function useBulkCreateKeywords() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: {
      keywords: Array<{
        keyword: string
        cluster_id: number
        engine: string
        device: string
        region_id: number
      }>
    }) => api.post('/keywords/bulk', data).then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.keywords.all }),
  })
}

export function useUpdateKeyword() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      ...data
    }: {
      id: number
      cluster_id?: number
      engine?: string
      device?: string
      region_id?: number
    }) => api.patch(`/keywords/${id}`, data).then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.keywords.all }),
  })
}

export function useDeleteKeywords() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (ids: number[]) =>
      api
        .delete('/keywords/bulk', { data: { ids } })
        .then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.keywords.all }),
  })
}
