export function formatCurrency(value: number, currencyCode?: string | null): string {
  const currency = currencyCode ?? 'USD'
  try {
    return new Intl.NumberFormat('en-US', {
      style: 'currency',
      currency,
      maximumFractionDigits: 2,
    }).format(value)
  } catch {
    return `${currency} ${value.toFixed(2)}`
  }
}

export function formatDate(value: string): string {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric' }).format(date)
}

export function toISODate(date: Date): string {
  return date.toISOString().slice(0, 10)
}

export function startOfMonth(date = new Date()): Date {
  return new Date(date.getFullYear(), date.getMonth(), 1)
}

export function startOfLastMonth(date = new Date()): Date {
  return new Date(date.getFullYear(), date.getMonth() - 1, 1)
}

export function endOfLastMonth(date = new Date()): Date {
  return new Date(date.getFullYear(), date.getMonth(), 0)
}

export function startOfYear(date = new Date()): Date {
  return new Date(date.getFullYear(), 0, 1)
}

export function daysAgo(days: number, date = new Date()): Date {
  const copy = new Date(date)
  copy.setDate(copy.getDate() - days)
  return copy
}
