import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import axios from 'axios'
import { Loader2, Mail } from 'lucide-react'

import { login } from '@/api/auth'
import { useAuthStore } from '@/lib/auth-store'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const loginSchema = z.object({
  clientCode: z.string().min(1, 'Client code is required'),
  email: z.string().min(1, 'Email is required').email('Enter a valid email address'),
  password: z.string().min(1, 'Password is required'),
})

type LoginFormValues = z.infer<typeof loginSchema>

// The backend can't tell the frontend "suspended" vs. "pending" vs. "wrong
// credentials" as a structured code today — login failures all land on the
// same client_code validation error, distinguished only by message text
// (see AuthService::login). Matching on these substrings is what decides
// whether to show the extra "you can't self-serve a ticket, contact us
// directly" block below.
function isAccountBlockedMessage(message: string): boolean {
  return message.includes('suspended') || message.includes('not yet active')
}

export function LoginPage() {
  const navigate = useNavigate()
  const setAuth = useAuthStore((state) => state.setAuth)
  const [serverError, setServerError] = useState<string | null>(null)

  const {
    register,
    handleSubmit,
    formState: { errors, isSubmitting },
  } = useForm<LoginFormValues>({
    resolver: zodResolver(loginSchema),
  })

  const onSubmit = async (values: LoginFormValues) => {
    setServerError(null)
    try {
      const payload = await login(values.clientCode, values.email, values.password)
      setAuth(payload)
      navigate('/dashboard', { replace: true })
    } catch (error) {
      if (axios.isAxiosError(error)) {
        const fieldMessage = error.response?.data?.errors?.client_code?.[0]
        setServerError(fieldMessage ?? error.response?.data?.message ?? 'Unable to log in. Please check your credentials.')
      } else {
        setServerError('Unable to log in. Please try again.')
      }
    }
  }

  const accountBlocked = serverError ? isAccountBlockedMessage(serverError) : false

  return (
    <div
      className="relative flex min-h-screen items-center justify-center bg-cover bg-center p-4"
      style={{ backgroundImage: "url('/bg.jpg')" }}
    >
      <div className="absolute inset-0 bg-hero-gradient opacity-30" />
      <div className="absolute inset-0 bg-[#0A2E52]/40" />

      <div className="relative w-full max-w-[440px] rounded-2xl bg-white p-8 shadow-xl">
        <div className="mb-6 flex flex-col items-center">
          <img src="/logo.png" alt="FlipErp" className="h-14 w-auto" />
          <div className="mt-4 h-1 w-16 rounded-full bg-gradient-to-r from-primary to-primary-hover" />
        </div>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="clientCode">Client Code</Label>
            {/*
              The `uppercase` class is text-transform — it changes only how the
              value looks, never what is submitted. The field therefore displayed
              NHC-WMRYI while sending nhc-wmryi, and the lookup found nothing, so
              a correctly-typed code was reported as wrong credentials. The input
              event now normalises the value itself, and autoCapitalize/autoCorrect
              stop a phone keyboard editing it on the way in.
            */}
            <Input
              id="clientCode"
              type="text"
              autoComplete="organization"
              autoCapitalize="characters"
              autoCorrect="off"
              spellCheck={false}
              placeholder="NHC-XXXXX"
              className="border-0 bg-[#EEF3FB] uppercase placeholder:normal-case"
              {...register('clientCode', {
                setValueAs: (v: string) => (v ?? '').trim().toUpperCase(),
              })}
              onInput={(e) => {
                const el = e.currentTarget
                el.value = el.value.toUpperCase()
              }}
            />
            {errors.clientCode && <p className="text-xs text-destructive">{errors.clientCode.message}</p>}
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="email">Email or Username</Label>
            {/*
              autoCapitalize is what matters on a phone: without it the keyboard
              capitalises the first letter, turning admin@x.com into Admin@x.com,
              which no longer matches and reads back as a wrong password.
            */}
            <Input
              id="email"
              type="text"
              autoComplete="username"
              autoCapitalize="none"
              autoCorrect="off"
              spellCheck={false}
              placeholder="you@company.com"
              className="border-0 bg-[#EEF3FB]"
              {...register('email', {
                setValueAs: (v: string) => (v ?? '').trim(),
              })}
            />
            {errors.email && <p className="text-xs text-destructive">{errors.email.message}</p>}
          </div>

          <div className="space-y-1.5">
            <Label htmlFor="password">Password</Label>
            <Input
              id="password"
              type="password"
              autoComplete="current-password"
              placeholder="••••••••"
              className="border-0 bg-[#EEF3FB]"
              {...register('password')}
            />
            {errors.password && <p className="text-xs text-destructive">{errors.password.message}</p>}
          </div>

          {serverError && (
            <div className="rounded-lg bg-danger-bg px-3 py-2 text-sm text-danger">
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
          )}

          <Button type="submit" className="w-full" disabled={isSubmitting}>
            {isSubmitting && <Loader2 className="h-4 w-4 animate-spin" />}
            Log In
          </Button>
        </form>

        <p className="mt-4 text-center text-sm">
          <a href="#" className="font-medium text-primary underline underline-offset-2">
            Forgot your password?
          </a>
        </p>

        <p className="mt-6 text-center text-xs text-muted-foreground">
          By logging in, you agree to our Terms of Service and Privacy Policy.
        </p>

        <p className="mt-3 text-center text-xs text-muted-foreground">
          Employee?{' '}
          <a href="/employee-portal/login" className="font-medium text-primary hover:underline">
            Access the Employee Portal
          </a>
        </p>

        <div className="mt-6 flex items-center justify-between border-t border-border pt-4 text-xs text-muted-foreground">
          <span>New here? Contact us</span>
          <a href="mailto:support@nilehc.tech" className="font-medium text-primary hover:underline">
            Help
          </a>
        </div>
      </div>
    </div>
  )
}
