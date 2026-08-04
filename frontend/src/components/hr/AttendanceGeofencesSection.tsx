import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { MapPin, Plus } from 'lucide-react'

import {
  createAttendanceGeofence,
  deleteAttendanceGeofence,
  fetchAttendanceGeofences,
  updateAttendanceGeofence,
} from '@/api/attendance-geofences'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { AttendanceGeofence } from '@/types/hr'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'

const emptyForm = { name: '', latitude: '', longitude: '', radiusMeters: '100', isActive: true }

export function AttendanceGeofencesSection({ branchId }: { branchId: number }) {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<AttendanceGeofence | null>(null)
  const [form, setForm] = useState(emptyForm)

  const { data: geofences, isLoading } = useQuery({
    queryKey: ['attendance-geofences', branchId],
    queryFn: () => fetchAttendanceGeofences(branchId),
  })

  useEffect(() => {
    if (!formOpen) return
    setForm(
      editing
        ? {
            name: editing.name,
            latitude: String(editing.latitude),
            longitude: String(editing.longitude),
            radiusMeters: String(editing.radius_meters),
            isActive: editing.is_active,
          }
        : emptyForm,
    )
  }, [formOpen, editing])

  const saveMutation = useMutation({
    mutationFn: () => {
      const payload = {
        branch_id: branchId,
        name: form.name,
        latitude: Number(form.latitude),
        longitude: Number(form.longitude),
        radius_meters: Number(form.radiusMeters),
        is_active: form.isActive,
      }
      return editing ? updateAttendanceGeofence(editing.id, payload) : createAttendanceGeofence(payload)
    },
    onSuccess: () => {
      toast.success(editing ? 'Geofence updated' : 'Geofence added')
      queryClient.invalidateQueries({ queryKey: ['attendance-geofences', branchId] })
      setFormOpen(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const deleteMutation = useMutation({
    mutationFn: (id: number) => deleteAttendanceGeofence(id),
    onSuccess: () => {
      toast.success('Geofence removed')
      queryClient.invalidateQueries({ queryKey: ['attendance-geofences', branchId] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const columns: DataTableColumn<AttendanceGeofence>[] = [
    { key: 'name', header: 'Name', accessor: (row) => row.name },
    { key: 'latitude', header: 'Latitude', accessor: (row) => row.latitude },
    { key: 'longitude', header: 'Longitude', accessor: (row) => row.longitude },
    { key: 'radius_meters', header: 'Radius (m)', accessor: (row) => row.radius_meters },
    {
      key: 'is_active',
      header: 'Status',
      render: (row) => <StatusBadge label={row.is_active ? 'Active' : 'Inactive'} variant={row.is_active ? 'success' : 'neutral'} />,
    },
  ]

  const rowActions: (row: AttendanceGeofence) => DataTableRowAction<AttendanceGeofence>[] = (row) => [
    ...(can('attendance-geofences.update') ? [{ label: 'Edit', onClick: (g: AttendanceGeofence) => { setEditing(g); setFormOpen(true) } }] : []),
    ...(can('attendance-geofences.delete') ? [{ label: 'Delete', destructive: true, onClick: (g: AttendanceGeofence) => deleteMutation.mutate(g.id) }] : []),
  ]

  const canSubmit = form.name && form.latitude && form.longitude && form.radiusMeters

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between space-y-0">
        <CardTitle className="flex items-center gap-2 text-base">
          <MapPin className="h-4 w-4" />
          Geofences
        </CardTitle>
        {can('attendance-geofences.create') && (
          <Button size="sm" onClick={() => { setEditing(null); setFormOpen(true) }}>
            <Plus className="h-4 w-4" />
            Add Geofence
          </Button>
        )}
      </CardHeader>
      <CardContent>
        <p className="mb-4 text-xs text-muted-foreground">
          Valid clock-in/out locations for this branch. Employees must be within a geofence's radius to clock in from the portal.
        </p>
        <DataTable
          columns={columns}
          data={geofences ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No geofences configured"
          emptySubtext="Add a geofence to enable QR-based clock-in for this branch."
        />
      </CardContent>

      <Dialog open={formOpen} onOpenChange={setFormOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{editing ? 'Edit Geofence' : 'Add Geofence'}</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-1.5">
              <Label>Name</Label>
              <Input value={form.name} onChange={(event) => setForm({ ...form, name: event.target.value })} placeholder="Main Office" />
            </div>
            <div className="grid grid-cols-2 gap-4">
              <div className="space-y-1.5">
                <Label>Latitude</Label>
                <Input type="number" step="any" value={form.latitude} onChange={(event) => setForm({ ...form, latitude: event.target.value })} placeholder="0.3476" />
              </div>
              <div className="space-y-1.5">
                <Label>Longitude</Label>
                <Input type="number" step="any" value={form.longitude} onChange={(event) => setForm({ ...form, longitude: event.target.value })} placeholder="32.5825" />
              </div>
            </div>
            <div className="space-y-1.5">
              <Label>Radius (meters)</Label>
              <Input type="number" min="10" value={form.radiusMeters} onChange={(event) => setForm({ ...form, radiusMeters: event.target.value })} />
            </div>
            <label className="flex items-center gap-2 text-sm text-foreground">
              <input type="checkbox" checked={form.isActive} onChange={(event) => setForm({ ...form, isActive: event.target.checked })} className="h-4 w-4 rounded border-input" />
              Active
            </label>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setFormOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!canSubmit || saveMutation.isPending} onClick={() => saveMutation.mutate()}>
              {editing ? 'Save Changes' : 'Add Geofence'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Card>
  )
}
