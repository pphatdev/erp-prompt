import { useApi } from '~/composables/useApi'
import type {
    Holiday,
    CreateHolidayPayload,
    UpdateHolidayPayload,
    CalendarFeed,
    PersonalCalendarFeed,
    HolidayListQuery,
} from '~/types/hrm-calendar'

interface PaginatedResponse<T> {
    data: T[]
    pagination?: { page: number; limit: number; total: number; totalPages: number }
}

const buildQuery = (q: Record<string, unknown> = {}): string => {
    const params = new URLSearchParams()
    for (const [k, v] of Object.entries(q)) {
        if (v === undefined || v === null || v === '') continue
        params.set(k, String(v))
    }
    const qs = params.toString()
    return qs ? `?${qs}` : ''
}

export const useHrmCalendar = () => {
    const api = useApi()

    const holidays = {
        list: (q: HolidayListQuery = {}) =>
            api.get<PaginatedResponse<Holiday>>(`holidays${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: Holiday }>(`holidays/${id}`),

        create: (body: CreateHolidayPayload) =>
            api.post<{ data: Holiday }>('holidays', body),

        update: (id: string, body: UpdateHolidayPayload) =>
            api.put<{ data: Holiday }>(`holidays/${id}`, body),

        destroy: (id: string) =>
            api.delete(`holidays/${id}`),
    }

    const calendar = {
        feed: (from: string, to: string) =>
            api.get<{ data: CalendarFeed }>(`hrm/calendar?from=${from}&to=${to}`),

        myFeed: (from: string, to: string) =>
            api.get<{ data: PersonalCalendarFeed }>(`me/calendar?from=${from}&to=${to}`),
    }

    return { holidays, calendar }
}

export const HOLIDAY_TYPES: { value: 'public' | 'company' | 'optional'; label: string; badge: string }[] = [
    { value: 'public',   label: 'Public',   badge: 'badge-soft-success' },
    { value: 'company',  label: 'Company',  badge: 'badge-soft-primary' },
    { value: 'optional', label: 'Optional', badge: 'badge-soft-info' },
]
