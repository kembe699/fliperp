import { useEffect } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'
import { toast } from 'sonner'
import { Bell, CheckCheck } from 'lucide-react'

import { fetchNotifications, fetchUnreadNotificationCount, markAllNotificationsRead, markNotificationRead } from '@/api/notifications'
import { useAuthStore } from '@/lib/auth-store'
import { getEcho } from '@/lib/echo'
import { formatRelativeTime } from '@/lib/format'
import { iconForCategory, isToastWorthy } from '@/lib/notification-meta'
import type { AppNotification } from '@/types/notification'
import type { PaginatedResponse } from '@/types/api'

import { Button } from '@/components/ui/button'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'

const RECENT_QUERY_KEY = ['notifications', 'recent']
const UNREAD_COUNT_QUERY_KEY = ['notifications', 'unread-count']

export function NotificationBell() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const userId = useAuthStore((state) => state.user?.id)

  const { data: unreadCount } = useQuery({
    queryKey: UNREAD_COUNT_QUERY_KEY,
    queryFn: fetchUnreadNotificationCount,
    refetchInterval: 60_000,
  })

  const { data: recent } = useQuery({
    queryKey: RECENT_QUERY_KEY,
    queryFn: () => fetchNotifications({ per_page: 8 }),
  })

  const markReadMutation = useMutation({
    mutationFn: (id: string) => markNotificationRead(id),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: RECENT_QUERY_KEY })
      queryClient.invalidateQueries({ queryKey: UNREAD_COUNT_QUERY_KEY })
    },
  })

  const markAllReadMutation = useMutation({
    mutationFn: markAllNotificationsRead,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: RECENT_QUERY_KEY })
      queryClient.invalidateQueries({ queryKey: UNREAD_COUNT_QUERY_KEY })
    },
  })

  // Live push over private-user.{id} (see routes/channels.php +
  // User::receivesBroadcastNotificationsOn()). Every notification type
  // broadcasts the same event name (AppNotification::broadcastAs()) so one
  // listener here covers all of them — the payload's `category` decides
  // the icon and whether it's toast-worthy.
  useEffect(() => {
    if (!userId) return
    const echo = getEcho()
    if (!echo) return

    const channel = echo.private(`user.${userId}`)

    channel.listen('.notification.created', (notification: AppNotification) => {
      queryClient.setQueryData<PaginatedResponse<AppNotification>>(RECENT_QUERY_KEY, (old) =>
        old ? { ...old, data: [notification, ...old.data].slice(0, 8) } : old,
      )
      queryClient.setQueryData<number>(UNREAD_COUNT_QUERY_KEY, (old) => (old ?? 0) + 1)

      if (isToastWorthy(notification.category)) {
        toast(notification.title, { description: notification.body })
      }
    })

    return () => {
      // stopListening, not leave() — the chat widget listens on this same
      // private-user.{id} channel for its own events, and leave() tears
      // down the whole channel subscription for every listener on it, not
      // just this one.
      channel.stopListening('.notification.created')
    }
  }, [userId, queryClient])

  const handleNotificationClick = (notification: AppNotification) => {
    if (!notification.read_at) {
      markReadMutation.mutate(notification.id)
    }
    if (notification.link) {
      navigate(notification.link)
    }
  }

  const notifications = recent?.data ?? []

  return (
    <Popover>
      <PopoverTrigger asChild>
        <Button variant="outline" size="icon" className="relative rounded-full" aria-label="Notifications">
          <Bell className="h-4 w-4" />
          {!!unreadCount && unreadCount > 0 && (
            <span className="absolute -right-1 -top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-semibold text-white">
              {unreadCount > 99 ? '99+' : unreadCount}
            </span>
          )}
        </Button>
      </PopoverTrigger>
      <PopoverContent align="end" className="w-96 p-0">
        <div className="flex items-center justify-between border-b border-border px-4 py-3">
          <span className="text-sm font-semibold text-foreground">Notifications</span>
          {!!unreadCount && unreadCount > 0 && (
            <button
              type="button"
              onClick={() => markAllReadMutation.mutate()}
              className="flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
            >
              <CheckCheck className="h-3.5 w-3.5" />
              Mark all as read
            </button>
          )}
        </div>

        <div className="max-h-96 overflow-y-auto">
          {notifications.length === 0 ? (
            <p className="p-6 text-center text-sm text-muted-foreground">You're all caught up.</p>
          ) : (
            notifications.map((notification) => {
              const Icon = iconForCategory(notification.category)
              const isUnread = !notification.read_at

              return (
                <button
                  key={notification.id}
                  type="button"
                  onClick={() => handleNotificationClick(notification)}
                  className={`flex w-full items-start gap-3 border-b border-border px-4 py-3 text-left last:border-b-0 hover:bg-muted/60 ${
                    isUnread ? 'bg-primary/5' : ''
                  }`}
                >
                  <Icon className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
                  <div className="min-w-0 flex-1">
                    <p className="text-sm font-medium text-foreground">{notification.title}</p>
                    <p className="mt-0.5 line-clamp-2 text-xs text-muted-foreground">{notification.body}</p>
                    <p className="mt-1 text-[11px] text-muted-foreground">{formatRelativeTime(notification.created_at)}</p>
                  </div>
                  {isUnread && <span className="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary" />}
                </button>
              )
            })
          )}
        </div>

        <div className="border-t border-border px-4 py-2.5 text-center">
          <button type="button" onClick={() => navigate('/notifications')} className="text-xs font-medium text-primary hover:underline">
            View all
          </button>
        </div>
      </PopoverContent>
    </Popover>
  )
}
