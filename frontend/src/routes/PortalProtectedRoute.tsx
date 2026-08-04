import type { ReactNode } from 'react'
import { Navigate } from 'react-router-dom'

import { usePortalAuthStore } from '@/lib/portal-auth-store'

export function PortalProtectedRoute({ children }: { children: ReactNode }) {
  const token = usePortalAuthStore((state) => state.token)

  if (!token) {
    return <Navigate to="/employee-portal/login" replace />
  }

  return <>{children}</>
}
