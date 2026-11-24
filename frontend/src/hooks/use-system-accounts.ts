import { useQuery } from '@tanstack/react-query'

import { fetchSystemAccounts } from '@/api/accounting'
import type { SystemAccount, SystemAccountFunction } from '@/types/accounting'

/**
 * The accounts this company's automatic postings use (cash, payables,
 * default expense ...), keyed by function. Screens read the account from here
 * instead of assuming a chart code such as 1000.
 */
export function useSystemAccounts() {
  const query = useQuery({ queryKey: ['system-accounts'], queryFn: fetchSystemAccounts, staleTime: 5 * 60_000 })
  const byFunction = Object.fromEntries((query.data ?? []).map((row) => [row.function, row.account])) as Partial<
    Record<SystemAccountFunction, SystemAccount['account']>
  >

  return { ...query, byFunction }
}
