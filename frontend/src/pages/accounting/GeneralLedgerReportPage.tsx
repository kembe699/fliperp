import { useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Camera, Download } from 'lucide-react'

import { downloadGeneralLedger, fetchGeneralLedgerAll, saveReportSnapshot, type GeneralLedgerAccount } from '@/api/reports'
import type { ReportCurrencyInfo } from '@/types/exchangeRate'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatCurrency } from '@/lib/currency'
import { useBaseCurrency } from '@/hooks/use-base-currency'
import { ConversionNotice, ReportCurrencySelect } from '@/components/shared/ReportCurrency'
import { DimensionFilters } from '@/components/shared/CodingFields'
import { codingSummary, type DimensionFilterValue } from '@/lib/coding'
import { startOfMonth, toISODate } from '@/lib/format'
import { sourceRecordLink } from '@/lib/journal-source-links'

import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export function GeneralLedgerReportPage() {
  const baseCurrency = useBaseCurrency()
  const [showIn, setShowIn] = useState(baseCurrency)
  const currencyParam = showIn === baseCurrency ? undefined : showIn
  const [from, setFrom] = useState(() => toISODate(startOfMonth()))
  const [to, setTo] = useState(() => toISODate(new Date()))
  const [downloading, setDownloading] = useState(false)
  const [dimensions, setDimensions] = useState<DimensionFilterValue>({})

  // Every account with a balance or activity in the period, each as its own ledger.
  const { data, isLoading, isError } = useQuery({
    queryKey: ['general-ledger', 'all', from, to, showIn, dimensions],
    queryFn: () => fetchGeneralLedgerAll({ from, to, currency: currencyParam, ...dimensions }),
  })

  const snapshotMutation = useMutation({
    mutationFn: () => saveReportSnapshot('general-ledger', { from, to }),
    onSuccess: () => toast.success('Snapshot saved'),
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const handleDownload = async () => {
    setDownloading(true)
    try {
      await downloadGeneralLedger(from, to, currencyParam, dimensions)
    } finally {
      setDownloading(false)
    }
  }

  return (
    <div>
      <PageHeader
        parent="Accounting Reports"
        title="General Ledger"
        action={
          <div className="flex gap-2">
            <Button variant="outline" disabled={snapshotMutation.isPending} onClick={() => snapshotMutation.mutate()}>
              <Camera className="h-4 w-4" />
              Save Snapshot
            </Button>
            <Button onClick={handleDownload} disabled={downloading}>
              <Download className="h-4 w-4" />
              {downloading ? 'Downloading…' : 'Download'}
            </Button>
          </div>
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
          <DimensionFilters value={dimensions} onChange={setDimensions} showDepartment />
        </CardContent>
      </Card>

      <ConversionNotice currency={data?.currency} />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load this report. Please try again.</p>
      ) : isLoading || !data ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-muted-foreground">Loading…</p>
      ) : data.accounts.length === 0 ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-muted-foreground">No ledger activity or balances for this period.</p>
      ) : (
        <div className="space-y-4">
          {data.accounts.map((ledger) => (
            <AccountLedger key={ledger.account.account_id} ledger={ledger} currency={data.currency} />
          ))}
        </div>
      )}
    </div>
  )
}

function AccountLedger({ ledger, currency }: { ledger: GeneralLedgerAccount; currency?: ReportCurrencyInfo }) {
  const currencyCode = currency?.code

  return (
    <Card>
      <CardContent className="p-6">
        <div className="mb-4 flex items-center justify-between">
          <div>
            <p className="text-base font-bold text-foreground">
              {ledger.account.code} — {ledger.account.name}
            </p>
            <p className="text-sm text-muted-foreground capitalize">{ledger.account.type}</p>
          </div>
          <div className="text-right">
            <p className="text-xs font-semibold uppercase text-muted-foreground">Opening Balance</p>
            <p className="text-sm font-medium text-foreground">{formatCurrency(ledger.opening_balance, currencyCode)}</p>
          </div>
        </div>

        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
              <th className="py-2">Date</th>
              <th className="py-2">Reference</th>
              <th className="py-2">Description</th>
              <th className="py-2">Source</th>
              <th className="py-2">Coding</th>
              <th className="py-2 text-right">As transacted</th>
              <th className="py-2 text-right">Debit</th>
              <th className="py-2 text-right">Credit</th>
              <th className="py-2 text-right">Balance</th>
            </tr>
          </thead>
          <tbody>
            {ledger.lines.map((line, index) => {
              const link = sourceRecordLink(line.source_module, line.source_id)
              return (
                <tr key={`${line.reference_number}-${index}`} className="border-b border-border last:border-b-0">
                  <td className="py-2 text-foreground">{line.date}</td>
                  <td className="py-2 text-foreground">{line.reference_number}</td>
                  <td className="py-2 text-muted-foreground">{line.description ?? '—'}</td>
                  <td className="py-2 text-muted-foreground">
                    {line.source_module ? (
                      link ? (
                        <a href={link} className="text-primary hover:underline">
                          {line.source_module} #{line.source_id}
                        </a>
                      ) : (
                        `${line.source_module}${line.source_id ? ` #${line.source_id}` : ''}`
                      )
                    ) : (
                      '—'
                    )}
                  </td>
                  <td className="py-2 text-xs text-muted-foreground">{codingSummary(line) ?? '—'}</td>
                  <td className="py-2 text-right text-xs text-muted-foreground">
                    {line.currency_code && line.currency_code !== currency?.base_currency
                      ? `${formatCurrency((line.transaction_debit ?? 0) - (line.transaction_credit ?? 0), line.currency_code)} @ ${line.exchange_rate}`
                      : '—'}
                  </td>
                  <td className="py-2 text-right text-foreground">{line.debit > 0 ? formatCurrency(line.debit, currencyCode) : '—'}</td>
                  <td className="py-2 text-right text-foreground">{line.credit > 0 ? formatCurrency(line.credit, currencyCode) : '—'}</td>
                  <td className="py-2 text-right font-medium text-foreground">{formatCurrency(line.balance, currencyCode)}</td>
                </tr>
              )
            })}
            {ledger.lines.length === 0 && (
              <tr>
                <td colSpan={9} className="py-4 text-center text-muted-foreground">
                  No activity in this period.
                </td>
              </tr>
            )}
          </tbody>
        </table>

        <div className="mt-3 flex justify-end border-t-2 border-border pt-2 text-sm font-bold text-foreground">
          <span className="mr-4">Closing Balance</span>
          <span>{formatCurrency(ledger.closing_balance, currencyCode)}</span>
        </div>
      </CardContent>
    </Card>
  )
}
