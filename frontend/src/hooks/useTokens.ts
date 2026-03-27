import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'

export function useTokens() {
  return useQuery({
    queryKey: queryKeys.tokens.all,
    queryFn: () => api.get('/tokens').then((r) => r.data),
    staleTime: 30_000,
  })
}

export function useCreateToken() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: { name: string; role: string; project_id?: number | null; expires_at?: string | null }) =>
      api.post('/tokens', data).then((r) => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.tokens.all }),
  })
}

export function useRevokeToken() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (tokenId: string) =>
      api.delete(`/tokens/${tokenId}`).then((r) => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.tokens.all }),
  })
}
