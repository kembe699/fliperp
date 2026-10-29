import { useQuery } from '@tanstack/react-query'

import { fetchDimensionOptions } from '@/api/grants'
import type { Coding, CodingLabels } from '@/types/grants'

/** SOFs (with DEAs), projects and cost centres; `all` includes closed/inactive ones (report filters). */
export function useDimensionOptions(all = false) {
  return useQuery({ queryKey: ['dimensions', 'options', all], queryFn: () => fetchDimensionOptions(all) })
}

/** "SOF-100 / DEA-01 / PRJ-1 / CC-JUB" for a coded line, or null when it carries no coding. */
export function codingSummary(line: Partial<CodingLabels>): string | null {
  const parts = [line.grant_code, line.dea_code, line.project_code, line.cost_centre_code].filter(Boolean)
  return parts.length ? parts.join(' / ') : null
}

export type DimensionFilterValue = Partial<Record<keyof Coding, number>>
