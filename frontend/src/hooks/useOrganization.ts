import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'

export function useOrganization() {
  return useQuery({
    queryKey: queryKeys.organization.all,
    queryFn: () => api.get('/organization').then((r) => r.data),
    staleTime: 60_000,
  })
}

export function useMembers() {
  return useQuery({
    queryKey: queryKeys.organization.members,
    queryFn: () => api.get('/organization/members').then((r) => r.data),
    staleTime: 30_000,
  })
}

export function useInviteMember() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: { email: string; role: string }) =>
      api.post('/organization/invite', data).then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.organization.members }),
  })
}

export function useRemoveMember() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (userId: number) =>
      api.delete(`/organization/members/${userId}`).then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.organization.members }),
  })
}

export function useUpdateMemberRole() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({ userId, role }: { userId: number; role: string }) =>
      api
        .put(`/organization/members/${userId}/role`, { role })
        .then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.organization.members }),
  })
}
