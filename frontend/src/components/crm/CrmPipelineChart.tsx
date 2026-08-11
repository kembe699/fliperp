import { Workflow } from 'lucide-react'
import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'

import type { CrmDealByStage } from '@/types/crm'
import { formatCurrency } from '@/lib/currency'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'

interface CrmPipelineChartProps {
  stages: CrmDealByStage[]
  isLoading?: boolean
}

export function CrmPipelineChart({ stages, isLoading }: CrmPipelineChartProps) {
  const data = stages.map((stage) => ({
    name: stage.stage_name,
    count: stage.count,
    value: stage.total_value,
    fill: stage.is_closed_won
      ? 'hsl(var(--success))'
      : stage.is_closed_lost
        ? 'hsl(var(--danger))'
        : 'hsl(var(--primary))',
  }))
  const hasData = data.some((stage) => stage.count > 0)

  return (
    <Card>
      <CardHeader>
        <CardTitle className="flex items-center gap-2 text-base">
          <Workflow className="h-4 w-4" />
          Pipeline by Stage
        </CardTitle>
      </CardHeader>
      <CardContent>
        {isLoading ? (
          <div className="flex h-52 items-center justify-center text-sm text-muted-foreground">Loading…</div>
        ) : !hasData ? (
          <div className="flex h-52 items-center justify-center text-sm text-muted-foreground">No deals yet.</div>
        ) : (
          <ResponsiveContainer width="100%" height={220}>
            <BarChart data={data} margin={{ top: 5, right: 8, left: -20, bottom: 0 }}>
              <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="hsl(var(--border))" />
              <XAxis
                dataKey="name"
                tick={{ fontSize: 11, fill: 'hsl(var(--muted-foreground))' }}
                axisLine={false}
                tickLine={false}
                interval={0}
                angle={-20}
                textAnchor="end"
                height={48}
              />
              <YAxis allowDecimals={false} tick={{ fontSize: 12, fill: 'hsl(var(--muted-foreground))' }} axisLine={false} tickLine={false} width={28} />
              <Tooltip
                contentStyle={{ borderRadius: 8, border: '1px solid hsl(var(--border))', fontSize: 12 }}
                labelStyle={{ fontWeight: 600, color: 'hsl(var(--foreground))' }}
                formatter={(value, _name, item) => [`${value} deal(s) · ${formatCurrency(item.payload.value)}`, 'Deals']}
              />
              <Bar dataKey="count" radius={[4, 4, 0, 0]}>
                {data.map((entry) => (
                  <Cell key={entry.name} fill={entry.fill} />
                ))}
              </Bar>
            </BarChart>
          </ResponsiveContainer>
        )}
      </CardContent>
    </Card>
  )
}
