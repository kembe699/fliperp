import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { createExchangeRate, deleteExchangeRate, fetchExchangeRates, updateExchangeRate } from '@/api/exchangeRates'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { getApiErrorInfo } from '@/lib/api-errors'
import { useAuthStore } from '@/lib/auth-store'
import { usePermissions } from '@/hooks/use-permissions'
import type { ExchangeRate } from '@/types/exchangeRate'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'
const rateFormat = new Intl.NumberFormat('en-US', { maximumFractionDigits: 6 })

export function ExchangeRatesPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()
  const base = useAuthStore((state) => state.company?.currency_code) ?? 'USD'
  const currencies = useAuthStore((state) => state.currencies)

  const [currencyFilter, setCurrencyFilter] = useState('all')
  const { data, isLoading, isError } = useQuery({
    queryKey: ['exchange-rates', 'list', currencyFilter],
    queryFn: () => fetchExchangeRates({ currency_code: currencyFilter === 'all' ? undefined : currencyFilter, per_page: 200 }),
  })
  const rates = useMemo(() => data?.data ?? [], [data])

  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<ExchangeRate | null>(null)
  const [currency, setCurrency] = useState('')
  const [rate, setRate] = useState('')
  const [effectiveDate, setEffectiveDate] = useState('')

  const foreignCurrencies = useMemo(() => currencies.filter((c) => c.code !== base), [currencies, base])
  const filterOptions = useMemo(() => Array.from(new Set(rates.map((r) => r.currency_code))).sort(), [rates])

  useEffect(() => {
    if (!formOpen) return
    setCurrency(editing?.currency_code ?? (foreignCurrencies.find((c) => c.code === 'SSP')?.code ?? foreignCurrencies[0]?.code ?? ''))
    setRate(editing ? String(editing.rate) : '')
    setEffectiveDate(editing?.effective_date ?? new Date().toISOString().slice(0, 10))
  }, [formOpen, editing, foreignCurrencies])

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['exchange-rates'] })

  const saveMutation = useMutation({
    mutationFn: () => {
      const payload = { currency_code: currency, rate: Number(rate), effective_date: effectiveDate }
      return editing ? updateExchangeRate(editing.id, payload) : createExchangeRate(payload)
    },
    onSuccess: () => {
      toast.success(editing ? 'Exchange rate updated' : 'Exchange rate added')
      invalidate()
      setFormOpen(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const deleteMutation = useMutation({
    mutationFn: deleteExchangeRate,
    onSuccess: () => {
      toast.success('Exchange rate deleted')
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const columns: DataTableColumn<ExchangeRate>[] = [
    { key: 'effective_date', header: 'Effective Date', accessor: (row) => row.effective_date, sortable: true },
    { key: 'currency_code', header: 'Currency', accessor: (row) => row.currency_code, sortable: true },
    {
      key: 'rate',
      header: `Rate (1 ${base} =)`,
      accessor: (row) => row.rate,
      render: (row) => `${rateFormat.format(row.rate)} ${row.currency_code}`,
    },
  ]

  const rowActions: (row: ExchangeRate) => DataTableRowAction<ExchangeRate>[] = () => [
    ...(can('exchange-rates.update') ? [{ label: 'Edit', onClick: (r: ExchangeRate) => { setEditing(r); setFormOpen(true) } }] : []),
    ...(can('exchange-rates.delete') ? [{ label: 'Delete', destructive: true, onClick: (r: ExchangeRate) => deleteMutation.mutate(r.id) }] : []),
  ]

  return (
    <div>
      <PageHeader
        parent="Accounting"
        title="Exchange Rates"
        action={
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('exchange-rates.csv', csvColumnsFromDataTable(columns), rates)} />
            {can('exchange-rates.create') && (
              <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
                <Plus className="h-4 w-4" />
                New Rate
              </Button>
            )}
          </div>
        }
      />

      <p className="mb-4 text-sm text-muted-foreground">
        Rates are entered per date as how many units of a currency equal 1 {base}, the base currency the ledger is kept in. A transaction uses the
        latest rate on or before its own date, unless a different rate is typed on the document.
      </p>

      <FilterBar>
        <Select value={currencyFilter} onValueChange={setCurrencyFilter}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Currency" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All currencies</SelectItem>
            {filterOptions.map((code) => (
              <SelectItem key={code} value={code}>
                {code}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </FilterBar>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load exchange rates. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={rates}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No exchange rates yet"
          emptySubtext={`Add a rate to record transactions in a currency other than ${base}.`}
        />
      )}

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? 'Edit Exchange Rate' : 'New Exchange Rate'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label>Currency</Label>
                <Select value={currency} onValueChange={setCurrency}>
                  <SelectTrigger>
                    <SelectValue placeholder="Select currency" />
                  </SelectTrigger>
                  <SelectContent>
                    {foreignCurrencies.map((c) => (
                      <SelectItem key={c.code} value={c.code}>
                        {c.code} — {c.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="space-y-1.5">
                <Label>Effective date</Label>
                <Input type="date" value={effectiveDate} onChange={(event) => setEffectiveDate(event.target.value)} />
              </div>
            </div>
            <div className="space-y-1.5">
              <Label>
                Rate <span className="font-normal text-muted-foreground">(1 {base} = ? {currency || 'currency'})</span>
              </Label>
              <Input type="number" min="0" step="any" value={rate} onChange={(event) => setRate(event.target.value)} placeholder="e.g. 7800" />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setFormOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!currency || !(Number(rate) > 0) || !effectiveDate || saveMutation.isPending} onClick={() => saveMutation.mutate()}>
              {editing ? 'Save Changes' : 'Add Rate'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
