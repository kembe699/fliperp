import type { PaymentRequestStatus } from '@/api/paymentRequests'
import type { StatusVariant } from '@/components/shared/StatusBadge'

export const PAYMENT_REQUEST_STATUS_VARIANT: Record<PaymentRequestStatus, StatusVariant> = {
  draft: 'neutral',
  submitted: 'info',
  partially_approved: 'info',
  approved: 'warning',
  paid: 'success',
  rejected: 'danger',
  cancelled: 'neutral',
}
