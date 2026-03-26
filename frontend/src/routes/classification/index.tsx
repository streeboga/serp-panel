import { createFileRoute, redirect } from '@tanstack/react-router'

export const Route = createFileRoute('/classification/')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) {
      throw redirect({ to: '/login' })
    }
  },
})
