import { cn } from '@/lib/utils'

/**
 * FlipERP's own product logo, from the official files in /public/brand
 * (source: brand/ at the repo root; see brand/BRAND.md). Every place the app
 * shows the FlipERP mark uses this component, so it changes in one place.
 * A client's own company logo is something else and never goes through here.
 *
 * - variant: horizontal (default), stacked (square spaces: login, splash), icon (collapsed sidebar, small headers)
 * - theme: light background (default) or dark/navy background (white version)
 */
export type BrandLogoVariant = 'horizontal' | 'stacked' | 'icon'
export type BrandLogoTheme = 'light' | 'dark'

const FILES: Record<BrandLogoVariant, Record<BrandLogoTheme, string>> = {
  horizontal: { light: '/brand/logo/fliperp-logo-horizontal.svg', dark: '/brand/logo/fliperp-logo-horizontal-white.svg' },
  stacked: { light: '/brand/logo/fliperp-logo-stacked.svg', dark: '/brand/logo/fliperp-logo-stacked-white.svg' },
  icon: { light: '/brand/icon/fliperp-icon.svg', dark: '/brand/icon/fliperp-icon-white.svg' },
}

export function brandLogoSrc(variant: BrandLogoVariant = 'horizontal', theme: BrandLogoTheme = 'light'): string {
  return FILES[variant][theme]
}

export function BrandLogo({ variant = 'horizontal', theme = 'light', className }: { variant?: BrandLogoVariant; theme?: BrandLogoTheme; className?: string }) {
  return <img src={brandLogoSrc(variant, theme)} alt="FlipERP" className={cn('w-auto select-none', className)} draggable={false} />
}
