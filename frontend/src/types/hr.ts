export interface Department {
  id: number
  company_id: number
  branch_id: number
  name: string
  parent_id: number | null
}

export interface Position {
  id: number
  company_id: number
  department_id: number
  title: string
  min_salary: number | null
  max_salary: number | null
}

export type EmploymentType = 'full_time' | 'part_time' | 'contract'
export type EmployeeStatus = 'active' | 'on_leave' | 'terminated'

export interface Employee {
  id: number
  company_id: number
  branch_id: number
  department_id: number
  position_id: number
  user_id: number | null
  employee_code: string
  first_name: string
  last_name: string
  photo_url: string | null
  national_id: string | null
  phone: string | null
  email: string | null
  hire_date: string
  termination_date: string | null
  employment_type: EmploymentType
  status: EmployeeStatus
  bank_name: string | null
  bank_account_number: string | null
}

export interface EmployeeFormValues {
  branch_id: number
  department_id: number
  position_id: number
  employee_code: string
  first_name: string
  last_name: string
  national_id?: string | null
  phone?: string | null
  email?: string | null
  hire_date: string
  termination_date?: string | null
  employment_type: EmploymentType
  status?: EmployeeStatus
  bank_name?: string | null
  bank_account_number?: string | null
}

export type EmployeeContractStatus = 'draft' | 'generated' | 'signed'

export interface EmployeeContract {
  id: number
  employee_id: number
  contract_type: string
  start_date: string
  end_date: string | null
  base_salary: number
  currency_code: string | null
  document_url: string | null
  contract_body: string | null
  status: EmployeeContractStatus
  signed_at: string | null
  signed_by_name: string | null
}

export interface AttendanceGeofence {
  id: number
  company_id: number
  branch_id: number
  name: string
  latitude: number
  longitude: number
  radius_meters: number
  is_active: boolean
}

export type AttendanceStatus = 'present' | 'absent' | 'late' | 'on_leave'

export interface Attendance {
  id: number
  employee_id: number
  date: string
  clock_in: string | null
  clock_out: string | null
  status: AttendanceStatus
}

export interface LeaveType {
  id: number
  company_id: number
  name: string
  days_per_year: number
  is_paid: boolean
}

export type LeaveRequestStatus = 'pending' | 'approved' | 'rejected'

export interface LeaveRequest {
  id: number
  employee_id: number
  leave_type_id: number
  start_date: string
  end_date: string
  days_count: number
  reason: string | null
  status: LeaveRequestStatus
  approved_by: number | null
}

export type PayrollRunStatus = 'draft' | 'processed'

export interface PayrollRun {
  id: number
  company_id: number
  branch_id: number | null
  period_start: string
  period_end: string
  status: PayrollRunStatus
  processed_by: number | null
  journal_entry_id: number | null
}

export interface Payslip {
  id: number
  payroll_run_id: number
  employee_id: number
  gross_pay: number
  total_deductions: number
  net_pay: number
  breakdown: { basic_salary: number; allowances: Record<string, number>; deductions: Record<string, number> }
}

export interface SalaryStructure {
  id: number
  employee_id: number
  basic_salary: number
  allowances: Record<string, number>
  effective_date: string
}

export type DeductionType = 'tax' | 'pension' | 'other'
export type CalculationType = 'percentage' | 'fixed' | 'bracket'

export interface DeductionBracket {
  min: number
  max: number | null
  rate: number
}

export interface StatutoryDeductionRule {
  id: number
  company_id: number
  name: string
  type: DeductionType
  calculation_type: CalculationType
  config: { rate?: number; amount?: number; brackets?: DeductionBracket[] }
  country_code: string
  is_active: boolean
}
