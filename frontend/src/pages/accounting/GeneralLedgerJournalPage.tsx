import { PageHeader } from '@/components/layout/PageHeader'
import { CenterReport } from '@/pages/reports-center/CenterReportPage'
import { findEntry, type CenterEntry } from '@/pages/reports-center/catalog'

/**
 * Accounting > General Ledger: every transaction as one line — its number, the
 * account debited and the account credited, the amounts — opening to all its
 * lines, as in IntelliCare. Same report as Reports Center > General Ledger.
 */
export function GeneralLedgerJournalPage() {
  const entry = findEntry('general-ledger') as CenterEntry

  return (
    <div>
      <PageHeader parent="Accounting Reports" title="General Ledger" />
      <CenterReport entry={entry} />
    </div>
  )
}
