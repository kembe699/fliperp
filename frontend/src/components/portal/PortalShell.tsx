import type { ReactNode } from 'react'
import { NavLink, useNavigate } from 'react-router-dom'
import { Clock, LogOut, Palmtree, User } from 'lucide-react'

import { usePortalAuthStore } from '@/lib/portal-auth-store'
import { portalLogout } from '@/api/employee-portal'
import { cn } from '@/lib/utils'

const NAV_ITEMS = [
  { to: '/employee-portal', label: 'Home', icon: User, end: true },
  { to: '/employee-portal/clock', label: 'Clock', icon: Clock, end: false },
  { to: '/employee-portal/leave', label: 'Leave', icon: Palmtree, end: false },
]

export function PortalShell({ children }: { children: ReactNode }) {
  const navigate = useNavigate()
  const clearAuth = usePortalAuthStore((state) => state.clearAuth)
  const employee = usePortalAuthStore((state) => state.employee)

  const handleLogout = async () => {
    try {
      await portalLogout()
    } catch {
      // Even if the network call fails, clear local state so the user isn't stuck.
    }
    clearAuth()
    navigate('/employee-portal/login', { replace: true })
  }

  return (
    <div className="flex min-h-screen flex-col bg-[#F5F7FA]">
      <header className="flex items-center justify-between border-b border-border bg-white px-4 py-3">
        <div className="flex items-center gap-2">
          <img src="/logo.png" alt="FlipErp" className="h-7 w-auto" />
          <span className="text-sm font-semibold text-foreground">Employee Portal</span>
        </div>
        {employee && <span className="text-xs text-muted-foreground">{employee.first_name}</span>}
      </header>

      <main className="mx-auto w-full max-w-md flex-1 px-4 py-5 pb-24">{children}</main>

      <nav className="fixed inset-x-0 bottom-0 border-t border-border bg-white">
        <div className="mx-auto flex max-w-md items-stretch justify-around">
          {NAV_ITEMS.map(({ to, label, icon: Icon, end }) => (
            <NavLink
              key={to}
              to={to}
              end={end}
              className={({ isActive }) =>
                cn(
                  'flex flex-1 flex-col items-center gap-1 px-2 py-3 text-xs font-medium',
                  isActive ? 'text-primary' : 'text-muted-foreground',
                )
              }
            >
              <Icon className="h-5 w-5" />
              {label}
            </NavLink>
          ))}
          <button type="button" onClick={handleLogout} className="flex flex-1 flex-col items-center gap-1 px-2 py-3 text-xs font-medium text-muted-foreground">
            <LogOut className="h-5 w-5" />
            Logout
          </button>
        </div>
      </nav>
    </div>
  )
}
