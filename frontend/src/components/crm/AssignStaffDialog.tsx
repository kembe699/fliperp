import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { assignCrmAccount } from '@/api/crm'
import { fetchUsers } from '@/api/settings'
import { getApiErrorInfo } from '@/lib/api-errors'
import type { CrmAssignmentRole } from '@/types/crm'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

interface AssignStaffDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  customerId: number
}

export function AssignStaffDialog({ open, onOpenChange, customerId }: AssignStaffDialogProps) {
  const queryClient = useQueryClient()
  const [userId, setUserId] = useState('')
  const [role, setRole] = useState<CrmAssignmentRole>('primary')
  const [error, setError] = useState<string | null>(null)

  const { data: users } = useQuery({ queryKey: ['settings-users-all'], queryFn: () => fetchUsers({ per_page: 100 }), enabled: open })

  useEffect(() => {
    if (!open) return
    setUserId('')
    setRole('primary')
    setError(null)
  }, [open])

  const mutation = useMutation({
    mutationFn: () => assignCrmAccount({ customer_id: customerId, user_id: Number(userId), role }),
    onSuccess: () => {
      toast.success('Staff member assigned')
      queryClient.invalidateQueries({ queryKey: ['crm-account-assignments', customerId] })
      onOpenChange(false)
    },
    onError: (err) => setError(getApiErrorInfo(err).message),
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Assign Staff Member</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label>Staff Member</Label>
            <Select value={userId} onValueChange={setUserId}>
              <SelectTrigger>
                <SelectValue placeholder="Select a staff member" />
              </SelectTrigger>
              <SelectContent>
                {users?.data.map((u) => (
                  <SelectItem key={u.id} value={String(u.id)}>
                    {u.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-1.5">
            <Label>Role</Label>
            <Select value={role} onValueChange={(value) => setRole(value as CrmAssignmentRole)}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="primary">Primary</SelectItem>
                <SelectItem value="support">Support</SelectItem>
              </SelectContent>
            </Select>
          </div>

          {error && <p className="text-xs text-destructive">{error}</p>}
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="button" disabled={!userId || mutation.isPending} onClick={() => mutation.mutate()}>
            Assign
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
