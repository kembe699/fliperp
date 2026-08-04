import { useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Camera, Download } from 'lucide-react'

import { downloadReport, fetchTrialBalance, saveReportSnapshot } from '@/api/reports'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatCurrency } from '@/lib/currency'
import { startOfMonth, toISODate } from '@/lib/format'

import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export function TrialBalanceReportPage() {
  const [from, setFrom] = useState(() => toISODate(startOfMonth()))
  const [to, setTo] = useState(() => toISODate(new Date()))
  const [downloading, setDownloading] = useState(false)

  const { data, isLoading, isError } = useQuery({ queryKey: ['trial-balance', from, to], queryFn: () => fetchTrialBalance({ from, to }) })

  const snapshotMutation = useMutation({
    mutationFn: () => saveReportSnapshot('trial-balance', { from, to }),
    onSuccess: () => toast.success('Snapshot saved'),
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const handleDownload = async () => {
    setDownloading(true)
    try {
      await downloadReport('trial-balance', from, to)
    } finally {
      setDownloading(false)
    }
  }

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
            <Label>From</Label>
            <Input type="date" value={from} onChange={(event) => setFrom(event.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label>To</Label>
            <Input type="date" value={to} onChange={(event) => setTo(event.target.value)} />
          </div>
        </CardContent>
      </Card>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load this report. Please try again.</p>
      ) : (
        <Card>
          <CardContent className="p-8">
            {isLoading || !data ? (
              <p className="text-sm text-muted-foreground">Loading…</p>
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
                        <td className="py-2 text-right text-foreground">{account.debit > 0 ? formatCurrency(account.debit) : '—'}</td>
                        <td className="py-2 text-right text-foreground">{account.credit > 0 ? formatCurrency(account.credit) : '—'}</td>
                        <td className="py-2 text-right font-medium text-foreground">{formatCurrency(account.balance)}</td>
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
                      <td className="py-2 text-right">{formatCurrency(data.total_debit)}</td>
                      <td className="py-2 text-right">{formatCurrency(data.total_credit)}</td>
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
