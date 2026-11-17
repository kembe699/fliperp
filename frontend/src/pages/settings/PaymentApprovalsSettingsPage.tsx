import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ArrowDown, ArrowUp, Pencil, Plus, Trash2, X } from 'lucide-react'

import { createApprovalRule, deleteApprovalRule, fetchApprovalRules, updateApprovalRule, type ApprovalRule, type ApprovalRuleInput } from '@/api/paymentRequests'
import { formatCurrency } from '@/lib/currency'
import { getApiErrorInfo } from '@/lib/api-errors'
import { useAuthStore } from '@/lib/auth-store'
import { usePermissions } from '@/hooks/use-permissions'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { FlipLoaderBlock } from '@/components/brand/FlipLoader'

interface StepDraft {
  label: string
  kind: 'role' | 'user'
  id: string
}

interface RuleDraft {
  name: string
  min_amount: string
  max_amount: string
  is_active: boolean
  steps: StepDraft[]
}

const EMPTY: RuleDraft = { name: '', min_amount: '0', max_amount: '', is_active: true, steps: [{ label: '', kind: 'role', id: '' }] }

/** Settings › Payment Approvals: who approves payment requests, by amount. */
export function PaymentApprovalsSettingsPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()
  const canManage = can('payment-approval-rules.manage')
  const base = useAuthStore((state) => state.company?.currency_code) ?? 'USD'

  const { data, isLoading, isError } = useQuery({ queryKey: ['payment-approval-rules'], queryFn: fetchApprovalRules })
  const [editing, setEditing] = useState<ApprovalRule | 'new' | null>(null)
  const [draft, setDraft] = useState<RuleDraft>(EMPTY)
  const [errors, setErrors] = useState<Record<string, string>>({})

  const open = (rule: ApprovalRule | 'new') => {
    setErrors({})
    setEditing(rule)
    setDraft(
      rule === 'new'
        ? EMPTY
        : {
            name: rule.name,
            min_amount: String(rule.min_amount),
            max_amount: rule.max_amount === null ? '' : String(rule.max_amount),
            is_active: rule.is_active,
            steps: rule.steps.map((s) => ({ label: s.label, kind: s.user_id ? 'user' : 'role', id: String(s.user_id ?? s.role_id ?? '') })),
          },
    )
  }

  const toInput = (): ApprovalRuleInput => ({
    name: draft.name,
    min_amount: Number(draft.min_amount || 0),
    max_amount: draft.max_amount === '' ? null : Number(draft.max_amount),
    is_active: draft.is_active,
    steps: draft.steps.map((s) => ({ label: s.label, role_id: s.kind === 'role' && s.id ? Number(s.id) : null, user_id: s.kind === 'user' && s.id ? Number(s.id) : null })),
  })

  const save = useMutation({
    mutationFn: () => (editing === 'new' ? createApprovalRule(toInput()) : updateApprovalRule((editing as ApprovalRule).id, toInput())),
    onSuccess: () => {
      toast.success('Approval rule saved')
      queryClient.invalidateQueries({ queryKey: ['payment-approval-rules'] })
      setEditing(null)
    },
    onError: (error) => {
      const info = getApiErrorInfo(error)
      if (info.errors) setErrors(Object.fromEntries(Object.entries(info.errors).map(([k, v]) => [k, Array.isArray(v) ? v[0] : String(v)])))
      toast.error(info.message)
    },
  })

  const remove = useMutation({
    mutationFn: (id: number) => deleteApprovalRule(id),
    onSuccess: () => {
      toast.success('Approval rule deleted')
      queryClient.invalidateQueries({ queryKey: ['payment-approval-rules'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const setStep = (index: number, patch: Partial<StepDraft>) => setDraft({ ...draft, steps: draft.steps.map((s, i) => (i === index ? { ...s, ...patch } : s)) })
  const moveStep = (index: number, by: number) => {
    const steps = [...draft.steps]
    const [step] = steps.splice(index, 1)
    steps.splice(index + by, 0, step)
    setDraft({ ...draft, steps })
  }

  const band = (rule: ApprovalRule) =>
    rule.max_amount === null
      ? `Above ${formatCurrency(rule.min_amount, base)}`
      : rule.min_amount > 0
        ? `Above ${formatCurrency(rule.min_amount, base)}, up to ${formatCurrency(rule.max_amount, base)}`
        : `Up to ${formatCurrency(rule.max_amount, base)}`

  return (
    <div>
      <PageHeader
        parent="Settings"
        title="Payment Approvals"
        action={
          canManage && (
            <Button onClick={() => open('new')}>
              <Plus className="h-4 w-4" />
              New rule
            </Button>
          )
        }
      />
      <p className="mb-4 max-w-3xl text-sm text-muted-foreground">
        Each rule covers an amount range in {base}. A payment request in another currency is converted at its date's rate. Its approvers sign off in the order listed. Whoever raised a request can never approve it, and one person approves only one step.
      </p>

      {isError && <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load the approval rules.</p>}
      {isLoading && <FlipLoaderBlock />}
      {data && data.rules.length === 0 && (
        <Card>
          <CardContent className="p-6 text-sm text-muted-foreground">No approval rules yet. Payment requests can't be submitted until an amount is covered by a rule.</CardContent>
        </Card>
      )}

      <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
        {data?.rules.map((rule) => (
          <Card key={rule.id} className={rule.is_active ? '' : 'opacity-60'}>
            <CardContent className="p-6">
              <div className="mb-3 flex items-start justify-between gap-2">
                <div>
                  <p className="font-semibold">{rule.name}</p>
                  <p className="text-sm text-muted-foreground">{band(rule)}</p>
                </div>
                <div className="flex items-center gap-1">
                  {!rule.is_active && <StatusBadge label="Off" />}
                  {canManage && (
                    <>
                      <Button variant="ghost" size="sm" aria-label={`Edit ${rule.name}`} onClick={() => open(rule)}>
                        <Pencil className="h-4 w-4" />
                      </Button>
                      <Button variant="ghost" size="sm" aria-label={`Delete ${rule.name}`} onClick={() => window.confirm(`Delete the rule "${rule.name}"? Requests already submitted keep their approvers.`) && remove.mutate(rule.id)}>
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    </>
                  )}
                </div>
              </div>
              <ol className="space-y-1 text-sm">
                {rule.steps.map((step) => (
                  <li key={step.id}>
                    <span className="font-medium">
                      {step.sequence}. {step.label}
                    </span>
                    <span className="text-muted-foreground"> · {step.user_name ?? `anyone with the ${step.role_name} role`}</span>
                  </li>
                ))}
              </ol>
            </CardContent>
          </Card>
        ))}
      </div>

      <Dialog open={editing !== null} onOpenChange={(isOpen) => !isOpen && setEditing(null)}>
        <DialogContent className="max-w-2xl">
          <DialogHeader>
            <DialogTitle>{editing === 'new' ? 'New approval rule' : 'Edit approval rule'}</DialogTitle>
          </DialogHeader>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <div className="space-y-1.5 sm:col-span-3">
              <Label required>Name</Label>
              <Input placeholder="e.g. Above USD 500" value={draft.name} onChange={(e) => setDraft({ ...draft, name: e.target.value })} />
              {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
            </div>
            <div className="space-y-1.5">
              <Label required>Above ({base})</Label>
              <Input type="number" min="0" step="0.01" value={draft.min_amount} onChange={(e) => setDraft({ ...draft, min_amount: e.target.value })} />
              {errors.min_amount && <p className="text-xs text-destructive">{errors.min_amount}</p>}
            </div>
            <div className="space-y-1.5">
              <Label>Up to and including ({base})</Label>
              <Input type="number" min="0" step="0.01" placeholder="No upper limit" value={draft.max_amount} onChange={(e) => setDraft({ ...draft, max_amount: e.target.value })} />
              {errors.max_amount && <p className="text-xs text-destructive">{errors.max_amount}</p>}
            </div>
            <label className="flex items-end gap-2 pb-2 text-sm">
              <input type="checkbox" checked={draft.is_active} onChange={(e) => setDraft({ ...draft, is_active: e.target.checked })} />
              Rule is on
            </label>
          </div>

          <div className="space-y-2">
            <p className="text-sm font-medium">Approvers, in order</p>
            {errors.steps && <p className="text-xs text-destructive">{errors.steps}</p>}
            {draft.steps.map((step, index) => (
              <div key={index} className="grid grid-cols-12 items-start gap-2 rounded-lg border border-border p-2">
                <span className="col-span-1 pt-2 text-sm font-semibold">{index + 1}.</span>
                <div className="col-span-4">
                  <Input placeholder="Label, e.g. Program Officer" value={step.label} onChange={(e) => setStep(index, { label: e.target.value })} />
                  {errors[`steps.${index}.label`] && <p className="text-xs text-destructive">{errors[`steps.${index}.label`]}</p>}
                </div>
                <div className="col-span-2">
                  <Select value={step.kind} onValueChange={(value) => setStep(index, { kind: value as StepDraft['kind'], id: '' })}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="role">Role</SelectItem>
                      <SelectItem value="user">Person</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
                <div className="col-span-3">
                  <Select value={step.id} onValueChange={(value) => setStep(index, { id: value })}>
                    <SelectTrigger>
                      <SelectValue placeholder={step.kind === 'role' ? 'Choose role' : 'Choose person'} />
                    </SelectTrigger>
                    <SelectContent>
                      {(step.kind === 'role' ? data?.roles ?? [] : data?.users ?? []).map((option) => (
                        <SelectItem key={option.id} value={String(option.id)}>
                          {option.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  {(errors[`steps.${index}.role_id`] || errors[`steps.${index}.user_id`]) && <p className="text-xs text-destructive">{errors[`steps.${index}.role_id`] ?? errors[`steps.${index}.user_id`]}</p>}
                </div>
                <div className="col-span-2 flex justify-end gap-1">
                  <Button type="button" variant="ghost" size="sm" disabled={index === 0} aria-label="Move up" onClick={() => moveStep(index, -1)}>
                    <ArrowUp className="h-4 w-4" />
                  </Button>
                  <Button type="button" variant="ghost" size="sm" disabled={index === draft.steps.length - 1} aria-label="Move down" onClick={() => moveStep(index, 1)}>
                    <ArrowDown className="h-4 w-4" />
                  </Button>
                  <Button type="button" variant="ghost" size="sm" disabled={draft.steps.length === 1} aria-label="Remove approver" onClick={() => setDraft({ ...draft, steps: draft.steps.filter((_, i) => i !== index) })}>
                    <X className="h-4 w-4" />
                  </Button>
                </div>
              </div>
            ))}
            <Button type="button" variant="outline" size="sm" onClick={() => setDraft({ ...draft, steps: [...draft.steps, { label: '', kind: 'role', id: '' }] })}>
              <Plus className="h-4 w-4" />
              Add approver
            </Button>
          </div>

          <DialogFooter>
            <Button variant="outline" onClick={() => setEditing(null)}>
              Cancel
            </Button>
            <Button disabled={save.isPending} onClick={() => save.mutate()}>
              {save.isPending ? 'Saving…' : 'Save rule'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
