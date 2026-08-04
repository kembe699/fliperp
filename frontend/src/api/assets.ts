import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { Asset, AssetCategory, AssetDepreciationSchedule, AssetFormValues, AssetMaintenanceLog, DepreciationMethod } from '@/types/assets'

// Asset categories
export async function fetchAssetCategories(): Promise<AssetCategory[]> {
  const { data } = await api.get<PaginatedResponse<AssetCategory>>('/asset-categories', { params: { per_page: 100 } })
  return data.data
}

export interface AssetCategoryInput {
  name: string
  depreciation_method: DepreciationMethod
  useful_life_years: number
}

export async function createAssetCategory(values: AssetCategoryInput): Promise<AssetCategory> {
  const { data } = await api.post<ApiResponse<AssetCategory>>('/asset-categories', values)
  return data.data
}

export async function updateAssetCategory(id: number, values: Partial<AssetCategoryInput>): Promise<AssetCategory> {
  const { data } = await api.put<ApiResponse<AssetCategory>>(`/asset-categories/${id}`, values)
  return data.data
}

export async function deleteAssetCategory(id: number): Promise<void> {
  await api.delete(`/asset-categories/${id}`)
}

// Assets
export async function fetchAssets(params: { page?: number; per_page?: number } = {}): Promise<PaginatedResponse<Asset>> {
  const { data } = await api.get<PaginatedResponse<Asset>>('/assets', { params })
  return data
}

export async function fetchAsset(id: number): Promise<Asset> {
  const { data } = await api.get<ApiResponse<Asset>>(`/assets/${id}`)
  return data.data
}

export async function createAsset(values: AssetFormValues): Promise<Asset> {
  const { data } = await api.post<ApiResponse<Asset>>('/assets', values)
  return data.data
}

export async function updateAsset(id: number, values: Partial<AssetFormValues>): Promise<Asset> {
  const { data } = await api.put<ApiResponse<Asset>>(`/assets/${id}`, values)
  return data.data
}

export async function assignAsset(id: number, employeeId: number): Promise<Asset> {
  const { data } = await api.post<ApiResponse<Asset>>(`/assets/${id}/assign`, { employee_id: employeeId })
  return data.data
}

export async function disposeAsset(id: number): Promise<Asset> {
  const { data } = await api.post<ApiResponse<Asset>>(`/assets/${id}/dispose`)
  return data.data
}

export async function fetchAssetMaintenanceLogs(id: number): Promise<AssetMaintenanceLog[]> {
  const { data } = await api.get<ApiResponse<AssetMaintenanceLog[]>>(`/assets/${id}/maintenance-logs`)
  return data.data
}

export async function createAssetMaintenanceLog(
  id: number,
  values: { maintenance_date: string; description: string; cost?: number | null; performed_by?: string | null },
): Promise<AssetMaintenanceLog> {
  const { data } = await api.post<ApiResponse<AssetMaintenanceLog>>(`/assets/${id}/maintenance-logs`, values)
  return data.data
}

export async function fetchAssetDepreciationSchedules(id: number): Promise<AssetDepreciationSchedule[]> {
  const { data } = await api.get<ApiResponse<AssetDepreciationSchedule[]>>(`/assets/${id}/depreciation-schedules`)
  return data.data
}

export async function runDepreciation(): Promise<{ schedules_created: number; journal_entry_id: number | null }> {
  const { data } = await api.post<ApiResponse<{ schedules_created: number; journal_entry_id: number | null }>>('/assets/run-depreciation')
  return data.data
}
