import { Link } from 'react-router-dom'
import { ArrowUpRight, type LucideIcon } from 'lucide-react'

interface QuickActionCardProps {
  icon: LucideIcon
  title: string
  subtitle: string
  to: string
}

export function QuickActionCard({ icon: Icon, title, subtitle, to }: QuickActionCardProps) {
  return (
    <Link
      to={to}
      className="group relative block rounded-xl border border-border bg-card p-6 transition-colors hover:border-primary/40"
    >
      <ArrowUpRight className="absolute right-5 top-5 h-4 w-4 text-muted-foreground transition-colors group-hover:text-primary" />
      <div className="mb-4 flex h-10 w-10 items-center justify-center rounded-lg bg-accent text-primary">
        <Icon className="h-5 w-5" />
      </div>
      <p className="text-base font-semibold text-foreground">{title}</p>
      <p className="mt-1 text-sm text-muted-foreground">{subtitle}</p>
    </Link>
  )
}
