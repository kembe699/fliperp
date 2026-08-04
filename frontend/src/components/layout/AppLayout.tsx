import { Outlet, useLocation } from 'react-router-dom'

import { AppSidebar } from '@/components/layout/AppSidebar'
import { AppTopbar } from '@/components/layout/AppTopbar'
import { RequirePermission } from '@/routes/RequirePermission'
import { permissionForPath } from '@/nav/nav-permissions'
import { ChatWidget } from '@/components/chat/ChatWidget'

export function AppLayout() {
  const location = useLocation()
  const requiredPermission = permissionForPath(location.pathname)

  return (
    <div className="flex h-screen bg-background">
      <AppSidebar />
      <div className="flex min-w-0 flex-1 flex-col">
        <AppTopbar />
        <main className="flex-1 overflow-y-auto p-6">
          {requiredPermission ? (
            <RequirePermission permission={requiredPermission}>
              <Outlet />
            </RequirePermission>
          ) : (
            <Outlet />
          )}
        </main>
      </div>
      <ChatWidget />
    </div>
  )
}
