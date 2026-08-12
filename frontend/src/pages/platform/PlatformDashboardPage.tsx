import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Building2, CircleCheck, CircleOff, Clock, Headset, Wallet } from 'lucide-react'

import { fetchPlatformBillingSummary, fetchPlatformClients, fetchPlatformTickets } from '@/api/platform'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'

import { PageHeader } from '@/components/layout/PageHeader'
import { StatCard } from '@/components/shared/StatCard'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import type { CompanyStatus } from '@/types/auth'

const STATUS_VARIANT: Record<CompanyStatus, 'success' | 'danger' | 'warning'> = {
  active: 'success',
  suspended: 'danger',
  pending: 'warning',
}

export function PlatformDashboardPage() {
  const navigate = useNavigate()

  const { data: clients, isLoading: clientsLoading } = useQuery({
    queryKey: ['platform-clients', 'dashboard'],
    queryFn: () => fetchPlatformClients({ per_page: 100 }),
  })

  const { data: billing, isLoading: billingLoading } = useQuery({
    queryKey: ['platform-billing-summary'],
    queryFn: fetchPlatformBillingSummary,
  })

  const { data: openTickets, isLoading: ticketsLoading } = useQuery({
    queryKey: ['platform-tickets', 'open-count'],
    queryFn: () => fetchPlatformTickets({ status: 'open', per_page: 1 }),
  })

  const all = clients?.data ?? []
  const active = all.filter((c) => c.status === 'active').length
  const suspended = all.filter((c) => c.status === 'suspended').length
  const pending = all.filter((c) => c.status === 'pending').length
  const recent = [...all].sort((a, b) => b.created_at.localeCompare(a.created_at)).slice(0, 5)

  return (
    <div>
      <PageHeader title="Platform Dashboard" />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard
          label="Total Clients"
          icon={Building2}
          value={clientsLoading ? '—' : all.length}
          subtext={clientsLoading ? undefined : `${active} active · ${pending} pending · ${suspended} suspended`}
        />
        <StatCard
          label="Total Revenue"
          icon={Wallet}
          value={billingLoading ? '—' : formatCurrency(billing?.total_invoiced ?? 0)}
          subtext={billingLoading ? undefined : `${formatCurrency(billing?.total_outstanding ?? 0)} outstanding`}
        />
        <StatCard
          label="Open Tickets"
          icon={Headset}
          value={ticketsLoading ? '—' : (openTickets?.meta.total ?? 0)}
          subtext="Awaiting a response"
        />
        <StatCard
          label="Pending Onboarding"
          icon={Clock}
          value={clientsLoading ? '—' : pending}
          subtext="Clients not yet activated"
        />
      </div>

      <div className="mt-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <Card className="lg:col-span-2">
          <CardHeader>
            <CardTitle>Recently Onboarded Clients</CardTitle>
          </CardHeader>
          <CardContent className="p-0">
            {recent.length === 0 ? (
              <p className="px-6 pb-6 text-sm text-muted-foreground">No clients onboarded yet.</p>
            ) : (
              <table className="w-full text-sm">
                <tbody>
                  {recent.map((client) => (
                    <tr
                      key={client.id}
                      className="cursor-pointer border-b border-border last:border-b-0 hover:bg-accent/30"
                      onClick={() => navigate(`/platform-admin/clients/${client.id}`)}
                    >
                      <td className="px-6 py-3 font-medium text-foreground">{client.name}</td>
                      <td className="px-4 py-3 font-mono text-xs text-muted-foreground">{client.client_code}</td>
                      <td className="px-4 py-3">
                        <StatusBadge label={client.status} variant={STATUS_VARIANT[client.status]} className="capitalize" />
                      </td>
                      <td className="px-4 py-3 text-right text-muted-foreground">{formatDate(client.created_at)}</td>
                    </tr>
                  ))}
                </tbody>
              </table>
            )}
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle className="flex items-center gap-2">
              <CircleCheck className="h-4 w-4 text-success" />
              Status Breakdown
            </CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="flex items-center justify-between text-sm">
              <span className="flex items-center gap-2 text-muted-foreground"><CircleCheck className="h-3.5 w-3.5 text-success" /> Active</span>
              <span className="font-semibold text-foreground">{active}</span>
            </div>
            <div className="flex items-center justify-between text-sm">
              <span className="flex items-center gap-2 text-muted-foreground"><Clock className="h-3.5 w-3.5 text-warning" /> Pending</span>
              <span className="font-semibold text-foreground">{pending}</span>
            </div>
            <div className="flex items-center justify-between text-sm">
              <span className="flex items-center gap-2 text-muted-foreground"><CircleOff className="h-3.5 w-3.5 text-destructive" /> Suspended</span>
              <span className="font-semibold text-foreground">{suspended}</span>
            </div>
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
