import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'

export interface PositionCell {
  position: number | null
  delta: number | null
}

export interface KeywordRow {
  keyword_id: number
  keyword: string
  engine: string
  device: string
  cluster: string | null
  category: string | null
  region: string | null
  region_id: number | null
  frequency: number | null
  frequency_exact: number | null
  frequency_broad: number | null
  frequency_phrase: number | null
  our_url: string | null
  effective_target_url: string | null
  target_url_source: 'keyword' | 'cluster' | 'category' | null
  target_match_status: 'top3' | 'top10' | 'cannibalization' | 'missing' | 'unset' | null
  positions: Record<string, PositionCell>
}

export interface PositionMatrixResponse {
  data: KeywordRow[]
  dates: string[]
}

export function usePositionMatrix(projectId: string, days: number = 14) {
  return useQuery<PositionMatrixResponse>({
    queryKey: queryKeys.positionMatrix.list(projectId, days),
    queryFn: () =>
      api
        .get(`/projects/${projectId}/positions`, { params: { days } })
        .then((r) => r.data),
    enabled: !!projectId,
    staleTime: 30_000,
  })
}
