import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { MeActivity, MeActivityStatus, MeIndicator, MeProject, MeProjectDashboard, MeProjectStatus, MeResult } from '@/types/me'

// Projects
export async function fetchMeProjects(params: { page?: number; per_page?: number } = {}): Promise<PaginatedResponse<MeProject>> {
  const { data } = await api.get<PaginatedResponse<MeProject>>('/me-projects', { params })
  return data
}

export async function fetchMeProject(id: number): Promise<MeProject> {
  const { data } = await api.get<ApiResponse<MeProject>>(`/me-projects/${id}`)
  return data.data
}

export interface MeProjectInput {
  name: string
  description?: string | null
  start_date: string
  end_date?: string | null
  status?: MeProjectStatus
  budget_period_id?: number | null
}

export async function createMeProject(values: MeProjectInput): Promise<MeProject> {
  const { data } = await api.post<ApiResponse<MeProject>>('/me-projects', values)
  return data.data
}

export async function fetchMeProjectDashboard(id: number): Promise<MeProjectDashboard> {
  const { data } = await api.get<ApiResponse<MeProjectDashboard>>(`/me-projects/${id}/dashboard`)
  return data.data
}

// Indicators
export async function fetchMeIndicators(projectId: number): Promise<MeIndicator[]> {
  const { data } = await api.get<PaginatedResponse<MeIndicator>>('/me-indicators', { params: { me_project_id: projectId, per_page: 100 } })
  return data.data
}

export interface MeIndicatorInput {
  me_project_id: number
  name: string
  unit_of_measure?: string | null
  target_value: number
  baseline_value?: number
}

export async function createMeIndicator(values: MeIndicatorInput): Promise<MeIndicator> {
  const { data } = await api.post<ApiResponse<MeIndicator>>('/me-indicators', values)
  return data.data
}

// Activities
export async function fetchMeActivities(projectId: number): Promise<MeActivity[]> {
  const { data } = await api.get<PaginatedResponse<MeActivity>>('/me-activities', { params: { me_project_id: projectId, per_page: 100 } })
  return data.data
}

export interface MeActivityInput {
  me_project_id: number
  name: string
  start_date: string
  end_date?: string | null
  responsible_employee_id?: number | null
  status?: MeActivityStatus
}

export async function createMeActivity(values: MeActivityInput): Promise<MeActivity> {
  const { data } = await api.post<ApiResponse<MeActivity>>('/me-activities', values)
  return data.data
}

// Results
export async function fetchMeResults(indicatorId: number): Promise<MeResult[]> {
  const { data } = await api.get<PaginatedResponse<MeResult>>('/me-results', { params: { me_indicator_id: indicatorId, per_page: 100 } })
  return data.data
}

export interface MeResultInput {
  me_indicator_id: number
  reporting_period: string
  actual_value: number
  notes?: string | null
}

export async function createMeResult(values: MeResultInput): Promise<MeResult> {
  const { data } = await api.post<ApiResponse<MeResult>>('/me-results', values)
  return data.data
}
