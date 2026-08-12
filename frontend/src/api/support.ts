import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { SupportTicket, TicketPriority } from '@/types/support'

export interface SupportTicketFilters {
  page?: number
  per_page?: number
  status?: string
}

export async function fetchSupportTickets(filters: SupportTicketFilters = {}): Promise<PaginatedResponse<SupportTicket>> {
  const { data } = await api.get<PaginatedResponse<SupportTicket>>('/support/tickets', { params: filters })
  return data
}

export async function fetchSupportTicket(id: number): Promise<SupportTicket> {
  const { data } = await api.get<ApiResponse<SupportTicket>>(`/support/tickets/${id}`)
  return data.data
}

export interface CreateSupportTicketInput {
  subject: string
  description: string
  priority?: TicketPriority
}

export async function createSupportTicket(values: CreateSupportTicketInput): Promise<SupportTicket> {
  const { data } = await api.post<ApiResponse<SupportTicket>>('/support/tickets', values)
  return data.data
}

export async function replySupportTicket(id: number, body: string): Promise<SupportTicket> {
  const { data } = await api.post<ApiResponse<SupportTicket>>(`/support/tickets/${id}/replies`, { body })
  return data.data
}
