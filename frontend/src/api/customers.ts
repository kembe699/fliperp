import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { Customer, CustomerStatement, CustomerType } from '@/types/customer'

export interface CustomerFilters {
  page?: number
  per_page?: number
  customer_type?: CustomerType
  branch_id?: number
  is_active?: boolean
  search?: string
}

export async function fetchCustomers(filters: CustomerFilters): Promise<PaginatedResponse<Customer>> {
  const { data } = await api.get<PaginatedResponse<Customer>>('/customers', { params: filters })
  return data
}

export interface CustomerFormValues {
  name: string
  phone: string
  email?: string | null
  address?: string | null
  tax_id?: string | null
  customer_type: CustomerType
  credit_limit: number
  branch_id?: number | null
  is_active?: boolean
}

export async function createCustomer(values: CustomerFormValues): Promise<Customer> {
  const { data } = await api.post<ApiResponse<Customer>>('/customers', values)
  return data.data
}

export async function updateCustomer(id: number, values: CustomerFormValues): Promise<Customer> {
  const { data } = await api.put<ApiResponse<Customer>>(`/customers/${id}`, values)
  return data.data
}

export async function deactivateCustomer(customer: Customer): Promise<Customer> {
  const { data } = await api.put<ApiResponse<Customer>>(`/customers/${customer.id}`, { is_active: false })
  return data.data
}

export async function fetchCustomerStatement(id: number): Promise<CustomerStatement> {
  const { data } = await api.get<ApiResponse<CustomerStatement>>(`/customers/${id}/statement`)
  return data.data
}
