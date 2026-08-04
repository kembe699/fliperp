import { api } from '@/lib/api'
import type { ApiResponse, PaginatedResponse } from '@/types/api'
import type { AccountingPeriod, AccountingPeriodStatus, ChartOfAccount, ChartOfAccountType, JournalEntry, JournalEntryStatus } from '@/types/accounting'

// Chart of Accounts
export interface ChartOfAccountInput {
  code: string
  name: string
  type: ChartOfAccountType
  parent_id?: number | null
  is_active?: boolean
}

export async function createChartOfAccount(values: ChartOfAccountInput): Promise<ChartOfAccount> {
  const { data } = await api.post<ApiResponse<ChartOfAccount>>('/chart-of-accounts', values)
  return data.data
}

export async function updateChartOfAccount(id: number, values: Partial<ChartOfAccountInput>): Promise<ChartOfAccount> {
  const { data } = await api.put<ApiResponse<ChartOfAccount>>(`/chart-of-accounts/${id}`, values)
  return data.data
}

export async function deleteChartOfAccount(id: number): Promise<void> {
  await api.delete(`/chart-of-accounts/${id}`)
}

// Journal entries
export interface JournalEntryFilters {
  page?: number
  per_page?: number
  status?: JournalEntryStatus
}

export async function fetchJournalEntries(filters: JournalEntryFilters): Promise<PaginatedResponse<JournalEntry>> {
  const { data } = await api.get<PaginatedResponse<JournalEntry>>('/journal-entries', { params: filters })
  return data
}

export async function fetchJournalEntry(id: number): Promise<JournalEntry> {
  const { data } = await api.get<ApiResponse<JournalEntry>>(`/journal-entries/${id}`)
  return data.data
}

export interface JournalEntryInput {
  branch_id?: number | null
  reference_number: string
  entry_date: string
  description?: string | null
  lines: { account_id: number; debit?: number; credit?: number; description?: string | null }[]
}

export async function createJournalEntry(values: JournalEntryInput): Promise<JournalEntry> {
  const { data } = await api.post<ApiResponse<JournalEntry>>('/journal-entries', values)
  return data.data
}

export async function postJournalEntry(id: number): Promise<JournalEntry> {
  const { data } = await api.post<ApiResponse<JournalEntry>>(`/journal-entries/${id}/post`)
  return data.data
}

export async function reverseJournalEntry(id: number): Promise<JournalEntry> {
  const { data } = await api.post<ApiResponse<JournalEntry>>(`/journal-entries/${id}/reverse`)
  return data.data
}

// Accounting periods
export async function fetchAccountingPeriods(params: { page?: number; per_page?: number } = {}): Promise<PaginatedResponse<AccountingPeriod>> {
  const { data } = await api.get<PaginatedResponse<AccountingPeriod>>('/accounting-periods', { params })
  return data
}

export interface AccountingPeriodInput {
  name: string
  start_date: string
  end_date: string
}

export async function createAccountingPeriod(values: AccountingPeriodInput): Promise<AccountingPeriod> {
  const { data } = await api.post<ApiResponse<AccountingPeriod>>('/accounting-periods', values)
  return data.data
}

export async function closeAccountingPeriod(id: number): Promise<AccountingPeriod> {
  const { data } = await api.post<ApiResponse<AccountingPeriod>>(`/accounting-periods/${id}/close`)
  return data.data
}

export type { AccountingPeriodStatus }
