import {
  createContext,
  useContext,
  useState,
  useEffect,
  useCallback,
  useMemo,
  type ReactNode,
} from 'react'
import api from '@/lib/api'
import type { User } from '@/types/api'

interface AuthContextType {
  user: User | null
  token: string | null
  organizationId: number | null
  login: (email: string, password: string) => Promise<void>
  register: (data: {
    name: string
    email: string
    password: string
    password_confirmation: string
    organization_name: string
  }) => Promise<void>
  logout: () => void
  setOrganization: (id: number) => void
  isLoading: boolean
}

// Parse JSON:API user response into flat User object
function parseUserFromApi(raw: Record<string, unknown>): User {
  const attrs = (raw.attributes ?? raw) as Record<string, unknown>
  const rels = (raw.relationships ?? {}) as Record<string, unknown>
  const orgs = Array.isArray(rels.organizations)
    ? rels.organizations.map((o: Record<string, unknown>) => ({
        id: Number(o.id),
        name: ((o.attributes as Record<string, unknown>)?.name as string) ?? '',
        slug: ((o.attributes as Record<string, unknown>)?.slug as string) ?? '',
        ...(o.attributes as Record<string, unknown>),
      }))
    : Array.isArray(attrs.organizations)
      ? attrs.organizations
      : []
  return {
    id: Number(raw.id ?? attrs.id),
    name: (attrs.name as string) ?? '',
    email: (attrs.email as string) ?? '',
    locale: (attrs.locale as string) ?? 'ru',
    theme: (attrs.theme as string) ?? null,
    organizations: orgs,
  } as User
}

const AuthContext = createContext<AuthContextType | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(null)
  const [token, setToken] = useState<string | null>(
    localStorage.getItem('token'),
  )
  const [organizationId, setOrganizationId] = useState<number | null>(
    Number(localStorage.getItem('organization_id')) || null,
  )
  const [isLoading, setIsLoading] = useState(true)

  const setOrganization = useCallback((id: number) => {
    setOrganizationId(id)
    localStorage.setItem('organization_id', String(id))
  }, [])

  useEffect(() => {
    if (token) {
      api
        .get('/auth/me')
        .then((res) => {
          const u = parseUserFromApi(res.data.user)
          setUser(u)
          if (!organizationId && u.organizations && u.organizations.length > 0) {
            setOrganization(Number(u.organizations[0].id))
          }
        })
        .catch(() => {
          setToken(null)
          localStorage.removeItem('token')
        })
        .finally(() => setIsLoading(false))
    } else {
      setIsLoading(false)
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [token])

  const login = useCallback(async (email: string, password: string) => {
    const res = await api.post('/auth/login', { email, password })
    const { token: t } = res.data
    const u = parseUserFromApi(res.data.user)
    setUser(u)
    setToken(t)
    localStorage.setItem('token', t)
    if (u.organizations && u.organizations.length > 0) {
      setOrganization(Number(u.organizations[0].id))
    }
  }, [setOrganization])

  const register = useCallback(
    async (data: {
      name: string
      email: string
      password: string
      password_confirmation: string
      organization_name: string
    }) => {
      const res = await api.post('/auth/register', data)
      const { token: t, organization } = res.data
      const u = parseUserFromApi(res.data.user)
      const org = organization?.attributes ? { id: Number(organization.id), ...organization.attributes } : organization
      setUser({ ...u, organizations: [org] } as User)
      setToken(t)
      localStorage.setItem('token', t)
      if (org?.id) setOrganization(Number(org.id))
    },
    [setOrganization],
  )

  const logout = useCallback(() => {
    api.post('/auth/logout').catch(() => {})
    setUser(null)
    setToken(null)
    setOrganizationId(null)
    localStorage.removeItem('token')
    localStorage.removeItem('organization_id')
  }, [])

  const value = useMemo(
    () => ({
      user,
      token,
      organizationId,
      login,
      register,
      logout,
      setOrganization,
      isLoading,
    }),
    [user, token, organizationId, login, register, logout, setOrganization, isLoading],
  )

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth() {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within AuthProvider')
  return ctx
}
