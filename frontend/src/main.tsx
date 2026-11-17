import { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import { QueryClientProvider } from '@tanstack/react-query'
import { Toaster } from 'sonner'

import '@fontsource/inter/400.css'
import '@fontsource/inter/500.css'
import '@fontsource/inter/600.css'
import '@fontsource/inter/700.css'
import './index.css'

import { queryClient } from '@/lib/query-client'
import App from './App.tsx'

/**
 * The splash in index.html (official FlipERP loader) stays up for at least
 * SPLASH_MIN_MS from the start of the page load, then fades out. Counted from
 * navigation start, so a slow connection doesn't add the 3 s on top.
 */
const SPLASH_MIN_MS = 3000

function hideSplash() {
  const splash = document.getElementById('flip-splash')
  if (!splash) return
  const wait = Math.max(0, SPLASH_MIN_MS - performance.now())
  window.setTimeout(() => {
    splash.classList.add('is-hidden')
    window.setTimeout(() => splash.remove(), 400)
  }, wait)
}

createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <QueryClientProvider client={queryClient}>
      <App />
      <Toaster position="top-right" richColors closeButton />
    </QueryClientProvider>
  </StrictMode>,
)

hideSplash()
