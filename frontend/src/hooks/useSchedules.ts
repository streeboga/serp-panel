import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'

export function useSchedules() {
  return useQuery({
    queryKey: ['schedules'],
    queryFn: () => api.get('/schedules').then(r => r.data),
  })
}

export function useCreateSchedule() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: {
      schedulable_type: string
      schedulable_id: number
      frequency: string
      is_active?: boolean
    }) => api.post('/schedules', data).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['schedules'] }),
  })
}

export function useUpdateSchedule() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, ...data }: {
      id: number
      frequency?: string
      is_active?: boolean
    }) => api.put(`/schedules/${id}`, data).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['schedules'] }),
  })
}

export function useDeleteSchedule() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api.delete(`/schedules/${id}`).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['schedules'] }),
  })
}

export function useRunSchedule() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api.post(`/schedules/${id}/run`).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['schedules'] }),
  })
}
