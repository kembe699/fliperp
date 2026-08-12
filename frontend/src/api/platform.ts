import { api } from '@/lib/api'
import type { Company, CompanyStatus } from '@/types/auth'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { Invoice } from '@/types/invoice'
import type { Quotation } from '@/types/quotation'
import type {
  CreatePlatformClientInput,
  CreatePlatformClientResult,
  CreatePlatformInvoiceInput,
  CreatePlatformQuotationInput,
  PlatformBillingSummary,
  PlatformClientDetail,
  PlatformTicket,
  PlatformTicketPriority,
  PlatformTicketStatus,
} from '@/types/platform'

export interface PlatformClientFilters {
  page?: number
  per_page?: number
  status?: CompanyStatus
  search?: string
}

export async function fetchPlatformClients(filters: PlatformClientFilters): Promise<PaginatedResponse<Company>> {
  const { data } = await api.get<PaginatedResponse<Company>>('/platform-admin/clients', { params: filters })
  return data
}

export async function fetchPlatformClient(id: number): Promise<PlatformClientDetail> {
  const { data } = await api.get<ApiResponse<PlatformClientDetail>>(`/platform-admin/clients/${id}`)
  return data.data
}

export async function createPlatformClient(input: CreatePlatformClientInput): Promise<CreatePlatformClientResult> {
  const { data } = await api.post<ApiResponse<CreatePlatformClientResult>>('/platform-admin/clients', input)
  return data.data
}

export async function updatePlatformClient(id: number, input: { name?: string; currency_code?: string; timezone?: string }): Promise<Company> {
  const { data } = await api.patch<ApiResponse<Company>>(`/platform-admin/clients/${id}`, input)
  return data.data
}

export async function suspendPlatformClient(id: number): Promise<Company> {
  const { data } = await api.patch<ApiResponse<Company>>(`/platform-admin/clients/${id}/suspend`)
  return data.data
}

export async function activatePlatformClient(id: number): Promise<Company> {
  const { data } = await api.patch<ApiResponse<Company>>(`/platform-admin/clients/${id}/activate`)
  return data.data
}

export async function fetchPlatformBillingSummary(): Promise<PlatformBillingSummary> {
  const { data } = await api.get<ApiResponse<PlatformBillingSummary>>('/platform-admin/billing/summary')
  return data.data
}

export async function createPlatformQuotation(clientId: number, input: CreatePlatformQuotationInput): Promise<Quotation> {
  const { data } = await api.post<ApiResponse<Quotation>>(`/platform-admin/clients/${clientId}/quotations`, input)
  return data.data
}

export async function createPlatformInvoice(clientId: number, input: CreatePlatformInvoiceInput): Promise<Invoice> {
  const { data } = await api.post<ApiResponse<Invoice>>(`/platform-admin/clients/${clientId}/invoices`, input)
  return data.data
}

export interface PlatformTicketFilters {
  page?: number
  per_page?: number
  status?: PlatformTicketStatus
  priority?: PlatformTicketPriority
  company_id?: number
  assigned_to?: number
}

export async function fetchPlatformTickets(filters: PlatformTicketFilters): Promise<PaginatedResponse<PlatformTicket>> {
  const { data } = await api.get<PaginatedResponse<PlatformTicket>>('/platform-admin/tickets', { params: filters })
  return data
}

export async function fetchPlatformTicket(id: number): Promise<PlatformTicket> {
  const { data } = await api.get<ApiResponse<PlatformTicket>>(`/platform-admin/tickets/${id}`)
  return data.data
}

export async function updatePlatformTicket(
  id: number,
  input: { status?: PlatformTicketStatus; priority?: PlatformTicketPriority; assigned_to?: number | null },
): Promise<PlatformTicket> {
  const { data } = await api.patch<ApiResponse<PlatformTicket>>(`/platform-admin/tickets/${id}`, input)
  return data.data
}

export async function replyPlatformTicket(id: number, body: string, isInternalNote: boolean): Promise<PlatformTicket> {
  const { data } = await api.post<ApiResponse<PlatformTicket>>(`/platform-admin/tickets/${id}/replies`, {
    body,
    is_internal_note: isInternalNote,
  })
  return data.data
}
