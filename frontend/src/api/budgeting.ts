import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { BudgetLine, BudgetPeriod, BudgetPeriodStatus, BudgetVsActualLine } from '@/types/budgeting'

export async function fetchBudgetPeriods(params: { page?: number; per_page?: number } = {}): Promise<PaginatedResponse<BudgetPeriod>> {
  const { data } = await api.get<PaginatedResponse<BudgetPeriod>>('/budget-periods', { params })
  return data
}

export async function fetchBudgetPeriod(id: number): Promise<BudgetPeriod> {
  const { data } = await api.get<ApiResponse<BudgetPeriod>>(`/budget-periods/${id}`)
  return data.data
}

export interface BudgetPeriodInput {
  name: string
  start_date: string
  end_date: string
  status?: BudgetPeriodStatus
}

export async function createBudgetPeriod(values: BudgetPeriodInput): Promise<BudgetPeriod> {
  const { data } = await api.post<ApiResponse<BudgetPeriod>>('/budget-periods', values)
  return data.data
}

export async function updateBudgetPeriod(id: number, values: Partial<BudgetPeriodInput>): Promise<BudgetPeriod> {
  const { data } = await api.put<ApiResponse<BudgetPeriod>>(`/budget-periods/${id}`, values)
  return data.data
}

export async function fetchBudgetVsActual(id: number): Promise<BudgetVsActualLine[]> {
  const { data } = await api.get<ApiResponse<BudgetVsActualLine[]>>(`/budget-periods/${id}/vs-actual`)
  return data.data
}

export async function fetchBudgetLines(budgetPeriodId: number): Promise<BudgetLine[]> {
  const { data } = await api.get<PaginatedResponse<BudgetLine>>('/budget-lines', { params: { budget_period_id: budgetPeriodId, per_page: 100 } })
  return data.data
}

export interface BudgetLineInput {
  budget_period_id: number
  branch_id?: number | null
  department_id?: number | null
  account_id: number
  budgeted_amount: number
}

export async function createBudgetLine(values: BudgetLineInput): Promise<BudgetLine> {
  const { data } = await api.post<ApiResponse<BudgetLine>>('/budget-lines', values)
  return data.data
}

export async function deleteBudgetLine(id: number): Promise<void> {
  await api.delete(`/budget-lines/${id}`)
}
