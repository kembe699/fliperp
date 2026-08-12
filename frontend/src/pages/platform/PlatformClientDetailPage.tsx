import { useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { FileText, Receipt } from 'lucide-react'

import { activatePlatformClient, fetchPlatformClient, suspendPlatformClient } from '@/api/platform'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import type { CompanyStatus } from '@/types/auth'
import type { PlatformTicketPriority, PlatformTicketStatus } from '@/types/platform'

import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'
import { PlatformBillingFormDialog } from '@/pages/platform/PlatformBillingFormDialog'

const STATUS_VARIANT: Record<CompanyStatus, 'success' | 'danger' | 'warning'> = {
  active: 'success',
  suspended: 'danger',
  pending: 'warning',
}

const TICKET_STATUS_VARIANT: Record<PlatformTicketStatus, 'info' | 'warning' | 'success' | 'neutral'> = {
  open: 'info',
  in_progress: 'warning',
  resolved: 'success',
  closed: 'neutral',
}

const TICKET_PRIORITY_VARIANT: Record<PlatformTicketPriority, 'neutral' | 'info' | 'warning' | 'danger'> = {
  low: 'neutral',
  medium: 'info',
  high: 'warning',
  urgent: 'danger',
}

export function PlatformClientDetailPage() {
  const { id } = useParams<{ id: string }>()
  const clientId = Number(id)
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const [billingDialog, setBillingDialog] = useState<'invoice' | 'quotation' | null>(null)

  const { data, isLoading } = useQuery({
    queryKey: ['platform-client', clientId],
    queryFn: () => fetchPlatformClient(clientId),
    enabled: !!clientId,
  })

  const suspendMutation = useMutation({
    mutationFn: () => suspendPlatformClient(clientId),
    onSuccess: () => {
      toast.success('Client suspended')
      queryClient.invalidateQueries({ queryKey: ['platform-client', clientId] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const activateMutation = useMutation({
    mutationFn: () => activatePlatformClient(clientId),
    onSuccess: () => {
      toast.success('Client activated')
      queryClient.invalidateQueries({ queryKey: ['platform-client', clientId] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  if (isLoading || !data) {
    return (
      <div>
        <PageHeader parent="Clients" title="Loading…" />
      </div>
    )
  }

  const { company, admin_users, billing, usage, tickets } = data

  return (
    <div>
      <PageHeader
        parent="Clients"
        title={company.name}
        action={
          <div className="flex gap-2">
            {company.status === 'active' ? (
              <Button variant="outline" className="text-destructive" onClick={() => suspendMutation.mutate()} disabled={suspendMutation.isPending}>
                Suspend Client
              </Button>
            ) : (
              <Button onClick={() => activateMutation.mutate()} disabled={activateMutation.isPending}>
                Activate Client
              </Button>
            )}
          </div>
        }
      />

      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <Card>
          <CardContent className="p-4">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Client Code</p>
            <p className="mt-1 font-mono text-lg font-semibold text-foreground">{company.client_code}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Status</p>
            <div className="mt-1.5">
              <StatusBadge label={company.status} variant={STATUS_VARIANT[company.status]} className="capitalize" />
            </div>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Users</p>
            <p className="mt-1 text-lg font-semibold text-foreground">{usage.user_count}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="p-4">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Onboarded</p>
            <p className="mt-1 text-lg font-semibold text-foreground">{formatDate(company.created_at)}</p>
          </CardContent>
        </Card>
      </div>

      <Tabs defaultValue="overview">
        <TabsList>
          <TabsTrigger value="overview">Overview</TabsTrigger>
          <TabsTrigger value="billing">Billing</TabsTrigger>
          <TabsTrigger value="tickets">Tickets</TabsTrigger>
        </TabsList>

        <TabsContent value="overview">
          <Card>
            <CardHeader>
              <CardTitle>Admin Users</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 pt-0">
              {admin_users.length === 0 ? (
                <p className="text-sm text-muted-foreground">No users yet.</p>
              ) : (
                admin_users.map((adminUser) => (
                  <div key={adminUser.id} className="flex items-center justify-between rounded-lg border border-border px-3 py-2 text-sm">
                    <span className="font-medium text-foreground">{adminUser.name}</span>
                    <span className="text-muted-foreground">{adminUser.email}</span>
                  </div>
                ))
              )}
              {usage.last_login_at && (
                <p className="pt-2 text-xs text-muted-foreground">Last login {formatDate(usage.last_login_at)}</p>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="billing">
          {!company.billing_customer_id ? (
            <Card>
              <CardContent className="p-6 text-sm text-muted-foreground">This client has no billing customer configured.</CardContent>
            </Card>
          ) : (
            <div className="space-y-4">
              <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <Card>
                  <CardContent className="p-4">
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Total Invoiced</p>
                    <p className="mt-1 text-xl font-semibold text-foreground">{formatCurrency(billing?.total_invoiced ?? 0)}</p>
                  </CardContent>
                </Card>
                <Card>
                  <CardContent className="p-4">
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Total Paid</p>
                    <p className="mt-1 text-xl font-semibold text-foreground">{formatCurrency(billing?.total_paid ?? 0)}</p>
                  </CardContent>
                </Card>
                <Card>
                  <CardContent className="p-4">
                    <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">Outstanding</p>
                    <p className="mt-1 text-xl font-semibold text-foreground">{formatCurrency(billing?.outstanding_balance ?? 0)}</p>
                  </CardContent>
                </Card>
              </div>

              <div className="flex gap-2">
                <Button onClick={() => setBillingDialog('invoice')}>
                  <Receipt className="h-4 w-4" />
                  Create Invoice
                </Button>
                <Button variant="outline" onClick={() => setBillingDialog('quotation')}>
                  <FileText className="h-4 w-4" />
                  Create Quotation
                </Button>
                <Button variant="outline" onClick={() => navigate('/invoices')}>
                  View All Invoices
                </Button>
              </div>
            </div>
          )}
        </TabsContent>

        <TabsContent value="tickets">
          <Card>
            <CardContent className="p-0">
              {tickets.length === 0 ? (
                <p className="p-6 text-sm text-muted-foreground">No tickets raised by this client.</p>
              ) : (
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                      <th className="px-4 py-2.5">Subject</th>
                      <th className="px-4 py-2.5">Priority</th>
                      <th className="px-4 py-2.5">Status</th>
                      <th className="px-4 py-2.5">Raised</th>
                      <th className="px-4 py-2.5" />
                    </tr>
                  </thead>
                  <tbody>
                    {tickets.map((ticket) => (
                      <tr key={ticket.id} className="border-b border-border last:border-b-0 hover:bg-accent/30">
                        <td className="px-4 py-3 font-medium text-foreground">{ticket.subject}</td>
                        <td className="px-4 py-3">
                          <StatusBadge label={ticket.priority} variant={TICKET_PRIORITY_VARIANT[ticket.priority]} className="capitalize" />
                        </td>
                        <td className="px-4 py-3">
                          <StatusBadge label={ticket.status.replace('_', ' ')} variant={TICKET_STATUS_VARIANT[ticket.status]} className="capitalize" />
                        </td>
                        <td className="px-4 py-3 text-muted-foreground">{formatDate(ticket.created_at)}</td>
                        <td className="px-4 py-3 text-right">
                          <Button variant="outline" size="sm" onClick={() => navigate(`/platform-admin/tickets?id=${ticket.id}`)}>
                            View
                          </Button>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              )}
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>

      {billingDialog && (
        <PlatformBillingFormDialog
          open={!!billingDialog}
          onOpenChange={(open) => !open && setBillingDialog(null)}
          clientId={clientId}
          kind={billingDialog}
        />
      )}
    </div>
  )
}
