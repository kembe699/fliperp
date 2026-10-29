import { useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Camera, Download } from 'lucide-react'

import { downloadReport, fetchBalanceSheet, saveReportSnapshot } from '@/api/reports'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatCurrency } from '@/lib/currency'
import { useBaseCurrency } from '@/hooks/use-base-currency'
import { ConversionNotice, ReportCurrencySelect } from '@/components/shared/ReportCurrency'
import { toISODate } from '@/lib/format'
import type { ReportAccountLine } from '@/types/accounting'

import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

function Section({ title, total, accounts, currencyCode }: { title: string; total: number; accounts: ReportAccountLine[]; currencyCode?: string }) {
  return (
    <div className="mb-6">
      <p className="mb-2 text-sm font-semibold text-foreground">{title}</p>
      <table className="w-full text-sm">
        <tbody>
          {accounts.map((account) => (
            <tr key={account.account_id} className="border-b border-border last:border-b-0">
              <td className="py-1.5 text-muted-foreground">{account.code}</td>
              <td className="py-1.5 text-foreground">{account.name}</td>
              <td className="py-1.5 text-right text-foreground">{formatCurrency(account.amount, currencyCode)}</td>
            </tr>
          ))}
          {accounts.length === 0 && (
            <tr>
              <td colSpan={3} className="py-2 text-muted-foreground">
                No balance.
              </td>
            </tr>
          )}
        </tbody>
      </table>
      <div className="flex justify-end border-t border-border pt-1.5 text-sm font-semibold text-foreground">{formatCurrency(total, currencyCode)}</div>
    </div>
  )
}

export function BalanceSheetReportPage() {
  const baseCurrency = useBaseCurrency()
  const [showIn, setShowIn] = useState(baseCurrency)
  const currencyParam = showIn === baseCurrency ? undefined : showIn
  const [asOf, setAsOf] = useState(() => toISODate(new Date()))
  const [downloading, setDownloading] = useState(false)

  const { data, isLoading, isError } = useQuery({ queryKey: ['balance-sheet', asOf, showIn], queryFn: () => fetchBalanceSheet({ as_of: asOf, currency: currencyParam }) })
  const currencyCode = data?.currency?.code

  const snapshotMutation = useMutation({
    mutationFn: () => saveReportSnapshot('balance-sheet', { as_of: asOf }),
    onSuccess: () => toast.success('Snapshot saved'),
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const handleDownload = async () => {
    setDownloading(true)
    try {
      await downloadReport('balance-sheet', asOf, asOf, currencyParam)
    } finally {
      setDownloading(false)
    }
  }

  return (
    <div>
      <PageHeader
        parent="Accounting Reports"
        title="Balance Sheet"
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
        <CardContent className="flex items-end gap-4 p-4">
          <div className="space-y-1.5">
            <Label>As Of</Label>
            <Input type="date" value={asOf} onChange={(event) => setAsOf(event.target.value)} />
          </div>
          <ReportCurrencySelect value={showIn} onChange={setShowIn} />
        </CardContent>
      </Card>

      <ConversionNotice currency={data?.currency} />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load this report. Please try again.</p>
      ) : (
        <Card>
          <CardContent className="p-8">
            {isLoading || !data ? (
              <p className="text-sm text-muted-foreground">Loading…</p>
            ) : (
              <>
                <Section title="Assets" total={data.assets.total} accounts={data.assets.accounts} currencyCode={currencyCode} />
                <Section title="Liabilities" total={data.liabilities.total} accounts={data.liabilities.accounts} currencyCode={currencyCode} />
                <div className="mb-6">
                  <p className="mb-2 text-sm font-semibold text-foreground">Equity</p>
                  <table className="w-full text-sm">
                    <tbody>
                      {data.equity.accounts.map((account) => (
                        <tr key={account.account_id} className="border-b border-border last:border-b-0">
                          <td className="py-1.5 text-muted-foreground">{account.code}</td>
                          <td className="py-1.5 text-foreground">{account.name}</td>
                          <td className="py-1.5 text-right text-foreground">{formatCurrency(account.amount, currencyCode)}</td>
                        </tr>
                      ))}
                      <tr className="border-b border-border">
                        <td className="py-1.5 text-muted-foreground">—</td>
                        <td className="py-1.5 text-foreground">Net Income (accumulated)</td>
                        <td className="py-1.5 text-right text-foreground">{formatCurrency(data.equity.net_income, currencyCode)}</td>
                      </tr>
                    </tbody>
                  </table>
                  <div className="flex justify-end border-t border-border pt-1.5 text-sm font-semibold text-foreground">{formatCurrency(data.equity.total, currencyCode)}</div>
                </div>
                <div className="flex justify-between border-t-2 border-border pt-2 text-base font-bold text-foreground">
                  <span>Total Liabilities + Equity</span>
                  <span>{formatCurrency(data.total_liabilities_and_equity, currencyCode)}</span>
                </div>
                {Math.round(data.assets.total * 100) !== Math.round(data.total_liabilities_and_equity * 100) && (
                  <p className="mt-4 text-sm font-medium text-danger">Warning: assets do not equal liabilities + equity.</p>
                )}
              </>
            )}
          </CardContent>
        </Card>
      )}
    </div>
  )
}
