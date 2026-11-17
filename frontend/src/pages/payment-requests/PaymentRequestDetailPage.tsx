import { useState, type ReactNode } from 'react'
import { Link, useNavigate, useParams } from 'react-router-dom'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Ban, CheckCircle2, Circle, Clock, Download, FileText, Paperclip, Pencil, Send, Trash2, Wallet, XCircle } from 'lucide-react'

import {
  approvePaymentRequest,
  cancelPaymentRequest,
  deletePaymentRequest,
  deletePaymentRequestAttachment,
  downloadPaymentRequestAttachment,
  downloadPaymentRequestVoucher,
  fetchPaymentRequest,
  PAYMENT_REQUEST_STATUS_LABEL,
  payPaymentRequest,
  rejectPaymentRequest,
  submitPaymentRequest,
  uploadPaymentRequestAttachments,
  type PaymentRequest,
  type PaymentRequestApproval,
} from '@/api/paymentRequests'
import { fetchPaymentTypes } from '@/api/pos'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { getApiErrorInfo } from '@/lib/api-errors'
import { useAuthStore } from '@/lib/auth-store'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { PAYMENT_REQUEST_STATUS_VARIANT } from './status'

type DialogKind = 'approve' | 'reject' | 'pay' | 'cancel' | null

const ACTION_LABEL: Record<string, string> = {
  created: 'Created',
  edited: 'Edited',
  submitted: 'Submitted for approval',
  resubmitted: 'Resubmitted after changes',
  approved: 'Approved',
  rejected: 'Rejected',
  paid: 'Paid',
  cancelled: 'Cancelled',
  attachment_added: 'Document added',
  attachment_removed: 'Document removed',
}

const dateTime = (value: string | null) => (value ? new Date(value).toLocaleString(undefined, { dateStyle: 'medium', timeStyle: 'short' }) : '—')

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div>
      <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{label}</p>
      <div className="mt-0.5 text-sm text-foreground">{children}</div>
    </div>
  )
}

function StepIcon({ status }: { status: PaymentRequestApproval['status'] }) {
  if (status === 'approved') return <CheckCircle2 className="h-5 w-5 text-success" />
  if (status === 'rejected') return <XCircle className="h-5 w-5 text-danger" />
  if (status === 'cancelled') return <Circle className="h-5 w-5 text-muted-foreground" />
  return <Clock className="h-5 w-5 text-warning" />
}

export function PaymentRequestDetailPage() {
  const { id } = useParams<{ id: string }>()
  const requestId = Number(id)
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const baseCurrency = useAuthStore((state) => state.company?.currency_code) ?? 'USD'

  const [dialog, setDialog] = useState<DialogKind>(null)
  const [comment, setComment] = useState('')
  const [payment, setPayment] = useState({ payment_date: new Date().toISOString().slice(0, 10), payment_type_id: '', reference: '' })

  const { data: pr, isLoading, isError } = useQuery({ queryKey: ['payment-request', requestId], queryFn: () => fetchPaymentRequest(requestId), enabled: !!requestId })
  const { data: paymentTypes } = useQuery({ queryKey: ['payment-types'], queryFn: fetchPaymentTypes, enabled: dialog === 'pay' })

  const done = (message: string) => (updated?: PaymentRequest) => {
    toast.success(message)
    if (updated) queryClient.setQueryData(['payment-request', requestId], updated)
    queryClient.invalidateQueries({ queryKey: ['payment-requests'] })
    queryClient.invalidateQueries({ queryKey: ['payment-request', requestId] })
    setDialog(null)
    setComment('')
  }
  const failed = (error: unknown) => toast.error(getApiErrorInfo(error).message)

  const submit = useMutation({ mutationFn: () => submitPaymentRequest(requestId), onSuccess: done('Submitted for approval'), onError: failed })
  const approve = useMutation({ mutationFn: () => approvePaymentRequest(requestId, comment || undefined), onSuccess: done('Approved'), onError: failed })
  const reject = useMutation({ mutationFn: () => rejectPaymentRequest(requestId, comment), onSuccess: done('Rejected and returned to the requester'), onError: failed })
  const cancel = useMutation({ mutationFn: () => cancelPaymentRequest(requestId, comment || undefined), onSuccess: done('Payment request cancelled'), onError: failed })
  const pay = useMutation({
    mutationFn: () => payPaymentRequest(requestId, { payment_date: payment.payment_date, payment_type_id: payment.payment_type_id ? Number(payment.payment_type_id) : null, reference: payment.reference || null }),
    onSuccess: done('Payment recorded'),
    onError: failed,
  })
  const remove = useMutation({
    mutationFn: () => deletePaymentRequest(requestId),
    onSuccess: () => {
      toast.success('Draft deleted')
      queryClient.invalidateQueries({ queryKey: ['payment-requests'] })
      navigate('/payment-requests')
    },
    onError: failed,
  })
  const upload = useMutation({ mutationFn: (files: File[]) => uploadPaymentRequestAttachments(requestId, files), onSuccess: done('Document added'), onError: failed })
  const removeFile = useMutation({ mutationFn: (attachmentId: number) => deletePaymentRequestAttachment(requestId, attachmentId), onSuccess: done('Document removed'), onError: failed })

  if (isLoading) return <p className="text-sm text-muted-foreground">Loading…</p>
  if (isError || !pr) return <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load this payment request.</p>

  const can = pr.can ?? { update: false, delete: false, submit: false, decide: false, pay: false, cancel: false, attach: false }
  const rounds = Object.entries(
    (pr.approvals ?? []).reduce<Record<number, PaymentRequestApproval[]>>((acc, step) => {
      ;(acc[step.round] ??= []).push(step)
      return acc
    }, {}),
  ).sort(([a], [b]) => Number(b) - Number(a))

  return (
    <div>
      <PageHeader
        parent="Payment Requests"
        title={pr.reference_number}
        action={
          <div className="flex flex-wrap gap-2">
            <Button variant="outline" onClick={() => downloadPaymentRequestVoucher(pr).catch(failed)}>
              <Download className="h-4 w-4" />
              Voucher PDF
            </Button>
            {can.update && (
              <Button variant="outline" onClick={() => navigate(`/payment-requests/${pr.id}/edit`)}>
                <Pencil className="h-4 w-4" />
                Edit
              </Button>
            )}
            {can.delete && (
              <Button variant="outline" className="text-destructive" disabled={remove.isPending} onClick={() => window.confirm('Delete this draft?') && remove.mutate()}>
                <Trash2 className="h-4 w-4" />
                Delete
              </Button>
            )}
            {can.cancel && pr.status !== 'draft' && (
              <Button variant="outline" className="text-destructive" onClick={() => setDialog('cancel')}>
                <Ban className="h-4 w-4" />
                Cancel request
              </Button>
            )}
            {can.submit && (
              <Button disabled={submit.isPending} onClick={() => submit.mutate()}>
                <Send className="h-4 w-4" />
                {pr.status === 'rejected' ? 'Resubmit for approval' : 'Submit for approval'}
              </Button>
            )}
            {can.decide && (
              <>
                <Button variant="outline" className="text-destructive" onClick={() => setDialog('reject')}>
                  <XCircle className="h-4 w-4" />
                  Reject
                </Button>
                <Button onClick={() => setDialog('approve')}>
                  <CheckCircle2 className="h-4 w-4" />
                  Approve
                </Button>
              </>
            )}
            {can.pay && (
              <Button onClick={() => setDialog('pay')}>
                <Wallet className="h-4 w-4" />
                Record payment
              </Button>
            )}
          </div>
        }
      />

      <div className="grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div className="space-y-4 lg:col-span-2">
          <Card>
            <CardContent className="space-y-5 p-6">
              <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                  <p className="text-sm text-muted-foreground">Pay {pr.payee}</p>
                  <p className="text-3xl font-bold tabular-nums">{formatCurrency(pr.amount, pr.currency_code)}</p>
                  {pr.currency_code !== baseCurrency && (
                    <p className="text-sm text-muted-foreground">
                      ≈ {formatCurrency(pr.base_amount, baseCurrency)} at {pr.exchange_rate.toLocaleString()} {pr.currency_code}/{baseCurrency}
                    </p>
                  )}
                </div>
                <div className="text-right">
                  <StatusBadge label={PAYMENT_REQUEST_STATUS_LABEL[pr.status]} variant={PAYMENT_REQUEST_STATUS_VARIANT[pr.status]} className="text-sm" />
                  {pr.current_step && <p className="mt-1 text-xs text-muted-foreground">Waiting for step {pr.current_step.sequence}: {pr.current_step.label}</p>}
                </div>
              </div>

              <p className="whitespace-pre-line text-sm">{pr.description}</p>

              <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                <Field label="Date">{formatDate(pr.request_date)}</Field>
                <Field label="Requested by">{pr.requester_name}</Field>
                <Field label="Expense account">{pr.supplier_bill_id ? 'Settles the supplier bill' : (pr.expense_account ?? 'General Operating Expenses')}</Field>
                {pr.grant_code && <Field label="SOF">{pr.grant_code}{pr.dea_code ? ` / ${pr.dea_code}` : ''}</Field>}
                {pr.project_name && <Field label="Project">{pr.project_name}</Field>}
                {pr.cost_centre_code && <Field label="Cost centre">{pr.cost_centre_code}</Field>}
                {pr.supplier_bill_id && <Field label="Supplier bill"><Link className="text-primary hover:underline" to={`/supplier-bills/${pr.supplier_bill_id}`}>{pr.supplier_bill_reference}</Link></Field>}
                {pr.purchase_order_id && <Field label="Purchase order"><Link className="text-primary hover:underline" to={`/purchase-orders/${pr.purchase_order_id}`}>{pr.purchase_order_reference}</Link></Field>}
                {pr.rfq_id && <Field label="RFQ"><Link className="text-primary hover:underline" to={`/rfqs/${pr.rfq_id}`}>{pr.rfq_reference}</Link></Field>}
              </div>
            </CardContent>
          </Card>

          {pr.status === 'paid' && (
            <Card>
              <CardContent className="grid grid-cols-2 gap-4 p-6 sm:grid-cols-3">
                <Field label="Paid on">{pr.payment_date ? formatDate(pr.payment_date) : '—'}</Field>
                <Field label="Paid by">{pr.paid_by_name}</Field>
                <Field label="Paid from">{pr.payment_account ?? '—'}{pr.payment_type_name ? ` · ${pr.payment_type_name}` : ''}</Field>
                <Field label="Payment reference">{pr.payment_reference ?? '—'}</Field>
                <Field label="Journal entry">
                  {pr.journal_entry_id ? <Link className="text-primary hover:underline" to={`/journal-entries/${pr.journal_entry_id}`}>{pr.journal_entry_reference}</Link> : '—'}
                </Field>
              </CardContent>
            </Card>
          )}

          <Card>
            <CardContent className="p-6">
              <p className="mb-3 text-sm font-semibold">History</p>
              <ol className="space-y-3">
                {(pr.events ?? []).map((event) => (
                  <li key={event.id} className="flex gap-3 text-sm">
                    <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary" />
                    <div>
                      <p>
                        <span className="font-medium">{ACTION_LABEL[event.action] ?? event.action}</span> by {event.user_name ?? 'someone'}
                        <span className="text-muted-foreground"> · {dateTime(event.at)}</span>
                      </p>
                      {event.comment && <p className="text-muted-foreground">“{event.comment}”</p>}
                    </div>
                  </li>
                ))}
              </ol>
            </CardContent>
          </Card>
        </div>

        <div className="space-y-4">
          <Card>
            <CardContent className="p-6">
              <p className="mb-3 text-sm font-semibold">Approvals</p>
              {rounds.length === 0 ? (
                <p className="text-sm text-muted-foreground">Not submitted yet. When it is, the approvers for this amount will show here.</p>
              ) : (
                rounds.map(([round, steps], index) => (
                  <div key={round} className={index > 0 ? 'mt-4 border-t border-border pt-4 opacity-70' : ''}>
                    {rounds.length > 1 && <p className="mb-2 text-xs font-semibold uppercase text-muted-foreground">{index === 0 ? 'Current submission' : `Earlier submission ${round}`}</p>}
                    <ol className="space-y-3">
                      {steps
                        .sort((a, b) => a.sequence - b.sequence)
                        .map((step) => (
                          <li key={step.id} className="flex gap-3">
                            <StepIcon status={step.status} />
                            <div className="text-sm">
                              <p className="font-medium">
                                {step.sequence}. {step.label}
                              </p>
                              {step.decided_by_name ? (
                                <p className="text-muted-foreground">
                                  {step.status === 'approved' ? 'Approved' : 'Rejected'} by {step.decided_by_name} · {dateTime(step.decided_at)}
                                </p>
                              ) : (
                                <p className="text-muted-foreground">{step.status === 'cancelled' ? 'Not needed' : `Waiting (${step.approver ?? step.label})`}</p>
                              )}
                              {step.comment && <p className="text-muted-foreground">“{step.comment}”</p>}
                            </div>
                          </li>
                        ))}
                    </ol>
                  </div>
                ))
              )}
            </CardContent>
          </Card>

          <Card>
            <CardContent className="p-6">
              <p className="mb-3 text-sm font-semibold">Supporting documents</p>
              {(pr.attachments ?? []).length === 0 && <p className="mb-2 text-sm text-muted-foreground">None attached.</p>}
              <ul className="mb-3 space-y-2">
                {(pr.attachments ?? []).map((file) => (
                  <li key={file.id} className="flex items-center justify-between gap-2 text-sm">
                    <button type="button" className="flex items-center gap-2 text-left text-primary hover:underline" onClick={() => downloadPaymentRequestAttachment(pr.id, file).catch(failed)}>
                      <FileText className="h-4 w-4 shrink-0" />
                      <span className="break-all">{file.file_name}</span>
                    </button>
                    {can.update && ['draft', 'rejected'].includes(pr.status) && (
                      <button type="button" className="text-muted-foreground hover:text-destructive" aria-label={`Remove ${file.file_name}`} onClick={() => removeFile.mutate(file.id)}>
                        <Trash2 className="h-4 w-4" />
                      </button>
                    )}
                  </li>
                ))}
              </ul>
              {can.attach && (
                <label className="inline-flex cursor-pointer items-center gap-2 rounded-md border border-border px-3 py-1.5 text-sm hover:bg-muted">
                  <Paperclip className="h-4 w-4" />
                  {upload.isPending ? 'Uploading…' : 'Add document'}
                  <input
                    type="file"
                    multiple
                    className="hidden"
                    accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx,.xls,.xlsx"
                    onChange={(event) => {
                      const files = Array.from(event.target.files ?? [])
                      if (files.length) upload.mutate(files)
                      event.target.value = ''
                    }}
                  />
                </label>
              )}
            </CardContent>
          </Card>
        </div>
      </div>

      {/* Approve / reject / cancel: a comment (required to reject) */}
      <Dialog open={dialog === 'approve' || dialog === 'reject' || dialog === 'cancel'} onOpenChange={(open) => !open && setDialog(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{dialog === 'approve' ? `Approve ${pr.reference_number}` : dialog === 'reject' ? `Reject ${pr.reference_number}` : `Cancel ${pr.reference_number}`}</DialogTitle>
          </DialogHeader>
          <p className="text-sm text-muted-foreground">
            {dialog === 'approve' && `${formatCurrency(pr.amount, pr.currency_code)} to ${pr.payee}. ${pr.current_step ? `You are approving step ${pr.current_step.sequence}: ${pr.current_step.label}.` : ''}`}
            {dialog === 'reject' && 'It goes back to the requester, who can change it and submit it again.'}
            {dialog === 'cancel' && 'A cancelled request cannot be approved or paid.'}
          </p>
          <div className="space-y-1.5">
            <Label required={dialog === 'reject'}>{dialog === 'reject' ? 'Reason' : 'Comment (optional)'}</Label>
            <Textarea rows={3} value={comment} onChange={(event) => setComment(event.target.value)} />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialog(null)}>
              Back
            </Button>
            {dialog === 'approve' && (
              <Button disabled={approve.isPending} onClick={() => approve.mutate()}>
                Approve
              </Button>
            )}
            {dialog === 'reject' && (
              <Button variant="destructive" disabled={reject.isPending || !comment.trim()} onClick={() => reject.mutate()}>
                Reject
              </Button>
            )}
            {dialog === 'cancel' && (
              <Button variant="destructive" disabled={cancel.isPending} onClick={() => cancel.mutate()}>
                Cancel request
              </Button>
            )}
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Pay */}
      <Dialog open={dialog === 'pay'} onOpenChange={(open) => !open && setDialog(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Record payment of {pr.reference_number}</DialogTitle>
          </DialogHeader>
          <p className="text-sm text-muted-foreground">
            {formatCurrency(pr.amount, pr.currency_code)} to {pr.payee}. {pr.supplier_bill_id ? 'This settles the linked supplier bill.' : 'Posted to the expense account and coding on the request.'}
          </p>
          <div className="grid grid-cols-1 gap-3">
            <div className="space-y-1.5">
              <Label required>Payment date</Label>
              <Input type="date" value={payment.payment_date} onChange={(event) => setPayment({ ...payment, payment_date: event.target.value })} />
            </div>
            <div className="space-y-1.5">
              <Label>Payment method</Label>
              <Select value={payment.payment_type_id} onValueChange={(value) => setPayment({ ...payment, payment_type_id: value })}>
                <SelectTrigger>
                  <SelectValue placeholder="Not specified" />
                </SelectTrigger>
                <SelectContent>
                  {(paymentTypes ?? []).map((type) => (
                    <SelectItem key={type.id} value={String(type.id)}>
                      {type.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>
            <div className="space-y-1.5">
              <Label>Reference (cheque, transfer or receipt no.)</Label>
              <Input value={payment.reference} onChange={(event) => setPayment({ ...payment, reference: event.target.value })} />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDialog(null)}>
              Back
            </Button>
            <Button disabled={pay.isPending || !payment.payment_date} onClick={() => pay.mutate()}>
              Record payment
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
