import { useEffect } from 'react'
import { useForm, Controller } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createCrmLead, updateCrmLead, type CrmLeadInput } from '@/api/crm'
import { fetchUsers } from '@/api/settings'
import { applyFieldErrors, getApiErrorInfo } from '@/lib/api-errors'
import type { CrmLead } from '@/types/crm'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

const leadSchema = z.object({
  name: z.string().min(1, 'Name is required'),
  company_name: z.string().optional().or(z.literal('')),
  email: z.string().email('Enter a valid email').optional().or(z.literal('')),
  phone: z.string().optional().or(z.literal('')),
  source: z.string().optional().or(z.literal('')),
  assigned_to: z.string().optional(),
  notes: z.string().optional().or(z.literal('')),
})

type LeadFormSchema = z.infer<typeof leadSchema>

const SOURCE_OPTIONS = ['referral', 'website', 'cold_call', 'social_media', 'event', 'other']

interface CrmLeadFormDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  lead?: CrmLead | null
}

export function CrmLeadFormDialog({ open, onOpenChange, lead }: CrmLeadFormDialogProps) {
  const queryClient = useQueryClient()
  const isEdit = !!lead

  const { data: users } = useQuery({
    queryKey: ['settings-users-all'],
    queryFn: () => fetchUsers({ per_page: 100 }),
    enabled: open,
  })

  const {
    register,
    handleSubmit,
    control,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<LeadFormSchema>({
    resolver: zodResolver(leadSchema),
    defaultValues: { name: '', company_name: '', email: '', phone: '', source: '', assigned_to: undefined, notes: '' },
  })

  useEffect(() => {
    if (!open) return
    reset({
      name: lead?.name ?? '',
      company_name: lead?.company_name ?? '',
      email: lead?.email ?? '',
      phone: lead?.phone ?? '',
      source: lead?.source ?? '',
      assigned_to: lead?.assigned_to ? String(lead.assigned_to.id) : undefined,
      notes: lead?.notes ?? '',
    })
  }, [open, lead, reset])

  const mutation = useMutation({
    mutationFn: async (values: LeadFormSchema) => {
      const payload: CrmLeadInput = {
        name: values.name,
        company_name: values.company_name || null,
        email: values.email || null,
        phone: values.phone || null,
        source: values.source || null,
        assigned_to: values.assigned_to ? Number(values.assigned_to) : null,
        notes: values.notes || null,
      }
      return isEdit ? updateCrmLead(lead!.id, payload) : createCrmLead(payload)
    },
    onSuccess: () => {
      toast.success(isEdit ? 'Lead updated' : 'Lead created')
      queryClient.invalidateQueries({ queryKey: ['crm-leads'] })
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
          <DialogTitle>{isEdit ? 'Edit Lead' : 'New Lead'}</DialogTitle>
        </DialogHeader>

        <form onSubmit={handleSubmit((values) => mutation.mutate(values))} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="name">Name</Label>
              <Input id="name" {...register('name')} />
              {errors.name && <p className="text-xs text-destructive">{errors.name.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="company_name">Company</Label>
              <Input id="company_name" {...register('company_name')} />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="email">Email</Label>
              <Input id="email" type="email" {...register('email')} />
              {errors.email && <p className="text-xs text-destructive">{errors.email.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="phone">Phone</Label>
              <Input id="phone" {...register('phone')} />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label>Source</Label>
              <Controller
                control={control}
                name="source"
                render={({ field }) => (
                  <Select value={field.value || undefined} onValueChange={field.onChange}>
                    <SelectTrigger>
                      <SelectValue placeholder="Select a source" />
                    </SelectTrigger>
                    <SelectContent>
                      {SOURCE_OPTIONS.map((source) => (
                        <SelectItem key={source} value={source}>
                          {source.replace('_', ' ')}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}
              />
            </div>
            <div className="space-y-1.5">
              <Label>Assigned To</Label>
              <Controller
                control={control}
                name="assigned_to"
                render={({ field }) => (
                  <Select value={field.value} onValueChange={field.onChange}>
                    <SelectTrigger>
                      <SelectValue placeholder="Unassigned" />
                    </SelectTrigger>
                    <SelectContent>
                      {users?.data.map((u) => (
                        <SelectItem key={u.id} value={String(u.id)}>
                          {u.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                )}
              />
            </div>
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="notes">Notes</Label>
            <Textarea id="notes" rows={3} {...register('notes')} />
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={isSubmitting}>
              {isEdit ? 'Save Changes' : 'Create Lead'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
