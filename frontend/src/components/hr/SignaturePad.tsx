import { forwardRef, useEffect, useImperativeHandle, useRef } from 'react'
import SignaturePadLib from 'signature_pad'
import { Eraser } from 'lucide-react'

import { Button } from '@/components/ui/button'

export interface SignaturePadHandle {
  /** Returns a base64 PNG data URI, or null if nothing has been drawn. */
  toDataUrl: () => string | null
  clear: () => void
}

/**
 * Thin wrapper around signature_pad (canvas-based, mouse + touch) sized to
 * fill its container and re-sized on resize so strokes stay crisp on HiDPI
 * screens.
 */
export const SignaturePad = forwardRef<SignaturePadHandle, { className?: string }>(function SignaturePad(
  { className },
  ref,
) {
  const canvasRef = useRef<HTMLCanvasElement>(null)
  const padRef = useRef<SignaturePadLib | null>(null)
  const isEmptyRef = useRef(true)

  useEffect(() => {
    const canvas = canvasRef.current
    if (!canvas) return

    const pad = new SignaturePadLib(canvas, { backgroundColor: 'rgba(255,255,255,1)', penColor: '#111827' })
    padRef.current = pad
    pad.addEventListener('endStroke', () => {
      isEmptyRef.current = pad.isEmpty()
    })

    const resize = () => {
      const ratio = Math.max(window.devicePixelRatio || 1, 1)
      const { width, height } = canvas.getBoundingClientRect()
      canvas.width = width * ratio
      canvas.height = height * ratio
      canvas.getContext('2d')?.scale(ratio, ratio)
      pad.clear()
      isEmptyRef.current = true
    }

    resize()
    window.addEventListener('resize', resize)
    return () => {
      window.removeEventListener('resize', resize)
      pad.off()
    }
  }, [])

  useImperativeHandle(ref, () => ({
    toDataUrl: () => (padRef.current && !padRef.current.isEmpty() ? padRef.current.toDataURL('image/png') : null),
    clear: () => {
      padRef.current?.clear()
      isEmptyRef.current = true
    },
  }))

  return (
    <div className={className}>
      <div className="relative h-40 w-full overflow-hidden rounded-lg border border-input bg-white">
        <canvas ref={canvasRef} className="absolute inset-0 h-full w-full touch-none" />
      </div>
      <div className="mt-2 flex items-center justify-between">
        <p className="text-xs text-muted-foreground">Draw your signature above using your mouse or finger.</p>
        <Button type="button" variant="outline" size="sm" onClick={() => padRef.current?.clear()}>
          <Eraser className="h-3.5 w-3.5" />
          Clear
        </Button>
      </div>
    </div>
  )
})
