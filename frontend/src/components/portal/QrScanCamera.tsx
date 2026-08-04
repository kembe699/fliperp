import { useEffect, useRef, useState } from 'react'
import QrScanner from 'qr-scanner'
import { AlertTriangle } from 'lucide-react'

import { Button } from '@/components/ui/button'

interface QrScanCameraProps {
  onScan: (decodedText: string) => void
  onCancel: () => void
}

/**
 * Opens the device camera in-app and decodes a QR code from the live video
 * feed (via qr-scanner, which uses the browser's native BarcodeDetector
 * when available and falls back to a WASM decoder otherwise). Requires a
 * secure context (HTTPS, or localhost for dev) — getUserMedia is blocked
 * on plain HTTP origins other than localhost.
 */
export function QrScanCamera({ onScan, onCancel }: QrScanCameraProps) {
  const videoRef = useRef<HTMLVideoElement>(null)
  const scannerRef = useRef<QrScanner | null>(null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    const video = videoRef.current
    if (!video) return

    if (!window.isSecureContext) {
      setError('Camera access requires a secure (https://) connection.')
      return
    }

    const scanner = new QrScanner(
      video,
      (result) => {
        scanner.stop()
        onScan(result.data)
      },
      {
        preferredCamera: 'environment',
        highlightScanRegion: true,
        highlightCodeOutline: true,
      },
    )
    scannerRef.current = scanner

    scanner.start().catch(() => {
      setError('Could not access the camera. Please allow camera permission for this site and try again.')
    })

    return () => {
      scanner.stop()
      scanner.destroy()
      scannerRef.current = null
    }
  }, [onScan])

  if (error) {
    return (
      <div className="flex flex-col items-center gap-4 py-10 text-center">
        <AlertTriangle className="h-14 w-14 text-warning" />
        <p className="text-sm text-foreground">{error}</p>
        <Button variant="outline" onClick={onCancel}>
          Cancel
        </Button>
      </div>
    )
  }

  return (
    <div className="flex flex-col items-center gap-4">
      <div className="relative w-full max-w-sm overflow-hidden rounded-xl border border-border bg-black">
        {/* eslint-disable-next-line jsx-a11y/media-has-caption */}
        <video ref={videoRef} className="aspect-square w-full object-cover" muted playsInline />
      </div>
      <p className="text-xs text-muted-foreground">Point your camera at the QR code posted at your office.</p>
      <Button variant="outline" onClick={onCancel}>
        Cancel
      </Button>
    </div>
  )
}
