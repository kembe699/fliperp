import { useMemo, useState } from 'react'
import { Plus, Trash2 } from 'lucide-react'

import { formatCurrency } from '@/lib/currency'
import type { Product } from '@/types/product'
import type { PriceListItem, TaxRate } from '@/types/pos'
import type { CrmService } from '@/types/crm'

import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { SearchableSelect } from '@/components/shared/SearchableSelect'

const PRODUCT_PREFIX = 'product:'
const SERVICE_PREFIX = 'service:'

export interface LineItemRow {
  key: string
  product_id: number | null
  description: string
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
  // Optional — pages without CRM access simply omit these and the picker only offers
  // products, same as before.
  services?: CrmService[]
  onResolveService?: (serviceId: number) => Promise<Product>
}

export function LineItemsEditor({ rows, onChange, products, taxRates, priceListItems, disabled, services, onResolveService }: LineItemsEditorProps) {
  // A service picked here doesn't become a real Product until onResolveService creates its
  // shadow product (see CrmService::ensureProduct) — kept locally so the row can show it as
  // selected immediately, without waiting for the parent's product list to refetch.
  const [resolvedProducts, setResolvedProducts] = useState<Product[]>([])
  const [resolvingKey, setResolvingKey] = useState<string | null>(null)

  const allProducts = useMemo(() => {
    const byId = new Map(products.map((product) => [product.id, product]))
    resolvedProducts.forEach((product) => byId.set(product.id, product))
    return Array.from(byId.values())
  }, [products, resolvedProducts])

  const pickerOptions = useMemo(
    () => [
      ...allProducts.map((product) => ({ value: `${PRODUCT_PREFIX}${product.id}`, label: product.name, sublabel: product.sku })),
      ...(services ?? []).map((service) => ({ value: `${SERVICE_PREFIX}${service.id}`, label: service.name, sublabel: 'Service' })),
    ],
    [allProducts, services],
  )

  const updateRow = (key: string, patch: Partial<LineItemRow>) => {
    onChange(rows.map((row) => (row.key === key ? { ...row, ...patch } : row)))
  }

  const removeRow = (key: string) => onChange(rows.filter((row) => row.key !== key))

  const addRow = () => {
    onChange([...rows, { key: crypto.randomUUID(), product_id: null, description: '', quantity: 1, unit_price: null, tax_rate_id: null, discount_amount: 0 }])
  }

  const applyProduct = (key: string, productId: number | null) => {
    const product = allProducts.find((p) => p.id === productId)
    const priceListPrice = priceListItems?.find((item) => item.product_id === productId && !item.product_variant_id)?.price
    const unitPrice = priceListPrice ?? (product ? Number(product.selling_price) : null)
    // Suggest the product/service's own description as a starting point, but never
    // overwrite something the user already typed for this line.
    const existingDescription = rows.find((row) => row.key === key)?.description
    const description = existingDescription || product?.description || ''

    updateRow(key, {
      product_id: productId,
      unit_price: unitPrice,
      tax_rate_id: product?.tax_rate_id ?? null,
      description,
    })
  }

  const handlePickOption = async (key: string, optionValue: string | null) => {
    if (!optionValue) {
      applyProduct(key, null)
      return
    }
    if (optionValue.startsWith(SERVICE_PREFIX)) {
      if (!onResolveService) return
      const serviceId = Number(optionValue.slice(SERVICE_PREFIX.length))
      setResolvingKey(key)
      try {
        const product = await onResolveService(serviceId)
        setResolvedProducts((prev) => (prev.some((p) => p.id === product.id) ? prev : [...prev, product]))
        applyProduct(key, product.id)
      } finally {
        setResolvingKey(null)
      }
      return
    }
    applyProduct(key, Number(optionValue.slice(PRODUCT_PREFIX.length)))
  }

  return (
    <div className="space-y-3">
      <div className="rounded-xl border border-border">
        <div className="grid grid-cols-[1fr_80px_110px_130px_100px_110px_36px] gap-2 border-b border-border bg-[#F9FAFB] px-3 py-2 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
          <span>Product / Service</span>
          <span>Qty</span>
          <span>Unit Price</span>
          <span>Tax Rate</span>
          <span>Discount</span>
          <span className="text-right">Line Total</span>
          <span />
        </div>

        {rows.length === 0 ? (
          <p className="p-4 text-sm text-muted-foreground">No line items yet. Add a product or service below.</p>
        ) : (
          rows.map((row) => (
            <div key={row.key} className="grid grid-cols-[1fr_80px_110px_130px_100px_110px_36px] items-center gap-2 border-b border-border px-3 py-2 last:border-b-0">
              <div className="space-y-1">
                <SearchableSelect
                  options={pickerOptions}
                  value={row.product_id ? `${PRODUCT_PREFIX}${row.product_id}` : null}
                  onChange={(value) => handlePickOption(row.key, value)}
                  placeholder={resolvingKey === row.key ? 'Adding service…' : 'Select product or service'}
                  searchPlaceholder="Search products and services…"
                  disabled={disabled || resolvingKey === row.key}
                  className="h-9"
                />
                <Input
                  value={row.description}
                  disabled={disabled}
                  onChange={(event) => updateRow(row.key, { description: event.target.value })}
                  placeholder="Short description (optional)"
                  className="h-7 text-xs text-muted-foreground"
                />
              </div>
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
