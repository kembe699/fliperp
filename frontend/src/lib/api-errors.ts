import axios from 'axios'
import type { FieldValues, Path, UseFormSetError } from 'react-hook-form'

export interface ApiErrorInfo {
  message: string
  errors: Record<string, string[]> | null
}

export function getApiErrorInfo(error: unknown): ApiErrorInfo {
  if (axios.isAxiosError(error)) {
    const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined
    const errors = data?.errors ?? null

    // The backend's top-level `message` for a 422 is always the generic "The given data
    // was invalid." (see bootstrap/app.php's ValidationException renderer) — both real
    // field-validation failures AND business-rule messages (e.g. "cannot delete a role
    // that users currently hold", thrown via ValidationException::withMessages()) land in
    // `errors` instead, which is the only place the actual reason lives. Prefer that
    // whenever it's present so the toast tells the user what actually went wrong, not a
    // one-size-fits-all phrase.
    const fieldMessages = errors ? Object.values(errors).flat() : []

    return {
      message: fieldMessages.length > 0 ? fieldMessages.join(' ') : (data?.message ?? 'Something went wrong. Please try again.'),
      errors,
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
