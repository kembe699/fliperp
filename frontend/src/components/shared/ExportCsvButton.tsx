import { useState } from 'react'
import { Download } from 'lucide-react'
import { toast } from 'sonner'

import { Button } from '@/components/ui/button'

export function ExportCsvButton({ onExport }: { onExport: () => Promise<void> }) {
  const [loading, setLoading] = useState(false)

  const handleClick = async () => {
    setLoading(true)
    try {
      await onExport()
    } catch {
      toast.error('Could not export CSV. Please try again.')
    } finally {
      setLoading(false)
    }
  }

  return (
    <Button variant="outline" disabled={loading} onClick={handleClick}>
      <Download className="h-4 w-4" />
      {loading ? 'Exporting…' : 'Export CSV'}
    </Button>
  )
}
