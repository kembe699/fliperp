import { api } from '@/lib/api'

/**
 * Fetches a file from an authenticated endpoint as a blob (a plain <a href> can't
 * carry the bearer token) and saves it to disk via a temporary anchor click —
 * the same trigger pattern used for report downloads in api/reports.ts.
 */
export async function downloadFile(path: string, filename: string, mimeType: string, params: Record<string, unknown> = {}): Promise<void> {
  const { data } = await api.get<Blob>(path, {
    params,
    responseType: 'blob',
  })

  const blob = new Blob([data], { type: mimeType })
  const url = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = url
  link.download = filename
  document.body.appendChild(link)
  link.click()
  link.remove()
  URL.revokeObjectURL(url)
}

export async function downloadPdf(path: string, filename: string): Promise<void> {
  return downloadFile(path, filename, 'application/pdf', { download: 1 })
}

export async function downloadIcs(path: string, filename: string): Promise<void> {
  return downloadFile(path, filename, 'text/calendar')
}
