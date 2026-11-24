import { useEffect, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { updateSystemAccounts } from '@/api/accounting'
import { getApiErrorInfo } from '@/lib/api-errors'
import { useSystemAccounts } from '@/hooks/use-system-accounts'
import type { ChartOfAccount, SystemAccountFunction } from '@/types/accounting'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

/**
 * Which account each automatic posting uses: payments come out of the cash
 * account, salaries post to the salaries expense account, and so on. Only
 * active posting accounts (not headings) can be chosen.
 */
export function SystemAccountsDialog({
  open,
  onOpenChange,
  accounts,
  canEdit,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  accounts: ChartOfAccount[]
  canEdit: boolean
}) {
  const queryClient = useQueryClient()
  const { data: rows = [], isLoading } = useSystemAccounts()
  const [choice, setChoice] = useState<Partial<Record<SystemAccountFunction, string>>>({})

  useEffect(() => {
    if (open) setChoice(Object.fromEntries(rows.map((row) => [row.function, row.account ? String(row.account.id) : ''])))
  }, [open, rows])

  const postable = [...accounts].filter((a) => a.is_active && a.is_postable !== false).sort((a, b) => a.code.localeCompare(b.code))

  const save = useMutation({
    mutationFn: () =>
      updateSystemAccounts(
        Object.fromEntries(Object.entries(choice).filter(([, id]) => id).map(([fn, id]) => [fn, Number(id)])) as Partial<Record<SystemAccountFunction, number>>,
      ),
    onSuccess: () => {
      toast.success('System accounts saved')
      queryClient.invalidateQueries({ queryKey: ['system-accounts'] })
      onOpenChange(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-h-[90vh] max-w-2xl overflow-y-auto">
        <DialogHeader>
          <DialogTitle>System accounts</DialogTitle>
          <DialogDescription>The accounts the system posts to by itself when you pay, invoice, run payroll or close a till.</DialogDescription>
        </DialogHeader>
        {isLoading ? (
          <p className="text-sm text-muted-foreground">Loading…</p>
        ) : (
          <div className="grid gap-3">
            {rows.map((row) => (
              <div key={row.function} className="grid gap-1.5 sm:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)] sm:items-center sm:gap-3">
                <Label htmlFor={`sa-${row.function}`}>{row.label}</Label>
                <Select disabled={!canEdit} value={choice[row.function] ?? ''} onValueChange={(value) => setChoice((current) => ({ ...current, [row.function]: value }))}>
                  <SelectTrigger id={`sa-${row.function}`}>
                    <SelectValue placeholder="Not set" />
                  </SelectTrigger>
                  <SelectContent>
                    {postable.map((account) => (
                      <SelectItem key={account.id} value={String(account.id)}>
                        {account.code} — {account.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
            ))}
          </div>
        )}
        {canEdit && (
          <DialogFooter>
            <Button variant="outline" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button disabled={save.isPending} onClick={() => save.mutate()}>
              Save
            </Button>
          </DialogFooter>
        )}
      </DialogContent>
    </Dialog>
  )
}
