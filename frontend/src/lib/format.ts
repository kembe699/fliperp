export function formatDate(value: string): string {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value
  return new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', year: 'numeric' }).format(date)
}

const RELATIVE_TIME_UNITS: [Intl.RelativeTimeFormatUnit, number][] = [
  ['year', 60 * 60 * 24 * 365],
  ['month', 60 * 60 * 24 * 30],
  ['week', 60 * 60 * 24 * 7],
  ['day', 60 * 60 * 24],
  ['hour', 60 * 60],
  ['minute', 60],
]

const relativeTimeFormatter = new Intl.RelativeTimeFormat('en-US', { numeric: 'auto' })

export function formatRelativeTime(value: string): string {
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) return value

  const seconds = Math.round((date.getTime() - Date.now()) / 1000)

  if (Math.abs(seconds) < 60) return 'just now'

  for (const [unit, unitSeconds] of RELATIVE_TIME_UNITS) {
    if (Math.abs(seconds) >= unitSeconds) {
      return relativeTimeFormatter.format(Math.round(seconds / unitSeconds), unit)
    }
  }

  return relativeTimeFormatter.format(Math.round(seconds / 60), 'minute')
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
