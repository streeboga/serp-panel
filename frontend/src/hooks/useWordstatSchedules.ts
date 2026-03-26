import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'

export function useWordstatSchedules() {
  return useQuery({
    queryKey: queryKeys.wordstatSchedules.all,
    queryFn: () => api.get('/wordstat-schedules').then((r) => r.data),
    staleTime: 30_000,
  })
}

export function useCreateWordstatSchedule() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: {
      project_id: number
      cluster_id?: number
      keyword_id?: number
      frequency_days: number
      collect_trends?: boolean
      collect_suggestions?: boolean
      regions?: number[]
      is_active?: boolean
    }) => api.post('/wordstat-schedules', data).then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.wordstatSchedules.all }),
  })
}

export function useUpdateWordstatSchedule() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      ...data
    }: {
      id: number
      frequency_days?: number
      collect_trends?: boolean
      collect_suggestions?: boolean
      regions?: number[]
      is_active?: boolean
    }) =>
      api.patch(`/wordstat-schedules/${id}`, data).then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.wordstatSchedules.all }),
  })
}

export function useDeleteWordstatSchedule() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) =>
      api.delete(`/wordstat-schedules/${id}`).then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.wordstatSchedules.all }),
  })
}

export function useRunWordstatSchedule() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) =>
      api.post(`/wordstat-schedules/${id}/run-now`).then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.wordstatSchedules.all }),
  })
}
