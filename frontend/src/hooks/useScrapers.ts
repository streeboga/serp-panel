import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'

export function useScrapers() {
  return useQuery({
    queryKey: ['scrapers'],
    queryFn: () => api.get('/scrapers').then(r => r.data),
  })
}

export function useCreateScraper() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: {
      name: string
      type: string
      base_url: string
      engines: string[]
      rate_limit?: number
      is_active?: boolean
    }) => api.post('/scrapers', data).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['scrapers'] }),
  })
}

export function useUpdateScraper() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, ...data }: {
      id: number
      name?: string
      type?: string
      base_url?: string
      engines?: string[]
      rate_limit?: number
      is_active?: boolean
    }) => api.put(`/scrapers/${id}`, data).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['scrapers'] }),
  })
}

export function useDeleteScraper() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api.delete(`/scrapers/${id}`).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['scrapers'] }),
  })
}

export function useTestScraper() {
  return useMutation({
    mutationFn: (id: number) => api.post(`/scrapers/${id}/test`).then(r => r.data),
  })
}
