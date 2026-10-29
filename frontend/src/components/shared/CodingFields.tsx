import { cn } from '@/lib/utils'
import { useDimensionOptions, type DimensionFilterValue } from '@/lib/coding'
import type { Coding } from '@/types/grants'

import { Label } from '@/components/ui/label'
import { SearchableSelect } from '@/components/shared/SearchableSelect'

interface Props {
  value: Coding
  onChange: (patch: Partial<Coding>) => void
  /** The transaction date: only SOFs whose dates cover it are offered (the backend rejects the rest). */
  date?: string
  /** Under a line-item row: no labels, smaller controls. */
  compact?: boolean
  className?: string
}

/**
 * The coding block for one line — SOF, DEA, project, cost centre — each
 * optional. The DEA list follows the chosen SOF, and changing the SOF clears
 * a DEA that belongs to another one.
 */
export function CodingFields({ value, onChange, date, compact = false, className }: Props) {
  const { data: options } = useDimensionOptions()

  const grants = (options?.grants ?? []).filter((grant) => !date || grant.id === value.grant_id || (grant.start_date <= date && grant.end_date >= date))
  const deas = options?.grants.find((grant) => grant.id === value.grant_id)?.deas ?? []
  const asValue = (id: number | null) => (id ? String(id) : null)
  const asId = (selected: string | null) => (selected ? Number(selected) : null)

  const fields = [
    {
      key: 'grant_id',
      label: 'SOF',
      node: (
        <SearchableSelect
          options={grants.map((grant) => ({ value: String(grant.id), label: grant.code, sublabel: `${grant.name} · ${grant.currency_code}${grant.donor_name ? ` · ${grant.donor_name}` : ''}` }))}
          value={asValue(value.grant_id)}
          onChange={(selected) => onChange({ grant_id: asId(selected), dea_id: null })}
          placeholder={compact ? 'SOF' : 'No SOF'}
          className={compact ? 'h-8 text-xs' : undefined}
          emptyText={date ? `No active SOF covers ${date}.` : 'No active SOFs.'}
        />
      ),
    },
    {
      key: 'dea_id',
      label: 'DEA',
      node: (
        <SearchableSelect
          options={deas.map((dea) => ({ value: String(dea.id), label: dea.code, sublabel: dea.name }))}
          value={asValue(value.dea_id)}
          onChange={(selected) => onChange({ dea_id: asId(selected) })}
          placeholder={compact ? 'DEA' : value.grant_id ? 'No DEA' : 'Choose a SOF first'}
          disabled={!value.grant_id}
          className={compact ? 'h-8 text-xs' : undefined}
          emptyText="This SOF has no DEAs."
        />
      ),
    },
    {
      key: 'project_id',
      label: 'Project',
      node: (
        <SearchableSelect
          options={(options?.projects ?? []).map((project) => ({ value: String(project.id), label: project.code ?? project.name, sublabel: project.code ? project.name : undefined }))}
          value={asValue(value.project_id)}
          onChange={(selected) => onChange({ project_id: asId(selected) })}
          placeholder={compact ? 'Project' : 'No project'}
          className={compact ? 'h-8 text-xs' : undefined}
        />
      ),
    },
    {
      key: 'cost_centre_id',
      label: 'Cost Centre',
      node: (
        <SearchableSelect
          options={(options?.cost_centres ?? []).map((centre) => ({ value: String(centre.id), label: centre.code, sublabel: centre.name }))}
          value={asValue(value.cost_centre_id)}
          onChange={(selected) => onChange({ cost_centre_id: asId(selected) })}
          placeholder={compact ? 'Cost centre' : 'No cost centre'}
          className={compact ? 'h-8 text-xs' : undefined}
        />
      ),
    },
  ]

  if (compact) {
    return (
      <div className={cn('grid grid-cols-2 gap-2 sm:grid-cols-4', className)}>
        {fields.map((field) => (
          <div key={field.key} title={field.label}>
            {field.node}
          </div>
        ))}
      </div>
    )
  }

  return (
    <div className={cn('grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4', className)}>
      {fields.map((field) => (
        <div key={field.key} className="space-y-1.5">
          <Label>{field.label}</Label>
          {field.node}
        </div>
      ))}
    </div>
  )
}

/** Report filters by SOF, DEA, project and cost centre (closed SOFs included, for past periods). */
export function DimensionFilters({ value, onChange, showDea = true }: { value: DimensionFilterValue; onChange: (value: DimensionFilterValue) => void; showDea?: boolean }) {
  const { data: options } = useDimensionOptions(true)
  const deas = options?.grants.find((grant) => grant.id === value.grant_id)?.deas ?? []
  const set = (key: keyof Coding, selected: string | null) => {
    const next = { ...value, [key]: selected ? Number(selected) : undefined }
    if (key === 'grant_id') delete next.dea_id
    onChange(next)
  }
  const pick = (id?: number) => (id ? String(id) : null)

  return (
    <>
      <div className="w-44 space-y-1.5">
        <Label>SOF</Label>
        <SearchableSelect
          options={(options?.grants ?? []).map((grant) => ({ value: String(grant.id), label: grant.code, sublabel: grant.name }))}
          value={pick(value.grant_id)}
          onChange={(selected) => set('grant_id', selected)}
          placeholder="All"
        />
      </div>
      {showDea && (
        <div className="w-36 space-y-1.5">
          <Label>DEA</Label>
          <SearchableSelect
            options={deas.map((dea) => ({ value: String(dea.id), label: dea.code, sublabel: dea.name }))}
            value={pick(value.dea_id)}
            onChange={(selected) => set('dea_id', selected)}
            placeholder="All"
            disabled={!value.grant_id}
          />
        </div>
      )}
      <div className="w-44 space-y-1.5">
        <Label>Project</Label>
        <SearchableSelect
          options={(options?.projects ?? []).map((project) => ({ value: String(project.id), label: project.code ?? project.name, sublabel: project.code ? project.name : undefined }))}
          value={pick(value.project_id)}
          onChange={(selected) => set('project_id', selected)}
          placeholder="All"
        />
      </div>
      <div className="w-40 space-y-1.5">
        <Label>Cost Centre</Label>
        <SearchableSelect
          options={(options?.cost_centres ?? []).map((centre) => ({ value: String(centre.id), label: centre.code, sublabel: centre.name }))}
          value={pick(value.cost_centre_id)}
          onChange={(selected) => set('cost_centre_id', selected)}
          placeholder="All"
        />
      </div>
    </>
  )
}
