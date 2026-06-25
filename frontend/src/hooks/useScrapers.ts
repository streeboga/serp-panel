import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'

export function useScrapers() {
  return useQuery({
    queryKey: queryKeys.scrapers.all,
    queryFn: () => api.get('/scrapers').then((r) => r.data),
    staleTime: 30_000,
  })
}

export function useCreateScraper() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: {
      name: string
      type: string
      base_url: string
      supported_engines?: string[]
      credentials?: Record<string, string>
      rate_limit?: number
      is_active?: boolean
    }) => api.post('/scrapers', data).then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.scrapers.all }),
  })
}

export function useUpdateScraper() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      ...data
    }: {
      id: number
      name?: string
      type?: string
      base_url?: string
      supported_engines?: string[]
      credentials?: Record<string, string>
      rate_limit?: number
      is_active?: boolean
    }) => api.patch(`/scrapers/${id}`, data).then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.scrapers.all }),
  })
}

export function useDeleteScraper() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) =>
      api.delete(`/scrapers/${id}`).then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.scrapers.all }),
  })
}

export function useTestScraper() {
  return useMutation({
    mutationFn: (id: number) =>
      api.post(`/scrapers/${id}/test`).then((r) => r.data),
  })
}
