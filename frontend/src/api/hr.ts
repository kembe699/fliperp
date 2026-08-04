import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type {
  Attendance,
  AttendanceStatus,
  Department,
  Employee,
  EmployeeContract,
  EmployeeFormValues,
  LeaveRequest,
  LeaveRequestStatus,
  LeaveType,
  PayrollRun,
  Payslip,
  Position,
  SalaryStructure,
  StatutoryDeductionRule,
} from '@/types/hr'

// Departments & Positions (read-only lookups for Employee form)
export async function fetchDepartments(): Promise<Department[]> {
  const { data } = await api.get<PaginatedResponse<Department>>('/departments', { params: { per_page: 100 } })
  return data.data
}

export async function fetchPositions(): Promise<Position[]> {
  const { data } = await api.get<PaginatedResponse<Position>>('/positions', { params: { per_page: 100 } })
  return data.data
}

// Employees
export async function fetchEmployees(params: { page?: number; per_page?: number } = {}): Promise<PaginatedResponse<Employee>> {
  const { data } = await api.get<PaginatedResponse<Employee>>('/employees', { params })
  return data
}

export async function fetchEmployee(id: number): Promise<Employee> {
  const { data } = await api.get<ApiResponse<Employee>>(`/employees/${id}`)
  return data.data
}

export async function createEmployee(values: EmployeeFormValues): Promise<Employee> {
  const { data } = await api.post<ApiResponse<Employee>>('/employees', values)
  return data.data
}

export async function updateEmployee(id: number, values: Partial<EmployeeFormValues>): Promise<Employee> {
  const { data } = await api.put<ApiResponse<Employee>>(`/employees/${id}`, values)
  return data.data
}

export async function uploadEmployeePhoto(id: number, file: File): Promise<Employee> {
  const formData = new FormData()
  formData.append('image', file)
  const { data } = await api.post<ApiResponse<Employee>>(`/employees/${id}/photo`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data.data
}

export async function deleteEmployeePhoto(id: number): Promise<Employee> {
  const { data } = await api.delete<ApiResponse<Employee>>(`/employees/${id}/photo`)
  return data.data
}

// Employee contracts
export async function fetchEmployeeContracts(employeeId: number): Promise<EmployeeContract[]> {
  const { data } = await api.get<PaginatedResponse<EmployeeContract>>('/employee-contracts', { params: { employee_id: employeeId, per_page: 50 } })
  return data.data
}

export interface EmployeeContractInput {
  employee_id: number
  contract_type: string
  start_date: string
  end_date?: string | null
  base_salary: number
  currency_code?: string | null
  document_url?: string | null
  contract_body?: string | null
}

export async function createEmployeeContract(values: EmployeeContractInput): Promise<EmployeeContract> {
  const { data } = await api.post<ApiResponse<EmployeeContract>>('/employee-contracts', values)
  return data.data
}

export async function updateEmployeeContract(
  id: number,
  values: Partial<EmployeeContractInput> & { status?: 'draft' | 'generated' },
): Promise<EmployeeContract> {
  const { data } = await api.put<ApiResponse<EmployeeContract>>(`/employee-contracts/${id}`, values)
  return data.data
}

export async function uploadEmployeeContractDocument(
  id: number,
  file: File,
  options: { isSignedPhysicalCopy?: boolean; signedByName?: string } = {},
): Promise<EmployeeContract> {
  const formData = new FormData()
  formData.append('document', file)
  if (options.isSignedPhysicalCopy) formData.append('is_signed_physical_copy', '1')
  if (options.signedByName) formData.append('signed_by_name', options.signedByName)
  const { data } = await api.post<ApiResponse<EmployeeContract>>(`/employee-contracts/${id}/upload-document`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data.data
}

export async function signEmployeeContract(id: number, signatureData: string, signedByName: string): Promise<EmployeeContract> {
  const { data } = await api.post<ApiResponse<EmployeeContract>>(`/employee-contracts/${id}/sign`, {
    signature_data: signatureData,
    signed_by_name: signedByName,
  })
  return data.data
}

// Attendance
export interface AttendanceFilters {
  page?: number
  per_page?: number
  employee_id?: number
  date_from?: string
  date_to?: string
  status?: AttendanceStatus
  department_id?: number
  search?: string
}

export async function fetchAttendance(filters: AttendanceFilters): Promise<PaginatedResponse<Attendance>> {
  const { data } = await api.get<PaginatedResponse<Attendance>>('/attendance', { params: filters })
  return data
}

export interface AttendanceInput {
  employee_id: number
  date: string
  clock_in?: string | null
  clock_out?: string | null
  status: AttendanceStatus
}

export async function createAttendance(values: AttendanceInput): Promise<Attendance> {
  const { data } = await api.post<ApiResponse<Attendance>>('/attendance', values)
  return data.data
}

// Leave types & requests
export async function fetchLeaveTypes(): Promise<LeaveType[]> {
  const { data } = await api.get<PaginatedResponse<LeaveType>>('/leave-types', { params: { per_page: 100 } })
  return data.data
}

export interface LeaveRequestFilters {
  page?: number
  per_page?: number
  employee_id?: number
  status?: LeaveRequestStatus
}

export async function fetchLeaveRequests(filters: LeaveRequestFilters): Promise<PaginatedResponse<LeaveRequest>> {
  const { data } = await api.get<PaginatedResponse<LeaveRequest>>('/leave-requests', { params: filters })
  return data
}

export interface LeaveRequestInput {
  employee_id: number
  leave_type_id: number
  start_date: string
  end_date: string
  reason?: string | null
}

export async function createLeaveRequest(values: LeaveRequestInput): Promise<LeaveRequest> {
  const { data } = await api.post<ApiResponse<LeaveRequest>>('/leave-requests', values)
  return data.data
}

export async function approveLeaveRequest(id: number): Promise<LeaveRequest> {
  const { data } = await api.post<ApiResponse<LeaveRequest>>(`/leave-requests/${id}/approve`)
  return data.data
}

export async function rejectLeaveRequest(id: number): Promise<LeaveRequest> {
  const { data } = await api.post<ApiResponse<LeaveRequest>>(`/leave-requests/${id}/reject`)
  return data.data
}

// Salary structures
export async function fetchSalaryStructures(params: { employee_id?: number; page?: number; per_page?: number } = {}): Promise<PaginatedResponse<SalaryStructure>> {
  const { data } = await api.get<PaginatedResponse<SalaryStructure>>('/salary-structures', { params })
  return data
}

export interface SalaryStructureInput {
  employee_id: number
  basic_salary: number
  allowances?: Record<string, number>
  effective_date: string
}

export async function createSalaryStructure(values: SalaryStructureInput): Promise<SalaryStructure> {
  const { data } = await api.post<ApiResponse<SalaryStructure>>('/salary-structures', values)
  return data.data
}

// Payroll runs
export async function fetchPayrollRuns(params: { page?: number; per_page?: number } = {}): Promise<PaginatedResponse<PayrollRun>> {
  const { data } = await api.get<PaginatedResponse<PayrollRun>>('/payroll-runs', { params })
  return data
}

export async function fetchPayrollRun(id: number): Promise<PayrollRun> {
  const { data } = await api.get<ApiResponse<PayrollRun>>(`/payroll-runs/${id}`)
  return data.data
}

export async function createPayrollRun(values: { branch_id?: number | null; period_start: string; period_end: string }): Promise<PayrollRun> {
  const { data } = await api.post<ApiResponse<PayrollRun>>('/payroll-runs', values)
  return data.data
}

export async function processPayrollRun(id: number): Promise<PayrollRun> {
  const { data } = await api.post<ApiResponse<PayrollRun>>(`/payroll-runs/${id}/process`)
  return data.data
}

export async function fetchPayslips(payrollRunId: number): Promise<Payslip[]> {
  const { data } = await api.get<ApiResponse<Payslip[]>>(`/payroll-runs/${payrollRunId}/payslips`)
  return data.data
}

// Statutory deduction rules
export async function fetchStatutoryDeductionRules(): Promise<StatutoryDeductionRule[]> {
  const { data } = await api.get<PaginatedResponse<StatutoryDeductionRule>>('/statutory-deduction-rules', { params: { per_page: 100 } })
  return data.data
}

export interface StatutoryDeductionRuleInput {
  name: string
  type: StatutoryDeductionRule['type']
  calculation_type: StatutoryDeductionRule['calculation_type']
  country_code: string
  is_active?: boolean
  config: StatutoryDeductionRule['config']
}

export async function createStatutoryDeductionRule(values: StatutoryDeductionRuleInput): Promise<StatutoryDeductionRule> {
  const { data } = await api.post<ApiResponse<StatutoryDeductionRule>>('/statutory-deduction-rules', values)
  return data.data
}

export async function updateStatutoryDeductionRule(id: number, values: Partial<StatutoryDeductionRuleInput>): Promise<StatutoryDeductionRule> {
  const { data } = await api.put<ApiResponse<StatutoryDeductionRule>>(`/statutory-deduction-rules/${id}`, values)
  return data.data
}

export async function deleteStatutoryDeductionRule(id: number): Promise<void> {
  await api.delete(`/statutory-deduction-rules/${id}`)
}
