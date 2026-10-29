import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createGrant, fetchDonors, updateGrant } from '@/api/grants'
import { getApiErrorInfo } from '@/lib/api-errors'
import { useAuthStore } from '@/lib/auth-store'
import type { Grant, GrantStatus } from '@/types/grants'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

interface Props {
  open: boolean
  onOpenChange: (open: boolean) => void
  /** Omit to create a new SOF. */
  grant?: Grant | null
  onSaved?: (grant: Grant) => void
}

/** Create or edit a SOF: its donor, code, currency, dates and total award. */
export function GrantFormDialog({ open, onOpenChange, grant, onSaved }: Props) {
  const queryClient = useQueryClient()
  const currencies = useAuthStore((state) => state.currencies)
  const { data: donors } = useQuery({ queryKey: ['donors'], queryFn: () => fetchDonors(), enabled: open })

  const [donorId, setDonorId] = useState('')
  const [code, setCode] = useState('')
  const [name, setName] = useState('')
  const [currency, setCurrency] = useState('USD')
  const [startDate, setStartDate] = useState('')
  const [endDate, setEndDate] = useState('')
  const [budget, setBudget] = useState('')
  const [status, setStatus] = useState<GrantStatus>('active')
  const [notes, setNotes] = useState('')

  useEffect(() => {
    if (!open) return
    setDonorId(grant ? String(grant.donor_id) : '')
    setCode(grant?.code ?? '')
    setName(grant?.name ?? '')
    setCurrency(grant?.currency_code ?? 'USD')
    setStartDate(grant?.start_date ?? '')
    setEndDate(grant?.end_date ?? '')
    setBudget(grant ? String(grant.budget_amount) : '')
    setStatus(grant?.status ?? 'active')
    setNotes(grant?.notes ?? '')
  }, [open, grant])

  const mutation = useMutation({
    mutationFn: () => {
      const payload = {
        donor_id: Number(donorId),
        code,
        name,
        currency_code: currency,
        start_date: startDate,
        end_date: endDate,
        budget_amount: budget ? Number(budget) : 0,
        status,
        notes: notes || null,
      }
      return grant ? updateGrant(grant.id, payload) : createGrant(payload)
    },
    onSuccess: (saved) => {
      toast.success(grant ? 'SOF updated' : 'SOF created')
      queryClient.invalidateQueries({ queryKey: ['grants'] })
      queryClient.invalidateQueries({ queryKey: ['grant', saved.id] })
      queryClient.invalidateQueries({ queryKey: ['dimensions'] })
      onOpenChange(false)
      onSaved?.(saved)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSave = donorId && code.trim() && name.trim() && currency && startDate && endDate && endDate >= startDate

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-xl">
        <DialogHeader>
          <DialogTitle>{grant ? `Edit SOF ${grant.code}` : 'New SOF (Source of Funding)'}</DialogTitle>
        </DialogHeader>
        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label required>Donor</Label>
            <Select value={donorId} onValueChange={setDonorId}>
              <SelectTrigger>
                <SelectValue placeholder={donors?.data.length === 0 ? 'Add a donor first (Grants › Donors)' : 'Select donor'} />
              </SelectTrigger>
              <SelectContent>
                {donors?.data.map((donor) => (
                  <SelectItem key={donor.id} value={String(donor.id)}>
                    {donor.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="grid grid-cols-3 gap-4">
            <div className="space-y-1.5">
              <Label required>SOF Code</Label>
              <Input value={code} onChange={(event) => setCode(event.target.value)} placeholder="SOF-12345" />
            </div>
            <div className="col-span-2 space-y-1.5">
              <Label required>Name</Label>
              <Input value={name} onChange={(event) => setName(event.target.value)} placeholder="Education in Emergencies, Juba" />
            </div>
          </div>
          <div className="grid grid-cols-3 gap-4">
            <div className="space-y-1.5">
              <Label required>SOF Currency</Label>
              <Select value={currency} onValueChange={setCurrency}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {currencies.map((c) => (
                    <SelectItem key={c.code} value={c.code}>
                      {c.code} — {c.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label required>Start Date</Label>
              <Input type="date" value={startDate} onChange={(event) => setStartDate(event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label required>End Date</Label>
              <Input type="date" value={endDate} onChange={(event) => setEndDate(event.target.value)} />
            </div>
          </div>
          <div className="grid grid-cols-3 gap-4">
            <div className="col-span-2 space-y-1.5">
              <Label>Total Award ({currency})</Label>
              <Input type="number" min="0" step="0.01" value={budget} onChange={(event) => setBudget(event.target.value)} />
            </div>
            {grant && (
              <div className="space-y-1.5">
                <Label>Status</Label>
                <Select value={status} onValueChange={(value) => setStatus(value as GrantStatus)}>
                  <SelectTrigger>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="active">Active</SelectItem>
                    <SelectItem value="closed">Closed</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            )}
          </div>
          <div className="space-y-1.5">
            <Label>Notes</Label>
            <Input value={notes} onChange={(event) => setNotes(event.target.value)} />
          </div>
          <p className="text-xs text-muted-foreground">
            Lines can only be coded to an active SOF, dated between its start and end dates. The Grant Report shows spend in {currency} alongside the
            base currency.
          </p>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button disabled={!canSave || mutation.isPending} onClick={() => mutation.mutate()}>
            {grant ? 'Save Changes' : 'Create SOF'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
