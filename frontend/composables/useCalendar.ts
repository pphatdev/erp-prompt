import { useApi } from '~/composables/useApi'

export type CalendarEventSource = 'calendar' | 'holiday' | 'leave' | 'shift' | 'appointment'

export interface CalendarFeedEvent {
    id: string
    source: CalendarEventSource
    category: string | null
    title: string
    description: string | null
    startTime: string | null
    endTime: string | null
    isAllDay: boolean
    employeeId: string | null
    meta: Record<string, any>
}

export interface CombinedEventsResponse {
    data: CalendarFeedEvent[]
    meta: { from: string; to: string; count: number }
}

export interface CreateEventPayload {
    title: string
    description?: string | null
    start_time: string
    end_time: string
    category?: 'general' | 'meeting' | 'training' | 'company' | 'personal'
    is_all_day?: boolean
    employee_id?: string | null
}

export interface UpdateEventPayload extends Partial<CreateEventPayload> {}

const buildQuery = (q: Record<string, any>): string => {
    const params = new URLSearchParams()
    for (const [k, v] of Object.entries(q)) {
        if (v === undefined || v === null || v === '') continue
        if (Array.isArray(v)) v.forEach(item => params.append(`${k}[]`, String(item)))
        else params.set(k, String(v))
    }
    const qs = params.toString()
    return qs ? `?${qs}` : ''
}

export const useCalendar = () => {
    const api = useApi()

    const events = {
        list: (q: {
            from: string
            to: string
            categories?: CalendarEventSource[]
            employee_id?: string | null
            branch_id?: string | null
        }) => api.get<CombinedEventsResponse>(`calendar/events${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: CalendarFeedEvent }>(`calendar/events/${id}`),

        create: (body: CreateEventPayload) =>
            api.post<{ data: CalendarFeedEvent }>('calendar/events', body),

        update: (id: string, body: UpdateEventPayload) =>
            api.put<{ data: CalendarFeedEvent }>(`calendar/events/${id}`, body),

        destroy: (id: string) =>
            api.delete(`calendar/events/${id}`),
    }

    const sourceMeta = (
        source: CalendarEventSource,
    ): { label: string; icon: string; variant: 'primary' | 'secondary' | 'success' | 'warning' | 'danger' | 'info'; dotClass: string } => {
        switch (source) {
            case 'holiday':
                return { label: 'Holiday', icon: 'ti-confetti', variant: 'success', dotClass: 'bg-(--color-success)' }
            case 'leave':
                return { label: 'Leave', icon: 'ti-beach', variant: 'warning', dotClass: 'bg-(--color-warning)' }
            case 'shift':
                return { label: 'Shift', icon: 'ti-clock-hour-4', variant: 'info', dotClass: 'bg-(--color-info)' }
            case 'appointment':
                return { label: 'Meeting', icon: 'ti-users', variant: 'primary', dotClass: 'bg-(--color-primary)' }
            case 'calendar':
            default:
                return { label: 'Event', icon: 'ti-calendar-event', variant: 'secondary', dotClass: 'bg-(--text-muted)' }
        }
    }

    return { events, sourceMeta }
}
