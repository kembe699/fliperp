import { useEffect, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus, Sparkles } from 'lucide-react'

import { createUnitOfMeasure, deleteUnitOfMeasure, fetchUnitsOfMeasure, seedUnitsOfMeasure, updateUnitOfMeasure } from '@/api/inventory'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { UnitOfMeasure } from '@/types/inventory'

import { PageHeader } from '@/components/layout/PageHeader'
import { SearchBar } from '@/components/shared/SearchBar'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export function UnitsOfMeasurePage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const { data: units, isLoading, isError } = useQuery({ queryKey: ['units-of-measure'], queryFn: fetchUnitsOfMeasure })

  const [search, setSearch] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<UnitOfMeasure | null>(null)
  const [name, setName] = useState('')
  const [abbreviation, setAbbreviation] = useState('')

  const filtered = useMemo(() => {
    return (units ?? []).filter(
      (row) => !search || row.name.toLowerCase().includes(search.toLowerCase()) || row.abbreviation.toLowerCase().includes(search.toLowerCase()),
    )
  }, [units, search])

  useEffect(() => {
    if (!formOpen) return
    setName(editing?.name ?? '')
    setAbbreviation(editing?.abbreviation ?? '')
  }, [formOpen, editing])

  const saveMutation = useMutation({
    mutationFn: () => {
      const payload = { name, abbreviation }
      return editing ? updateUnitOfMeasure(editing.id, payload) : createUnitOfMeasure(payload)
    },
    onSuccess: () => {
      toast.success(editing ? 'Unit of measure updated' : 'Unit of measure created')
      queryClient.invalidateQueries({ queryKey: ['units-of-measure'] })
      setFormOpen(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const deleteMutation = useMutation({
    mutationFn: deleteUnitOfMeasure,
    onSuccess: () => {
      toast.success('Unit of measure deleted')
      queryClient.invalidateQueries({ queryKey: ['units-of-measure'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const seedMutation = useMutation({
    mutationFn: seedUnitsOfMeasure,
    onSuccess: (seeded) => {
      toast.success(`${seeded.length} units of measure available`)
      queryClient.invalidateQueries({ queryKey: ['units-of-measure'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const columns: DataTableColumn<UnitOfMeasure>[] = [
    { key: 'name', header: 'Name', accessor: (row) => row.name, sortable: true },
    { key: 'abbreviation', header: 'Abbreviation', accessor: (row) => row.abbreviation },
  ]

  const rowActions: (row: UnitOfMeasure) => DataTableRowAction<UnitOfMeasure>[] = (row) => [
    ...(can('units-of-measure.update') ? [{ label: 'Edit', onClick: (u: UnitOfMeasure) => { setEditing(u); setFormOpen(true) } }] : []),
    ...(can('units-of-measure.delete') ? [{ label: 'Delete', destructive: true, onClick: (u: UnitOfMeasure) => deleteMutation.mutate(u.id) }] : []),
  ]

  return (
    <div>
      <PageHeader
        parent="Inventory"
        title="Units of Measure"
        action={
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('units-of-measure.csv', csvColumnsFromDataTable(columns), filtered)} />
            {can('units-of-measure.create') && (
              <Button variant="outline" onClick={() => seedMutation.mutate()} disabled={seedMutation.isPending}>
                <Sparkles className="h-4 w-4" />
                {seedMutation.isPending ? 'Seeding…' : 'Seed Common Units'}
              </Button>
            )}
            {can('units-of-measure.create') && (
              <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
                <Plus className="h-4 w-4" />
                New Unit
              </Button>
            )}
          </div>
        }
      />

      <div className="mb-4">
        <SearchBar
          options={[{ value: 'name', label: 'Name / Abbreviation' }]}
          placeholder="Search units of measure…"
          onSearch={(_by, query) => setSearch(query)}
          onClear={() => setSearch('')}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load units of measure. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={filtered}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No units of measure found"
          emptySubtext="Seed the common set to get started, or create your own."
        />
      )}

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? 'Edit Unit of Measure' : 'New Unit of Measure'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-1.5">
              <Label>Name</Label>
              <Input value={name} onChange={(event) => setName(event.target.value)} placeholder="Kilogram" />
            </div>
            <div className="space-y-1.5">
              <Label>Abbreviation</Label>
              <Input value={abbreviation} onChange={(event) => setAbbreviation(event.target.value)} placeholder="Kg" />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setFormOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!name || !abbreviation || saveMutation.isPending} onClick={() => saveMutation.mutate()}>
              {editing ? 'Save Changes' : 'Create Unit'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
