/** Only source modules with an unambiguous, single-record frontend route are made clickable. */
const SOURCE_ROUTE: Record<string, (id: number) => string> = {
  payroll: (id) => `/payroll-runs/${id}`,
  assets: (id) => `/assets/${id}`,
}

export function sourceRecordLink(sourceModule: string | null, sourceId: number | null): string | null {
  if (!sourceModule || !sourceId) return null
  return SOURCE_ROUTE[sourceModule]?.(sourceId) ?? null
}
