import { createFileRoute, redirect } from '@tanstack/react-router'

export const Route = createFileRoute('/ai-visibility')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) {
      throw redirect({ to: '/login' })
    }
  },
})
