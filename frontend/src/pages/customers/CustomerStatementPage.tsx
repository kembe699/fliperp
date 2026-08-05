import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Download, Plus, ShieldAlert } from 'lucide-react'

import { fetchCustomerStatement } from '@/api/customers'
import { fetchCrmServiceStatement, fetchCurrentCrmAssignments, fetchCrmActivities, resolveCrmActivity, unassignCrmAccount } from '@/api/crm'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { downloadPdf } from '@/lib/pdf-download'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { CustomerStatementTransaction } from '@/types/customer'
import type { CrmActivity, CrmServiceStatementEntry, CrmAccountAssignment } from '@/types/crm'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { EmptyState } from '@/components/shared/EmptyState'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { CrmCustomerServiceFormDialog } from '@/components/crm/CrmCustomerServiceFormDialog'
import { AssignStaffDialog } from '@/components/crm/AssignStaffDialog'
import { LogActivityDialog } from '@/components/crm/LogActivityDialog'

const TYPE_LABEL: Record<CustomerStatementTransaction['type'], string> = {
  sale: 'POS Sale',
  sale_payment: 'POS Payment',
  invoice: 'Invoice',
  invoice_payment: 'Invoice Payment',
}

const CUSTOMER_SERVICE_STATUS_VARIANT: Record<CrmServiceStatementEntry['status'], 'success' | 'neutral' | 'danger'> = {
  active: 'success',
  completed: 'neutral',
  cancelled: 'danger',
}

const ACTIVITY_TYPE_VARIANT: Record<CrmActivity['type'], 'info' | 'warning' | 'danger' | 'neutral'> = {
  call: 'info',
  email: 'info',
  meeting: 'info',
  note: 'neutral',
  complaint: 'danger',
  follow_up: 'warning',
}

function RestrictedAccessNotice({ message }: { message: string }) {
  return (
    <div className="rounded-xl border border-border bg-card">
      <EmptyState icon={ShieldAlert} title="No access to this customer's CRM data" subtext={message} />
    </div>
  )
}

export function CustomerStatementPage() {
  const { id } = useParams<{ id: string }>()
  const customerId = Number(id)
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const { data, isLoading, isError } = useQuery({
    queryKey: ['customer-statement', customerId],
    queryFn: () => fetchCustomerStatement(customerId),
    enabled: !!customerId,
  })

  const [downloading, setDownloading] = useState(false)
  const [serviceFormOpen, setServiceFormOpen] = useState(false)
  const [assignStaffOpen, setAssignStaffOpen] = useState(false)
  const [logActivityOpen, setLogActivityOpen] = useState(false)

  const canViewServices = can('crm-customer-services.view')
  const canViewAssignments = can('crm-account-assignments.view')
  const canViewActivities = can('crm-activities.view')
  const showCrmTabs = canViewServices || canViewAssignments || canViewActivities

  const serviceStatement = useQuery({
    queryKey: ['crm-service-statement', customerId],
    queryFn: () => fetchCrmServiceStatement(customerId),
    enabled: !!customerId && canViewServices,
    retry: false,
  })

  const assignments = useQuery({
    queryKey: ['crm-account-assignments', customerId],
    queryFn: () => fetchCurrentCrmAssignments(customerId),
    enabled: !!customerId && canViewAssignments,
    retry: false,
  })

  const activities = useQuery({
    queryKey: ['crm-activities', customerId],
    queryFn: () => fetchCrmActivities({ customer_id: customerId, per_page: 50 }),
    enabled: !!customerId && canViewActivities,
    retry: false,
  })

  const unassignMutation = useMutation({
    mutationFn: unassignCrmAccount,
    onSuccess: () => {
      toast.success('Staff member unassigned')
      queryClient.invalidateQueries({ queryKey: ['crm-account-assignments', customerId] })
    },
    onError: (err) => toast.error(getApiErrorInfo(err).message),
  })

  const resolveActivityMutation = useMutation({
    mutationFn: resolveCrmActivity,
    onSuccess: () => {
      toast.success('Activity resolved')
      queryClient.invalidateQueries({ queryKey: ['crm-activities', customerId] })
    },
    onError: (err) => toast.error(getApiErrorInfo(err).message),
  })

  const handleDownloadPdf = async () => {
    if (!data) return
    setDownloading(true)
    try {
      await downloadPdf(`/customers/${customerId}/statement/pdf`, `statement-${data.customer_name}.pdf`)
    } catch {
      toast.error('Could not download the statement PDF. Please try again.')
    } finally {
      setDownloading(false)
    }
  }

  const columns: DataTableColumn<CustomerStatementTransaction>[] = [
    { key: 'date', header: 'Date', accessor: (row) => row.date, sortable: true, render: (row) => formatDate(row.date) },
    { key: 'type', header: 'Type', render: (row) => <StatusBadge label={TYPE_LABEL[row.type]} variant="info" /> },
    { key: 'reference_number', header: 'Reference', accessor: (row) => row.reference_number ?? '—' },
    { key: 'debit', header: 'Debit', accessor: (row) => row.debit, render: (row) => (row.debit ? formatCurrency(row.debit) : '—') },
    { key: 'credit', header: 'Credit', accessor: (row) => row.credit, render: (row) => (row.credit ? formatCurrency(row.credit) : '—') },
    {
      key: 'running_balance',
      header: 'Running Balance',
      accessor: (row) => row.running_balance,
      render: (row) => formatCurrency(row.running_balance),
    },
  ]

  const serviceColumns: DataTableColumn<CrmServiceStatementEntry>[] = [
    { key: 'start_date', header: 'Start', accessor: (row) => row.start_date, render: (row) => formatDate(row.start_date) },
    { key: 'service_name', header: 'Service', accessor: (row) => row.service_name },
    {
      key: 'status',
      header: 'Status',
      render: (row) => <StatusBadge label={row.status} variant={CUSTOMER_SERVICE_STATUS_VARIANT[row.status]} />,
    },
    { key: 'price_charged', header: 'Price Charged', render: (row) => formatCurrency(row.price_charged) },
    { key: 'running_total', header: 'Running Total', render: (row) => formatCurrency(row.running_total) },
  ]

  const assignmentColumns: DataTableColumn<CrmAccountAssignment>[] = [
    { key: 'user', header: 'Staff Member', render: (row) => row.user?.name ?? '—' },
    { key: 'role', header: 'Role', render: (row) => <StatusBadge label={row.role} variant={row.role === 'primary' ? 'info' : 'neutral'} /> },
    { key: 'assigned_at', header: 'Assigned', accessor: (row) => row.assigned_at, render: (row) => formatDate(row.assigned_at) },
  ]

  const assignmentRowActions: (row: CrmAccountAssignment) => DataTableRowAction<CrmAccountAssignment>[] = (row) => [
    ...(can('crm-account-assignments.unassign')
      ? [{ label: 'Unassign', destructive: true, onClick: (a: CrmAccountAssignment) => unassignMutation.mutate(a.id) }]
      : []),
  ]

  const activityColumns: DataTableColumn<CrmActivity>[] = [
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

  const activityRowActions: (row: CrmActivity) => DataTableRowAction<CrmActivity>[] = (row) => [
    ...(row.status === 'open' && can('crm-activities.resolve')
      ? [{ label: 'Resolve', onClick: (a: CrmActivity) => resolveActivityMutation.mutate(a.id) }]
      : []),
  ]

  return (
    <div>
      <PageHeader
        parent="Customers"
        title={data ? `${data.customer_name} — Statement` : 'Customer Statement'}
        action={
          <Button variant="outline" disabled={!data || downloading} onClick={handleDownloadPdf}>
            <Download className="h-4 w-4" />
            {downloading ? 'Downloading…' : 'Download PDF'}
          </Button>
        }
      />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load this statement.</p>
      ) : (
        <>
          <Card className="mb-6 max-w-xs">
            <CardContent className="p-6">
              <p className="text-sm text-muted-foreground">Closing Balance</p>
              <p className={`mt-1 text-3xl font-bold ${data && data.closing_balance > 0 ? 'text-danger' : 'text-foreground'}`}>
                {isLoading || !data ? '—' : formatCurrency(data.closing_balance)}
              </p>
              <p className="mt-1 text-xs text-muted-foreground">Positive means the customer owes this amount.</p>
            </CardContent>
          </Card>

          {!showCrmTabs ? (
            <DataTable
              columns={columns}
              data={data?.transactions ?? []}
              rowKey={(row, index) => `${row.type}-${row.reference_number}-${index}`}
              isLoading={isLoading}
              emptyTitle="No transactions found"
              emptySubtext="This customer has no sales, invoices or payments yet."
            />
          ) : (
            <Tabs defaultValue="statement">
              <TabsList>
                <TabsTrigger value="statement">Statement</TabsTrigger>
                {canViewServices && <TabsTrigger value="services">Services</TabsTrigger>}
                {canViewAssignments && <TabsTrigger value="staff">Assigned Staff</TabsTrigger>}
                {canViewActivities && <TabsTrigger value="activity">Activity Timeline</TabsTrigger>}
              </TabsList>

              <TabsContent value="statement">
                <DataTable
                  columns={columns}
                  data={data?.transactions ?? []}
                  rowKey={(row, index) => `${row.type}-${row.reference_number}-${index}`}
                  isLoading={isLoading}
                  emptyTitle="No transactions found"
                  emptySubtext="This customer has no sales, invoices or payments yet."
                />
              </TabsContent>

              {canViewServices && (
                <TabsContent value="services">
                  {serviceStatement.isError ? (
                    <RestrictedAccessNotice message={getApiErrorInfo(serviceStatement.error).message} />
                  ) : (
                    <>
                      <div className="mb-3 flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                          Total spent: <span className="font-semibold text-foreground">{formatCurrency(serviceStatement.data?.total_spent ?? 0)}</span>
                        </p>
                        {can('crm-customer-services.create') && (
                          <Button size="sm" onClick={() => setServiceFormOpen(true)}>
                            <Plus className="h-4 w-4" />
                            Log Service
                          </Button>
                        )}
                      </div>
                      <DataTable
                        columns={serviceColumns}
                        data={serviceStatement.data?.entries ?? []}
                        rowKey={(row) => row.id}
                        isLoading={serviceStatement.isLoading}
                        emptyTitle="No services logged"
                        emptySubtext="No CRM services have been recorded for this customer yet."
                      />
                    </>
                  )}
                </TabsContent>
              )}

              {canViewAssignments && (
                <TabsContent value="staff">
                  {assignments.isError ? (
                    <RestrictedAccessNotice message={getApiErrorInfo(assignments.error).message} />
                  ) : (
                    <>
                      <div className="mb-3 flex justify-end">
                        {can('crm-account-assignments.assign') && (
                          <Button size="sm" onClick={() => setAssignStaffOpen(true)}>
                            <Plus className="h-4 w-4" />
                            Assign Staff
                          </Button>
                        )}
                      </div>
                      <DataTable
                        columns={assignmentColumns}
                        data={assignments.data ?? []}
                        rowKey={(row) => row.id}
                        rowActions={assignmentRowActions}
                        isLoading={assignments.isLoading}
                        emptyTitle="No staff assigned"
                        emptySubtext="No staff members are currently assigned to this customer."
                      />
                    </>
                  )}
                </TabsContent>
              )}

              {canViewActivities && (
                <TabsContent value="activity">
                  {activities.isError ? (
                    <RestrictedAccessNotice message={getApiErrorInfo(activities.error).message} />
                  ) : (
                    <>
                      <div className="mb-3 flex justify-end">
                        {can('crm-activities.create') && (
                          <Button size="sm" onClick={() => setLogActivityOpen(true)}>
                            <Plus className="h-4 w-4" />
                            Log Activity
                          </Button>
                        )}
                      </div>
                      <DataTable
                        columns={activityColumns}
                        data={activities.data?.data ?? []}
                        rowKey={(row) => row.id}
                        rowActions={activityRowActions}
                        isLoading={activities.isLoading}
                        emptyTitle="No activity recorded"
                        emptySubtext="No calls, emails, meetings or complaints have been logged for this customer yet."
                      />
                    </>
                  )}
                </TabsContent>
              )}
            </Tabs>
          )}
        </>
      )}

      <CrmCustomerServiceFormDialog open={serviceFormOpen} onOpenChange={setServiceFormOpen} customerId={customerId} />
      <AssignStaffDialog open={assignStaffOpen} onOpenChange={setAssignStaffOpen} customerId={customerId} />
      <LogActivityDialog open={logActivityOpen} onOpenChange={setLogActivityOpen} customerId={customerId} />
    </div>
  )
}
