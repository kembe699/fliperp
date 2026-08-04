export interface Supplier {
  id: number
  company_id: number
  name: string
  contact_person: string | null
  phone: string | null
  email: string | null
  address: string | null
  tax_id: string | null
  payment_terms_days: number
  is_active: boolean
  created_at: string
  updated_at: string
}

export interface SupplierStatementTransaction {
  type: 'bill' | 'payment'
  date: string
  reference_number: string | null
  debit: number
  credit: number
  running_balance: number
}

export interface SupplierStatement {
  supplier_id: number
  supplier_name: string
  closing_balance: number
  transactions: SupplierStatementTransaction[]
}

export type PurchaseOrderStatus = 'draft' | 'submitted' | 'approved' | 'partially_received' | 'received' | 'cancelled'

export interface PurchaseOrderItem {
  id: number
  product_id: number
  product_variant_id: number | null
  quantity_ordered: number
  unit_cost: number
  quantity_received: number
}

export interface PurchaseOrder {
  id: number
  company_id: number
  branch_id: number
  warehouse_id: number
  supplier_id: number
  reference_number: string
  order_date: string
  expected_delivery_date: string | null
  status: PurchaseOrderStatus
  notes: string | null
  created_by: number
  approved_by: number | null
  items: PurchaseOrderItem[]
  created_at: string
  updated_at: string
}

export interface ReceivingStatusItem {
  purchase_order_item_id: number
  product_id: number
  product_variant_id: number | null
  quantity_ordered: number
  quantity_received: number
  quantity_outstanding: number
  fully_received: boolean
}

export interface ReceivingStatus {
  purchase_order_id: number
  status: PurchaseOrderStatus
  items: ReceivingStatusItem[]
}

export type GrnStatus = 'draft' | 'confirmed'
export type GrnItemCondition = 'good' | 'damaged' | 'rejected'

export interface GrnItem {
  id: number
  product_id: number
  product_variant_id: number | null
  purchase_order_item_id: number | null
  quantity_received: number
  unit_cost: number
  condition: GrnItemCondition
}

export interface GoodsReceivedNote {
  id: number
  company_id: number
  purchase_order_id: number | null
  warehouse_id: number
  supplier_id: number
  reference_number: string
  received_date: string
  status: GrnStatus
  received_by: number
  notes: string | null
  items: GrnItem[]
  created_at: string
  updated_at: string
}

export type SupplierBillStatus = 'unpaid' | 'partially_paid' | 'paid' | 'overdue'

export interface SupplierBill {
  id: number
  company_id: number
  supplier_id: number
  grn_id: number | null
  purchase_order_id: number | null
  reference_number: string
  bill_date: string
  due_date: string
  subtotal: number
  tax_amount: number
  total_amount: number
  amount_paid: number
  balance_due: number
  status: SupplierBillStatus
  journal_entry_id: number | null
}
