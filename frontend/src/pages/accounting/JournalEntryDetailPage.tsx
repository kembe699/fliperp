import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { RotateCcw, Send } from 'lucide-react'

import { fetchJournalEntry, postJournalEntry, reverseJournalEntry } from '@/api/accounting'
import { formatCurrency, formatDate } from '@/lib/format'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import { sourceRecordLink } from '@/lib/journal-source-links'
import type { JournalEntryStatus } from '@/types/accounting'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { StatusBadge } from '@/components/shared/StatusBadge'

const STATUS_VARIANT: Record<JournalEntryStatus, 'neutral' | 'success' | 'danger'> = {
  draft: 'neutral',
  posted: 'success',
  reversed: 'danger',
}

export function JournalEntryDetailPage() {
  const { id } = useParams<{ id: string }>()
  const entryId = Number(id)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const { data: entry, isLoading } = useQuery({ queryKey: ['journal-entry', entryId], queryFn: () => fetchJournalEntry(entryId), enabled: !!entryId })

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['journal-entry', entryId] })
    queryClient.invalidateQueries({ queryKey: ['journal-entries'] })
  }

  const postMutation = useMutation({
    mutationFn: () => postJournalEntry(entryId),
    onSuccess: () => {
      toast.success('Journal entry posted')
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const reverseMutation = useMutation({
    mutationFn: () => reverseJournalEntry(entryId),
    onSuccess: (reversal) => {
      toast.success('Journal entry reversed')
      invalidate()
      navigate(`/journal-entries/${reversal.id}`)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  if (isLoading || !entry) {
    return <div className="p-6 text-sm text-muted-foreground">Loading journal entry…</div>
  }

  const totalDebit = entry.total_debit ?? entry.lines.reduce((sum, l) => sum + l.debit, 0)
  const totalCredit = entry.total_credit ?? entry.lines.reduce((sum, l) => sum + l.credit, 0)
  const isBalanced = Math.round(totalDebit * 100) === Math.round(totalCredit * 100)
  const canPost = can('journal-entries.post') && entry.status === 'draft'
  const canReverse = can('journal-entries.reverse') && entry.status === 'posted'
  const sourceLink = sourceRecordLink(entry.source_module, entry.source_id)

  return (
    <div>
      <PageHeader
        parent="Journal Entries"
        title={entry.reference_number}
        action={
          <div className="flex gap-2">
            {canPost && (
              <Button disabled={!isBalanced || postMutation.isPending} onClick={() => postMutation.mutate()}>
                <Send className="h-4 w-4" />
                Post
              </Button>
            )}
            {canReverse && (
              <Button variant="outline" disabled={reverseMutation.isPending} onClick={() => reverseMutation.mutate()}>
                <RotateCcw className="h-4 w-4" />
                Reverse
              </Button>
            )}
          </div>
        }
      />

      <Card>
        <CardContent className="p-8">
          <div className="mb-8 flex items-start justify-between">
            <div className="grid grid-cols-2 gap-6 sm:grid-cols-4">
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">Date</p>
                <p className="mt-1 text-sm font-medium text-foreground">{formatDate(entry.entry_date)}</p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">Description</p>
                <p className="mt-1 text-sm text-foreground">{entry.description ?? '—'}</p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">Source</p>
                <p className="mt-1 text-sm text-foreground">
                  {entry.source_module ? (
                    sourceLink ? (
                      <a href={sourceLink} className="text-primary hover:underline">
                        {entry.source_module} #{entry.source_id}
                      </a>
                    ) : (
                      `${entry.source_module}${entry.source_id ? ` #${entry.source_id}` : ''}`
                    )
                  ) : (
                    '—'
                  )}
                </p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">Balance Check</p>
                <p className={`mt-1 text-sm font-semibold ${isBalanced ? 'text-success' : 'text-danger'}`}>{isBalanced ? 'Balanced' : 'Not Balanced'}</p>
              </div>
            </div>
            <StatusBadge label={entry.status} variant={STATUS_VARIANT[entry.status]} className="text-sm" />
          </div>

          <table className="mb-6 w-full text-sm">
            <thead>
              <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                <th className="py-2">Account</th>
                <th className="py-2">Memo</th>
                <th className="py-2 text-right">Debit</th>
                <th className="py-2 text-right">Credit</th>
              </tr>
            </thead>
            <tbody>
              {entry.lines.map((line) => (
                <tr key={line.id} className="border-b border-border last:border-b-0">
                  <td className="py-2 text-foreground">
                    {line.account_code} — {line.account_name}
                  </td>
                  <td className="py-2 text-muted-foreground">{line.description ?? '—'}</td>
                  <td className="py-2 text-right text-foreground">{line.debit > 0 ? formatCurrency(line.debit) : '—'}</td>
                  <td className="py-2 text-right text-foreground">{line.credit > 0 ? formatCurrency(line.credit) : '—'}</td>
                </tr>
              ))}
            </tbody>
          </table>

          <div className="ml-auto w-full max-w-xs space-y-1.5 border-t border-border pt-3">
            <div className="flex justify-between text-sm font-semibold text-foreground">
              <span>Total Debit</span>
              <span>{formatCurrency(totalDebit)}</span>
            </div>
            <div className="flex justify-between text-sm font-semibold text-foreground">
              <span>Total Credit</span>
              <span>{formatCurrency(totalCredit)}</span>
            </div>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
