import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useNavigate } from 'react-router-dom'

import { fetchNotifications, markAllNotificationsRead, markNotificationRead } from '@/api/notifications'
import { formatRelativeTime } from '@/lib/format'
import { CATEGORY_LABELS, iconForCategory, labelForCategory } from '@/lib/notification-meta'
import type { AppNotification } from '@/types/notification'

import { PageHeader } from '@/components/layout/PageHeader'
import { FilterBar } from '@/components/layout/FilterBar'
import { DataTable, type DataTableColumn, type DataTableRowAction } from '@/components/shared/DataTable'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Button } from '@/components/ui/button'

const pillTrigger = 'h-8 w-auto gap-1.5 rounded-full border-border bg-card px-3.5 text-sm text-muted-foreground'

export function NotificationsPage() {
  const navigate = useNavigate()
  const queryClient = useQueryClient()
  const [page, setPage] = useState(1)
  const [status, setStatus] = useState<'all' | 'read' | 'unread'>('all')
  const [category, setCategory] = useState('all')

  const queryKey = ['notifications', 'page', page, status, category]

  const { data, isLoading, isError } = useQuery({
    queryKey,
    queryFn: () =>
      fetchNotifications({
        page,
        per_page: 15,
        status: status === 'all' ? undefined : status,
        category: category === 'all' ? undefined : category,
      }),
  })

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['notifications'] })
  }

  const markReadMutation = useMutation({
    mutationFn: (id: string) => markNotificationRead(id),
    onSuccess: invalidate,
  })

  const markAllReadMutation = useMutation({
    mutationFn: markAllNotificationsRead,
    onSuccess: invalidate,
  })

  const openNotification = (notification: AppNotification) => {
    if (!notification.read_at) {
      markReadMutation.mutate(notification.id)
    }
    if (notification.link) {
      navigate(notification.link)
    }
  }

  const columns: DataTableColumn<AppNotification>[] = [
    {
      key: 'title',
      header: 'Notification',
      render: (row) => {
        const Icon = iconForCategory(row.category)
        return (
          <button type="button" onClick={() => openNotification(row)} className="flex items-start gap-3 text-left">
            <Icon className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
            <span>
              <span className={`block text-sm ${!row.read_at ? 'font-semibold text-foreground' : 'font-medium text-foreground'}`}>{row.title}</span>
              <span className="mt-0.5 block text-xs text-muted-foreground">{row.body}</span>
            </span>
          </button>
        )
      },
    },
    { key: 'category', header: 'Type', render: (row) => <span className="text-xs text-muted-foreground">{labelForCategory(row.category)}</span> },
    { key: 'created_at', header: 'When', render: (row) => formatRelativeTime(row.created_at) },
    {
      key: 'read_at',
      header: 'Status',
      render: (row) => <StatusBadge label={row.read_at ? 'Read' : 'Unread'} variant={row.read_at ? 'neutral' : 'info'} />,
    },
  ]

  const rowActions: (row: AppNotification) => DataTableRowAction<AppNotification>[] = (row) => [
    ...(!row.read_at ? [{ label: 'Mark as read', onClick: (n: AppNotification) => markReadMutation.mutate(n.id) }] : []),
    ...(row.link ? [{ label: 'Open', onClick: openNotification }] : []),
  ]

  return (
    <div>
      <PageHeader
        parent="Account"
        title="Notifications"
        action={
          <Button variant="outline" onClick={() => markAllReadMutation.mutate()}>
            Mark all as read
          </Button>
        }
      />

      <FilterBar>
        <Select value={status} onValueChange={(value) => { setStatus(value as 'all' | 'read' | 'unread'); setPage(1) }}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Status" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All</SelectItem>
            <SelectItem value="unread">Unread</SelectItem>
            <SelectItem value="read">Read</SelectItem>
          </SelectContent>
        </Select>

        <Select value={category} onValueChange={(value) => { setCategory(value); setPage(1) }}>
          <SelectTrigger className={pillTrigger}>
            <SelectValue placeholder="Type" />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value="all">All types</SelectItem>
            {Object.entries(CATEGORY_LABELS).map(([value, label]) => (
              <SelectItem key={value} value={value}>
                {label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      </FilterBar>

      {isError ? (
        <p className="rounded-xl border border-border bg-card p-6 text-sm text-destructive">Could not load notifications. Please try again.</p>
      ) : (
        <DataTable
          columns={columns}
          data={data?.data ?? []}
          rowKey={(row) => row.id}
          isLoading={isLoading}
          rowActions={rowActions}
          emptyTitle="No notifications"
          emptySubtext="You're all caught up — new activity will show up here."
          page={data?.meta.current_page}
          pageCount={data?.meta.last_page}
          totalRows={data?.meta.total}
          onPageChange={setPage}
        />
      )}
    </div>
  )
}
