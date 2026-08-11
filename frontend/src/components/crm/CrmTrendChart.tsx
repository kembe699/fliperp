import type { LucideIcon } from 'lucide-react'
import { Area, AreaChart, CartesianGrid, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'

import type { CrmTrendPoint } from '@/types/crm'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

function formatMonthLabel(period: string): string {
  const [year, month] = period.split('-').map(Number)
  return new Date(year, month - 1, 1).toLocaleDateString(undefined, { month: 'short' })
}

interface CrmTrendChartProps {
  title: string
  icon: LucideIcon
  data: CrmTrendPoint[]
  dataKey: keyof Pick<CrmTrendPoint, 'new_customers' | 'new_leads' | 'deals_won'>
  color: string
  isLoading?: boolean
  emptyText?: string
}

export function CrmTrendChart({ title, icon: Icon, data, dataKey, color, isLoading, emptyText }: CrmTrendChartProps) {
  const total = data.reduce((sum, point) => sum + point[dataKey], 0)
  const hasData = data.some((point) => point[dataKey] > 0)
  const chartData = data.map((point) => ({ ...point, label: formatMonthLabel(point.period) }))
  const gradientId = `crm-trend-${dataKey}`

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <Icon className="h-4 w-4" />
          {title}
        </CardTitle>
      </CardHeader>
      <CardContent>
        {isLoading ? (
          <div className="flex h-52 items-center justify-center text-sm text-muted-foreground">Loading…</div>
        ) : !hasData ? (
          <div className="flex h-52 flex-col items-center justify-center gap-1 text-sm text-muted-foreground">
            <p>{emptyText ?? 'No data in this period yet.'}</p>
          </div>
        ) : (
          <>
            <p className="mb-3 text-2xl font-bold text-foreground">{total}</p>
            <ResponsiveContainer width="100%" height={180}>
              <AreaChart data={chartData} margin={{ top: 5, right: 8, left: -20, bottom: 0 }}>
                <defs>
                  <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                    <stop offset="5%" stopColor={color} stopOpacity={0.35} />
                    <stop offset="95%" stopColor={color} stopOpacity={0} />
                  </linearGradient>
                </defs>
                <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="hsl(var(--border))" />
                <XAxis dataKey="label" tick={{ fontSize: 12, fill: 'hsl(var(--muted-foreground))' }} axisLine={false} tickLine={false} />
                <YAxis allowDecimals={false} tick={{ fontSize: 12, fill: 'hsl(var(--muted-foreground))' }} axisLine={false} tickLine={false} width={28} />
                <Tooltip
                  contentStyle={{ borderRadius: 8, border: '1px solid hsl(var(--border))', fontSize: 12 }}
                  labelStyle={{ fontWeight: 600, color: 'hsl(var(--foreground))' }}
                />
                <Area type="monotone" dataKey={dataKey} name={title} stroke={color} strokeWidth={2} fill={`url(#${gradientId})`} />
              </AreaChart>
            </ResponsiveContainer>
          </>
        )}
      </CardContent>
    </Card>
  )
}
