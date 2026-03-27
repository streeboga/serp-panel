import { createFileRoute, useNavigate, Link } from '@tanstack/react-router'
import { useState, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useAuth } from '@/contexts/AuthContext'
import { parseApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

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
    <div className="min-h-screen flex items-center justify-center bg-background glow-bg">
      <div className="relative z-10 w-full max-w-sm">
        <div className="text-center mb-6">
          <h1 className="text-2xl font-bold tracking-tight">{t('auth.createAccount')}</h1>
          <p className="text-[13px] text-muted-foreground mt-1">Начните мониторинг за минуту</p>
        </div>
        <div className="glass-card rounded-lg p-6">
          <form onSubmit={handleSubmit} className="space-y-3">
            {error && <p className="text-destructive text-[12px] bg-destructive/10 rounded-lg px-3 py-2">{error}</p>}
            <div className="space-y-1.5">
              <Label className="text-[12px]">{t('auth.name')}</Label>
              <Input value={form.name} onChange={update('name')} required className="h-9" />
            </div>
            <div className="space-y-1.5">
              <Label className="text-[12px]">{t('auth.email')}</Label>
              <Input type="email" value={form.email} onChange={update('email')} required className="h-9" />
            </div>
            <div className="space-y-1.5">
              <Label className="text-[12px]">{t('auth.password')}</Label>
              <Input type="password" value={form.password} onChange={update('password')} required className="h-9" />
            </div>
            <div className="space-y-1.5">
              <Label className="text-[12px]">{t('auth.confirmPassword')}</Label>
              <Input type="password" value={form.password_confirmation} onChange={update('password_confirmation')} required className="h-9" />
            </div>
            <div className="space-y-1.5">
              <Label className="text-[12px]">{t('auth.organizationName')}</Label>
              <Input value={form.organization_name} onChange={update('organization_name')} required className="h-9" />
            </div>
            <Button type="submit" className="w-full" disabled={loading}>
              {loading ? t('auth.creating') : t('auth.register')}
            </Button>
            <p className="text-center text-[12px] text-muted-foreground">
              {t('auth.haveAccount')}{' '}
              <Link to="/login" className="text-accent-blue hover:underline">
                {t('auth.login')}
              </Link>
            </p>
          </form>
        </div>
      </div>
    </div>
  )
}
