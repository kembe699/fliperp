import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus, Trash2 } from 'lucide-react'

import { createJournalEntry } from '@/api/accounting'
import { fetchChartOfAccounts } from '@/api/reports'
import { fetchBranches } from '@/api/branches'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatCurrency } from '@/lib/currency'
import { useAuthStore } from '@/lib/auth-store'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { SearchableSelect } from '@/components/shared/SearchableSelect'
import { CurrencyRateFields } from '@/components/shared/CurrencyRateFields'
import { CodingFields } from '@/components/shared/CodingFields'
import { EMPTY_CODING, type Coding } from '@/types/grants'

interface Row extends Coding {
  key: string
  account_id: number | null
  debit: string
  credit: string
  description: string
}

const emptyRow = (): Row => ({ key: crypto.randomUUID(), account_id: null, debit: '', credit: '', description: '', ...EMPTY_CODING })

export function JournalEntryFormPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { data: accounts } = useQuery({ queryKey: ['chart-of-accounts'], queryFn: fetchChartOfAccounts })
  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })

  const [branchId, setBranchId] = useState('none')
  const [referenceNumber, setReferenceNumber] = useState('')
  const [entryDate, setEntryDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [description, setDescription] = useState('')
  const baseCurrency = useAuthStore((state) => state.company?.currency_code) ?? 'USD'
  const [currency, setCurrency] = useState(baseCurrency)
  const [rate, setRate] = useState('1')
  const [rows, setRows] = useState<Row[]>(() => [emptyRow(), emptyRow()])

  const addRow = () => setRows((prev) => [...prev, emptyRow()])
  const removeRow = (key: string) => setRows((prev) => prev.filter((row) => row.key !== key))
  const updateRow = (key: string, patch: Partial<Row>) => setRows((prev) => prev.map((row) => (row.key === key ? { ...row, ...patch } : row)))

  const totalDebit = rows.reduce((sum, row) => sum + (Number(row.debit) || 0), 0)
  const totalCredit = rows.reduce((sum, row) => sum + (Number(row.credit) || 0), 0)
  const isBalanced = Math.round(totalDebit * 100) === Math.round(totalCredit * 100) && totalDebit > 0

  const mutation = useMutation({
    mutationFn: () =>
      createJournalEntry({
        branch_id: branchId === 'none' ? null : Number(branchId),
        reference_number: referenceNumber,
        entry_date: entryDate,
        description: description || null,
        currency_code: currency,
        exchange_rate: Number(rate),
        lines: rows
          .filter((row) => row.account_id)
          .map((row) => ({
            account_id: row.account_id!,
            debit: Number(row.debit) || 0,
            credit: Number(row.credit) || 0,
            description: row.description || null,
            grant_id: row.grant_id,
            dea_id: row.dea_id,
            project_id: row.project_id,
            cost_centre_id: row.cost_centre_id,
          })),
      }),
    onSuccess: (entry) => {
      toast.success('Journal entry created as draft')
      queryClient.invalidateQueries({ queryKey: ['journal-entries'] })
      navigate(`/journal-entries/${entry.id}`)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSubmit = referenceNumber && entryDate && isBalanced && Number(rate) > 0 && rows.filter((row) => row.account_id).length >= 2

  return (
    <div>
      <PageHeader parent="Journal Entries" title="New Journal Entry" />

      <Card className="mb-4">
        <CardContent className="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2 lg:grid-cols-4">
          <div className="space-y-1.5">
            <Label required>Reference Number</Label>
            <Input value={referenceNumber} onChange={(event) => setReferenceNumber(event.target.value)} placeholder="JE-001" />
          </div>
          <div className="space-y-1.5">
            <Label required>Entry Date</Label>
            <Input type="date" value={entryDate} onChange={(event) => setEntryDate(event.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label>Branch (optional)</Label>
            <Select value={branchId} onValueChange={setBranchId}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="none">No branch</SelectItem>
                {branches?.map((branch) => (
                  <SelectItem key={branch.id} value={String(branch.id)}>
                    {branch.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5 lg:col-span-1">
            <Label>Description</Label>
            <Input value={description} onChange={(event) => setDescription(event.target.value)} />
          </div>
          <CurrencyRateFields currency={currency} onCurrencyChange={setCurrency} date={entryDate} rate={rate} onRateChange={setRate} />
        </CardContent>
      </Card>

      <Card className="mb-4">
        <CardContent className="space-y-3 p-6">
          <div>
            <p className="text-sm font-semibold text-foreground">Lines</p>
            <p className="text-xs text-muted-foreground">Code each line to a SOF, DEA, project and cost centre where it applies (all optional).</p>
          </div>
          <div className="hidden items-center gap-2 text-[11px] font-semibold uppercase tracking-wide text-muted-foreground sm:flex">
            <span className="flex-1">
              Account<span className="ml-0.5 text-danger">*</span>
            </span>
            <span className="w-28">Debit ({currency})</span>
            <span className="w-28">Credit ({currency})</span>
            <span className="w-40">Line memo</span>
            <span className="w-4" />
          </div>
          {rows.map((row) => (
            <div key={row.key} className="space-y-2 rounded-lg border border-border p-2">
            <div className="flex items-center gap-2">
              <div className="flex-1">
                <SearchableSelect
                  options={(accounts ?? []).map((account) => ({ value: String(account.id), label: `${account.code} — ${account.name}` }))}
                  value={row.account_id ? String(row.account_id) : null}
                  onChange={(value) => updateRow(row.key, { account_id: value ? Number(value) : null })}
                  placeholder="Select account"
                />
              </div>
              <Input
                type="number"
                min="0"
                step="0.01"
                value={row.debit}
                onChange={(event) => updateRow(row.key, { debit: event.target.value, credit: event.target.value ? '' : row.credit })}
                className="w-28"
                placeholder="Debit"
              />
              <Input
                type="number"
                min="0"
                step="0.01"
                value={row.credit}
                onChange={(event) => updateRow(row.key, { credit: event.target.value, debit: event.target.value ? '' : row.debit })}
                className="w-28"
                placeholder="Credit"
              />
              <Input value={row.description} onChange={(event) => updateRow(row.key, { description: event.target.value })} className="w-40" placeholder="Line memo" />
              <button type="button" onClick={() => removeRow(row.key)} className="text-muted-foreground hover:text-destructive">
                <Trash2 className="h-4 w-4" />
              </button>
            </div>
            <CodingFields compact value={row} date={entryDate} onChange={(patch) => updateRow(row.key, patch)} />
            </div>
          ))}
          <Button type="button" variant="outline" size="sm" onClick={addRow}>
            <Plus className="h-4 w-4" />
            Add Line
          </Button>

          <div className="flex items-center justify-end gap-6 border-t border-border pt-3 text-sm">
            <span className="text-muted-foreground">
              Total Debit: <span className="font-medium text-foreground">{formatCurrency(totalDebit, currency)}</span>
            </span>
            <span className="text-muted-foreground">
              Total Credit: <span className="font-medium text-foreground">{formatCurrency(totalCredit, currency)}</span>
            </span>
            <span className={`font-semibold ${isBalanced ? 'text-success' : 'text-danger'}`}>{isBalanced ? 'Balanced' : 'Not Balanced'}</span>
          </div>
        </CardContent>
      </Card>

      <div className="flex justify-end gap-2">
        <Button variant="outline" onClick={() => navigate(-1)}>
          Cancel
        </Button>
        <Button disabled={!canSubmit || mutation.isPending} onClick={() => mutation.mutate()}>
          Save as Draft
        </Button>
      </div>
    </div>
  )
}
