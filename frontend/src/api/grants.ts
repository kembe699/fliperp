import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { CostCentre, DimensionOptions, Donor, Grant, GrantBudgetLine, GrantDea, GrantReport, GrantStatus, GrantSummary } from '@/types/grants'

/** SOFs (with DEAs), projects and cost centres for line pickers; `all` includes closed/inactive ones (report filters). */
export async function fetchDimensionOptions(all = false): Promise<DimensionOptions> {
  const { data } = await api.get<ApiResponse<DimensionOptions>>('/dimensions/options', { params: all ? { all: 1 } : {} })
  return data.data
}

// ── Donors ─────────────────────────────────────────────────────────────

export interface DonorInput {
  name: string
  code?: string | null
  contact_email?: string | null
  notes?: string | null
  is_active?: boolean
}

export async function fetchDonors(params: { search?: string; per_page?: number } = {}): Promise<PaginatedResponse<Donor>> {
  const { data } = await api.get<PaginatedResponse<Donor>>('/donors', { params: { per_page: 200, ...params } })
  return data
}

export async function createDonor(values: DonorInput): Promise<Donor> {
  const { data } = await api.post<ApiResponse<Donor>>('/donors', values)
  return data.data
}

export async function updateDonor(id: number, values: Partial<DonorInput>): Promise<Donor> {
  const { data } = await api.put<ApiResponse<Donor>>(`/donors/${id}`, values)
  return data.data
}

export async function deleteDonor(id: number): Promise<void> {
  await api.delete(`/donors/${id}`)
}

// ── Cost centres ───────────────────────────────────────────────────────

export interface CostCentreInput {
  code: string
  name: string
  is_active?: boolean
}

export async function fetchCostCentres(params: { search?: string; per_page?: number } = {}): Promise<PaginatedResponse<CostCentre>> {
  const { data } = await api.get<PaginatedResponse<CostCentre>>('/cost-centres', { params: { per_page: 200, ...params } })
  return data
}

export async function createCostCentre(values: CostCentreInput): Promise<CostCentre> {
  const { data } = await api.post<ApiResponse<CostCentre>>('/cost-centres', values)
  return data.data
}

export async function updateCostCentre(id: number, values: Partial<CostCentreInput>): Promise<CostCentre> {
  const { data } = await api.put<ApiResponse<CostCentre>>(`/cost-centres/${id}`, values)
  return data.data
}

export async function deleteCostCentre(id: number): Promise<void> {
  await api.delete(`/cost-centres/${id}`)
}

// ── SOFs (grants) ──────────────────────────────────────────────────────

export interface GrantInput {
  donor_id: number
  code: string
  name: string
  currency_code: string
  start_date: string
  end_date: string
  budget_amount?: number | null
  status?: GrantStatus
  notes?: string | null
}

export async function fetchGrants(params: { donor_id?: number; status?: string; search?: string; per_page?: number } = {}): Promise<PaginatedResponse<Grant>> {
  const { data } = await api.get<PaginatedResponse<Grant>>('/grants', { params: { per_page: 200, ...params } })
  return data
}

export async function fetchGrantSummary(): Promise<GrantSummary[]> {
  const { data } = await api.get<ApiResponse<GrantSummary[]>>('/grants/summary')
  return data.data
}

export async function fetchGrant(id: number): Promise<Grant> {
  const { data } = await api.get<ApiResponse<Grant>>(`/grants/${id}`)
  return data.data
}

export async function createGrant(values: GrantInput): Promise<Grant> {
  const { data } = await api.post<ApiResponse<Grant>>('/grants', values)
  return data.data
}

export async function updateGrant(id: number, values: Partial<GrantInput>): Promise<Grant> {
  const { data } = await api.put<ApiResponse<Grant>>(`/grants/${id}`, values)
  return data.data
}

export async function deleteGrant(id: number): Promise<void> {
  await api.delete(`/grants/${id}`)
}

export async function createGrantDea(grantId: number, values: { code: string; name: string }): Promise<GrantDea> {
  const { data } = await api.post<ApiResponse<GrantDea>>(`/grants/${grantId}/deas`, values)
  return data.data
}

export async function updateGrantDea(id: number, values: Partial<{ code: string; name: string; is_active: boolean }>): Promise<GrantDea> {
  const { data } = await api.put<ApiResponse<GrantDea>>(`/grant-deas/${id}`, values)
  return data.data
}

export async function deleteGrantDea(id: number): Promise<void> {
  await api.delete(`/grant-deas/${id}`)
}

/** Sets the budget for an account (and DEA) of the SOF; saving the same pair again replaces it. */
export async function saveGrantBudgetLine(grantId: number, values: { account_id: number; dea_id: number | null; budgeted_amount: number }): Promise<GrantBudgetLine> {
  const { data } = await api.post<ApiResponse<GrantBudgetLine>>(`/grants/${grantId}/budget-lines`, values)
  return data.data
}

export async function deleteGrantBudgetLine(id: number): Promise<void> {
  await api.delete(`/grant-budget-lines/${id}`)
}

export async function fetchGrantReport(id: number, params: { from?: string; to?: string; currency?: string } = {}): Promise<GrantReport> {
  const { data } = await api.get<ApiResponse<GrantReport>>(`/grants/${id}/report`, { params })
  return data.data
}
