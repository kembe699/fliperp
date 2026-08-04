import type { StatusVariant } from '@/components/shared/StatusBadge'
import type { InvoiceStatus } from '@/types/invoice'
import type { QuotationStatus } from '@/types/quotation'

export const INVOICE_STATUS_VARIANT: Record<InvoiceStatus, StatusVariant> = {
  draft: 'neutral',
  sent: 'info',
  partially_paid: 'warning',
  paid: 'success',
  overdue: 'danger',
  cancelled: 'neutral',
}

export const INVOICE_STATUS_LABEL: Record<InvoiceStatus, string> = {
  draft: 'Draft',
  sent: 'Sent',
  partially_paid: 'Partially Paid',
  paid: 'Paid',
  overdue: 'Overdue',
  cancelled: 'Cancelled',
}

export const QUOTATION_STATUS_VARIANT: Record<QuotationStatus, StatusVariant> = {
  draft: 'neutral',
  sent: 'info',
  accepted: 'success',
  rejected: 'danger',
  expired: 'warning',
  converted: 'info',
}

export const QUOTATION_STATUS_LABEL: Record<QuotationStatus, string> = {
  draft: 'Draft',
  sent: 'Sent',
  accepted: 'Accepted',
  rejected: 'Rejected',
  expired: 'Expired',
  converted: 'Converted',
}
