import type { StatusVariant } from '@/components/shared/StatusBadge'
import type { StockAdjustmentStatus, StockTransferStatus } from '@/types/inventory'
import type { GrnStatus, PurchaseOrderStatus, SupplierBillStatus } from '@/types/procurement'

export const STOCK_TRANSFER_STATUS_VARIANT: Record<StockTransferStatus, StatusVariant> = {
  pending: 'neutral',
  in_transit: 'info',
  completed: 'success',
  cancelled: 'danger',
}

export const STOCK_ADJUSTMENT_STATUS_VARIANT: Record<StockAdjustmentStatus, StatusVariant> = {
  draft: 'neutral',
  approved: 'success',
}

export const PURCHASE_ORDER_STATUS_VARIANT: Record<PurchaseOrderStatus, StatusVariant> = {
  draft: 'neutral',
  submitted: 'info',
  approved: 'success',
  partially_received: 'warning',
  received: 'success',
  cancelled: 'danger',
}

export const GRN_STATUS_VARIANT: Record<GrnStatus, StatusVariant> = {
  draft: 'neutral',
  confirmed: 'success',
}

export const SUPPLIER_BILL_STATUS_VARIANT: Record<SupplierBillStatus, StatusVariant> = {
  unpaid: 'warning',
  partially_paid: 'info',
  paid: 'success',
  overdue: 'danger',
}

export const SUPPLIER_BILL_STATUS_LABEL: Record<SupplierBillStatus, string> = {
  unpaid: 'Unpaid',
  partially_paid: 'Partially Paid',
  paid: 'Paid',
  overdue: 'Overdue',
}

export const MOVEMENT_TYPE_VARIANT: Record<string, StatusVariant> = {
  purchase: 'success',
  sale: 'danger',
  return: 'info',
  transfer_in: 'success',
  transfer_out: 'danger',
  adjustment: 'warning',
}
