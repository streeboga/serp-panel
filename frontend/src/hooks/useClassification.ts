import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'

export function useSiteTypes() {
  return useQuery({
    queryKey: ['site-types'],
    queryFn: () => api.get('/site-types').then(r => r.data),
  })
}

export function useClassificationRules() {
  return useQuery({
    queryKey: ['classification-rules'],
    queryFn: () => api.get('/classification-rules').then(r => r.data),
  })
}

export function useCreateClassificationRule() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: {
      rule_type: string
      pattern: string
      site_type_id: number
      priority?: number
    }) => api.post('/classification-rules', data).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['classification-rules'] }),
  })
}

export function useUpdateClassificationRule() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ id, ...data }: {
      id: number
      rule_type?: string
      pattern?: string
      site_type_id?: number
      priority?: number
    }) => api.put(`/classification-rules/${id}`, data).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['classification-rules'] }),
  })
}

export function useDeleteClassificationRule() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: number) => api.delete(`/classification-rules/${id}`).then(r => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: ['classification-rules'] }),
  })
}

export function useDomainClassifications() {
  return useQuery({
    queryKey: ['domain-classifications'],
    queryFn: () => api.get('/domain-classifications').then(r => r.data),
  })
}
