import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { createBranch, deleteBranch, fetchBranches, updateBranch, type Branch } from '@/api/branches'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const pillTrigger = 'h-8 w-40 text-sm'

export function BranchesSettingsPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const { data: branches, isLoading, isError } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })

  const [status, setStatus] = useState('all')
  const [search, setSearch] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<Branch | null>(null)
  const [name, setName] = useState('')
  const [code, setCode] = useState('')
  const [address, setAddress] = useState('')
  const [phone, setPhone] = useState('')
  const [isMain, setIsMain] = useState(false)

  const filtered = useMemo(() => {
    return (branches ?? [])
      .filter((row) => status === 'all' || (status === 'active' ? row.is_active : !row.is_active))
      .filter((row) => !search || row.name.toLowerCase().includes(search.toLowerCase()) || row.code.toLowerCase().includes(search.toLowerCase()))
  }, [branches, status, search])

  useEffect(() => {
    if (!formOpen) return
    setName(editing?.name ?? '')
    setCode(editing?.code ?? '')
    setAddress(editing?.address ?? '')
    setPhone(editing?.phone ?? '')
    setIsMain(editing?.is_main ?? false)
  }, [formOpen, editing])

  const saveMutation = useMutation({
    mutationFn: () => {
      const payload = { name, code, address: address || null, phone: phone || null, is_main: isMain }
      return editing ? updateBranch(editing.id, payload) : createBranch(payload)
    },
    onSuccess: () => {
      toast.success(editing ? 'Branch updated' : 'Branch created')
      queryClient.invalidateQueries({ queryKey: ['branches'] })
      setFormOpen(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const deleteMutation = useMutation({
    mutationFn: deleteBranch,
    onSuccess: () => {
      toast.success('Branch deleted')
      queryClient.invalidateQueries({ queryKey: ['branches'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const columns: DataTableColumn<Branch>[] = [
    { key: 'name', header: 'Name', accessor: (row) => row.name, sortable: true },
    { key: 'code', header: 'Code', accessor: (row) => row.code },
    { key: 'address', header: 'Address', accessor: (row) => row.address ?? '—' },
    { key: 'is_main', header: 'Main', render: (row) => (row.is_main ? <StatusBadge label="Main" variant="info" /> : '—') },
    { key: 'is_active', header: 'Status', render: (row) => <StatusBadge label={row.is_active ? 'Active' : 'Inactive'} variant={row.is_active ? 'success' : 'neutral'} /> },
  ]

  const rowActions: (row: Branch) => DataTableRowAction<Branch>[] = (row) => [
    ...(can('branches.update') ? [{ label: 'Edit', onClick: (b: Branch) => { setEditing(b); setFormOpen(true) } }] : []),
    ...(can('branches.delete') && !row.is_main ? [{ label: 'Delete', destructive: true, onClick: (b: Branch) => deleteMutation.mutate(b.id) }] : []),
  ]

  return (
    <div>
      <PageHeader
        parent="Settings"
        title="Branches"
        action={
          <div className="flex gap-2">
            {can('branches.create') && (
              <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
                <Plus className="h-4 w-4" />
                New Branch
              </Button>
            )}
          </div>
        }
      />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load branches. Please try again.</p>
      ) : (
        <DataTable
          onSearchChange={setSearch}
          toolbarExtra={
            <>
              <Select value={status} onValueChange={setStatus}>
                <SelectTrigger className={pillTrigger}>
                  <SelectValue placeholder="Status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All statuses</SelectItem>
                  <SelectItem value="active">Active</SelectItem>
                  <SelectItem value="inactive">Inactive</SelectItem>
                </SelectContent>
              </Select>
            </>
          }
          columns={columns}
          data={filtered}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No branches found"
          emptySubtext="Create a branch to organize your company's locations."
        />
      )}

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? 'Edit Branch' : 'New Branch'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label required>Name</Label>
                <Input value={name} onChange={(event) => setName(event.target.value)} />
              </div>
              <div className="space-y-1.5">
                <Label required>Code</Label>
                <Input value={code} onChange={(event) => setCode(event.target.value)} />
              </div>
            </div>
            <div className="space-y-1.5">
              <Label>Address</Label>
              <Input value={address} onChange={(event) => setAddress(event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Phone</Label>
              <Input value={phone} onChange={(event) => setPhone(event.target.value)} />
            </div>
            <label className="flex items-center gap-2 text-sm text-foreground">
              <input type="checkbox" checked={isMain} onChange={(event) => setIsMain(event.target.checked)} className="h-4 w-4 rounded border-input" />
              Main branch
            </label>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setFormOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!name || !code || saveMutation.isPending} onClick={() => saveMutation.mutate()}>
              {editing ? 'Save Changes' : 'Create Branch'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
