import { useEffect, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus, Trash2 } from 'lucide-react'

import { createStatutoryDeductionRule, updateStatutoryDeductionRule } from '@/api/hr'
import { getApiErrorInfo } from '@/lib/api-errors'
import type { CalculationType, DeductionBracket, DeductionType, StatutoryDeductionRule } from '@/types/hr'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const TYPES: DeductionType[] = ['tax', 'pension', 'other']
const CALCULATION_TYPES: CalculationType[] = ['percentage', 'fixed', 'bracket']

export function StatutoryDeductionRuleFormDialog({
  open,
  onOpenChange,
  rule,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  rule: StatutoryDeductionRule | null
}) {
  const queryClient = useQueryClient()

  const [name, setName] = useState('')
  const [type, setType] = useState<DeductionType>('tax')
  const [calculationType, setCalculationType] = useState<CalculationType>('percentage')
  const [countryCode, setCountryCode] = useState('')
  const [isActive, setIsActive] = useState(true)
  const [rate, setRate] = useState('')
  const [amount, setAmount] = useState('')
  const [brackets, setBrackets] = useState<DeductionBracket[]>([{ min: 0, max: null, rate: 0 }])

  useEffect(() => {
    if (!open) return
    setName(rule?.name ?? '')
    setType(rule?.type ?? 'tax')
    setCalculationType(rule?.calculation_type ?? 'percentage')
    setCountryCode(rule?.country_code ?? '')
    setIsActive(rule?.is_active ?? true)
    setRate(rule?.config.rate !== undefined ? String(rule.config.rate) : '')
    setAmount(rule?.config.amount !== undefined ? String(rule.config.amount) : '')
    setBrackets(rule?.config.brackets ?? [{ min: 0, max: null, rate: 0 }])
  }, [open, rule])

  const addBracket = () => setBrackets((prev) => [...prev, { min: 0, max: null, rate: 0 }])
  const removeBracket = (index: number) => setBrackets((prev) => prev.filter((_, i) => i !== index))
  const updateBracket = (index: number, patch: Partial<DeductionBracket>) =>
    setBrackets((prev) => prev.map((bracket, i) => (i === index ? { ...bracket, ...patch } : bracket)))

  const buildConfig = () => {
    if (calculationType === 'percentage') return { rate: Number(rate) }
    if (calculationType === 'fixed') return { amount: Number(amount) }
    return { brackets }
  }

  const mutation = useMutation({
    mutationFn: () => {
      const payload = {
        name,
        type,
        calculation_type: calculationType,
        country_code: countryCode,
        is_active: isActive,
        config: buildConfig(),
      }
      return rule ? updateStatutoryDeductionRule(rule.id, payload) : createStatutoryDeductionRule(payload)
    },
    onSuccess: () => {
      toast.success(rule ? 'Deduction rule updated' : 'Deduction rule created')
      queryClient.invalidateQueries({ queryKey: ['statutory-deduction-rules'] })
      onOpenChange(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const configValid =
    (calculationType === 'percentage' && rate !== '') ||
    (calculationType === 'fixed' && amount !== '') ||
    (calculationType === 'bracket' && brackets.length > 0)
  const canSubmit = name && countryCode && configValid

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>{rule ? 'Edit Deduction Rule' : 'New Deduction Rule'}</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label>Name</Label>
            <Input value={name} onChange={(event) => setName(event.target.value)} placeholder="Uganda PAYE" />
          </div>
          <div className="grid grid-cols-3 gap-4">
            <div className="space-y-1.5">
              <Label>Type</Label>
              <Select value={type} onValueChange={(value) => setType(value as DeductionType)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {TYPES.map((t) => (
                    <SelectItem key={t} value={t}>
                      {t}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label>Calculation</Label>
              <Select value={calculationType} onValueChange={(value) => setCalculationType(value as CalculationType)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {CALCULATION_TYPES.map((c) => (
                    <SelectItem key={c} value={c}>
                      {c}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label>Country Code</Label>
              <Input value={countryCode} onChange={(event) => setCountryCode(event.target.value.toUpperCase())} placeholder="UGA" maxLength={3} />
            </div>
          </div>

          {calculationType === 'percentage' && (
            <div className="space-y-1.5">
              <Label>Rate (0–1, e.g. 0.05 = 5%)</Label>
              <Input type="number" min="0" max="1" step="0.001" value={rate} onChange={(event) => setRate(event.target.value)} />
            </div>
          )}

          {calculationType === 'fixed' && (
            <div className="space-y-1.5">
              <Label>Fixed Amount</Label>
              <Input type="number" min="0" step="0.01" value={amount} onChange={(event) => setAmount(event.target.value)} />
            </div>
          )}

          {calculationType === 'bracket' && (
            <div className="space-y-2">
              <Label>Brackets</Label>
              {brackets.map((bracket, index) => (
                <div key={index} className="flex items-center gap-2">
                  <Input
                    type="number"
                    min="0"
                    step="0.01"
                    value={bracket.min}
                    onChange={(event) => updateBracket(index, { min: Number(event.target.value) })}
                    className="w-28"
                    placeholder="Min"
                  />
                  <Input
                    type="number"
                    min="0"
                    step="0.01"
                    value={bracket.max ?? ''}
                    onChange={(event) => updateBracket(index, { max: event.target.value === '' ? null : Number(event.target.value) })}
                    className="w-28"
                    placeholder="Max (blank = open)"
                  />
                  <Input
                    type="number"
                    min="0"
                    max="1"
                    step="0.001"
                    value={bracket.rate}
                    onChange={(event) => updateBracket(index, { rate: Number(event.target.value) })}
                    className="w-24"
                    placeholder="Rate"
                  />
                  <button type="button" onClick={() => removeBracket(index)} className="text-muted-foreground hover:text-destructive">
                    <Trash2 className="h-4 w-4" />
                  </button>
                </div>
              ))}
              <Button type="button" variant="outline" size="sm" onClick={addBracket}>
                <Plus className="h-4 w-4" />
                Add Bracket
              </Button>
            </div>
          )}

          <label className="flex items-center gap-2 text-sm text-foreground">
            <input type="checkbox" checked={isActive} onChange={(event) => setIsActive(event.target.checked)} className="h-4 w-4 rounded border-input" />
            Active
          </label>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button disabled={!canSubmit || mutation.isPending} onClick={() => mutation.mutate()}>
            {rule ? 'Save Changes' : 'Create Rule'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
