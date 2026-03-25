import { createContext, useContext, useState, useEffect, type ReactNode } from 'react'
import api from '@/lib/api'

interface User {
  id: number
  name: string
  email: string
  organizations: Array<{
    id: number
    name: string
    slug: string
    pivot: { role: string }
  }>
}

interface AuthContextType {
  user: User | null
  token: string | null
  organizationId: number | null
  login: (email: string, password: string) => Promise<void>
  register: (data: { name: string; email: string; password: string; password_confirmation: string; organization_name: string }) => Promise<void>
  logout: () => void
  setOrganization: (id: number) => void
  isLoading: boolean
}

const AuthContext = createContext<AuthContextType | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [token, setToken] = useState<string | null>(localStorage.getItem('token'))
  const [organizationId, setOrganizationId] = useState<number | null>(
    Number(localStorage.getItem('organization_id')) || null
  )
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    if (token) {
      api.get('/auth/me').then(res => {
        setUser(res.data.user)
        if (!organizationId && res.data.user.organizations.length > 0) {
          setOrganization(res.data.user.organizations[0].id)
        }
      }).catch(() => {
        setToken(null)
        localStorage.removeItem('token')
      }).finally(() => setIsLoading(false))
    } else {
      setIsLoading(false)
    }
  }, [token])

  const login = async (email: string, password: string) => {
    const res = await api.post('/auth/login', { email, password })
    const { user: u, token: t } = res.data
    setUser(u)
    setToken(t)
    localStorage.setItem('token', t)
    if (u.organizations.length > 0) {
      setOrganization(u.organizations[0].id)
    }
  }

  const register = async (data: { name: string; email: string; password: string; password_confirmation: string; organization_name: string }) => {
    const res = await api.post('/auth/register', data)
    const { user: u, token: t, organization } = res.data
    setUser({ ...u, organizations: [organization] })
    setToken(t)
    localStorage.setItem('token', t)
    setOrganization(organization.id)
  }

  const logout = () => {
    api.post('/auth/logout').catch(() => {})
    setUser(null)
    setToken(null)
    setOrganizationId(null)
    localStorage.removeItem('token')
    localStorage.removeItem('organization_id')
  }

  const setOrganization = (id: number) => {
    setOrganizationId(id)
    localStorage.setItem('organization_id', String(id))
  }

  return (
    <AuthContext.Provider value={{ user, token, organizationId, login, register, logout, setOrganization, isLoading }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within AuthProvider')
  return ctx
}
