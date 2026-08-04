import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Download } from 'lucide-react'

import { fetchCustomerStatement } from '@/api/customers'
import { formatCurrency, formatDate } from '@/lib/format'
import { downloadPdf } from '@/lib/pdf-download'
import type { CustomerStatementTransaction } from '@/types/customer'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'

const TYPE_LABEL: Record<CustomerStatementTransaction['type'], string> = {
  sale: 'POS Sale',
  sale_payment: 'POS Payment',
  invoice: 'Invoice',
  invoice_payment: 'Invoice Payment',
}

export function CustomerStatementPage() {
  const { id } = useParams<{ id: string }>()
  const customerId = Number(id)

  const { data, isLoading, isError } = useQuery({
    queryKey: ['customer-statement', customerId],
    queryFn: () => fetchCustomerStatement(customerId),
    enabled: !!customerId,
  })

  const [downloading, setDownloading] = useState(false)

  const handleDownloadPdf = async () => {
    if (!data) return
    setDownloading(true)
    try {
      await downloadPdf(`/customers/${customerId}/statement/pdf`, `statement-${data.customer_name}.pdf`)
    } catch {
      toast.error('Could not download the statement PDF. Please try again.')
    } finally {
      setDownloading(false)
    }
  }

  const columns: DataTableColumn<CustomerStatementTransaction>[] = [
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
      <PageHeader
        parent="Customers"
        title={data ? `${data.customer_name} — Statement` : 'Customer Statement'}
        action={
          <Button variant="outline" disabled={!data || downloading} onClick={handleDownloadPdf}>
            <Download className="h-4 w-4" />
            {downloading ? 'Downloading…' : 'Download PDF'}
          </Button>
        }
      />

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
              <p className="mt-1 text-xs text-muted-foreground">Positive means the customer owes this amount.</p>
            </CardContent>
          </Card>

          <DataTable
            columns={columns}
            data={data?.transactions ?? []}
            rowKey={(row, index) => `${row.type}-${row.reference_number}-${index}`}
            isLoading={isLoading}
            emptyTitle="No transactions found"
            emptySubtext="This customer has no sales, invoices or payments yet."
          />
        </>
      )}
    </div>
  )
}
