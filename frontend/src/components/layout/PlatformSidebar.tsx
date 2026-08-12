import { Link, useLocation, useNavigate } from 'react-router-dom'
import { LayoutDashboard, Users, Receipt, Headset, LogOut, ArrowLeftRight } from 'lucide-react'

import { cn } from '@/lib/utils'
import { useAuthStore } from '@/lib/auth-store'
import { logout as logoutRequest } from '@/api/auth'
import { Avatar, AvatarFallback } from '@/components/ui/avatar'

function initials(name: string): string {
  const parts = name.trim().split(/\s+/)
  const first = parts[0]?.[0] ?? ''
  const last = parts.length > 1 ? (parts[parts.length - 1]?.[0] ?? '') : ''
  return (first + last).toUpperCase()
}

const navItems = [
  { label: 'Dashboard', path: '/platform-admin', icon: LayoutDashboard },
  { label: 'Clients', path: '/platform-admin/clients', icon: Users },
  { label: 'Billing', path: '/platform-admin/billing', icon: Receipt },
  { label: 'Tickets', path: '/platform-admin/tickets', icon: Headset },
]

export function PlatformSidebar() {
  const location = useLocation()
  const navigate = useNavigate()
  const user = useAuthStore((state) => state.user)
  const clearAuth = useAuthStore((state) => state.clearAuth)

  const handleLogout = async () => {
    try {
      await logoutRequest()
    } catch {
      // Even if the API call fails, still clear local state and redirect.
    }
    clearAuth()
    navigate('/login', { replace: true })
  }

  return (
    <aside className="flex h-screen w-[260px] shrink-0 flex-col border-r border-border bg-sidebar">
      <div className="border-b border-border px-5 py-4">
        <img src="/logo.png" alt="FlipErp" className="h-8 w-auto" />
      </div>

      <div className="px-5 pb-4 pt-5">
        <p className="text-sm font-semibold text-foreground">Platform Admin</p>
        <p className="text-[11px] font-semibold uppercase tracking-wide text-primary">Nile Hive Concept Co.</p>
        <div className="mt-4 h-px w-full bg-primary/20" />
      </div>

      <nav className="flex-1 overflow-y-auto px-3 pb-4">
        <p className="px-2 pb-2 text-[11px] font-semibold uppercase tracking-wider text-muted-foreground">Platform</p>
        <ul className="space-y-0.5">
          {navItems.map((item) => {
            const Icon = item.icon
            const isActive = location.pathname === item.path
            return (
              <li key={item.path}>
                <Link
                  to={item.path}
                  className={cn(
                    'flex items-center gap-2.5 rounded-full px-3 py-2 text-sm font-medium transition-colors',
                    isActive ? 'bg-accent text-primary' : 'text-foreground hover:bg-accent/60',
                  )}
                >
                  <Icon className={cn('h-4 w-4 shrink-0', isActive ? 'text-primary' : 'text-muted-foreground')} />
                  <span>{item.label}</span>
                </Link>
              </li>
            )
          })}
        </ul>
      </nav>

      <div className="shrink-0 border-t border-border p-3">
        <button
          type="button"
          onClick={() => navigate('/dashboard')}
          className="mb-2 flex w-full items-center gap-2.5 rounded-full px-3 py-2 text-left text-sm font-medium text-foreground hover:bg-accent/60"
        >
          <ArrowLeftRight className="h-4 w-4 text-muted-foreground" />
          Exit to ERP
        </button>

        <div className="flex items-center gap-2.5 rounded-lg p-2">
          <Avatar className="h-9 w-9">
            <AvatarFallback>{user ? initials(user.name) : '?'}</AvatarFallback>
          </Avatar>
          <div className="min-w-0 flex-1">
            <p className="truncate text-sm font-medium text-foreground">{user?.name ?? 'User'}</p>
            <p className="truncate text-xs text-muted-foreground">{user?.email ?? ''}</p>
          </div>
          <button type="button" aria-label="Log out" onClick={handleLogout} className="rounded-md p-1.5 text-muted-foreground hover:bg-accent hover:text-destructive">
            <LogOut className="h-4 w-4" />
          </button>
        </div>
      </div>
    </aside>
  )
}
