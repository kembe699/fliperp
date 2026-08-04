import {
  AlertTriangle,
  Ban,
  Banknote,
  Bell,
  CalendarCheck,
  CalendarClock,
  ClipboardCheck,
  FileWarning,
  PackageX,
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
])

export function isToastWorthy(category: string): boolean {
  return TOAST_CATEGORIES.has(category)
}
