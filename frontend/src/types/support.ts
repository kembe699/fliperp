export type TicketPriority = 'low' | 'medium' | 'high' | 'urgent'
export type TicketStatus = 'open' | 'in_progress' | 'resolved' | 'closed'
export type TicketSource = 'contact_support_widget' | 'manual' | 'email'

export interface TicketReply {
  id: number
  ticket_id: number
  author_id: number
  author_name: string | null
  body: string
  is_internal_note: boolean
  created_at: string
}

export interface SupportTicket {
  id: number
  company_id: number
  company_name: string | null
  raised_by_user_id: number | null
  raised_by_name: string | null
  subject: string
  description: string
  priority: TicketPriority
  status: TicketStatus
  assigned_to: number | null
  assignee_name: string | null
  source: TicketSource
  replies: TicketReply[]
  created_at: string
  updated_at: string
}
