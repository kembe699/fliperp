import { useQuery } from '@tanstack/react-query'
import { Info } from 'lucide-react'

import { fetchRatedCurrencies } from '@/api/exchangeRates'
import { useAuthStore } from '@/lib/auth-store'
import type { ReportCurrencyInfo } from '@/types/exchangeRate'

import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

/** "Show in" picker for report pages: the base currency (ledger figures) or any currency with a rate. */
export function ReportCurrencySelect({ value, onChange }: { value: string; onChange: (code: string) => void }) {
  const base = useAuthStore((state) => state.company?.currency_code) ?? 'USD'
  const { data: rated } = useQuery({ queryKey: ['exchange-rates', 'currencies'], queryFn: fetchRatedCurrencies })
  const options = Array.from(new Set([base, ...(rated ?? [])]))

  return (
    <div className="space-y-1.5">
      <Label>Show in</Label>
      <Select value={value} onValueChange={onChange}>
        <SelectTrigger className="w-36">
          <SelectValue />
        </SelectTrigger>
        <SelectContent>
          {options.map((code) => (
            <SelectItem key={code} value={code}>
              {code}
              {code === base ? ' (ledger)' : ''}
            </SelectItem>
          ))}
        </SelectContent>
      </Select>
    </div>
  )
}

/** Shown above a report whose figures were converted from the ledger's base currency. */
export function ConversionNotice({ currency }: { currency?: ReportCurrencyInfo }) {
  if (!currency?.converted || currency.rate == null) return null

  const rate = new Intl.NumberFormat('en-US', { maximumFractionDigits: 6 }).format(currency.rate)

  return (
    <div className="mb-4 flex items-start gap-2 rounded-xl border border-warning/40 bg-warning-bg px-4 py-3 text-sm text-foreground">
      <Info className="mt-0.5 h-4 w-4 shrink-0" />
      <p>
        <span className="font-semibold">Converted figures, not ledger amounts.</span> Converted at 1 {currency.base_currency} = {rate} {currency.code} on{' '}
        {currency.rate_date}. The ledger is kept in {currency.base_currency}.
      </p>
    </div>
  )
}
