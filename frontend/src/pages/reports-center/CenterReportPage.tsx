import { useMemo, useState } from 'react'
import { Navigate, useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'
import { Calendar, Printer, RefreshCw } from 'lucide-react'

import { fetchCenterReport, type AuditChange, type CenterColumn, type CenterReport, type CenterRow, type JournalLine } from '@/api/reportsCenter'
import { fetchBranches } from '@/api/branches'
import { getApiErrorInfo } from '@/lib/api-errors'
import { formatCurrency } from '@/lib/currency'
import { formatDate } from '@/lib/format'
import { useAuthStore } from '@/lib/auth-store'
import { usePermissions } from '@/hooks/use-permissions'
import { DataTable, type DataTableColumn } from '@/components/shared/DataTable'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { findEntry, type CenterEntry } from './catalog'

const ALL = '__all'

/** YYYY-MM-DD in local time (toISOString would shift a local midnight to the previous day east of UTC). */
function toISODate(date: Date): string {
  const pad = (n: number) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}
const startOfMonth = () => new Date(new Date().getFullYear(), new Date().getMonth(), 1)

function quickRanges(): { label: string; from: string; to: string }[] {
  const today = new Date()
  const d = (y: number, m: number, day: number) => toISODate(new Date(y, m, day))
  const y = today.getFullYear()
  const m = today.getMonth()
  const monday = new Date(today)
  monday.setDate(today.getDate() - ((today.getDay() + 6) % 7))
  const yesterday = new Date(today)
  yesterday.setDate(today.getDate() - 1)
  const q = Math.floor(m / 3) * 3
  return [
    { label: 'Today', from: toISODate(today), to: toISODate(today) },
    { label: 'Yesterday', from: toISODate(yesterday), to: toISODate(yesterday) },
    { label: 'This week', from: toISODate(monday), to: toISODate(today) },
    { label: 'This month', from: d(y, m, 1), to: toISODate(today) },
    { label: 'Last month', from: d(y, m - 1, 1), to: d(y, m, 0) },
    { label: 'This quarter', from: d(y, q, 1), to: toISODate(today) },
    { label: 'This year', from: d(y, 0, 1), to: toISODate(today) },
    { label: 'Last year', from: d(y - 1, 0, 1), to: d(y - 1, 11, 31) },
  ]
}

function formatValue(type: CenterColumn['type'], value: unknown): string {
  if (value === null || value === undefined || value === '') return '—'
  const n = Number(value)
  switch (type) {
    case 'money':
      return formatCurrency(n)
    case 'number':
      return n.toLocaleString(undefined, { maximumFractionDigits: 2 })
    case 'percent':
      return `${n.toFixed(1)}%`
    case 'date':
      return formatDate(String(value))
    default:
      return String(value)
  }
}

/** One Reports Center report: its filters, the figures, and one table (CSV/PDF built in). */
export function CenterReportPage() {
  const { reportId = 'overview' } = useParams()
  const entry = findEntry(reportId)
  const { can } = usePermissions()

  if (!entry) return <Navigate to="/reports-center/overview" replace />
  if (!can(entry.permission ?? 'reports.view')) return <p className="text-sm text-muted-foreground">You don't have access to this report.</p>
  if (entry.kind === 'page') {
    const Page = entry.component
    return <Page />
  }
  return <CenterReport key={entry.id} entry={entry} />
}

export function CenterReport({ entry }: { entry: CenterEntry }) {
  const company = useAuthStore((state) => state.company)
  const [from, setFrom] = useState(() => toISODate(startOfMonth()))
  const [to, setTo] = useState(() => toISODate(new Date()))
  const [branchId, setBranchId] = useState(ALL)
  const [groupBy, setGroupBy] = useState(entry.groupBy?.[0]?.value ?? '')
  const [source, setSource] = useState(entry.source?.[0]?.value ?? 'all')
  const [status, setStatus] = useState(ALL)
  const [accountType, setAccountType] = useState(ALL)

  const { data: branches = [] } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches, staleTime: 5 * 60_000 })
  const params = {
    from,
    to,
    branch_id: branchId === ALL ? undefined : Number(branchId),
    group_by: groupBy || undefined,
    source: entry.source ? source : undefined,
    status: status === ALL ? undefined : status,
    account_type: entry.accountType && accountType !== ALL ? accountType : undefined,
  }
  const { data, isFetching, isError, error, refetch } = useQuery({
    queryKey: ['reports-center', entry.report, params],
    queryFn: () => fetchCenterReport(entry.report, params),
  })

  const groupLabel = entry.groupBy?.find((g) => g.value === groupBy)?.label
  const branchLabel = branches.find((b) => String(b.id) === branchId)?.name

  return (
    <div className="space-y-4">
      {/* Filters */}
      <Card className="print:hidden">
        <CardContent className="flex flex-wrap items-end gap-3 p-4">
          {!entry.noDates && (
            <>
              <Field label="From">
                <Input type="date" value={from} max={to} onChange={(e) => setFrom(e.target.value)} className="h-9 w-[150px]" />
              </Field>
              <Field label="To">
                <Input type="date" value={to} min={from} onChange={(e) => setTo(e.target.value)} className="h-9 w-[150px]" />
              </Field>
              <Field label="Quick range">
                <Select
                  value=""
                  onValueChange={(label) => {
                    const r = quickRanges().find((x) => x.label === label)
                    if (r) {
                      setFrom(r.from)
                      setTo(r.to)
                    }
                  }}
                >
                  <SelectTrigger className="h-9 w-[140px]">
                    <Calendar className="mr-1 h-3.5 w-3.5 text-muted-foreground" />
                    <SelectValue placeholder="Pick…" />
                  </SelectTrigger>
                  <SelectContent>
                    {quickRanges().map((r) => (
                      <SelectItem key={r.label} value={r.label}>
                        {r.label}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </Field>
            </>
          )}
          {branches.length > 1 && (
            <Field label="Branch">
              <Select value={branchId} onValueChange={setBranchId}>
                <SelectTrigger className="h-9 w-[170px]">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={ALL}>All branches</SelectItem>
                  {branches.map((b) => (
                    <SelectItem key={b.id} value={String(b.id)}>
                      {b.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
          )}
          {entry.groupBy && (
            <Field label="Report">
              <Select value={groupBy} onValueChange={setGroupBy}>
                <SelectTrigger className="h-9 w-[200px]">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {entry.groupBy.map((g) => (
                    <SelectItem key={g.value} value={g.value}>
                      {g.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
          )}
          {entry.source && (
            <Field label="Include">
              <Select value={source} onValueChange={setSource}>
                <SelectTrigger className="h-9 w-[200px]">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  {entry.source.map((s) => (
                    <SelectItem key={s.value} value={s.value}>
                      {s.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
          )}
          {entry.status && (
            <Field label={entry.status.label}>
              <Select value={status} onValueChange={setStatus}>
                <SelectTrigger className="h-9 w-[190px]">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={ALL}>{entry.status.allLabel ?? 'All'}</SelectItem>
                  {entry.status.options.map((s) => (
                    <SelectItem key={s.value} value={s.value}>
                      {s.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
          )}
          {entry.accountType && (
            <Field label="Account type">
              <Select value={accountType} onValueChange={setAccountType}>
                <SelectTrigger className="h-9 w-[160px]">
                  <SelectValue />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value={ALL}>All types</SelectItem>
                  {['asset', 'liability', 'equity', 'revenue', 'expense'].map((t) => (
                    <SelectItem key={t} value={t}>
                      {t.charAt(0).toUpperCase() + t.slice(1)}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </Field>
          )}
          <div className="ml-auto flex gap-2">
            <Button size="sm" className="h-9 gap-1.5" onClick={() => refetch()} disabled={isFetching}>
              <RefreshCw className={`h-3.5 w-3.5 ${isFetching ? 'animate-spin' : ''}`} /> Refresh
            </Button>
            <Button variant="outline" size="sm" className="h-9 gap-1.5" onClick={() => window.print()}>
              <Printer className="h-3.5 w-3.5" /> Print
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Letterhead, printed only */}
      <div className="hidden border-b pb-3 print:block">
        <div className="flex items-center gap-3">
          {company?.logo_url && <img src={company.logo_url} alt="" className="h-12 w-auto object-contain" />}
          <div>
            <p className="text-lg font-bold">{company?.name}</p>
            <p className="text-base font-semibold">
              {entry.label}
              {groupLabel ? ` — ${groupLabel}` : ''}
            </p>
            <p className="text-xs text-gray-600">
              {entry.noDates ? `As at ${formatDate(toISODate(new Date()))}` : `${formatDate(from)} – ${formatDate(to)}`}
              {branchLabel ? ` · ${branchLabel}` : ' · All branches'} · Printed {new Date().toLocaleString()}
            </p>
          </div>
        </div>
      </div>

      {isError && (
        <Card>
          <CardContent className="p-4 text-sm text-destructive">{getApiErrorInfo(error).message}</CardContent>
        </Card>
      )}

      {data?.kpis && <KpiGrid data={data} />}
      {data && (entry.report === 'overview' || groupBy === 'day' || groupBy === 'month') && data.rows.length > 1 && <TrendChart data={data} />}
      {data && <TotalsStrip data={data} />}

      <ReportTable entry={entry} data={data} loading={isFetching && !data} fileName={`${entry.id}${groupBy ? `-${groupBy}` : ''}-${from}-${to}`} title={groupLabel ?? entry.label} />
    </div>
  )
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="space-y-1">
      <p className="text-xs font-medium text-muted-foreground">{label}</p>
      {children}
    </div>
  )
}

function KpiGrid({ data }: { data: CenterReport }) {
  return (
    <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4 print:grid-cols-4">
      {data.kpis!.map((k) => (
        <Card key={k.key}>
          <CardContent className="p-4">
            <p className="text-xs font-medium uppercase tracking-wide text-muted-foreground">{k.label}</p>
            <p className={`mt-1 break-words text-base font-bold tabular-nums sm:text-xl ${k.key === 'net_profit' && k.value < 0 ? 'text-destructive' : 'text-foreground'}`}>
              {formatValue(k.type, k.value)}
            </p>
            {k.sub && <p className="text-xs text-muted-foreground">{k.sub}</p>}
          </CardContent>
        </Card>
      ))}
    </div>
  )
}

function TrendChart({ data }: { data: CenterReport }) {
  const money = data.columns.filter((c) => c.type === 'money' && c.key !== 'net').slice(0, 2)
  const label = data.columns[0]
  const colors = ['hsl(var(--primary))', 'hsl(var(--destructive))']
  if (!label || money.length === 0) return null
  return (
    <Card className="print:hidden">
      <CardContent className="h-64 p-4">
        <ResponsiveContainer width="100%" height="100%">
          {/* Rows come newest first for the table; the chart reads oldest → newest. */}
          <BarChart data={[...data.rows].reverse()} margin={{ left: 8, right: 8 }}>
            <CartesianGrid strokeDasharray="3 3" vertical={false} />
            <XAxis dataKey={label.key} tick={{ fontSize: 11 }} />
            <YAxis tick={{ fontSize: 11 }} tickFormatter={(v) => Intl.NumberFormat(undefined, { notation: 'compact' }).format(Number(v))} />
            <Tooltip formatter={(v) => formatCurrency(Number(v))} />
            {money.length > 1 && <Legend />}
            {money.map((c, i) => (
              <Bar key={c.key} dataKey={c.key} name={c.label} fill={colors[i]} radius={[3, 3, 0, 0]} />
            ))}
          </BarChart>
        </ResponsiveContainer>
      </CardContent>
    </Card>
  )
}

function TotalsStrip({ data }: { data: CenterReport }) {
  const shown = data.columns.filter((c) => (c.type === 'money' || c.type === 'number') && data.totals[c.key] !== undefined)
  if (shown.length === 0) return null
  return (
    <div className="flex flex-wrap gap-x-8 gap-y-2 rounded-lg border border-border bg-card px-4 py-3">
      <p className="self-center text-xs font-semibold uppercase tracking-wide text-muted-foreground">Totals</p>
      {shown.map((c) => (
        <div key={c.key}>
          <p className="text-xs text-muted-foreground">{c.label}</p>
          <p className="font-semibold tabular-nums text-foreground">{formatValue(c.type, data.totals[c.key])}</p>
        </div>
      ))}
    </div>
  )
}

function ReportTable({ entry, data, loading, fileName, title }: { entry: CenterEntry; data?: CenterReport; loading: boolean; fileName: string; title: string }) {
  const columns = useMemo<DataTableColumn<CenterRow>[]>(
    () =>
      (data?.columns ?? []).map((c) => ({
        key: c.key,
        header: c.label,
        sortable: true,
        className: c.type === 'money' || c.type === 'number' || c.type === 'percent' ? 'whitespace-nowrap text-right tabular-nums' : c.type === 'date' ? 'whitespace-nowrap' : undefined,
        accessor: (row) => (c.type === 'text' || c.type === 'date' ? (row[c.key] as string | null) : Number(row[c.key] ?? 0)),
        render: (row) => formatValue(c.type, row[c.key]),
        filterable: false,
      })),
    [data?.columns],
  )

  return (
    <DataTable
      title={title}
      columns={columns}
      data={data?.rows ?? []}
      rowKey={(_, i) => i}
      isLoading={loading}
      emptyTitle="Nothing to report"
      emptySubtext={entry.noDates ? 'No stock recorded.' : 'Nothing in this period — try a wider date range.'}
      exportFileName={fileName}
      dateFilterKey={false}
      toolbarExtra={<span />}
      pageSize={50}
      renderExpanded={
        data?.rows.some((row) => Array.isArray(row.changes))
          ? (row) => <AuditDetails row={row} />
          : data?.rows.some((row) => Array.isArray(row.lines))
            ? (row) => <JournalLines row={row} />
            : undefined
      }
    />
  )
}

/** One journal entry's account lines (the IntelliCare general-ledger drop-down). */
function JournalLines({ row }: { row: CenterRow }) {
  const lines = (row.lines as JournalLine[] | undefined) ?? []
  const totalDebit = lines.reduce((s, l) => s + Number(l.debit), 0)
  const totalCredit = lines.reduce((s, l) => s + Number(l.credit), 0)
  return (
    <div className="px-6 py-4">
      <div className="ml-6 overflow-x-auto rounded-lg border border-border bg-card">
        <table className="w-full text-sm">
          <thead>
            <tr className="border-b border-border bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
              <th className="px-4 py-2.5 font-semibold">Account</th>
              <th className="px-4 py-2.5 font-semibold">Type</th>
              <th className="px-4 py-2.5 text-right font-semibold">Debit</th>
              <th className="px-4 py-2.5 text-right font-semibold">Credit</th>
              <th className="px-4 py-2.5 font-semibold">Description</th>
            </tr>
          </thead>
          <tbody className="divide-y divide-border">
            {lines.map((l, i) => (
              <tr key={i}>
                <td className="whitespace-nowrap px-4 py-2.5">
                  <span className="font-mono text-xs text-primary">{l.code}</span> <span className="text-foreground">{l.account}</span>
                </td>
                <td className="px-4 py-2.5 capitalize text-muted-foreground">{l.type}</td>
                <td className="px-4 py-2.5 text-right tabular-nums">{Number(l.debit) ? formatCurrency(Number(l.debit)) : '—'}</td>
                <td className="px-4 py-2.5 text-right tabular-nums">{Number(l.credit) ? formatCurrency(Number(l.credit)) : '—'}</td>
                <td className="px-4 py-2.5 text-muted-foreground">{l.description || '—'}</td>
              </tr>
            ))}
          </tbody>
          <tfoot>
            <tr className="border-t border-border bg-muted/30 font-semibold">
              <td className="px-4 py-2.5" colSpan={2}>
                Total
                {row.posted_by ? <span className="ml-2 text-xs font-normal text-muted-foreground">posted by {String(row.posted_by)}</span> : null}
              </td>
              <td className="px-4 py-2.5 text-right tabular-nums">{formatCurrency(totalDebit)}</td>
              <td className="px-4 py-2.5 text-right tabular-nums">{formatCurrency(totalCredit)}</td>
              <td />
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  )
}

const fieldLabel = (field: string) => field.replace(/_id$/, '').replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase())

function showValue(value: unknown): string {
  if (value === null || value === undefined || value === '') return '—'
  if (typeof value === 'boolean') return value ? 'Yes' : 'No'
  if (typeof value === 'object') return JSON.stringify(value)
  return String(value)
}

/** What one audit line actually did: each field it changed, from what to what. */
function AuditDetails({ row }: { row: CenterRow }) {
  const changes = (row.changes as AuditChange[] | undefined) ?? []
  const action = String(row.action ?? '')
  return (
    <div className="space-y-3 px-6 py-4">
      <p className="text-sm text-foreground">
        <span className="font-semibold">{String(row.user ?? '')}</span> {action} <span className="font-semibold">{String(row.module ?? '')}</span>
        {row.record ? <span className="text-muted-foreground"> {String(row.record)}</span> : null}
        <span className="text-muted-foreground"> on {String(row.when ?? '')}</span>
      </p>
      {changes.length === 0 ? (
        <p className="text-sm text-muted-foreground">No field details were recorded for this action.</p>
      ) : (
        <div className="overflow-x-auto rounded-md border border-border bg-card">
          <table className="w-full text-sm">
            <thead>
              <tr className="border-b border-border bg-muted/40 text-left text-xs uppercase tracking-wide text-muted-foreground">
                <th className="px-3 py-2 font-semibold">Field</th>
                {action !== 'created' && <th className="px-3 py-2 font-semibold">Before</th>}
                {action !== 'deleted' && <th className="px-3 py-2 font-semibold">{action === 'created' ? 'Value' : 'After'}</th>}
              </tr>
            </thead>
            <tbody>
              {changes.map((change) => (
                <tr key={change.field} className="border-b border-border last:border-0 align-top">
                  <td className="whitespace-nowrap px-3 py-2 font-medium text-foreground">{fieldLabel(change.field)}</td>
                  {action !== 'created' && <td className="break-all px-3 py-2 text-muted-foreground line-through decoration-destructive/50">{showValue(change.from)}</td>}
                  {action !== 'deleted' && <td className="break-all px-3 py-2 font-medium text-primary">{showValue(change.to)}</td>}
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      )}
      {(row.ip || row.device) && (
        <p className="text-xs text-muted-foreground">
          {row.ip ? `IP ${String(row.ip)}` : ''}
          {row.device ? ` · ${String(row.device)}` : ''}
        </p>
      )}
    </div>
  )
}
