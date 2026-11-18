import type { ReactNode } from 'react'
import { useQuery } from '@tanstack/react-query'

import { fetchAccountingPeriods } from '@/api/accounting'
import { formatDate } from '@/lib/format'
import { usePermissions } from '@/hooks/use-permissions'
import { cn } from '@/lib/utils'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const CUSTOM = '__custom'

type Props = {
  className?: string
  /** Wraps the control with the page's own label style; defaults to a Label above it. */
  field?: (label: string, control: ReactNode) => ReactNode
} & (
  | { mode?: 'range'; from: string; to: string; onChange: (from: string, to: string) => void }
  | { mode: 'asOf'; asOf: string; onChange: (asOf: string) => void }
)

/**
 * Picks an accounting period (Settings › Accounting Periods) and fills the
 * report's dates with it: From/To for a period report, "As of" its last day
 * for the balance sheet. Shows "Custom dates" when the dates match no period,
 * and nothing at all when the company has no periods.
 */
export function AccountingPeriodSelect(props: Props) {
  const { can } = usePermissions()
  const allowed = can('accounting-periods.view') || can('reports.view')
  const { data } = useQuery({
    queryKey: ['accounting-periods', 'picker'],
    queryFn: () => fetchAccountingPeriods({ per_page: 100 }),
    enabled: allowed,
    staleTime: 5 * 60_000,
  })
  const periods = data?.data ?? []
  if (!allowed || periods.length === 0) return null

  const current =
    props.mode === 'asOf'
      ? periods.find((p) => p.end_date === props.asOf)
      : periods.find((p) => p.start_date === props.from && p.end_date === props.to)

  const pick = (id: string) => {
    const period = periods.find((p) => String(p.id) === id)
    if (!period) return
    if (props.mode === 'asOf') props.onChange(period.end_date)
    else props.onChange(period.start_date, period.end_date)
  }

  const control = (
    <Select value={current ? String(current.id) : CUSTOM} onValueChange={pick}>
      <SelectTrigger className={cn('w-[190px]', props.className)} aria-label="Accounting period">
        <SelectValue>{current?.name ?? 'Custom dates'}</SelectValue>
      </SelectTrigger>
      <SelectContent>
        <SelectItem value={CUSTOM} disabled>
          Custom dates
        </SelectItem>
        {periods.map((p) => (
          <SelectItem key={p.id} value={String(p.id)}>
            <span>{p.name}</span>
            <span className="ml-2 text-xs text-muted-foreground">
              {formatDate(p.start_date)} – {formatDate(p.end_date)}
              {p.status === 'closed' ? ' · closed' : ''}
            </span>
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )

  if (props.field) return props.field('Accounting period', control)
  return (
    <div className="space-y-1.5">
      <Label>Accounting period</Label>
      {control}
    </div>
  )
}
