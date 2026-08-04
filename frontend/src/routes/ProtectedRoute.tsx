import type { ReactNode } from 'react'
import { useEffect } from 'react'
import { Navigate } from 'react-router-dom'

import { fetchMe } from '@/api/auth'
import { useAuthStore } from '@/lib/auth-store'

export function ProtectedRoute({ children }: { children: ReactNode }) {
  const token = useAuthStore((state) => state.token)
  const setAuth = useAuthStore((state) => state.setAuth)

  // The auth store (user/company/roles/permissions) is persisted to
  // localStorage and otherwise only ever refreshed by the specific action
  // that changed it (e.g. saving Company Settings) — so a browser session
  // that predates such a change, or one on another device, keeps showing
  // stale data indefinitely. Resync once against the server on mount.
  useEffect(() => {
    if (!token) return
    fetchMe().then(setAuth).catch(() => {})
  }, [token, setAuth])

  if (!token) {
    return <Navigate to="/login" replace />
  }

  return <>{children}</>
}
