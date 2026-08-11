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
  items: InvoiceLineInput[]
}
