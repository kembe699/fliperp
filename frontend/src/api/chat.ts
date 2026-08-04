import { api } from '@/lib/api'
import type { ApiResponse } from '@/types/api'
import type { ChatConversation, ChatMessage } from '@/types/chat'

export async function startConversationWith(userId: number): Promise<ChatConversation> {
  const { data } = await api.post<ApiResponse<ChatConversation>>(`/chat/conversations/with/${userId}`)
  return data.data
}

export async function fetchConversations(): Promise<ChatConversation[]> {
  const { data } = await api.get<ApiResponse<ChatConversation[]>>('/chat/conversations')
  return data.data
}

export async function fetchMessages(conversationId: number, before?: number): Promise<ChatMessage[]> {
  const { data } = await api.get<ApiResponse<ChatMessage[]>>(`/chat/conversations/${conversationId}/messages`, {
    params: before ? { before } : undefined,
  })
  return data.data
}

export async function sendChatMessage(conversationId: number, body: string, attachments: File[]): Promise<ChatMessage> {
  const formData = new FormData()
  if (body) formData.append('body', body)
  attachments.forEach((file) => formData.append('attachments[]', file))

  const { data } = await api.post<ApiResponse<ChatMessage>>(`/chat/conversations/${conversationId}/messages`, formData, {
    headers: { 'Content-Type': 'multipart/form-data' },
  })
  return data.data
}

export async function markConversationRead(conversationId: number): Promise<void> {
  await api.post(`/chat/conversations/${conversationId}/read`)
}

export async function downloadChatAttachment(path: string, filename: string, mimeType: string): Promise<void> {
  const { data } = await api.get<Blob>(path, { responseType: 'blob' })

  const blob = new Blob([data], { type: mimeType })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}
