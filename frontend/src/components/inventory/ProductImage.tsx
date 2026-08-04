import { Package } from 'lucide-react'

import { cn } from '@/lib/utils'
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'

/**
 * Radix's Avatar tracks image load/error state itself, so a broken or 404ing
 * image_url automatically falls back to the placeholder icon below — no
 * manual onError wiring needed at call sites.
 */
export function ProductImage({ src, alt, className }: { src?: string | null; alt: string; className?: string }) {
  return (
    <Avatar className={cn('h-9 w-9 rounded-lg', className)}>
      {src && <AvatarImage src={src} alt={alt} className="object-cover" />}
      <AvatarFallback className="rounded-lg bg-muted">
        <Package className="h-1/2 w-1/2 text-muted-foreground" />
      </AvatarFallback>
    </Avatar>
  )
}
