import { useState } from 'react'
import { useMutation, useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Camera, Download } from 'lucide-react'

import { downloadGeneralLedger, fetchChartOfAccounts, fetchGeneralLedger, saveReportSnapshot } from '@/api/reports'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatCurrency } from '@/lib/currency'
import { startOfMonth, toISODate } from '@/lib/format'
import { sourceRecordLink } from '@/lib/journal-source-links'

import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { SearchableSelect } from '@/components/shared/SearchableSelect'

export function GeneralLedgerReportPage() {
  const [accountId, setAccountId] = useState<string | null>(null)
  const [from, setFrom] = useState(() => toISODate(startOfMonth()))
  const [to, setTo] = useState(() => toISODate(new Date()))
  const [downloading, setDownloading] = useState(false)

  const { data: accounts } = useQuery({ queryKey: ['chart-of-accounts'], queryFn: fetchChartOfAccounts })
  const { data, isLoading, isError } = useQuery({
    queryKey: ['general-ledger', accountId, from, to],
    queryFn: () => fetchGeneralLedger({ account_id: Number(accountId), from, to }),
    enabled: !!accountId,
  })

  const snapshotMutation = useMutation({
    mutationFn: () => saveReportSnapshot('general-ledger', { account_id: Number(accountId), from, to }),
    onSuccess: () => toast.success('Snapshot saved'),
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const handleDownload = async () => {
    if (!accountId) return
    setDownloading(true)
    try {
      await downloadGeneralLedger(Number(accountId), from, to)
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
            <Button variant="outline" disabled={!accountId || snapshotMutation.isPending} onClick={() => snapshotMutation.mutate()}>
              <Camera className="h-4 w-4" />
              Save Snapshot
            </Button>
            <Button onClick={handleDownload} disabled={!accountId || downloading}>
              <Download className="h-4 w-4" />
              {downloading ? 'Downloading…' : 'Download'}
            </Button>
          </div>
        }
      />

      <Card className="mb-4">
        <CardContent className="flex items-end gap-4 p-4">
          <div className="w-64 space-y-1.5">
            <Label>Account</Label>
            <SearchableSelect
              options={(accounts ?? []).map((account) => ({ value: String(account.id), label: `${account.code} — ${account.name}` }))}
              value={accountId}
              onChange={setAccountId}
              placeholder="Select account"
            />
          </div>
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

      {!accountId ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-muted-foreground">Select an account to view its ledger.</p>
      ) : isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load this report. Please try again.</p>
      ) : (
        <Card>
          <CardContent className="p-8">
            {isLoading || !data ? (
              <p className="text-sm text-muted-foreground">Loading…</p>
            ) : (
              <>
                <div className="mb-6 flex items-center justify-between">
                  <div>
                    <p className="text-lg font-bold text-foreground">
                      {data.account.code} — {data.account.name}
                    </p>
                    <p className="text-sm text-muted-foreground capitalize">{data.account.type}</p>
                  </div>
                  <div className="text-right">
                    <p className="text-xs font-semibold uppercase text-muted-foreground">Opening Balance</p>
                    <p className="text-sm font-medium text-foreground">{formatCurrency(data.opening_balance)}</p>
                  </div>
                </div>

                <table className="w-full text-sm">
                  <thead>
                    <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                      <th className="py-2">Date</th>
                      <th className="py-2">Reference</th>
                      <th className="py-2">Description</th>
                      <th className="py-2">Source</th>
                      <th className="py-2 text-right">Debit</th>
                      <th className="py-2 text-right">Credit</th>
                      <th className="py-2 text-right">Balance</th>
                    </tr>
                  </thead>
                  <tbody>
                    {data.lines.map((line, index) => {
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
                          <td className="py-2 text-right text-foreground">{line.debit > 0 ? formatCurrency(line.debit) : '—'}</td>
                          <td className="py-2 text-right text-foreground">{line.credit > 0 ? formatCurrency(line.credit) : '—'}</td>
                          <td className="py-2 text-right font-medium text-foreground">{formatCurrency(line.balance)}</td>
                        </tr>
                      )
                    })}
                    {data.lines.length === 0 && (
                      <tr>
                        <td colSpan={7} className="py-6 text-center text-muted-foreground">
                          No activity in this period.
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>

                <div className="mt-4 flex justify-end border-t-2 border-border pt-2 text-base font-bold text-foreground">
                  <span className="mr-4">Closing Balance</span>
                  <span>{formatCurrency(data.closing_balance)}</span>
                </div>
              </>
            )}
          </CardContent>
        </Card>
      )}
    </div>
  )
}
