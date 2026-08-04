export type PaymentTypeKind = 'cash' | 'card' | 'mobile_money' | 'bank_transfer' | 'credit'

export interface PaymentType {
  id: number
  company_id: number
  name: string
  type: PaymentTypeKind
  is_active: boolean
}

export interface TaxRate {
  id: number
  company_id: number
  name: string
  rate: number
  is_default: boolean
  is_active: boolean
}

export interface PriceList {
  id: number
  company_id: number
  name: string
  currency_code: string
  is_default: boolean
  valid_from: string | null
  valid_to: string | null
  is_active: boolean
}

export interface PriceListItem {
  id: number
  price_list_id: number
  product_id: number
  product_variant_id: number | null
  price: number
}

export interface Warehouse {
  id: number
  company_id: number
  branch_id: number
  name: string
  code: string
  is_default: boolean
}

export interface CashDrawerSession {
  id: number
  company_id: number
  branch_id: number
  user_id: number
  opening_float: number
  closing_float: number | null
  expected_closing: number | null
  variance: number | null
  journal_entry_id: number | null
  recovered_amount: number
  outstanding_shortage: number
  opened_at: string
  closed_at: string | null
  status: 'open' | 'closed'
}

export interface CashDrawerVarianceRecovery {
  id: number
  company_id: number
  cash_drawer_session_id: number
  amount: number
  notes: string | null
  received_by: number
  journal_entry_id: number | null
  created_at: string
}
