import type { ReactNode } from 'react'
import { Link } from 'react-router-dom'
import { ArrowLeft } from 'lucide-react'

import { BrandLogo } from '@/components/brand/BrandLogo'

/** Public page (no sign-in needed): the FlipERP Terms of Service, linked from the login page. */

const LAST_UPDATED = '17 November 2026'
const SUPPORT_EMAIL = 'support@nilehc.tech'

const SECTIONS: { title: string; body: ReactNode }[] = [
  {
    title: 'About these terms',
    body: (
      <>
        <p>
          FlipERP is a business management system provided by Nile Hive Concept Co. Ltd (“Nile Hive”, “we”, “us”). These terms apply to every
          organisation that uses FlipERP (the “client”) and to every person who signs in on a client’s behalf (a “user”).
        </p>
        <p>
          By signing in you accept these terms. If you sign in on behalf of an organisation, you confirm that you are allowed to do so. Where a
          client has a signed agreement, quotation or purchase order with Nile Hive, that document takes priority over these terms where the two differ.
        </p>
      </>
    ),
  },
  {
    title: 'Accounts and access',
    body: (
      <ul>
        <li>Each client receives a client code. The client’s administrators create users and decide what each user can see and do.</li>
        <li>Keep your password private and do not share your account. You are responsible for activity carried out with your sign-in.</li>
        <li>Tell your administrator, or us at {SUPPORT_EMAIL}, straight away if you think someone else has used your account.</li>
        <li>Administrators should remove or deactivate users who no longer need access, such as staff who have left.</li>
      </ul>
    ),
  },
  {
    title: 'Your data',
    body: (
      <ul>
        <li>The client owns the data it enters into FlipERP, including financial records, documents, staff and supplier details.</li>
        <li>We use that data only to provide, secure, back up and support the service, and as the law requires. We do not sell it.</li>
        <li>Each client’s data is kept separate from other clients’ data. Only Nile Hive staff who need it to support the client can access it.</li>
        <li>The client is responsible for having the right to enter personal information about its staff, suppliers and customers into FlipERP.</li>
        <li>On request, we will help the client export its data, including when the service ends.</li>
      </ul>
    ),
  },
  {
    title: 'Acceptable use',
    body: (
      <>
        <p>You must not:</p>
        <ul>
          <li>use FlipERP for anything unlawful or fraudulent, or to store content you have no right to hold;</li>
          <li>try to access another client’s data, another user’s account, or parts of the system you have not been given;</li>
          <li>attempt to break, overload, scan or reverse-engineer the service, or get round its security or permissions;</li>
          <li>upload viruses or harmful files.</li>
        </ul>
      </>
    ),
  },
  {
    title: 'Subscriptions and payment',
    body: (
      <ul>
        <li>Fees, modules and the number of users are set out in the client’s quotation, invoice or agreement.</li>
        <li>Invoices are due by the date shown on them. If payment is overdue, we may suspend access after giving the client notice.</li>
        <li>Suspension does not delete data. Access is restored once the account is brought up to date.</li>
      </ul>
    ),
  },
  {
    title: 'Availability and support',
    body: (
      <ul>
        <li>We aim to keep FlipERP available at all times, but we cannot promise it will never be interrupted. We may need short planned maintenance windows and will try to schedule them outside working hours.</li>
        <li>FlipERP needs an internet connection to work.</li>
        <li>Support is available at {SUPPORT_EMAIL} and through Contact Support inside FlipERP.</li>
        <li>We back up client data regularly and before every update to the system.</li>
      </ul>
    ),
  },
  {
    title: 'The client’s responsibilities',
    body: (
      <p>
        FlipERP is a tool. The client remains responsible for its own accounts, tax filings, payroll, donor reporting and compliance, and for
        checking figures and documents before relying on them or sending them to others. Approval rules, user permissions and exchange rates set
        up in FlipERP are the client’s decisions.
      </p>
    ),
  },
  {
    title: 'Confidentiality and security',
    body: (
      <p>
        We protect client data with access controls, separation between clients, audit logs and regular backups, and we keep client information
        confidential. Users must also keep confidential any information they see in FlipERP that they are not entitled to share.
      </p>
    ),
  },
  {
    title: 'Intellectual property',
    body: (
      <p>
        FlipERP, its software, design and the FlipERP name and logo belong to Nile Hive. Clients receive the right to use FlipERP for their own
        organisation while their subscription is active. This does not transfer ownership of the software. Client logos and documents remain the
        client’s.
      </p>
    ),
  },
  {
    title: 'Suspension and ending the service',
    body: (
      <ul>
        <li>We may suspend an account that breaks these terms, puts the service or other clients at risk, or is overdue for payment.</li>
        <li>A client may stop using FlipERP as set out in its agreement.</li>
        <li>When the service ends, the client can ask for an export of its data within 30 days. After that, we may delete it, except where the law requires us to keep it.</li>
      </ul>
    ),
  },
  {
    title: 'Limitation of liability',
    body: (
      <p>
        We provide FlipERP with reasonable care and skill. As far as the law allows, we are not liable for indirect or consequential losses, such
        as lost profits or lost business, and our total liability to a client in any 12-month period is limited to the fees that client paid for
        FlipERP in that period. Nothing in these terms limits liability that cannot be limited by law.
      </p>
    ),
  },
  {
    title: 'Changes to these terms',
    body: (
      <p>
        We may update these terms from time to time. The date at the top of this page shows when they last changed, and we will tell clients about
        important changes. Continuing to use FlipERP after a change means accepting the updated terms.
      </p>
    ),
  },
  {
    title: 'Governing law',
    body: <p>These terms are governed by the laws of the Republic of South Sudan.</p>,
  },
  {
    title: 'Contact',
    body: (
      <p>
        Questions about these terms: <a href={`mailto:${SUPPORT_EMAIL}`}>{SUPPORT_EMAIL}</a>.
      </p>
    ),
  },
]

export function TermsPage() {
  return (
    <div className="min-h-screen bg-[#F5F7FA]">
      <header className="border-b border-border bg-white">
        <div className="mx-auto flex max-w-3xl items-center justify-between px-4 py-4">
          <BrandLogo className="h-8" />
          <Link to="/login" className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline">
            <ArrowLeft className="h-4 w-4" />
            Back to sign in
          </Link>
        </div>
      </header>

      <main className="mx-auto max-w-3xl px-4 py-10">
        <h1 className="text-3xl font-semibold text-foreground">Terms of Service</h1>
        <p className="mt-2 text-sm text-muted-foreground">Last updated {LAST_UPDATED}</p>

        <nav aria-label="Contents" className="mt-6 rounded-xl border border-border bg-white p-5">
          <p className="mb-2 text-sm font-semibold text-foreground">Contents</p>
          <ol className="grid list-decimal gap-1 pl-5 text-sm text-primary sm:grid-cols-2">
            {SECTIONS.map((section, index) => (
              <li key={section.title}>
                <a href={`#section-${index + 1}`} className="hover:underline">
                  {section.title}
                </a>
              </li>
            ))}
          </ol>
        </nav>

        <div className="mt-8 space-y-8 rounded-xl border border-border bg-white p-6 sm:p-8">
          {SECTIONS.map((section, index) => (
            <section key={section.title} id={`section-${index + 1}`} className="scroll-mt-6">
              <h2 className="mb-3 text-lg font-semibold text-foreground">
                {index + 1}. {section.title}
              </h2>
              <div className="space-y-3 text-sm leading-relaxed text-muted-foreground [&_a]:font-medium [&_a]:text-primary [&_a]:underline [&_li]:mt-1.5 [&_ul]:list-disc [&_ul]:pl-5">
                {section.body}
              </div>
            </section>
          ))}
        </div>

        <p className="mt-8 text-center text-xs text-muted-foreground">© {new Date().getFullYear()} Nile Hive Concept Co. Ltd. FlipERP is a product of Nile Hive.</p>
      </main>
    </div>
  )
}
