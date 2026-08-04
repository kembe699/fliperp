import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ChevronDown } from 'lucide-react'

import { createRole, fetchPermissions, updateRole } from '@/api/settings'
import { getApiErrorInfo } from '@/lib/api-errors'
import type { Role } from '@/types/settings'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

function groupByModule(names: string[]): Record<string, string[]> {
  const groups: Record<string, string[]> = {}
  for (const name of names) {
    const [module] = name.split('.')
    ;(groups[module] ??= []).push(name)
  }
  return groups
}

export function RoleFormDialog({
  open,
  onOpenChange,
  role,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  role: Role | null
}) {
  const queryClient = useQueryClient()
  const { data: permissions } = useQuery({ queryKey: ['permissions'], queryFn: fetchPermissions, enabled: open })

  const [name, setName] = useState('')
  const [selected, setSelected] = useState<string[]>([])

  useEffect(() => {
    if (!open) return
    setName(role?.name ?? '')
    setSelected(role?.permissions ?? [])
  }, [open, role])

  const groups = useMemo(() => groupByModule((permissions ?? []).map((p) => p.name)), [permissions])

  const togglePermission = (permissionName: string) =>
    setSelected((prev) => (prev.includes(permissionName) ? prev.filter((p) => p !== permissionName) : [...prev, permissionName]))

  const toggleModule = (moduleNames: string[], allSelected: boolean) =>
    setSelected((prev) => (allSelected ? prev.filter((p) => !moduleNames.includes(p)) : [...new Set([...prev, ...moduleNames])]))

  const mutation = useMutation({
    mutationFn: () => {
      const payload = { name, permissions: selected }
      return role ? updateRole(role.id, payload) : createRole(payload)
    },
    onSuccess: () => {
      toast.success(role ? 'Role updated' : 'Role created')
      queryClient.invalidateQueries({ queryKey: ['roles'] })
      onOpenChange(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const canSubmit = name

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-2xl">
        <DialogHeader>
          <DialogTitle>{role ? 'Edit Role' : 'New Role'}</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label>Role Name</Label>
            <Input value={name} onChange={(event) => setName(event.target.value)} placeholder="warehouse_clerk" />
          </div>

          <div className="space-y-1.5">
            <Label>Permissions</Label>
            <div className="max-h-96 space-y-1 overflow-y-auto rounded-lg border border-border p-2">
              {Object.entries(groups)
                .sort(([a], [b]) => a.localeCompare(b))
                .map(([module, moduleNames]) => {
                  const allSelected = moduleNames.every((n) => selected.includes(n))
                  return (
                    <details key={module} className="rounded-md border border-border/60">
                      <summary className="flex cursor-pointer list-none items-center justify-between gap-2 px-3 py-2 text-sm font-medium text-foreground">
                        <span className="flex items-center gap-2">
                          <ChevronDown className="h-3.5 w-3.5 text-muted-foreground transition-transform [details[open]_&]:rotate-180" />
                          {module.replace(/-/g, ' ')}
                        </span>
                        <label className="flex items-center gap-1.5 text-xs font-normal text-muted-foreground" onClick={(e) => e.stopPropagation()}>
                          <input type="checkbox" checked={allSelected} onChange={() => toggleModule(moduleNames, allSelected)} className="h-3.5 w-3.5 rounded border-input" />
                          Select all
                        </label>
                      </summary>
                      <div className="grid grid-cols-2 gap-1.5 border-t border-border/60 p-3">
                        {moduleNames.map((permissionName) => (
                          <label key={permissionName} className="flex items-center gap-2 text-sm text-foreground">
                            <input
                              type="checkbox"
                              checked={selected.includes(permissionName)}
                              onChange={() => togglePermission(permissionName)}
                              className="h-4 w-4 rounded border-input"
                            />
                            {permissionName.split('.').slice(1).join('.')}
                          </label>
                        ))}
                      </div>
                    </details>
                  )
                })}
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button disabled={!canSubmit || mutation.isPending} onClick={() => mutation.mutate()}>
            {role ? 'Save Changes' : 'Create Role'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
