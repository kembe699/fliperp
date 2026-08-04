export type SaleType = 'pos' | 'dine_in' | 'takeaway' | 'delivery'
export type SaleStatus = 'held' | 'completed' | 'voided' | 'refunded'

export interface SaleItem {
  id: number
  sale_id: number
  product_id: number
  product_variant_id: number | null
  quantity: number
  unit_price: number
  tax_rate_id: number | null
  discount_amount: number
  line_total: number
}

export interface SalePayment {
  id: number
  sale_id: number
  payment_type_id: number
  amount: number
  reference_number: string | null
  paid_at: string
}

export interface Sale {
  id: number
  company_id: number
  branch_id: number
  warehouse_id: number
  customer_id: number | null
  cash_drawer_session_id: number | null
  table_id: number | null
  reference_number: string
  sale_type: SaleType
  status: SaleStatus
  subtotal: number
  tax_amount: number
  discount_amount: number
  total_amount: number
  amount_paid: number
  balance_due: number
  served_by: number
  sale_date: string
  journal_entry_id: number | null
  items: SaleItem[]
  payments: SalePayment[]
  created_at: string
  updated_at: string
}

export interface CreateSaleItemInput {
  product_id: number
  product_variant_id?: number | null
  quantity: number
  unit_price: number
  tax_rate_id?: number | null
  discount_amount?: number
}

export interface CreateSaleInput {
  branch_id?: number
  warehouse_id: number
  customer_id?: number | null
  table_id?: number | null
  reference_number?: string
  sale_type?: SaleType
  discount_amount?: number
  sale_date?: string
  items: CreateSaleItemInput[]
}
