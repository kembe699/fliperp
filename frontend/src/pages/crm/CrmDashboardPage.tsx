import { useQuery } from '@tanstack/react-query'
import { Users, Handshake, TrendingUp, PackageCheck, AlertCircle } from 'lucide-react'

import { fetchCrmReportSummary, fetchCrmStaffReport } from '@/api/crm'
import { formatCurrency } from '@/lib/currency'
import { useAuthStore } from '@/lib/auth-store'
import { usePermissions } from '@/hooks/use-permissions'

import { PageHeader } from '@/components/layout/PageHeader'
import { StatCard } from '@/components/shared/StatCard'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Card, CardContent } from '@/components/ui/card'

export function CrmDashboardPage() {
  const user = useAuthStore((state) => state.user)
  const { can } = usePermissions()
  const canViewAll = can('crm-customers.view-all')

  const { data: summary, isLoading } = useQuery({
    queryKey: ['crm-report-summary'],
    queryFn: () => fetchCrmReportSummary(),
  })

  const { data: staffReport } = useQuery({
    queryKey: ['crm-report-staff'],
    queryFn: fetchCrmStaffReport,
    enabled: !canViewAll,
  })

  const myRow = staffReport?.find((row) => row.user_id === user?.id)

  const openDealsValue = summary?.deals.by_stage
    .filter((stage) => !stage.is_closed_won && !stage.is_closed_lost)
    .reduce((sum, stage) => sum + stage.total_value, 0)

  return (
    <div>
      <PageHeader title="CRM Dashboard" />

      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          icon={Users}
          label="Total Leads"
          value={isLoading || !summary ? '—' : summary.leads.total}
          subtext={
            summary && (
              <div className="flex flex-wrap gap-1.5">
                <StatusBadge label={`${summary.leads.by_status.open} open`} variant="info" />
                <StatusBadge label={`${summary.leads.by_status.converted} converted`} variant="success" />
                <StatusBadge label={`${summary.leads.by_status.disqualified} disqualified`} variant="neutral" />
              </div>
            )
          }
        />
        <StatCard
          icon={Handshake}
          label="Open Deals Value"
          value={isLoading || openDealsValue === undefined ? '—' : formatCurrency(openDealsValue)}
        />
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
      </div>

      {!canViewAll && (
        <Card className="mt-6 max-w-md border-primary/30 bg-accent/40">
          <CardContent className="flex items-start gap-4 p-5">
            <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-primary/10 text-primary">
              <AlertCircle className="h-5 w-5" />
            </div>
            <div>
              <p className="text-sm font-semibold text-foreground">My Assignments</p>
              <p className="mt-1 text-sm text-muted-foreground">
                You're assigned to <span className="font-semibold text-foreground">{myRow?.customers_assigned ?? 0}</span> customer
                {myRow?.customers_assigned === 1 ? '' : 's'}, with{' '}
                <span className="font-semibold text-foreground">{myRow?.open_complaints_count ?? 0}</span> open complaint
                {myRow?.open_complaints_count === 1 ? '' : 's'} needing attention.
              </p>
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  )
}
