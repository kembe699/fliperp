import { MessageCircle } from 'lucide-react'

import { formatRelativeTime } from '@/lib/format'
import type { ChatConversation, PresenceMember } from '@/types/chat'

import { Avatar, AvatarFallback } from '@/components/ui/avatar'

function initials(name: string): string {
  const parts = name.trim().split(/\s+/)
  const first = parts[0]?.[0] ?? ''
  const last = parts.length > 1 ? (parts[parts.length - 1]?.[0] ?? '') : ''
  return (first + last).toUpperCase()
}

interface ChatHomeViewProps {
  onlineMembers: PresenceMember[]
  currentUserId: number | undefined
  conversations: ChatConversation[]
  onSelectOnlineUser: (userId: number) => void
  onSelectConversation: (conversation: ChatConversation) => void
}

export function ChatHomeView({ onlineMembers, currentUserId, conversations, onSelectOnlineUser, onSelectConversation }: ChatHomeViewProps) {
  const others = onlineMembers.filter((member) => member.id !== currentUserId)
  const onlineIds = new Set(others.map((member) => member.id))

  return (
    <div className="flex-1 overflow-y-auto">
      <div className="px-3 pt-3">
        <p className="mb-2 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Online now</p>
        {others.length === 0 ? (
          <p className="pb-3 text-xs text-muted-foreground">No one else is online right now.</p>
        ) : (
          <div className="flex gap-3 overflow-x-auto pb-3">
            {others.map((member) => (
              <button key={member.id} type="button" onClick={() => onSelectOnlineUser(member.id)} className="flex w-14 shrink-0 flex-col items-center gap-1">
                <div className="relative">
                  <Avatar className="h-10 w-10">
                    <AvatarFallback className="text-xs">{initials(member.name)}</AvatarFallback>
                  </Avatar>
                  <span className="absolute -right-0.5 -bottom-0.5 h-3 w-3 rounded-full border-2 border-card bg-success" />
                </div>
                <span className="w-full truncate text-center text-[11px] text-foreground">{member.name.split(' ')[0]}</span>
              </button>
            ))}
          </div>
        )}
      </div>

      <div className="border-t border-border px-3 pt-3">
        <p className="mb-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Recent conversations</p>
        {conversations.length === 0 ? (
          <div className="flex flex-col items-center gap-2 py-8 text-center">
            <MessageCircle className="h-8 w-8 text-muted-foreground" />
            <p className="text-xs text-muted-foreground">Start a conversation with someone online.</p>
          </div>
        ) : (
          <div className="pb-2">
            {conversations.map((conversation) => {
              const isOnline = conversation.other_user ? onlineIds.has(conversation.other_user.id) : false
              return (
                <button
                  key={conversation.id}
                  type="button"
                  onClick={() => onSelectConversation(conversation)}
                  className="flex w-full items-center gap-3 rounded-lg px-1.5 py-2 text-left hover:bg-muted/60"
                >
                  <div className="relative shrink-0">
                    <Avatar className="h-9 w-9">
                      <AvatarFallback className="text-xs">{initials(conversation.other_user?.name ?? '?')}</AvatarFallback>
                    </Avatar>
                    {isOnline && <span className="absolute -right-0.5 -bottom-0.5 h-2.5 w-2.5 rounded-full border-2 border-card bg-success" />}
                  </div>
                  <div className="min-w-0 flex-1">
                    <div className="flex items-center justify-between gap-2">
                      <p className="truncate text-sm font-medium text-foreground">{conversation.other_user?.name ?? 'Unknown user'}</p>
                      <span className="shrink-0 text-[11px] text-muted-foreground">{formatRelativeTime(conversation.updated_at)}</span>
                    </div>
                    <p className="truncate text-xs text-muted-foreground">{conversation.last_message?.body ?? 'No messages yet'}</p>
                  </div>
                  {conversation.unread_count > 0 && (
                    <span className="flex h-5 min-w-5 shrink-0 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-primary-foreground">
                      {conversation.unread_count}
                    </span>
                  )}
                </button>
              )
            })}
          </div>
        )}
      </div>
    </div>
  )
}
