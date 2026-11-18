import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Bar, BarChart, CartesianGrid, Legend, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'

import { fetchMarketingReport } from '@/api/crm-marketing'
import { localISODate } from '@/lib/format'

import { PageHeader } from '@/components/layout/PageHeader'
import { StatCard } from '@/components/shared/StatCard'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { FlipLoaderBlock } from '@/components/brand/FlipLoader'

const daysAgo = (n: number) => localISODate(new Date(Date.now() - n * 86_400_000))

/** CRM > Analytics: how marketing is doing — emails, campaigns, lead magnets, bookings and lead sources. */
export function CrmAnalyticsPage() {
  const [range, setRange] = useState('30')
  const [from, setFrom] = useState(daysAgo(29))
  const [to, setTo] = useState(localISODate(new Date()))

  const { data, isLoading, isError } = useQuery({
    queryKey: ['crm-marketing-report', from, to],
    queryFn: () => fetchMarketingReport({ from, to }),
    staleTime: 0,
  })

  const pickRange = (value: string) => {
    setRange(value)
    if (value !== 'custom') {
      setFrom(daysAgo(Number(value) - 1))
      setTo(localISODate(new Date()))
    }
  }

  return (
    <div>
      <PageHeader
        parent="CRM"
        title="Analytics"
        action={
          <div className="flex flex-wrap items-center gap-2">
            <Select value={range} onValueChange={pickRange}>
              <SelectTrigger className="h-9 w-40">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="7">Last 7 days</SelectItem>
                <SelectItem value="30">Last 30 days</SelectItem>
                <SelectItem value="90">Last 90 days</SelectItem>
                <SelectItem value="365">Last 12 months</SelectItem>
                <SelectItem value="custom">Custom dates</SelectItem>
              </SelectContent>
            </Select>
            {range === 'custom' && (
              <>
                <Input type="date" className="h-9 w-[9.5rem]" value={from} max={to} onChange={(e) => setFrom(e.target.value)} aria-label="From" />
                <Input type="date" className="h-9 w-[9.5rem]" value={to} min={from} onChange={(e) => setTo(e.target.value)} aria-label="To" />
              </>
            )}
          </div>
        }
      />

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load analytics. Please try again.</p>
      ) : isLoading || !data ? (
        <FlipLoaderBlock label="Loading analytics…" />
      ) : (
        <div className="space-y-6">
          <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
            <StatCard label="New leads" value={data.leads.new} subtext={`${data.leads.converted} converted`} />
            <StatCard label="Lead magnet sign-ups" value={data.lead_magnets.submissions} />
            <StatCard label="Meetings booked" value={data.bookings.total} subtext={`${data.bookings.upcoming} upcoming`} />
            <StatCard label="Emails to clients" value={data.emails.sent} subtext={data.emails.failed ? `${data.emails.failed} failed` : 'none failed'} />
            <StatCard label="Campaign emails" value={data.campaigns.sent} subtext={`${data.campaigns.campaigns} campaign${data.campaigns.campaigns === 1 ? '' : 's'}`} />
            <StatCard label="Open / click rate" value={`${data.campaigns.open_rate}%`} subtext={`${data.campaigns.click_rate}% clicked · ${data.campaigns.unsubscribed} unsubscribed`} />
          </div>

          <section className="rounded-xl border border-border bg-card p-4">
            <h2 className="mb-3 text-sm font-semibold">New leads, sign-ups and bookings per day</h2>
            <div className="h-64">
              <ResponsiveContainer width="100%" height="100%">
                <BarChart data={data.daily} margin={{ top: 4, right: 8, left: -20, bottom: 0 }}>
                  <CartesianGrid strokeDasharray="3 3" vertical={false} stroke="hsl(var(--border))" />
                  <XAxis
                    dataKey="date"
                    tickFormatter={(d: string) => new Date(`${d}T00:00:00`).toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}
                    tick={{ fontSize: 11 }}
                    minTickGap={16}
                  />
                  <YAxis allowDecimals={false} tick={{ fontSize: 11 }} />
                  <Tooltip labelFormatter={(d) => new Date(`${d}T00:00:00`).toLocaleDateString(undefined, { weekday: 'short', month: 'short', day: 'numeric' })} />
                  <Legend wrapperStyle={{ fontSize: 12 }} />
                  <Bar dataKey="leads" name="New leads" stackId="a" fill="hsl(213 73% 40%)" />
                  <Bar dataKey="signups" name="Lead magnet sign-ups" stackId="a" fill="hsl(173 58% 39%)" />
                  <Bar dataKey="bookings" name="Bookings" stackId="a" fill="hsl(35 92% 50%)" radius={[3, 3, 0, 0]} />
                </BarChart>
              </ResponsiveContainer>
            </div>
          </section>

          <div className="grid gap-6 lg:grid-cols-2">
            <Panel title="Where leads come from">
              {data.leads.by_source.length === 0 ? (
                <Empty>No new leads in this period.</Empty>
              ) : (
                <div className="space-y-2">
                  {data.leads.by_source.map((s) => {
                    const max = data.leads.by_source[0].total || 1
                    return (
                      <div key={s.source}>
                        <div className="flex justify-between text-sm">
                          <span className="truncate">{s.source}</span>
                          <span className="font-medium">{s.total}</span>
                        </div>
                        <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-muted">
                          <div className="h-full rounded-full bg-primary" style={{ width: `${(s.total / max) * 100}%` }} />
                        </div>
                      </div>
                    )
                  })}
                </div>
              )}
            </Panel>

            <Panel title="Campaigns" link={{ to: '/crm/campaigns', label: 'All campaigns' }}>
              {data.campaigns.recent.length === 0 ? (
                <Empty>No campaigns sent in this period.</Empty>
              ) : (
                <Table head={['Campaign', 'Sent', 'Opened', 'Clicked']}>
                  {data.campaigns.recent.map((c) => (
                    <tr key={c.id} className="border-t border-border">
                      <td className="py-2 pr-2"><Link to={`/crm/campaigns/${c.id}`} className="text-primary hover:underline">{c.name}</Link></td>
                      <td className="py-2 pr-2 text-right">{c.sent}</td>
                      <td className="py-2 pr-2 text-right">{c.open_rate}%</td>
                      <td className="py-2 text-right">{c.click_rate}%</td>
                    </tr>
                  ))}
                </Table>
              )}
            </Panel>

            <Panel title="Lead magnets" link={{ to: '/crm/lead-magnets', label: 'Manage' }}>
              {data.lead_magnets.items.length === 0 ? (
                <Empty>No lead magnets yet.</Empty>
              ) : (
                <Table head={['Lead magnet', 'Views', 'Sign-ups', 'Conversion']}>
                  {data.lead_magnets.items.map((m) => (
                    <tr key={m.id} className="border-t border-border">
                      <td className="py-2 pr-2">{m.title}</td>
                      <td className="py-2 pr-2 text-right">{m.views}</td>
                      <td className="py-2 pr-2 text-right">{m.submissions}</td>
                      <td className="py-2 text-right">{m.conversion_rate}%</td>
                    </tr>
                  ))}
                </Table>
              )}
            </Panel>

            <Panel title="Bookings by page" link={{ to: '/crm/calendar', label: 'Calendar' }}>
              {data.bookings.pages.length === 0 ? (
                <Empty>No booking pages yet.</Empty>
              ) : (
                <Table head={['Booking page', 'Booked in period']}>
                  {data.bookings.pages.map((p) => (
                    <tr key={p.id} className="border-t border-border">
                      <td className="py-2 pr-2">{p.title}</td>
                      <td className="py-2 text-right">{p.bookings}</td>
                    </tr>
                  ))}
                </Table>
              )}
            </Panel>
          </div>
        </div>
      )}
    </div>
  )
}

function Panel({ title, link, children }: { title: string; link?: { to: string; label: string }; children: React.ReactNode }) {
  return (
    <section className="rounded-xl border border-border bg-card p-4">
      <div className="mb-3 flex items-center justify-between">
        <h2 className="text-sm font-semibold">{title}</h2>
        {link && <Link to={link.to} className="text-xs text-primary hover:underline">{link.label}</Link>}
      </div>
      {children}
    </section>
  )
}

function Empty({ children }: { children: React.ReactNode }) {
  return <p className="py-6 text-center text-sm text-muted-foreground">{children}</p>
}

function Table({ head, children }: { head: string[]; children: React.ReactNode }) {
  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-[320px] text-sm">
        <thead>
          <tr className="text-xs uppercase text-muted-foreground">
            {head.map((h, i) => (
              <th key={h} className={i === 0 ? 'pb-2 text-left font-medium' : 'pb-2 text-right font-medium'}>{h}</th>
            ))}
          </tr>
        </thead>
        <tbody>{children}</tbody>
      </table>
    </div>
  )
}
