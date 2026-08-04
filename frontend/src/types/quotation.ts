export type QuotationStatus = 'draft' | 'sent' | 'accepted' | 'rejected' | 'expired' | 'converted'

export interface QuotationItem {
  id: number
  quotation_id: number
  product_id: number
  product_variant_id: number | null
  quantity: number
  unit_price: number
  tax_rate_id: number | null
  discount_amount: number
  line_total: number
}

export interface Quotation {
  id: number
  company_id: number
  branch_id: number
  customer_id: number
  price_list_id: number | null
  reference_number: string
  quotation_date: string
  valid_until: string
  status: QuotationStatus
  notes: string | null
  created_by: number
  subtotal: number
  tax_amount: number
  discount_amount: number
  total_amount: number
  items: QuotationItem[]
  created_at: string
  updated_at: string
}

export interface QuotationLineInput {
  product_id: number | null
  product_variant_id?: number | null
  quantity: number
  unit_price: number | null
  tax_rate_id?: number | null
  discount_amount?: number
}

export interface QuotationFormInput {
  branch_id: number
  customer_id: number
  price_list_id?: number | null
  reference_number?: string
  quotation_date?: string
  valid_until: string
  notes?: string
  discount_amount?: number
  items: QuotationLineInput[]
}
