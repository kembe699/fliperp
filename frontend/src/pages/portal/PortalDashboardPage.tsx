import { useNavigate } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Clock, LogIn, LogOut } from 'lucide-react'

import { fetchPortalMe } from '@/api/employee-portal'
import { EmployeeAvatar } from '@/components/hr/EmployeeAvatar'
import { PortalStatCard } from '@/components/portal/PortalStatCard'
import { Button } from '@/components/ui/button'

export function PortalDashboardPage() {
  const navigate = useNavigate()

  const { data: me, isLoading } = useQuery({ queryKey: ['portal-me'], queryFn: fetchPortalMe })

  if (isLoading || !me) {
    return <p className="py-10 text-center text-sm text-muted-foreground">Loading…</p>
  }

  const { employee, today_attendance: today, leave_balance: leaveBalance } = me
  const hasClockedIn = !!today?.clock_in
  const hasClockedOut = !!today?.clock_out

  return (
    <div className="space-y-6">
      <div className="flex flex-col items-center gap-3 py-4 text-center">
        <EmployeeAvatar src={employee.photo_url} name={`${employee.first_name} ${employee.last_name}`} className="h-20 w-20 text-2xl" />
        <div>
          <p className="text-lg font-semibold text-foreground">
            {employee.first_name} {employee.last_name}
          </p>
          <p className="text-sm text-muted-foreground">{employee.employee_code}</p>
        </div>
      </div>

      <div className="rounded-2xl border border-border bg-white p-5 text-center">
        <p className="mb-1 flex items-center justify-center gap-1.5 text-xs font-medium uppercase tracking-wide text-muted-foreground">
          <Clock className="h-3.5 w-3.5" />
          Today
        </p>
        {!hasClockedIn && <p className="text-sm text-muted-foreground">You haven't clocked in yet.</p>}
        {hasClockedIn && !hasClockedOut && <p className="text-sm text-foreground">Clocked in at {today?.clock_in}</p>}
        {hasClockedIn && hasClockedOut && (
          <p className="text-sm text-foreground">
            Clocked in {today?.clock_in} · Clocked out {today?.clock_out}
          </p>
        )}

        <Button
          size="lg"
          disabled={hasClockedIn && hasClockedOut}
          onClick={() => navigate('/employee-portal/clock')}
          className="mt-4 h-14 w-full text-base"
        >
          {!hasClockedIn ? <LogIn className="h-5 w-5" /> : !hasClockedOut ? <LogOut className="h-5 w-5" /> : null}
          {!hasClockedIn ? 'Clock In' : !hasClockedOut ? 'Clock Out' : 'Done for Today'}
        </Button>
      </div>

      <div className="grid grid-cols-3 gap-3">
        <PortalStatCard label="Entitled" value={leaveBalance.total_entitled_days} />
        <PortalStatCard label="Taken" value={leaveBalance.total_taken_days} />
        <PortalStatCard label="Remaining" value={leaveBalance.total_remaining_days} />
      </div>
    </div>
  )
}
