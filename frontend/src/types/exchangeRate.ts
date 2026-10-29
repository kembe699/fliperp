export interface ExchangeRate {
  id: number
  company_id: number
  currency_code: string
  /** Units of currency_code per 1 unit of the company's base currency (1 USD = 7,800 SSP → 7800). */
  rate: number
  effective_date: string
  created_by: number | null
  created_at: string | null
  updated_at: string | null
}

export interface ExchangeRateLookup {
  currency_code: string
  base_currency: string
  is_base: boolean
  /** null when no rate is set on or before the date */
  rate: number | null
  /** Date of the rate row used (latest on or before the requested date). */
  effective_date: string | null
}

/** Attached to every report response; converted is true when figures are not the ledger's own base amounts. */
export interface ReportCurrencyInfo {
  code: string
  base_currency: string
  converted: boolean
  rate?: number
  rate_date?: string
}
