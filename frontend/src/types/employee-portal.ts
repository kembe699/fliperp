import type { Attendance, AttendanceStatus, Employee, LeaveRequest, LeaveRequestStatus } from '@/types/hr'

export interface PortalUser {
  id: number
  name: string
  email: string
  roles: string[]
}

export type PortalEmployee = Employee

export interface PortalMe {
  employee: PortalEmployee
  today_attendance: Attendance | null
  recent_attendance: Attendance[]
  leave_balance: {
    total_entitled_days: number
    total_taken_days: number
    total_remaining_days: number
  }
}

export interface LeaveSummary {
  year: number
  total_entitled_days: number
  total_taken_days: number
  total_remaining_days: number
  last_approved_leave_date: string | null
  by_leave_type: {
    leave_type_id: number
    leave_type_name: string
    entitled_days: number
    taken_days: number
    remaining_days: number
  }[]
}

export type { Attendance, AttendanceStatus, LeaveRequest, LeaveRequestStatus }
