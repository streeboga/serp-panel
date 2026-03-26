import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'

export function useAlerts() {
  return useQuery({
    queryKey: queryKeys.alerts.all,
    queryFn: () => api.get('/alerts').then((r) => r.data),
    staleTime: 30_000,
  })
}

export function useAlert(id: string) {
  return useQuery({
    queryKey: queryKeys.alerts.detail(id),
    queryFn: () => api.get(`/alerts/${id}`).then((r) => r.data),
    enabled: !!id,
    staleTime: 30_000,
  })
}

export function useCreateAlert() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: {
      keyword_id: number
      threshold_position: number
      direction: 'drops_below' | 'rises_above'
      channel: 'email' | 'telegram'
      recipient: string
      is_active?: boolean
    }) => api.post('/alerts', data).then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.alerts.all }),
  })
}

export function useUpdateAlert() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      ...data
    }: {
      id: number
      threshold_position?: number
      direction?: 'drops_below' | 'rises_above'
      channel?: 'email' | 'telegram'
      recipient?: string
      is_active?: boolean
    }) => api.patch(`/alerts/${id}`, data).then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.alerts.all }),
  })
}

export function useDeleteAlert() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) =>
      api.delete(`/alerts/${id}`).then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.alerts.all }),
  })
}
