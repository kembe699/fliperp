import { useState } from 'react'
import { Search } from 'lucide-react'

import { Button } from '@/components/ui/button'
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

export function SearchBar({ options, onSearch, onClear, placeholder = 'Search…' }: SearchBarProps) {
  const [searchBy, setSearchBy] = useState(options[0]?.value ?? '')
  const [query, setQuery] = useState('')

  const handleClear = () => {
    setQuery('')
    onClear?.()
  }

  return (
    <div className="flex flex-wrap items-center gap-2">
      <Select value={searchBy} onValueChange={setSearchBy}>
        <SelectTrigger className="h-10 w-40 rounded-lg text-sm">
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

      <div className="relative flex-1 min-w-48">
        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
        <Input
          value={query}
          onChange={(event) => setQuery(event.target.value)}
          placeholder={placeholder}
          className="pl-9"
        />
      </div>

      <Button onClick={() => onSearch(searchBy, query)}>Search</Button>

      <button type="button" onClick={handleClear} className="text-sm font-medium text-primary underline-offset-2 hover:underline">
        Clear
      </button>
    </div>
  )
}
