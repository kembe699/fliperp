import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Link } from 'react-router-dom'
import {
  Download,
  FileText,
  Mail,
  Package,
  Receipt,
  ShoppingCart,
  Truck,
} from 'lucide-react'

import { useAuthStore } from '@/lib/auth-store'
import { useUiStore } from '@/lib/ui-store'
import { formatCurrency } from '@/lib/currency'
import { formatDate, startOfMonth, startOfLastMonth, endOfLastMonth, startOfYear, daysAgo, toISODate } from '@/lib/format'
import { fetchDashboardSummary } from '@/api/dashboard'
import { fetchChartOfAccounts, fetchGeneralLedger, downloadReport, QUICK_REPORT_TYPES, quickReportLabel, type QuickReportType } from '@/api/reports'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar, FilterPill } from '@/components/layout/FilterBar'
import { HeroBalanceCard } from '@/components/shared/HeroBalanceCard'
import { QuickActionCard } from '@/components/shared/QuickActionCard'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import type { GeneralLedgerLine } from '@/api/reports'

const REPORT_PERIODS = ['This Month', 'Last Month', 'This Year', 'Custom'] as const
type ReportPeriod = (typeof REPORT_PERIODS)[number]

function periodToRange(period: ReportPeriod): { from: string; to: string } {
  const today = new Date()
  switch (period) {
    case 'This Month':
      return { from: toISODate(startOfMonth(today)), to: toISODate(today) }
    case 'Last Month':
      return { from: toISODate(startOfLastMonth(today)), to: toISODate(endOfLastMonth(today)) }
    case 'This Year':
      return { from: toISODate(startOfYear(today)), to: toISODate(today) }
    default:
      return { from: toISODate(startOfMonth(today)), to: toISODate(today) }
  }
}

const ACTIVITY_WINDOWS = [
  { label: 'Last 7 days', days: 7 },
  { label: 'Last 30 days', days: 30 },
  { label: 'Last 90 days', days: 90 },
]

export function DashboardPage() {
  const user = useAuthStore((state) => state.user)
  const company = useAuthStore((state) => state.company)
  const selectedBranchId = useUiStore((state) => state.selectedBranchId)

  const today = new Date()
  const summaryRange = { from: toISODate(startOfMonth(today)), to: toISODate(today) }

  const { data: summary, isLoading: summaryLoading } = useQuery({
    queryKey: ['dashboard-summary', selectedBranchId, summaryRange.from, summaryRange.to],
    queryFn: () => fetchDashboardSummary({ branch_id: selectedBranchId ?? undefined, ...summaryRange }),
  })

  // Report generator shell state
  const [reportType, setReportType] = useState<QuickReportType>('trial-balance')
  const [reportPeriod, setReportPeriod] = useState<ReportPeriod>('This Month')
  const [reportRange, setReportRange] = useState(() => periodToRange('This Month'))
  const [downloading, setDownloading] = useState(false)

  const handlePeriodChange = (period: ReportPeriod) => {
    setReportPeriod(period)
    if (period !== 'Custom') {
      setReportRange(periodToRange(period))
    }
  }

  const handleDownload = async () => {
    setDownloading(true)
    try {
      await downloadReport(reportType, reportRange.from, reportRange.to)
    } finally {
      setDownloading(false)
    }
  }

  // Latest activity: dashboard/summary has no activity feed, so this uses the
  // general-ledger report for the Cash account as a real stand-in.
  const [activityWindowDays, setActivityWindowDays] = useState(30)
  const activityRange = useMemo(
    () => ({ from: toISODate(daysAgo(activityWindowDays)), to: toISODate(new Date()) }),
    [activityWindowDays],
  )

  const { data: accounts } = useQuery({ queryKey: ['chart-of-accounts'], queryFn: fetchChartOfAccounts })
  const cashAccount = accounts?.find((account) => account.code === '1000')

  const { data: ledger, isLoading: ledgerLoading } = useQuery({
    queryKey: ['dashboard-activity', cashAccount?.id, activityRange.from, activityRange.to],
    queryFn: () => fetchGeneralLedger({ account_id: cashAccount!.id, from: activityRange.from, to: activityRange.to }),
    enabled: !!cashAccount,
  })

  const activityRows = useMemo(() => [...(ledger?.lines ?? [])].reverse(), [ledger])

  const columns: DataTableColumn<GeneralLedgerLine>[] = [
    { key: 'date', header: 'Date', accessor: (row) => row.date, sortable: true, render: (row) => formatDate(row.date) },
    { key: 'reference_number', header: 'Reference', accessor: (row) => row.reference_number },
    {
      key: 'source_module',
      header: 'Source',
      render: (row) => (row.source_module ? <StatusBadge label={row.source_module} variant="info" /> : '—'),
    },
    {
      key: 'debit',
      header: 'Debit',
      accessor: (row) => row.debit,
      sortable: true,
      render: (row) => (row.debit ? formatCurrency(row.debit, company?.currency_code) : '—'),
    },
    {
      key: 'credit',
      header: 'Credit',
      accessor: (row) => row.credit,
      sortable: true,
      render: (row) => (row.credit ? formatCurrency(row.credit, company?.currency_code) : '—'),
    },
    {
      key: 'balance',
      header: 'Balance',
      accessor: (row) => row.balance,
      sortable: true,
      render: (row) => formatCurrency(row.balance, company?.currency_code),
    },
  ]

  const greetingDate = new Intl.DateTimeFormat('en-US', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' }).format(today)
  const firstName = user?.name?.split(' ')[0] ?? ''

  return (
    <div>
      <div className="mb-6">
        <p className="text-xs font-semibold uppercase tracking-wide text-primary">{greetingDate}</p>
        <h1 className="mt-1 text-2xl font-bold text-foreground">
          Welcome back{firstName ? `, ${firstName}` : ''} — <span className="text-primary">{company?.name}</span>
        </h1>
        <p className="mt-1 text-sm text-muted-foreground">Here's what's happening across your business this month.</p>
      </div>

      <div className="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div className="lg:col-span-2">
          <HeroBalanceCard
            label="Net Position (Gross Profit Estimate) — This Month"
            value={summaryLoading || !summary ? '—' : formatCurrency(summary.gross_profit_estimate, company?.currency_code)}
            stats={
              summary
                ? [
                    { label: 'Total Sales', value: formatCurrency(summary.total_sales, company?.currency_code) },
                    { label: 'Total Purchases', value: formatCurrency(summary.total_purchases, company?.currency_code) },
                  ]
                : []
            }
            actions={
              <>
                <Button variant="solid-white" size="sm" asChild>
                  <Link to="/reports/trial-balance">View Reports</Link>
                </Button>
                <Button variant="ghost-white" size="sm" asChild>
                  <Link to="/pos">Go to POS</Link>
                </Button>
              </>
            }
          />
        </div>

        <Card>
          <CardHeader className="pb-3">
            <CardTitle className="text-base">Generate a Report</CardTitle>
          </CardHeader>
          <CardContent className="space-y-3">
            <div className="space-y-1.5">
              <Label>Report type</Label>
              <Select value={reportType} onValueChange={(value) => setReportType(value as QuickReportType)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {QUICK_REPORT_TYPES.map((type) => (
                    <SelectItem key={type} value={type}>
                      {quickReportLabel(type)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="space-y-1.5">
              <Label>Period</Label>
              <Select value={reportPeriod} onValueChange={(value) => handlePeriodChange(value as ReportPeriod)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {REPORT_PERIODS.map((period) => (
                    <SelectItem key={period} value={period}>
                      {period}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            <div className="grid grid-cols-2 gap-2">
              <div className="space-y-1.5">
                <Label>From</Label>
                <Input
                  type="date"
                  value={reportRange.from}
                  onChange={(event) => {
                    setReportPeriod('Custom')
                    setReportRange((prev) => ({ ...prev, from: event.target.value }))
                  }}
                />
              </div>
              <div className="space-y-1.5">
                <Label>To</Label>
                <Input
                  type="date"
                  value={reportRange.to}
                  onChange={(event) => {
                    setReportPeriod('Custom')
                    setReportRange((prev) => ({ ...prev, to: event.target.value }))
                  }}
                />
              </div>
            </div>

            <div className="flex gap-2 pt-1">
              <Button size="sm" className="flex-1" onClick={handleDownload} disabled={downloading}>
                <Download className="h-4 w-4" />
                {downloading ? 'Downloading…' : 'Download'}
              </Button>
              <Button size="sm" variant="outline" className="flex-1" disabled title="Coming soon">
                <Mail className="h-4 w-4" />
                Send to Email
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>

      <div className="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <QuickActionCard icon={ShoppingCart} title="Sales" subtitle="POS, invoices & quotations" to="/sales" />
        <QuickActionCard icon={FileText} title="Create Invoice" subtitle="Bill a customer" to="/invoices/new" />
        <QuickActionCard icon={Truck} title="Procurement" subtitle="Purchase orders & bills" to="/procurement" />
        <QuickActionCard icon={Receipt} title="Reports" subtitle="Financials & statements" to="/reports/trial-balance" />
      </div>

      <PageHeader title="Latest Cash Activity" />
      <FilterBar>
        {ACTIVITY_WINDOWS.map((window) => (
          <FilterPill
            key={window.days}
            label={window.label}
            active={activityWindowDays === window.days}
            withChevron={false}
            onClick={() => setActivityWindowDays(window.days)}
          />
        ))}
      </FilterBar>

      <DataTable
        columns={columns}
        data={activityRows}
        rowKey={(row) => `${row.source_module}-${row.source_id}-${row.reference_number}`}
        isLoading={ledgerLoading}
        emptyTitle="No activity found"
        emptySubtext="Cash movements will show up here once sales, purchases or payments are recorded."
      />
      {!accounts?.some((account) => account.code === '1000') && !summaryLoading && (
        <p className="mt-2 text-xs text-muted-foreground">
          <Package className="mr-1 inline h-3 w-3" />
          No Cash account (1000) found in the chart of accounts for this company.
        </p>
      )}
    </div>
  )
}
