import { useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Camera, Download } from 'lucide-react'

import { downloadReport, fetchCashFlow, saveReportSnapshot } from '@/api/reports'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatCurrency } from '@/lib/currency'
import { startOfMonth, toISODate } from '@/lib/format'

import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const CATEGORY_LABEL: Record<string, string> = {
  sales: 'POS Sales',
  customer_payments: 'Customer Payments',
  supplier_payments: 'Supplier Payments',
  payroll_runs: 'Payroll',
}

export function CashFlowReportPage() {
  const [from, setFrom] = useState(() => toISODate(startOfMonth()))
  const [to, setTo] = useState(() => toISODate(new Date()))
  const [downloading, setDownloading] = useState(false)

  const { data, isLoading, isError } = useQuery({ queryKey: ['cash-flow', from, to], queryFn: () => fetchCashFlow({ from, to }) })

  const snapshotMutation = useMutation({
    mutationFn: () => saveReportSnapshot('cash-flow', { from, to }),
    onSuccess: () => toast.success('Snapshot saved'),
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const handleDownload = async () => {
    setDownloading(true)
    try {
      await downloadReport('cash-flow', from, to)
    } finally {
      setDownloading(false)
    }
  }

  return (
    <div>
      <PageHeader
        parent="Accounting Reports"
        title="Cash Flow"
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
                      <th className="py-2">Category</th>
                      <th className="py-2 text-right">Cash In</th>
                      <th className="py-2 text-right">Cash Out</th>
                      <th className="py-2 text-right">Net</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.categories.map((category) => (
                      <tr key={category.source_module} className="border-b border-border last:border-b-0">
                        <td className="py-2 text-foreground">{CATEGORY_LABEL[category.source_module] ?? category.source_module}</td>
                        <td className="py-2 text-right text-success">{formatCurrency(category.cash_in)}</td>
                        <td className="py-2 text-right text-danger">{formatCurrency(category.cash_out)}</td>
                        <td className={`py-2 text-right font-medium ${category.net >= 0 ? 'text-success' : 'text-danger'}`}>{formatCurrency(category.net)}</td>
                      </tr>
                    ))}
                  </tbody>
                </table>
                <div className="mt-4 flex justify-between border-t-2 border-border pt-2 text-base font-bold text-foreground">
                  <span>Net Cash Movement</span>
                  <span className={data.net_cash_movement >= 0 ? 'text-success' : 'text-danger'}>{formatCurrency(data.net_cash_movement)}</span>
                </div>
              </>
            )}
          </CardContent>
        </Card>
      )}
    </div>
  )
}
