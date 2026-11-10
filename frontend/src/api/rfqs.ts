import { api } from '@/lib/api'
import { downloadPdf } from '@/lib/pdf-download'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { PurchaseOrder } from '@/types/procurement'

export type RfqStatus = 'draft' | 'sent' | 'converted' | 'cancelled'

export interface RfqItem {
  id: number
  product_id: number
  product_variant_id: number | null
  product_name: string | null
  variant_name: string | null
  quantity: number
  description: string | null
}

export interface RfqSupplierLine {
  supplier_id: number
  name: string | null
  email: string | null
  sent_at: string | null
  sent_to: string | null
  quoted_at: string | null
  delivery_days: number | null
  quote_notes: string | null
  /** Unit price per RFQ item id. */
  prices: Record<string, number>
  quote_total: number | null
  items_priced: number
}

export interface Rfq {
  id: number
  reference_number: string
  title: string
  issue_date: string
  response_due_date: string | null
  status: RfqStatus
  notes: string | null
  branch_id: number | null
  warehouse_id: number | null
  items_count: number | null
  items?: RfqItem[]
  suppliers?: RfqSupplierLine[]
  selected_supplier_id: number | null
  selected_supplier_name?: string | null
  purchase_order_id: number | null
  purchase_order_reference?: string | null
  created_at: string
}

export interface RfqInput {
  title: string
  issue_date?: string
  response_due_date?: string | null
  warehouse_id?: number | null
  branch_id?: number | null
  notes?: string | null
  items: { product_id: number; quantity: number; description?: string | null }[]
  supplier_ids: number[]
}

export async function fetchRfqs(): Promise<PaginatedResponse<Rfq>> {
  const { data } = await api.get<PaginatedResponse<Rfq>>('/rfqs', { params: { per_page: 500 } })
  return data
}

export async function fetchRfq(id: number): Promise<Rfq> {
  const { data } = await api.get<ApiResponse<Rfq>>(`/rfqs/${id}`)
  return data.data
}

export async function createRfq(values: RfqInput): Promise<Rfq> {
  const { data } = await api.post<ApiResponse<Rfq>>('/rfqs', values)
  return data.data
}

export async function updateRfq(id: number, values: RfqInput): Promise<Rfq> {
  const { data } = await api.put<ApiResponse<Rfq>>(`/rfqs/${id}`, values)
  return data.data
}

export async function deleteRfq(id: number): Promise<void> {
  await api.delete(`/rfqs/${id}`)
}

/** Emails the RFQ to these suppliers on it, or to all of them when none are given. */
export async function sendRfq(id: number, supplierIds?: number[]): Promise<{ sent: string[]; skipped: string[]; message: string }> {
  const { data } = await api.post<ApiResponse<{ sent: string[]; skipped: string[] }>>(`/rfqs/${id}/send`, supplierIds ? { supplier_ids: supplierIds } : {})
  return { ...data.data, message: data.message }
}

export async function recordRfqQuote(
  id: number,
  values: { supplier_id: number; prices: { rfq_item_id: number; unit_price: number | null }[]; delivery_days?: number | null; notes?: string | null },
): Promise<Rfq> {
  const { data } = await api.post<ApiResponse<Rfq>>(`/rfqs/${id}/quote`, values)
  return data.data
}

export async function convertRfq(
  id: number,
  values: { supplier_id: number; warehouse_id?: number | null; order_date?: string; expected_delivery_date?: string | null },
): Promise<PurchaseOrder> {
  const { data } = await api.post<ApiResponse<PurchaseOrder>>(`/rfqs/${id}/convert`, values)
  return data.data
}

export async function cancelRfq(id: number): Promise<Rfq> {
  const { data } = await api.post<ApiResponse<Rfq>>(`/rfqs/${id}/cancel`)
  return data.data
}

export function downloadRfqPdf(rfq: Rfq): Promise<void> {
  return downloadPdf(`/rfqs/${rfq.id}/pdf`, `rfq-${rfq.reference_number}.pdf`)
}
