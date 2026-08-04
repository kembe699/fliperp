import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Printer, QrCode, RefreshCw } from 'lucide-react'

import { fetchAttendanceQrSvg, regenerateAttendanceQrToken } from '@/api/attendance-geofences'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'

export function AttendanceQrCodeSection({ branchId, branchName }: { branchId: number; branchName: string }) {
  const queryClient = useQueryClient()
  const { can } = usePermissions()
  const [confirmOpen, setConfirmOpen] = useState(false)

  const { data: svg, isLoading } = useQuery({
    queryKey: ['attendance-qr-code', branchId],
    queryFn: () => fetchAttendanceQrSvg(branchId),
  })

  const regenerateMutation = useMutation({
    mutationFn: () => regenerateAttendanceQrToken(branchId),
    onSuccess: () => {
      toast.success('QR token regenerated — the old printed code no longer works')
      queryClient.invalidateQueries({ queryKey: ['attendance-qr-code', branchId] })
      setConfirmOpen(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const handlePrint = () => {
    if (!svg) return
    const printWindow = window.open('', '_blank', 'width=420,height=560')
    if (!printWindow) {
      toast.error('Could not open the print window. Please allow pop-ups for this site.')
      return
    }
    printWindow.document.write(`
      <!doctype html>
      <html>
        <head>
          <title>Attendance QR — ${branchName}</title>
          <style>
            body { font-family: sans-serif; text-align: center; padding: 40px 20px; }
            h1 { font-size: 18px; margin-bottom: 4px; }
            p { color: #444; font-size: 13px; margin-top: 0; }
            .qr { margin: 24px auto; width: 280px; }
            .qr svg { width: 100%; height: auto; }
            .instructions { margin-top: 24px; font-size: 13px; color: #444; }
          </style>
        </head>
        <body>
          <h1>${branchName}</h1>
          <p>Attendance Clock-In / Clock-Out</p>
          <div class="qr">${svg}</div>
          <div class="instructions">Scan this code with your phone camera to clock in or out. You must be at this location.</div>
        </body>
      </html>
    `)
    printWindow.document.close()
    printWindow.focus()
    printWindow.print()
  }

  return (
    <Card>
      <CardHeader className="flex flex-row items-center justify-between space-y-0">
        <CardTitle className="flex items-center gap-2 text-base">
          <QrCode className="h-4 w-4" />
          Attendance QR Code
        </CardTitle>
        <div className="flex gap-2">
          <Button size="sm" variant="outline" onClick={handlePrint} disabled={!svg}>
            <Printer className="h-4 w-4" />
            Print QR Code
          </Button>
          {can('attendance-geofences.update') && (
            <Button size="sm" variant="outline" onClick={() => setConfirmOpen(true)}>
              <RefreshCw className="h-4 w-4" />
              Regenerate Token
            </Button>
          )}
        </div>
      </CardHeader>
      <CardContent>
        <p className="mb-4 text-xs text-muted-foreground">
          Print and post this code at {branchName}. Employees scan it from the portal to clock in/out — it also verifies they're within a configured geofence.
        </p>
        <div className="flex justify-center">
          {isLoading ? (
            <p className="py-8 text-sm text-muted-foreground">Loading QR code…</p>
          ) : svg ? (
            <div className="w-56 rounded-xl border border-border bg-white p-4" dangerouslySetInnerHTML={{ __html: svg }} />
          ) : (
            <p className="py-8 text-sm text-muted-foreground">No QR code yet.</p>
          )}
        </div>
      </CardContent>

      <Dialog open={confirmOpen} onOpenChange={setConfirmOpen}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Regenerate QR Token?</DialogTitle>
          </DialogHeader>
          <p className="text-sm text-muted-foreground">
            This invalidates the current QR code immediately. Any printed copies posted at {branchName} will stop working and employees
            won't be able to clock in/out until you print and post the new one. Continue?
          </p>
          <DialogFooter>
            <Button variant="outline" onClick={() => setConfirmOpen(false)}>
              Cancel
            </Button>
            <Button variant="destructive" disabled={regenerateMutation.isPending} onClick={() => regenerateMutation.mutate()}>
              Regenerate
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Card>
  )
}
