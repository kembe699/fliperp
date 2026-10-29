export interface CustomerPayment {
  id: number
  company_id: number
  customer_id: number
  invoice_id: number
  payment_type_id: number
  payment_date: string
  amount: number
  currency_code: string
  exchange_rate: number
  reference_number: string | null
  received_by: number
  journal_entry_id: number | null
  created_at: string
  updated_at: string
}

export interface CreateCustomerPaymentInput {
  customer_id: number
  invoice_id: number
  payment_type_id: number
  payment_date: string
  amount: number
  /** The invoice's currency is always used; only the rate can be set (defaults to the rate for payment_date). */
  exchange_rate?: number
  reference_number?: string
}
