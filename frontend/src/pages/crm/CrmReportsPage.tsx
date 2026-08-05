import { useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { DollarSign, TrendingUp, Users, PackageCheck } from 'lucide-react'

import { fetchCrmReportSummary } from '@/api/crm'
import { fetchBranches } from '@/api/branches'
import { formatCurrency } from '@/lib/currency'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { startOfMonth, toISODate } from '@/lib/format'
import type { CrmDealByStage, CrmStaffPerformance } from '@/types/crm'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { StatCard } from '@/components/shared/StatCard'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'

function FunnelBar({ stage, maxValue }: { stage: CrmDealByStage; maxValue: number }) {
  const widthPercent = maxValue > 0 ? Math.max((stage.total_value / maxValue) * 100, stage.count > 0 ? 4 : 0) : 0

  return (
    <div>
      <div className="mb-1 flex items-center justify-between text-sm">
        <span className="font-medium text-foreground">{stage.stage_name}</span>
        <span className="text-muted-foreground">
          {stage.count} deal{stage.count === 1 ? '' : 's'} · {formatCurrency(stage.total_value)}
        </span>
      </div>
      <div className="h-3 w-full overflow-hidden rounded-full bg-muted">
        <div
          className={`h-full rounded-full ${
            stage.is_closed_won ? 'bg-success' : stage.is_closed_lost ? 'bg-danger' : 'bg-primary'
          }`}
          style={{ width: `${widthPercent}%` }}
        />
      </div>
    </div>
  )
}

export function CrmReportsPage() {
  const [from, setFrom] = useState(toISODate(startOfMonth()))
  const [to, setTo] = useState(toISODate(new Date()))
  const [branchId, setBranchId] = useState<string>('all')

  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })

  const { data: summary, isLoading } = useQuery({
    queryKey: ['crm-report-summary-filtered', from, to, branchId],
    queryFn: () => fetchCrmReportSummary({ from, to, branch_id: branchId === 'all' ? undefined : Number(branchId) }),
  })

  const maxStageValue = Math.max(...(summary?.deals.by_stage.map((s) => s.total_value) ?? [0]), 1)

  const staffColumns: DataTableColumn<CrmStaffPerformance>[] = [
    { key: 'user_name', header: 'Staff Member', accessor: (row) => row.user_name, sortable: true },
    { key: 'deals_closed_won', header: 'Deals Closed Won', accessor: (row) => row.deals_closed_won, sortable: true },
    {
      key: 'total_value',
      header: 'Total Value',
      accessor: (row) => row.total_value,
      sortable: true,
      render: (row) => formatCurrency(row.total_value),
    },
  ]

  return (
    <div>
      <PageHeader
        parent="CRM"
        title="Reports"
        action={
          <ExportCsvButton
            onExport={async () => {
              exportToCsv('crm-staff-performance.csv', csvColumnsFromDataTable(staffColumns), summary?.staff_performance ?? [])
            }}
          />
        }
      />

      <FilterBar>
        <div className="flex items-center gap-2">
          <Label htmlFor="report-from" className="text-xs text-muted-foreground">
            From
          </Label>
          <Input id="report-from" type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="h-8 w-40" />
        </div>
        <div className="flex items-center gap-2">
          <Label htmlFor="report-to" className="text-xs text-muted-foreground">
            To
          </Label>
          <Input id="report-to" type="date" value={to} onChange={(e) => setTo(e.target.value)} className="h-8 w-40" />
        </div>
        <Select value={branchId} onValueChange={setBranchId}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Branch" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All branches</SelectItem>
            {branches?.map((branch) => (
              <SelectItem key={branch.id} value={String(branch.id)}>
                {branch.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </FilterBar>

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard icon={Users} label="Total Leads" value={isLoading || !summary ? '—' : summary.leads.total} />
        <StatCard
          icon={TrendingUp}
          label="Conversion Rate"
          value={isLoading || !summary || summary.conversion_rate === null ? '—' : `${summary.conversion_rate}%`}
        />
        <StatCard
          icon={PackageCheck}
          label="Active Customer Services"
          value={isLoading || !summary ? '—' : summary.customers_with_active_services}
        />
        <StatCard
          icon={DollarSign}
          label="Total Revenue"
          value={isLoading || !summary ? '—' : formatCurrency(summary.total_revenue)}
        />
      </div>

      <Card className="mt-6">
        <CardContent className="space-y-4 p-6">
          <p className="text-sm font-semibold text-foreground">Pipeline Funnel</p>
          {isLoading || !summary ? (
            <p className="text-sm text-muted-foreground">Loading…</p>
          ) : summary.deals.by_stage.length === 0 ? (
            <p className="text-sm text-muted-foreground">No deals in this date range.</p>
          ) : (
            <div className="space-y-3">
              {summary.deals.by_stage.map((stage) => (
                <FunnelBar key={stage.stage_id} stage={stage} maxValue={maxStageValue} />
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      <div className="mt-6">
        <p className="mb-3 text-sm font-semibold text-foreground">Staff Performance</p>
        <DataTable
          columns={staffColumns}
          data={summary?.staff_performance ?? []}
          rowKey={(row) => row.user_id}
          isLoading={isLoading}
          emptyTitle="No staff performance data"
          emptySubtext="No closed deals in this date range yet."
        />
      </div>
    </div>
  )
}
