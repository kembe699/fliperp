import { useEffect, useState } from 'react'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createSupplier, updateSupplier } from '@/api/procurement'
import { getApiErrorInfo } from '@/lib/api-errors'
import type { Supplier } from '@/types/procurement'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

export function SupplierFormDialog({
  open,
  onOpenChange,
  supplier,
}: {
  open: boolean
  onOpenChange: (open: boolean) => void
  supplier: Supplier | null
}) {
  const queryClient = useQueryClient()

  const [name, setName] = useState('')
  const [contactPerson, setContactPerson] = useState('')
  const [phone, setPhone] = useState('')
  const [email, setEmail] = useState('')
  const [address, setAddress] = useState('')
  const [taxId, setTaxId] = useState('')
  const [paymentTermsDays, setPaymentTermsDays] = useState('30')

  useEffect(() => {
    if (!open) return
    setName(supplier?.name ?? '')
    setContactPerson(supplier?.contact_person ?? '')
    setPhone(supplier?.phone ?? '')
    setEmail(supplier?.email ?? '')
    setAddress(supplier?.address ?? '')
    setTaxId(supplier?.tax_id ?? '')
    setPaymentTermsDays(supplier ? String(supplier.payment_terms_days) : '30')
  }, [open, supplier])

  const mutation = useMutation({
    mutationFn: () => {
      const payload = {
        name,
        contact_person: contactPerson || null,
        phone: phone || null,
        email: email || null,
        address: address || null,
        tax_id: taxId || null,
        payment_terms_days: Number(paymentTermsDays),
      }
      return supplier ? updateSupplier(supplier.id, payload) : createSupplier(payload)
    },
    onSuccess: () => {
      toast.success(supplier ? 'Supplier updated' : 'Supplier created')
      queryClient.invalidateQueries({ queryKey: ['suppliers'] })
      onOpenChange(false)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{supplier ? 'Edit Supplier' : 'New Supplier'}</DialogTitle>
        </DialogHeader>

        <div className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Name</Label>
              <Input value={name} onChange={(event) => setName(event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Contact Person</Label>
              <Input value={contactPerson} onChange={(event) => setContactPerson(event.target.value)} />
            </div>
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Phone</Label>
              <Input value={phone} onChange={(event) => setPhone(event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Email</Label>
              <Input type="email" value={email} onChange={(event) => setEmail(event.target.value)} />
            </div>
          </div>
          <div className="space-y-1.5">
            <Label>Address</Label>
            <Input value={address} onChange={(event) => setAddress(event.target.value)} />
          </div>
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Tax ID</Label>
              <Input value={taxId} onChange={(event) => setTaxId(event.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Payment Terms (days)</Label>
              <Input type="number" min="0" value={paymentTermsDays} onChange={(event) => setPaymentTermsDays(event.target.value)} />
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" onClick={() => onOpenChange(false)}>
            Cancel
          </Button>
          <Button disabled={!name || mutation.isPending} onClick={() => mutation.mutate()}>
            {supplier ? 'Save Changes' : 'Create Supplier'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
