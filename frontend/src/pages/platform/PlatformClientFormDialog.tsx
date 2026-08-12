import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Check, Copy } from 'lucide-react'

import { createPlatformClient } from '@/api/platform'
import { getApiErrorInfo } from '@/lib/api-errors'
import type { CreatePlatformClientInput, CreatePlatformClientResult } from '@/types/platform'

import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const emptyForm: CreatePlatformClientInput = {
  company_name: '',
  admin_name: '',
  admin_email: '',
  branch_name: '',
  billing_phone: '',
}

interface PlatformClientFormDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function PlatformClientFormDialog({ open, onOpenChange }: PlatformClientFormDialogProps) {
  const queryClient = useQueryClient()
  const [form, setForm] = useState<CreatePlatformClientInput>(emptyForm)
  const [result, setResult] = useState<CreatePlatformClientResult | null>(null)
  const [copied, setCopied] = useState<'code' | 'password' | null>(null)

  const createMutation = useMutation({
    mutationFn: () => createPlatformClient(form),
    onSuccess: (data) => {
      setResult(data)
      queryClient.invalidateQueries({ queryKey: ['platform-clients'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const handleClose = (nextOpen: boolean) => {
    if (!nextOpen) {
      setForm(emptyForm)
      setResult(null)
      setCopied(null)
    }
    onOpenChange(nextOpen)
  }

  const copy = (value: string, which: 'code' | 'password') => {
    navigator.clipboard.writeText(value)
    setCopied(which)
    setTimeout(() => setCopied(null), 1500)
  }

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent className="max-w-md">
        {result ? (
          <div className="space-y-4">
            <DialogHeader>
              <DialogTitle>Client Onboarded</DialogTitle>
            </DialogHeader>
            <p className="text-sm text-muted-foreground">
              Share these credentials with <strong className="text-foreground">{result.admin_user.name}</strong> — they
              won't be shown again. A welcome email has also been sent to {result.admin_user.email}.
            </p>

            <div className="space-y-1">
              <Label>Client Code</Label>
              <div className="flex items-center gap-2">
                <Input readOnly value={result.company.client_code} className="font-mono" />
                <Button type="button" variant="outline" size="icon" onClick={() => copy(result.company.client_code, 'code')}>
                  {copied === 'code' ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
                </Button>
              </div>
            </div>

            <div className="space-y-1">
              <Label>Temporary Password</Label>
              <div className="flex items-center gap-2">
                <Input readOnly value={result.temp_password} className="font-mono" />
                <Button type="button" variant="outline" size="icon" onClick={() => copy(result.temp_password, 'password')}>
                  {copied === 'password' ? <Check className="h-4 w-4" /> : <Copy className="h-4 w-4" />}
                </Button>
              </div>
            </div>

            <DialogFooter>
              <Button className="w-full" onClick={() => handleClose(false)}>
                Done
              </Button>
            </DialogFooter>
          </div>
        ) : (
          <>
            <DialogHeader>
              <DialogTitle>Onboard New Client</DialogTitle>
            </DialogHeader>
            <form
              className="space-y-4"
              onSubmit={(event) => {
                event.preventDefault()
                createMutation.mutate()
              }}
            >
              <div className="space-y-1">
                <Label htmlFor="company_name">Company Name</Label>
                <Input
                  id="company_name"
                  required
                  value={form.company_name}
                  onChange={(event) => setForm({ ...form, company_name: event.target.value })}
                />
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <Label htmlFor="admin_name">Admin Name</Label>
                  <Input
                    id="admin_name"
                    required
                    value={form.admin_name}
                    onChange={(event) => setForm({ ...form, admin_name: event.target.value })}
                  />
                </div>
                <div className="space-y-1">
                  <Label htmlFor="admin_email">Admin Email</Label>
                  <Input
                    id="admin_email"
                    type="email"
                    required
                    value={form.admin_email}
                    onChange={(event) => setForm({ ...form, admin_email: event.target.value })}
                  />
                </div>
              </div>
              <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                  <Label htmlFor="branch_name">Main Branch Name</Label>
                  <Input
                    id="branch_name"
                    placeholder="Main Branch"
                    value={form.branch_name}
                    onChange={(event) => setForm({ ...form, branch_name: event.target.value })}
                  />
                </div>
                <div className="space-y-1">
                  <Label htmlFor="billing_phone">Billing Phone</Label>
                  <Input
                    id="billing_phone"
                    value={form.billing_phone}
                    onChange={(event) => setForm({ ...form, billing_phone: event.target.value })}
                  />
                </div>
              </div>

              <DialogFooter>
                <Button type="submit" className="w-full" disabled={createMutation.isPending}>
                  {createMutation.isPending ? 'Onboarding…' : 'Onboard Client'}
                </Button>
              </DialogFooter>
            </form>
          </>
        )}
      </DialogContent>
    </Dialog>
  )
}
