import { useQuery } from '@tanstack/react-query'

import { fetchBranches } from '@/api/branches'
import { useUiStore } from '@/lib/ui-store'
import { cn } from '@/lib/utils'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'

/** The "All branches" picker: in the top bar, and inside the slide-in menu on small phones. */
export function BranchPicker({ className }: { className?: string }) {
  const selectedBranchId = useUiStore((state) => state.selectedBranchId)
  const setSelectedBranchId = useUiStore((state) => state.setSelectedBranchId)
  const { data: branches } = useQuery({ queryKey: ['branches'], queryFn: fetchBranches })

  return (
    <Select value={selectedBranchId ? String(selectedBranchId) : 'all'} onValueChange={(value) => setSelectedBranchId(value === 'all' ? null : Number(value))}>
      <SelectTrigger className={cn('h-9 rounded-full text-sm', className)} aria-label="Branch">
        <SelectValue placeholder="All branches" />
      </SelectTrigger>
      <SelectContent>
        <SelectItem value="all">All branches</SelectItem>
        {branches?.map((branch) => (
          <SelectItem key={branch.id} value={String(branch.id)}>
            {branch.name}
          </SelectItem>
        ))}
      </SelectContent>
    </Select>
  )
}
