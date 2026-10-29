import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { ExchangeRate, ExchangeRateLookup } from '@/types/exchangeRate'

export async function fetchExchangeRates(params: { currency_code?: string; page?: number; per_page?: number } = {}): Promise<PaginatedResponse<ExchangeRate>> {
  const { data } = await api.get<PaginatedResponse<ExchangeRate>>('/exchange-rates', { params })
  return data
}

/** Currencies the company has at least one rate for (the base currency is not included). */
export async function fetchRatedCurrencies(): Promise<string[]> {
  const { data } = await api.get<ApiResponse<string[]>>('/exchange-rates/currencies')
  return data.data
}

export async function lookupExchangeRate(currencyCode: string, date: string): Promise<ExchangeRateLookup> {
  const { data } = await api.get<ApiResponse<ExchangeRateLookup>>('/exchange-rates/lookup', {
    params: { currency_code: currencyCode, date },
  })
  return data.data
}

export interface ExchangeRateInput {
  currency_code: string
  rate: number
  effective_date: string
}

export async function createExchangeRate(values: ExchangeRateInput): Promise<ExchangeRate> {
  const { data } = await api.post<ApiResponse<ExchangeRate>>('/exchange-rates', values)
  return data.data
}

export async function updateExchangeRate(id: number, values: Partial<ExchangeRateInput>): Promise<ExchangeRate> {
  const { data } = await api.put<ApiResponse<ExchangeRate>>(`/exchange-rates/${id}`, values)
  return data.data
}

export async function deleteExchangeRate(id: number): Promise<void> {
  await api.delete(`/exchange-rates/${id}`)
}
