import { createFileRoute, useNavigate, Link } from '@tanstack/react-router'
import { useState, useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useAuth } from '@/contexts/AuthContext'
import { parseApiError } from '@/lib/api'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export const Route = createFileRoute('/login')({
  component: LoginPage,
})

function LoginPage() {
  const { login } = useAuth()
  const navigate = useNavigate()
  const { t } = useTranslation()
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [error, setError] = useState('')
  const [loading, setLoading] = useState(false)

  const handleSubmit = useCallback(
    async (e: React.FormEvent) => {
      e.preventDefault()
      setLoading(true)
      setError('')
      try {
        await login(email, password)
        navigate({ to: '/' })
      } catch (err: unknown) {
        setError(parseApiError(err))
      } finally {
        setLoading(false)
      }
    },
    [email, password, login, navigate],
  )

  return (
    <div className="min-h-screen flex items-center justify-center bg-background glow-bg">
      <div className="relative z-10 w-full max-w-sm">
        <div className="text-center mb-6">
          <h1 className="text-2xl font-bold tracking-tight">{t('app.name')}</h1>
          <p className="text-[13px] text-muted-foreground mt-1">SEO мониторинг позиций</p>
        </div>
        <div className="glass-card rounded-lg p-6">
          <form onSubmit={handleSubmit} className="space-y-4">
            {error && <p className="text-destructive text-[12px] bg-destructive/10 rounded-lg px-3 py-2">{error}</p>}
            <div className="space-y-1.5">
              <Label htmlFor="email" className="text-[12px]">{t('auth.email')}</Label>
              <Input
                id="email"
                type="email"
                value={email}
                onChange={(e) => setEmail(e.target.value)}
                required
                className="h-9"
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="password" className="text-[12px]">{t('auth.password')}</Label>
              <Input
                id="password"
                type="password"
                value={password}
                onChange={(e) => setPassword(e.target.value)}
                required
                className="h-9"
              />
            </div>
            <Button type="submit" className="w-full" disabled={loading}>
              {loading ? t('auth.loggingIn') : t('auth.login')}
            </Button>
            <p className="text-center text-[12px] text-muted-foreground">
              {t('auth.noAccount')}{' '}
              <Link to="/register" className="text-accent-blue hover:underline">
                {t('auth.register')}
              </Link>
            </p>
          </form>
        </div>
      </div>
    </div>
  )
}
