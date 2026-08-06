import { useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus } from 'lucide-react'

import { deleteStatutoryDeductionRule, fetchStatutoryDeductionRules } from '@/api/hr'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { StatutoryDeductionRule } from '@/types/hr'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { SearchBar } from '@/components/shared/SearchBar'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { StatutoryDeductionRuleFormDialog } from '@/components/hr/StatutoryDeductionRuleFormDialog'

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'

function summarizeConfig(rule: StatutoryDeductionRule): string {
  if (rule.calculation_type === 'percentage') return `${((rule.config.rate ?? 0) * 100).toFixed(1)}%`
  if (rule.calculation_type === 'fixed') return String(rule.config.amount ?? 0)
  return `${rule.config.brackets?.length ?? 0} bracket(s)`
}

export function StatutoryDeductionRulesPage() {
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const [status, setStatus] = useState('all')
  const [search, setSearch] = useState('')
  const [formOpen, setFormOpen] = useState(false)
  const [editing, setEditing] = useState<StatutoryDeductionRule | null>(null)

  const { data, isLoading, isError } = useQuery({ queryKey: ['statutory-deduction-rules'], queryFn: fetchStatutoryDeductionRules })

  const filtered = useMemo(() => {
    return (data ?? [])
      .filter((row) => status === 'all' || (status === 'active' ? row.is_active : !row.is_active))
      .filter((row) => !search || row.name.toLowerCase().includes(search.toLowerCase()))
  }, [data, status, search])

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
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('statutory-deduction-rules.csv', csvColumnsFromDataTable(columns), filtered)} />
            {can('statutory-deduction-rules.create') && (
              <Button onClick={() => { setEditing(null); setFormOpen(true) }}>
                <Plus className="h-4 w-4" />
                New Rule
              </Button>
            )}
          </div>
        }
      />

      <FilterBar>
        <Select value={status} onValueChange={setStatus}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All statuses</SelectItem>
            <SelectItem value="active">Active</SelectItem>
            <SelectItem value="inactive">Inactive</SelectItem>
          </SelectContent>
        </Select>
      </FilterBar>

      <div className="mb-4">
        <SearchBar
          options={[{ value: 'name', label: 'Name' }]}
          placeholder="Search deduction rules…"
          onSearch={(_by, query) => setSearch(query)}
          onClear={() => setSearch('')}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load deduction rules. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={filtered}
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
