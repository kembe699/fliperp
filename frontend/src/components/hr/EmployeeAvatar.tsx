import { cn } from '@/lib/utils'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'

function initials(name: string): string {
  const parts = name.trim().split(/\s+/).filter(Boolean)
  if (parts.length === 0) return '?'
  return (parts[0][0] + (parts[parts.length - 1]?.[0] ?? '')).toUpperCase()
}

/**
 * Radix's Avatar tracks image load/error state itself, so a broken or 404ing
 * photo_url automatically falls back to initials below — no manual onError
 * wiring needed at call sites (mirrors inventory/ProductImage.tsx).
 */
export function EmployeeAvatar({ src, name, className }: { src?: string | null; name: string; className?: string }) {
  return (
    <Avatar className={cn('h-9 w-9', className)}>
      {src && <AvatarImage src={src} alt={name} className="object-cover" />}
      <AvatarFallback className="bg-primary/10 font-semibold text-primary">{initials(name)}</AvatarFallback>
    </Avatar>
  )
}
