export type CustomerType = 'walk_in' | 'regular' | 'credit'

export interface Customer {
  id: number
  company_id: number
  branch_id: number | null
  name: string
  phone: string
  email: string | null
  address: string | null
  tax_id: string | null
  customer_type: CustomerType
  credit_limit: number
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface CustomerStatementTransaction {
  type: 'sale' | 'sale_payment' | 'invoice' | 'invoice_payment'
  date: string
  reference_number: string | null
  debit: number
  credit: number
  running_balance: number
}

export interface CustomerStatement {
  customer_id: number
  customer_name: string
  closing_balance: number
  transactions: CustomerStatementTransaction[]
}
