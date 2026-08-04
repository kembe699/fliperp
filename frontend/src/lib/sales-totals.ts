import type { LineItemRow } from '@/components/sales/LineItemsEditor'

export function computeTotals(rows: LineItemRow[], taxRates: { id: number; rate: number }[], orderDiscount: number) {
  let subtotal = 0
  let taxAmount = 0

  for (const row of rows) {
    const lineTotal = row.quantity * (row.unit_price ?? 0) - row.discount_amount
    subtotal += lineTotal

    const taxRate = taxRates.find((rate) => rate.id === row.tax_rate_id)
    if (taxRate) {
      taxAmount += lineTotal * (Number(taxRate.rate) / 100)
    }
  }

  subtotal = round2(subtotal)
  taxAmount = round2(taxAmount)
  const discountAmount = round2(orderDiscount)
  const total = round2(subtotal - discountAmount + taxAmount)

  return { subtotal, taxAmount, discountAmount, total }
}

function round2(value: number): number {
  return Math.round(value * 100) / 100
}
