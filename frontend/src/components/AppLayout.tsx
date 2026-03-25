import { Link, useNavigate } from '@tanstack/react-router'
import { useAuth } from '@/contexts/AuthContext'
import { Button } from '@/components/ui/button'

const navItems = [
  { label: 'Dashboard', to: '/' },
  { label: 'Projects', to: '/projects' },
  { label: 'Classification', to: '/classification' },
  { label: 'Scrapers', to: '/scrapers' },
  { label: 'Schedules', to: '/schedules' },
  { label: 'Settings', to: '/settings' },
]

export function AppLayout({ children }: { children: React.ReactNode }) {
  const { user, logout } = useAuth()
  const navigate = useNavigate()

  const handleLogout = () => {
    logout()
    navigate({ to: '/login' })
  }

  return (
    <div className="min-h-screen flex">
      <aside className="w-64 bg-gray-900 text-white flex flex-col">
        <div className="p-4 border-b border-gray-700">
          <h1 className="text-xl font-bold">SEO Monitor</h1>
          <p className="text-sm text-gray-400 mt-1">{user?.name}</p>
        </div>
        <nav className="flex-1 p-4 space-y-1">
          {navItems.map(item => (
            <Link
              key={item.to}
              to={item.to}
              className="block px-3 py-2 rounded text-sm hover:bg-gray-800 transition-colors [&.active]:bg-gray-700"
            >
              {item.label}
            </Link>
          ))}
        </nav>
        <div className="p-4 border-t border-gray-700">
          <Button variant="ghost" size="sm" onClick={handleLogout} className="w-full text-gray-400 hover:text-white">
            Log out
          </Button>
        </div>
      </aside>
      <main className="flex-1 bg-gray-50 p-6 overflow-auto">
        {children}
      </main>
    </div>
  )
}
