import { useAuthStore } from '@/lib/auth-store'

// Called from plain DataTable column `render`/`accessor` functions as well
// as components, so it reads the store directly via getState() rather than
// the usePermissions()-style hook pattern — most call sites aren't hooks.
function symbolFor(code: string): string {
  const match = useAuthStore.getState().currencies.find((currency) => currency.code === code)
  return match?.symbol ?? code
}

/**
 * Formats an amount using the company's configured currency by default.
 * Symbol comes from the /currencies reference list (e.g. "SSP", "KSh",
 * "$"), falling back to the raw currency code if it isn't in that list.
 * Pass currencyCodeOverride to format in a specific currency regardless of
 * the current company (e.g. the public receipt-verification page, which
 * reads currency_code from the API response instead of the auth store).
 */
export function formatCurrency(amount: number, currencyCodeOverride?: string | null): string {
  const code = currencyCodeOverride ?? useAuthStore.getState().company?.currency_code ?? 'USD'
  const symbol = symbolFor(code)

  const isNegative = amount < 0
  const number = new Intl.NumberFormat('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(Math.abs(amount))

  // Letter-based symbols (SSP, KSh, USh...) read better with a space;
  // single-glyph symbols ($, €, £) sit tight against the number.
  const formatted = /^[A-Za-z]/.test(symbol) ? `${symbol} ${number}` : `${symbol}${number}`

  return isNegative ? `-${formatted}` : formatted
}
