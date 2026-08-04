export interface CartLine {
  productId: number
  productName: string
  sku: string
  unitPrice: number
  quantity: number
  taxRateId: number | null
  taxRatePercent: number
}

export function lineTotal(line: CartLine): number {
  return round2(line.unitPrice * line.quantity)
}

export function lineTax(line: CartLine): number {
  return round2(lineTotal(line) * (line.taxRatePercent / 100))
}

export function round2(value: number): number {
  return Math.round(value * 100) / 100
}

export function cartSubtotal(lines: CartLine[]): number {
  return round2(lines.reduce((sum, line) => sum + lineTotal(line), 0))
}

export function cartTax(lines: CartLine[]): number {
  return round2(lines.reduce((sum, line) => sum + lineTax(line), 0))
}

export function cartTotal(lines: CartLine[]): number {
  return round2(cartSubtotal(lines) + cartTax(lines))
}
