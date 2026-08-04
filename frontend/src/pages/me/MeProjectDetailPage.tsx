import { useState } from 'react'
import { useParams } from 'react-router-dom'
import { useQuery } from '@tanstack/react-query'
import { Plus } from 'lucide-react'

import { fetchMeActivities, fetchMeIndicators, fetchMeProject, fetchMeProjectDashboard } from '@/api/me'
import { fetchEmployees } from '@/api/hr'
import { formatDate } from '@/lib/format'
import { usePermissions } from '@/hooks/use-permissions'
import type { MeActivityStatus, MeIndicator } from '@/types/me'

import { PageHeader } from '@/components/layout/PageHeader'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { MeIndicatorFormDialog } from '@/components/me/MeIndicatorFormDialog'
import { MeActivityFormDialog } from '@/components/me/MeActivityFormDialog'
import { RecordResultDialog } from '@/components/me/RecordResultDialog'

const ACTIVITY_STATUS_VARIANT: Record<MeActivityStatus, 'neutral' | 'info' | 'success' | 'danger'> = {
  not_started: 'neutral',
  in_progress: 'info',
  completed: 'success',
  delayed: 'danger',
}

export function MeProjectDetailPage() {
  const { id } = useParams<{ id: string }>()
  const projectId = Number(id)
  const { can } = usePermissions()

  const [indicatorFormOpen, setIndicatorFormOpen] = useState(false)
  const [activityFormOpen, setActivityFormOpen] = useState(false)
  const [recordResultIndicator, setRecordResultIndicator] = useState<MeIndicator | null>(null)

  const { data: project, isLoading } = useQuery({ queryKey: ['me-project', projectId], queryFn: () => fetchMeProject(projectId), enabled: !!projectId })
  const { data: dashboard } = useQuery({ queryKey: ['me-project-dashboard', projectId], queryFn: () => fetchMeProjectDashboard(projectId), enabled: !!projectId })
  const { data: indicators } = useQuery({ queryKey: ['me-indicators', projectId], queryFn: () => fetchMeIndicators(projectId), enabled: !!projectId })
  const { data: activities } = useQuery({ queryKey: ['me-activities', projectId], queryFn: () => fetchMeActivities(projectId), enabled: !!projectId })
  const { data: employees } = useQuery({ queryKey: ['employees-all'], queryFn: () => fetchEmployees({ per_page: 200 }) })

  if (isLoading || !project) {
    return <div className="p-6 text-sm text-muted-foreground">Loading project…</div>
  }

  return (
    <div>
      <PageHeader parent="M&E Projects" title={project.name} />

      {dashboard && (
        <div className="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
          <Card>
            <CardContent className="p-6">
              <p className="text-xs font-semibold uppercase text-muted-foreground">Status</p>
              <p className="mt-1 text-lg font-bold text-foreground capitalize">{dashboard.status}</p>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="p-6">
              <p className="text-xs font-semibold uppercase text-muted-foreground">Indicators</p>
              <p className="mt-1 text-lg font-bold text-primary">{dashboard.indicators.length}</p>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="p-6">
              <p className="text-xs font-semibold uppercase text-muted-foreground">Activities</p>
              <p className="mt-1 text-lg font-bold text-foreground">
                {dashboard.activities_summary.completed}/{dashboard.activities_summary.total}
              </p>
            </CardContent>
          </Card>
          <Card>
            <CardContent className="p-6">
              <p className="text-xs font-semibold uppercase text-muted-foreground">Completion</p>
              <p className="mt-1 text-lg font-bold text-primary">
                {dashboard.activities_summary.completion_percent !== null ? `${dashboard.activities_summary.completion_percent}%` : '—'}
              </p>
            </CardContent>
          </Card>
        </div>
      )}

      <div className="mb-6">
        <div className="mb-3 flex items-center justify-between">
          <p className="text-sm font-semibold text-foreground">Indicators</p>
          {can('me-indicators.create') && (
            <Button size="sm" onClick={() => setIndicatorFormOpen(true)}>
              <Plus className="h-4 w-4" />
              Add Indicator
            </Button>
          )}
        </div>
        <Card>
          <CardContent className="p-6">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                  <th className="py-2">Name</th>
                  <th className="py-2 text-right">Baseline</th>
                  <th className="py-2 text-right">Target</th>
                  <th className="py-2 text-right">Actual</th>
                  <th className="py-2 text-right">Progress</th>
                  <th className="py-2 text-right">Actions</th>
                </tr>
              </thead>
              <tbody>
                {dashboard?.indicators.map((progress) => {
                  const indicator = indicators?.find((i) => i.id === progress.indicator_id) ?? null
                  return (
                    <tr key={progress.indicator_id} className="border-b border-border last:border-b-0">
                      <td className="py-2 text-foreground">{progress.name}</td>
                      <td className="py-2 text-right text-foreground">
                        {progress.baseline_value} {progress.unit_of_measure ?? ''}
                      </td>
                      <td className="py-2 text-right text-foreground">
                        {progress.target_value} {progress.unit_of_measure ?? ''}
                      </td>
                      <td className="py-2 text-right text-foreground">
                        {progress.actual_value} {progress.unit_of_measure ?? ''}
                      </td>
                      <td className="py-2 text-right">
                        {progress.progress_percent !== null ? (
                          <span className={progress.progress_percent >= 100 ? 'font-medium text-success' : 'text-foreground'}>{progress.progress_percent}%</span>
                        ) : (
                          '—'
                        )}
                      </td>
                      <td className="py-2 text-right">
                        {can('me-results.create') && (
                          <Button variant="outline" size="sm" onClick={() => setRecordResultIndicator(indicator)}>
                            Record Result
                          </Button>
                        )}
                      </td>
                    </tr>
                  )
                })}
                {(!dashboard || dashboard.indicators.length === 0) && (
                  <tr>
                    <td colSpan={6} className="py-6 text-center text-muted-foreground">
                      No indicators defined for this project yet.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </CardContent>
        </Card>
      </div>

      <div>
        <div className="mb-3 flex items-center justify-between">
          <p className="text-sm font-semibold text-foreground">Activities</p>
          {can('me-activities.create') && (
            <Button size="sm" onClick={() => setActivityFormOpen(true)}>
              <Plus className="h-4 w-4" />
              Add Activity
            </Button>
          )}
        </div>
        <Card>
          <CardContent className="p-6">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-border text-left text-[11px] uppercase tracking-wide text-muted-foreground">
                  <th className="py-2">Name</th>
                  <th className="py-2">Start</th>
                  <th className="py-2">End</th>
                  <th className="py-2">Responsible</th>
                  <th className="py-2 text-right">Status</th>
                </tr>
              </thead>
              <tbody>
                {activities?.map((activity) => {
                  const employee = employees?.data.find((e) => e.id === activity.responsible_employee_id)
                  return (
                    <tr key={activity.id} className="border-b border-border last:border-b-0">
                      <td className="py-2 text-foreground">{activity.name}</td>
                      <td className="py-2 text-muted-foreground">{formatDate(activity.start_date)}</td>
                      <td className="py-2 text-muted-foreground">{activity.end_date ? formatDate(activity.end_date) : '—'}</td>
                      <td className="py-2 text-muted-foreground">{employee ? `${employee.first_name} ${employee.last_name}` : '—'}</td>
                      <td className="py-2 text-right">
                        <StatusBadge label={activity.status.replace('_', ' ')} variant={ACTIVITY_STATUS_VARIANT[activity.status]} />
                      </td>
                    </tr>
                  )
                })}
                {(!activities || activities.length === 0) && (
                  <tr>
                    <td colSpan={5} className="py-6 text-center text-muted-foreground">
                      No activities defined for this project yet.
                    </td>
                  </tr>
                )}
              </tbody>
            </table>
          </CardContent>
        </Card>
      </div>

      <MeIndicatorFormDialog open={indicatorFormOpen} onOpenChange={setIndicatorFormOpen} projectId={projectId} />
      <MeActivityFormDialog open={activityFormOpen} onOpenChange={setActivityFormOpen} projectId={projectId} />
      <RecordResultDialog open={!!recordResultIndicator} onOpenChange={(open) => !open && setRecordResultIndicator(null)} indicator={recordResultIndicator} projectId={projectId} />
    </div>
  )
}
