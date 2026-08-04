export type DepreciationMethod = 'straight_line' | 'reducing_balance'

export interface AssetCategory {
  id: number
  company_id: number
  name: string
  depreciation_method: DepreciationMethod
  useful_life_years: number
}

export type AssetStatus = 'in_use' | 'in_storage' | 'under_maintenance' | 'disposed'

export interface Asset {
  id: number
  company_id: number
  branch_id: number
  asset_category_id: number
  asset_code: string
  name: string
  purchase_date: string
  purchase_cost: number
  current_value: number
  status: AssetStatus
  assigned_to_employee_id: number | null
}

export interface AssetFormValues {
  branch_id: number
  asset_category_id: number
  asset_code: string
  name: string
  purchase_date: string
  purchase_cost: number
  current_value?: number
  status?: AssetStatus
}

export interface AssetMaintenanceLog {
  id: number
  asset_id: number
  maintenance_date: string
  description: string
  cost: number
  performed_by: string | null
}

export interface AssetDepreciationSchedule {
  id: number
  asset_id: number
  period_date: string
  depreciation_amount: number
  accumulated_depreciation: number
  book_value: number
  journal_entry_id: number | null
}
