import { useMemo, useState } from 'react'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchEmployees, fetchSalaryStructures } from '@/api/hr'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { usePermissions } from '@/hooks/use-permissions'
import type { SalaryStructure } from '@/types/hr'

import { PageHeader } from '@/components/layout/PageHeader'
import { SearchBar } from '@/components/shared/SearchBar'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { Button } from '@/components/ui/button'
import { SalaryStructureFormDialog } from '@/components/hr/SalaryStructureFormDialog'

export function SalaryStructuresPage() {
  const { can } = usePermissions()
  const [search, setSearch] = useState('')
  const [formOpen, setFormOpen] = useState(false)

  const { data, isLoading, isError } = useQuery({ queryKey: ['salary-structures', 'all'], queryFn: () => fetchSalaryStructures({ per_page: 2000 }) })
  const { data: employees } = useQuery({ queryKey: ['employees-all'], queryFn: () => fetchEmployees({ per_page: 200 }) })

  const employeeName = (id: number) => {
    const employee = employees?.data.find((e) => e.id === id)
    return employee ? `${employee.first_name} ${employee.last_name}` : `#${id}`
  }

  const filtered = useMemo(() => {
    return (data?.data ?? []).filter((row) => !search || employeeName(row.employee_id).toLowerCase().includes(search.toLowerCase()))
  }, [data, search, employees])

  const columns: DataTableColumn<SalaryStructure>[] = [
    { key: 'employee_id', header: 'Employee', render: (row) => employeeName(row.employee_id) },
    { key: 'basic_salary', header: 'Basic Salary', render: (row) => formatCurrency(row.basic_salary) },
    { key: 'allowances', header: 'Allowances', render: (row) => formatCurrency(Object.values(row.allowances ?? {}).reduce((a, b) => a + b, 0)) },
    { key: 'effective_date', header: 'Effective Date', accessor: (row) => row.effective_date, sortable: true, render: (row) => formatDate(row.effective_date) },
  ]

  return (
    <div>
      <PageHeader
        parent="HR & Payroll"
        title="Salary Structures"
        action={
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('salary-structures.csv', csvColumnsFromDataTable(columns), filtered)} />
            {can('salary-structures.create') && (
              <Button onClick={() => setFormOpen(true)}>
                <Plus className="h-4 w-4" />
                New Salary Structure
              </Button>
            )}
          </div>
        }
      />

      <div className="mb-4">
        <SearchBar
          options={[{ value: 'employee', label: 'Employee' }]}
          placeholder="Search salary structures…"
          onSearch={(_by, query) => setSearch(query)}
          onClear={() => setSearch('')}
        />
      </div>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load salary structures. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={filtered}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          emptyTitle="No salary structures found"
          emptySubtext="Create a salary structure so employees can be included in payroll runs."
        />
      )}

      <SalaryStructureFormDialog open={formOpen} onOpenChange={setFormOpen} />
    </div>
  )
}
