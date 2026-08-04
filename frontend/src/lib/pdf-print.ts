import { api } from '@/lib/api'

/**
 * Fetches a PDF from an authenticated endpoint as a blob (same reasoning as
 * downloadPdf: a plain <a>/window.open URL can't carry the bearer token) and
 * silently prints it via a hidden iframe. A hidden iframe is used instead of
 * window.open() so popup blockers never intervene — the print call happens
 * once the browser's built-in PDF viewer finishes loading inside the iframe.
 */
export async function printPdf(path: string): Promise<void> {
  const { data } = await api.get<Blob>(path, { responseType: 'blob' })

  const blob = new Blob([data], { type: 'application/pdf' })
  const url = URL.createObjectURL(blob)

  const iframe = document.createElement('iframe')
  iframe.style.position = 'fixed'
  iframe.style.right = '0'
  iframe.style.bottom = '0'
  iframe.style.width = '0'
  iframe.style.height = '0'
  iframe.style.border = '0'
  iframe.src = url

  iframe.onload = () => {
    try {
      iframe.contentWindow?.focus()
      iframe.contentWindow?.print()
    } catch {
      // Some browsers restrict print() on cross-origin-like blob contexts;
      // the PDF still opened in the iframe so the user can print manually.
    }
  }

  document.body.appendChild(iframe)

  window.setTimeout(() => {
    iframe.remove()
    URL.revokeObjectURL(url)
  }, 60_000)
}
