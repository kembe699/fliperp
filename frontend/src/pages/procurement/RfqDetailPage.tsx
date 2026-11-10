import { useMemo, useState } from 'react'
import { useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Ban, Download, FilePlus2, Mail, Pencil, Trash2 } from 'lucide-react'

import { cancelRfq, convertRfq, deleteRfq, downloadRfqPdf, fetchRfq, recordRfqQuote, sendRfq, type Rfq, type RfqStatus, type RfqSupplierLine } from '@/api/rfqs'
import { fetchWarehouses } from '@/api/inventory'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { getApiErrorInfo } from '@/lib/api-errors'
import { usePermissions } from '@/hooks/use-permissions'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { StatusBadge, type StatusVariant } from '@/components/shared/StatusBadge'

export const RFQ_STATUS_VARIANT: Record<RfqStatus, StatusVariant> = { draft: 'neutral', sent: 'info', converted: 'success', cancelled: 'danger' }

const localToday = () => {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

export function RfqDetailPage() {
  const { id } = useParams<{ id: string }>()
  const rfqId = Number(id)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const { can } = usePermissions()
  const [sending, setSending] = useState(false)
  const [quoting, setQuoting] = useState<RfqSupplierLine | null>(null)
  const [converting, setConverting] = useState<RfqSupplierLine | null>(null)
  const [downloading, setDownloading] = useState(false)

  const { data: rfq, isLoading } = useQuery({ queryKey: ['rfq', rfqId], queryFn: () => fetchRfq(rfqId), enabled: !!rfqId })
  const { data: warehouses = [] } = useQuery({ queryKey: ['warehouses'], queryFn: fetchWarehouses })

  const refresh = (updated?: Rfq) => {
    if (updated) queryClient.setQueryData(['rfq', rfqId], updated)
    queryClient.invalidateQueries({ queryKey: ['rfq', rfqId] })
    queryClient.invalidateQueries({ queryKey: ['rfqs'] })
  }

  const cancelMutation = useMutation({
    mutationFn: () => cancelRfq(rfqId),
    onSuccess: (r) => {
      toast.success('RFQ cancelled')
      refresh(r)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })
  const deleteMutation = useMutation({
    mutationFn: () => deleteRfq(rfqId),
    onSuccess: () => {
      toast.success('RFQ deleted')
      queryClient.invalidateQueries({ queryKey: ['rfqs'] })
      navigate('/rfqs')
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  // Lowest quoted price per item, and the lowest full quote — highlighted in the comparison.
  const best = useMemo(() => {
    const perItem: Record<number, number> = {}
    const quoted = (rfq?.suppliers ?? []).filter((s) => s.quoted_at)
    for (const item of rfq?.items ?? []) {
      const prices = quoted.map((s) => s.prices[item.id]).filter((p): p is number => p !== undefined)
      if (prices.length) perItem[item.id] = Math.min(...prices)
    }
    const full = quoted.filter((s) => s.items_priced === (rfq?.items?.length ?? 0) && s.quote_total !== null)
    const lowestTotal = full.length ? Math.min(...full.map((s) => s.quote_total as number)) : null
    return { perItem, lowestTotal }
  }, [rfq])

  if (isLoading || !rfq) return <div className="p-6 text-sm text-muted-foreground">Loading request for quotation…</div>

  const open = rfq.status === 'draft' || rfq.status === 'sent'
  const canManage = can('purchase-orders.create')
  const items = rfq.items ?? []
  const suppliers = rfq.suppliers ?? []
  const anyQuote = suppliers.some((s) => s.quoted_at)
  const warehouseName = warehouses.find((w) => w.id === rfq.warehouse_id)?.name

  const download = async () => {
    setDownloading(true)
    try {
      await downloadRfqPdf(rfq)
    } catch (error) {
      toast.error(getApiErrorInfo(error).message)
    } finally {
      setDownloading(false)
    }
  }

  return (
    <div>
      <PageHeader
        parent="Requests for Quotation"
        title={`${rfq.reference_number} — ${rfq.title}`}
        action={
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" disabled={downloading} onClick={download}>
              <Download className="h-4 w-4" />
              {downloading ? 'Downloading…' : 'Download PDF'}
            </Button>
            {canManage && rfq.status === 'draft' && (
              <Button variant="outline" onClick={() => navigate(`/rfqs/${rfq.id}/edit`)}>
                <Pencil className="h-4 w-4" />
                Edit
              </Button>
            )}
            {canManage && open && (
              <Button variant="outline" className="text-destructive hover:text-destructive" disabled={cancelMutation.isPending} onClick={() => window.confirm('Cancel this RFQ?') && cancelMutation.mutate()}>
                <Ban className="h-4 w-4" />
                Cancel RFQ
              </Button>
            )}
            {canManage && (rfq.status === 'draft' || rfq.status === 'cancelled') && (
              <Button variant="outline" className="text-destructive hover:text-destructive" disabled={deleteMutation.isPending} onClick={() => window.confirm('Delete this RFQ?') && deleteMutation.mutate()}>
                <Trash2 className="h-4 w-4" />
                Delete
              </Button>
            )}
            {canManage && open && (
              <Button onClick={() => setSending(true)}>
                <Mail className="h-4 w-4" />
                Send to suppliers
              </Button>
            )}
          </div>
        }
      />

      <Card className="mb-4">
        <CardContent className="grid grid-cols-2 gap-6 p-6 sm:grid-cols-5">
          <Info label="Status">
            <StatusBadge label={rfq.status} variant={RFQ_STATUS_VARIANT[rfq.status]} />
          </Info>
          <Info label="Issued">{formatDate(rfq.issue_date)}</Info>
          <Info label="Quotes due">{rfq.response_due_date ? formatDate(rfq.response_due_date) : '—'}</Info>
          <Info label="Deliver to">{warehouseName ?? 'Not chosen yet'}</Info>
          <Info label="Purchase order">
            {rfq.purchase_order_id ? (
              <button type="button" className="font-medium text-primary hover:underline" onClick={() => navigate(`/purchase-orders/${rfq.purchase_order_id}`)}>
                {rfq.purchase_order_reference ?? `PO #${rfq.purchase_order_id}`}
              </button>
            ) : (
              '—'
            )}
            {rfq.selected_supplier_name && <p className="text-xs text-muted-foreground">{rfq.selected_supplier_name}</p>}
          </Info>
          {rfq.notes && (
            <div className="col-span-2 sm:col-span-5">
              <p className="text-xs font-semibold uppercase text-muted-foreground">Notes for suppliers</p>
              <p className="mt-1 whitespace-pre-line text-sm text-foreground">{rfq.notes}</p>
            </div>
          )}
        </CardContent>
      </Card>

      <Card className="mb-4">
        <CardContent className="p-6">
          <p className="mb-3 text-sm font-semibold text-foreground">Suppliers asked</p>
          <div className="overflow-x-auto">
            <table className="w-full min-w-[720px] text-sm">
              <thead>
                <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                  <th className="py-2 pr-3">Supplier</th>
                  <th className="py-2 pr-3">Sent</th>
                  <th className="py-2 pr-3">Quote</th>
                  <th className="py-2 pr-3 text-right">Quote total</th>
                  <th className="py-2 pr-3 text-right">Delivery</th>
                  <th className="py-2" />
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {suppliers.map((s) => {
                  const isBest = best.lowestTotal !== null && s.quote_total === best.lowestTotal
                  const chosen = rfq.selected_supplier_id === s.supplier_id
                  return (
                    <tr key={s.supplier_id} className={chosen ? 'bg-success-bg/40' : ''}>
                      <td className="py-2.5 pr-3">
                        <p className="font-medium text-foreground">{s.name}</p>
                        <p className={`text-xs ${s.email ? 'text-muted-foreground' : 'text-warning'}`}>{s.email ?? 'No email address'}</p>
                      </td>
                      <td className="py-2.5 pr-3">
                        {s.sent_at ? <StatusBadge label={`Sent ${formatDate(s.sent_at)}`} variant="success" /> : <StatusBadge label="Not sent" variant="neutral" />}
                      </td>
                      <td className="py-2.5 pr-3">
                        {s.quoted_at ? (
                          <span className="text-foreground">
                            {s.items_priced} of {items.length} priced
                            {s.quote_notes ? <span className="block text-xs text-muted-foreground">{s.quote_notes}</span> : null}
                          </span>
                        ) : (
                          <span className="text-muted-foreground">Waiting</span>
                        )}
                      </td>
                      <td className="py-2.5 pr-3 text-right tabular-nums">
                        {s.quote_total !== null ? (
                          <span className={isBest ? 'font-bold text-success' : 'text-foreground'}>
                            {formatCurrency(s.quote_total)}
                            {isBest && <span className="block text-xs font-medium">Lowest</span>}
                          </span>
                        ) : (
                          '—'
                        )}
                      </td>
                      <td className="py-2.5 pr-3 text-right">{s.delivery_days !== null ? `${s.delivery_days} days` : '—'}</td>
                      <td className="py-2.5 text-right">
                        {chosen ? (
                          <StatusBadge label="Chosen" variant="success" />
                        ) : (
                          canManage &&
                          open && (
                            <div className="flex justify-end gap-2">
                              <Button size="sm" variant="outline" onClick={() => setQuoting(s)}>
                                {s.quoted_at ? 'Edit quote' : 'Enter quote'}
                              </Button>
                              <Button size="sm" onClick={() => setConverting(s)}>
                                <FilePlus2 className="h-3.5 w-3.5" />
                                Create PO
                              </Button>
                            </div>
                          )
                        )}
                      </td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardContent className="p-6">
          <p className="mb-3 text-sm font-semibold text-foreground">{anyQuote ? 'Items and quoted unit prices' : 'Items requested'}</p>
          <div className="overflow-x-auto">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-muted-foreground">
                  <th className="py-2 pr-3">Item</th>
                  <th className="py-2 pr-3 text-right">Quantity</th>
                  {anyQuote &&
                    suppliers
                      .filter((s) => s.quoted_at)
                      .map((s) => (
                        <th key={s.supplier_id} className="py-2 pr-3 text-right">
                          {s.name}
                        </th>
                      ))}
                </tr>
              </thead>
              <tbody className="divide-y divide-border">
                {items.map((item) => (
                  <tr key={item.id}>
                    <td className="py-2.5 pr-3">
                      <p className="font-medium text-foreground">
                        {item.product_name}
                        {item.variant_name ? ` — ${item.variant_name}` : ''}
                      </p>
                      {item.description && <p className="text-xs text-muted-foreground">{item.description}</p>}
                    </td>
                    <td className="py-2.5 pr-3 text-right tabular-nums">{item.quantity}</td>
                    {anyQuote &&
                      suppliers
                        .filter((s) => s.quoted_at)
                        .map((s) => {
                          const p = s.prices[item.id]
                          return (
                            <td key={s.supplier_id} className={`py-2.5 pr-3 text-right tabular-nums ${p !== undefined && p === best.perItem[item.id] ? 'font-bold text-success' : ''}`}>
                              {p !== undefined ? formatCurrency(p) : '—'}
                            </td>
                          )
                        })}
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        </CardContent>
      </Card>

      {sending && <SendDialog rfq={rfq} onClose={() => setSending(false)} onDone={(r) => refresh(r)} />}
      {quoting && <QuoteDialog rfq={rfq} line={quoting} onClose={() => setQuoting(null)} onDone={(r) => refresh(r)} />}
      {converting && (
        <ConvertDialog
          rfq={rfq}
          line={converting}
          warehouses={warehouses}
          onClose={() => setConverting(null)}
          onDone={(poId) => {
            refresh()
            queryClient.invalidateQueries({ queryKey: ['purchase-orders'] })
            navigate(`/purchase-orders/${poId}`)
          }}
        />
      )}
    </div>
  )
}

function Info({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div>
      <p className="text-xs font-semibold uppercase text-muted-foreground">{label}</p>
      <div className="mt-1 text-sm text-foreground">{children}</div>
    </div>
  )
}

/** Email the RFQ (PDF attached) to all suppliers on it or the ones ticked. */
function SendDialog({ rfq, onClose, onDone }: { rfq: Rfq; onClose: () => void; onDone: (rfq?: Rfq) => void }) {
  const suppliers = rfq.suppliers ?? []
  const [chosen, setChosen] = useState<number[]>(() => suppliers.filter((s) => s.email && !s.sent_at).map((s) => s.supplier_id))
  const mutation = useMutation({
    mutationFn: (all: boolean) => sendRfq(rfq.id, all ? undefined : chosen),
    onSuccess: (result) => {
      if (result.sent.length) toast.success(result.message)
      else toast.error(result.message)
      onDone()
      onClose()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="max-w-lg">
        <DialogHeader>
          <DialogTitle>Send {rfq.reference_number} to suppliers</DialogTitle>
        </DialogHeader>
        <p className="text-sm text-muted-foreground">Each supplier gets an email with the RFQ attached as a PDF, addressed to them.</p>
        <div className="max-h-72 space-y-1 overflow-y-auto">
          {suppliers.map((s) => (
            <label key={s.supplier_id} className={`flex items-start gap-2 rounded-lg border border-border p-2.5 ${s.email ? 'cursor-pointer hover:bg-accent/40' : 'opacity-60'}`}>
              <input
                type="checkbox"
                className="mt-1"
                disabled={!s.email}
                checked={chosen.includes(s.supplier_id)}
                onChange={() => setChosen((list) => (list.includes(s.supplier_id) ? list.filter((x) => x !== s.supplier_id) : [...list, s.supplier_id]))}
              />
              <span className="min-w-0 flex-1">
                <span className="block text-sm font-medium text-foreground">{s.name}</span>
                <span className="block text-xs text-muted-foreground">
                  {s.email ?? 'No email address — add one on the supplier to email them'}
                  {s.sent_at ? ` · already sent ${formatDate(s.sent_at)}` : ''}
                </span>
              </span>
            </label>
          ))}
        </div>
        <DialogFooter className="gap-2 sm:justify-between">
          <Button variant="outline" disabled={mutation.isPending} onClick={() => mutation.mutate(true)}>
            Send to all ({suppliers.filter((s) => s.email).length})
          </Button>
          <Button disabled={mutation.isPending || chosen.length === 0} onClick={() => mutation.mutate(false)}>
            <Mail className="h-4 w-4" />
            {mutation.isPending ? 'Sending…' : `Send to selected (${chosen.length})`}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

/** Type in what a supplier quoted: a unit price per item, delivery time, notes. */
function QuoteDialog({ rfq, line, onClose, onDone }: { rfq: Rfq; line: RfqSupplierLine; onClose: () => void; onDone: (rfq: Rfq) => void }) {
  const items = rfq.items ?? []
  const [prices, setPrices] = useState<Record<number, string>>(() =>
    Object.fromEntries(items.map((item) => [item.id, line.prices[item.id] !== undefined ? String(line.prices[item.id]) : ''])),
  )
  const [days, setDays] = useState(line.delivery_days !== null ? String(line.delivery_days) : '')
  const [notes, setNotes] = useState(line.quote_notes ?? '')
  const total = items.reduce((sum, item) => sum + (prices[item.id] ? Number(prices[item.id]) * Number(item.quantity) : 0), 0)

  const mutation = useMutation({
    mutationFn: () =>
      recordRfqQuote(rfq.id, {
        supplier_id: line.supplier_id,
        prices: items.map((item) => ({ rfq_item_id: item.id, unit_price: prices[item.id] === '' ? null : Number(prices[item.id]) })),
        delivery_days: days === '' ? null : Number(days),
        notes: notes.trim() || null,
      }),
    onSuccess: (r) => {
      toast.success(`Quote from ${line.name} saved`)
      onDone(r)
      onClose()
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="max-w-xl">
        <DialogHeader>
          <DialogTitle>Quote from {line.name}</DialogTitle>
        </DialogHeader>
        <div className="max-h-80 space-y-2 overflow-y-auto">
          {items.map((item) => (
            <div key={item.id} className="grid grid-cols-[1fr_80px_140px] items-center gap-3">
              <span className="text-sm text-foreground">{item.product_name}</span>
              <span className="text-right text-sm tabular-nums text-muted-foreground">× {item.quantity}</span>
              <Input type="number" min={0} step="any" value={prices[item.id]} onChange={(e) => setPrices((p) => ({ ...p, [item.id]: e.target.value }))} placeholder="Unit price" />
            </div>
          ))}
        </div>
        <div className="grid grid-cols-2 gap-3">
          <div className="space-y-1.5">
            <Label>Delivery time (days)</Label>
            <Input type="number" min={0} value={days} onChange={(e) => setDays(e.target.value)} />
          </div>
          <div className="space-y-1.5">
            <Label>Notes</Label>
            <Input value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="Validity, terms…" />
          </div>
        </div>
        <p className="text-right text-sm">
          Quote total: <span className="font-bold tabular-nums">{formatCurrency(total)}</span>
        </p>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button disabled={mutation.isPending} onClick={() => mutation.mutate()}>
            {mutation.isPending ? 'Saving…' : 'Save quote'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}

/** Turn the RFQ into a purchase order for this supplier, at their quoted prices. */
function ConvertDialog({
  rfq,
  line,
  warehouses,
  onClose,
  onDone,
}: {
  rfq: Rfq
  line: RfqSupplierLine
  warehouses: { id: number; name: string }[]
  onClose: () => void
  onDone: (poId: number) => void
}) {
  const [warehouseId, setWarehouseId] = useState(rfq.warehouse_id ? String(rfq.warehouse_id) : '')
  const [orderDate, setOrderDate] = useState(localToday)
  const unpriced = (rfq.items?.length ?? 0) - line.items_priced

  const mutation = useMutation({
    mutationFn: () => convertRfq(rfq.id, { supplier_id: line.supplier_id, warehouse_id: warehouseId ? Number(warehouseId) : null, order_date: orderDate }),
    onSuccess: (po) => {
      toast.success(`Purchase order ${po.reference_number} created for ${line.name}`)
      onDone(po.id)
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  return (
    <Dialog open onOpenChange={(o) => !o && onClose()}>
      <DialogContent className="max-w-md">
        <DialogHeader>
          <DialogTitle>Create purchase order for {line.name}</DialogTitle>
        </DialogHeader>
        <div className="space-y-4">
          <div className="rounded-lg bg-accent p-3 text-sm">
            {line.quote_total !== null ? (
              <>
                Quoted total <span className="font-bold">{formatCurrency(line.quote_total)}</span>
                {unpriced > 0 && <span className="block text-xs text-muted-foreground">{unpriced} item(s) not priced — they use the product's cost price.</span>}
              </>
            ) : (
              <span>No quote recorded for this supplier — the order uses each product's cost price. You can change prices on the purchase order before submitting it.</span>
            )}
          </div>
          <div className="space-y-1.5">
            <Label required>Deliver to (warehouse)</Label>
            <Select value={warehouseId} onValueChange={setWarehouseId}>
              <SelectTrigger>
                <SelectValue placeholder="Choose a warehouse" />
              </SelectTrigger>
              <SelectContent>
                {warehouses.map((w) => (
                  <SelectItem key={w.id} value={String(w.id)}>
                    {w.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <div className="space-y-1.5">
            <Label>Order date</Label>
            <Input type="date" value={orderDate} onChange={(e) => setOrderDate(e.target.value)} />
          </div>
          <p className="text-xs text-muted-foreground">The purchase order is created as a draft; submit and approve it as usual. This RFQ is then closed.</p>
        </div>
        <DialogFooter>
          <Button variant="outline" onClick={onClose}>
            Cancel
          </Button>
          <Button disabled={mutation.isPending || !warehouseId} onClick={() => mutation.mutate()}>
            <FilePlus2 className="h-4 w-4" />
            {mutation.isPending ? 'Creating…' : 'Create purchase order'}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
