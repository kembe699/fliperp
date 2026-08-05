import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ArrowDown, ArrowUp, Pencil, Plus, Trash2 } from 'lucide-react'

import { deleteCrmPipelineStage, fetchCrmPipelineStages, reorderCrmPipelineStages } from '@/api/crm'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { CrmPipelineStage } from '@/types/crm'

import { Button } from '@/components/ui/button'
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { CrmPipelineStageFormDialog } from '@/components/crm/CrmPipelineStageFormDialog'

interface ManagePipelineStagesDialogProps {
  open: boolean
  onOpenChange: (open: boolean) => void
}

export function ManagePipelineStagesDialog({ open, onOpenChange }: ManagePipelineStagesDialogProps) {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const [formOpen, setFormOpen] = useState(false)
  const [editingStage, setEditingStage] = useState<CrmPipelineStage | null>(null)

  const { data: stages } = useQuery({ queryKey: ['crm-pipeline-stages'], queryFn: fetchCrmPipelineStages, enabled: open })

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['crm-pipeline-stages'] })
    queryClient.invalidateQueries({ queryKey: ['crm-kanban'] })
  }

  const reorderMutation = useMutation({
    mutationFn: reorderCrmPipelineStages,
    onSuccess: () => {
      toast.success('Stages reordered')
      invalidate()
    },
    onError: (err) => toast.error(getApiErrorInfo(err).message),
  })

  const deleteMutation = useMutation({
    mutationFn: deleteCrmPipelineStage,
    onSuccess: () => {
      toast.success('Stage deleted')
      invalidate()
    },
    onError: (err) => toast.error(getApiErrorInfo(err).message),
  })

  const moveStage = (index: number, direction: -1 | 1) => {
    if (!stages) return
    const targetIndex = index + direction
    if (targetIndex < 0 || targetIndex >= stages.length) return

    const reordered = [...stages]
    ;[reordered[index], reordered[targetIndex]] = [reordered[targetIndex], reordered[index]]
    reorderMutation.mutate(reordered.map((s) => s.id))
  }

  return (
    <>
      <Dialog open={open} onOpenChange={onOpenChange}>
        <DialogContent className="max-w-xl">
          <DialogHeader>
            <DialogTitle>Manage Pipeline Stages</DialogTitle>
          </DialogHeader>

          <div className="space-y-2">
            {(stages ?? []).map((stage, index) => (
              <div key={stage.id} className="flex items-center justify-between gap-3 rounded-lg border border-border p-3">
                <div className="flex items-center gap-3">
                  <span className="h-3 w-3 shrink-0 rounded-full" style={{ backgroundColor: stage.color ?? '#94a3b8' }} />
                  <span className="text-sm font-medium text-foreground">{stage.name}</span>
                  {stage.is_closed_won && <StatusBadge label="Closed Won" variant="success" />}
                  {stage.is_closed_lost && <StatusBadge label="Closed Lost" variant="danger" />}
                </div>
                <div className="flex items-center gap-1">
                  {can('crm-pipeline-stages.reorder') && (
                    <>
                      <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        disabled={index === 0 || reorderMutation.isPending}
                        onClick={() => moveStage(index, -1)}
                      >
                        <ArrowUp className="h-4 w-4" />
                      </Button>
                      <Button
                        type="button"
                        variant="outline"
                        size="icon"
                        disabled={index === (stages?.length ?? 1) - 1 || reorderMutation.isPending}
                        onClick={() => moveStage(index, 1)}
                      >
                        <ArrowDown className="h-4 w-4" />
                      </Button>
                    </>
                  )}
                  {can('crm-pipeline-stages.update') && (
                    <Button
                      type="button"
                      variant="outline"
                      size="icon"
                      onClick={() => {
                        setEditingStage(stage)
                        setFormOpen(true)
                      }}
                    >
                      <Pencil className="h-4 w-4" />
                    </Button>
                  )}
                  {can('crm-pipeline-stages.delete') && (
                    <Button
                      type="button"
                      variant="outline"
                      size="icon"
                      className="text-destructive hover:text-destructive"
                      onClick={() => deleteMutation.mutate(stage.id)}
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  )}
                </div>
              </div>
            ))}

            {stages?.length === 0 && <p className="py-6 text-center text-sm text-muted-foreground">No pipeline stages yet.</p>}
          </div>

          {can('crm-pipeline-stages.create') && (
            <Button
              type="button"
              variant="outline"
              onClick={() => {
                setEditingStage(null)
                setFormOpen(true)
              }}
            >
              <Plus className="h-4 w-4" />
              Add Stage
            </Button>
          )}
        </DialogContent>
      </Dialog>

      <CrmPipelineStageFormDialog open={formOpen} onOpenChange={setFormOpen} stage={editingStage} />
    </>
  )
}
