import type { ReactNode } from 'react'
import { useEffect } from 'react'
import { Navigate } from 'react-router-dom'

import { fetchMe } from '@/api/auth'
import { fetchCurrencies } from '@/api/settings'
import { useAuthStore } from '@/lib/auth-store'

export function ProtectedRoute({ children }: { children: ReactNode }) {
  const token = useAuthStore((state) => state.token)
  const setAuth = useAuthStore((state) => state.setAuth)
  const setCurrencies = useAuthStore((state) => state.setCurrencies)

  // The auth store (user/company/roles/permissions) is persisted to
  // localStorage and otherwise only ever refreshed by the specific action
  // that changed it (e.g. saving Company Settings) — so a browser session
  // that predates such a change, or one on another device, keeps showing
  // stale data indefinitely. Resync once against the server on mount.
  useEffect(() => {
    if (!token) return
    fetchMe().then(setAuth).catch(() => {})
    // Currency symbols (formatCurrency reads these from the store, not via
    // a hook, since it's called from plain table-column render functions).
    fetchCurrencies().then(setCurrencies).catch(() => {})
  }, [token, setAuth, setCurrencies])

  if (!token) {
    return <Navigate to="/login" replace />
  }

  return <>{children}</>
}
