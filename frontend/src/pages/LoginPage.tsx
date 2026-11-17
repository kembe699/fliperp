import { useEffect, useState, type KeyboardEvent, type ReactNode } from 'react'
import { useNavigate } from 'react-router-dom'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import axios from 'axios'
import { AlertCircle, Building2, Eye, EyeOff, Loader2, Lock, Mail } from 'lucide-react'

import { login } from '@/api/auth'
import { useAuthStore } from '@/lib/auth-store'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { BrandLogo } from '@/components/brand/BrandLogo'

const loginSchema = z.object({
  clientCode: z.string().min(1, 'Enter your client code'),
  email: z.string().min(1, 'Enter your email').email('Enter a valid email address'),
  password: z.string().min(1, 'Enter your password'),
})

type LoginFormValues = z.infer<typeof loginSchema>

/** The client code is the same every time for a given office, so it is remembered on this device. */
const CLIENT_CODE_KEY = 'fliperp:last-client-code'

function rememberedClientCode(): string {
  try {
    return localStorage.getItem(CLIENT_CODE_KEY) ?? ''
  } catch {
    return ''
  }
}

// The backend can't tell the frontend "suspended" vs. "pending" vs. "wrong
// credentials" as a structured code today — login failures all land on the
// same client_code validation error, distinguished only by message text
// (see AuthService::login). Matching on these substrings is what decides
// whether to show the extra "you can't self-serve a ticket, contact us
// directly" block below.
function isAccountBlockedMessage(message: string): boolean {
  return message.includes('suspended') || message.includes('not yet active')
}

/** An input with an icon on the left (and optionally a control on the right). */
function IconField({ icon, children, trailing }: { icon: ReactNode; children: ReactNode; trailing?: ReactNode }) {
  return (
    <div className="relative">
      <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground">{icon}</span>
      {children}
      {trailing && <span className="absolute right-1 top-1/2 -translate-y-1/2">{trailing}</span>}
    </div>
  )
}

const fieldClass = 'h-11 border-0 bg-[#EEF3FB] pl-10 focus-visible:ring-2 focus-visible:ring-primary/40'

export function LoginPage() {
  const navigate = useNavigate()
  const setAuth = useAuthStore((state) => state.setAuth)
  const [serverError, setServerError] = useState<string | null>(null)
  const [showPassword, setShowPassword] = useState(false)
  const [capsLock, setCapsLock] = useState(false)
  const [showResetHelp, setShowResetHelp] = useState(false)
  const savedCode = rememberedClientCode()

  const {
    register,
    handleSubmit,
    setFocus,
    formState: { errors, isSubmitting },
  } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
    defaultValues: { clientCode: savedCode, email: '', password: '' },
  })

  // Start where there is something to type: the email when the code is remembered.
  useEffect(() => {
    setFocus(savedCode ? 'email' : 'clientCode')
  }, [savedCode, setFocus])

  const onSubmit = async (values: LoginFormValues) => {
    setServerError(null)
    try {
      const payload = await login(values.clientCode, values.email, values.password)
      try {
        localStorage.setItem(CLIENT_CODE_KEY, values.clientCode)
      } catch {
        // Private browsing or storage switched off: nothing to remember, sign-in still works.
      }
      setAuth(payload)
      navigate('/dashboard', { replace: true })
    } catch (error) {
      if (axios.isAxiosError(error)) {
        const fieldMessage = error.response?.data?.errors?.client_code?.[0]
        if (!error.response) {
          setServerError('Cannot reach FlipERP. Check your internet connection and try again.')
        } else if (error.response.status === 429) {
          setServerError('Too many sign-in attempts. Please wait a minute and try again.')
        } else {
          setServerError(fieldMessage ?? error.response?.data?.message ?? 'Unable to sign in. Please check your details.')
        }
      } else {
        setServerError('Unable to sign in. Please try again.')
      }
    }
  }

  const checkCapsLock = (event: KeyboardEvent<HTMLInputElement>) => setCapsLock(event.getModifierState('CapsLock'))
  const accountBlocked = serverError ? isAccountBlockedMessage(serverError) : false
  const errorProps = (name: keyof LoginFormValues) => ({
    'aria-invalid': errors[name] ? true : undefined,
    'aria-describedby': errors[name] ? `${name}-error` : undefined,
  })
  const fieldError = (name: keyof LoginFormValues) =>
    errors[name] && (
      <p id={`${name}-error`} className="text-xs text-destructive">
        {errors[name]?.message}
      </p>
    )

  return (
    <div
      className="relative flex min-h-screen items-center justify-center bg-cover bg-center p-4"
      style={{ backgroundImage: "url('/bg.jpg')" }}
    >
      <div className="absolute inset-0 bg-hero-gradient opacity-30" />
      <div className="absolute inset-0 bg-[#0A2E52]/40" />

      <div className="relative w-full max-w-[420px] rounded-2xl bg-white p-8 shadow-xl">
        <div className="mb-6 flex flex-col items-center text-center">
          <BrandLogo variant="stacked" className="h-20" />
          <h1 className="mt-5 text-xl font-semibold text-foreground">Sign in to your workspace</h1>
          <p className="mt-1 text-sm text-muted-foreground">Use the client code and email your administrator gave you.</p>
        </div>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4" noValidate>
          <div className="space-y-1.5">
            <Label htmlFor="clientCode" required>Client code</Label>
            {/*
              The `uppercase` class is text-transform — it changes only how the
              value looks, never what is submitted. The field therefore displayed
              NHC-WMRYI while sending nhc-wmryi, and the lookup found nothing, so
              a correctly-typed code was reported as wrong credentials. The input
              event now normalises the value itself, and autoCapitalize/autoCorrect
              stop a phone keyboard editing it on the way in.
            */}
            <IconField icon={<Building2 className="h-4 w-4" />}>
              <Input
                id="clientCode"
                type="text"
                autoComplete="organization"
                autoCapitalize="characters"
                autoCorrect="off"
                spellCheck={false}
                placeholder="e.g. NHC-XXXXX"
                className={cn(fieldClass, 'uppercase placeholder:normal-case')}
                {...errorProps('clientCode')}
                {...register('clientCode', {
                  setValueAs: (v: string) => (v ?? '').trim().toUpperCase(),
                })}
                onInput={(e) => {
                  const el = e.currentTarget
                  el.value = el.value.toUpperCase()
                }}
              />
            </IconField>
            {fieldError('clientCode')}
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="email" required>Email</Label>
            {/*
              autoCapitalize is what matters on a phone: without it the keyboard
              capitalises the first letter, turning admin@x.com into Admin@x.com,
              which no longer matches and reads back as a wrong password.
            */}
            <IconField icon={<Mail className="h-4 w-4" />}>
              <Input
                id="email"
                type="email"
                inputMode="email"
                autoComplete="username"
                autoCapitalize="none"
                autoCorrect="off"
                spellCheck={false}
                placeholder="you@company.com"
                className={fieldClass}
                {...errorProps('email')}
                {...register('email', {
                  setValueAs: (v: string) => (v ?? '').trim(),
                })}
              />
            </IconField>
            {fieldError('email')}
          </div>

          <div className="space-y-1.5">
            <div className="flex items-center justify-between">
              <Label htmlFor="password" required>Password</Label>
              <button
                type="button"
                className="text-xs font-medium text-primary hover:underline"
                aria-expanded={showResetHelp}
                aria-controls="reset-help"
                onClick={() => setShowResetHelp((open) => !open)}
              >
                Forgot your password?
              </button>
            </div>
            <IconField
              icon={<Lock className="h-4 w-4" />}
              trailing={
                <button
                  type="button"
                  className="flex h-9 w-9 items-center justify-center rounded-md text-muted-foreground hover:text-foreground"
                  aria-label={showPassword ? 'Hide password' : 'Show password'}
                  aria-pressed={showPassword}
                  onClick={() => setShowPassword((shown) => !shown)}
                >
                  {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                </button>
              }
            >
              <Input
                id="password"
                type={showPassword ? 'text' : 'password'}
                autoComplete="current-password"
                placeholder="Your password"
                className={cn(fieldClass, 'pr-11')}
                onKeyUp={checkCapsLock}
                onKeyDown={checkCapsLock}
                {...errorProps('password')}
                {...register('password')}
              />
            </IconField>
            {fieldError('password')}
            {capsLock && <p className="text-xs text-warning">Caps Lock is on.</p>}
            {showResetHelp && (
              <p id="reset-help" className="rounded-lg bg-[#EEF3FB] px-3 py-2 text-xs text-muted-foreground">
                Ask your company administrator to set a new password for you (Settings › Users), or email{' '}
                <a href="mailto:support@nilehc.tech" className="font-medium text-primary underline underline-offset-2">
                  support@nilehc.tech
                </a>
                .
              </p>
            )}
          </div>

          {serverError && (
            <div role="alert" className="flex gap-2 rounded-lg bg-danger-bg px-3 py-2 text-sm text-danger">
              <AlertCircle className="mt-0.5 h-4 w-4 shrink-0" />
              <div>
                <p>{serverError}</p>
                {accountBlocked && (
                  <p className="mt-1.5 flex items-center gap-1.5 text-xs">
                    <Mail className="h-3.5 w-3.5 shrink-0" />
                    Need help? Contact{' '}
                    <a href="mailto:support@nilehc.tech" className="font-medium underline underline-offset-2">
                      support@nilehc.tech
                    </a>
                  </p>
                )}
              </div>
            </div>
          )}

          <Button type="submit" className="h-11 w-full text-base" disabled={isSubmitting}>
            {isSubmitting && <Loader2 className="h-4 w-4 animate-spin" />}
            {isSubmitting ? 'Signing in…' : 'Sign in'}
          </Button>
        </form>

        <p className="mt-5 text-center text-sm text-muted-foreground">
          Employee?{' '}
          <a href="/employee-portal/login" className="font-medium text-primary hover:underline">
            Use the Employee Portal
          </a>
        </p>

        <div className="mt-6 flex flex-col items-center gap-1 border-t border-border pt-4 text-center text-xs text-muted-foreground">
          <span>
            New to FlipERP?{' '}
            <a href="mailto:support@nilehc.tech" className="font-medium text-primary hover:underline">
              Contact us
            </a>
          </span>
          <span>By signing in you accept our terms.</span>
        </div>
      </div>
    </div>
  )
}
