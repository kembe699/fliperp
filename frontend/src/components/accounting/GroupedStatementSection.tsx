import { formatCurrency } from '@/lib/currency'
import type { ReportAccountLine, StatementGroup, StatementSection } from '@/types/accounting'

/**
 * One section of a not-for-profit statement (Income, Expenditure, Assets,
 * Net Assets / Funds …): the chart's headings in order, each with its
 * accounts, its sub-headings and a subtotal, then the section total.
 * `extraRows` are added at the end of the section, before its total (the
 * computed surplus line under Net Assets / Funds).
 */
export function GroupedStatementSection({
  section,
  currencyCode,
  total,
  extraRows = [],
}: {
  section: StatementSection
  currencyCode?: string
  /** Overrides the section's own total (Net Assets / Funds including the surplus line). */
  total?: number
  extraRows?: { label: string; amount: number }[]
}) {
  return (
    <div className="mb-6">
      <p className="mb-2 text-sm font-semibold uppercase tracking-wide text-foreground">{section.label}</p>
      {section.groups.length === 0 && extraRows.length === 0 && <p className="py-2 text-sm text-muted-foreground">No activity.</p>}
      {section.groups.map((group) => (
        <Group key={`${group.code}-${group.name}`} group={group} depth={0} currencyCode={currencyCode} />
      ))}
      {extraRows.length > 0 && (
        <table className="w-full text-sm">
          <tbody>
            {extraRows.map((row) => (
              <tr key={row.label} className="border-b border-border last:border-b-0">
                <td className="w-24 py-1.5 text-muted-foreground">—</td>
                <td className="py-1.5 text-foreground">{row.label}</td>
                <td className="py-1.5 text-right text-foreground">{formatCurrency(row.amount, currencyCode)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      <div className="flex justify-between border-t border-border pt-1.5 text-sm font-semibold text-foreground">
        <span>{section.total_label}</span>
        <span>{formatCurrency(total ?? section.total, currencyCode)}</span>
      </div>
    </div>
  )
}

function Group({ group, depth, currencyCode }: { group: StatementGroup; depth: number; currencyCode?: string }) {
  const indent = { paddingLeft: `${depth * 1.25}rem` }
  const empty = group.accounts.length === 0 && group.groups.length === 0

  return (
    <div className={depth === 0 ? 'mb-3' : 'mt-1'}>
      <div className="flex justify-between py-1 text-sm" style={indent}>
        <span className={depth === 0 ? 'font-semibold text-foreground' : 'font-medium text-foreground'}>
          {group.code && <span className="mr-2 text-muted-foreground">{group.code}</span>}
          {group.name}
        </span>
        {empty && <span className="text-muted-foreground">{formatCurrency(group.total, currencyCode)}</span>}
      </div>
      {group.accounts.length > 0 && (
        <table className="w-full text-sm">
          <tbody>
            {group.accounts.map((account: ReportAccountLine) => (
              <tr key={account.account_id} className="border-b border-border last:border-b-0">
                <td className="w-24 py-1.5 text-muted-foreground" style={{ paddingLeft: `${(depth + 1) * 1.25}rem` }}>
                  {account.code}
                </td>
                <td className="py-1.5 text-foreground">{account.name}</td>
                <td className="py-1.5 text-right text-foreground">{formatCurrency(account.amount, currencyCode)}</td>
              </tr>
            ))}
          </tbody>
        </table>
      )}
      {group.groups.map((sub) => (
        <Group key={`${sub.code}-${sub.name}`} group={sub} depth={depth + 1} currencyCode={currencyCode} />
      ))}
      {!empty && (
        <div className="flex justify-between border-t border-dashed border-border py-1 text-sm font-medium text-foreground" style={indent}>
          <span>Total {group.name}</span>
          <span>{formatCurrency(group.total, currencyCode)}</span>
        </div>
      )}
    </div>
  )
}
