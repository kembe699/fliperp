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
  created_at: string
  updated_at: string
}

export interface StockLevel {
  id: number
  product_id: number
  product_name: string
  product_sku: string
  reorder_level: number
  product_variant_id: number | null
  warehouse_id: number
  warehouse_name: string
  quantity_on_hand: number
  quantity_reserved: number
  is_low_stock: boolean
  updated_at: string
}
