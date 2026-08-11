import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { Customer } from '@/types/customer'
import type { Product } from '@/types/product'
import type { Quotation } from '@/types/quotation'
import type {
  CrmAccountAssignment,
  CrmActivity,
  CrmActivityType,
  CrmAssignmentRole,
  CrmCustomerService,
  CrmCustomerServiceStatus,
  CrmDeal,
  CrmDealDetail,
  CrmEmail,
  CrmKanbanColumn,
  CrmLead,
  CrmLeadDetail,
  CrmLeadStatus,
  CrmMeeting,
  CrmMeetingStatus,
  CrmPipelineStage,
  CrmReportSummary,
  CrmService,
  CrmServiceStatement,
  CrmStaffReportRow,
  CrmTrendPoint,
} from '@/types/crm'

// --- Services catalog ---

export interface CrmServiceFilters {
  page?: number
  per_page?: number
  category?: string
  is_active?: boolean
}

export async function fetchCrmServices(filters: CrmServiceFilters = {}): Promise<PaginatedResponse<CrmService>> {
  const { data } = await api.get<PaginatedResponse<CrmService>>('/crm/services', { params: filters })
  return data
}

export interface CrmServiceInput {
  name: string
  description?: string | null
  category?: string | null
  default_price: number
  is_active?: boolean
}

export async function createCrmService(values: CrmServiceInput): Promise<CrmService> {
  const { data } = await api.post<ApiResponse<CrmService>>('/crm/services', values)
  return data.data
}

export async function updateCrmService(id: number, values: Partial<CrmServiceInput>): Promise<CrmService> {
  const { data } = await api.put<ApiResponse<CrmService>>(`/crm/services/${id}`, values)
  return data.data
}

export async function deleteCrmService(id: number): Promise<void> {
  await api.delete(`/crm/services/${id}`)
}

// Quotations/invoices only accept product_id line items — this resolves a CRM service to
// its shadow Product (creating it on first use, see CrmService::ensureProduct) so it can be
// added as an ordinary line item. Called the moment a service is picked in the line items
// editor, not ahead of time, matching the backend's lazy-creation design.
export async function ensureCrmServiceProduct(serviceId: number): Promise<Product> {
  const { data } = await api.post<ApiResponse<Product>>(`/crm/services/${serviceId}/ensure-product`)
  return data.data
}

// --- Pipeline stages ---

export async function fetchCrmPipelineStages(): Promise<CrmPipelineStage[]> {
  const { data } = await api.get<ApiResponse<CrmPipelineStage[]>>('/crm/pipeline-stages')
  return data.data
}

export interface CrmPipelineStageInput {
  name: string
  position?: number
  color?: string | null
  is_closed_won?: boolean
  is_closed_lost?: boolean
}

export async function createCrmPipelineStage(values: CrmPipelineStageInput): Promise<CrmPipelineStage> {
  const { data } = await api.post<ApiResponse<CrmPipelineStage>>('/crm/pipeline-stages', values)
  return data.data
}

export async function updateCrmPipelineStage(id: number, values: Partial<CrmPipelineStageInput>): Promise<CrmPipelineStage> {
  const { data } = await api.put<ApiResponse<CrmPipelineStage>>(`/crm/pipeline-stages/${id}`, values)
  return data.data
}

export async function deleteCrmPipelineStage(id: number): Promise<void> {
  await api.delete(`/crm/pipeline-stages/${id}`)
}

export async function reorderCrmPipelineStages(stageIds: number[]): Promise<CrmPipelineStage[]> {
  const { data } = await api.post<ApiResponse<CrmPipelineStage[]>>('/crm/pipeline-stages/reorder', { stage_ids: stageIds })
  return data.data
}

// --- Leads ---

export interface CrmLeadFilters {
  page?: number
  per_page?: number
  status?: CrmLeadStatus
  source?: string
  assigned_to?: number
  branch_id?: number
  search?: string
}

export async function fetchCrmLeads(filters: CrmLeadFilters = {}): Promise<PaginatedResponse<CrmLead>> {
  const { data } = await api.get<PaginatedResponse<CrmLead>>('/crm/leads', { params: filters })
  return data
}

export interface CrmLeadInput {
  branch_id?: number | null
  name: string
  company_name?: string | null
  email?: string | null
  phone?: string | null
  source?: string | null
  assigned_to?: number | null
  notes?: string | null
}

export async function createCrmLead(values: CrmLeadInput): Promise<CrmLead> {
  const { data } = await api.post<ApiResponse<CrmLead>>('/crm/leads', values)
  return data.data
}

export async function updateCrmLead(id: number, values: Partial<CrmLeadInput & { status: CrmLeadStatus }>): Promise<CrmLead> {
  const { data } = await api.put<ApiResponse<CrmLead>>(`/crm/leads/${id}`, values)
  return data.data
}

export async function deleteCrmLead(id: number): Promise<void> {
  await api.delete(`/crm/leads/${id}`)
}

export interface ConvertCrmLeadInput {
  branch_id?: number | null
  phone?: string
  customer_type?: 'walk_in' | 'regular' | 'credit'
  credit_limit?: number
}

export async function convertCrmLead(id: number, values: ConvertCrmLeadInput = {}): Promise<CrmLead> {
  const { data } = await api.post<ApiResponse<CrmLead>>(`/crm/leads/${id}/convert`, values)
  return data.data
}

// --- Deals ---

export interface CrmDealFilters {
  page?: number
  per_page?: number
  pipeline_stage_id?: number
  assigned_to?: number
  customer_id?: number
  lead_id?: number
}

export async function fetchCrmDeals(filters: CrmDealFilters = {}): Promise<PaginatedResponse<CrmDeal>> {
  const { data } = await api.get<PaginatedResponse<CrmDeal>>('/crm/deals', { params: filters })
  return data
}

export async function fetchCrmKanban(): Promise<CrmKanbanColumn[]> {
  const { data } = await api.get<ApiResponse<CrmKanbanColumn[]>>('/crm/deals/kanban')
  return data.data
}

export interface CrmDealInput {
  lead_id?: number | null
  customer_id?: number | null
  pipeline_stage_id: number
  crm_service_id?: number | null
  title: string
  // Never sent by the frontend — the backend computes it from attached
  // services and ignores this field entirely (see DealService::syncServices()).
  value?: number
  expected_close_date?: string | null
  assigned_to?: number | null
}

export async function createCrmDeal(values: CrmDealInput): Promise<CrmDeal> {
  const { data } = await api.post<ApiResponse<CrmDeal>>('/crm/deals', values)
  return data.data
}

export async function updateCrmDeal(id: number, values: Partial<CrmDealInput>): Promise<CrmDeal> {
  const { data } = await api.put<ApiResponse<CrmDeal>>(`/crm/deals/${id}`, values)
  return data.data
}

export async function deleteCrmDeal(id: number): Promise<void> {
  await api.delete(`/crm/deals/${id}`)
}

export interface MoveCrmDealStageResult {
  deal: CrmDeal
  promptCustomerServiceLog: boolean
}

export async function moveCrmDealStage(id: number, pipelineStageId: number, lostReason?: string): Promise<MoveCrmDealStageResult> {
  const { data } = await api.patch<ApiResponse<CrmDeal> & { meta: { prompt_customer_service_log: boolean } }>(
    `/crm/deals/${id}/stage`,
    { pipeline_stage_id: pipelineStageId, lost_reason: lostReason },
  )
  return { deal: data.data, promptCustomerServiceLog: data.meta.prompt_customer_service_log }
}

// --- Customer services ---

export interface CrmCustomerServiceInput {
  customer_id: number
  crm_service_id: number
  deal_id?: number | null
  price_charged: number
  start_date: string
  end_date?: string | null
  status?: CrmCustomerServiceStatus
}

export async function fetchCrmCustomerServices(customerId: number, perPage = 50): Promise<PaginatedResponse<CrmCustomerService>> {
  const { data } = await api.get<PaginatedResponse<CrmCustomerService>>('/crm/customer-services', {
    params: { customer_id: customerId, per_page: perPage },
  })
  return data
}

export async function createCrmCustomerService(values: CrmCustomerServiceInput): Promise<CrmCustomerService> {
  const { data } = await api.post<ApiResponse<CrmCustomerService>>('/crm/customer-services', values)
  return data.data
}

export async function updateCrmCustomerService(id: number, values: Partial<CrmCustomerServiceInput>): Promise<CrmCustomerService> {
  const { data } = await api.put<ApiResponse<CrmCustomerService>>(`/crm/customer-services/${id}`, values)
  return data.data
}

export async function deleteCrmCustomerService(id: number): Promise<void> {
  await api.delete(`/crm/customer-services/${id}`)
}

export async function fetchCrmServiceStatement(customerId: number): Promise<CrmServiceStatement> {
  const { data } = await api.get<ApiResponse<CrmServiceStatement>>(`/crm/customers/${customerId}/service-statement`)
  return data.data
}

// --- Account assignments ---

export async function fetchCurrentCrmAssignments(customerId: number): Promise<CrmAccountAssignment[]> {
  const { data } = await api.get<ApiResponse<CrmAccountAssignment[]>>('/crm/account-assignments/current', {
    params: { customer_id: customerId },
  })
  return data.data
}

export interface CrmAccountAssignmentInput {
  customer_id: number
  user_id: number
  role?: CrmAssignmentRole
}

export async function assignCrmAccount(values: CrmAccountAssignmentInput): Promise<CrmAccountAssignment> {
  const { data } = await api.post<ApiResponse<CrmAccountAssignment>>('/crm/account-assignments', values)
  return data.data
}

export async function unassignCrmAccount(id: number): Promise<CrmAccountAssignment> {
  const { data } = await api.post<ApiResponse<CrmAccountAssignment>>(`/crm/account-assignments/${id}/unassign`)
  return data.data
}

// --- Activities ---

export interface CrmActivityFilters {
  page?: number
  per_page?: number
  customer_id?: number
  lead_id?: number
  deal_id?: number
  type?: CrmActivityType
  status?: 'open' | 'resolved'
}

export async function fetchCrmActivities(filters: CrmActivityFilters = {}): Promise<PaginatedResponse<CrmActivity>> {
  const { data } = await api.get<PaginatedResponse<CrmActivity>>('/crm/activities', { params: filters })
  return data
}

export interface CrmActivityInput {
  customer_id?: number | null
  lead_id?: number | null
  deal_id?: number | null
  type: CrmActivityType
  subject: string
  description?: string | null
  activity_date: string
}

export async function createCrmActivity(values: CrmActivityInput): Promise<CrmActivity> {
  const { data } = await api.post<ApiResponse<CrmActivity>>('/crm/activities', values)
  return data.data
}

export async function resolveCrmActivity(id: number): Promise<CrmActivity> {
  const { data } = await api.post<ApiResponse<CrmActivity>>(`/crm/activities/${id}/resolve`)
  return data.data
}

// --- CRM-scoped customers (staff-visibility aware) ---

export interface CrmCustomerFilters {
  page?: number
  per_page?: number
  customer_type?: string
  branch_id?: number
  search?: string
}

export async function fetchCrmCustomers(filters: CrmCustomerFilters = {}): Promise<PaginatedResponse<Customer>> {
  const { data } = await api.get<PaginatedResponse<Customer>>('/crm/customers', { params: filters })
  return data
}

export async function fetchCrmCustomer(customerId: number): Promise<Customer> {
  const { data } = await api.get<ApiResponse<Customer>>(`/crm/customers/${customerId}`)
  return data.data
}

// --- Reports ---

export async function fetchCrmReportSummary(params: { from?: string; to?: string; branch_id?: number } = {}): Promise<CrmReportSummary> {
  const { data } = await api.get<ApiResponse<CrmReportSummary>>('/crm/reports/summary', { params })
  return data.data
}

export async function fetchCrmStaffReport(): Promise<CrmStaffReportRow[]> {
  const { data } = await api.get<ApiResponse<CrmStaffReportRow[]>>('/crm/reports/staff')
  return data.data
}

export async function fetchCrmTrends(params: { months?: number; branch_id?: number } = {}): Promise<CrmTrendPoint[]> {
  const { data } = await api.get<ApiResponse<CrmTrendPoint[]>>('/crm/reports/trends', { params })
  return data.data
}

// --- Lead/Deal service sync + aggregated detail + quotation-from-CRM ---

export async function syncCrmLeadServices(leadId: number, serviceIds: number[]): Promise<CrmService[]> {
  const { data } = await api.post<ApiResponse<CrmService[]>>(`/crm/leads/${leadId}/services`, { service_ids: serviceIds })
  return data.data
}

export async function syncCrmDealServices(dealId: number, serviceIds: number[]): Promise<CrmService[]> {
  const { data } = await api.post<ApiResponse<CrmService[]>>(`/crm/deals/${dealId}/services`, { service_ids: serviceIds })
  return data.data
}

export async function fetchCrmLeadDetail(leadId: number): Promise<CrmLeadDetail> {
  const { data } = await api.get<ApiResponse<CrmLeadDetail>>(`/crm/leads/${leadId}/detail`)
  return data.data
}

export async function fetchCrmDealDetail(dealId: number): Promise<CrmDealDetail> {
  const { data } = await api.get<ApiResponse<CrmDealDetail>>(`/crm/deals/${dealId}/detail`)
  return data.data
}

export interface CrmQuotationLineInput {
  crm_service_id: number
  quantity?: number
  unit_price?: number
}

export interface CreateCrmQuotationInput {
  branch_id?: number | null
  price_list_id?: number | null
  valid_until?: string
  notes?: string | null
  items?: CrmQuotationLineInput[]
}

export async function createCrmLeadQuotation(leadId: number, values: CreateCrmQuotationInput = {}): Promise<Quotation> {
  const { data } = await api.post<ApiResponse<Quotation>>(`/crm/leads/${leadId}/quotations`, values)
  return data.data
}

export async function createCrmDealQuotation(dealId: number, values: CreateCrmQuotationInput = {}): Promise<Quotation> {
  const { data } = await api.post<ApiResponse<Quotation>>(`/crm/deals/${dealId}/quotations`, values)
  return data.data
}

export interface SendQuotationToContactInput {
  to_email?: string
  to_name?: string
  subject?: string
  body?: string
  lead_id?: number
  deal_id?: number
  customer_id?: number
}

export async function sendQuotationToContact(quotationId: number, values: SendQuotationToContactInput = {}): Promise<CrmEmail> {
  const { data } = await api.post<ApiResponse<CrmEmail>>(`/crm/quotations/${quotationId}/send-to-contact`, values)
  return data.data
}

// --- Meetings ---

export interface CrmMeetingFilters {
  page?: number
  per_page?: number
  lead_id?: number
  deal_id?: number
  customer_id?: number
  organizer_id?: number
  status?: CrmMeetingStatus
  from?: string
  to?: string
}

export async function fetchCrmMeetings(filters: CrmMeetingFilters = {}): Promise<PaginatedResponse<CrmMeeting>> {
  const { data } = await api.get<PaginatedResponse<CrmMeeting>>('/crm/meetings', { params: filters })
  return data
}

export interface CrmMeetingAttendeeInput {
  user_id?: number
  external_name?: string
  external_email?: string
}

export interface CrmMeetingInput {
  lead_id?: number | null
  deal_id?: number | null
  customer_id?: number | null
  title: string
  description?: string | null
  scheduled_at: string
  duration_minutes?: number
  location?: string | null
  meeting_link?: string | null
  organizer_id?: number
  attendees?: CrmMeetingAttendeeInput[]
}

export async function createCrmMeeting(values: CrmMeetingInput): Promise<CrmMeeting> {
  const { data } = await api.post<ApiResponse<CrmMeeting>>('/crm/meetings', values)
  return data.data
}

export async function updateCrmMeetingStatus(meetingId: number, status: CrmMeetingStatus): Promise<CrmMeeting> {
  const { data } = await api.patch<ApiResponse<CrmMeeting>>(`/crm/meetings/${meetingId}/status`, { status })
  return data.data
}

// --- CRM direct emails ---

export interface CrmEmailFilters {
  page?: number
  per_page?: number
  lead_id?: number
  deal_id?: number
  customer_id?: number
  sent_by?: number
}

export async function fetchCrmEmails(filters: CrmEmailFilters = {}): Promise<PaginatedResponse<CrmEmail>> {
  const { data } = await api.get<PaginatedResponse<CrmEmail>>('/crm/emails', { params: filters })
  return data
}

export interface CrmEmailInput {
  lead_id?: number | null
  deal_id?: number | null
  customer_id?: number | null
  to_email: string
  to_name?: string | null
  subject: string
  body: string
}

export async function sendCrmEmail(values: CrmEmailInput): Promise<CrmEmail> {
  const { data } = await api.post<ApiResponse<CrmEmail>>('/crm/emails/send', values)
  return data.data
}
