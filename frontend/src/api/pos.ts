import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { CashDrawerSession, PaymentType, PriceList, PriceListItem, TaxRate, Warehouse } from '@/types/pos'

export async function fetchPaymentTypes(): Promise<PaymentType[]> {
  const { data } = await api.get<PaginatedResponse<PaymentType>>('/payment-types', { params: { per_page: 50 } })
  return data.data
}

export async function fetchTaxRates(): Promise<TaxRate[]> {
  const { data } = await api.get<PaginatedResponse<TaxRate>>('/tax-rates', { params: { per_page: 50 } })
  return data.data
}

export async function fetchPriceLists(): Promise<PriceList[]> {
  const { data } = await api.get<PaginatedResponse<PriceList>>('/price-lists', { params: { per_page: 50 } })
  return data.data
}

export async function fetchPriceListItems(priceListId: number): Promise<PriceListItem[]> {
  const { data } = await api.get<ApiResponse<PriceListItem[]>>(`/price-lists/${priceListId}/items`)
  return data.data
}

export async function fetchWarehouses(): Promise<Warehouse[]> {
  const { data } = await api.get<PaginatedResponse<Warehouse>>('/warehouses', { params: { per_page: 50 } })
  return data.data
}

export async function fetchCurrentCashDrawerSession(): Promise<CashDrawerSession | null> {
  const { data } = await api.get<ApiResponse<CashDrawerSession | null>>('/cash-drawer/current')
  return data.data
}

export async function openCashDrawer(input: { branch_id?: number; opening_float: number }): Promise<CashDrawerSession> {
  const { data } = await api.post<ApiResponse<CashDrawerSession>>('/cash-drawer/open', input)
  return data.data
}

export async function closeCashDrawer(input: { closing_float: number }): Promise<CashDrawerSession> {
  const { data } = await api.post<ApiResponse<CashDrawerSession>>('/cash-drawer/close', input)
  return data.data
}
