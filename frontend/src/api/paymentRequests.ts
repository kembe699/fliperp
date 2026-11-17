import { api } from '@/lib/api'
import { downloadFile, downloadPdf } from '@/lib/pdf-download'
import type { ApiResponse, PaginatedResponse } from '@/types/api'

export type PaymentRequestStatus = 'draft' | 'submitted' | 'partially_approved' | 'approved' | 'paid' | 'rejected' | 'cancelled'

export const PAYMENT_REQUEST_STATUS_LABEL: Record<PaymentRequestStatus, string> = {
  draft: 'Draft',
  submitted: 'Submitted',
  partially_approved: 'Approved (in part)',
  approved: 'Approved – to pay',
  paid: 'Paid',
  rejected: 'Rejected',
  cancelled: 'Cancelled',
}

export interface PaymentRequestApproval {
  id: number
  round: number
  sequence: number
  label: string
  approver: string | null
  status: 'pending' | 'approved' | 'rejected' | 'cancelled'
  decided_by_name: string | null
  decided_at: string | null
  comment: string | null
}

export interface PaymentRequestEvent {
  id: number
  action: string
  from_status: string | null
  to_status: string | null
  comment: string | null
  user_name: string | null
  at: string
}

export interface PaymentRequestAttachment {
  id: number
  file_name: string
  file_size: number
  mime_type: string
  uploaded_by_name: string | null
  uploaded_at: string
}

export interface PaymentRequest {
  id: number
  reference_number: string
  request_date: string
  status: PaymentRequestStatus
  requested_by: number
  requester_name: string | null
  supplier_id: number | null
  supplier_name: string | null
  payee_name: string | null
  payee: string
  description: string
  amount: number
  currency_code: string
  exchange_rate: number
  base_amount: number
  expense_account_id: number | null
  expense_account: string | null
  grant_id: number | null
  grant_code: string | null
  dea_id: number | null
  dea_code: string | null
  project_id: number | null
  project_name: string | null
  cost_centre_id: number | null
  cost_centre_code: string | null
  purchase_order_id: number | null
  purchase_order_reference: string | null
  rfq_id: number | null
  rfq_reference: string | null
  supplier_bill_id: number | null
  supplier_bill_reference: string | null
  approval_round: number
  current_step: { sequence: number; label: string } | null
  submitted_at: string | null
  approved_at: string | null
  payment_date: string | null
  payment_type_name: string | null
  payment_account: string | null
  payment_reference: string | null
  journal_entry_id: number | null
  journal_entry_reference: string | null
  supplier_payment_id: number | null
  paid_at: string | null
  paid_by_name: string | null
  created_at: string
  approvals?: PaymentRequestApproval[]
  events?: PaymentRequestEvent[]
  attachments?: PaymentRequestAttachment[]
  can: { update: boolean; delete: boolean; submit: boolean; decide: boolean; pay: boolean; cancel: boolean; attach: boolean } | null
}

export interface PaymentRequestInput {
  request_date: string
  supplier_id?: number | null
  payee_name?: string | null
  description: string
  amount: number
  currency_code: string
  exchange_rate?: number | null
  expense_account_id?: number | null
  grant_id?: number | null
  dea_id?: number | null
  project_id?: number | null
  cost_centre_id?: number | null
  purchase_order_id?: number | null
  rfq_id?: number | null
  supplier_bill_id?: number | null
}

export type PaymentRequestTab = 'all' | 'mine' | 'awaiting'

export async function fetchPaymentRequests(tab: PaymentRequestTab = 'all'): Promise<PaginatedResponse<PaymentRequest>> {
  const { data } = await api.get<PaginatedResponse<PaymentRequest>>('/payment-requests', { params: { tab, per_page: 500 } })
  return data
}

export async function fetchPaymentRequest(id: number): Promise<PaymentRequest> {
  const { data } = await api.get<ApiResponse<PaymentRequest>>(`/payment-requests/${id}`)
  return data.data
}

export async function createPaymentRequest(values: PaymentRequestInput): Promise<PaymentRequest> {
  const { data } = await api.post<ApiResponse<PaymentRequest>>('/payment-requests', values)
  return data.data
}

export async function updatePaymentRequest(id: number, values: Partial<PaymentRequestInput>): Promise<PaymentRequest> {
  const { data } = await api.put<ApiResponse<PaymentRequest>>(`/payment-requests/${id}`, values)
  return data.data
}

export async function deletePaymentRequest(id: number): Promise<void> {
  await api.delete(`/payment-requests/${id}`)
}

async function act(id: number, action: string, body: Record<string, unknown> = {}): Promise<PaymentRequest> {
  const { data } = await api.post<ApiResponse<PaymentRequest>>(`/payment-requests/${id}/${action}`, body)
  return data.data
}

export const submitPaymentRequest = (id: number, comment?: string) => act(id, 'submit', { comment })
export const approvePaymentRequest = (id: number, comment?: string) => act(id, 'approve', { comment })
export const rejectPaymentRequest = (id: number, comment: string) => act(id, 'reject', { comment })
export const cancelPaymentRequest = (id: number, comment?: string) => act(id, 'cancel', { comment })
export const payPaymentRequest = (id: number, values: { payment_date: string; payment_type_id?: number | null; reference?: string | null }) => act(id, 'pay', values)

export async function uploadPaymentRequestAttachments(id: number, files: File[]): Promise<PaymentRequest> {
  const form = new FormData()
  files.forEach((file) => form.append('attachments[]', file))
  const { data } = await api.post<ApiResponse<PaymentRequest>>(`/payment-requests/${id}/attachments`, form, { headers: { 'Content-Type': 'multipart/form-data' } })
  return data.data
}

export async function deletePaymentRequestAttachment(id: number, attachmentId: number): Promise<PaymentRequest> {
  const { data } = await api.delete<ApiResponse<PaymentRequest>>(`/payment-requests/${id}/attachments/${attachmentId}`)
  return data.data
}

export function downloadPaymentRequestAttachment(id: number, attachment: PaymentRequestAttachment): Promise<void> {
  return downloadFile(`/payment-requests/${id}/attachments/${attachment.id}`, attachment.file_name, attachment.mime_type)
}

export function downloadPaymentRequestVoucher(request: PaymentRequest): Promise<void> {
  return downloadPdf(`/payment-requests/${request.id}/pdf`, `payment-request-${request.reference_number}.pdf`)
}

// ── Settings › Payment Approvals ────────────────────────────────────────────

export interface ApprovalRuleStep {
  id?: number
  sequence?: number
  label: string
  role_id: number | null
  role_name?: string | null
  user_id: number | null
  user_name?: string | null
}

export interface ApprovalRule {
  id: number
  name: string
  min_amount: number
  max_amount: number | null
  is_active: boolean
  steps: ApprovalRuleStep[]
}

export interface ApprovalRuleInput {
  name: string
  min_amount: number
  max_amount: number | null
  is_active: boolean
  steps: { label: string; role_id: number | null; user_id: number | null }[]
}

export interface ApprovalRulesPayload {
  rules: ApprovalRule[]
  roles: { id: number; name: string }[]
  users: { id: number; name: string; email: string }[]
}

export async function fetchApprovalRules(): Promise<ApprovalRulesPayload> {
  const { data } = await api.get<ApiResponse<ApprovalRulesPayload>>('/payment-approval-rules')
  return data.data
}

export async function createApprovalRule(values: ApprovalRuleInput): Promise<ApprovalRule> {
  const { data } = await api.post<ApiResponse<ApprovalRule>>('/payment-approval-rules', values)
  return data.data
}

export async function updateApprovalRule(id: number, values: Partial<ApprovalRuleInput>): Promise<ApprovalRule> {
  const { data } = await api.put<ApiResponse<ApprovalRule>>(`/payment-approval-rules/${id}`, values)
  return data.data
}

export async function deleteApprovalRule(id: number): Promise<void> {
  await api.delete(`/payment-approval-rules/${id}`)
}
