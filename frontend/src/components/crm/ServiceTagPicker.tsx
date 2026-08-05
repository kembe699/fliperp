import { useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Plus, X } from 'lucide-react'

import { fetchCrmServices } from '@/api/crm'
import { getApiErrorInfo } from '@/lib/api-errors'
import type { CrmService } from '@/types/crm'

import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'

interface ServiceTagPickerProps {
  services: CrmService[]
  onSync: (serviceIds: number[]) => Promise<unknown>
  queryKeyToInvalidate: unknown[]
  disabled?: boolean
  label?: string
  emptyText?: string
}

export function ServiceTagPicker({ services, onSync, queryKeyToInvalidate, disabled, label = 'Interested Services', emptyText = 'No services attached yet.' }: ServiceTagPickerProps) {
  const queryClient = useQueryClient()
  const [open, setOpen] = useState(false)

  const { data: catalog } = useQuery({ queryKey: ['crm-services-all'], queryFn: () => fetchCrmServices({ per_page: 100, is_active: true }), enabled: open })

  const mutation = useMutation({
    mutationFn: onSync,
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: queryKeyToInvalidate })
    },
    onError: (error) => toast.error(getApiErrorInfo(error).message),
  })

  const currentIds = services.map((s) => s.id)
  const availableToAdd = (catalog?.data ?? []).filter((service) => !currentIds.includes(service.id))

  const add = (serviceId: number) => {
    setOpen(false)
    mutation.mutate([...currentIds, serviceId])
  }

  const remove = (serviceId: number) => {
    mutation.mutate(currentIds.filter((id) => id !== serviceId))
  }

  return (
    <div className="space-y-1.5">
      <p className="text-sm font-medium text-foreground">{label}</p>
      <div className="flex flex-wrap items-center gap-1.5">
        {services.length === 0 && <p className="text-sm text-muted-foreground">{emptyText}</p>}
        {services.map((service) => (
          <Badge key={service.id} variant="outline" className="gap-1 pr-1.5">
            {service.name}
            {!disabled && (
              <button
                type="button"
                aria-label={`Remove ${service.name}`}
                disabled={mutation.isPending}
                onClick={() => remove(service.id)}
                className="rounded-full p-0.5 hover:bg-muted"
              >
                <X className="h-3 w-3" />
              </button>
            )}
          </Badge>
        ))}
        {!disabled && (
          <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
              <Button type="button" variant="outline" size="sm" className="h-7 gap-1 rounded-full px-2.5 text-xs" disabled={mutation.isPending}>
                <Plus className="h-3 w-3" />
                Add
              </Button>
            </PopoverTrigger>
            <PopoverContent className="w-64 p-0" align="start">
              <Command>
                <CommandInput placeholder="Search services…" />
                <CommandList>
                  <CommandEmpty>No matching services.</CommandEmpty>
                  <CommandGroup>
                    {availableToAdd.map((service) => (
                      <CommandItem key={service.id} value={service.name} onSelect={() => add(service.id)}>
                        {service.name}
                      </CommandItem>
                    ))}
                  </CommandGroup>
                </CommandList>
              </Command>
            </PopoverContent>
          </Popover>
        )}
      </div>
    </div>
  )
}
