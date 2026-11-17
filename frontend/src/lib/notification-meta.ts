import {
  AlertTriangle,
  Ban,
  Banknote,
  Bell,
  CalendarCheck,
  CalendarClock,
  CheckCircle2,
  ClipboardCheck,
  FileWarning,
  PackageX,
  Wallet,
  XCircle,
  type LucideIcon,
} from 'lucide-react'

export const NOTIFICATION_ICONS: Record<string, LucideIcon> = {
  leave_request_submitted: CalendarClock,
  leave_request_decided: CalendarCheck,
  invoice_overdue: FileWarning,
  low_stock_alert: PackageX,
  payroll_run_processed: Banknote,
  sale_voided: Ban,
  purchase_order_approval_needed: ClipboardCheck,
  cash_drawer_variance_flagged: AlertTriangle,
  payment_request_approval_needed: ClipboardCheck,
  payment_request_approved: CheckCircle2,
  payment_request_rejected: XCircle,
  payment_request_paid: Wallet,
  payment_request_ready_to_pay: Wallet,
}

export function iconForCategory(category: string): LucideIcon {
  return NOTIFICATION_ICONS[category] ?? Bell
}

export const CATEGORY_LABELS: Record<string, string> = {
  leave_request_submitted: 'Leave Request',
  leave_request_decided: 'Leave Decision',
  invoice_overdue: 'Invoice Overdue',
  low_stock_alert: 'Low Stock',
  payroll_run_processed: 'Payroll',
  sale_voided: 'Sale Voided',
  purchase_order_approval_needed: 'Purchase Order',
  cash_drawer_variance_flagged: 'Cash Drawer',
  payment_request_approval_needed: 'Payment Request',
  payment_request_approved: 'Payment Request',
  payment_request_rejected: 'Payment Request',
  payment_request_paid: 'Payment Request',
  payment_request_ready_to_pay: 'Payment Request',
}

export function labelForCategory(category: string): string {
  return CATEGORY_LABELS[category] ?? 'Notification'
}

/**
 * These signal something needs attention or action soon (an approval, a
 * shortage, a stockout) — worth interrupting with a toast. The rest are
 * informational/confirmatory and would just be noise as a toast; the
 * unread badge is enough for those.
 */
const TOAST_CATEGORIES = new Set([
  'cash_drawer_variance_flagged',
  'leave_request_submitted',
  'purchase_order_approval_needed',
  'low_stock_alert',
  'sale_voided',
  'payment_request_approval_needed',
  'payment_request_rejected',
  'payment_request_ready_to_pay',
])

export function isToastWorthy(category: string): boolean {
  return TOAST_CATEGORIES.has(category)
}
