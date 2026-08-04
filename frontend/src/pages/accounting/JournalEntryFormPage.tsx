import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus, Trash2 } from 'lucide-react'

import { createJournalEntry } from '@/api/accounting'
import { fetchChartOfAccounts } from '@/api/reports'
import { fetchBranches } from '@/api/branches'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatCurrency } from '@/lib/format'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { SearchableSelect } from '@/components/shared/SearchableSelect'

interface Row {
  key: string
  account_id: number | null
  debit: string
  credit: string
  description: string
}

export function JournalEntryFormPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { data: accounts } = useQuery({ queryKey: ['chart-of-accounts'], queryFn: fetchChartOfAccounts })
  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })

  const [branchId, setBranchId] = useState('none')
  const [referenceNumber, setReferenceNumber] = useState('')
  const [entryDate, setEntryDate] = useState(() => new Date().toISOString().slice(0, 10))
  const [description, setDescription] = useState('')
  const [rows, setRows] = useState<Row[]>([
    { key: crypto.randomUUID(), account_id: null, debit: '', credit: '', description: '' },
    { key: crypto.randomUUID(), account_id: null, debit: '', credit: '', description: '' },
  ])

  const addRow = () => setRows((prev) => [...prev, { key: crypto.randomUUID(), account_id: null, debit: '', credit: '', description: '' }])
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
        lines: rows
          .filter((row) => row.account_id)
          .map((row) => ({ account_id: row.account_id!, debit: Number(row.debit) || 0, credit: Number(row.credit) || 0, description: row.description || null })),
      }),
    onSuccess: (entry) => {
      toast.success('Journal entry created as draft')
      queryClient.invalidateQueries({ queryKey: ['journal-entries'] })
      navigate(`/journal-entries/${entry.id}`)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSubmit = referenceNumber && entryDate && isBalanced && rows.filter((row) => row.account_id).length >= 2

  return (
    <div>
      <PageHeader parent="Journal Entries" title="New Journal Entry" />

      <Card className="mb-4">
        <CardContent className="grid grid-cols-1 gap-4 p-6 sm:grid-cols-2 lg:grid-cols-4">
          <div className="space-y-1.5">
            <Label>Reference Number</Label>
            <Input value={referenceNumber} onChange={(event) => setReferenceNumber(event.target.value)} placeholder="JE-001" />
          </div>
          <div className="space-y-1.5">
            <Label>Entry Date</Label>
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
        </CardContent>
      </Card>

      <Card className="mb-4">
        <CardContent className="space-y-3 p-6">
          <p className="text-sm font-semibold text-foreground">Lines</p>
          {rows.map((row) => (
            <div key={row.key} className="flex items-center gap-2">
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
          ))}
          <Button type="button" variant="outline" size="sm" onClick={addRow}>
            <Plus className="h-4 w-4" />
            Add Line
          </Button>

          <div className="flex items-center justify-end gap-6 border-t border-border pt-3 text-sm">
            <span className="text-muted-foreground">
              Total Debit: <span className="font-medium text-foreground">{formatCurrency(totalDebit)}</span>
            </span>
            <span className="text-muted-foreground">
              Total Credit: <span className="font-medium text-foreground">{formatCurrency(totalCredit)}</span>
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
