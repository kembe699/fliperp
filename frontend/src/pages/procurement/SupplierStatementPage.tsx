import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'

import { fetchSupplierStatement } from '@/api/procurement'
import { formatCurrency, formatDate } from '@/lib/format'
import type { SupplierStatementTransaction } from '@/types/procurement'

import { PageHeader } from '@/components/layout/PageHeader'
import { Card, CardContent } from '@/components/ui/card'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'

const TYPE_LABEL: Record<SupplierStatementTransaction['type'], string> = {
  bill: 'Supplier Bill',
  payment: 'Payment',
}

export function SupplierStatementPage() {
  const { id } = useParams<{ id: string }>()
  const supplierId = Number(id)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['supplier-statement', supplierId],
    queryFn: () => fetchSupplierStatement(supplierId),
    enabled: !!supplierId,
  })

  const columns: DataTableColumn<SupplierStatementTransaction>[] = [
    { key: 'date', header: 'Date', accessor: (row) => row.date, sortable: true, render: (row) => formatDate(row.date) },
    { key: 'type', header: 'Type', render: (row) => <StatusBadge label={TYPE_LABEL[row.type]} variant="info" /> },
    { key: 'reference_number', header: 'Reference', accessor: (row) => row.reference_number ?? '—' },
    { key: 'debit', header: 'Debit', accessor: (row) => row.debit, render: (row) => (row.debit ? formatCurrency(row.debit) : '—') },
    { key: 'credit', header: 'Credit', accessor: (row) => row.credit, render: (row) => (row.credit ? formatCurrency(row.credit) : '—') },
    {
      key: 'running_balance',
      header: 'Running Balance',
      accessor: (row) => row.running_balance,
      render: (row) => formatCurrency(row.running_balance),
    },
  ]

  return (
    <div>
      <PageHeader parent="Suppliers" title={data ? `${data.supplier_name} — Statement` : 'Supplier Statement'} />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load this statement.</p>
      ) : (
        <>
          <Card className="mb-6 max-w-xs">
            <CardContent className="p-6">
              <p className="text-sm text-muted-foreground">Closing Balance</p>
              <p className={`mt-1 text-3xl font-bold ${data && data.closing_balance > 0 ? 'text-danger' : 'text-foreground'}`}>
                {isLoading || !data ? '—' : formatCurrency(data.closing_balance)}
              </p>
              <p className="mt-1 text-xs text-muted-foreground">Positive means we owe this supplier this amount.</p>
            </CardContent>
          </Card>

          <DataTable
            columns={columns}
            data={data?.transactions ?? []}
            rowKey={(row, index) => `${row.type}-${row.reference_number}-${index}`}
            isLoading={isLoading}
            emptyTitle="No transactions found"
            emptySubtext="This supplier has no bills or payments yet."
          />
        </>
      )}
    </div>
  )
}
