import { useMemo, useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { AlertTriangle, CheckCircle2 } from 'lucide-react'

import { fetchPayables, fetchReceivables } from '@/api/reports'
import { formatCurrency } from '@/lib/currency'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { useBaseCurrency } from '@/hooks/use-base-currency'
import type { AgingBucket, AgingDocument, AgingParty, PayablesReport, ReceivablesReport } from '@/types/aging'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { StatCard } from '@/components/shared/StatCard'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs'

const BUCKETS: { key: AgingBucket; label: string }[] = [
  { key: 'd0_30', label: '0–30 days' },
  { key: 'd31_60', label: '31–60 days' },
  { key: 'd61_90', label: '61–90 days' },
  { key: 'd90_plus', label: '90+ days' },
]

const BUCKET_VARIANT: Record<AgingBucket, 'neutral' | 'info' | 'warning' | 'danger'> = {
  d0_30: 'neutral',
  d31_60: 'info',
  d61_90: 'warning',
  d90_plus: 'danger',
}

type Party = AgingParty & { id: number }

interface Props {
  kind: 'receivables' | 'payables'
}

/**
 * Accounts receivable or payable as at a date: what is owed, how overdue it
 * is, per customer/supplier and per document, and whether it agrees with the
 * ledger's control account. Base-currency figures throughout; each document
 * also shows its balance in its own currency.
 */
export function AgingReportView({ kind }: Props) {
  const base = useBaseCurrency()
  const isReceivables = kind === 'receivables'
  const partyLabel = isReceivables ? 'Customer' : 'Supplier'
  const documentLabel = isReceivables ? 'Invoice' : 'Bill'

  const [asOf, setAsOf] = useState(() => new Date().toISOString().slice(0, 10))
  const [tab, setTab] = useState('parties')
  const [partyFilter, setPartyFilter] = useState<Party | null>(null)

  const { data, isLoading, isError } = useQuery<ReceivablesReport | PayablesReport>({
    queryKey: ['aging', kind, asOf],
    queryFn: () => (isReceivables ? fetchReceivables(asOf) : fetchPayables(asOf)),
  })

  const parties: Party[] = useMemo(() => {
    if (!data) return []
    return 'customers' in data
      ? data.customers.map((c) => ({ ...c, id: c.customer_id }))
      : data.suppliers.map((s) => ({ ...s, id: s.supplier_id }))
  }, [data])

  const documents = useMemo(
    () => (data?.documents ?? []).filter((d) => !partyFilter || d.party_id === partyFilter.id),
    [data, partyFilter],
  )

  const money = (amount: number) => formatCurrency(amount, base)

  const partyColumns: DataTableColumn<Party>[] = [
    { key: 'name', header: partyLabel, accessor: (row) => row.name, sortable: true },
    { key: 'documents', header: `Open ${documentLabel}s`, accessor: (row) => row.documents, sortable: true },
    ...BUCKETS.map((b) => ({
      key: b.key,
      header: b.label,
      accessor: (row: Party) => row[b.key],
      render: (row: Party) => (row[b.key] ? money(row[b.key]) : '—'),
    })),
    { key: 'overdue', header: 'Overdue', accessor: (row) => row.overdue, sortable: true, render: (row) => (row.overdue ? <span className="text-danger">{money(row.overdue)}</span> : '—') },
    { key: 'outstanding', header: 'Balance', accessor: (row) => row.outstanding, sortable: true, render: (row) => <span className="font-semibold">{money(row.outstanding)}</span> },
  ]

  const documentColumns: DataTableColumn<AgingDocument>[] = [
    {
      key: 'reference_number',
      header: documentLabel,
      accessor: (row) => row.reference_number,
      sortable: true,
      render: (row) => (
        <Link className="font-medium text-primary hover:underline" to={isReceivables ? `/invoices/${row.id}` : `/supplier-bills/${row.id}`}>
          {row.reference_number}
        </Link>
      ),
    },
    { key: 'party_name', header: partyLabel, accessor: (row) => row.party_name, sortable: true },
    { key: 'date', header: 'Date', accessor: (row) => row.date, sortable: true },
    { key: 'due_date', header: 'Due', accessor: (row) => row.due_date, sortable: true },
    {
      key: 'days_overdue',
      header: 'Days overdue',
      accessor: (row) => row.days_overdue,
      sortable: true,
      render: (row) => (row.not_yet_due ? <span className="text-muted-foreground">Not yet due</span> : row.days_overdue),
    },
    {
      key: 'balance',
      header: 'Balance (own currency)',
      accessor: (row) => row.balance,
      render: (row) => formatCurrency(row.balance, row.currency_code ?? base),
    },
    { key: 'balance_base', header: `Balance (${base})`, accessor: (row) => row.balance_base, sortable: true, render: (row) => <span className="font-semibold">{money(row.balance_base)}</span> },
    {
      key: 'bucket',
      header: 'Age',
      accessor: (row) => BUCKETS.find((b) => b.key === row.bucket)?.label ?? row.bucket,
      render: (row) => <StatusBadge label={BUCKETS.find((b) => b.key === row.bucket)?.label ?? row.bucket} variant={BUCKET_VARIANT[row.bucket]} />,
    },
  ]

  const reconciled = data ? Math.abs(data.difference) < 0.005 : true

  return (
    <div>
      <PageHeader
        parent="Accounting"
        title={isReceivables ? 'Accounts Receivable' : 'Accounts Payable'}
        action={
          <ExportCsvButton
            onExport={async () =>
              tab === 'parties'
                ? exportToCsv(`${kind}-by-${partyLabel.toLowerCase()}-${asOf}.csv`, csvColumnsFromDataTable(partyColumns), parties)
                : exportToCsv(`${kind}-open-${documentLabel.toLowerCase()}s-${asOf}.csv`, csvColumnsFromDataTable(documentColumns), documents)
            }
          />
        }
      />

      <Card className="mb-4">
        <CardContent className="flex items-end gap-4 p-4">
          <div className="space-y-1.5">
            <Label>As of</Label>
            <Input type="date" value={asOf} onChange={(event) => setAsOf(event.target.value)} />
          </div>
          <p className="pb-2 text-sm text-muted-foreground">
            {isReceivables ? 'What customers owe' : 'What is owed to suppliers'} on this date, counting only payments made by then. Amounts in {base}.
          </p>
        </CardContent>
      </Card>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load this report. Please try again.</p>
      ) : isLoading || !data ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-muted-foreground">Loading…</p>
      ) : (
        <>
          <div className="mb-4 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <StatCard label={isReceivables ? 'Total receivable' : 'Total payable'} value={money(data.totals.outstanding)} subtext={`${data.documents.length} open ${documentLabel.toLowerCase()}(s)`} />
            <StatCard label="Overdue" value={<span className={data.totals.overdue > 0 ? 'text-danger' : ''}>{money(data.totals.overdue)}</span>} />
            <StatCard label="Not yet due" value={money(data.totals.not_yet_due)} />
            <StatCard
              label={`Ledger: ${data.control_account.code} ${data.control_account.name ?? ''}`}
              value={money(data.control_account.balance)}
              subtext={
                reconciled ? (
                  <span className="inline-flex items-center gap-1 text-success">
                    <CheckCircle2 className="h-3.5 w-3.5" /> Agrees with open {documentLabel.toLowerCase()}s
                  </span>
                ) : (
                  <span className="inline-flex items-center gap-1 text-danger">
                    <AlertTriangle className="h-3.5 w-3.5" /> Differs by {money(data.difference)}
                  </span>
                )
              }
            />
          </div>

          <div className="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
            {BUCKETS.map((b) => (
              <StatCard key={b.key} label={b.label} value={money(data.totals[b.key])} className={b.key === 'd90_plus' && data.totals.d90_plus > 0 ? 'border-danger/40' : undefined} />
            ))}
          </div>

          {!reconciled && (
            <div className="mb-4 flex items-start gap-2 rounded-xl border border-warning/40 bg-warning-bg px-4 py-3 text-sm text-foreground">
              <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" />
              <p>
                The ledger's {data.control_account.code} balance differs from the open {documentLabel.toLowerCase()}s by {money(data.difference)}. This means
                something was posted to {data.control_account.code} directly (e.g. a manual journal or opening balance), or a{' '}
                {documentLabel.toLowerCase()} has no ledger posting
                {isReceivables ? '' : ' — bills entered before bills posted to the ledger can be caught up by an administrator'}.
              </p>
            </div>
          )}

          <Tabs value={tab} onValueChange={setTab}>
            <TabsList className="mb-4">
              <TabsTrigger value="parties">By {partyLabel.toLowerCase()}</TabsTrigger>
              <TabsTrigger value="documents">Open {documentLabel.toLowerCase()}s</TabsTrigger>
            </TabsList>
            <TabsContent value="parties">
              <DataTable
                columns={partyColumns}
                data={parties}
                rowKey={(row) => row.id}
                rowActions={() => [
                  {
                    label: `View ${documentLabel.toLowerCase()}s`,
                    onClick: (row: Party) => {
                      setPartyFilter(row)
                      setTab('documents')
                    },
                  },
                ]}
                emptyTitle={`Nothing ${isReceivables ? 'receivable' : 'payable'}`}
                emptySubtext={`No open ${documentLabel.toLowerCase()}s on ${asOf}.`}
              />
            </TabsContent>
            <TabsContent value="documents">
              {partyFilter && (
                <div className="mb-3 flex items-center gap-2 text-sm">
                  <span className="text-muted-foreground">
                    Showing {partyFilter.name} — balance {money(partyFilter.outstanding)}
                  </span>
                  <Button variant="outline" size="sm" onClick={() => setPartyFilter(null)}>
                    Show all
                  </Button>
                </div>
              )}
              <DataTable
                columns={documentColumns}
                data={documents}
                rowKey={(row) => row.id}
                emptyTitle={`No open ${documentLabel.toLowerCase()}s`}
                emptySubtext={`Nothing outstanding on ${asOf}.`}
              />
            </TabsContent>
          </Tabs>
        </>
      )}
    </div>
  )
}
