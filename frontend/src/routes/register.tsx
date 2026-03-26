import { createFileRoute, useNavigate, Link } from '@tanstack/react-router'
import { useState, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useAuth } from '@/contexts/AuthContext'
import { parseApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

export const Route = createFileRoute('/register')({
  component: RegisterPage,
})

function RegisterPage() {
  const { register } = useAuth()
  const navigate = useNavigate()
  const { t } = useTranslation()
  const [form, setForm] = useState({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    organization_name: '',
  })
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  const update =
    (field: string) => (e: React.ChangeEvent<HTMLInputElement>) =>
      setForm((prev) => ({ ...prev, [field]: e.target.value }))

  const handleSubmit = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      setLoading(true)
      setError('')
      try {
        await register(form)
        navigate({ to: '/' })
      } catch (err: unknown) {
        setError(parseApiError(err))
      } finally {
        setLoading(false)
      }
    },
    [form, register, navigate],
  )

  return (
    <div className="min-h-screen flex items-center justify-center bg-background">
      <Card className="w-full max-w-md">
        <CardHeader>
          <CardTitle className="text-2xl text-center">
            {t('auth.createAccount')}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSubmit} className="space-y-4">
            {error && <p className="text-destructive text-sm">{error}</p>}
            <div className="space-y-2">
              <Label>{t('auth.name')}</Label>
              <Input
                value={form.name}
                onChange={update('name')}
                required
              />
            </div>
            <div className="space-y-2">
              <Label>{t('auth.email')}</Label>
              <Input
                type="email"
                value={form.email}
                onChange={update('email')}
                required
              />
            </div>
            <div className="space-y-2">
              <Label>{t('auth.password')}</Label>
              <Input
                type="password"
                value={form.password}
                onChange={update('password')}
                required
              />
            </div>
            <div className="space-y-2">
              <Label>{t('auth.confirmPassword')}</Label>
              <Input
                type="password"
                value={form.password_confirmation}
                onChange={update('password_confirmation')}
                required
              />
            </div>
            <div className="space-y-2">
              <Label>{t('auth.organizationName')}</Label>
              <Input
                value={form.organization_name}
                onChange={update('organization_name')}
                required
              />
            </div>
            <Button type="submit" className="w-full" disabled={loading}>
              {loading ? t('auth.creating') : t('auth.register')}
            </Button>
            <p className="text-center text-sm text-muted-foreground">
              {t('auth.haveAccount')}{' '}
              <Link to="/login" className="text-primary hover:underline">
                {t('auth.login')}
              </Link>
            </p>
          </form>
        </CardContent>
      </Card>
    </div>
  )
}
