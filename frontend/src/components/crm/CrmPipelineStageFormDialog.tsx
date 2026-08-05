import { useEffect } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { z } from 'zod'
import { useMutation, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'

import { createCrmPipelineStage, updateCrmPipelineStage, type CrmPipelineStageInput } from '@/api/crm'
import { applyFieldErrors, getApiErrorInfo } from '@/lib/api-errors'
import type { CrmPipelineStage } from '@/types/crm'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const stageSchema = z.object({
  name: z.string().min(1, 'Name is required'),
  color: z.string().optional().or(z.literal('')),
  is_closed_won: z.boolean(),
  is_closed_lost: z.boolean(),
})

type StageFormSchema = z.infer<typeof stageSchema>

interface CrmPipelineStageFormDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
  stage?: CrmPipelineStage | null
}

export function CrmPipelineStageFormDialog({ open, onOpenChange, stage }: CrmPipelineStageFormDialogProps) {
  const queryClient = useQueryClient()
  const isEdit = !!stage

  const {
    register,
    handleSubmit,
    reset,
    setError,
    formState: { errors, isSubmitting },
  } = useForm<StageFormSchema>({
    resolver: zodResolver(stageSchema),
    defaultValues: { name: '', color: '#94a3b8', is_closed_won: false, is_closed_lost: false },
  })

  useEffect(() => {
    if (!open) return
    reset({
      name: stage?.name ?? '',
      color: stage?.color ?? '#94a3b8',
      is_closed_won: stage?.is_closed_won ?? false,
      is_closed_lost: stage?.is_closed_lost ?? false,
    })
  }, [open, stage, reset])

  const mutation = useMutation({
    mutationFn: async (values: StageFormSchema) => {
      const payload: CrmPipelineStageInput = {
        name: values.name,
        color: values.color || null,
        is_closed_won: values.is_closed_won,
        is_closed_lost: values.is_closed_lost,
      }
      return isEdit ? updateCrmPipelineStage(stage!.id, payload) : createCrmPipelineStage(payload)
    },
    onSuccess: () => {
      toast.success(isEdit ? 'Stage updated' : 'Stage created')
      queryClient.invalidateQueries({ queryKey: ['crm-pipeline-stages'] })
      queryClient.invalidateQueries({ queryKey: ['crm-kanban'] })
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
          <DialogTitle>{isEdit ? 'Edit Stage' : 'New Stage'}</DialogTitle>
        </DialogHeader>

        <form onSubmit={handleSubmit((values) => mutation.mutate(values))} className="space-y-4">
          <div className="grid grid-cols-2 gap-4">
            <div className="space-y-1.5">
              <Label htmlFor="stage-name">Name</Label>
              <Input id="stage-name" {...register('name')} />
              {errors.name && <p className="text-xs text-destructive">{errors.name.message}</p>}
            </div>
            <div className="space-y-1.5">
              <Label htmlFor="stage-color">Color</Label>
              <Input id="stage-color" type="color" className="h-10 w-full p-1" {...register('color')} />
            </div>
          </div>

          <div className="flex gap-6">
            <label className="flex items-center gap-2 text-sm text-foreground">
              <input type="checkbox" {...register('is_closed_won')} className="h-4 w-4 rounded border-input" />
              Closed Won stage
            </label>
            <label className="flex items-center gap-2 text-sm text-foreground">
              <input type="checkbox" {...register('is_closed_lost')} className="h-4 w-4 rounded border-input" />
              Closed Lost stage
            </label>
          </div>

          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => onOpenChange(false)}>
              Cancel
            </Button>
            <Button type="submit" disabled={isSubmitting}>
              {isEdit ? 'Save Changes' : 'Create Stage'}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  )
}
