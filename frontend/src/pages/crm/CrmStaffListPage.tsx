import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'

import { fetchCrmStaffReport } from '@/api/crm'
import { formatCurrency } from '@/lib/currency'
import type { CrmStaffReportRow } from '@/types/crm'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'

export function CrmStaffListPage() {
  const navigate = useNavigate()

  const { data, isLoading, isError } = useQuery({ queryKey: ['crm-report-staff'], queryFn: fetchCrmStaffReport })

  const columns: DataTableColumn<CrmStaffReportRow>[] = [
    { key: 'user_name', header: 'Staff Member', accessor: (row) => row.user_name, sortable: true },
    { key: 'customers_assigned', header: 'Customers Assigned', accessor: (row) => row.customers_assigned, sortable: true },
    {
      key: 'open_activities_count',
      header: 'Open Activities',
      accessor: (row) => row.open_activities_count,
      render: (row) => (row.open_activities_count > 0 ? <StatusBadge label={String(row.open_activities_count)} variant="info" /> : '0'),
    },
    {
      key: 'open_complaints_count',
      header: 'Open Complaints',
      accessor: (row) => row.open_complaints_count,
      render: (row) =>
        row.open_complaints_count > 0 ? <StatusBadge label={String(row.open_complaints_count)} variant="danger" /> : '0',
    },
    {
      key: 'closed_won_value',
      header: 'Closed Won Value',
      accessor: (row) => row.closed_won_value,
      sortable: true,
      render: (row) => formatCurrency(row.closed_won_value),
    },
    {
      key: 'upcoming_meetings_count',
      header: 'Upcoming Meetings',
      accessor: (row) => row.upcoming_meetings_count,
      sortable: true,
      render: (row) =>
        row.upcoming_meetings_count > 0 ? <StatusBadge label={String(row.upcoming_meetings_count)} variant="info" /> : '0',
    },
  ]

  const rowActions: (row: CrmStaffReportRow) => DataTableRowAction<CrmStaffReportRow>[] = (row) => [
    { label: 'View Details', onClick: (r: CrmStaffReportRow) => navigate(`/crm/staff/${r.user_id}`) },
  ]

  return (
    <div>
      <PageHeader parent="CRM" title="Staff" />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">
          Could not load the staff report. Please try again.
        </p>
      ) : (
        <DataTable
          columns={columns}
          data={data ?? []}
          rowKey={(row) => row.user_id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No staff data"
          emptySubtext="No CRM staff performance data is available yet."
        />
      )}
    </div>
  )
}
