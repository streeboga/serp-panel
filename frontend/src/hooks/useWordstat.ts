import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'

export function useWordstat(keywordId: string) {
  return useQuery({
    queryKey: ['wordstat', keywordId],
    queryFn: () =>
      api.get(`/keywords/${keywordId}/wordstat`).then(r => r.data),
    enabled: !!keywordId,
  })
}

export function useWordstatTrends(keywordId: string) {
  return useQuery({
    queryKey: ['wordstat-trends', keywordId],
    queryFn: () =>
      api.get(`/keywords/${keywordId}/wordstat/trends`).then(r => r.data),
    enabled: !!keywordId,
  })
}

export function useWordstatSuggestions(keywordId: string) {
  return useQuery({
    queryKey: ['wordstat-suggestions', keywordId],
    queryFn: () =>
      api.get(`/keywords/${keywordId}/wordstat/suggestions`).then(r => r.data),
    enabled: !!keywordId,
  })
}
