import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'

// --- Booking pages ---

export interface CrmBookingPage {
  id: number
  slug: string
  title: string
  description: string | null
  duration_minutes: number
  buffer_minutes: number
  days: number[]
  start_time: string
  end_time: string
  timezone: string
  min_notice_hours: number
  max_days_ahead: number
  host_user_id: number
  host: { id: number; name: string } | null
  location: string | null
  meeting_link: string | null
  is_active: boolean
  bookings_count: number
  upcoming_count: number
  public_url: string
  created_at: string
}

export type CrmBookingPageInput = Omit<
  CrmBookingPage,
  'id' | 'host' | 'bookings_count' | 'upcoming_count' | 'public_url' | 'created_at' | 'slug'
> & { slug?: string | null }

export async function fetchBookingPages(): Promise<CrmBookingPage[]> {
  const { data } = await api.get<ApiResponse<CrmBookingPage[]>>('/crm/booking-pages')
  return data.data
}

export async function saveBookingPage(values: CrmBookingPageInput, id?: number): Promise<CrmBookingPage> {
  const { data } = id
    ? await api.put<ApiResponse<CrmBookingPage>>(`/crm/booking-pages/${id}`, values)
    : await api.post<ApiResponse<CrmBookingPage>>('/crm/booking-pages', values)
  return data.data
}

export async function deleteBookingPage(id: number): Promise<void> {
  await api.delete(`/crm/booking-pages/${id}`)
}

// --- Lead magnets ---

export interface CrmLeadMagnet {
  id: number
  slug: string
  title: string
  headline: string | null
  description: string | null
  file_name: string | null
  has_file: boolean
  external_url: string | null
  button_label: string
  thank_you_message: string | null
  ask_phone: boolean
  ask_company: boolean
  is_active: boolean
  views_count: number
  submissions_count: number
  downloads_count: number
  conversion_rate: number
  public_url: string
  created_at: string
}

export interface CrmLeadMagnetInput {
  title: string
  slug?: string
  headline?: string
  description?: string
  external_url?: string
  button_label?: string
  thank_you_message?: string
  ask_phone: boolean
  ask_company: boolean
  is_active: boolean
  file?: File | null
}

export interface CrmLeadMagnetSubmission {
  id: number
  lead_id: number | null
  name: string
  email: string
  phone: string | null
  company_name: string | null
  download_count: number
  downloaded_at: string | null
  created_at: string
}

export async function fetchLeadMagnets(): Promise<CrmLeadMagnet[]> {
  const { data } = await api.get<ApiResponse<CrmLeadMagnet[]>>('/crm/lead-magnets')
  return data.data
}

export async function saveLeadMagnet(values: CrmLeadMagnetInput, id?: number): Promise<CrmLeadMagnet> {
  const form = new FormData()
  Object.entries(values).forEach(([key, value]) => {
    if (value === undefined || value === null) return
    if (value instanceof File) form.append(key, value)
    else if (typeof value === 'boolean') form.append(key, value ? '1' : '0')
    else form.append(key, String(value))
  })
  const { data } = await api.post<ApiResponse<CrmLeadMagnet>>(id ? `/crm/lead-magnets/${id}` : '/crm/lead-magnets', form)
  return data.data
}

export async function deleteLeadMagnet(id: number): Promise<void> {
  await api.delete(`/crm/lead-magnets/${id}`)
}

export async function fetchLeadMagnetSubmissions(id: number): Promise<CrmLeadMagnetSubmission[]> {
  const { data } = await api.get<ApiResponse<CrmLeadMagnetSubmission[]>>(`/crm/lead-magnets/${id}/submissions`)
  return data.data
}

// --- Email campaigns ---

export type CrmCampaignStatus = 'draft' | 'scheduled' | 'sending' | 'sent' | 'cancelled'

export interface CrmCampaignAudience {
  type: 'leads' | 'customers' | 'all'
  lead_statuses?: string[]
  lead_sources?: string[]
  lead_magnet_id?: number | null
}

export interface CrmCampaignStats {
  total: number
  sent: number
  failed: number
  pending: number
  opened: number
  clicked: number
  unsubscribed: number
  open_rate: number
  click_rate: number
}

export interface CrmCampaign {
  id: number
  name: string
  subject: string
  body: string
  audience: CrmCampaignAudience
  status: CrmCampaignStatus
  recipients_count: number
  scheduled_at: string | null
  started_at: string | null
  finished_at: string | null
  created_by: { id: number; name: string } | null
  stats: CrmCampaignStats
  created_at: string
}

export interface CrmCampaignInput {
  name: string
  subject: string
  body: string
  audience: CrmCampaignAudience
}

export interface CrmCampaignRecipient {
  id: number
  email: string
  name: string | null
  lead_id: number | null
  customer_id: number | null
  status: 'pending' | 'sent' | 'failed'
  error_message: string | null
  open_count: number
  click_count: number
  sent_at: string | null
  opened_at: string | null
  clicked_at: string | null
  unsubscribed_at: string | null
}

export async function fetchCampaigns(): Promise<CrmCampaign[]> {
  const { data } = await api.get<ApiResponse<CrmCampaign[]>>('/crm/campaigns')
  return data.data
}

export async function fetchCampaign(id: number): Promise<CrmCampaign> {
  const { data } = await api.get<ApiResponse<CrmCampaign>>(`/crm/campaigns/${id}`)
  return data.data
}

export async function saveCampaign(values: CrmCampaignInput, id?: number): Promise<CrmCampaign> {
  const { data } = id
    ? await api.put<ApiResponse<CrmCampaign>>(`/crm/campaigns/${id}`, values)
    : await api.post<ApiResponse<CrmCampaign>>('/crm/campaigns', values)
  return data.data
}

export async function deleteCampaign(id: number): Promise<void> {
  await api.delete(`/crm/campaigns/${id}`)
}

export async function previewCampaignAudience(audience: CrmCampaignAudience): Promise<{ count: number; sample: { name: string | null; email: string }[] }> {
  const { data } = await api.post<ApiResponse<{ count: number; sample: { name: string | null; email: string }[] }>>(
    '/crm/campaigns/audience-preview',
    { audience },
  )
  return data.data
}

export async function fetchCampaignLeadSources(): Promise<string[]> {
  const { data } = await api.get<ApiResponse<string[]>>('/crm/campaigns/lead-sources')
  return data.data
}

export async function sendCampaignTest(id: number, email: string): Promise<string> {
  const { data } = await api.post<ApiResponse<null>>(`/crm/campaigns/${id}/test`, { email })
  return data.message
}

export async function scheduleCampaign(id: number, scheduledAt?: string | null): Promise<{ campaign: CrmCampaign; message: string }> {
  const { data } = await api.post<ApiResponse<CrmCampaign>>(`/crm/campaigns/${id}/schedule`, { scheduled_at: scheduledAt ?? null })
  return { campaign: data.data, message: data.message }
}

export async function cancelCampaign(id: number): Promise<CrmCampaign> {
  const { data } = await api.post<ApiResponse<CrmCampaign>>(`/crm/campaigns/${id}/cancel`)
  return data.data
}

export async function fetchCampaignRecipients(
  id: number,
  params: { page?: number; per_page?: number; filter?: string } = {},
): Promise<PaginatedResponse<CrmCampaignRecipient>> {
  const { data } = await api.get<PaginatedResponse<CrmCampaignRecipient>>(`/crm/campaigns/${id}/recipients`, { params })
  return data
}

// --- Analytics ---

export interface CrmMarketingReport {
  from: string
  to: string
  emails: { sent: number; failed: number; queued: number }
  campaigns: {
    campaigns: number
    sent: number
    opened: number
    clicked: number
    unsubscribed: number
    open_rate: number
    click_rate: number
    recent: { id: number; name: string; status: CrmCampaignStatus; started_at: string | null; sent: number; open_rate: number; click_rate: number; unsubscribed: number }[]
  }
  lead_magnets: {
    submissions: number
    items: { id: number; title: string; is_active: boolean; views: number; submissions: number; submissions_in_range: number; downloads: number; conversion_rate: number }[]
  }
  bookings: { total: number; upcoming: number; pages: { id: number; title: string; bookings: number }[] }
  leads: { new: number; converted: number; by_source: { source: string; total: number }[] }
  daily: { date: string; leads: number; signups: number; bookings: number }[]
}

export async function fetchMarketingReport(params: { from?: string; to?: string } = {}): Promise<CrmMarketingReport> {
  const { data } = await api.get<ApiResponse<CrmMarketingReport>>('/crm/reports/marketing', { params })
  return data.data
}

// --- Public (no login) ---

export interface PublicBookingPage {
  slug: string
  title: string
  description: string | null
  duration_minutes: number
  timezone: string
  days: number[]
  max_days_ahead: number
  location: string | null
  has_meeting_link: boolean
  host_name: string | null
  company: { name: string | null; logo_url: string | null }
}

export async function fetchPublicBookingPage(slug: string): Promise<PublicBookingPage> {
  const { data } = await api.get<ApiResponse<PublicBookingPage>>(`/public/booking/${slug}`)
  return data.data
}

export async function fetchPublicBookingDays(slug: string): Promise<string[]> {
  const { data } = await api.get<ApiResponse<{ days: string[] }>>(`/public/booking/${slug}/days`)
  return data.data.days
}

export async function fetchPublicBookingSlots(slug: string, date: string): Promise<string[]> {
  const { data } = await api.get<ApiResponse<{ slots: string[] }>>(`/public/booking/${slug}/slots`, { params: { date } })
  return data.data.slots
}

export interface PublicBookingInput {
  name: string
  email: string
  phone?: string
  company_name?: string
  notes?: string
  start: string
}

export async function bookPublicMeeting(slug: string, values: PublicBookingInput) {
  const { data } = await api.post<ApiResponse<{ scheduled_at: string; duration_minutes: number; timezone: string; location: string | null; meeting_link: string | null }>>(
    `/public/booking/${slug}`,
    values,
  )
  return data.data
}

export interface PublicLeadMagnet {
  slug: string
  title: string
  headline: string | null
  description: string | null
  button_label: string
  ask_phone: boolean
  ask_company: boolean
  company: { name: string | null; logo_url: string | null }
}

export async function fetchPublicLeadMagnet(slug: string): Promise<PublicLeadMagnet> {
  const { data } = await api.get<ApiResponse<PublicLeadMagnet>>(`/public/lead-magnets/${slug}`)
  return data.data
}

export async function submitPublicLeadMagnet(
  slug: string,
  values: { name: string; email: string; phone?: string; company_name?: string },
): Promise<{ thank_you_message: string; download_url: string }> {
  const { data } = await api.post<ApiResponse<{ thank_you_message: string; download_url: string }>>(`/public/lead-magnets/${slug}`, values)
  return data.data
}

export async function unsubscribePublic(token: string): Promise<{ email: string; company: string }> {
  const { data } = await api.post<ApiResponse<{ email: string; company: string }>>(`/public/email/unsubscribe/${token}`)
  return data.data
}
