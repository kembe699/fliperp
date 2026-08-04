import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { deleteStatutoryDeductionRule, fetchStatutoryDeductionRules } from '@/api/hr'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { StatutoryDeductionRule } from '@/types/hr'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { StatutoryDeductionRuleFormDialog } from '@/components/hr/StatutoryDeductionRuleFormDialog'

function summarizeConfig(rule: StatutoryDeductionRule): string {
  if (rule.calculation_type === 'percentage') return `${((rule.config.rate ?? 0) * 100).toFixed(1)}%`
  if (rule.calculation_type === 'fixed') return String(rule.config.amount ?? 0)
  return `${rule.config.brackets?.length ?? 0} bracket(s)`
}

export function StatutoryDeductionRulesPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<StatutoryDeductionRule | null>(null)

  const { data, isLoading, isError } = useQuery({ queryKey: ['statutory-deduction-rules'], queryFn: fetchStatutoryDeductionRules })

  const deleteMutation = useMutation({
    mutationFn: deleteStatutoryDeductionRule,
    onSuccess: () => {
      toast.success('Deduction rule deleted')
      queryClient.invalidateQueries({ queryKey: ['statutory-deduction-rules'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const columns: DataTableColumn<StatutoryDeductionRule>[] = [
    { key: 'name', header: 'Name', accessor: (row) => row.name, sortable: true },
    { key: 'type', header: 'Type', accessor: (row) => row.type },
    { key: 'calculation_type', header: 'Calculation', accessor: (row) => row.calculation_type },
    { key: 'config', header: 'Value', render: (row) => summarizeConfig(row) },
    { key: 'country_code', header: 'Country', accessor: (row) => row.country_code },
    { key: 'is_active', header: 'Status', render: (row) => <StatusBadge label={row.is_active ? 'Active' : 'Inactive'} variant={row.is_active ? 'success' : 'neutral'} /> },
  ]

  const rowActions: (row: StatutoryDeductionRule) => DataTableRowAction<StatutoryDeductionRule>[] = (row) => [
    ...(can('statutory-deduction-rules.update') ? [{ label: 'Edit', onClick: (r: StatutoryDeductionRule) => { setEditing(r); setFormOpen(true) } }] : []),
    ...(can('statutory-deduction-rules.delete') ? [{ label: 'Delete', destructive: true, onClick: (r: StatutoryDeductionRule) => deleteMutation.mutate(r.id) }] : []),
  ]

  return (
    <div>
      <PageHeader
        parent="HR & Payroll"
        title="Statutory Deduction Rules"
        action={
          can('statutory-deduction-rules.create') && (
            <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
              <Plus className="h-4 w-4" />
              New Rule
            </Button>
          )
        }
      />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load deduction rules. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No deduction rules found"
          emptySubtext="Create statutory deduction rules to apply during payroll processing."
        />
      )}

      <StatutoryDeductionRuleFormDialog open={formOpen} onOpenChange={setFormOpen} rule={editing} />
    </div>
  )
}
