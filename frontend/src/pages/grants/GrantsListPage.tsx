import { useMemo, useState } from 'react'
import { Link, useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchGrantSummary, fetchGrants } from '@/api/grants'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { usePermissions } from '@/hooks/use-permissions'
import { useBaseCurrency } from '@/hooks/use-base-currency'
import type { Grant, GrantSummary } from '@/types/grants'

import { PageHeader } from '@/components/layout/PageHeader'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { GrantFormDialog } from '@/components/grants/GrantFormDialog'
import { Button } from '@/components/ui/button'

type Row = Grant & Partial<GrantSummary>

export function GrantsListPage() {
  const navigate = useNavigate()
  const { can } = usePermissions()
  const base = useBaseCurrency()
  const [formOpen, setFormOpen] = useState(false)

  const { data, isLoading, isError } = useQuery({ queryKey: ['grants', 'list'], queryFn: () => fetchGrants() })
  const { data: summary } = useQuery({ queryKey: ['grants', 'summary'], queryFn: fetchGrantSummary, enabled: can('reports.view') })

  const rows: Row[] = useMemo(() => {
    const bySof = new Map((summary ?? []).map((s) => [s.grant_id, s]))
    return (data?.data ?? []).map((grant) => ({ ...grant, ...bySof.get(grant.id) }))
  }, [data, summary])

  const columns: DataTableColumn<Row>[] = [
    {
      key: 'code',
      header: 'SOF',
      accessor: (row) => row.code,
      sortable: true,
      render: (row) => (
        <Link to={`/grants/${row.id}`} className="font-medium text-primary hover:underline">
          {row.code}
        </Link>
      ),
    },
    { key: 'name', header: 'Name', accessor: (row) => row.name, sortable: true },
    { key: 'donor_name', header: 'Donor', accessor: (row) => row.donor_name ?? '', sortable: true },
    { key: 'currency_code', header: 'Currency', accessor: (row) => row.currency_code },
    { key: 'period', header: 'Period', accessor: (row) => `${row.start_date} – ${row.end_date}`, render: (row) => `${formatDate(row.start_date)} – ${formatDate(row.end_date)}` },
    { key: 'budget_amount', header: 'Award', accessor: (row) => row.budget_amount, sortable: true, render: (row) => formatCurrency(row.budget_amount, row.currency_code) },
    { key: 'actual_sof', header: 'Spent (SOF currency)', accessor: (row) => row.actual_sof ?? '', render: (row) => (row.actual_sof != null ? formatCurrency(row.actual_sof, row.currency_code) : '—') },
    { key: 'actual_base', header: `Spent (${base})`, accessor: (row) => row.actual_base ?? '', render: (row) => (row.actual_base != null ? formatCurrency(row.actual_base, base) : '—') },
    {
      key: 'burn_percent',
      header: 'Burn',
      accessor: (row) => row.burn_percent ?? '',
      sortable: true,
      render: (row) => (row.burn_percent != null ? <span className={row.burn_percent > 100 ? 'font-medium text-danger' : ''}>{row.burn_percent}%</span> : '—'),
    },
    {
      key: 'status',
      header: 'Status',
      accessor: (row) => row.status,
      render: (row) => <StatusBadge label={row.status === 'active' ? 'Active' : 'Closed'} variant={row.status === 'active' ? 'success' : 'neutral'} />,
    },
  ]

  return (
    <div>
      <PageHeader
        parent="Grants"
        title="SOFs (Sources of Funding)"
        action={
          <div className="flex gap-2">
            <ExportCsvButton onExport={async () => exportToCsv('sofs.csv', csvColumnsFromDataTable(columns), rows)} />
            {can('grants.create') && (
              <Button onClick={() => setFormOpen(true)}>
                <Plus className="h-4 w-4" />
                New SOF
              </Button>
            )}
          </div>
        }
      />

      <p className="mb-4 text-sm text-muted-foreground">
        Each SOF is one donor award with its own currency, dates and budget. Code bills, invoices, purchase orders and journals to a SOF (and its DEA,
        project and cost centre) and the SOF Grant Report shows spend against the donor budget.
      </p>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load SOFs. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={rows}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={() => [
            { label: 'Open', onClick: (row: Row) => navigate(`/grants/${row.id}`) },
            ...(can('reports.view') ? [{ label: 'Grant Report', onClick: (row: Row) => navigate(`/grants/${row.id}/report`) }] : []),
          ]}
          emptyTitle="No SOFs yet"
          emptySubtext="Create a SOF for each donor award you receive."
        />
      )}

      <GrantFormDialog open={formOpen} onOpenChange={setFormOpen} onSaved={(grant) => navigate(`/grants/${grant.id}`)} />
    </div>
  )
}
