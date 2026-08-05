import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createCrmService, updateCrmService, type CrmServiceInput } from '@/api/crm'
import { applyFieldErrors, getApiErrorInfo } from '@/lib/api-errors'
import type { CrmService } from '@/types/crm'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'

const serviceSchema = z.object({
  name: z.string().min(1, 'Name is required'),
  category: z.string().optional().or(z.literal('')),
  description: z.string().optional().or(z.literal('')),
  default_price: z.number().min(0, 'Must be 0 or more'),
})

type ServiceFormSchema = z.infer<typeof serviceSchema>

interface CrmServiceFormDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  service?: CrmService | null
}

export function CrmServiceFormDialog({ open, onOpenChange, service }: CrmServiceFormDialogProps) {
  const queryClient = useQueryClient()
  const isEdit = !!service

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<ServiceFormSchema>({
    resolver: zodResolver(serviceSchema),
    defaultValues: { name: '', category: '', description: '', default_price: 0 },
  })

  useEffect(() => {
    if (!open) return
    reset({
      name: service?.name ?? '',
      category: service?.category ?? '',
      description: service?.description ?? '',
      default_price: service?.default_price ?? 0,
    })
  }, [open, service, reset])

  const mutation = useMutation({
    mutationFn: async (values: ServiceFormSchema) => {
      const payload: CrmServiceInput = {
        name: values.name,
        category: values.category || null,
        description: values.description || null,
        default_price: values.default_price,
      }
      return isEdit ? updateCrmService(service!.id, payload) : createCrmService(payload)
    },
    onSuccess: () => {
      toast.success(isEdit ? 'Service updated' : 'Service created')
      queryClient.invalidateQueries({ queryKey: ['crm-services'] })
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
          <DialogTitle>{isEdit ? 'Edit Service' : 'New Service'}</DialogTitle>
        </DialogHeader>

        <form onSubmit={handleSubmit((values) => mutation.mutate(values))} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="name">Name</Label>
              <Input id="name" {...register('name')} />
              {errors.name && <p className="text-xs text-destructive">{errors.name.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="category">Category</Label>
              <Input id="category" {...register('category')} />
            </div>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="default_price">Default Price</Label>
            <Input id="default_price" type="number" step="0.01" min="0" {...register('default_price', { valueAsNumber: true })} />
            {errors.default_price && <p className="text-xs text-destructive">{errors.default_price.message}</p>}
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="description">Description</Label>
            <Textarea id="description" rows={3} {...register('description')} />
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={isSubmitting}>
              {isEdit ? 'Save Changes' : 'Create Service'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
