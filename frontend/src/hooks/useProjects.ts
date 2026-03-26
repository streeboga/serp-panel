import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query'
import api from '@/lib/api'
import { queryKeys } from '@/lib/query-keys'
import type { Project } from '@/types/api'

export function useProjects() {
  return useQuery({
    queryKey: queryKeys.projects.list(),
    queryFn: () => api.get('/projects').then((r) => r.data),
    staleTime: 60_000,
    gcTime: 5 * 60_000,
  })
}

export function useProject(id: string) {
  return useQuery({
    queryKey: queryKeys.projects.detail(id),
    queryFn: () => api.get(`/projects/${id}`).then((r) => r.data),
    enabled: !!id,
    staleTime: 30_000,
  })
}

export function useCreateProject() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (data: { name: string; description?: string }) =>
      api.post('/projects', data).then((r) => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.projects.all }),
  })
}

export function useUpdateProject() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: ({
      id,
      ...data
    }: {
      id: string
      name: string
      description?: string
    }) => api.put(`/projects/${id}`, data).then((r) => r.data),
    onMutate: async ({ id, name, description }) => {
      await qc.cancelQueries({ queryKey: queryKeys.projects.detail(id) })
      const previous = qc.getQueryData(queryKeys.projects.detail(id))
      qc.setQueryData(queryKeys.projects.detail(id), (old: Project | undefined) =>
        old ? { ...old, name, description } : old,
      )
      return { previous }
    },
    onError: (_err, { id }, context) => {
      if (context?.previous) {
        qc.setQueryData(queryKeys.projects.detail(id), context.previous)
      }
    },
    onSettled: () => qc.invalidateQueries({ queryKey: queryKeys.projects.all }),
  })
}

export function useDeleteProject() {
  const qc = useQueryClient()
  return useMutation({
    mutationFn: (id: string) =>
      api.delete(`/projects/${id}`).then((r) => r.data),
    onSuccess: () => qc.invalidateQueries({ queryKey: queryKeys.projects.all }),
  })
}
