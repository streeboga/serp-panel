import { useQuery } from '@tanstack/react-query'
import api from '@/lib/api'

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
  our_url: string | null
  positions: Record<string, PositionCell>
}

export interface PositionMatrixResponse {
  data: KeywordRow[]
  dates: string[]
}

export function usePositionMatrix(projectId: string, days: number = 14) {
  return useQuery<PositionMatrixResponse>({
    queryKey: ['position-matrix', projectId, days],
    queryFn: () =>
      api
        .get(`/projects/${projectId}/positions`, { params: { days } })
        .then((r) => r.data),
    enabled: !!projectId,
    staleTime: 30_000,
  })
}
