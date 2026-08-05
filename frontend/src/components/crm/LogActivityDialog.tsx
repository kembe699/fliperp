import { useEffect, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createCrmActivity } from '@/api/crm'
import { getApiErrorInfo } from '@/lib/api-errors'
import { toISODate } from '@/lib/format'
import type { CrmActivityType } from '@/types/crm'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const TYPE_OPTIONS: CrmActivityType[] = ['call', 'email', 'meeting', 'note', 'complaint', 'follow_up']

interface LogActivityDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  customerId: number
}

export function LogActivityDialog({ open, onOpenChange, customerId }: LogActivityDialogProps) {
  const queryClient = useQueryClient()
  const [type, setType] = useState<CrmActivityType>('note')
  const [subject, setSubject] = useState('')
  const [description, setDescription] = useState('')
  const [activityDate, setActivityDate] = useState('')
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    if (!open) return
    setType('note')
    setSubject('')
    setDescription('')
    setActivityDate(toISODate(new Date()))
    setError(null)
  }, [open])

  const mutation = useMutation({
    mutationFn: () =>
      createCrmActivity({
        customer_id: customerId,
        type,
        subject,
        description: description || null,
        activity_date: activityDate,
      }),
    onSuccess: () => {
      toast.success('Activity logged')
      queryClient.invalidateQueries({ queryKey: ['crm-activities', customerId] })
      onOpenChange(false)
    },
    onError: (err) => setError(getApiErrorInfo(err).message),
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Log Activity</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Type</Label>
              <Select value={type} onValueChange={(value) => setType(value as CrmActivityType)}>
                <SelectTrigger>
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {TYPE_OPTIONS.map((option) => (
                    <SelectItem key={option} value={option}>
                      {option.replace('_', ' ')}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="activity-date">Date</Label>
              <Input id="activity-date" type="date" value={activityDate} onChange={(e) => setActivityDate(e.target.value)} />
            </div>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="activity-subject">Subject</Label>
            <Input id="activity-subject" value={subject} onChange={(e) => setSubject(e.target.value)} />
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="activity-description">Description</Label>
            <Textarea id="activity-description" rows={3} value={description} onChange={(e) => setDescription(e.target.value)} />
          </div>

          {error && <p className="text-xs text-destructive">{error}</p>}
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="button" disabled={!subject.trim() || !activityDate || mutation.isPending} onClick={() => mutation.mutate()}>
            Log Activity
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
