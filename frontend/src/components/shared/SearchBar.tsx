import { useEffect, useRef, useState } from 'react'
import { Search, X } from 'lucide-react'

import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

interface SearchBarOption {
  value: string
  label: string
}

interface SearchBarProps {
  options: SearchBarOption[]
  onSearch: (searchBy: string, query: string) => void
  onClear?: () => void
  placeholder?: string
}

/**
 * Compact server-side search: searches as you type (after a short pause) or on
 * Enter, with an ✕ to clear. The "search by" picker only shows when there is a
 * choice to make, so a single-field search is just the box.
 */
export function SearchBar({ options, onSearch, onClear, placeholder = 'Search…' }: SearchBarProps) {
  const [searchBy, setSearchBy] = useState(options[0]?.value ?? '')
  const [query, setQuery] = useState('')
  const last = useRef({ searchBy, query: '' })
  const handlers = useRef({ onSearch, onClear })
  handlers.current = { onSearch, onClear }

  const run = (by: string, text: string) => {
    const trimmed = text.trim()
    if (last.current.searchBy === by && last.current.query === trimmed) return
    last.current = { searchBy: by, query: trimmed }
    if (trimmed === '' && handlers.current.onClear) handlers.current.onClear()
    else handlers.current.onSearch(by, trimmed)
  }

  useEffect(() => {
    const timer = window.setTimeout(() => run(searchBy, query), 400)
    return () => window.clearTimeout(timer)
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [searchBy, query])

  return (
    <div className="flex items-center gap-2">
      {options.length > 1 && (
        <Select value={searchBy} onValueChange={setSearchBy}>
          <SelectTrigger className="h-8 w-32 text-sm" aria-label="Search by">
            <SelectValue placeholder="Search by" />
          </SelectTrigger>
          <SelectContent>
            {options.map((option) => (
              <SelectItem key={option.value} value={option.value}>
                {option.label}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}

      <div className="relative w-52">
        <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          type="search"
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === 'Enter') run(searchBy, query)
            if (event.key === 'Escape') setQuery('')
          }}
          placeholder={placeholder}
          aria-label={placeholder.replace(/…$/, '')}
          className="h-8 pl-8 pr-8 text-sm [&::-webkit-search-cancel-button]:hidden"
        />
        {query && (
          <button
            type="button"
            aria-label="Clear search"
            onClick={() => {
              setQuery('')
              run(searchBy, '')
            }}
            className="absolute right-1.5 top-1/2 -translate-y-1/2 rounded p-1 text-muted-foreground hover:bg-accent"
          >
            <X className="h-3.5 w-3.5" />
          </button>
        )}
      </div>
    </div>
  )
}
