import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import type { PortalEmployee, PortalUser } from '@/types/employee-portal'

interface PortalAuthState {
  token: string | null
  user: PortalUser | null
  employee: PortalEmployee | null
  setAuth: (payload: { token: string; user: PortalUser; employee: PortalEmployee }) => void
  clearAuth: () => void
}

/**
 * Deliberately separate from the main app's useAuthStore (different
 * persisted key, different shape) — the employee portal is a distinct,
 * lower-privilege auth flow that must never share tokens with the main
 * HR/admin app, even if both are open in the same browser.
 */
export const usePortalAuthStore = create<PortalAuthState>()(
  persist(
    (set) => ({
      token: null,
      user: null,
      employee: null,
      setAuth: (payload) => set({ token: payload.token, user: payload.user, employee: payload.employee }),
      clearAuth: () => set({ token: null, user: null, employee: null }),
    }),
    { name: 'erp-portal-auth' },
  ),
)
