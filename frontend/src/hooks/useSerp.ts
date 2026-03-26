import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'

export function useSerpResults(
  keywordId: string,
  params?: { date?: string; top_n?: number },
) {
  return useQuery({
    queryKey: queryKeys.serp.results(keywordId, params),
    queryFn: () =>
      api.get(`/keywords/${keywordId}/serp`, { params }).then((r) => r.data),
    enabled: !!keywordId,
    staleTime: 60_000,
    gcTime: 5 * 60_000,
  })
}

export function useSerpDates(keywordId: string) {
  return useQuery({
    queryKey: queryKeys.serp.dates(keywordId),
    queryFn: () =>
      api.get(`/keywords/${keywordId}/serp/dates`).then((r) => r.data),
    enabled: !!keywordId,
    staleTime: 5 * 60_000,
  })
}

export function useSerpHistory(keywordId: string) {
  return useQuery({
    queryKey: queryKeys.serp.history(keywordId),
    queryFn: () =>
      api.get(`/keywords/${keywordId}/serp/history`).then((r) => r.data),
    enabled: !!keywordId,
    staleTime: 60_000,
  })
}
