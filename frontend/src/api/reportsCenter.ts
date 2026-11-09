import { api } from '@/lib/api'
import type { ApiResponse } from '@/types/api'

export type CenterColumnType = 'text' | 'money' | 'number' | 'percent' | 'date'

export interface CenterColumn {
  key: string
  label: string
  type: CenterColumnType
}

export interface CenterKpi {
  key: string
  label: string
  value: number
  type: CenterColumnType
  sub?: string
}

export interface AuditChange {
  field: string
  from: unknown
  to: unknown
}

/** Most cells are text or numbers; an audit line also carries the fields it changed. */
export type CenterRow = Record<string, string | number | null | AuditChange[]>

/** Every Reports Center report comes back in this one shape. */
export interface CenterReport {
  report: string
  from: string
  to: string
  columns: CenterColumn[]
  rows: CenterRow[]
  totals: Record<string, string | number>
  kpis?: CenterKpi[]
}

export interface CenterParams {
  from: string
  to: string
  branch_id?: number
  group_by?: string
  source?: string
  status?: string
}

export async function fetchCenterReport(report: string, params: CenterParams): Promise<CenterReport> {
  const { data } = await api.get<ApiResponse<CenterReport>>(`/reports/center/${report}`, { params })
  return data.data
}
