import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { deletePlatformClient } from '@/api/platform'
import { getApiErrorInfo } from '@/lib/api-errors'
import type { Company } from '@/types/auth'

import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

interface DeletePlatformClientDialogProps {
  client: Company | null
  onOpenChange: (open: boolean) => void
  onDeleted?: () => void
}

/**
 * Permanent, irreversible removal of a client and everything in it. The client
 * code has to be typed back before the button unlocks (the backend checks it too).
 */
export function DeletePlatformClientDialog({ client, onOpenChange, onDeleted }: DeletePlatformClientDialogProps) {
  const queryClient = useQueryClient()
  const [confirmCode, setConfirmCode] = useState('')

  const deleteMutation = useMutation({
    mutationFn: () => deletePlatformClient(client!.id, confirmCode),
    onSuccess: () => {
      toast.success(`${client!.name} has been permanently deleted`)
      queryClient.invalidateQueries({ queryKey: ['platform-clients'] })
      queryClient.removeQueries({ queryKey: ['platform-client', client!.id] })
      handleOpenChange(false)
      onDeleted?.()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const handleOpenChange = (open: boolean) => {
    if (!open) setConfirmCode('')
    onOpenChange(open)
  }

  const matches = !!client && confirmCode.trim().toUpperCase() === client.client_code.toUpperCase()

  return (
    <Dialog open={!!client} onOpenChange={handleOpenChange}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>Delete client permanently</DialogTitle>
          <DialogDescription>
            This deletes <strong className="text-foreground">{client?.name}</strong> and everything in it — users, branches, accounts,
            products, sales, invoices and settings. It cannot be undone, and the client code becomes free to reuse.
          </DialogDescription>
        </DialogHeader>
        <form
          className="space-y-4"
          onSubmit={(event) => {
            event.preventDefault()
            if (matches) deleteMutation.mutate()
          }}
        >
          <div className="space-y-1">
            <Label htmlFor="confirm_code">
              Type <span className="font-mono font-semibold">{client?.client_code}</span> to confirm
            </Label>
            <Input
              id="confirm_code"
              autoComplete="off"
              className="font-mono uppercase"
              value={confirmCode}
              onChange={(event) => setConfirmCode(event.target.value)}
            />
          </div>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => handleOpenChange(false)}>
              Cancel
            </Button>
            <Button type="submit" variant="destructive" disabled={!matches || deleteMutation.isPending}>
              {deleteMutation.isPending ? 'Deleting…' : 'Delete permanently'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
