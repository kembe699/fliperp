import { cn } from '@/lib/utils'
import type { PlatformModule } from '@/types/platform'

interface ModuleToggleListProps {
  modules: PlatformModule[]
  value: string[]
  onChange: (value: string[]) => void
  disabled?: boolean
}

/**
 * One switch per module. A client only sees the modules switched on here —
 * their menus, pages and the API behind them; everything else is hidden.
 */
export function ModuleToggleList({ modules, value, onChange, disabled = false }: ModuleToggleListProps) {
  const toggle = (key: string) =>
    onChange(value.includes(key) ? value.filter((k) => k !== key) : modules.map((m) => m.key).filter((k) => k === key || value.includes(k)))

  return (
    <div className="divide-y divide-border rounded-lg border border-border">
      {modules.map((module) => {
        const on = value.includes(module.key)
        return (
          <label key={module.key} className={cn('flex items-start justify-between gap-4 px-4 py-3', disabled ? 'opacity-60' : 'cursor-pointer')}>
            <span>
              <span className="block text-sm font-medium text-foreground">{module.label}</span>
              <span className="block text-xs text-muted-foreground">{module.description}</span>
            </span>
            <button
              type="button"
              role="switch"
              aria-checked={on}
              aria-label={module.label}
              disabled={disabled}
              onClick={() => toggle(module.key)}
              className={cn(
                'relative mt-0.5 inline-flex h-5 w-9 shrink-0 items-center rounded-full transition-colors',
                on ? 'bg-primary' : 'bg-muted-foreground/30',
              )}
            >
              <span className={cn('inline-block h-4 w-4 rounded-full bg-background shadow transition-transform', on ? 'translate-x-4' : 'translate-x-0.5')} />
            </button>
          </label>
        )
      })}
    </div>
  )
}
