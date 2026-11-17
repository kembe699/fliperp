import { useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Camera } from 'lucide-react'

import { fetchTrialBalance, saveReportSnapshot } from '@/api/reports'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatCurrency } from '@/lib/currency'
import { useBaseCurrency } from '@/hooks/use-base-currency'
import { ConversionNotice, ReportCurrencySelect } from '@/components/shared/ReportCurrency'
import { DimensionFilters } from '@/components/shared/CodingFields'
import type { DimensionFilterValue } from '@/lib/coding'
import { startOfMonth, toISODate } from '@/lib/format'

import { PageHeader } from '@/components/layout/PageHeader'
import { ExportMenu } from '@/components/shared/ExportMenu'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { FlipLoaderBlock } from '@/components/brand/FlipLoader'

export function TrialBalanceReportPage() {
  const baseCurrency = useBaseCurrency()
  const [showIn, setShowIn] = useState(baseCurrency)
  const currencyParam = showIn === baseCurrency ? undefined : showIn
  const [from, setFrom] = useState(() => toISODate(startOfMonth()))
  const [to, setTo] = useState(() => toISODate(new Date()))
  const [dimensions, setDimensions] = useState<DimensionFilterValue>({})

  const { data, isLoading, isError } = useQuery({ queryKey: ['trial-balance', from, to, showIn, dimensions], queryFn: () => fetchTrialBalance({ from, to, currency: currencyParam, ...dimensions }) })
  const currencyCode = data?.currency?.code

  const snapshotMutation = useMutation({
    mutationFn: () => saveReportSnapshot('trial-balance', { from, to }),
    onSuccess: () => toast.success('Snapshot saved'),
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  return (
    <div>
      <PageHeader
        parent="Accounting Reports"
        title="Trial Balance"
        action={
          <div className="flex gap-2">
            <Button variant="outline" disabled={snapshotMutation.isPending} onClick={() => snapshotMutation.mutate()}>
              <Camera className="h-4 w-4" />
              Save Snapshot
            </Button>
            <ExportMenu path="/reports/trial-balance/export" params={{ from, to, currency: currencyParam, ...dimensions }} fileName={`trial-balance-${from}-to-${to}`} />
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
      ) : (
        <Card>
          <CardContent className="p-8">
            {isLoading || !data ? (
              <FlipLoaderBlock />
            ) : (
              <>
                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                      <th className="py-2">Code</th>
                      <th className="py-2">Account</th>
                      <th className="py-2 text-right">Debit</th>
                      <th className="py-2 text-right">Credit</th>
                      <th className="py-2 text-right">Balance</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.accounts.map((account) => (
                      <tr key={account.account_id} className="border-b border-border last:border-b-0">
                        <td className="py-2 text-muted-foreground">{account.code}</td>
                        <td className="py-2 text-foreground">{account.name}</td>
                        <td className="py-2 text-right text-foreground">{account.debit > 0 ? formatCurrency(account.debit, currencyCode) : '—'}</td>
                        <td className="py-2 text-right text-foreground">{account.credit > 0 ? formatCurrency(account.credit, currencyCode) : '—'}</td>
                        <td className="py-2 text-right font-medium text-foreground">{formatCurrency(account.balance, currencyCode)}</td>
                      </tr>
                    ))}
                    {data.accounts.length === 0 && (
                      <tr>
                        <td colSpan={5} className="py-6 text-center text-muted-foreground">
                          No posted activity in this period.
                        </td>
                      </tr>
                    )}
                  </tbody>
                  <tfoot>
                    <tr className="border-t-2 border-border font-bold text-foreground">
                      <td className="py-2" colSpan={2}>
                        Total
                      </td>
                      <td className="py-2 text-right">{formatCurrency(data.total_debit, currencyCode)}</td>
                      <td className="py-2 text-right">{formatCurrency(data.total_credit, currencyCode)}</td>
                      <td className="py-2 text-right" />
                    </tr>
                  </tfoot>
                </table>
                {Math.round(data.total_debit * 100) !== Math.round(data.total_credit * 100) && (
                  <p className="mt-4 text-sm font-medium text-danger">Warning: totals do not balance.</p>
                )}
              </>
            )}
          </CardContent>
        </Card>
      )}
    </div>
  )
}
