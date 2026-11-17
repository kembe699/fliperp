import { useEffect, useState } from 'react'
import { Banknote, Clock, CreditCard, Landmark, Plus, Smartphone, Trash2, Wallet, type LucideIcon } from 'lucide-react'

import { formatCurrency } from '@/lib/currency'
import { round2 } from '@/pages/pos/types'
import type { PaymentType } from '@/types/pos'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { cn } from '@/lib/utils'

export interface PaymentRow {
  key: string
  paymentTypeId: number | null
  amount: string
}

interface PaymentDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  totalDue: number
  paymentTypes: PaymentType[]
  allowPartial: boolean
  isSubmitting: boolean
  onComplete: (rows: { paymentTypeId: number; amount: number }[]) => void
}

const METHOD_ICON: Record<string, LucideIcon> = {
  cash: Banknote,
  card: CreditCard,
  mobile_money: Smartphone,
  bank_transfer: Landmark,
  credit: Clock,
}

/** Cash is the default way to pay; otherwise the first method that isn't Credit. */
function defaultPaymentTypeId(paymentTypes: PaymentType[]): number | null {
  return (paymentTypes.find((t) => t.type === 'cash') ?? paymentTypes.find((t) => t.type !== 'credit') ?? paymentTypes[0])?.id ?? null
}

export function PaymentDialog({ open, onOpenChange, totalDue, paymentTypes: allPaymentTypes, allowPartial, isSubmitting, onComplete }: PaymentDialogProps) {
  // Cash first (the usual way to pay), Credit last, the rest in between.
  const ORDER: Record<string, number> = { cash: 0, mobile_money: 1, card: 2, bank_transfer: 3, credit: 9 }
  const paymentTypes = allPaymentTypes
    .filter((t) => t.is_active !== false)
    .sort((a, b) => (ORDER[a.type] ?? 5) - (ORDER[b.type] ?? 5))
  const [rows, setRows] = useState<PaymentRow[]>([])

  useEffect(() => {
    if (open) {
      setRows([{ key: crypto.randomUUID(), paymentTypeId: defaultPaymentTypeId(paymentTypes), amount: totalDue.toFixed(2) }])
    }
    // paymentTypes is derived from allPaymentTypes on every render; depend on the source.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, totalDue, allPaymentTypes])

  const paidSoFar = round2(rows.reduce((sum, row) => sum + (Number(row.amount) || 0), 0))
  const remaining = round2(totalDue - paidSoFar)
  const canComplete = rows.every((row) => row.paymentTypeId && Number(row.amount) > 0) && (allowPartial ? paidSoFar > 0 : remaining === 0)

  const addRow = () => {
    setRows((prev) => [
      ...prev,
      { key: crypto.randomUUID(), paymentTypeId: defaultPaymentTypeId(paymentTypes), amount: Math.max(remaining, 0).toFixed(2) },
    ])
  }

  const removeRow = (key: string) => setRows((prev) => prev.filter((row) => row.key !== key))

  const updateRow = (key: string, patch: Partial<PaymentRow>) =>
    setRows((prev) => prev.map((row) => (row.key === key ? { ...row, ...patch } : row)))

  const complete = () =>
    onComplete(
      rows
        .filter((row) => row.paymentTypeId && Number(row.amount) > 0)
        .map((row) => ({ paymentTypeId: row.paymentTypeId!, amount: Number(row.amount) })),
    )

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-xl">
        <DialogHeader>
          <DialogTitle>Take Payment</DialogTitle>
          <DialogDescription>Choose how the customer is paying. Split it across methods if needed.</DialogDescription>
        </DialogHeader>

        <div className="flex items-center justify-between rounded-xl border border-border bg-muted/40 px-5 py-4">
          <span className="text-sm font-medium text-muted-foreground">Total due</span>
          <span className="text-2xl font-bold tabular-nums text-foreground">{formatCurrency(totalDue)}</span>
        </div>

        <div className="space-y-3">
          {rows.map((row, index) => (
            <div key={row.key} className="rounded-xl border border-border p-4">
              <div className="mb-3 flex items-center justify-between gap-2">
                <p className="text-sm font-semibold text-foreground">{rows.length > 1 ? `Payment ${index + 1}` : 'Payment method'}</p>
                {rows.length > 1 && (
                  <button
                    type="button"
                    onClick={() => removeRow(row.key)}
                    className="flex h-8 w-8 items-center justify-center rounded-full text-muted-foreground hover:bg-danger-bg hover:text-danger"
                    aria-label={`Remove payment ${index + 1}`}
                  >
                    <Trash2 className="h-4 w-4" />
                  </button>
                )}
              </div>

              <div className="flex flex-wrap gap-2" role="radiogroup" aria-label="Payment method">
                {paymentTypes.map((paymentType) => {
                  const Icon = METHOD_ICON[paymentType.type] ?? Wallet
                  const selected = row.paymentTypeId === paymentType.id
                  return (
                    <button
                      key={paymentType.id}
                      type="button"
                      role="radio"
                      aria-checked={selected}
                      onClick={() => updateRow(row.key, { paymentTypeId: paymentType.id })}
                      className={cn(
                        'inline-flex min-h-10 items-center gap-2 rounded-lg border px-3.5 text-sm font-medium transition-colors',
                        selected ? 'border-primary bg-primary text-primary-foreground' : 'border-border bg-card text-foreground hover:border-primary/50',
                      )}
                    >
                      <Icon className="h-4 w-4" />
                      {paymentType.name}
                    </button>
                  )
                })}
              </div>

              <div className="mt-4 space-y-1.5">
                <Label htmlFor={`amount-${row.key}`}>Amount</Label>
                <Input
                  id={`amount-${row.key}`}
                  type="number"
                  inputMode="decimal"
                  step="0.01"
                  min="0"
                  className="h-11 text-base tabular-nums"
                  value={row.amount}
                  onChange={(event) => updateRow(row.key, { amount: event.target.value })}
                />
              </div>
            </div>
          ))}

          <Button type="button" variant="outline" className="w-full border-dashed" onClick={addRow}>
            <Plus className="h-4 w-4" />
            Add split payment
          </Button>
        </div>

        <div className="space-y-2 rounded-xl bg-muted/50 px-5 py-4 text-sm">
          <div className="flex items-center justify-between">
            <span className="text-muted-foreground">Paying now</span>
            <span className="font-medium tabular-nums text-foreground">{formatCurrency(paidSoFar)}</span>
          </div>
          <div className="flex items-center justify-between border-t border-border pt-2">
            <span className="font-medium text-foreground">Remaining</span>
            <span className={cn('text-base font-semibold tabular-nums', remaining > 0 ? 'text-danger' : 'text-success')}>{formatCurrency(remaining)}</span>
          </div>
          {remaining < 0 && <p className="text-xs text-danger">The payments add up to more than the total. Reduce an amount.</p>}
          {allowPartial && remaining > 0 && (
            <p className="text-xs text-muted-foreground">This customer has credit terms — the remaining balance will be tracked as receivable.</p>
          )}
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={isSubmitting}>
            Cancel
          </Button>
          <Button className="sm:min-w-40" disabled={!canComplete || isSubmitting} onClick={complete}>
            {isSubmitting ? 'Completing…' : 'Complete Sale'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
