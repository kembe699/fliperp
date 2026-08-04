import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus, RefreshCw } from 'lucide-react'

import { fetchAssetCategories, fetchAssets, runDepreciation } from '@/api/assets'
import { formatCurrency } from '@/lib/currency'
import { csvColumnsFromDataTable, exportToCsv } from '@/lib/csv-export'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'
import type { Asset, AssetStatus } from '@/types/assets'

import { PageHeader } from '@/components/layout/PageHeader'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Button } from '@/components/ui/button'
import { ExportCsvButton } from '@/components/shared/ExportCsvButton'
import { AssetFormDialog } from '@/components/assets/AssetFormDialog'

const STATUS_VARIANT: Record<AssetStatus, 'success' | 'neutral' | 'warning' | 'danger'> = {
  in_use: 'success',
  in_storage: 'neutral',
  under_maintenance: 'warning',
  disposed: 'danger',
}

export function AssetsListPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = usePermissions()

  const [page, setPage] = useState(1)
  const [formOpen, setFormOpen] = useState(false)

  const { data, isLoading, isError } = useQuery({ queryKey: ['assets', page], queryFn: () => fetchAssets({ page, per_page: 15 }) })
  const { data: categories } = useQuery({ queryKey: ['asset-categories'], queryFn: fetchAssetCategories })

  const categoryName = (id: number) => categories?.find((c) => c.id === id)?.name ?? `#${id}`

  const depreciationMutation = useMutation({
    mutationFn: runDepreciation,
    onSuccess: (result) => {
      toast.success(`Depreciation run complete — ${result.schedules_created} schedule(s) created`)
      queryClient.invalidateQueries({ queryKey: ['assets'] })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const columns: DataTableColumn<Asset>[] = [
    { key: 'asset_code', header: 'Code', accessor: (row) => row.asset_code, sortable: true },
    { key: 'name', header: 'Name', accessor: (row) => row.name },
    { key: 'asset_category_id', header: 'Category', accessor: (row) => categoryName(row.asset_category_id) },
    { key: 'purchase_cost', header: 'Purchase Cost', accessor: (row) => row.purchase_cost, render: (row) => formatCurrency(row.purchase_cost) },
    { key: 'current_value', header: 'Current Value', accessor: (row) => row.current_value, render: (row) => formatCurrency(row.current_value) },
    { key: 'status', header: 'Status', render: (row) => <StatusBadge label={row.status.replace('_', ' ')} variant={STATUS_VARIANT[row.status]} /> },
  ]

  return (
    <div>
      <PageHeader
        parent="Assets"
        title="Assets"
        action={
          <div className="flex gap-2">
            <ExportCsvButton
              onExport={async () => {
                const all = await fetchAssets({ per_page: 10000 })
                exportToCsv('assets.csv', csvColumnsFromDataTable(columns), all.data)
              }}
            />
            {can('assets.run-depreciation') && (
              <Button variant="outline" disabled={depreciationMutation.isPending} onClick={() => depreciationMutation.mutate()}>
                <RefreshCw className="h-4 w-4" />
                Run Depreciation
              </Button>
            )}
            {can('assets.create') && (
              <Button onClick={() => setFormOpen(true)}>
                <Plus className="h-4 w-4" />
                New Asset
              </Button>
            )}
          </div>
        }
      />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load assets. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={() => [{ label: 'View', onClick: (row: Asset) => navigate(`/assets/${row.id}`) }]}
          emptyTitle="No assets found"
          emptySubtext="Register an asset to start tracking depreciation and maintenance."
          page={data?.meta.current_page}
          pageCount={data?.meta.last_page}
          totalRows={data?.meta.total}
          onPageChange={setPage}
        />
      )}

      <AssetFormDialog open={formOpen} onOpenChange={setFormOpen} asset={null} />
    </div>
  )
}
