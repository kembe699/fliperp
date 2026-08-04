import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { fetchDispatch, fetchVehicles, trackDispatch } from '@/api/logistics'
import { fetchBranches } from '@/api/branches'
import { formatDate } from '@/lib/format'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { DispatchStatus } from '@/types/logistics'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const STATUS_VARIANT: Record<DispatchStatus, 'warning' | 'info' | 'success' | 'danger'> = {
  pending: 'warning',
  in_transit: 'info',
  delivered: 'success',
  cancelled: 'danger',
}

export function DispatchDetailPage() {
  const { id } = useParams<{ id: string }>()
  const dispatchId = Number(id)
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const [trackOpen, setTrackOpen] = useState(false)
  const [status, setStatus] = useState('')
  const [location, setLocation] = useState('')
  const [notes, setNotes] = useState('')

  const { data: dispatch, isLoading } = useQuery({ queryKey: ['dispatch', dispatchId], queryFn: () => fetchDispatch(dispatchId), enabled: !!dispatchId })
  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })
  const { data: vehicles } = useQuery({ queryKey: ['vehicles'], queryFn: fetchVehicles })

  const trackMutation = useMutation({
    mutationFn: () => trackDispatch(dispatchId, { status, location: location || null, notes: notes || null }),
    onSuccess: () => {
      toast.success('Tracking update recorded')
      queryClient.invalidateQueries({ queryKey: ['dispatch', dispatchId] })
      setTrackOpen(false)
      setStatus('')
      setLocation('')
      setNotes('')
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  if (isLoading || !dispatch) {
    return <div className="p-6 text-sm text-muted-foreground">Loading dispatch…</div>
  }

  const timeline = [...dispatch.tracking].sort((a, b) => new Date(a.recorded_at).getTime() - new Date(b.recorded_at).getTime())

  return (
    <div>
      <PageHeader
        parent="Dispatches"
        title={dispatch.reference_number}
        action={
          can('dispatches.track') && (
            <Button onClick={() => setTrackOpen(true)}>
              <Plus className="h-4 w-4" />
              Add Tracking Update
            </Button>
          )
        }
      />

      <Card className="mb-6">
        <CardContent className="p-8">
          <div className="mb-8 flex items-start justify-between">
            <div className="grid grid-cols-2 gap-6 sm:grid-cols-4">
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">Branch</p>
                <p className="mt-1 text-sm font-medium text-foreground">{branches?.find((b) => b.id === dispatch.branch_id)?.name ?? '—'}</p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">Vehicle</p>
                <p className="mt-1 text-sm text-foreground">{dispatch.vehicle_id ? vehicles?.find((v) => v.id === dispatch.vehicle_id)?.registration_number ?? `#${dispatch.vehicle_id}` : 'Unassigned'}</p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">Source</p>
                <p className="mt-1 text-sm text-foreground">{dispatch.source_module}</p>
              </div>
              <div>
                <p className="text-xs font-semibold uppercase text-muted-foreground">Dispatch Date</p>
                <p className="mt-1 text-sm text-foreground">{formatDate(dispatch.dispatch_date)}</p>
              </div>
            </div>
            <StatusBadge label={dispatch.status.replace('_', ' ')} variant={STATUS_VARIANT[dispatch.status]} className="text-sm" />
          </div>

          {dispatch.items.length > 0 && (
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                  <th className="py-2">Item</th>
                  <th className="py-2 text-right">Quantity</th>
                  <th className="py-2 text-right">Unit</th>
                </tr>
              </thead>
              <tbody>
                {dispatch.items.map((item) => (
                  <tr key={item.id} className="border-b border-border last:border-b-0">
                    <td className="py-2 text-foreground">{item.item_description}</td>
                    <td className="py-2 text-right text-foreground">{item.quantity}</td>
                    <td className="py-2 text-right text-muted-foreground">{item.unit ?? '—'}</td>
                  </tr>
                ))}
              </tbody>
            </table>
          )}
        </CardContent>
      </Card>

      <p className="mb-3 text-sm font-semibold text-foreground">Tracking Timeline</p>
      <Card>
        <CardContent className="p-6">
          {timeline.length === 0 ? (
            <p className="text-sm text-muted-foreground">No tracking updates recorded yet.</p>
          ) : (
            <ol className="space-y-4">
              {timeline.map((entry, index) => (
                <li key={entry.id} className="flex gap-3">
                  <div className="flex flex-col items-center">
                    <div className={`h-2.5 w-2.5 rounded-full ${index === timeline.length - 1 ? 'bg-primary' : 'bg-muted-foreground/40'}`} />
                    {index < timeline.length - 1 && <div className="mt-1 w-px flex-1 bg-border" />}
                  </div>
                  <div className="pb-2">
                    <p className="text-sm font-medium text-foreground">{entry.status}</p>
                    {entry.location && <p className="text-sm text-muted-foreground">{entry.location}</p>}
                    {entry.notes && <p className="text-sm text-muted-foreground">{entry.notes}</p>}
                    <p className="text-xs text-muted-foreground">{new Date(entry.recorded_at).toLocaleString()}</p>
                  </div>
                </li>
              ))}
            </ol>
          )}
        </CardContent>
      </Card>

      <Dialog open={trackOpen} onOpenChange={setTrackOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Add Tracking Update</DialogTitle>
          </DialogHeader>
          <div className="space-y-4">
            <div className="space-y-1.5">
              <Label>Status</Label>
              <Input value={status} onChange={(event) => setStatus(event.target.value)} placeholder="e.g. in_transit, delivered" />
            </div>
            <div className="space-y-1.5">
              <Label>Location</Label>
              <Input value={location} onChange={(event) => setLocation(event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Notes</Label>
              <Input value={notes} onChange={(event) => setNotes(event.target.value)} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setTrackOpen(false)}>
              Cancel
            </Button>
            <Button disabled={!status || trackMutation.isPending} onClick={() => trackMutation.mutate()}>
              Add Update
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
