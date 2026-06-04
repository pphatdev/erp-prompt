import { useApi } from '~/composables/useApi'

export type WorkScheduleTargetType = 'global' | 'department' | 'employee'

export interface WorkScheduleInterval {
    start: string // 'HH:MM'
    end: string
}

export interface WorkScheduleRow {
    id?: string | null
    dayOfWeek: number // 1..7 (Mon..Sun)
    isWorkDay: boolean
    intervals: WorkScheduleInterval[]
}

export interface WorkScheduleSnapshotResponse {
    data: WorkScheduleRow[]
    meta: { targetType: WorkScheduleTargetType; targetId: string | null }
}

export interface WorkSchedulePayload {
    targetType: WorkScheduleTargetType
    targetId?: string | null
    days: WorkScheduleRow[]
}

/**
 * Thin client for /work-schedules. Snapshot returns all 7 days (missing
 * days synthesised as off-placeholders); upsertWeek replaces the entire
 * week atomically; clearOverrides drops every row for a target so it
 * falls back to its parent layer.
 */
export const useWorkSchedules = () => {
    const api = useApi()

    const snapshot = (targetType: WorkScheduleTargetType, targetId?: string | null) => {
        const qs = new URLSearchParams({ targetType })
        if (targetType !== 'global' && targetId) qs.set('targetId', targetId)
        return api.get<WorkScheduleSnapshotResponse>(`work-schedules/snapshot?${qs.toString()}`)
    }

    const upsertWeek = (payload: WorkSchedulePayload) =>
        api.put<{ data: WorkScheduleRow[] }>('work-schedules', payload)

    const clearOverrides = (targetType: 'department' | 'employee', targetId: string) =>
        api.delete<{ message: string; deleted: number }>(
            `work-schedules?targetType=${targetType}&targetId=${encodeURIComponent(targetId)}`
        )

    return { snapshot, upsertWeek, clearOverrides }
}
