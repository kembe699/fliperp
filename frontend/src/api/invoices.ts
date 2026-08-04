import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { Invoice, InvoiceFormInput, InvoiceStatus } from '@/types/invoice'

export interface InvoiceFilters {
  page?: number
  per_page?: number
  status?: InvoiceStatus
  branch_id?: number
  customer_id?: number
  from?: string
  to?: string
}

export async function fetchInvoices(filters: InvoiceFilters): Promise<PaginatedResponse<Invoice>> {
  const { data } = await api.get<PaginatedResponse<Invoice>>('/invoices', { params: filters })
  return data
}

export async function fetchInvoice(id: number): Promise<Invoice> {
  const { data } = await api.get<ApiResponse<Invoice>>(`/invoices/${id}`)
  return data.data
}

export async function createInvoice(input: InvoiceFormInput): Promise<Invoice> {
  const { data } = await api.post<ApiResponse<Invoice>>('/invoices', input)
  return data.data
}

export async function updateInvoice(id: number, input: InvoiceFormInput): Promise<Invoice> {
  const { data } = await api.put<ApiResponse<Invoice>>(`/invoices/${id}`, input)
  return data.data
}

export async function sendInvoice(id: number): Promise<Invoice> {
  const { data } = await api.post<ApiResponse<Invoice>>(`/invoices/${id}/send`)
  return data.data
}

export async function cancelInvoice(id: number): Promise<Invoice> {
  const { data } = await api.post<ApiResponse<Invoice>>(`/invoices/${id}/cancel`)
  return data.data
}

export async function deleteInvoice(id: number): Promise<void> {
  await api.delete(`/invoices/${id}`)
}

export async function emailInvoice(id: number): Promise<string> {
  const { data } = await api.post<ApiResponse<null>>(`/invoices/${id}/email`)
  return data.message
}
