import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { CreateSaleInput, Sale, SalePayment, SaleStatus } from '@/types/sale'

export interface SaleFilters {
  page?: number
  per_page?: number
  status?: SaleStatus
  branch_id?: number
  served_by?: number
  payment_type_id?: number
  search?: string
  from?: string
  to?: string
}

export async function fetchSales(filters: SaleFilters): Promise<PaginatedResponse<Sale>> {
  const { data } = await api.get<PaginatedResponse<Sale>>('/sales', { params: filters })
  return data
}

export async function createSale(input: CreateSaleInput): Promise<Sale> {
  const { data } = await api.post<ApiResponse<Sale>>('/sales', input)
  return data.data
}

export async function updateSale(id: number, input: Partial<CreateSaleInput>): Promise<Sale> {
  const { data } = await api.put<ApiResponse<Sale>>(`/sales/${id}`, input)
  return data.data
}

export async function fetchHeldSales(): Promise<Sale[]> {
  const { data } = await api.get<ApiResponse<Sale[]>>('/sales/held')
  return data.data
}

export async function fetchSale(id: number): Promise<Sale> {
  const { data } = await api.get<ApiResponse<Sale>>(`/sales/${id}`)
  return data.data
}

export async function addSalePayment(
  saleId: number,
  input: { payment_type_id: number; amount: number; reference_number?: string },
): Promise<SalePayment> {
  const { data } = await api.post<ApiResponse<SalePayment>>(`/sales/${saleId}/payments`, input)
  return data.data
}

export async function completeSale(saleId: number): Promise<Sale> {
  const { data } = await api.post<ApiResponse<Sale>>(`/sales/${saleId}/complete`)
  return data.data
}

export async function voidSale(saleId: number): Promise<Sale> {
  const { data } = await api.post<ApiResponse<Sale>>(`/sales/${saleId}/void`)
  return data.data
}

export async function refundSale(saleId: number): Promise<Sale> {
  const { data } = await api.post<ApiResponse<Sale>>(`/sales/${saleId}/refund`)
  return data.data
}

export async function deleteSale(saleId: number): Promise<void> {
  await api.delete(`/sales/${saleId}`)
}

export interface PublicSaleVerification {
  reference_number: string
  sale_date: string
  total_amount: number
  currency_code: string
  status: string
  company_name: string | null
}

/** Unauthenticated — backs the receipt QR code, scanned with no login. */
export async function verifySalePublic(saleId: number): Promise<PublicSaleVerification> {
  const { data } = await api.get<ApiResponse<PublicSaleVerification>>(`/public/sales/${saleId}/verify`)
  return data.data
}
