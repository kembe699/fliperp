import { useState } from 'react'
import {
  DndContext,
  DragOverlay,
  PointerSensor,
  useDraggable,
  useDroppable,
  useSensor,
  useSensors,
  type DragEndEvent,
  type DragStartEvent,
} from '@dnd-kit/core'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { ListTree, Plus, User } from 'lucide-react'

import { fetchCrmKanban, fetchCrmPipelineStages, moveCrmDealStage } from '@/api/crm'
import { formatCurrency } from '@/lib/currency'
import { usePermissions } from '@/hooks/use-permissions'
import type { CrmDeal, CrmKanbanColumn } from '@/types/crm'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { CrmDealFormDialog } from '@/components/crm/CrmDealFormDialog'
import { LostReasonDialog } from '@/components/crm/LostReasonDialog'
import { CustomerServiceLogDialog } from '@/components/crm/CustomerServiceLogDialog'
import { ManagePipelineStagesDialog } from '@/components/crm/ManagePipelineStagesDialog'

function DealCard({ deal }: { deal: CrmDeal }) {
  const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
    id: `deal-${deal.id}`,
    data: { deal },
  })

  return (
    <div
      ref={setNodeRef}
      {...listeners}
      {...attributes}
      style={
        transform
          ? { transform: `translate3d(${transform.x}px, ${transform.y}px, 0)`, opacity: isDragging ? 0.4 : 1 }
          : undefined
      }
      className="cursor-grab space-y-2 rounded-lg border border-border bg-background p-3 text-sm shadow-sm active:cursor-grabbing"
    >
      <p className="font-medium text-foreground">{deal.title}</p>
      <p className="text-xs text-muted-foreground">{deal.customer?.name ?? deal.lead?.name ?? 'Unlinked'}</p>
      <div className="flex items-center justify-between">
        <span className="font-semibold text-foreground">{formatCurrency(deal.value)}</span>
        {deal.assigned_to && (
          <span className="inline-flex items-center gap-1 text-xs text-muted-foreground">
            <User className="h-3 w-3" />
            {deal.assigned_to.name}
          </span>
        )}
      </div>
    </div>
  )
}

function KanbanColumn({ column }: { column: CrmKanbanColumn }) {
  const { setNodeRef, isOver } = useDroppable({ id: `stage-${column.stage.id}`, data: { stage: column.stage } })
  const totalValue = column.deals.reduce((sum, deal) => sum + deal.value, 0)

  return (
    <div
      ref={setNodeRef}
      className={`flex w-72 shrink-0 flex-col rounded-xl border p-3 transition-colors ${
        isOver ? 'border-primary bg-accent/40' : 'border-border bg-card'
      } ${column.stage.is_closed_won ? 'ring-1 ring-success/40' : ''} ${column.stage.is_closed_lost ? 'ring-1 ring-danger/40' : ''}`}
    >
      <div className="mb-3 flex items-center justify-between">
        <div>
          <p className="text-sm font-semibold text-foreground">{column.stage.name}</p>
          <p className="text-xs text-muted-foreground">
            {column.deals.length} · {formatCurrency(totalValue)}
          </p>
        </div>
      </div>
      <div className="flex min-h-24 flex-1 flex-col gap-2">
        {column.deals.map((deal) => (
          <DealCard key={deal.id} deal={deal} />
        ))}
      </div>
    </div>
  )
}

export function CrmPipelinePage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const [activeDeal, setActiveDeal] = useState<CrmDeal | null>(null)
  const [dealFormOpen, setDealFormOpen] = useState(false)
  const [lostReasonTarget, setLostReasonTarget] = useState<{ deal: CrmDeal; stageId: number } | null>(null)
  const [serviceLogDeal, setServiceLogDeal] = useState<CrmDeal | null>(null)
  const [manageStagesOpen, setManageStagesOpen] = useState(false)

  const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 8 } }))

  const { data: stages } = useQuery({ queryKey: ['crm-pipeline-stages'], queryFn: fetchCrmPipelineStages })
  const { data: columns, isLoading } = useQuery({ queryKey: ['crm-kanban'], queryFn: fetchCrmKanban })

  const moveMutation = useMutation({
    mutationFn: ({ dealId, stageId, lostReason }: { dealId: number; stageId: number; lostReason?: string }) =>
      moveCrmDealStage(dealId, stageId, lostReason),
    onMutate: async ({ dealId, stageId }) => {
      await queryClient.cancelQueries({ queryKey: ['crm-kanban'] })
      const previous = queryClient.getQueryData<CrmKanbanColumn[]>(['crm-kanban'])

      queryClient.setQueryData<CrmKanbanColumn[]>(['crm-kanban'], (old) => {
        if (!old) return old
        let movedDeal: CrmDeal | undefined
        const withoutDeal = old.map((column) => {
          const found = column.deals.find((d) => d.id === dealId)
          if (found) movedDeal = found
          return { ...column, deals: column.deals.filter((d) => d.id !== dealId) }
        })
        if (!movedDeal) return old
        return withoutDeal.map((column) =>
          column.stage.id === stageId
            ? { ...column, deals: [...column.deals, { ...movedDeal!, pipeline_stage_id: stageId }] }
            : column,
        )
      })

      return { previous }
    },
    onError: (_err, _vars, context) => {
      if (context?.previous) queryClient.setQueryData(['crm-kanban'], context.previous)
      toast.error('Could not move this deal. It has been restored to its original stage.')
    },
    onSuccess: (result) => {
      toast.success('Deal moved')
      if (result.promptCustomerServiceLog) {
        setServiceLogDeal(result.deal)
      }
    },
    onSettled: () => {
      queryClient.invalidateQueries({ queryKey: ['crm-kanban'] })
      queryClient.invalidateQueries({ queryKey: ['crm-report-summary'] })
    },
  })

  const handleDragStart = (event: DragStartEvent) => {
    setActiveDeal((event.active.data.current?.deal as CrmDeal) ?? null)
  }

  const handleDragEnd = (event: DragEndEvent) => {
    setActiveDeal(null)
    const { active, over } = event
    if (!over) return

    const deal = active.data.current?.deal as CrmDeal | undefined
    const toStage = over.data.current?.stage as CrmKanbanColumn['stage'] | undefined
    if (!deal || !toStage || deal.pipeline_stage_id === toStage.id) return

    if (toStage.is_closed_lost) {
      setLostReasonTarget({ deal, stageId: toStage.id })
      return
    }

    moveMutation.mutate({ dealId: deal.id, stageId: toStage.id })
  }

  return (
    <div>
      <PageHeader
        parent="CRM"
        title="Pipeline"
        action={
          <div className="flex gap-2">
            {can('crm-pipeline-stages.view') && (
              <Button variant="outline" onClick={() => setManageStagesOpen(true)}>
                <ListTree className="h-4 w-4" />
                Manage Stages
              </Button>
            )}
            {can('crm-deals.create') && (
              <Button onClick={() => setDealFormOpen(true)}>
                <Plus className="h-4 w-4" />
                New Deal
              </Button>
            )}
          </div>
        }
      />

      {isLoading || !columns ? (
        <p className="text-sm text-muted-foreground">Loading pipeline…</p>
      ) : (
        <DndContext sensors={sensors} onDragStart={handleDragStart} onDragEnd={handleDragEnd}>
          <div className="flex gap-4 overflow-x-auto pb-4">
            {columns.map((column) => (
              <KanbanColumn key={column.stage.id} column={column} />
            ))}
          </div>
          <DragOverlay>{activeDeal && <DealCard deal={activeDeal} />}</DragOverlay>
        </DndContext>
      )}

      <CrmDealFormDialog open={dealFormOpen} onOpenChange={setDealFormOpen} stages={stages ?? []} />

      <ManagePipelineStagesDialog open={manageStagesOpen} onOpenChange={setManageStagesOpen} />

      <LostReasonDialog
        open={!!lostReasonTarget}
        onOpenChange={(open) => !open && setLostReasonTarget(null)}
        isSubmitting={moveMutation.isPending}
        onConfirm={(reason) => {
          if (!lostReasonTarget) return
          moveMutation.mutate({ dealId: lostReasonTarget.deal.id, stageId: lostReasonTarget.stageId, lostReason: reason })
          setLostReasonTarget(null)
        }}
      />

      <CustomerServiceLogDialog open={!!serviceLogDeal} onOpenChange={(open) => !open && setServiceLogDeal(null)} deal={serviceLogDeal} />
    </div>
  )
}
