import { useEffect, useRef } from 'react'
import { Bold, Italic, List, ListOrdered, Underline } from 'lucide-react'

import { cn } from '@/lib/utils'

interface RichTextEditorProps {
  value: string
  onChange: (html: string) => void
  className?: string
  disabled?: boolean
}

const TOOLBAR_COMMANDS = [
  { command: 'bold', icon: Bold, label: 'Bold' },
  { command: 'italic', icon: Italic, label: 'Italic' },
  { command: 'underline', icon: Underline, label: 'Underline' },
  { command: 'insertUnorderedList', icon: List, label: 'Bullet list' },
  { command: 'insertOrderedList', icon: ListOrdered, label: 'Numbered list' },
] as const

/**
 * A zero-dependency rich text editor: contentEditable + document.execCommand
 * for a handful of formatting commands. Contract text is short-form legal
 * paragraphs, not a full document-authoring surface, so this covers the
 * need without pulling in Tiptap/ProseMirror.
 */
export function RichTextEditor({ value, onChange, className, disabled }: RichTextEditorProps) {
  const editorRef = useRef<HTMLDivElement>(null)
  const isInternalUpdate = useRef(false)

  useEffect(() => {
    if (!editorRef.current) return
    if (isInternalUpdate.current) {
      isInternalUpdate.current = false
      return
    }
    if (editorRef.current.innerHTML !== value) {
      editorRef.current.innerHTML = value
    }
  }, [value])

  const handleInput = () => {
    if (!editorRef.current) return
    isInternalUpdate.current = true
    onChange(editorRef.current.innerHTML)
  }

  const runCommand = (command: string) => {
    if (disabled) return
    editorRef.current?.focus()
    document.execCommand(command)
    handleInput()
  }

  return (
    <div className={cn('rounded-lg border border-input', className)}>
      <div className="flex items-center gap-1 border-b border-border bg-muted/40 px-2 py-1.5">
        {TOOLBAR_COMMANDS.map(({ command, icon: Icon, label }) => (
          <button
            key={command}
            type="button"
            title={label}
            disabled={disabled}
            onClick={() => runCommand(command)}
            className="rounded p-1.5 text-muted-foreground hover:bg-accent hover:text-foreground disabled:opacity-50"
          >
            <Icon className="h-3.5 w-3.5" />
          </button>
        ))}
      </div>
      <div
        ref={editorRef}
        contentEditable={!disabled}
        onInput={handleInput}
        className="min-h-[280px] max-h-[420px] overflow-y-auto px-4 py-3 text-sm leading-relaxed text-foreground focus:outline-none [&_p]:mb-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5"
        suppressContentEditableWarning
      />
    </div>
  )
}
