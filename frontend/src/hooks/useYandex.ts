import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'

export function useYandexStatus() {
  return useQuery({
    queryKey: ['yandex', 'status'],
    queryFn: () => api.get('/organization/yandex/status').then((r) => r.data),
    staleTime: 60_000,
  })
}

export function useYandexRedirect() {
  return useMutation({
    mutationFn: () => api.get('/auth/yandex/redirect').then((r) => r.data),
  })
}

export function useYandexSaveToken() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (token: string) =>
      api.post('/organization/yandex/save-token', { token }).then((r) => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['yandex'] }),
  })
}

export function useYandexDisconnect() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: () => api.delete('/organization/yandex').then((r) => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['yandex'] }),
  })
}
