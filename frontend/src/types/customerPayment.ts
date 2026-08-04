export interface CustomerPayment {
  id: number
  company_id: number
  customer_id: number
  invoice_id: number
  payment_type_id: number
  payment_date: string
  amount: number
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
  reference_number?: string
}
