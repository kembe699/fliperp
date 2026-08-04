export interface ChatMessageAttachment {
  id: number
  file_name: string
  file_size: number
  mime_type: string
  download_path: string
}

export interface ChatMessage {
  id: number
  conversation_id: number
  sender_id: number
  sender_name: string
  body: string | null
  attachments: ChatMessageAttachment[]
  created_at: string
}

export interface ChatConversation {
  id: number
  other_user: { id: number; name: string } | null
  last_message: { body: string | null; sender_id: number; created_at: string } | null
  unread_count: number
  updated_at: string
}

export interface PresenceMember {
  id: number
  name: string
  avatar: string | null
}
