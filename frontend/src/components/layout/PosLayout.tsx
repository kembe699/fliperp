import { Outlet, useLocation, useNavigate } from 'react-router-dom'
import { ArrowLeft, LayoutDashboard } from 'lucide-react'

import { useAuthStore } from '@/lib/auth-store'
import { permissionForPath } from '@/nav/nav-permissions'
import { RequirePermission } from '@/routes/RequirePermission'
import { Button } from '@/components/ui/button'

/**
 * Full-screen frame for the POS terminal: no sidebar or top bar, so the
 * cashier gets the whole screen. One slim bar keeps the way back to the
 * Dashboard visible in every state (opening the drawer, selling, loading).
 */
export function PosLayout() {
  const navigate = useNavigate()
  const location = useLocation()
  const company = useAuthStore((state) => state.company)
  const user = useAuthStore((state) => state.user)
  const requiredPermission = permissionForPath(location.pathname)

  return (
    <div className="flex h-dvh flex-col bg-background">
      <header className="flex h-12 shrink-0 items-center justify-between gap-3 border-b border-border bg-card px-3 sm:px-4">
        <div className="flex min-w-0 items-center gap-3">
          <Button variant="outline" size="sm" className="gap-1.5" onClick={() => navigate('/dashboard')}>
            <ArrowLeft className="h-4 w-4" />
            <LayoutDashboard className="h-4 w-4" />
            Dashboard
          </Button>
          <span className="truncate text-sm font-semibold text-foreground">{company?.name}</span>
        </div>
        {user?.name && <span className="hidden truncate text-sm text-muted-foreground sm:inline">{user.name}</span>}
      </header>
      <main className="min-h-0 flex-1 overflow-y-auto p-3 sm:p-4 lg:overflow-hidden">
        {requiredPermission ? (
          <RequirePermission permission={requiredPermission}>
            <Outlet />
          </RequirePermission>
        ) : (
          <Outlet />
        )}
      </main>
    </div>
  )
}
