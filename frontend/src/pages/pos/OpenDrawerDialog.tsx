import { useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Lock } from 'lucide-react'

import { openCashDrawer } from '@/api/pos'
import { getApiErrorInfo } from '@/lib/api-errors'

import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription } from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export function OpenDrawerDialog({ branchId }: { branchId?: number }) {
  const queryClient = useQueryClient()
  const [openingFloat, setOpeningFloat] = useState('')

  const mutation = useMutation({
    mutationFn: () => openCashDrawer({ branch_id: branchId, opening_float: Number(openingFloat) }),
    onSuccess: () => {
      toast.success('Cash drawer opened')
      queryClient.invalidateQueries({ queryKey: ['cash-drawer-current'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  return (
    <Dialog open>
      <DialogContent onInteractOutside={(event) => event.preventDefault()} onEscapeKeyDown={(event) => event.preventDefault()} hideClose>
        <DialogHeader>
          <div className="mb-2 flex h-10 w-10 items-center justify-center rounded-full bg-accent text-primary">
            <Lock className="h-5 w-5" />
          </div>
          <DialogTitle>Open Cash Drawer</DialogTitle>
          <DialogDescription>You need an open cash drawer session before you can make sales at this terminal.</DialogDescription>
        </DialogHeader>

        <form
          onSubmit={(event) => {
            event.preventDefault()
            mutation.mutate()
          }}
          className="space-y-4"
        >
          <div className="space-y-1.5">
            <Label htmlFor="opening_float">Opening Float</Label>
            <Input
              id="opening_float"
              type="number"
              step="0.01"
              min="0"
              required
              autoFocus
              value={openingFloat}
              onChange={(event) => setOpeningFloat(event.target.value)}
            />
          </div>
          <Button type="submit" className="w-full" disabled={mutation.isPending}>
            {mutation.isPending ? 'Opening…' : 'Open Drawer'}
          </Button>
        </form>
      </DialogContent>
    </Dialog>
  )
}
