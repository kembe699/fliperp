import { Navigate, Outlet } from 'react-router-dom'

import { useAuthStore } from '@/lib/auth-store'
import { PlatformSidebar } from '@/components/layout/PlatformSidebar'
import { NotificationBell } from '@/components/layout/NotificationBell'

export function PlatformLayout() {
  const user = useAuthStore((state) => state.user)

  // Backend already 403s every /platform-admin/* call for non-staff (EnsurePlatformStaff
  // middleware) — this is the client-side mirror of that gate, so a non-staff user
  // navigating here directly bounces immediately instead of seeing a flash of the UI.
  if (!user?.is_platform_staff) {
    return <Navigate to="/dashboard" replace />
  }

  return (
    <div className="flex h-screen bg-background">
      <PlatformSidebar />
      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex h-16 shrink-0 items-center justify-end border-b border-border bg-card px-6">
          <NotificationBell />
        </header>
        <main className="flex-1 overflow-y-auto p-6">
          <Outlet />
        </main>
      </div>
    </div>
  )
}
