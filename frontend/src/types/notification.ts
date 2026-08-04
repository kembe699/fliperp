export interface AppNotification {
  id: string
  title: string
  body: string
  link: string | null
  category: string
  read_at: string | null
  created_at: string
}
