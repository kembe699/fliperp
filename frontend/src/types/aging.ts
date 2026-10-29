export type AgingBucket = 'd0_30' | 'd31_60' | 'd61_90' | 'd90_plus'

export interface AgingBuckets {
  d0_30: number
  d31_60: number
  d61_90: number
  d90_plus: number
}

export interface AgingDocument {
  id: number
  reference_number: string
  party_id: number
  party_name: string
  date: string
  due_date: string
  currency_code: string | null
  exchange_rate: number
  /** In the document's own currency. */
  total: number
  paid: number
  balance: number
  /** Base currency, at the document's own rate. */
  balance_base: number
  days_overdue: number
  bucket: AgingBucket
  not_yet_due: boolean
}

export interface AgingParty extends AgingBuckets {
  name: string
  documents: number
  outstanding: number
  overdue: number
  oldest_days_overdue: number
}

interface AgingReportBase {
  as_of: string
  /** All in the base currency. */
  totals: AgingBuckets & { outstanding: number; overdue: number; not_yet_due: number }
  control_account: { code: string; name: string | null; balance: number }
  /** Control account balance minus outstanding documents; should be 0. */
  difference: number
  documents: AgingDocument[]
}

export interface ReceivablesReport extends AgingReportBase {
  customers: (AgingParty & { customer_id: number })[]
}

export interface PayablesReport extends AgingReportBase {
  suppliers: (AgingParty & { supplier_id: number })[]
}
