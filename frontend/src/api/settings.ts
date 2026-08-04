import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { Company, Permission, Role, SettingsUser } from '@/types/settings'
import type { PaymentType, PaymentTypeKind, TaxRate } from '@/types/pos'
import type { Currency } from '@/types/auth'

export async function fetchCurrencies(): Promise<Currency[]> {
  const { data } = await api.get<ApiResponse<Currency[]>>('/currencies')
  return data.data
}

// Company (single-record settings form)
export async function fetchCompany(id: number): Promise<Company> {
  const { data } = await api.get<ApiResponse<Company>>(`/companies/${id}`)
  return data.data
}

export interface CompanyInput {
  name?: string
  slug?: string
  logo_url?: string | null
  currency_code?: string | null
  timezone?: string | null
  is_active?: boolean
}

export async function updateCompany(id: number, values: CompanyInput): Promise<Company> {
  const { data } = await api.put<ApiResponse<Company>>(`/companies/${id}`, values)
  return data.data
}

export async function uploadCompanyLogo(file: File): Promise<Company> {
  const formData = new FormData()
  formData.append('image', file)
  const { data } = await api.post<ApiResponse<Company>>('/company/logo', formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data.data
}

export async function deleteCompanyLogo(): Promise<Company> {
  const { data } = await api.delete<ApiResponse<Company>>('/company/logo')
  return data.data
}

// Users
export async function fetchUsers(params: { page?: number; per_page?: number } = {}): Promise<PaginatedResponse<SettingsUser>> {
  const { data } = await api.get<PaginatedResponse<SettingsUser>>('/users', { params })
  return data
}

export interface UserInput {
  name: string
  email: string
  password?: string
  phone?: string | null
  branch_id?: number | null
  is_active?: boolean
  roles?: string[]
}

export async function createUser(values: UserInput): Promise<SettingsUser> {
  const { data } = await api.post<ApiResponse<SettingsUser>>('/users', values)
  return data.data
}

export async function updateUser(id: number, values: Partial<UserInput>): Promise<SettingsUser> {
  const { data } = await api.put<ApiResponse<SettingsUser>>(`/users/${id}`, values)
  return data.data
}

export async function deleteUser(id: number): Promise<void> {
  await api.delete(`/users/${id}`)
}

// Roles & permissions
export async function fetchRoles(): Promise<Role[]> {
  const { data } = await api.get<PaginatedResponse<Role>>('/roles', { params: { per_page: 100 } })
  return data.data
}

export async function fetchPermissions(): Promise<Permission[]> {
  const { data } = await api.get<PaginatedResponse<Permission>>('/permissions', { params: { per_page: 500 } })
  return data.data
}

export interface RoleInput {
  name: string
  permissions?: string[]
}

export async function createRole(values: RoleInput): Promise<Role> {
  const { data } = await api.post<ApiResponse<Role>>('/roles', values)
  return data.data
}

export async function updateRole(id: number, values: Partial<RoleInput>): Promise<Role> {
  const { data } = await api.put<ApiResponse<Role>>(`/roles/${id}`, values)
  return data.data
}

export async function deleteRole(id: number): Promise<void> {
  await api.delete(`/roles/${id}`)
}

// Tax rates
export async function createTaxRate(values: { name: string; rate: number; is_default?: boolean; is_active?: boolean }): Promise<TaxRate> {
  const { data } = await api.post<ApiResponse<TaxRate>>('/tax-rates', values)
  return data.data
}

export async function updateTaxRate(id: number, values: Partial<{ name: string; rate: number; is_default: boolean; is_active: boolean }>): Promise<TaxRate> {
  const { data } = await api.put<ApiResponse<TaxRate>>(`/tax-rates/${id}`, values)
  return data.data
}

export async function deleteTaxRate(id: number): Promise<void> {
  await api.delete(`/tax-rates/${id}`)
}

// Payment types
export async function createPaymentType(values: { name: string; type: PaymentTypeKind; is_active?: boolean }): Promise<PaymentType> {
  const { data } = await api.post<ApiResponse<PaymentType>>('/payment-types', values)
  return data.data
}

export async function updatePaymentType(id: number, values: Partial<{ name: string; type: PaymentTypeKind; is_active: boolean }>): Promise<PaymentType> {
  const { data } = await api.put<ApiResponse<PaymentType>>(`/payment-types/${id}`, values)
  return data.data
}

export async function deletePaymentType(id: number): Promise<void> {
  await api.delete(`/payment-types/${id}`)
}
