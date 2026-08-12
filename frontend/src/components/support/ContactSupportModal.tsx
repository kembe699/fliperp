import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { CheckCircle2 } from 'lucide-react'

import { createSupportTicket } from '@/api/support'
import { getApiErrorInfo } from '@/lib/api-errors'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'

export function ContactSupportModal({ open, onOpenChange }: { open: boolean; onOpenChange: (open: boolean) => void }) {
  const queryClient = useQueryClient()
  const [subject, setSubject] = useState('')
  const [description, setDescription] = useState('')
  const [submitted, setSubmitted] = useState(false)

  const reset = () => {
    setSubject('')
    setDescription('')
    setSubmitted(false)
  }

  const mutation = useMutation({
    mutationFn: () => createSupportTicket({ subject, description }),
    onSuccess: () => {
      setSubmitted(true)
      queryClient.invalidateQueries({ queryKey: ['support-tickets'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const handleClose = (nextOpen: boolean) => {
    if (!nextOpen) reset()
    onOpenChange(nextOpen)
  }

  return (
    <Dialog open={open} onOpenChange={handleClose}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Contact Support</DialogTitle>
        </DialogHeader>

        {submitted ? (
          <div className="flex flex-col items-center gap-3 py-6 text-center">
            <CheckCircle2 className="h-10 w-10 text-success" />
            <p className="text-sm font-medium text-foreground">Your ticket has been submitted</p>
            <p className="text-sm text-muted-foreground">Our team will get back to you shortly. You can track it under My Support Tickets.</p>
            <Button className="mt-2" onClick={() => handleClose(false)}>
              Done
            </Button>
          </div>
        ) : (
          <>
            <div className="space-y-4">
              <div className="space-y-1.5">
                <Label>Subject</Label>
                <Input value={subject} onChange={(event) => setSubject(event.target.value)} placeholder="Briefly describe the issue" />
              </div>
              <div className="space-y-1.5">
                <Label>Description</Label>
                <Textarea
                  value={description}
                  onChange={(event) => setDescription(event.target.value)}
                  rows={5}
                  placeholder="What's happening? Include any steps to reproduce, if relevant."
                />
              </div>
            </div>
            <DialogFooter>
              <Button variant="outline" onClick={() => handleClose(false)}>
                Cancel
              </Button>
              <Button disabled={!subject || !description || mutation.isPending} onClick={() => mutation.mutate()}>
                {mutation.isPending ? 'Submitting…' : 'Submit Ticket'}
              </Button>
            </DialogFooter>
          </>
        )}
      </DialogContent>
    </Dialog>
  )
}
