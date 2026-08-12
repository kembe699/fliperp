import axios from 'axios'
import { useAuthStore } from '@/lib/auth-store'
import { getEcho } from '@/lib/echo'

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api/v1',
  headers: {
    Accept: 'application/json',
  },
})

api.interceptors.request.use((config) => {
  const token = useAuthStore.getState().token
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  // Lets broadcast(...)->toOthers() (used when sending a chat message)
  // exclude the sender's own open tab from the echo of their own message —
  // without this header Echo can't tell which connection to skip and the
  // sender would see their own message appear twice.
  const socketId = getEcho()?.socketId()
  if (socketId) {
    config.headers['X-Socket-Id'] = socketId
  }
  return config
})

api.interceptors.response.use(
  (response) => response,
  (error) => {
    if (error.response?.status === 401) {
      useAuthStore.getState().clearAuth()
      if (window.location.pathname !== '/login') {
        window.location.href = '/login'
      }
    }
    // A company suspended/marked pending mid-session — EnsureCompanyActive rejects
    // the very next request with this reason, not just future logins. Full-page
    // redirect (not a React Query error toast): the whole app should stop, not
    // keep rendering half-authenticated screens.
    const reason = error.response?.data?.reason
    if (error.response?.status === 403 && (reason === 'company_suspended' || reason === 'company_pending')) {
      useAuthStore.getState().clearAuth()
      if (!window.location.pathname.startsWith('/account-blocked')) {
        window.location.href = `/account-blocked?reason=${reason}`
      }
    }
    return Promise.reject(error)
  },
)
