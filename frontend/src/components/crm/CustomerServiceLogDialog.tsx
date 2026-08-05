import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createCrmCustomerService, fetchCrmServices } from '@/api/crm'
import { getApiErrorInfo } from '@/lib/api-errors'
import { toISODate } from '@/lib/format'
import type { CrmDeal } from '@/types/crm'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

interface CustomerServiceLogDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  deal: CrmDeal | null
}

export function CustomerServiceLogDialog({ open, onOpenChange, deal }: CustomerServiceLogDialogProps) {
  const queryClient = useQueryClient()
  const [serviceId, setServiceId] = useState<string>('')
  const [price, setPrice] = useState('')
  const [startDate, setStartDate] = useState('')
  const [error, setError] = useState<string | null>(null)

  const { data: services } = useQuery({
    queryKey: ['crm-services-all'],
    queryFn: () => fetchCrmServices({ per_page: 100, is_active: true }),
    enabled: open,
  })

  useEffect(() => {
    if (!open || !deal) return
    setServiceId(deal.crm_service_id ? String(deal.crm_service_id) : '')
    setPrice(String(deal.value))
    setStartDate(toISODate(new Date()))
    setError(null)
  }, [open, deal])

  const mutation = useMutation({
    mutationFn: () =>
      createCrmCustomerService({
        customer_id: deal!.customer_id!,
        crm_service_id: Number(serviceId),
        deal_id: deal!.id,
        price_charged: Number(price),
        start_date: startDate,
      }),
    onSuccess: () => {
      toast.success('Customer service logged')
      queryClient.invalidateQueries({ queryKey: ['crm-customer-services'] })
      queryClient.invalidateQueries({ queryKey: ['crm-report-summary'] })
      onOpenChange(false)
    },
    onError: (err) => {
      const info = getApiErrorInfo(err)
      setError(info.message)
    },
  })

  if (!deal) return null

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>Log Customer Service</DialogTitle>
          <DialogDescription>
            "{deal.title}" was just marked Closed Won. Record the service delivered to this customer, or skip for now.
          </DialogDescription>
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
              <Label htmlFor="price-charged">Price Charged</Label>
              <Input id="price-charged" type="number" step="0.01" min="0" value={price} onChange={(e) => setPrice(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="start-date">Start Date</Label>
              <Input id="start-date" type="date" value={startDate} onChange={(e) => setStartDate(e.target.value)} />
            </div>
          </div>

          {error && <p className="text-xs text-destructive">{error}</p>}
        </div>

        <DialogFooter>
          <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
            Skip
          </Button>
          <Button type="button" disabled={!serviceId || !price || !startDate || mutation.isPending} onClick={() => mutation.mutate()}>
            Log Service
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
