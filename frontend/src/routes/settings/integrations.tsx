import { createFileRoute, redirect } from '@tanstack/react-router'

export const Route = createFileRoute('/settings/integrations')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) {
      throw redirect({ to: '/login' })
    }
  },
})
