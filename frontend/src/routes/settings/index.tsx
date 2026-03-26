import { createFileRoute, redirect } from '@tanstack/react-router'

export const Route = createFileRoute('/settings/')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) {
      throw redirect({ to: '/login' })
    }
  },
})
