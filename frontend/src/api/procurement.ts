import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type {
  GoodsReceivedNote,
  GrnItemCondition,
  PurchaseOrder,
  PurchaseOrderStatus,
  ReceivingStatus,
  Supplier,
  SupplierBill,
  SupplierBillStatus,
  SupplierPayment,
  SupplierStatement,
} from '@/types/procurement'

// Suppliers
export async function fetchSuppliers(params: { per_page?: number; page?: number } = {}): Promise<PaginatedResponse<Supplier>> {
  const { data } = await api.get<PaginatedResponse<Supplier>>('/suppliers', { params })
  return data
}

export interface SupplierFormValues {
  name: string
  contact_person?: string | null
  phone?: string | null
  email?: string | null
  address?: string | null
  tax_id?: string | null
  payment_terms_days?: number
  is_active?: boolean
}

export async function createSupplier(values: SupplierFormValues): Promise<Supplier> {
  const { data } = await api.post<ApiResponse<Supplier>>('/suppliers', values)
  return data.data
}

export async function updateSupplier(id: number, values: SupplierFormValues): Promise<Supplier> {
  const { data } = await api.put<ApiResponse<Supplier>>(`/suppliers/${id}`, values)
  return data.data
}

export async function fetchSupplierStatement(id: number): Promise<SupplierStatement> {
  const { data } = await api.get<ApiResponse<SupplierStatement>>(`/suppliers/${id}/statement`)
  return data.data
}

// Purchase Orders
export interface PurchaseOrderFilters {
  page?: number
  per_page?: number
  status?: PurchaseOrderStatus
  supplier_id?: number
  branch_id?: number
}

export async function fetchPurchaseOrders(filters: PurchaseOrderFilters): Promise<PaginatedResponse<PurchaseOrder>> {
  const { data } = await api.get<PaginatedResponse<PurchaseOrder>>('/purchase-orders', { params: filters })
  return data
}

export async function fetchPurchaseOrder(id: number): Promise<PurchaseOrder> {
  const { data } = await api.get<ApiResponse<PurchaseOrder>>(`/purchase-orders/${id}`)
  return data.data
}

export interface PurchaseOrderInput {
  branch_id: number
  warehouse_id: number
  supplier_id: number
  reference_number: string
  order_date: string
  expected_delivery_date?: string | null
  notes?: string | null
  items: { product_id: number; product_variant_id?: number | null; quantity_ordered: number; unit_cost: number }[]
}

export async function createPurchaseOrder(values: PurchaseOrderInput): Promise<PurchaseOrder> {
  const { data } = await api.post<ApiResponse<PurchaseOrder>>('/purchase-orders', values)
  return data.data
}

export async function updatePurchaseOrder(id: number, values: Partial<PurchaseOrderInput>): Promise<PurchaseOrder> {
  const { data } = await api.put<ApiResponse<PurchaseOrder>>(`/purchase-orders/${id}`, values)
  return data.data
}

export async function submitPurchaseOrder(id: number): Promise<PurchaseOrder> {
  const { data } = await api.post<ApiResponse<PurchaseOrder>>(`/purchase-orders/${id}/submit`)
  return data.data
}

export async function approvePurchaseOrder(id: number): Promise<PurchaseOrder> {
  const { data } = await api.post<ApiResponse<PurchaseOrder>>(`/purchase-orders/${id}/approve`)
  return data.data
}

export async function cancelPurchaseOrder(id: number): Promise<PurchaseOrder> {
  const { data } = await api.post<ApiResponse<PurchaseOrder>>(`/purchase-orders/${id}/cancel`)
  return data.data
}

export async function fetchReceivingStatus(id: number): Promise<ReceivingStatus> {
  const { data } = await api.get<ApiResponse<ReceivingStatus>>(`/purchase-orders/${id}/receiving-status`)
  return data.data
}

// Goods Received Notes
export async function fetchGoodsReceivedNotes(params: { per_page?: number; page?: number } = {}): Promise<PaginatedResponse<GoodsReceivedNote>> {
  const { data } = await api.get<PaginatedResponse<GoodsReceivedNote>>('/goods-received-notes', { params })
  return data
}

export async function fetchGoodsReceivedNote(id: number): Promise<GoodsReceivedNote> {
  const { data } = await api.get<ApiResponse<GoodsReceivedNote>>(`/goods-received-notes/${id}`)
  return data.data
}

export interface GrnInput {
  purchase_order_id?: number | null
  warehouse_id: number
  supplier_id: number
  reference_number: string
  received_date: string
  notes?: string | null
  items: {
    product_id: number
    product_variant_id?: number | null
    purchase_order_item_id?: number | null
    quantity_received: number
    unit_cost: number
    condition: GrnItemCondition
  }[]
}

export async function createGoodsReceivedNote(values: GrnInput): Promise<GoodsReceivedNote> {
  const { data } = await api.post<ApiResponse<GoodsReceivedNote>>('/goods-received-notes', values)
  return data.data
}

export async function confirmGoodsReceivedNote(id: number): Promise<GoodsReceivedNote> {
  const { data } = await api.post<ApiResponse<GoodsReceivedNote>>(`/goods-received-notes/${id}/confirm`)
  return data.data
}

// Supplier bills (used only to find the bill auto-created by a GRN confirm)
export async function findSupplierBillByGrnId(grnId: number): Promise<SupplierBill | null> {
  const { data } = await api.get<PaginatedResponse<SupplierBill>>('/supplier-bills', { params: { per_page: 100 } })
  return data.data.find((bill) => bill.grn_id === grnId) ?? null
}

export interface SupplierBillFilters {
  page?: number
  per_page?: number
  status?: SupplierBillStatus
  supplier_id?: number
  from?: string
  to?: string
  search?: string
}

export async function fetchSupplierBills(filters: SupplierBillFilters = {}): Promise<PaginatedResponse<SupplierBill>> {
  const { data } = await api.get<PaginatedResponse<SupplierBill>>('/supplier-bills', { params: filters })
  return data
}

export async function fetchSupplierBill(id: number): Promise<SupplierBill> {
  const { data } = await api.get<ApiResponse<SupplierBill>>(`/supplier-bills/${id}`)
  return data.data
}

export interface SupplierBillInput {
  supplier_id: number
  purchase_order_id?: number | null
  reference_number: string
  bill_date: string
  due_date: string
  subtotal: number
  tax_amount?: number
}

export async function createSupplierBill(values: SupplierBillInput): Promise<SupplierBill> {
  const { data } = await api.post<ApiResponse<SupplierBill>>('/supplier-bills', values)
  return data.data
}

// Supplier payments
export async function fetchSupplierPayments(filters: { supplier_bill_id?: number; supplier_id?: number; per_page?: number } = {}): Promise<PaginatedResponse<SupplierPayment>> {
  const { data } = await api.get<PaginatedResponse<SupplierPayment>>('/supplier-payments', { params: filters })
  return data
}

export interface SupplierPaymentInput {
  supplier_id: number
  supplier_bill_id: number
  payment_date: string
  amount: number
  payment_type_id?: number | null
  reference_number?: string | null
}

export async function createSupplierPayment(values: SupplierPaymentInput): Promise<SupplierPayment> {
  const { data } = await api.post<ApiResponse<SupplierPayment>>('/supplier-payments', values)
  return data.data
}
