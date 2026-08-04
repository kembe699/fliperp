import { Minus, Plus, ShoppingCart, Trash2 } from 'lucide-react'

import { formatCurrency } from '@/lib/format'
import type { Customer } from '@/types/customer'
import { cartSubtotal, cartTax, cartTotal, lineTotal, type CartLine } from '@/pages/pos/types'

import { Button } from '@/components/ui/button'
import { EmptyState } from '@/components/shared/EmptyState'
import { SearchableSelect } from '@/components/shared/SearchableSelect'

interface CartPanelProps {
  lines: CartLine[]
  onQuantityChange: (productId: number, quantity: number) => void
  onRemove: (productId: number) => void
  customers: Customer[]
  customerId: number | null
  onCustomerChange: (customerId: number | null) => void
  onHold: () => void
  onPay: () => void
  isBusy: boolean
  resumingReference: string | null
}

export function CartPanel({
  lines,
  onQuantityChange,
  onRemove,
  customers,
  customerId,
  onCustomerChange,
  onHold,
  onPay,
  isBusy,
  resumingReference,
}: CartPanelProps) {
  const subtotal = cartSubtotal(lines)
  const tax = cartTax(lines)
  const total = cartTotal(lines)

  return (
    <div className="flex h-full flex-col">
      <div className="mb-3">
        <p className="mb-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Customer</p>
        <SearchableSelect
          options={customers.map((customer) => ({ value: String(customer.id), label: customer.name, sublabel: customer.phone }))}
          value={customerId ? String(customerId) : null}
          onChange={(value) => onCustomerChange(value ? Number(value) : null)}
          placeholder="Walk-in customer"
          searchPlaceholder="Search customers…"
        />
      </div>

      {resumingReference && (
        <div className="mb-3 rounded-lg bg-info-bg px-3 py-2 text-xs font-medium text-info">
          Resuming held sale {resumingReference}
        </div>
      )}

      <div className="flex-1 overflow-y-auto rounded-xl border border-border bg-card">
        {lines.length === 0 ? (
          <EmptyState icon={ShoppingCart} title="Cart is empty" subtext="Tap a product to add it to the sale." />
        ) : (
          <ul className="divide-y divide-border">
            {lines.map((line) => (
              <li key={line.productId} className="flex items-center gap-2 p-3">
                <div className="min-w-0 flex-1">
                  <p className="truncate text-sm font-medium text-foreground">{line.productName}</p>
                  <p className="text-xs text-muted-foreground">{formatCurrency(line.unitPrice)} each</p>
                </div>
                <div className="flex items-center gap-1.5">
                  <button
                    type="button"
                    onClick={() => onQuantityChange(line.productId, Math.max(1, line.quantity - 1))}
                    className="flex h-7 w-7 items-center justify-center rounded-md border border-border hover:bg-accent"
                  >
                    <Minus className="h-3.5 w-3.5" />
                  </button>
                  <span className="w-6 text-center text-sm font-medium">{line.quantity}</span>
                  <button
                    type="button"
                    onClick={() => onQuantityChange(line.productId, line.quantity + 1)}
                    className="flex h-7 w-7 items-center justify-center rounded-md border border-border hover:bg-accent"
                  >
                    <Plus className="h-3.5 w-3.5" />
                  </button>
                </div>
                <span className="w-20 shrink-0 text-right text-sm font-semibold">{formatCurrency(lineTotal(line))}</span>
                <button
                  type="button"
                  onClick={() => onRemove(line.productId)}
                  className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-muted-foreground hover:bg-danger-bg hover:text-danger"
                  aria-label="Remove item"
                >
                  <Trash2 className="h-3.5 w-3.5" />
                </button>
              </li>
            ))}
          </ul>
        )}
      </div>

      <div className="mt-3 space-y-1.5 rounded-xl border border-border bg-card p-4">
        <div className="flex justify-between text-sm text-muted-foreground">
          <span>Subtotal</span>
          <span>{formatCurrency(subtotal)}</span>
        </div>
        <div className="flex justify-between text-sm text-muted-foreground">
          <span>Tax</span>
          <span>{formatCurrency(tax)}</span>
        </div>
        <div className="flex justify-between border-t border-border pt-1.5 text-base font-bold text-foreground">
          <span>Total</span>
          <span>{formatCurrency(total)}</span>
        </div>
      </div>

      <div className="mt-3 grid grid-cols-2 gap-2">
        <Button variant="outline" onClick={onHold} disabled={lines.length === 0 || isBusy}>
          Hold Sale
        </Button>
        <Button onClick={onPay} disabled={lines.length === 0 || isBusy}>
          Pay
        </Button>
      </div>
    </div>
  )
}
