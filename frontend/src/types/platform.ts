import type { Company, CompanyStatus } from '@/types/auth'
import type { Invoice } from '@/types/invoice'
import type { Quotation } from '@/types/quotation'

export type PlatformTicketPriority = 'low' | 'medium' | 'high' | 'urgent'
export type PlatformTicketStatus = 'open' | 'in_progress' | 'resolved' | 'closed'
export type PlatformTicketSource = 'client_portal' | 'email' | 'phone' | 'staff'

export interface PlatformTicketReply {
  id: number
  ticket_id: number
  author_id: number
  author_name: string | null
  body: string
  is_internal_note: boolean
  created_at: string
}

export interface PlatformTicket {
  id: number
  company_id: number
  company_name: string | null
  raised_by_user_id: number
  raised_by_name: string | null
  subject: string
  description: string
  priority: PlatformTicketPriority
  status: PlatformTicketStatus
  assigned_to: number | null
  assignee_name: string | null
  source: PlatformTicketSource
  replies: PlatformTicketReply[]
  created_at: string
  updated_at: string
}

export interface PlatformClientBillingSummary {
  total_invoiced: number
  total_paid: number
  outstanding_balance: number
  invoice_count: number
}

export interface PlatformClientTicketSummary {
  id: number
  subject: string
  status: PlatformTicketStatus
  priority: PlatformTicketPriority
  created_at: string
}

export interface PlatformClientDetail {
  company: Company
  admin_users: { id: number; name: string; email: string }[]
  billing: PlatformClientBillingSummary | null
  usage: {
    user_count: number
    last_login_at: string | null
  }
  tickets: PlatformClientTicketSummary[]
}

export interface CreatePlatformClientInput {
  company_name: string
  currency_code?: string
  timezone?: string
  branch_name?: string
  branch_address?: string
  branch_phone?: string
  billing_phone?: string
  admin_name: string
  admin_email: string
}

export interface CreatePlatformClientResult {
  company: Company
  admin_user: { id: number; name: string; email: string }
  temp_password: string
}

export interface PlatformBillingByClient {
  company_id: number
  company_name: string
  client_code: string
  status: CompanyStatus
  total_invoiced: number
  total_paid: number
  outstanding_balance: number
  invoice_count: number
}

export interface PlatformBillingSummary {
  total_invoiced: number
  total_collected: number
  total_outstanding: number
  client_count: number
  by_client: PlatformBillingByClient[]
}

export interface PlatformLineItemInput {
  product_id: number
  description?: string | null
  quantity: number
  unit_price?: number | null
  tax_rate_id?: number | null
  discount_amount?: number
}

export interface CreatePlatformInvoiceInput {
  branch_id?: number | null
  due_date: string
  notes?: string
  discount_amount?: number
  items: PlatformLineItemInput[]
}

export interface CreatePlatformQuotationInput {
  branch_id?: number | null
  valid_until: string
  notes?: string
  discount_amount?: number
  items: PlatformLineItemInput[]
}

export type { Invoice, Quotation }
