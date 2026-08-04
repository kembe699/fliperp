import { useEffect } from 'react'
import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { CheckCircle2, XCircle } from 'lucide-react'

import { verifySalePublic } from '@/api/sales'
import { fetchCurrencies } from '@/api/settings'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { useAuthStore } from '@/lib/auth-store'

const STATUS_LABEL: Record<string, string> = {
  completed: 'Completed',
  voided: 'Voided',
  refunded: 'Refunded',
  held: 'Held',
}

export function ReceiptVerifyPage() {
  const { id } = useParams<{ id: string }>()
  const saleId = Number(id)
  const setCurrencies = useAuthStore((state) => state.setCurrencies)

  // No login here, so ProtectedRoute never populates the currencies list
  // formatCurrency() needs for a proper symbol — fetch it directly. The
  // endpoint is unauthenticated for exactly this reason.
  useEffect(() => {
    fetchCurrencies().then(setCurrencies).catch(() => {})
  }, [setCurrencies])

  const { data, isLoading, isError } = useQuery({
    queryKey: ['public-sale-verify', saleId],
    queryFn: () => verifySalePublic(saleId),
    enabled: !!saleId,
    retry: false,
  })

  return (
    <div className="flex min-h-screen items-center justify-center bg-muted p-4">
      <div className="w-full max-w-sm rounded-2xl border border-border bg-card p-8 text-center shadow-sm">
        {isLoading ? (
          <p className="text-sm text-muted-foreground">Verifying receipt…</p>
        ) : isError || !data ? (
          <>
            <XCircle className="mx-auto h-14 w-14 text-destructive" />
            <p className="mt-4 text-base font-semibold text-foreground">Receipt not found</p>
            <p className="mt-1 text-sm text-muted-foreground">This receipt could not be verified.</p>
          </>
        ) : (
          <>
            <CheckCircle2 className="mx-auto h-14 w-14 text-success" />
            <p className="mt-4 text-base font-semibold text-foreground">Receipt Verified</p>
            {data.company_name && <p className="text-sm text-muted-foreground">{data.company_name}</p>}

            <div className="mt-6 space-y-2 text-left text-sm">
              <div className="flex justify-between">
                <span className="text-muted-foreground">Reference</span>
                <span className="font-medium text-foreground">{data.reference_number}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Date</span>
                <span className="font-medium text-foreground">{formatDate(data.sale_date)}</span>
              </div>
              <div className="flex justify-between">
                <span className="text-muted-foreground">Total</span>
                <span className="font-medium text-foreground">{formatCurrency(data.total_amount, data.currency_code)}</span>
              </div>
              <div className="flex justify-between border-t border-border pt-2">
                <span className="text-muted-foreground">Status</span>
                <span className="font-semibold text-foreground">{STATUS_LABEL[data.status] ?? data.status}</span>
              </div>
            </div>
          </>
        )}
      </div>
    </div>
  )
}
