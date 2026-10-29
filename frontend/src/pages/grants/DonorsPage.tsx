import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { createDonor, deleteDonor, fetchDonors, updateDonor } from '@/api/grants'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { Donor } from '@/types/grants'

import { PageHeader } from '@/components/layout/PageHeader'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export function DonorsPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()
  const { data, isLoading, isError } = useQuery({ queryKey: ['donors'], queryFn: () => fetchDonors() })
  const donors = data?.data ?? []

  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<Donor | null>(null)
  const [name, setName] = useState('')
  const [code, setCode] = useState('')
  const [email, setEmail] = useState('')
  const [notes, setNotes] = useState('')

  useEffect(() => {
    if (!formOpen) return
    setName(editing?.name ?? '')
    setCode(editing?.code ?? '')
    setEmail(editing?.contact_email ?? '')
    setNotes(editing?.notes ?? '')
  }, [formOpen, editing])

  const invalidate = () => queryClient.invalidateQueries({ queryKey: ['donors'] })

  const saveMutation = useMutation({
    mutationFn: () => {
      const payload = { name, code: code || null, contact_email: email || null, notes: notes || null }
      return editing ? updateDonor(editing.id, payload) : createDonor(payload)
    },
    onSuccess: () => {
      toast.success(editing ? 'Donor updated' : 'Donor added')
      invalidate()
      setFormOpen(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const deleteMutation = useMutation({
    mutationFn: deleteDonor,
    onSuccess: () => {
      toast.success('Donor deleted')
      invalidate()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const columns: DataTableColumn<Donor>[] = [
    { key: 'name', header: 'Donor', accessor: (row) => row.name, sortable: true },
    { key: 'code', header: 'Code', accessor: (row) => row.code ?? '' },
    { key: 'contact_email', header: 'Contact', accessor: (row) => row.contact_email ?? '' },
    { key: 'grants_count', header: 'SOFs', accessor: (row) => row.grants_count ?? 0, sortable: true },
    {
      key: 'is_active',
      header: 'Status',
      accessor: (row) => (row.is_active ? 'Active' : 'Inactive'),
      render: (row) => <StatusBadge label={row.is_active ? 'Active' : 'Inactive'} variant={row.is_active ? 'success' : 'neutral'} />,
    },
  ]

  const rowActions = (): DataTableRowAction<Donor>[] => [
    ...(can('donors.update') ? [{ label: 'Edit', onClick: (row: Donor) => { setEditing(row); setFormOpen(true) } }] : []),
    ...(can('donors.delete') ? [{ label: 'Delete', destructive: true, onClick: (row: Donor) => deleteMutation.mutate(row.id) }] : []),
  ]

  return (
    <div>
      <PageHeader
        parent="Grants"
        title="Donors"
        action={
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('donors.csv', csvColumnsFromDataTable(columns), donors)} />
            {can('donors.create') && (
              <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
                <Plus className="h-4 w-4" />
                New Donor
              </Button>
            )}
          </div>
        }
      />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load donors. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={donors}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No donors yet"
          emptySubtext="Add the donors that fund your SOFs, e.g. ECHO, FCDO or USAID."
        />
      )}

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? 'Edit Donor' : 'New Donor'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="grid grid-cols-3 gap-4">
              <div className="col-span-2 space-y-1.5">
                <Label required>Name</Label>
                <Input value={name} onChange={(event) => setName(event.target.value)} placeholder="European Civil Protection and Humanitarian Aid" />
              </div>
              <div className="space-y-1.5">
                <Label>Code</Label>
                <Input value={code} onChange={(event) => setCode(event.target.value)} placeholder="ECHO" />
              </div>
            </div>
            <div className="space-y-1.5">
              <Label>Contact Email</Label>
              <Input type="email" value={email} onChange={(event) => setEmail(event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Notes</Label>
              <Input value={notes} onChange={(event) => setNotes(event.target.value)} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setFormOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!name.trim() || saveMutation.isPending} onClick={() => saveMutation.mutate()}>
              {editing ? 'Save Changes' : 'Add Donor'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
