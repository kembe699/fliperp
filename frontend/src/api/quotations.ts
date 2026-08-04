import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { Quotation, QuotationFormInput, QuotationStatus } from '@/types/quotation'
import type { Invoice } from '@/types/invoice'

export interface QuotationFilters {
  page?: number
  per_page?: number
  status?: QuotationStatus
  branch_id?: number
  customer_id?: number
}

export async function fetchQuotations(filters: QuotationFilters): Promise<PaginatedResponse<Quotation>> {
  const { data } = await api.get<PaginatedResponse<Quotation>>('/quotations', { params: filters })
  return data
}

export async function fetchQuotation(id: number): Promise<Quotation> {
  const { data } = await api.get<ApiResponse<Quotation>>(`/quotations/${id}`)
  return data.data
}

export async function createQuotation(input: QuotationFormInput): Promise<Quotation> {
  const { data } = await api.post<ApiResponse<Quotation>>('/quotations', input)
  return data.data
}

export async function updateQuotation(id: number, input: QuotationFormInput): Promise<Quotation> {
  const { data } = await api.put<ApiResponse<Quotation>>(`/quotations/${id}`, input)
  return data.data
}

export async function sendQuotation(id: number): Promise<Quotation> {
  const { data } = await api.post<ApiResponse<Quotation>>(`/quotations/${id}/send`)
  return data.data
}

export async function acceptQuotation(id: number): Promise<Quotation> {
  const { data } = await api.post<ApiResponse<Quotation>>(`/quotations/${id}/accept`)
  return data.data
}

export async function rejectQuotation(id: number): Promise<Quotation> {
  const { data } = await api.post<ApiResponse<Quotation>>(`/quotations/${id}/reject`)
  return data.data
}

export async function convertQuotationToInvoice(id: number): Promise<Invoice> {
  const { data } = await api.post<ApiResponse<Invoice>>(`/quotations/${id}/convert-to-invoice`)
  return data.data
}

export async function deleteQuotation(id: number): Promise<void> {
  await api.delete(`/quotations/${id}`)
}
