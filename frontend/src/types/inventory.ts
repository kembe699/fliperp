export interface Category {
  id: number
  company_id: number
  name: string
  parent_id: number | null
  image_url: string | null
  is_active: boolean
  sort_order: number
}

export interface UnitOfMeasure {
  id: number
  company_id: number
  name: string
  abbreviation: string
}

export interface ProductVariant {
  id: number
  product_id: number
  name: string
  sku: string
  barcode: string | null
  price_adjustment: number
}

export interface Product {
  id: number
  company_id: number
  category_id: number
  name: string
  sku: string
  barcode: string | null
  description: string | null
  unit_of_measure_id: number
  cost_price: number
  selling_price: number
  tax_rate_id: number | null
  reorder_level: number
  is_active: boolean
  image_url: string | null
  track_inventory: boolean
  variants?: ProductVariant[]
  created_at: string
  updated_at: string
}

export interface ProductFormValues {
  category_id: number
  name: string
  sku: string
  barcode?: string | null
  description?: string | null
  unit_of_measure_id: number
  cost_price: number
  selling_price: number
  tax_rate_id?: number | null
  reorder_level?: number
  is_active?: boolean
  track_inventory?: boolean
  image_url?: string | null
}

export interface StockMovement {
  id: number
  company_id: number
  product_id: number
  product_variant_id: number | null
  warehouse_id: number
  warehouse_name: string
  movement_type: string
  quantity: number
  reference_type: string | null
  reference_id: number | null
  reason: string | null
  performed_by: number
  moved_at: string
}

export interface Warehouse {
  id: number
  company_id: number
  branch_id: number
  name: string
  code: string
  is_default: boolean
}

export type StockTransferStatus = 'pending' | 'in_transit' | 'completed' | 'cancelled'

export interface StockTransferItem {
  id: number
  product_id: number
  product_variant_id: number | null
  quantity: number
}

export interface StockTransfer {
  id: number
  company_id: number
  from_warehouse_id: number
  to_warehouse_id: number
  reference_number: string
  status: StockTransferStatus
  initiated_by: number
  items: StockTransferItem[]
  created_at: string
  updated_at: string
}

export type StockAdjustmentStatus = 'draft' | 'approved'

export interface StockAdjustmentItem {
  id: number
  product_id: number
  product_variant_id: number | null
  system_quantity: number
  counted_quantity: number
  variance: number
}

export interface StockAdjustment {
  id: number
  company_id: number
  warehouse_id: number
  reference_number: string
  reason: string | null
  adjusted_by: number
  status: StockAdjustmentStatus
  approved_by: number | null
  items: StockAdjustmentItem[]
  created_at: string
  updated_at: string
}
