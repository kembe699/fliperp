export interface User {
  id: number
  company_id: number | null
  branch_id: number | null
  name: string
  email: string
  phone: string | null
  is_active: boolean
  last_login_at: string | null
  roles: string[]
  permissions: string[]
  created_at: string
  updated_at: string
}

export interface Company {
  id: number
  name: string
  slug: string
  logo_url: string | null
  currency_code: string | null
  timezone: string | null
  is_active: boolean
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
