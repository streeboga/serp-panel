import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'

export function useSerpResults(keywordId: string, params?: { date?: string; top_n?: number }) {
  return useQuery({
    queryKey: ['serp', keywordId, params],
    queryFn: () =>
      api.get(`/keywords/${keywordId}/serp`, { params }).then(r => r.data),
    enabled: !!keywordId,
  })
}

export function useSerpDates(keywordId: string) {
  return useQuery({
    queryKey: ['serp-dates', keywordId],
    queryFn: () =>
      api.get(`/keywords/${keywordId}/serp/dates`).then(r => r.data),
    enabled: !!keywordId,
  })
}

export function useSerpHistory(keywordId: string) {
  return useQuery({
    queryKey: ['serp-history', keywordId],
    queryFn: () =>
      api.get(`/keywords/${keywordId}/serp/history`).then(r => r.data),
    enabled: !!keywordId,
  })
}
