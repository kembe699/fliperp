import { useState } from 'react'
import { useNavigate } from 'react-router-dom'
import { zodResolver } from '@hookform/resolvers/zod'
import { useForm } from 'react-hook-form'
import { z } from 'zod'
import axios from 'axios'
import { Loader2 } from 'lucide-react'

import { portalLogin } from '@/api/employee-portal'
import { usePortalAuthStore } from '@/lib/portal-auth-store'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

const loginSchema = z.object({
  email: z.string().min(1, 'Email is required').email('Enter a valid email address'),
  password: z.string().min(1, 'Password is required'),
})

type LoginFormValues = z.infer<typeof loginSchema>

export function PortalLoginPage() {
  const navigate = useNavigate()
  const setAuth = usePortalAuthStore((state) => state.setAuth)
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
      const payload = await portalLogin(values.email, values.password)
      setAuth(payload)
      navigate('/employee-portal', { replace: true })
    } catch (error) {
      if (axios.isAxiosError(error)) {
        setServerError(error.response?.data?.message ?? 'Unable to log in. Please check your credentials.')
      } else {
        setServerError('Unable to log in. Please try again.')
      }
    }
  }

  return (
    <div className="relative flex min-h-screen items-center justify-center bg-[#F5F7FA] p-4">
      <div className="w-full max-w-[400px] rounded-2xl bg-white p-8 shadow-xl">
        <div className="mb-6 flex flex-col items-center">
          <img src="/logo.png" alt="FlipErp" className="h-12 w-auto" />
          <p className="mt-2 text-sm font-medium text-muted-foreground">Employee Portal</p>
          <div className="mt-4 h-1 w-16 rounded-full bg-gradient-to-r from-primary to-primary-hover" />
        </div>

        <form onSubmit={handleSubmit(onSubmit)} className="space-y-4">
          <div className="space-y-1.5">
            <Label htmlFor="email">Email</Label>
            <Input
              id="email"
              type="text"
              autoComplete="username"
              placeholder="you@company.com"
              className="border-0 bg-[#EEF3FB] text-base"
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
              className="border-0 bg-[#EEF3FB] text-base"
              {...register('password')}
            />
            {errors.password && <p className="text-xs text-destructive">{errors.password.message}</p>}
          </div>

          {serverError && <p className="rounded-lg bg-danger-bg px-3 py-2 text-sm text-danger">{serverError}</p>}

          <Button type="submit" className="h-12 w-full text-base" disabled={isSubmitting}>
            {isSubmitting && <Loader2 className="h-4 w-4 animate-spin" />}
            Log In
          </Button>
        </form>

        <p className="mt-6 text-center text-xs text-muted-foreground">
          Not an employee?{' '}
          <a href="/login" className="font-medium text-primary hover:underline">
            Go to the main login
          </a>
        </p>
      </div>
    </div>
  )
}
