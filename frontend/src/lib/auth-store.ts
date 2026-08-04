import { create } from 'zustand'
import { persist } from 'zustand/middleware'
import type { AuthPayload, Company, User } from '@/types/auth'

interface AuthState {
  token: string | null
  user: User | null
  company: Company | null
  roles: string[]
  permissions: string[]
  setAuth: (payload: AuthPayload) => void
  setCompany: (company: Company) => void
  clearAuth: () => void
}

export const useAuthStore = create<AuthState>()(
  persist(
    (set, get) => ({
      token: null,
      user: null,
      company: null,
      roles: [],
      permissions: [],
      setAuth: (payload) =>
        set({
          // /auth/me does not re-issue a token, so keep the one already stored.
          token: payload.token ?? get().token,
          user: payload.user,
          company: payload.company,
          roles: payload.roles,
          permissions: payload.permissions,
        }),
      setCompany: (company) => set({ company }),
      clearAuth: () =>
        set({
          token: null,
          user: null,
          company: null,
          roles: [],
          permissions: [],
        }),
    }),
    { name: 'erp-auth' },
  ),
)
