export type HolidayType = 'public' | 'company' | 'optional'

export interface Holiday {
    id: string
    name: string
    date: string
    type: HolidayType
    isRecurring: boolean
    notes: string | null
    createdAt: string | null
    updatedAt: string | null
}

export interface CreateHolidayPayload {
    name: string
    date: string
    type?: HolidayType
    is_recurring?: boolean
    notes?: string | null
}

export type UpdateHolidayPayload = Partial<CreateHolidayPayload>

/** Single expanded occurrence of a holiday (recurring entries expand by year). */
export interface CalendarHoliday {
    date: string
    id: string
    name: string
    type: HolidayType
    isRecurring: boolean
    notes: string | null
}

export interface CalendarLeave {
    id: string
    employeeId: string
    employeeName: string | null
    leaveTypeId: string | null
    leaveTypeName: string | null
    startDate: string | null
    endDate: string | null
    status: string
}

export interface CalendarFeed {
    from: string
    to: string
    holidays: CalendarHoliday[]
    leaves: CalendarLeave[]
}

export interface PersonalLeave {
    id: string
    leaveTypeId: string | null
    leaveTypeName: string | null
    startDate: string | null
    endDate: string | null
    status: string
    reason: string | null
}

export interface PersonalCalendarFeed {
    from: string
    to: string
    holidays: CalendarHoliday[]
    personalLeaves: PersonalLeave[]
}

export interface HolidayListQuery {
    page?: number
    limit?: number
    type?: HolidayType | string
    search?: string
    from?: string
    to?: string
    recurring_only?: boolean | string
}
