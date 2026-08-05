import { useEffect, useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { convertCrmLead } from '@/api/crm'
import { getApiErrorInfo } from '@/lib/api-errors'
import type { CrmLead } from '@/types/crm'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

interface ConvertLeadDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  lead: CrmLead | null
}

export function ConvertLeadDialog({ open, onOpenChange, lead }: ConvertLeadDialogProps) {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [phone, setPhone] = useState('')
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (open) {
      setPhone('')
      setError(null)
    }
  }, [open, lead])

  const needsPhone = !lead?.phone

  const mutation = useMutation({
    mutationFn: () => convertCrmLead(lead!.id, needsPhone ? { phone } : {}),
    onSuccess: (updatedLead) => {
      toast.success('Lead converted to customer')
      queryClient.invalidateQueries({ queryKey: ['crm-leads'] })
      queryClient.invalidateQueries({ queryKey: ['crm-report-summary'] })
      onOpenChange(false)
      if (updatedLead.converted_customer_id) {
        navigate(`/customers/${updatedLead.converted_customer_id}/statement`)
      }
    },
    onError: (err) => {
      const info = getApiErrorInfo(err)
      setError(info.errors?.phone?.[0] ?? info.message)
    },
  })

  if (!lead) return null

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Convert Lead to Customer</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <p className="text-sm text-muted-foreground">
            This will create a new customer record from <span className="font-semibold text-foreground">{lead.name}</span> and mark
            the lead as converted.
          </p>

          {needsPhone && (
            <div className="space-y-1.5">
              <Label htmlFor="convert-phone">Phone number</Label>
              <Input
                id="convert-phone"
                value={phone}
                onChange={(event) => setPhone(event.target.value)}
                placeholder="This lead has no phone on file — enter one to continue"
              />
              {error && <p className="text-xs text-destructive">{error}</p>}
            </div>
          )}

          {!needsPhone && error && <p className="text-xs text-destructive">{error}</p>}
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button
            type="button"
            disabled={mutation.isPending || (needsPhone && !phone.trim())}
            onClick={() => mutation.mutate()}
          >
            Convert
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
