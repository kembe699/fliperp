import { useState, type ReactNode } from 'react'
import { useParams } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'
import { ArrowRight, Building2, CheckCircle2, Download, FileText, Gift, Link2, Lock, Mail, MailCheck, Phone, Sparkles, User, Zap } from 'lucide-react'

import { fetchPublicLeadMagnet, submitPublicLeadMagnet, type PublicLeadMagnet } from '@/api/crm-marketing'
import { getApiErrorInfo } from '@/lib/api-errors'
import { cn } from '@/lib/utils'

import { FlipLoaderBlock } from '@/components/brand/FlipLoader'

/**
 * /lm/:slug — the public lead magnet landing page: a branded hero selling the
 * download, a form card with cover art, and a download screen after sign-up.
 * Content comes from CRM > Lead Magnets, so the layout has to look good with
 * as little as a title.
 */
export function PublicLeadMagnetPage() {
  const { slug = '' } = useParams<{ slug: string }>()
  const { data: magnet, isLoading, isError } = useQuery({
    queryKey: ['public-lead-magnet', slug],
    queryFn: () => fetchPublicLeadMagnet(slug),
    retry: false,
    staleTime: Infinity,
  })

  if (isLoading) return <FlipLoaderBlock label="Loading…" />
  if (isError || !magnet) {
    return (
      <div className="flex min-h-dvh items-center justify-center bg-muted p-4">
        <div className="max-w-sm rounded-2xl border border-border bg-card p-8 text-center shadow-sm">
          <p className="text-lg font-semibold">This page is no longer available</p>
          <p className="mt-2 text-sm text-muted-foreground">The offer may have ended. Please contact the business directly.</p>
        </div>
      </div>
    )
  }

  return <LeadMagnetLanding magnet={magnet} slug={slug} />
}

function LeadMagnetLanding({ magnet, slug }: { magnet: PublicLeadMagnet; slug: string }) {
  const [form, setForm] = useState({ name: '', email: '', phone: '', company_name: '' })
  const submit = useMutation({ mutationFn: () => submitPublicLeadMagnet(slug, form) })
  const set = (key: keyof typeof form, value: string) => setForm((f) => ({ ...f, [key]: value }))

  const heading = magnet.headline || magnet.title
  const lines = (magnet.description ?? '').split('\n').map((l) => l.replace(/^[\s•*\-–]+/, '').trim()).filter(Boolean)
  const asChecklist = lines.length >= 2
  const companyName = magnet.company.name ?? ''

  return (
    <div className="flex min-h-dvh flex-col bg-background">
      <section className="relative flex flex-1 flex-col overflow-hidden bg-[linear-gradient(135deg,hsl(212_73%_39%)_0%,hsl(214_75%_26%)_55%,hsl(222_60%_16%)_100%)] text-white">
        {/* Decoration */}
        <div aria-hidden className="pointer-events-none absolute inset-0 opacity-[0.12] [background-image:radial-gradient(white_1px,transparent_1px)] [background-size:22px_22px]" />
        <div aria-hidden className="pointer-events-none absolute -right-24 -top-24 h-80 w-80 rounded-full bg-sky-400/30 blur-3xl" />
        <div aria-hidden className="pointer-events-none absolute -bottom-32 -left-20 h-96 w-96 rounded-full bg-indigo-500/25 blur-3xl" />

        <div className="relative mx-auto flex w-full max-w-6xl flex-1 flex-col px-4 sm:px-6">
          <header className="flex items-center gap-3 py-5">
            {magnet.company.logo_url ? (
              <img src={magnet.company.logo_url} alt="" className="h-9 w-9 rounded-lg bg-white object-contain p-1" />
            ) : (
              <span className="flex h-9 w-9 items-center justify-center rounded-lg bg-white/15 text-sm font-bold ring-1 ring-white/25">
                {companyName.slice(0, 1).toUpperCase() || '★'}
              </span>
            )}
            <span className="font-semibold tracking-tight">{companyName}</span>
          </header>

          <div className="grid flex-1 items-center gap-10 pb-10 pt-4 sm:pb-14 lg:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)] lg:gap-14 lg:pt-8">
            {/* Pitch */}
            <div className="animate-in fade-in slide-in-from-bottom-4 duration-700">
              <span className="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold uppercase tracking-wider ring-1 ring-white/25">
                <Sparkles className="h-3.5 w-3.5 text-amber-300" />
                Free download
              </span>
              <h1 className="mt-5 text-balance text-3xl font-bold leading-tight sm:text-4xl lg:text-5xl">{capitalise(heading)}</h1>
              {magnet.headline && <p className="mt-3 text-lg font-medium text-sky-100">{capitalise(magnet.title)}</p>}

              {lines.length > 0 &&
                (asChecklist ? (
                  <ul className="mt-6 space-y-3">
                    {lines.map((line) => (
                      <li key={line} className="flex gap-3 text-base text-white/90">
                        <CheckCircle2 className="mt-0.5 h-5 w-5 shrink-0 text-emerald-300" />
                        <span>{line}</span>
                      </li>
                    ))}
                  </ul>
                ) : (
                  <p className="mt-5 max-w-xl text-base leading-relaxed text-white/85 sm:text-lg">{capitalise(lines[0])}</p>
                ))}

              <div className="mt-8 flex flex-wrap gap-x-6 gap-y-3 text-sm text-white/85">
                <Perk icon={<Gift className="h-4 w-4" />}>100% free</Perk>
                <Perk icon={<Zap className="h-4 w-4" />}>Instant access</Perk>
                <Perk icon={<MailCheck className="h-4 w-4" />}>Also sent to your inbox</Perk>
              </div>
            </div>

            {/* Form card */}
            <div className="relative mt-16 animate-in fade-in slide-in-from-bottom-6 duration-700 lg:mt-0 lg:pl-6">
              <Cover title={magnet.title} fileType={magnet.file_type} />
              <div className="relative rounded-2xl bg-card p-6 pt-20 text-foreground shadow-2xl shadow-black/30 ring-1 ring-black/5 sm:p-8 sm:pt-20">
                {submit.isSuccess ? (
                  <div className="pt-6 text-center animate-in fade-in zoom-in-95 duration-500">
                    <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50 ring-8 ring-emerald-50/60">
                      <CheckCircle2 className="h-9 w-9 text-emerald-600" />
                    </div>
                    <h2 className="mt-5 text-2xl font-bold">It's yours!</h2>
                    <p className="mt-2 whitespace-pre-wrap text-sm text-muted-foreground">{submit.data.thank_you_message}</p>
                    <a
                      href={submit.data.download_url}
                      className="mt-6 inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-6 text-base font-semibold text-primary-foreground shadow-lg shadow-primary/30 transition hover:bg-primary/90"
                    >
                      <Download className="h-5 w-5" />
                      {magnet.file_type === 'LINK' ? 'Open now' : 'Download now'}
                    </a>
                    {!/email/i.test(submit.data.thank_you_message) && (
                      <p className="mt-4 text-xs text-muted-foreground">
                        We've also emailed the link to <span className="font-medium text-foreground">{form.email}</span>.
                      </p>
                    )}
                  </div>
                ) : (
                  <>
                    <h2 className="text-xl font-bold">Get your free copy</h2>
                    <p className="mt-1 text-sm text-muted-foreground">Tell us where to send it — it takes 10 seconds.</p>
                    <form
                      className="mt-5 grid gap-3"
                      onSubmit={(e) => {
                        e.preventDefault()
                        if (!submit.isPending) submit.mutate()
                      }}
                    >
                      <Field icon={<User className="h-4 w-4" />} label="Your name">
                        <input required autoComplete="name" placeholder="Your name" value={form.name} onChange={(e) => set('name', e.target.value)} className={inputClass} />
                      </Field>
                      <Field icon={<Mail className="h-4 w-4" />} label="Email address">
                        <input required type="email" autoComplete="email" placeholder="you@example.com" value={form.email} onChange={(e) => set('email', e.target.value)} className={inputClass} />
                      </Field>
                      {magnet.ask_phone && (
                        <Field icon={<Phone className="h-4 w-4" />} label="Phone number">
                          <input required type="tel" autoComplete="tel" placeholder="Phone number" value={form.phone} onChange={(e) => set('phone', e.target.value)} className={inputClass} />
                        </Field>
                      )}
                      {magnet.ask_company && (
                        <Field icon={<Building2 className="h-4 w-4" />} label="Company">
                          <input autoComplete="organization" placeholder="Company (optional)" value={form.company_name} onChange={(e) => set('company_name', e.target.value)} className={inputClass} />
                        </Field>
                      )}
                      {submit.isError && <p className="rounded-lg bg-danger-bg px-3 py-2 text-sm text-danger">{getApiErrorInfo(submit.error).message}</p>}
                      <button
                        type="submit"
                        disabled={submit.isPending}
                        className="group mt-1 inline-flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-6 text-base font-semibold text-primary-foreground shadow-lg shadow-primary/30 transition hover:bg-primary/90 disabled:opacity-70"
                      >
                        {submit.isPending ? 'Sending…' : capitalise(magnet.button_label)}
                        {!submit.isPending && <ArrowRight className="h-5 w-5 transition-transform group-hover:translate-x-1" />}
                      </button>
                      <p className="flex items-center justify-center gap-1.5 text-center text-xs text-muted-foreground">
                        <Lock className="h-3 w-3" />
                        Your details are safe with us. Unsubscribe any time.
                      </p>
                    </form>
                  </>
                )}
              </div>
            </div>
          </div>
        </div>
        <footer className="relative py-6 text-center text-xs text-white/60">
          {companyName && <>© {new Date().getFullYear()} {companyName} · </>}Powered by FlipERP
        </footer>
      </section>
    </div>
  )
}

const inputClass =
  'h-12 w-full rounded-xl border border-input bg-background pl-10 pr-3 text-base text-foreground outline-none transition placeholder:text-muted-foreground/70 focus:border-primary focus:ring-4 focus:ring-primary/15'

function Field({ icon, label, children }: { icon: ReactNode; label: string; children: ReactNode }) {
  return (
    <label className="relative block">
      <span className="sr-only">{label}</span>
      <span className="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted-foreground">{icon}</span>
      {children}
    </label>
  )
}

function Perk({ icon, children }: { icon: ReactNode; children: ReactNode }) {
  return (
    <span className="inline-flex items-center gap-2">
      <span className="flex h-7 w-7 items-center justify-center rounded-full bg-white/15 text-amber-300">{icon}</span>
      {children}
    </span>
  )
}

/** A small document "cover" that sits on top of the form card. */
function Cover({ title, fileType }: { title: string; fileType: string }) {
  const isLink = fileType === 'LINK'
  return (
    <div className="absolute left-1/2 top-0 z-10 -translate-x-1/2 -translate-y-1/2 lg:left-[calc(50%+0.75rem)]">
      <div className="relative w-28 rotate-[-6deg] sm:w-32">
        <div className="absolute inset-0 translate-x-2 translate-y-2 rotate-[8deg] rounded-lg bg-white/40" />
        <div className="relative aspect-[3/4] overflow-hidden rounded-lg bg-white shadow-xl ring-1 ring-black/5">
          <div className="h-1/3 bg-[linear-gradient(135deg,hsl(212_73%_45%),hsl(222_60%_25%))] p-2">
            {isLink ? <Link2 className="h-5 w-5 text-white/90" /> : <FileText className="h-5 w-5 text-white/90" />}
          </div>
          <div className="space-y-1.5 p-2">
            <p className="line-clamp-3 text-[10px] font-bold leading-tight text-foreground">{capitalise(title)}</p>
            <div className="h-1 w-full rounded bg-muted" />
            <div className="h-1 w-4/5 rounded bg-muted" />
            <div className="h-1 w-3/5 rounded bg-muted" />
          </div>
          <span className={cn('absolute bottom-1.5 right-1.5 rounded px-1.5 py-0.5 text-[9px] font-bold text-white', isLink ? 'bg-indigo-500' : 'bg-rose-500')}>
            {fileType}
          </span>
        </div>
      </div>
    </div>
  )
}

const capitalise = (text: string) => (text ? text.charAt(0).toUpperCase() + text.slice(1) : text)
