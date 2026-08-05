import { useEffect, useState, type KeyboardEvent } from 'react'
import { Pencil } from 'lucide-react'

import { cn } from '@/lib/utils'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'

interface InlineTextFieldProps {
  label: string
  value: string | number | null
  onSave: (value: string) => Promise<unknown>
  type?: string
  placeholder?: string
  disabled?: boolean
  className?: string
}

export function InlineTextField({ label, value, onSave, type = 'text', placeholder = '—', disabled, className }: InlineTextFieldProps) {
  const [editing, setEditing] = useState(false)
  const [draft, setDraft] = useState(value === null || value === undefined ? '' : String(value))
  const [saving, setSaving] = useState(false)

  useEffect(() => {
    if (!editing) setDraft(value === null || value === undefined ? '' : String(value))
  }, [value, editing])

  const commit = async () => {
    const original = value === null || value === undefined ? '' : String(value)
    if (draft === original) {
      setEditing(false)
      return
    }
    setSaving(true)
    try {
      await onSave(draft)
    } finally {
      setSaving(false)
      setEditing(false)
    }
  }

  const handleKeyDown = (event: KeyboardEvent<HTMLInputElement>) => {
    if (event.key === 'Enter') {
      event.currentTarget.blur()
    } else if (event.key === 'Escape') {
      setDraft(value === null || value === undefined ? '' : String(value))
      setEditing(false)
    }
  }

  return (
    <div className={cn('space-y-1.5', className)}>
      <Label>{label}</Label>
      {editing ? (
        <Input
          autoFocus
          type={type}
          value={draft}
          disabled={saving}
          onChange={(event) => setDraft(event.target.value)}
          onBlur={commit}
          onKeyDown={handleKeyDown}
        />
      ) : (
        <button
          type="button"
          disabled={disabled}
          onClick={() => !disabled && setEditing(true)}
          className={cn(
            'group flex h-10 w-full items-center justify-between rounded-lg border border-transparent px-3 text-left text-sm text-foreground hover:border-border hover:bg-accent/40 disabled:cursor-default disabled:hover:border-transparent disabled:hover:bg-transparent',
          )}
        >
          <span className={cn(!value && 'text-muted-foreground')}>{value || placeholder}</span>
          {!disabled && <Pencil className="h-3.5 w-3.5 text-muted-foreground opacity-0 group-hover:opacity-100" />}
        </button>
      )}
    </div>
  )
}
