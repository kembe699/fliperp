import { api } from '@/lib/api'
import type { ApiResponse } from '@/types/api'
import type { DashboardSummary } from '@/types/dashboard'

export interface DashboardSummaryParams {
  branch_id?: number
  from: string
  to: string
}

export async function fetchDashboardSummary(params: DashboardSummaryParams): Promise<DashboardSummary> {
  const { data } = await api.get<ApiResponse<DashboardSummary>>('/dashboard/summary', { params })
  return data.data
}
