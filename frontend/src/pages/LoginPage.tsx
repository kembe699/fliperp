import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import axios from 'axios'
import { Loader2 } from 'lucide-react'

import { login } from '@/api/auth'
import { useAuthStore } from '@/lib/auth-store'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const loginSchema = z.object({
  email: z.string().min(1, 'Email is required').email('Enter a valid email address'),
  password: z.string().min(1, 'Password is required'),
})

type LoginFormValues = z.infer<typeof loginSchema>

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
      const payload = await login(values.email, values.password)
      setAuth(payload)
      navigate('/dashboard', { replace: true })
    } catch (error) {
      if (axios.isAxiosError(error)) {
        setServerError(error.response?.data?.message ?? 'Unable to log in. Please check your credentials.')
      } else {
        setServerError('Unable to log in. Please try again.')
      }
    }
  }

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
            <Label htmlFor="email">Email or Username</Label>
            <Input
              id="email"
              type="text"
              autoComplete="username"
              placeholder="you@company.com"
              className="border-0 bg-[#EEF3FB]"
              {...register('email')}
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

          {serverError && <p className="rounded-lg bg-danger-bg px-3 py-2 text-sm text-danger">{serverError}</p>}

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
          <a href="#" className="font-medium text-primary hover:underline">
            Help
          </a>
        </div>
      </div>
    </div>
  )
}
