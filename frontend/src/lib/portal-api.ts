import axios from 'axios'
import { usePortalAuthStore } from '@/lib/portal-auth-store'

/**
 * A separate axios instance from the main app's `api` client — same base
 * URL, but reads/writes the portal's own token store and redirects to the
 * portal's own login page on 401, never the main app's.
 */
export const portalApi = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1',
  headers: {
    Accept: 'application/json',
  },
})

portalApi.interceptors.request.use((config) => {
  const token = usePortalAuthStore.getState().token
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

portalApi.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      usePortalAuthStore.getState().clearAuth()
      if (window.location.pathname !== '/employee-portal/login') {
        window.location.href = '/employee-portal/login'
      }
    }
    return Promise.reject(error)
  },
)
