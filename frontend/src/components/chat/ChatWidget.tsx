import { useEffect, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { MessageCircle, X } from 'lucide-react'

import { fetchConversations, startConversationWith } from '@/api/chat'
import { useAuthStore } from '@/lib/auth-store'
import { getEcho } from '@/lib/echo'
import type { ChatConversation, PresenceMember } from '@/types/chat'

import { ChatHomeView } from '@/components/chat/ChatHomeView'
import { ChatConversationView } from '@/components/chat/ChatConversationView'

export function ChatWidget() {
  const userId = useAuthStore((state) => state.user?.id)
  const companyId = useAuthStore((state) => state.company?.id)
  const queryClient = useQueryClient()

  const [isOpen, setIsOpen] = useState(false)
  const [activeConversation, setActiveConversation] = useState<ChatConversation | null>(null)
  const [onlineMembers, setOnlineMembers] = useState<PresenceMember[]>([])

  const { data: conversations = [] } = useQuery({
    queryKey: ['chat-conversations'],
    queryFn: fetchConversations,
    enabled: !!userId,
    refetchInterval: 30_000,
  })

  const totalUnread = conversations.reduce((sum, conversation) => sum + conversation.unread_count, 0)

  // Presence roster for "who's online" — stays subscribed for the whole
  // session (not just while the widget is open) so it's already populated
  // the instant the launcher is clicked.
  useEffect(() => {
    if (!companyId) return
    const echo = getEcho()
    if (!echo) return

    const channel = echo.join(`company.${companyId}`)
    channel.here((members: PresenceMember[]) => setOnlineMembers(members))
    channel.joining((member: PresenceMember) => setOnlineMembers((prev) => [...prev.filter((m) => m.id !== member.id), member]))
    channel.leaving((member: PresenceMember) => setOnlineMembers((prev) => prev.filter((m) => m.id !== member.id)))

    return () => {
      echo.leave(`company.${companyId}`)
    }
  }, [companyId])

  // Badge/list bump for messages arriving in a conversation that isn't
  // currently open (see ChatService::sendMessage() — the server only
  // fires this at recipients who aren't actively viewing).
  useEffect(() => {
    if (!userId) return
    const echo = getEcho()
    if (!echo) return

    const channel = echo.private(`user.${userId}`)
    channel.listen('.chat.unread', () => {
      queryClient.invalidateQueries({ queryKey: ['chat-conversations'] })
    })

    return () => {
      channel.stopListening('.chat.unread')
    }
  }, [userId, queryClient])

  useEffect(() => {
    if (!isOpen) return
    const handleKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') setIsOpen(false)
    }
    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [isOpen])

  const startConversationMutation = useMutation({
    mutationFn: startConversationWith,
    onSuccess: (conversation) => {
      queryClient.invalidateQueries({ queryKey: ['chat-conversations'] })
      setActiveConversation(conversation)
    },
  })

  const handleSelectOnlineUser = (targetUserId: number) => {
    const existing = conversations.find((conversation) => conversation.other_user?.id === targetUserId)
    if (existing) {
      setActiveConversation(existing)
    } else {
      startConversationMutation.mutate(targetUserId)
    }
  }

  if (!userId) return null

  const isOtherUserOnline = activeConversation?.other_user
    ? onlineMembers.some((member) => member.id === activeConversation.other_user!.id)
    : false

  return (
    <>
      <button
        type="button"
        onClick={() => setIsOpen((prev) => !prev)}
        className="fixed bottom-5 right-5 z-40 flex h-14 w-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-lg transition-transform hover:scale-105"
        aria-label={isOpen ? 'Close chat' : 'Open chat'}
      >
        {isOpen ? <X className="h-6 w-6" /> : <MessageCircle className="h-6 w-6" />}
        {!isOpen && totalUnread > 0 && (
          <span className="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-semibold text-white">
            {totalUnread > 99 ? '99+' : totalUnread}
          </span>
        )}
      </button>

      {isOpen && (
        <div
          className="fixed bottom-24 right-5 z-40 flex h-[520px] w-96 max-w-[calc(100vw-2.5rem)] flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-2xl duration-200 animate-in fade-in slide-in-from-bottom-4 zoom-in-95"
        >
          {activeConversation ? (
            <ChatConversationView conversation={activeConversation} isOtherUserOnline={isOtherUserOnline} onBack={() => setActiveConversation(null)} />
          ) : (
            <>
              <div className="border-b border-border px-4 py-3">
                <p className="text-sm font-semibold text-foreground">Messages</p>
              </div>
              <ChatHomeView
                onlineMembers={onlineMembers}
                currentUserId={userId}
                conversations={conversations}
                onSelectOnlineUser={handleSelectOnlineUser}
                onSelectConversation={setActiveConversation}
              />
            </>
          )}
        </div>
      )}
    </>
  )
}
