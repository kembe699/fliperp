import { useEffect, useState } from 'react'
import { useQuery } from '@tanstack/react-query'

import { fetchRatedCurrencies, lookupExchangeRate } from '@/api/exchangeRates'
import { useAuthStore } from '@/lib/auth-store'

import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

interface Props {
  currency: string
  /** Omit to show the currency as fixed (e.g. a payment always uses its invoice's currency). */
  onCurrencyChange?: (code: string) => void
  /** The document's date; the rate defaults to the one in force on it. */
  date: string
  /** As typed — '' until known. */
  rate: string
  onRateChange: (rate: string) => void
}

/**
 * Currency + exchange rate for a document. The rate is looked up for the
 * document's date whenever the currency or date changes, and can be typed
 * over; once overridden it is left alone until "Use rate for date" is used.
 * Rates read "1 {base} = rate {currency}" everywhere in the app.
 */
export function CurrencyRateFields({ currency, onCurrencyChange, date, rate, onRateChange }: Props) {
  const base = useAuthStore((state) => state.company?.currency_code) ?? 'USD'
  const isBase = currency === base
  const [overridden, setOverridden] = useState(false)

  const { data: rated } = useQuery({ queryKey: ['exchange-rates', 'currencies'], queryFn: fetchRatedCurrencies })
  const { data: lookup, isFetching } = useQuery({
    queryKey: ['exchange-rates', 'lookup', currency, date],
    queryFn: () => lookupExchangeRate(currency, date),
    enabled: !isBase && !!currency && !!date,
  })

  useEffect(() => {
    if (isBase) {
      onRateChange('1')
      setOverridden(false)
      return
    }
    if (!overridden && lookup) onRateChange(lookup.rate != null ? String(lookup.rate) : '')
    // onRateChange is a setter from the parent; re-running on its identity would loop.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [isBase, lookup, overridden])

  const options = Array.from(new Set([base, ...(rated ?? []), currency].filter(Boolean)))

  return (
    <>
      <div className="space-y-1.5">
        <Label required>Currency</Label>
        {onCurrencyChange ? (
          <Select
            value={currency}
            onValueChange={(code) => {
              setOverridden(false)
              onCurrencyChange(code)
            }}
          >
            <SelectTrigger>
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              {options.map((code) => (
                <SelectItem key={code} value={code}>
                  {code}
                  {code === base ? ' (base)' : ''}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        ) : (
          <Input value={currency} disabled />
        )}
      </div>
      {!isBase && (
        <div className="space-y-1.5">
          <Label required>
            Exchange rate <span className="font-normal text-muted-foreground">(1 {base} = ? {currency})</span>
          </Label>
          <Input
            type="number"
            min="0"
            step="any"
            value={rate}
            onChange={(event) => {
              setOverridden(true)
              onRateChange(event.target.value)
            }}
          />
          <p className="text-xs text-muted-foreground">
            {isFetching ? (
              'Looking up rate…'
            ) : overridden ? (
              <>
                Manual rate.{' '}
                <button type="button" className="underline" onClick={() => setOverridden(false)}>
                  Use rate for date
                </button>
              </>
            ) : lookup?.rate != null ? (
              `Rate set for ${lookup.effective_date}`
            ) : (
              <span className="text-danger">No {currency} rate on or before {date} — enter one here or add it under Exchange Rates.</span>
            )}
          </p>
        </div>
      )}
    </>
  )
}
