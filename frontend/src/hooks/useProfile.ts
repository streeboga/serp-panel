import { useMutation } from '@tanstack/react-query'
import api from '@/lib/api'

export function useUpdateProfile() {
  return useMutation({
    mutationFn: (data: {
      name?: string
      locale?: 'en' | 'ru'
      theme?: 'light' | 'dark' | null
    }) => api.patch('/auth/profile', data).then((r) => r.data),
  })
}
