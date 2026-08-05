export interface CrmRef {
  id: number
  name: string
}

export interface CrmService {
  id: number
  company_id: number
  name: string
  description: string | null
  category: string | null
  default_price: number
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface CrmPipelineStage {
  id: number
  company_id: number
  name: string
  position: number
  color: string | null
  is_closed_won: boolean
  is_closed_lost: boolean
  created_at: string
  updated_at: string
}

export type CrmLeadStatus = 'open' | 'converted' | 'disqualified'

export interface CrmLead {
  id: number
  company_id: number
  branch_id: number | null
  name: string
  company_name: string | null
  email: string | null
  phone: string | null
  source: string | null
  status: CrmLeadStatus
  converted_customer_id: number | null
  assigned_to: CrmRef | null
  notes: string | null
  created_at: string
  updated_at: string
}

export interface CrmDeal {
  id: number
  company_id: number
  lead_id: number | null
  lead: CrmRef | null
  customer_id: number | null
  customer: CrmRef | null
  pipeline_stage_id: number
  pipeline_stage: CrmPipelineStage | null
  crm_service_id: number | null
  service: CrmRef | null
  title: string
  value: number
  expected_close_date: string | null
  assigned_to: CrmRef | null
  closed_at: string | null
  lost_reason: string | null
  created_at: string
  updated_at: string
}

export interface CrmKanbanColumn {
  stage: CrmPipelineStage
  deals: CrmDeal[]
}

export type CrmCustomerServiceStatus = 'active' | 'completed' | 'cancelled'

export interface CrmCustomerService {
  id: number
  company_id: number
  customer_id: number
  crm_service_id: number
  service: CrmRef | null
  deal_id: number | null
  price_charged: number
  start_date: string
  end_date: string | null
  status: CrmCustomerServiceStatus
  created_at: string
  updated_at: string
}

export interface CrmServiceStatementEntry {
  id: number
  service_id: number
  service_name: string | null
  deal_id: number | null
  price_charged: number
  start_date: string
  end_date: string | null
  status: CrmCustomerServiceStatus
  running_total: number
}

export interface CrmServiceStatement {
  customer_id: number
  customer_name: string
  total_spent: number
  entries: CrmServiceStatementEntry[]
}

export type CrmAssignmentRole = 'primary' | 'support'

export interface CrmAccountAssignment {
  id: number
  company_id: number
  customer_id: number
  customer: CrmRef | null
  user_id: number
  user: CrmRef | null
  role: CrmAssignmentRole
  assigned_at: string
  unassigned_at: string | null
  created_at: string
}

export type CrmActivityType = 'call' | 'email' | 'meeting' | 'note' | 'complaint' | 'follow_up'
export type CrmActivityStatus = 'open' | 'resolved'

export interface CrmActivity {
  id: number
  company_id: number
  customer_id: number | null
  customer: CrmRef | null
  lead_id: number | null
  deal_id: number | null
  type: CrmActivityType
  subject: string
  description: string | null
  logged_by: CrmRef | null
  activity_date: string
  status: CrmActivityStatus
  resolved_at: string | null
  resolved_by: CrmRef | null
  created_at: string
  updated_at: string
}

export interface CrmDealByStage {
  stage_id: number
  stage_name: string
  is_closed_won: boolean
  is_closed_lost: boolean
  count: number
  total_value: number
}

export interface CrmStaffPerformance {
  user_id: number
  user_name: string
  deals_closed_won: number
  total_value: number
}

export interface CrmReportSummary {
  from: string | null
  to: string | null
  branch_id: number | null
  leads: {
    total: number
    by_status: Record<CrmLeadStatus, number>
  }
  deals: {
    by_stage: CrmDealByStage[]
  }
  conversion_rate: number | null
  customers_with_active_services: number
  total_revenue: number
  staff_performance: CrmStaffPerformance[]
}

export interface CrmStaffDeal {
  deal_id: number
  title: string
  stage_name: string | null
  value: number
}

export interface CrmStaffReportRow {
  user_id: number
  user_name: string
  customers_assigned: number
  open_activities_count: number
  open_complaints_count: number
  deals: CrmStaffDeal[]
  closed_won_value: number
}
