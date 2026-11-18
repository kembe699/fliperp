import { cn } from '@/lib/utils'

export type StatusVariant = 'success' | 'warning' | 'danger' | 'info' | 'neutral'

/** Statuses and types are shown as coloured text only — no background pill. */
const textColor: Record<StatusVariant, string> = {
  success: 'text-[hsl(142,72%,29%)]', // darker than --success so small text stays readable on white
  warning: 'text-warning',
  danger: 'text-danger',
  info: 'text-info',
  neutral: 'text-muted-foreground',
}

interface StatusBadgeProps {
  label: string
  variant?: StatusVariant
  className?: string
}

export function StatusBadge({ label, variant = 'neutral', className }: StatusBadgeProps) {
  return <span className={cn('inline-flex items-center whitespace-nowrap text-sm font-medium', textColor[variant], className)}>{label}</span>
}
