import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { resolveCrmActivity } from '@/api/crm'
import { formatDate } from '@/lib/format'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { CrmActivity } from '@/types/crm'

import { Button } from '@/components/ui/button'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { LogActivityDialog } from '@/components/crm/LogActivityDialog'

const ACTIVITY_TYPE_VARIANT: Record<CrmActivity['type'], 'info' | 'warning' | 'danger' | 'neutral'> = {
  call: 'info',
  email: 'info',
  meeting: 'info',
  note: 'neutral',
  complaint: 'danger',
  follow_up: 'warning',
}

interface CrmDrawerActivitiesTabProps {
  leadId?: number
  dealId?: number
  activities: CrmActivity[]
  queryKeyToInvalidate: unknown[]
}

export function CrmDrawerActivitiesTab({ leadId, dealId, activities, queryKeyToInvalidate }: CrmDrawerActivitiesTabProps) {
  const queryClient = useQueryClient()
  const { can } = usePermissions()
  const [logOpen, setLogOpen] = useState(false)

  const resolveMutation = useMutation({
    mutationFn: resolveCrmActivity,
    onSuccess: () => {
      toast.success('Activity resolved')
      queryClient.invalidateQueries({ queryKey: queryKeyToInvalidate })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const columns: DataTableColumn<CrmActivity>[] = [
    { key: 'activity_date', header: 'Date', accessor: (row) => row.activity_date, render: (row) => formatDate(row.activity_date) },
    { key: 'type', header: 'Type', render: (row) => <StatusBadge label={row.type.replace('_', ' ')} variant={ACTIVITY_TYPE_VARIANT[row.type]} /> },
    { key: 'subject', header: 'Subject', accessor: (row) => row.subject },
    { key: 'logged_by', header: 'Logged By', render: (row) => row.logged_by?.name ?? '—' },
    {
      key: 'status',
      header: 'Status',
      render: (row) => <StatusBadge label={row.status} variant={row.status === 'open' ? 'warning' : 'success'} />,
    },
  ]

  const rowActions: (row: CrmActivity) => DataTableRowAction<CrmActivity>[] = (row) => [
    ...(row.status === 'open' && can('crm-activities.resolve')
      ? [{ label: 'Resolve', onClick: (a: CrmActivity) => resolveMutation.mutate(a.id) }]
      : []),
  ]

  return (
    <div className="space-y-4">
      {can('crm-activities.create') && (
        <div className="flex justify-end">
          <Button size="sm" onClick={() => setLogOpen(true)}>
            <Plus className="h-4 w-4" />
            Log Activity
          </Button>
        </div>
      )}

      <DataTable
        columns={columns}
        data={activities}
        rowKey={(row) => row.id}
        rowActions={rowActions}
        emptyTitle="No activity recorded"
        emptySubtext="No calls, emails, meetings or complaints have been logged yet."
      />

      <LogActivityDialog
        open={logOpen}
        onOpenChange={setLogOpen}
        leadId={leadId}
        dealId={dealId}
        queryKeyToInvalidate={queryKeyToInvalidate}
      />
    </div>
  )
}
