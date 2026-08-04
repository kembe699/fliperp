import { useEffect, useRef, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import EmojiPicker, { type EmojiClickData } from 'emoji-picker-react'
import { ArrowLeft, File, Loader2, Paperclip, Send, SmilePlus, X } from 'lucide-react'

import { downloadChatAttachment, fetchMessages, markConversationRead, sendChatMessage } from '@/api/chat'
import { getEcho } from '@/lib/echo'
import { useAuthStore } from '@/lib/auth-store'
import { cn } from '@/lib/utils'
import type { ChatConversation, ChatMessage, ChatMessageAttachment } from '@/types/chat'

import { Avatar, AvatarFallback } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import { Textarea } from '@/components/ui/textarea'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'

function initials(name: string): string {
  const parts = name.trim().split(/\s+/)
  const first = parts[0]?.[0] ?? ''
  const last = parts.length > 1 ? (parts[parts.length - 1]?.[0] ?? '') : ''
  return (first + last).toUpperCase()
}

function formatFileSize(bytes: number): string {
  if (bytes < 1024) return `${bytes} B`
  if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`
  return `${(bytes / (1024 * 1024)).toFixed(1)} MB`
}

function AttachmentChip({ attachment }: { attachment: ChatMessageAttachment }) {
  const [downloading, setDownloading] = useState(false)

  const handleDownload = async () => {
    setDownloading(true)
    try {
      await downloadChatAttachment(attachment.download_path, attachment.file_name, attachment.mime_type)
    } finally {
      setDownloading(false)
    }
  }

  return (
    <div className="flex items-center gap-2 rounded-lg border border-border bg-background px-2.5 py-1.5">
      <File className="h-4 w-4 shrink-0 text-muted-foreground" />
      <div className="min-w-0 flex-1">
        <p className="truncate text-xs font-medium text-foreground">{attachment.file_name}</p>
        <p className="text-[11px] text-muted-foreground">{formatFileSize(attachment.file_size)}</p>
      </div>
      <button type="button" onClick={handleDownload} disabled={downloading} className="shrink-0 text-xs font-medium text-primary hover:underline">
        {downloading ? <Loader2 className="h-3.5 w-3.5 animate-spin" /> : 'Download'}
      </button>
    </div>
  )
}

interface ChatConversationViewProps {
  conversation: ChatConversation
  isOtherUserOnline: boolean
  onBack: () => void
}

export function ChatConversationView({ conversation, isOtherUserOnline, onBack }: ChatConversationViewProps) {
  const queryClient = useQueryClient()
  const currentUserId = useAuthStore((state) => state.user?.id)
  const [body, setBody] = useState('')
  const [pendingFiles, setPendingFiles] = useState<File[]>([])
  const [emojiOpen, setEmojiOpen] = useState(false)
  const textareaRef = useRef<HTMLTextAreaElement>(null)
  const fileInputRef = useRef<HTMLInputElement>(null)
  const scrollRef = useRef<HTMLDivElement>(null)
  const stickToBottomRef = useRef(true)

  const messagesQueryKey = ['chat-messages', conversation.id]

  const { data: messages = [] } = useQuery({
    queryKey: messagesQueryKey,
    queryFn: () => fetchMessages(conversation.id),
  })

  useEffect(() => {
    markConversationRead(conversation.id).then(() => {
      queryClient.invalidateQueries({ queryKey: ['chat-conversations'] })
    })
  }, [conversation.id, queryClient])

  // Scroll to bottom on first load and whenever a new message arrives,
  // unless the reader has scrolled up to read history — don't yank them
  // back down mid-read.
  useEffect(() => {
    if (stickToBottomRef.current) {
      scrollRef.current?.scrollTo({ top: scrollRef.current.scrollHeight })
    }
  }, [messages])

  useEffect(() => {
    const echo = getEcho()
    if (!echo) return

    const channel = echo.private(`conversation.${conversation.id}`)

    channel.listen('.message.sent', (message: ChatMessage) => {
      queryClient.setQueryData<ChatMessage[]>(messagesQueryKey, (old) => (old ? [...old, message] : [message]))
      queryClient.invalidateQueries({ queryKey: ['chat-conversations'] })
      // A message arriving while this thread is the one open counts as read.
      markConversationRead(conversation.id)
    })

    return () => {
      channel.stopListening('.message.sent')
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [conversation.id])

  const sendMutation = useMutation({
    mutationFn: () => sendChatMessage(conversation.id, body, pendingFiles),
    onSuccess: (message) => {
      queryClient.setQueryData<ChatMessage[]>(messagesQueryKey, (old) => (old ? [...old, message] : [message]))
      queryClient.invalidateQueries({ queryKey: ['chat-conversations'] })
      setBody('')
      setPendingFiles([])
    },
  })

  const handleScroll = () => {
    const el = scrollRef.current
    if (!el) return
    stickToBottomRef.current = el.scrollHeight - el.scrollTop - el.clientHeight < 80
  }

  const handleSend = () => {
    if (!isOtherUserOnline) return
    if (!body.trim() && pendingFiles.length === 0) return
    stickToBottomRef.current = true
    sendMutation.mutate()
  }

  const handleKeyDown = (event: React.KeyboardEvent<HTMLTextAreaElement>) => {
    if (event.key === 'Enter' && !event.shiftKey) {
      event.preventDefault()
      handleSend()
    }
  }

  const handleEmojiClick = (emojiData: EmojiClickData) => {
    const textarea = textareaRef.current
    if (!textarea) {
      setBody((prev) => prev + emojiData.emoji)
      return
    }
    const start = textarea.selectionStart ?? body.length
    const end = textarea.selectionEnd ?? body.length
    setBody(body.slice(0, start) + emojiData.emoji + body.slice(end))
    requestAnimationFrame(() => {
      textarea.focus()
      textarea.selectionStart = textarea.selectionEnd = start + emojiData.emoji.length
    })
  }

  const otherUserName = conversation.other_user?.name ?? 'Unknown user'

  return (
    <div className="flex h-full flex-col">
      <div className="flex items-center gap-2.5 border-b border-border px-3 py-2.5">
        <button type="button" onClick={onBack} className="rounded-full p-1 text-muted-foreground hover:bg-muted">
          <ArrowLeft className="h-4 w-4" />
        </button>
        <Avatar className="h-8 w-8">
          <AvatarFallback className="text-xs">{initials(otherUserName)}</AvatarFallback>
        </Avatar>
        <div className="min-w-0 flex-1">
          <p className="truncate text-sm font-semibold text-foreground">{otherUserName}</p>
          <p className={cn('text-xs', isOtherUserOnline ? 'text-success' : 'text-muted-foreground')}>{isOtherUserOnline ? 'Online' : 'Offline'}</p>
        </div>
      </div>

      <div ref={scrollRef} onScroll={handleScroll} className="flex-1 space-y-3 overflow-y-auto px-3 py-3">
        {messages.map((message) => {
          const isOwn = message.sender_id === currentUserId
          return (
            <div key={message.id} className={cn('flex flex-col', isOwn ? 'items-end' : 'items-start')}>
              <div
                className={cn(
                  'max-w-[80%] rounded-2xl px-3 py-2 text-sm',
                  isOwn ? 'bg-primary text-primary-foreground' : 'bg-muted text-foreground',
                )}
              >
                {message.body && <p className="whitespace-pre-wrap break-words">{message.body}</p>}
                {message.attachments.length > 0 && (
                  <div className={cn('space-y-1.5', message.body && 'mt-2')}>
                    {message.attachments.map((attachment) => (
                      <AttachmentChip key={attachment.id} attachment={attachment} />
                    ))}
                  </div>
                )}
              </div>
              <span className="mt-0.5 text-[10px] text-muted-foreground">
                {new Date(message.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}
              </span>
            </div>
          )
        })}
      </div>

      {!isOtherUserOnline && (
        <p className="border-t border-border bg-muted/40 px-3 py-2 text-center text-xs text-muted-foreground">
          {otherUserName} is offline — you can't send new messages until they're back online.
        </p>
      )}

      {pendingFiles.length > 0 && (
        <div className="flex flex-wrap gap-1.5 border-t border-border px-3 pt-2">
          {pendingFiles.map((file, index) => (
            <span key={`${file.name}-${index}`} className="flex items-center gap-1 rounded-full bg-muted px-2 py-1 text-xs text-foreground">
              {file.name}
              <button type="button" onClick={() => setPendingFiles((prev) => prev.filter((_, i) => i !== index))}>
                <X className="h-3 w-3" />
              </button>
            </span>
          ))}
        </div>
      )}

      <div className="flex items-end gap-1.5 border-t border-border p-2.5">
        <input
          ref={fileInputRef}
          type="file"
          multiple
          className="hidden"
          onChange={(event) => {
            setPendingFiles((prev) => [...prev, ...Array.from(event.target.files ?? [])])
            event.target.value = ''
          }}
        />
        <Button
          variant="ghost"
          size="icon"
          className="h-8 w-8 shrink-0"
          disabled={!isOtherUserOnline}
          onClick={() => fileInputRef.current?.click()}
        >
          <Paperclip className="h-4 w-4" />
        </Button>

        <Popover open={emojiOpen} onOpenChange={setEmojiOpen}>
          <PopoverTrigger asChild>
            <Button variant="ghost" size="icon" className="h-8 w-8 shrink-0" disabled={!isOtherUserOnline}>
              <SmilePlus className="h-4 w-4" />
            </Button>
          </PopoverTrigger>
          <PopoverContent side="top" align="start" className="w-auto border-none bg-transparent p-0 shadow-none">
            <EmojiPicker
              onEmojiClick={(emojiData) => {
                handleEmojiClick(emojiData)
                setEmojiOpen(false)
              }}
              width={300}
              height={360}
            />
          </PopoverContent>
        </Popover>

        <Textarea
          ref={textareaRef}
          value={body}
          onChange={(event) => setBody(event.target.value)}
          onKeyDown={handleKeyDown}
          placeholder={isOtherUserOnline ? 'Type a message…' : `${otherUserName} is offline`}
          disabled={!isOtherUserOnline}
          rows={1}
          className="max-h-24 min-h-[2.25rem] flex-1 resize-none py-1.5 text-sm"
        />

        <Button
          size="icon"
          className="h-8 w-8 shrink-0"
          disabled={!isOtherUserOnline || (!body.trim() && pendingFiles.length === 0) || sendMutation.isPending}
          onClick={handleSend}
        >
          <Send className="h-4 w-4" />
        </Button>
      </div>
    </div>
  )
}
