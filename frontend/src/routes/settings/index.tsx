import { createFileRoute, redirect } from '@tanstack/react-router'
import { AppLayout } from '@/components/AppLayout'
import { useAuth } from '@/contexts/AuthContext'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'

export const Route = createFileRoute('/settings/')({
  beforeLoad: () => {
    if (!localStorage.getItem('token')) {
      throw redirect({ to: '/login' })
    }
  },
  component: SettingsPage,
})

function SettingsPage() {
  const { user } = useAuth()

  const org = user?.organizations?.[0]

  return (
    <AppLayout>
      <div className="space-y-6">
        <h1 className="text-2xl font-bold">Settings</h1>

        <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
          <Card>
            <CardHeader>
              <CardTitle>Organization</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              {org ? (
                <>
                  <div>
                    <p className="text-sm text-muted-foreground">Name</p>
                    <p className="font-medium">{org.name}</p>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">Slug</p>
                    <p className="font-mono text-sm">{org.slug}</p>
                  </div>
                </>
              ) : (
                <p className="text-muted-foreground">No organization</p>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Your Account</CardTitle>
            </CardHeader>
            <CardContent className="space-y-3">
              {user ? (
                <>
                  <div>
                    <p className="text-sm text-muted-foreground">Name</p>
                    <p className="font-medium">{user.name}</p>
                  </div>
                  <div>
                    <p className="text-sm text-muted-foreground">Email</p>
                    <p>{user.email}</p>
                  </div>
                  {org && (
                    <div>
                      <p className="text-sm text-muted-foreground">Role</p>
                      <Badge variant="outline">{org.pivot.role}</Badge>
                    </div>
                  )}
                </>
              ) : (
                <p className="text-muted-foreground">Loading...</p>
              )}
            </CardContent>
          </Card>
        </div>

        <Card>
          <CardHeader>
            <CardTitle>Members</CardTitle>
          </CardHeader>
          <CardContent>
            <p className="text-muted-foreground">
              Member management will be available in a future update.
            </p>
          </CardContent>
        </Card>
      </div>
    </AppLayout>
  )
}
