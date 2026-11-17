import { PageHeader } from '@/components/layout/PageHeader'
import { LedgerViewTabs } from '@/components/accounting/LedgerViewTabs'
import { CenterReport } from '@/pages/reports-center/CenterReportPage'
import { GENERAL_LEDGER } from '@/pages/reports-center/catalog'

/**
 * Accounting > General Ledger: every transaction as one line — its number, the
 * account debited and the account credited, the amounts — opening to all its
 * lines, as in IntelliCare. Same report as Reports Center > General Ledger.
 */
export function GeneralLedgerJournalPage() {
  return (
    <div>
      <PageHeader parent="Accounting Reports" title="General Ledger" />
      <LedgerViewTabs />
      <CenterReport entry={GENERAL_LEDGER} />
    </div>
  )
}
