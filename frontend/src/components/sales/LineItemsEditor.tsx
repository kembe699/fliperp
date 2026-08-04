import { Plus, Trash2 } from 'lucide-react'

import { formatCurrency } from '@/lib/currency'
import type { Product } from '@/types/product'
import type { PriceListItem, TaxRate } from '@/types/pos'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { SearchableSelect } from '@/components/shared/SearchableSelect'

export interface LineItemRow {
  key: string
  product_id: number | null
  quantity: number
  unit_price: number | null
  tax_rate_id: number | null
  discount_amount: number
}

export function lineRowTotal(row: LineItemRow): number {
  const gross = row.quantity * (row.unit_price ?? 0) - row.discount_amount
  return Math.round(gross * 100) / 100
}

interface LineItemsEditorProps {
  rows: LineItemRow[]
  onChange: (rows: LineItemRow[]) => void
  products: Product[]
  taxRates: TaxRate[]
  priceListItems?: PriceListItem[]
  disabled?: boolean
}

export function LineItemsEditor({ rows, onChange, products, taxRates, priceListItems, disabled }: LineItemsEditorProps) {
  const updateRow = (key: string, patch: Partial<LineItemRow>) => {
    onChange(rows.map((row) => (row.key === key ? { ...row, ...patch } : row)))
  }

  const removeRow = (key: string) => onChange(rows.filter((row) => row.key !== key))

  const addRow = () => {
    onChange([...rows, { key: crypto.randomUUID(), product_id: null, quantity: 1, unit_price: null, tax_rate_id: null, discount_amount: 0 }])
  }

  const handleProductChange = (key: string, productId: number | null) => {
    const product = products.find((p) => p.id === productId)
    const priceListPrice = priceListItems?.find((item) => item.product_id === productId && !item.product_variant_id)?.price
    const unitPrice = priceListPrice ?? (product ? Number(product.selling_price) : null)

    updateRow(key, {
      product_id: productId,
      unit_price: unitPrice,
      tax_rate_id: product?.tax_rate_id ?? null,
    })
  }

  return (
    <div className="space-y-3">
      <div className="rounded-xl border border-border">
        <div className="grid grid-cols-[1fr_80px_110px_130px_100px_110px_36px] gap-2 border-b border-border bg-[#F9FAFB] px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
          <span>Product</span>
          <span>Qty</span>
          <span>Unit Price</span>
          <span>Tax Rate</span>
          <span>Discount</span>
          <span className="text-right">Line Total</span>
          <span />
        </div>

        {rows.length === 0 ? (
          <p className="p-4 text-sm text-muted-foreground">No line items yet. Add a product below.</p>
        ) : (
          rows.map((row) => (
            <div key={row.key} className="grid grid-cols-[1fr_80px_110px_130px_100px_110px_36px] items-center gap-2 border-b border-border px-3 py-2 last:border-b-0">
              <SearchableSelect
                options={products.map((product) => ({ value: String(product.id), label: product.name, sublabel: product.sku }))}
                value={row.product_id ? String(row.product_id) : null}
                onChange={(value) => handleProductChange(row.key, value ? Number(value) : null)}
                placeholder="Select product"
                className="h-9"
              />
              <Input
                type="number"
                min="0.01"
                step="0.01"
                value={row.quantity}
                disabled={disabled}
                onChange={(event) => updateRow(row.key, { quantity: Number(event.target.value) })}
                className="h-9"
              />
              <Input
                type="number"
                min="0"
                step="0.01"
                value={row.unit_price ?? ''}
                disabled={disabled}
                onChange={(event) => updateRow(row.key, { unit_price: event.target.value ? Number(event.target.value) : null })}
                className="h-9"
              />
              <Select
                value={row.tax_rate_id ? String(row.tax_rate_id) : 'none'}
                onValueChange={(value) => updateRow(row.key, { tax_rate_id: value === 'none' ? null : Number(value) })}
                disabled={disabled}
              >
                <SelectTrigger className="h-9">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="none">No tax</SelectItem>
                  {taxRates.map((rate) => (
                    <SelectItem key={rate.id} value={String(rate.id)}>
                      {rate.name} ({Number(rate.rate)}%)
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Input
                type="number"
                min="0"
                step="0.01"
                value={row.discount_amount}
                disabled={disabled}
                onChange={(event) => updateRow(row.key, { discount_amount: Number(event.target.value) })}
                className="h-9"
              />
              <span className="text-right text-sm font-medium">{formatCurrency(lineRowTotal(row))}</span>
              <button
                type="button"
                onClick={() => removeRow(row.key)}
                disabled={disabled}
                className="flex h-8 w-8 items-center justify-center rounded-md text-muted-foreground hover:bg-danger-bg hover:text-danger disabled:opacity-40"
                aria-label="Remove line"
              >
                <Trash2 className="h-4 w-4" />
              </button>
            </div>
          ))
        )}
      </div>

      {!disabled && (
        <Button type="button" variant="outline" size="sm" onClick={addRow}>
          <Plus className="h-4 w-4" />
          Add Line
        </Button>
      )}
    </div>
  )
}
