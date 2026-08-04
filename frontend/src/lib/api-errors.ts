import axios from 'axios'
import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'

export interface ApiErrorInfo {
  message: string
  errors: Record<string, string[]> | null
}

export function getApiErrorInfo(error: unknown): ApiErrorInfo {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined
    return {
      message: data?.message ?? 'Something went wrong. Please try again.',
      errors: data?.errors ?? null,
    }
  }
  return { message: 'Something went wrong. Please try again.', errors: null }
}

/**
 * Maps top-level Laravel validation errors onto react-hook-form fields.
 * Dot-notation keys (e.g. "items.0.product_id") don't map to a single
 * registered field, so they're returned unmapped for the caller to show
 * as a banner instead.
 */
export function applyFieldErrors<T extends FieldValues>(
  errors: Record<string, string[]>,
  setError: UseFormSetError<T>,
): string[] {
  const unmapped: string[] = []

  for (const [field, messages] of Object.entries(errors)) {
    if (field.includes('.')) {
      unmapped.push(...messages)
      continue
    }
    setError(field as Path<T>, { type: 'server', message: messages[0] })
  }

  return unmapped
}
