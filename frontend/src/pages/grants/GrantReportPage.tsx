import { useState } from 'react'
import { Link, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { AlertTriangle } from 'lucide-react'

import { fetchGrantReport } from '@/api/grants'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { useBaseCurrency } from '@/hooks/use-base-currency'
import type { GrantReportRow, GrantReportTransaction } from '@/types/grants'

import { PageHeader } from '@/components/layout/PageHeader'
import { ConversionNotice, ReportCurrencySelect } from '@/components/shared/ReportCurrency'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { StatCard } from '@/components/shared/StatCard'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'

/**
 * SOF Grant Report: the donor budget against spend coded to the SOF, per
 * account and DEA. Spend is shown in the SOF's own currency (what the donor
 * is reported in) next to the ledger's base currency; "Show in" converts the
 * base columns only, like any other report.
 */
export function GrantReportPage() {
  const { id } = useParams<{ id: string }>()
  const grantId = Number(id)
  const base = useBaseCurrency()
  const [showIn, setShowIn] = useState(base)
  const [from, setFrom] = useState('')
  const [to, setTo] = useState('')
  const [tab, setTab] = useState('accounts')

  const { data, isLoading, isError } = useQuery({
    queryKey: ['grant-report', grantId, from, to, showIn],
    queryFn: () => fetchGrantReport(grantId, { from: from || undefined, to: to || undefined, currency: showIn === base ? undefined : showIn }),
    enabled: !!grantId,
  })

  const sof = data?.sof_currency ?? ''
  const shown = data?.currency?.code ?? base
  const sofMoney = (amount: number) => formatCurrency(amount, sof)
  const shownMoney = (amount: number) => formatCurrency(amount, shown)

  const rowColumns: DataTableColumn<GrantReportRow>[] = [
    { key: 'account_code', header: 'Account', accessor: (row) => row.account_code, sortable: true, render: (row) => `${row.account_code} — ${row.account_name}` },
    { key: 'account_name', header: 'Account name', accessor: (row) => row.account_name },
    { key: 'dea_code', header: 'DEA', accessor: (row) => row.dea_code ?? '', render: (row) => (row.dea_code ? `${row.dea_code}${row.dea_name ? ` — ${row.dea_name}` : ''}` : '—') },
    { key: 'budget_sof', header: `Budget (${sof})`, accessor: (row) => row.budget_sof, sortable: true, render: (row) => sofMoney(row.budget_sof) },
    { key: 'actual_sof', header: `Actual (${sof})`, accessor: (row) => row.actual_sof, sortable: true, render: (row) => <span className="font-medium">{sofMoney(row.actual_sof)}</span> },
    {
      key: 'variance_sof',
      header: `Variance (${sof})`,
      accessor: (row) => row.variance_sof,
      sortable: true,
      render: (row) => <span className={row.variance_sof < 0 ? 'text-danger' : 'text-success'}>{sofMoney(row.variance_sof)}</span>,
    },
    {
      key: 'utilization_percent',
      header: 'Used',
      accessor: (row) => row.utilization_percent ?? '',
      render: (row) => (row.utilization_percent != null ? <span className={row.utilization_percent > 100 ? 'font-medium text-danger' : ''}>{row.utilization_percent}%</span> : '—'),
    },
    { key: 'actual_base', header: `Actual (${shown})`, accessor: (row) => row.actual_base, sortable: true, render: (row) => shownMoney(row.actual_base) },
  ]
  const tableRowColumns = rowColumns.filter((column) => column.key !== 'account_name')

  const transactionColumns: DataTableColumn<GrantReportTransaction>[] = [
    { key: 'date', header: 'Date', accessor: (row) => row.date, sortable: true },
    { key: 'reference_number', header: 'Reference', accessor: (row) => row.reference_number, sortable: true },
    { key: 'account_code', header: 'Account', accessor: (row) => `${row.account_code} ${row.account_name}` },
    { key: 'dea_code', header: 'DEA', accessor: (row) => row.dea_code ?? '' },
    { key: 'project_code', header: 'Project', accessor: (row) => row.project_code ?? '' },
    { key: 'cost_centre_code', header: 'Cost Centre', accessor: (row) => row.cost_centre_code ?? '' },
    { key: 'description', header: 'Description', accessor: (row) => row.description ?? '' },
    { key: 'currency_code', header: 'Txn currency', accessor: (row) => row.currency_code },
    { key: 'transaction_amount', header: 'Txn amount', accessor: (row) => row.transaction_amount, render: (row) => formatCurrency(row.transaction_amount, row.currency_code) },
    { key: 'exchange_rate', header: `Rate (1 ${data?.base_currency ?? base} =)`, accessor: (row) => row.exchange_rate },
    { key: 'actual_base', header: `Amount (${shown})`, accessor: (row) => row.actual_base, render: (row) => shownMoney(row.actual_base) },
    { key: 'actual_sof', header: `Amount (${sof})`, accessor: (row) => row.actual_sof, render: (row) => <span className="font-medium">{sofMoney(row.actual_sof)}</span> },
    { key: 'is_income', header: 'Type', accessor: (row) => (row.is_income ? 'Income' : 'Spend') },
  ]

  const fileStem = `grant-report-${data?.grant.code ?? grantId}-${data?.from ?? ''}-to-${data?.to ?? ''}${data?.currency?.converted ? `-base-in-${shown}` : ''}`

  return (
    <div>
      <PageHeader
        parent="SOFs"
        title={data ? `Grant Report — ${data.grant.code}` : 'Grant Report'}
        action={
          <ExportCsvButton
            onExport={async () =>
              data &&
              (tab === 'accounts'
                ? exportToCsv(`${fileStem}.csv`, csvColumnsFromDataTable(rowColumns), data.rows)
                : exportToCsv(`${fileStem}-transactions.csv`, csvColumnsFromDataTable(transactionColumns), data.transactions))
            }
          />
        }
      />

      <Card className="mb-4">
        <CardContent className="flex flex-wrap items-end gap-4 p-4">
          <div className="space-y-1.5">
            <Label>From</Label>
            <Input type="date" value={from} onChange={(event) => setFrom(event.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label>To</Label>
            <Input type="date" value={to} onChange={(event) => setTo(event.target.value)} />
          </div>
          <ReportCurrencySelect value={showIn} onChange={setShowIn} />
          <p className="pb-2 text-sm text-muted-foreground">
            {data ? (
              <>
                <Link to={`/grants/${grantId}`} className="text-primary hover:underline">
                  {data.grant.name}
                </Link>
                {data.grant.donor ? ` · ${data.grant.donor}` : ''} · {formatDate(data.from)} – {formatDate(data.to)}. Leave the dates empty for the whole SOF
                period.
              </>
            ) : (
              'Leave the dates empty for the whole SOF period.'
            )}
          </p>
        </CardContent>
      </Card>

      <ConversionNotice currency={data?.currency} />
      {data?.currency?.converted && (
        <p className="-mt-2 mb-4 text-xs text-muted-foreground">Only the {shown} columns are converted. {sof} figures are the SOF's own and are never converted.</p>
      )}

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load this report. Please try again.</p>
      ) : isLoading || !data ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-muted-foreground">Loading…</p>
      ) : (
        <>
          {data.missing_rates.length > 0 && (
            <div className="mb-4 flex items-start gap-2 rounded-xl border border-warning/40 bg-warning-bg px-4 py-3 text-sm text-foreground">
              <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
              <p>
                No {sof} exchange rate exists on or before {data.missing_rates.join(', ')}, so those lines count as 0 {sof}. Add a {sof} rate under Exchange
                Rates to include them.
              </p>
            </div>
          )}

          <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label={`Total award (${sof})`} value={sofMoney(data.totals.award_sof)} subtext={`Budget lines: ${sofMoney(data.totals.budget_sof)}`} />
            <StatCard label={`Spent (${sof})`} value={sofMoney(data.totals.actual_sof)} subtext={`${shownMoney(data.totals.actual_base)} in ${shown}`} />
            <StatCard
              label={`Remaining (${sof})`}
              value={<span className={data.totals.remaining_award_sof < 0 ? 'text-danger' : ''}>{sofMoney(data.totals.remaining_award_sof)}</span>}
              subtext={`Budget-line variance ${sofMoney(data.totals.variance_sof)}`}
            />
            <StatCard
              label="Burn rate"
              value={data.totals.burn_percent != null ? `${data.totals.burn_percent}%` : '—'}
              subtext={`${data.totals.time_elapsed_percent}% of the SOF period elapsed`}
              className={data.totals.burn_percent != null && data.totals.burn_percent > 100 ? 'border-danger/40' : undefined}
            />
          </div>
          {(data.totals.income_sof !== 0 || data.totals.income_base !== 0) && (
            <p className="mb-4 text-sm text-muted-foreground">
              Income coded to this SOF: {sofMoney(data.totals.income_sof)} ({shownMoney(data.totals.income_base)}).
            </p>
          )}

          <Tabs value={tab} onValueChange={setTab}>
            <TabsList className="mb-4">
              <TabsTrigger value="accounts">Budget vs actual</TabsTrigger>
              <TabsTrigger value="transactions">Transactions ({data.transactions.length})</TabsTrigger>
            </TabsList>
            <TabsContent value="accounts">
              <DataTable
                columns={tableRowColumns}
                data={data.rows}
                rowKey={(row) => `${row.account_id}-${row.dea_id ?? 0}`}
                emptyTitle="Nothing budgeted or spent yet"
                emptySubtext="Add budget lines on the SOF, and code transactions to it."
              />
            </TabsContent>
            <TabsContent value="transactions">
              <DataTable
                columns={transactionColumns}
                data={data.transactions}
                rowKey={(row) => row.journal_entry_line_id}
                emptyTitle="No transactions coded to this SOF"
                emptySubtext="Code bill, invoice, purchase order or journal lines to it."
              />
            </TabsContent>
          </Tabs>
        </>
      )}
    </div>
  )
}
