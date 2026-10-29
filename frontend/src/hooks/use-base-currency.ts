import { useAuthStore } from '@/lib/auth-store'

/** The company's base currency: the one its ledger is kept in. */
export function useBaseCurrency(): string {
  return useAuthStore((state) => state.company?.currency_code) ?? 'USD'
}
