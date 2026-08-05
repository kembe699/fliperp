import type { LucideIcon } from 'lucide-react'
import type { ReactNode } from 'react'

import { cn } from '@/lib/utils'

interface StatCardProps {
  label: string
  value: ReactNode
  icon?: LucideIcon
  subtext?: ReactNode
  className?: string
}

/**
 * A generic stat tile for dashboards/report pages — mirrors the pattern
 * already established by the Employee Portal's PortalStatCard, made
 * available here for the main admin app (which didn't have a shared
 * version of this before now).
 */
export function StatCard({ label, value, icon: Icon, subtext, className }: StatCardProps) {
  return (
    <div className={cn('rounded-xl border border-border bg-card p-4', className)}>
      <div className="flex items-center gap-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">
        {Icon && <Icon className="h-3.5 w-3.5" />}
        {label}
      </div>
      <p className="mt-1.5 text-2xl font-semibold text-foreground">{value}</p>
      {subtext && <div className="mt-1 text-xs text-muted-foreground">{subtext}</div>}
    </div>
  )
}
