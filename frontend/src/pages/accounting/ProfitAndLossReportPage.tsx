import { useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Camera, Download } from 'lucide-react'

import { downloadReport, fetchProfitAndLoss, saveReportSnapshot } from '@/api/reports'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatCurrency, startOfMonth, toISODate } from '@/lib/format'
import type { ReportAccountLine } from '@/types/accounting'

import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

function Section({ title, total, accounts }: { title: string; total: number; accounts: ReportAccountLine[] }) {
  return (
    <div className="mb-6">
      <p className="mb-2 text-sm font-semibold text-foreground">{title}</p>
      <table className="w-full text-sm">
        <tbody>
          {accounts.map((account) => (
            <tr key={account.account_id} className="border-b border-border last:border-b-0">
              <td className="py-1.5 text-muted-foreground">{account.code}</td>
              <td className="py-1.5 text-foreground">{account.name}</td>
              <td className="py-1.5 text-right text-foreground">{formatCurrency(account.amount)}</td>
            </tr>
          ))}
          {accounts.length === 0 && (
            <tr>
              <td colSpan={3} className="py-2 text-muted-foreground">
                No activity.
              </td>
            </tr>
          )}
        </tbody>
      </table>
      <div className="flex justify-end border-t border-border pt-1.5 text-sm font-semibold text-foreground">{formatCurrency(total)}</div>
    </div>
  )
}

export function ProfitAndLossReportPage() {
  const [from, setFrom] = useState(() => toISODate(startOfMonth()))
  const [to, setTo] = useState(() => toISODate(new Date()))
  const [downloading, setDownloading] = useState(false)

  const { data, isLoading, isError } = useQuery({ queryKey: ['profit-and-loss', from, to], queryFn: () => fetchProfitAndLoss({ from, to }) })

  const snapshotMutation = useMutation({
    mutationFn: () => saveReportSnapshot('profit-and-loss', { from, to }),
    onSuccess: () => toast.success('Snapshot saved'),
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const handleDownload = async () => {
    setDownloading(true)
    try {
      await downloadReport('profit-and-loss', from, to)
    } finally {
      setDownloading(false)
    }
  }

  return (
    <div>
      <PageHeader
        parent="Accounting Reports"
        title="Profit & Loss"
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
                <Section title="Revenue" total={data.revenue.total} accounts={data.revenue.accounts} />
                <Section title="Cost of Goods Sold" total={data.cogs.total} accounts={data.cogs.accounts} />
                <div className="mb-6 flex justify-between border-y border-border py-2 text-base font-bold text-foreground">
                  <span>Gross Profit</span>
                  <span>{formatCurrency(data.gross_profit)}</span>
                </div>
                <Section title="Operating Expenses" total={data.operating_expenses.total} accounts={data.operating_expenses.accounts} />
                <div className={`flex justify-between border-t-2 border-border pt-2 text-lg font-bold ${data.net_profit >= 0 ? 'text-success' : 'text-danger'}`}>
                  <span>Net Profit</span>
                  <span>{formatCurrency(data.net_profit)}</span>
                </div>
              </>
            )}
          </CardContent>
        </Card>
      )}
    </div>
  )
}
