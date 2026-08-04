import { useEffect, useState } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useQuery, useQueryClient } from '@tanstack/react-query'
import { AlertTriangle, CheckCircle2, Loader2, MapPin, QrCode, ScanLine, XCircle } from 'lucide-react'

import { fetchPortalMe, portalCheckLocation, portalClockIn, portalClockOut } from '@/api/employee-portal'
import { getApiErrorInfo } from '@/lib/api-errors'
import { Button } from '@/components/ui/button'
import { QrScanCamera } from '@/components/portal/QrScanCamera'

type Phase = 'idle' | 'scanning' | 'locating' | 'location-denied' | 'checking' | 'in-range' | 'out-of-range' | 'submitting' | 'success' | 'error'

interface ClockContext {
  branch: string
  token: string
}

/** Accepts a full clock URL (from a printed/displayed QR), a scheme-less URL, or a bare "branch=..&token=.." query string. */
function parseClockContext(decodedText: string): ClockContext | null {
  // A URL missing "http(s)://" (e.g. a misconfigured backend FRONTEND_URL)
  // parses as relative rather than throwing, so `new URL()` alone can't be
  // trusted to catch it — try it as-is, then retry with a scheme prepended.
  for (const candidate of [decodedText, `https://${decodedText.replace(/^\/+/, '')}`]) {
    try {
      const url = new URL(candidate)
      const branch = url.searchParams.get('branch')
      const token = url.searchParams.get('token')
      if (branch && token) return { branch, token }
    } catch {
      // not a valid URL even with a scheme prepended — fall through
    }
  }

  const queryString = decodedText.includes('?') ? decodedText.slice(decodedText.indexOf('?') + 1) : decodedText
  const params = new URLSearchParams(queryString)
  const branch = params.get('branch')
  const token = params.get('token')
  return branch && token ? { branch, token } : null
}

export function PortalClockPage() {
  const [searchParams] = useSearchParams()
  const queryClient = useQueryClient()

  const { data: me } = useQuery({ queryKey: ['portal-me'], queryFn: fetchPortalMe })

  const [context, setContext] = useState<ClockContext | null>(() => {
    const branch = searchParams.get('branch')
    const token = searchParams.get('token')
    return branch && token ? { branch, token } : null
  })

  const [phase, setPhase] = useState<Phase>(context ? 'locating' : 'idle')
  const [message, setMessage] = useState<string | null>(null)
  const [coords, setCoords] = useState<{ latitude: number; longitude: number } | null>(null)

  const hasClockedIn = !!me?.today_attendance?.clock_in
  const hasClockedOut = !!me?.today_attendance?.clock_out
  const action = !hasClockedIn ? 'in' : !hasClockedOut ? 'out' : null

  const handleScan = (decodedText: string) => {
    const parsed = parseClockContext(decodedText)
    if (!parsed) {
      setPhase('error')
      setMessage(
        `That QR code doesn't look like an attendance code. Please scan the one posted at your office. (Scanned: "${decodedText.slice(0, 120)}")`,
      )
      return
    }
    setContext(parsed)
    setPhase('locating')
  }

  useEffect(() => {
    if (!context) return
    if (!navigator.geolocation) {
      setPhase('location-denied')
      setMessage('Your browser does not support location access, which is required to clock in/out.')
      return
    }

    setPhase('locating')
    navigator.geolocation.getCurrentPosition(
      (position) => {
        setCoords({ latitude: position.coords.latitude, longitude: position.coords.longitude })
      },
      (error) => {
        setPhase('location-denied')
        setMessage(
          error.code === error.PERMISSION_DENIED
            ? 'Location access was denied. Please enable location permissions for this site in your browser settings and try again.'
            : 'Could not determine your location. Please try again.',
        )
      },
      { enableHighAccuracy: true, timeout: 15000 },
    )
  }, [context])

  useEffect(() => {
    if (!coords || !context) return

    let cancelled = false
    setPhase('checking')

    portalCheckLocation({ branch_id: Number(context.branch), token: context.token, latitude: coords.latitude, longitude: coords.longitude })
      .then((result) => {
        if (cancelled) return
        if (result.within_range) {
          setPhase('in-range')
          setMessage(null)
        } else {
          setPhase('out-of-range')
          setMessage(result.reason ?? "You're too far from the office.")
        }
      })
      .catch((error) => {
        if (cancelled) return
        setPhase('error')
        setMessage(getApiErrorInfo(error).message)
      })

    return () => {
      cancelled = true
    }
  }, [coords, context])

  const handleConfirm = async () => {
    if (!coords || !context || !action) return
    setPhase('submitting')
    try {
      const payload = { branch_id: Number(context.branch), token: context.token, latitude: coords.latitude, longitude: coords.longitude }
      if (action === 'in') {
        await portalClockIn(payload)
      } else {
        await portalClockOut(payload)
      }
      setPhase('success')
      queryClient.invalidateQueries({ queryKey: ['portal-me'] })
    } catch (error) {
      setPhase('error')
      setMessage(getApiErrorInfo(error).message)
    }
  }

  const reset = () => {
    setContext(null)
    setCoords(null)
    setMessage(null)
    setPhase('idle')
  }

  if (phase === 'idle') {
    return (
      <div className="flex flex-col items-center gap-4 py-16 text-center">
        <QrCode className="h-16 w-16 text-muted-foreground" />
        <div>
          <p className="text-base font-semibold text-foreground">Scan to Clock In or Out</p>
          <p className="mt-1 text-sm text-muted-foreground">
            Scan the QR code posted at your office to clock in or out.
          </p>
        </div>
        <Button size="lg" className="h-14 w-full max-w-xs text-base" onClick={() => setPhase('scanning')}>
          <ScanLine className="h-5 w-5" />
          Open Camera to Scan
        </Button>
      </div>
    )
  }

  if (phase === 'scanning') {
    return (
      <div className="py-6">
        <QrScanCamera onScan={handleScan} onCancel={reset} />
      </div>
    )
  }

  return (
    <div className="flex flex-col items-center gap-6 py-10 text-center">
      {phase === 'locating' && (
        <>
          <Loader2 className="h-14 w-14 animate-spin text-primary" />
          <p className="text-sm text-muted-foreground">Getting your location…</p>
        </>
      )}

      {phase === 'location-denied' && (
        <>
          <AlertTriangle className="h-14 w-14 text-warning" />
          <p className="text-sm text-foreground">{message}</p>
          <Button variant="outline" onClick={reset}>
            Try Again
          </Button>
        </>
      )}

      {phase === 'checking' && (
        <>
          <Loader2 className="h-14 w-14 animate-spin text-primary" />
          <p className="text-sm text-muted-foreground">Checking your location…</p>
        </>
      )}

      {phase === 'in-range' && action && (
        <>
          <MapPin className="h-14 w-14 text-success" />
          <p className="text-sm font-medium text-foreground">You're at the office — tap to clock {action}.</p>
          <Button size="lg" className="h-14 w-full max-w-xs text-base" onClick={handleConfirm}>
            Clock {action === 'in' ? 'In' : 'Out'}
          </Button>
        </>
      )}

      {phase === 'in-range' && !action && (
        <>
          <CheckCircle2 className="h-14 w-14 text-success" />
          <p className="text-sm text-foreground">You've already clocked in and out today.</p>
        </>
      )}

      {phase === 'out-of-range' && (
        <>
          <XCircle className="h-14 w-14 text-destructive" />
          <p className="text-sm font-medium text-foreground">You're too far from the office.</p>
          {message && <p className="text-xs text-muted-foreground">{message}</p>}
          <Button variant="outline" onClick={reset}>
            Try Again
          </Button>
        </>
      )}

      {phase === 'submitting' && (
        <>
          <Loader2 className="h-14 w-14 animate-spin text-primary" />
          <p className="text-sm text-muted-foreground">Submitting…</p>
        </>
      )}

      {phase === 'success' && (
        <>
          <CheckCircle2 className="h-14 w-14 text-success" />
          <p className="text-sm font-medium text-foreground">Clocked {action === 'in' ? 'in' : 'out'} successfully!</p>
        </>
      )}

      {phase === 'error' && (
        <>
          <XCircle className="h-14 w-14 text-destructive" />
          <p className="text-sm text-foreground">{message ?? 'Something went wrong. Please try again.'}</p>
          <Button variant="outline" onClick={reset}>
            Try Again
          </Button>
        </>
      )}
    </div>
  )
}
