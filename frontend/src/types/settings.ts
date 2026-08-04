export interface Company {
  id: number
  name: string
  slug: string
  logo_url: string | null
  currency_code: string | null
  timezone: string | null
  is_active: boolean
}

export interface SettingsUser {
  id: number
  company_id: number
  branch_id: number | null
  name: string
  email: string
  phone: string | null
  is_active: boolean
  last_login_at: string | null
  roles: string[]
  permissions: string[]
}

export interface Role {
  id: number
  name: string
  guard_name: string
  permissions: string[]
  users_count?: number
}

export interface Permission {
  id: number
  name: string
  guard_name: string
}
