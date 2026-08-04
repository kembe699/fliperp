import { formatCurrency } from '@/lib/format'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

interface TotalsFooterProps {
  subtotal: number
  taxAmount: number
  discountAmount: number
  total: number
  onDiscountChange?: (value: number) => void
  disabled?: boolean
}

export function TotalsFooter({ subtotal, taxAmount, discountAmount, total, onDiscountChange, disabled }: TotalsFooterProps) {
  return (
    <div className="ml-auto w-full max-w-xs space-y-2 rounded-xl border border-border bg-card p-4">
      <div className="flex justify-between text-sm text-muted-foreground">
        <span>Subtotal</span>
        <span>{formatCurrency(subtotal)}</span>
      </div>
      <div className="flex justify-between text-sm text-muted-foreground">
        <span>Tax</span>
        <span>{formatCurrency(taxAmount)}</span>
      </div>
      <div className="flex items-center justify-between text-sm text-muted-foreground">
        <Label htmlFor="order-discount" className="text-sm font-normal text-muted-foreground">
          Discount
        </Label>
        {onDiscountChange ? (
          <Input
            id="order-discount"
            type="number"
            min="0"
            step="0.01"
            value={discountAmount}
            disabled={disabled}
            onChange={(event) => onDiscountChange(Number(event.target.value))}
            className="h-8 w-28 text-right"
          />
        ) : (
          <span>{formatCurrency(discountAmount)}</span>
        )}
      </div>
      <div className="flex justify-between border-t border-border pt-2 text-base font-bold text-foreground">
        <span>Total</span>
        <span>{formatCurrency(total)}</span>
      </div>
    </div>
  )
}
