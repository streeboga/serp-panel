import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'

export function useBillingUsage() {
  return useQuery({
    queryKey: queryKeys.billing.usage,
    queryFn: () => api.get('/billing/usage').then((r) => r.data),
    staleTime: 60_000,
  })
}

export function useUpdateBillingTier() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: { tier: string }) =>
      api.patch('/billing/tier', data).then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.billing.usage }),
  })
}
