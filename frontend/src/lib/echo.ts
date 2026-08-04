import Echo from 'laravel-echo'
import Pusher from 'pusher-js'

import { api } from '@/lib/api'

declare global {
  interface Window {
    Pusher: typeof Pusher
  }
}

window.Pusher = Pusher

let echoInstance: Echo<'reverb'> | null = null
let warnedMissingKey = false

export function initEcho(): Echo<'reverb'> | null {
  if (echoInstance) return echoInstance

  // Vite bakes VITE_* vars in at build time — if this environment (e.g. a
  // deploy target) never had them set when the frontend was built, the key
  // comes through as undefined and pusher-js throws synchronously from its
  // constructor. Degrade to "real-time features are off" instead of taking
  // the whole app down for every logged-in user.
  if (!import.meta.env.VITE_REVERB_APP_KEY) {
    if (!warnedMissingKey) {
      console.warn('VITE_REVERB_APP_KEY is not set — notifications and chat will not receive live updates.')
      warnedMissingKey = true
    }
    return null
  }

  echoInstance = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
    // Echo's built-in authorizer posts to /broadcasting/auth using its own
    // XHR call with cookie credentials — this app authenticates with a
    // Sanctum bearer token, not a session cookie, so that default request
    // never carries an Authorization header and every private/presence
    // channel subscription would silently fail with a 401. Routing the
    // auth request through the shared `api` axios instance instead reuses
    // its request interceptor (lib/api.ts), which already attaches the
    // current bearer token from the auth store on every request. Left
    // inline (rather than a standalone named function) so TypeScript
    // infers the exact callback signature Pusher expects, instead of
    // needing to import its non-exported internal auth types.
    authorizer: (channel) => ({
      authorize(socketId, callback) {
        api
          .post('/broadcasting/auth', { socket_id: socketId, channel_name: channel.name })
          .then((response) => callback(null, response.data))
          .catch((error) => callback(error, null))
      },
    }),
  })

  return echoInstance
}

export function disconnectEcho(): void {
  echoInstance?.disconnect()
  echoInstance = null
}

export function getEcho(): Echo<'reverb'> | null {
  return echoInstance
}
