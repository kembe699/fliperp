import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Handshake, TrendingUp, Users, AlertCircle } from 'lucide-react'

import { fetchCrmStaffReport } from '@/api/crm'
import { formatCurrency } from '@/lib/currency'
import type { CrmStaffDeal } from '@/types/crm'

import { PageHeader } from '@/components/layout/PageHeader'
import { StatCard } from '@/components/shared/StatCard'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'

export function CrmStaffDetailPage() {
  const { id } = useParams<{ id: string }>()
  const userId = Number(id)

  const { data, isLoading } = useQuery({ queryKey: ['crm-report-staff'], queryFn: fetchCrmStaffReport })
  const staff = data?.find((row) => row.user_id === userId)

  const dealColumns: DataTableColumn<CrmStaffDeal>[] = [
    { key: 'title', header: 'Deal', accessor: (row) => row.title },
    { key: 'stage_name', header: 'Stage', accessor: (row) => row.stage_name ?? '—' },
    { key: 'value', header: 'Value', render: (row) => formatCurrency(row.value) },
  ]

  if (isLoading) {
    return <div className="p-6 text-sm text-muted-foreground">Loading staff member…</div>
  }

  if (!staff) {
    return (
      <div>
        <PageHeader parent="CRM · Staff" title="Staff Member Not Found" />
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-muted-foreground">
          No staff performance data was found for this user.
        </p>
      </div>
    )
  }

  return (
    <div>
      <PageHeader parent="CRM · Staff" title={staff.user_name} />

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard icon={Users} label="Customers Assigned" value={staff.customers_assigned} />
        <StatCard icon={AlertCircle} label="Open Activities" value={staff.open_activities_count} />
        <StatCard icon={AlertCircle} label="Open Complaints" value={staff.open_complaints_count} />
        <StatCard icon={TrendingUp} label="Closed Won Value" value={formatCurrency(staff.closed_won_value)} />
      </div>

      <div className="mt-6">
        <div className="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
          <Handshake className="h-4 w-4" />
          Deals
        </div>
        <DataTable
          columns={dealColumns}
          data={staff.deals}
          rowKey={(row) => row.deal_id}
          emptyTitle="No deals"
          emptySubtext="This staff member has no deals assigned yet."
        />
      </div>
    </div>
  )
}
