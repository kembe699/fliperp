export interface User {
  id: number
  company_id: number | null
  branch_id: number | null
  name: string
  email: string
  phone: string | null
  is_active: boolean
  is_platform_staff: boolean
  last_login_at: string | null
  roles: string[]
  permissions: string[]
  created_at: string
  updated_at: string
}

export type CompanyStatus = 'active' | 'suspended' | 'pending'

export interface Company {
  id: number
  name: string
  slug: string
  client_code: string
  logo_url: string | null
  currency_code: string | null
  timezone: string | null
  is_active: boolean
  status: CompanyStatus
  is_platform: boolean
  billing_customer_id: number | null
  onboarded_by: number | null
  suspended_at: string | null
  activated_at: string | null
  created_at: string
  updated_at: string
}

export interface AuthPayload {
  token: string | null
  user: User
  company: Company | null
  roles: string[]
  permissions: string[]
}

export interface Currency {
  id: number
  code: string
  name: string
  symbol: string
}
