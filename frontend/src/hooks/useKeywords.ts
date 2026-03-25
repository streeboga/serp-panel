import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'

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
  return useQuery({
    queryKey: ['keywords', projectId, { page, per_page, search, engine, device }],
    queryFn: () =>
      api.get(`/projects/${projectId}/keywords`, {
        params: { page, per_page, search, engine, device },
      }).then(r => r.data),
    enabled: !!projectId,
  })
}

export function useKeyword(projectId: string, keywordId: string) {
  return useQuery({
    queryKey: ['keywords', projectId, keywordId],
    queryFn: () =>
      api.get(`/projects/${projectId}/keywords/${keywordId}`).then(r => r.data),
    enabled: !!projectId && !!keywordId,
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
    }) => api.post('/keywords/import', data).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['keywords'] }),
  })
}

export function useRegions() {
  return useQuery({
    queryKey: ['regions'],
    queryFn: () => api.get('/regions').then(r => r.data),
  })
}

export function useProjectClusters(projectId: string) {
  return useQuery({
    queryKey: ['project-clusters', projectId],
    queryFn: () => api.get(`/projects/${projectId}/clusters`).then(r => r.data),
    enabled: !!projectId,
  })
}

export function useDeleteKeyword(projectId: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (keywordId: string) =>
      api.delete(`/projects/${projectId}/keywords/${keywordId}`).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['keywords', projectId] }),
  })
}
