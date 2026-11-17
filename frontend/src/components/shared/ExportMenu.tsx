import { useState } from 'react'
import { toast } from 'sonner'
import { ChevronDown, Download, FileSpreadsheet, FileText, Sheet } from 'lucide-react'

import { downloadFile } from '@/lib/pdf-download'
import { getApiErrorInfo } from '@/lib/api-errors'
import { Button } from '@/components/ui/button'
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu'

type Format = 'csv' | 'xlsx' | 'pdf'

const FORMATS: { format: Format; label: string; mime: string; icon: typeof FileText }[] = [
  { format: 'xlsx', label: 'Excel (.xlsx)', mime: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', icon: FileSpreadsheet },
  { format: 'csv', label: 'CSV (.csv)', mime: 'text/csv', icon: Sheet },
  { format: 'pdf', label: 'PDF (.pdf)', mime: 'application/pdf', icon: FileText },
]

interface Props {
  /** The report's export endpoint, e.g. "/reports/profit-and-loss/export". */
  path: string
  /** Exactly the filters the page uses to fetch the report, so the file matches the screen. */
  params: Record<string, string | number | null | undefined>
  /** File name without extension, e.g. "profit-and-loss-2026-07-01-to-2026-11-17". */
  fileName: string
  disabled?: boolean
}

/**
 * Export button for a report page: downloads the same report, with the same
 * filters, as Excel, CSV or PDF. The server builds every format from the data
 * the screen shows, so totals always agree.
 */
export function ExportMenu({ path, params, fileName, disabled }: Props) {
  const [busy, setBusy] = useState<Format | null>(null)

  const download = async (format: Format, mime: string) => {
    setBusy(format)
    try {
      const clean = Object.fromEntries(Object.entries(params).filter(([, value]) => value !== undefined && value !== null && value !== ''))
      await downloadFile(path, `${fileName}.${format}`, mime, { ...clean, format })
    } catch (error) {
      toast.error(getApiErrorInfo(error).message || 'Could not export this report.')
    } finally {
      setBusy(null)
    }
  }

  return (
    <DropdownMenu>
      <DropdownMenuTrigger asChild>
        <Button disabled={disabled || busy !== null}>
          <Download className="h-4 w-4" />
          {busy ? 'Exporting…' : 'Export'}
          <ChevronDown className="h-3.5 w-3.5 opacity-70" />
        </Button>
      </DropdownMenuTrigger>
      <DropdownMenuContent align="end">
        {FORMATS.map(({ format, label, mime, icon: Icon }) => (
          <DropdownMenuItem key={format} onSelect={() => download(format, mime)}>
            <Icon className="h-4 w-4" />
            {label}
          </DropdownMenuItem>
        ))}
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
