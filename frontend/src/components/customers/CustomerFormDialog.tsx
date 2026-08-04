import { useEffect } from 'react'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createCustomer, updateCustomer, type CustomerFormValues } from '@/api/customers'
import { fetchBranches } from '@/api/branches'
import { applyFieldErrors, getApiErrorInfo } from '@/lib/api-errors'
import type { Customer } from '@/types/customer'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const customerSchema = z.object({
  name: z.string().min(1, 'Name is required'),
  phone: z.string().min(1, 'Phone is required'),
  email: z.string().email('Enter a valid email').optional().or(z.literal('')),
  address: z.string().optional().or(z.literal('')),
  tax_id: z.string().optional().or(z.literal('')),
  customer_type: z.enum(['walk_in', 'regular', 'credit']),
  credit_limit: z.number().min(0, 'Must be 0 or more'),
  branch_id: z.string().optional(),
})

type CustomerFormSchema = z.infer<typeof customerSchema>

interface CustomerFormDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  customer?: Customer | null
}

export function CustomerFormDialog({ open, onOpenChange, customer }: CustomerFormDialogProps) {
  const queryClient = useQueryClient()
  const isEdit = !!customer

  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches, enabled: open })

  const {
    register,
    handleSubmit,
    control,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<CustomerFormSchema>({
    resolver: zodResolver(customerSchema),
    defaultValues: {
      name: '',
      phone: '',
      email: '',
      address: '',
      tax_id: '',
      customer_type: 'walk_in',
      credit_limit: 0,
      branch_id: undefined,
    },
  })

  useEffect(() => {
    if (!open) return
    reset({
      name: customer?.name ?? '',
      phone: customer?.phone ?? '',
      email: customer?.email ?? '',
      address: customer?.address ?? '',
      tax_id: customer?.tax_id ?? '',
      customer_type: customer?.customer_type ?? 'walk_in',
      credit_limit: customer?.credit_limit ?? 0,
      branch_id: customer?.branch_id ? String(customer.branch_id) : undefined,
    })
  }, [open, customer, reset])

  const mutation = useMutation({
    mutationFn: async (values: CustomerFormSchema) => {
      const payload: CustomerFormValues = {
        name: values.name,
        phone: values.phone,
        email: values.email || null,
        address: values.address || null,
        tax_id: values.tax_id || null,
        customer_type: values.customer_type,
        credit_limit: values.credit_limit,
        branch_id: values.branch_id ? Number(values.branch_id) : null,
      }
      return isEdit ? updateCustomer(customer!.id, payload) : createCustomer(payload)
    },
    onSuccess: () => {
      toast.success(isEdit ? 'Customer updated' : 'Customer created')
      queryClient.invalidateQueries({ queryKey: ['customers'] })
      onOpenChange(false)
    },
    onError: (error) => {
      const info = getApiErrorInfo(error)
      if (info.errors) {
        const unmapped = applyFieldErrors(info.errors, setError)
        unmapped.forEach((message) => toast.error(message))
      } else {
        toast.error(info.message)
      }
    },
  })

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent>
        <DialogHeader>
          <DialogTitle>{isEdit ? 'Edit Customer' : 'New Customer'}</DialogTitle>
        </DialogHeader>

        <form onSubmit={handleSubmit((values) => mutation.mutate(values))} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="name">Name</Label>
              <Input id="name" {...register('name')} />
              {errors.name && <p className="text-xs text-destructive">{errors.name.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="phone">Phone</Label>
              <Input id="phone" {...register('phone')} />
              {errors.phone && <p className="text-xs text-destructive">{errors.phone.message}</p>}
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="email">Email</Label>
              <Input id="email" type="email" {...register('email')} />
              {errors.email && <p className="text-xs text-destructive">{errors.email.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="tax_id">Tax ID</Label>
              <Input id="tax_id" {...register('tax_id')} />
            </div>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="address">Address</Label>
            <Textarea id="address" {...register('address')} />
          </div>

          <div className="grid grid-cols-3 gap-4">
            <div className="space-y-1.5">
              <Label>Customer Type</Label>
              <Controller
                control={control}
                name="customer_type"
                render={({ field }) => (
                  <Select value={field.value} onValueChange={field.onChange}>
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="walk_in">Walk-in</SelectItem>
                      <SelectItem value="regular">Regular</SelectItem>
                      <SelectItem value="credit">Credit</SelectItem>
                    </SelectContent>
                  </Select>
                )}
              />
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="credit_limit">Credit Limit</Label>
              <Input id="credit_limit" type="number" step="0.01" min="0" {...register('credit_limit', { valueAsNumber: true })} />
              {errors.credit_limit && <p className="text-xs text-destructive">{errors.credit_limit.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label>Branch</Label>
              <Controller
                control={control}
                name="branch_id"
                render={({ field }) => (
                  <Select value={field.value} onValueChange={field.onChange}>
                    <SelectTrigger>
                      <SelectValue placeholder="Any branch" />
                    </SelectTrigger>
                    <SelectContent>
                      {branches?.map((branch) => (
                        <SelectItem key={branch.id} value={String(branch.id)}>
                          {branch.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}
              />
            </div>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={isSubmitting}>
              {isEdit ? 'Save Changes' : 'Create Customer'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
