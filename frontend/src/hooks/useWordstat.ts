import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'

export function useWordstat(keywordId: string) {
  return useQuery({
    queryKey: queryKeys.wordstat.data(keywordId),
    queryFn: () =>
      api.get(`/keywords/${keywordId}/wordstat`).then((r) => r.data),
    enabled: !!keywordId,
    staleTime: 5 * 60_000,
  })
}

export function useWordstatTrends(keywordId: string) {
  return useQuery({
    queryKey: queryKeys.wordstat.trends(keywordId),
    queryFn: () =>
      api.get(`/keywords/${keywordId}/wordstat/trends`).then((r) => r.data),
    enabled: !!keywordId,
    staleTime: 5 * 60_000,
  })
}

export function useWordstatSuggestions(keywordId: string) {
  return useQuery({
    queryKey: queryKeys.wordstat.suggestions(keywordId),
    queryFn: () =>
      api
        .get(`/keywords/${keywordId}/wordstat/suggestions`)
        .then((r) => r.data),
    enabled: !!keywordId,
    staleTime: 5 * 60_000,
  })
}
