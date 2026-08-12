import { useSearchParams } from 'react-router-dom'
import { Ban, Clock, Mail } from 'lucide-react'

import { Button } from '@/components/ui/button'

/**
 * Landed on when the api.ts response interceptor catches a 403 with
 * reason=company_suspended|company_pending — i.e. an ALREADY-logged-in user
 * whose company's status changed mid-session (see EnsureCompanyActive
 * middleware). LoginPage handles the same two states for someone who hasn't
 * logged in yet; this is the "you were kicked out just now" equivalent.
 */
export function AccountBlockedPage() {
  const [searchParams] = useSearchParams()
  const reason = searchParams.get('reason')
  const isSuspended = reason === 'company_suspended'

  return (
    <div className="relative flex min-h-screen items-center justify-center bg-cover bg-center p-4" style={{ backgroundImage: "url('/bg.jpg')" }}>
      <div className="absolute inset-0 bg-[#0A2E52]/60" />

      <div className="relative w-full max-w-md rounded-2xl bg-white p-8 text-center shadow-xl">
        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-danger-bg">
          {isSuspended ? <Ban className="h-7 w-7 text-danger" /> : <Clock className="h-7 w-7 text-danger" />}
        </div>

        <h1 className="mt-4 text-lg font-semibold text-foreground">
          {isSuspended ? 'Your account has been suspended' : 'Your account is not yet active'}
        </h1>
        <p className="mt-2 text-sm text-muted-foreground">
          {isSuspended
            ? 'Access to your workspace has been paused. Please contact support to resolve this.'
            : 'Your workspace is still being activated. Please contact support if this is taking longer than expected.'}
        </p>

        <a
          href="mailto:support@nilehc.tech"
          className="mt-6 flex items-center justify-center gap-2 rounded-lg bg-[#EEF3FB] px-4 py-2.5 text-sm font-medium text-primary"
        >
          <Mail className="h-4 w-4" />
          support@nilehc.tech
        </a>

        <Button variant="outline" className="mt-4 w-full" onClick={() => (window.location.href = '/login')}>
          Back to Login
        </Button>
      </div>
    </div>
  )
}
