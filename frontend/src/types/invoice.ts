export type InvoiceStatus = 'draft' | 'sent' | 'partially_paid' | 'paid' | 'overdue' | 'cancelled'

export interface InvoiceItem {
  id: number
  invoice_id: number
  product_id: number
  product_variant_id: number | null
  description: string | null
  quantity: number
  unit_price: number
  tax_rate_id: number | null
  discount_amount: number
  line_total: number
  grant_id?: number | null
  grant_code?: string | null
  dea_id?: number | null
  dea_code?: string | null
  project_id?: number | null
  project_code?: string | null
  cost_centre_id?: number | null
  cost_centre_code?: string | null
}

export interface Invoice {
  id: number
  company_id: number
  branch_id: number
  customer_id: number
  quotation_id: number | null
  reference_number: string
  invoice_date: string
  due_date: string
  status: InvoiceStatus
  subtotal: number
  tax_amount: number
  discount_amount: number
  total_amount: number
  amount_paid: number
  currency_code: string
  /** Units of currency_code per 1 base unit. */
  exchange_rate: number
  balance_due: number
  journal_entry_id: number | null
  created_by: number
  items: InvoiceItem[]
  created_at: string
  updated_at: string
}

export interface InvoiceLineInput {
  product_id: number | null
  product_variant_id?: number | null
  description?: string | null
  quantity: number
  unit_price: number | null
  tax_rate_id?: number | null
  discount_amount?: number
  grant_id?: number | null
  dea_id?: number | null
  project_id?: number | null
  cost_centre_id?: number | null
}

export interface InvoiceFormInput {
  branch_id: number
  customer_id: number
  quotation_id?: number | null
  price_list_id?: number | null
  reference_number?: string
  invoice_date?: string
  due_date: string
  discount_amount?: number
  currency_code?: string
  exchange_rate?: number
  items: InvoiceLineInput[]
}
