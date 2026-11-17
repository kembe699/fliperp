import { cn } from '@/lib/utils'

/**
 * The official FlipERP loader (brand/loader/fliperp-loader.svg, an animated
 * SVG). For page loads and big panels or tables while their data loads; for
 * buttons keep the small inline spinner. With "reduce motion" switched on in
 * the OS, the static icon is shown instead of the animation.
 */
const SIZES = { sm: 'h-10 w-10', md: 'h-16 w-16', lg: 'h-24 w-24' } as const

export function FlipLoader({ size = 'md', label = 'Loading…', className }: { size?: keyof typeof SIZES; label?: string; className?: string }) {
  return (
    <div role="status" aria-live="polite" className={cn('flex flex-col items-center justify-center', className)}>
      <picture>
        <source srcSet="/brand/icon/fliperp-icon.svg" media="(prefers-reduced-motion: reduce)" />
        <img src="/brand/loader/fliperp-loader.svg" alt="" aria-hidden="true" className={SIZES[size]} draggable={false} />
      </picture>
      <span className="sr-only">{label}</span>
    </div>
  )
}

/** A loader centred in the area a page or panel would fill. */
export function FlipLoaderBlock({ label, className }: { label?: string; className?: string }) {
  return <FlipLoader size="md" label={label} className={cn('min-h-[240px] w-full', className)} />
}
