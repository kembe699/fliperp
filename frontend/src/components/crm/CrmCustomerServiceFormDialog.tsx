import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createCrmCustomerService, fetchCrmServices } from '@/api/crm'
import { getApiErrorInfo } from '@/lib/api-errors'
import { toISODate } from '@/lib/format'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

interface CrmCustomerServiceFormDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  customerId: number
}

export function CrmCustomerServiceFormDialog({ open, onOpenChange, customerId }: CrmCustomerServiceFormDialogProps) {
  const queryClient = useQueryClient()
  const [serviceId, setServiceId] = useState('')
  const [price, setPrice] = useState('')
  const [startDate, setStartDate] = useState('')
  const [error, setError] = useState<string | null>(null)

  const { data: services } = useQuery({
    queryKey: ['crm-services-all'],
    queryFn: () => fetchCrmServices({ per_page: 100, is_active: true }),
    enabled: open,
  })

  useEffect(() => {
    if (!open) return
    setServiceId('')
    setPrice('')
    setStartDate(toISODate(new Date()))
    setError(null)
  }, [open])

  const mutation = useMutation({
    mutationFn: () =>
      createCrmCustomerService({
        customer_id: customerId,
        crm_service_id: Number(serviceId),
        price_charged: Number(price),
        start_date: startDate,
      }),
    onSuccess: () => {
      toast.success('Customer service logged')
      queryClient.invalidateQueries({ queryKey: ['crm-service-statement', customerId] })
      onOpenChange(false)
    },
    onError: (err) => setError(getApiErrorInfo(err).message),
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Log Customer Service</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="space-y-1.5">
            <Label>Service</Label>
            <Select value={serviceId} onValueChange={setServiceId}>
              <SelectTrigger>
                <SelectValue placeholder="Select a service" />
              </SelectTrigger>
              <SelectContent>
                {services?.data.map((service) => (
                  <SelectItem key={service.id} value={String(service.id)}>
                    {service.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="cs-price">Price Charged</Label>
              <Input id="cs-price" type="number" step="0.01" min="0" value={price} onChange={(e) => setPrice(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="cs-start">Start Date</Label>
              <Input id="cs-start" type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} />
            </div>
          </div>

          {error && <p className="text-xs text-destructive">{error}</p>}
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button type="button" disabled={!serviceId || !price || !startDate || mutation.isPending} onClick={() => mutation.mutate()}>
            Log Service
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
