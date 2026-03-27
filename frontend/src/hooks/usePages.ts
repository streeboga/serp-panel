import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'

interface PagesParams {
  projectId: string
  page_type?: string
  domain_id?: string
  tags?: string
  search?: string
}

export function usePages(params: PagesParams) {
  const { projectId, page_type, domain_id, tags, search } = params
  return useQuery({
    queryKey: queryKeys.pages.list(projectId, { page_type, domain_id, tags, search }),
    queryFn: () =>
      api
        .get(`/projects/${projectId}/pages`, {
          params: {
            'filter[page_type]': page_type || undefined,
            'filter[domain_id]': domain_id || undefined,
            'filter[tags]': tags || undefined,
            'filter[url]': search || undefined,
          },
        })
        .then((r) => r.data),
    enabled: !!projectId,
    staleTime: 30_000,
  })
}

export function usePage(pageId: string) {
  return useQuery({
    queryKey: queryKeys.pages.detail(pageId),
    queryFn: () => api.get(`/pages/${pageId}`).then((r) => r.data),
    enabled: !!pageId,
    staleTime: 30_000,
  })
}

export function useCreatePage(projectId: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: Record<string, unknown>) =>
      api.post(`/projects/${projectId}/pages`, data).then((r) => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.pages.list(projectId) })
    },
  })
}

export function useUpdatePage() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ pageId, data }: { pageId: string; data: Record<string, unknown> }) =>
      api.patch(`/pages/${pageId}`, data).then((r) => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.pages.all })
    },
  })
}

export function useDeletePage() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (pageId: string) => api.delete(`/pages/${pageId}`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.pages.all })
    },
  })
}

export function useSyncTags() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ pageId, tags }: { pageId: string; tags: string[] }) =>
      api.patch(`/pages/${pageId}/tags`, { tags }).then((r) => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.pages.all })
    },
  })
}

export function useAttachPage() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ pageId, data }: { pageId: string; data: Record<string, unknown> }) =>
      api.post(`/pages/${pageId}/attach`, data).then((r) => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.pages.all })
      qc.invalidateQueries({ queryKey: queryKeys.keywords.all })
    },
  })
}

export function useBulkAttachPage() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ pageId, data }: { pageId: string; data: Record<string, unknown> }) =>
      api.post(`/pages/${pageId}/bulk-attach`, data).then((r) => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.pages.all })
      qc.invalidateQueries({ queryKey: queryKeys.keywords.all })
    },
  })
}

export function useDetachPageable() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (pageableId: string) => api.delete(`/pageables/${pageableId}`),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.pages.all })
      qc.invalidateQueries({ queryKey: queryKeys.keywords.all })
    },
  })
}

export function useMatchOrCreatePage(projectId: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: Record<string, unknown>) =>
      api.post(`/projects/${projectId}/pages/match-or-create`, data).then((r) => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.pages.all })
    },
  })
}

export function useKeywordPages(keywordId: string) {
  return useQuery({
    queryKey: ['keyword-pages', keywordId],
    queryFn: () =>
      api.get(`/keywords/${keywordId}/pages`).then((r) => r.data),
    enabled: !!keywordId,
    staleTime: 30_000,
  })
}

export function useImportPages(projectId: string) {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: { pages: Array<Record<string, unknown>> }) =>
      api.post(`/projects/${projectId}/pages/import`, data).then((r) => r.data),
    onSuccess: () => {
      qc.invalidateQueries({ queryKey: queryKeys.pages.all })
    },
  })
}

export function useTargetReport(projectId: string) {
  return useQuery({
    queryKey: queryKeys.pages.targetReport(projectId),
    queryFn: () =>
      api.get(`/projects/${projectId}/pages/target-report`).then((r) => r.data),
    enabled: !!projectId,
    staleTime: 60_000,
  })
}
