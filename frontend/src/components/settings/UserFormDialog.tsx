import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createUser, fetchRoles, updateUser } from '@/api/settings'
import { fetchBranches } from '@/api/branches'
import { getApiErrorInfo } from '@/lib/api-errors'
import type { SettingsUser } from '@/types/settings'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

export function UserFormDialog({
  open,
  onOpenChange,
  user,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  user: SettingsUser | null
}) {
  const queryClient = useQueryClient()
  const { data: roles } = useQuery({ queryKey: ['roles'], queryFn: fetchRoles, enabled: open })
  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches, enabled: open })

  const [name, setName] = useState('')
  const [email, setEmail] = useState('')
  const [password, setPassword] = useState('')
  const [phone, setPhone] = useState('')
  const [branchId, setBranchId] = useState('none')
  const [isActive, setIsActive] = useState(true)
  const [selectedRoles, setSelectedRoles] = useState<string[]>([])

  useEffect(() => {
    if (!open) return
    setName(user?.name ?? '')
    setEmail(user?.email ?? '')
    setPassword('')
    setPhone(user?.phone ?? '')
    setBranchId(user?.branch_id ? String(user.branch_id) : 'none')
    setIsActive(user?.is_active ?? true)
    setSelectedRoles(user?.roles ?? [])
  }, [open, user])

  const toggleRole = (roleName: string) =>
    setSelectedRoles((prev) => (prev.includes(roleName) ? prev.filter((r) => r !== roleName) : [...prev, roleName]))

  const mutation = useMutation({
    mutationFn: () => {
      const payload = {
        name,
        email,
        phone: phone || null,
        branch_id: branchId === 'none' ? null : Number(branchId),
        is_active: isActive,
        roles: selectedRoles,
        ...(password ? { password } : {}),
      }
      return user ? updateUser(user.id, payload) : createUser({ ...payload, password: password || '' })
    },
    onSuccess: () => {
      toast.success(user ? 'User updated' : 'User created')
      queryClient.invalidateQueries({ queryKey: ['users'] })
      onOpenChange(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSubmit = name && email && (user || password.length >= 8)

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>{user ? 'Edit User' : 'New User'}</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Name</Label>
              <Input value={name} onChange={(event) => setName(event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Email</Label>
              <Input type="email" value={email} onChange={(event) => setEmail(event.target.value)} />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>{user ? 'New Password (optional)' : 'Password'}</Label>
              <Input type="password" value={password} onChange={(event) => setPassword(event.target.value)} placeholder="Min 8 characters" />
            </div>
            <div className="space-y-1.5">
              <Label>Phone</Label>
              <Input value={phone} onChange={(event) => setPhone(event.target.value)} />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label>Branch</Label>
            <Select value={branchId} onValueChange={setBranchId}>
              <SelectTrigger>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="none">No branch</SelectItem>
                {branches?.map((branch) => (
                  <SelectItem key={branch.id} value={String(branch.id)}>
                    {branch.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label>Roles</Label>
            <div className="grid grid-cols-2 gap-2 rounded-lg border border-border p-3">
              {roles?.map((role) => (
                <label key={role.id} className="flex items-center gap-2 text-sm text-foreground">
                  <input type="checkbox" checked={selectedRoles.includes(role.name)} onChange={() => toggleRole(role.name)} className="h-4 w-4 rounded border-input" />
                  {role.name.replace(/_/g, ' ')}
                </label>
              ))}
            </div>
          </div>
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
            {user ? 'Save Changes' : 'Create User'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
