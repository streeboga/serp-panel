import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'

export interface ConnectedAccount {
  id: number
  type: string
  label: string
  is_active: boolean
  has_credentials: boolean
  expires_at: string | null
  created_at: string
}

export function useAccounts() {
  return useQuery<{ data: ConnectedAccount[] }>({
    queryKey: queryKeys.accounts.all,
    queryFn: () => api.get('/accounts').then((r) => r.data),
    staleTime: 30_000,
  })
}

export function useCreateAccount() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: { type: string; label: string; credentials?: Record<string, string> }) =>
      api.post('/accounts', data).then((r) => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.accounts.all }),
  })
}

export function useDeleteAccount() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api.delete(`/accounts/${id}`).then((r) => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.accounts.all }),
  })
}

export function useTestAccount() {
  return useMutation({
    mutationFn: (id: number) => api.post(`/accounts/${id}/test`).then((r) => r.data),
  })
}
