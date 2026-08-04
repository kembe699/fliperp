import { cn } from '@/lib/utils'

export type StatusVariant = 'success' | 'warning' | 'danger' | 'info' | 'neutral'

const dotColor: Record<StatusVariant, string> = {
  success: 'bg-success',
  warning: 'bg-warning',
  danger: 'bg-danger',
  info: 'bg-info',
  neutral: 'bg-muted-foreground',
}

const badgeColor: Record<StatusVariant, string> = {
  success: 'bg-success-bg text-success',
  warning: 'bg-warning-bg text-warning',
  danger: 'bg-danger-bg text-danger',
  info: 'bg-info-bg text-info',
  neutral: 'bg-muted text-muted-foreground',
}

interface StatusBadgeProps {
  label: string
  variant?: StatusVariant
  className?: string
}

export function StatusBadge({ label, variant = 'neutral', className }: StatusBadgeProps) {
  return (
    <span className={cn('inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold', badgeColor[variant], className)}>
      <span className={cn('h-1.5 w-1.5 rounded-full', dotColor[variant])} />
      {label}
    </span>
  )
}
