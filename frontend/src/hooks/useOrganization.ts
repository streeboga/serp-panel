import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'

export function useOrganizations() {
  return useQuery({
    queryKey: queryKeys.organizations.all,
    queryFn: () => api.get('/organizations').then((r) => r.data),
    staleTime: 60_000,
  })
}

export function useOrganization() {
  return useQuery({
    queryKey: queryKeys.organization.all,
    queryFn: () => api.get('/organization').then((r) => r.data),
    staleTime: 60_000,
  })
}

export function useUpdateOrganization() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: { name?: string }) =>
      api.patch('/organization', data).then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.organization.all }),
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
        .patch(`/organization/members/${userId}/role`, { role })
        .then((r) => r.data),
    onSuccess: () =>
      qc.invalidateQueries({ queryKey: queryKeys.organization.members }),
  })
}
