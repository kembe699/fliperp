import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { ArrowUpRight, TrendingUp, Wallet, WalletCards } from 'lucide-react'

import { fetchPlatformBillingSummary } from '@/api/platform'
import { formatCurrency } from '@/lib/currency'
import type { CompanyStatus } from '@/types/auth'

import { PageHeader } from '@/components/layout/PageHeader'
import { StatCard } from '@/components/shared/StatCard'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'

const STATUS_VARIANT: Record<CompanyStatus, 'success' | 'danger' | 'warning'> = {
  active: 'success',
  suspended: 'danger',
  pending: 'warning',
}

const REPORT_LINKS = [
  { label: 'Profit & Loss', path: '/reports/profit-and-loss' },
  { label: 'Trial Balance', path: '/reports/trial-balance' },
  { label: 'Balance Sheet', path: '/reports/balance-sheet' },
  { label: 'Cash Flow', path: '/reports/cash-flow' },
]

export function PlatformBillingPage() {
  const navigate = useNavigate()

  const { data, isLoading } = useQuery({ queryKey: ['platform-billing-summary'], queryFn: fetchPlatformBillingSummary })

  return (
    <div>
      <PageHeader title="Billing" />

      <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <StatCard label="Total Invoiced" icon={Wallet} value={isLoading ? '—' : formatCurrency(data?.total_invoiced ?? 0)} />
        <StatCard label="Total Collected" icon={WalletCards} value={isLoading ? '—' : formatCurrency(data?.total_collected ?? 0)} />
        <StatCard label="Outstanding" icon={TrendingUp} value={isLoading ? '—' : formatCurrency(data?.total_outstanding ?? 0)} />
        <StatCard label="Billable Clients" icon={Wallet} value={isLoading ? '—' : (data?.client_count ?? 0)} />
      </div>

      <div className="mt-6 rounded-xl border border-border bg-card p-4">
        <p className="mb-3 text-sm font-medium text-foreground">
          This is the platform company's own revenue — reported through the same Accounting module every tenant uses.
          For the full financial picture (journal entries, P&amp;L, balance sheet), use the existing Accounting Reports:
        </p>
        <div className="flex flex-wrap gap-2">
          {REPORT_LINKS.map((report) => (
            <Button key={report.path} variant="outline" size="sm" onClick={() => navigate(report.path)}>
              {report.label}
              <ArrowUpRight className="h-3.5 w-3.5" />
            </Button>
          ))}
        </div>
      </div>

      <Card className="mt-6">
        <CardHeader>
          <CardTitle>Revenue by Client</CardTitle>
        </CardHeader>
        <CardContent className="p-0">
          {isLoading ? (
            <p className="px-6 pb-6 text-sm text-muted-foreground">Loading…</p>
          ) : !data || data.by_client.length === 0 ? (
            <p className="px-6 pb-6 text-sm text-muted-foreground">No billable clients yet.</p>
          ) : (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                  <th className="px-6 py-2.5">Client</th>
                  <th className="px-4 py-2.5">Status</th>
                  <th className="px-4 py-2.5">Invoices</th>
                  <th className="px-4 py-2.5">Invoiced</th>
                  <th className="px-4 py-2.5">Paid</th>
                  <th className="px-6 py-2.5 text-right">Outstanding</th>
                </tr>
              </thead>
              <tbody>
                {data.by_client.map((client) => (
                  <tr
                    key={client.company_id}
                    className="cursor-pointer border-b border-border last:border-b-0 hover:bg-accent/30"
                    onClick={() => navigate(`/platform-admin/clients/${client.company_id}`)}
                  >
                    <td className="px-6 py-3 font-medium text-foreground">{client.company_name}</td>
                    <td className="px-4 py-3">
                      <StatusBadge label={client.status} variant={STATUS_VARIANT[client.status]} className="capitalize" />
                    </td>
                    <td className="px-4 py-3 text-muted-foreground">{client.invoice_count}</td>
                    <td className="px-4 py-3">{formatCurrency(client.total_invoiced)}</td>
                    <td className="px-4 py-3">{formatCurrency(client.total_paid)}</td>
                    <td className="px-6 py-3 text-right font-medium text-foreground">{formatCurrency(client.outstanding_balance)}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
