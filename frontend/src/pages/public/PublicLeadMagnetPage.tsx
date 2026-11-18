import { useState, type ReactNode } from 'react'
import { useParams } from 'react-router-dom'
import { useMutation, useQuery } from '@tanstack/react-query'

import { fetchPublicLeadMagnet, submitPublicLeadMagnet, type PublicLeadMagnet } from '@/api/crm-marketing'
import { getApiErrorInfo } from '@/lib/api-errors'

import { FlipLoaderBlock } from '@/components/brand/FlipLoader'

/**
 * /lm/:slug — the public lead magnet page. Deliberately plain: the client's
 * own words, a short form, and the download. Content comes from CRM > Lead
 * Magnets, so it has to read well with as little as a title.
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
      <Page>
        <div className="mx-auto max-w-md py-24 text-center">
          <h1 className="text-xl font-semibold text-white">This page is no longer available</h1>
          <p className="mt-2 text-white/75">The offer may have ended. Please get in touch with the business directly.</p>
        </div>
      </Page>
    )
  }

  return <LeadMagnet magnet={magnet} slug={slug} />
}

function LeadMagnet({ magnet, slug }: { magnet: PublicLeadMagnet; slug: string }) {
  const [form, setForm] = useState({ name: '', email: '', phone: '', company_name: '' })
  const submit = useMutation({ mutationFn: () => submitPublicLeadMagnet(slug, form) })
  const set = (key: keyof typeof form, value: string) => setForm((f) => ({ ...f, [key]: value }))

  const lines = (magnet.description ?? '').split('\n').map((l) => l.replace(/^[\s•*\-–]+/, '').trim()).filter(Boolean)
  const firstName = form.name.trim().split(/\s+/)[0]
  const kind = magnet.file_type === 'LINK' ? 'Free resource' : `Free ${magnet.file_type} download`

  return (
    <Page company={magnet.company}>
      <div className="grid w-full gap-10 py-8 md:grid-cols-[minmax(0,1.15fr)_minmax(0,1fr)] md:gap-16 md:py-20">
        <div>
          <span className="inline-block rounded-full bg-white/10 px-3 py-1 text-sm font-medium text-sky-100 ring-1 ring-inset ring-white/20">{kind}</span>
          <h1 className="mt-5 text-3xl font-bold leading-tight tracking-tight text-white md:text-5xl">
            {capitalise(magnet.headline || magnet.title)}
          </h1>
          {magnet.headline && <p className="mt-3 text-lg text-white/80">{capitalise(magnet.title)}</p>}

          {lines.length > 1 ? (
            <div className="mt-10">
              <p className="text-xs font-semibold uppercase tracking-widest text-sky-200/80">What's inside</p>
              <ol className="mt-4 space-y-4">
                {lines.map((line, i) => (
                  <li key={line} className="flex items-start gap-4">
                    <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-white/30 text-sm font-semibold tabular-nums text-white">
                      {i + 1}
                    </span>
                    <span className="pt-1 text-lg text-white/90">{capitalise(line)}</span>
                  </li>
                ))}
              </ol>
            </div>
          ) : (
            lines[0] && <p className="mt-6 max-w-prose text-lg leading-relaxed text-white/85">{capitalise(lines[0])}</p>
          )}

          {magnet.signups_count >= 5 && (
            <p className="mt-10 border-t border-white/15 pt-5 text-sm text-white/70">
              Already downloaded by <span className="font-semibold text-white">{magnet.signups_count.toLocaleString()}</span> people.
            </p>
          )}
        </div>

        <div className="md:pt-2">
          <div className="overflow-hidden rounded-xl bg-white text-stone-900 shadow-2xl shadow-black/25">
            <div className="flex items-center justify-between gap-3 border-b border-stone-200 bg-stone-50 px-6 py-4 md:px-8">
              <div className="min-w-0">
                <p className="text-xs font-medium uppercase tracking-wider text-stone-500">You'll get</p>
                <p className="truncate font-semibold text-stone-900">{capitalise(magnet.title)}</p>
              </div>
              <span className="shrink-0 rounded bg-primary/10 px-2 py-1 text-xs font-bold text-primary">{magnet.file_type === 'LINK' ? 'LINK' : magnet.file_type}</span>
            </div>
            <div className="p-6 md:p-8">
            {submit.isSuccess ? (
              <div>
                <h2 className="text-xl font-semibold text-stone-900">Thanks{firstName ? `, ${firstName}` : ''}.</h2>
                <p className="mt-2 whitespace-pre-wrap text-stone-600">{submit.data.thank_you_message}</p>
                <a
                  href={submit.data.download_url}
                  className="mt-6 inline-flex h-11 w-full items-center justify-center rounded-md bg-primary px-5 font-medium text-primary-foreground transition-colors hover:bg-primary/90"
                >
                  {magnet.file_type === 'LINK' ? 'Open it' : 'Download'}
                </a>
                {!/email/i.test(submit.data.thank_you_message) && (
                  <p className="mt-4 text-sm text-stone-500">A copy is on its way to {form.email}.</p>
                )}
              </div>
            ) : (
              <form
                onSubmit={(e) => {
                  e.preventDefault()
                  if (!submit.isPending) submit.mutate()
                }}
              >
                <h2 className="text-lg font-semibold text-stone-900">Where should we send it?</h2>
                <p className="mt-1 text-sm text-stone-500">It's free. You'll get the download straight away.</p>
                <div className="mt-5 space-y-4">
                  <Field label="Name" id="lm-name">
                    <input id="lm-name" required autoComplete="name" value={form.name} onChange={(e) => set('name', e.target.value)} className={inputClass} />
                  </Field>
                  <Field label="Email" id="lm-email">
                    <input id="lm-email" required type="email" autoComplete="email" value={form.email} onChange={(e) => set('email', e.target.value)} className={inputClass} />
                  </Field>
                  {magnet.ask_phone && (
                    <Field label="Phone" id="lm-phone">
                      <input id="lm-phone" required type="tel" autoComplete="tel" value={form.phone} onChange={(e) => set('phone', e.target.value)} className={inputClass} />
                    </Field>
                  )}
                  {magnet.ask_company && (
                    <Field label="Company" hint="optional" id="lm-company">
                      <input id="lm-company" autoComplete="organization" value={form.company_name} onChange={(e) => set('company_name', e.target.value)} className={inputClass} />
                    </Field>
                  )}
                </div>
                {submit.isError && <p className="mt-4 text-sm text-red-700">{getApiErrorInfo(submit.error).message}</p>}
                <button
                  type="submit"
                  disabled={submit.isPending}
                  className="mt-6 h-11 w-full rounded-md bg-primary px-5 font-medium text-primary-foreground transition-colors hover:bg-primary/90 disabled:opacity-60"
                >
                  {submit.isPending ? 'Sending…' : capitalise(magnet.button_label)}
                </button>
                <p className="mt-3 text-sm text-stone-500">We'll email you a copy too. No spam, and you can unsubscribe any time.</p>
              </form>
            )}
            </div>
          </div>
        </div>
      </div>
    </Page>
  )
}

function Page({ company, children }: { company?: PublicLeadMagnet['company']; children: ReactNode }) {
  return (
    <div className="relative isolate flex min-h-dvh flex-col overflow-hidden bg-[linear-gradient(160deg,hsl(212_73%_36%)_0%,hsl(216_70%_24%)_100%)] text-white">
      {/* One quiet diagonal panel for depth */}
      <div aria-hidden className="pointer-events-none absolute -right-1/4 top-0 -z-10 h-full w-3/4 -skew-x-12 bg-white/[0.04]" />
      <header className="mx-auto flex h-16 w-full max-w-5xl items-center gap-3 border-b border-white/10 px-5">
        {company?.logo_url && <img src={company.logo_url} alt="" className="h-8 w-8 rounded bg-white object-contain p-0.5" />}
        <span className="font-semibold">{company?.name}</span>
      </header>
      <main className="mx-auto flex w-full max-w-5xl flex-1 items-center px-5">{children}</main>
      <footer className="py-8 text-center text-sm text-white/55">
        {company?.name && <>© {new Date().getFullYear()} {company.name} · </>}Powered by FlipERP
      </footer>
    </div>
  )
}

const inputClass =
  'mt-1.5 h-11 w-full rounded-md border border-stone-300 bg-white px-3 text-base text-stone-900 outline-none transition-colors focus:border-primary focus:ring-1 focus:ring-primary'

function Field({ label, hint, id, children }: { label: string; hint?: string; id: string; children: ReactNode }) {
  return (
    <div>
      <label htmlFor={id} className="text-sm font-medium text-stone-700">
        {label} {hint && <span className="font-normal text-stone-400">({hint})</span>}
      </label>
      {children}
    </div>
  )
}

const capitalise = (text: string) => (text ? text.charAt(0).toUpperCase() + text.slice(1) : text)
