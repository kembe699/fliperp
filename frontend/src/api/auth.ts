import { api } from '@/lib/api'
import type { ApiResponse } from '@/types/api'
import type { AuthPayload } from '@/types/auth'

export async function login(email: string, password: string): Promise<AuthPayload> {
  const { data } = await api.post<ApiResponse<AuthPayload>>('/auth/login', { email, password })
  return data.data
}

export async function fetchMe(): Promise<AuthPayload> {
  const { data } = await api.get<ApiResponse<AuthPayload>>('/auth/me')
  return data.data
}

export interface UpdateProfileInput {
  name?: string
  email?: string
  phone?: string | null
  current_password?: string
  password?: string
  password_confirmation?: string
}

export async function updateProfile(values: UpdateProfileInput): Promise<AuthPayload> {
  const { data } = await api.put<ApiResponse<AuthPayload>>('/auth/profile', values)
  return data.data
}

export async function logout(): Promise<void> {
  await api.post('/auth/logout')
}
