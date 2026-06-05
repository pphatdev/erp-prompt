import { useApi } from '~/composables/useApi'

export type ChecklistStatus = 'pending' | 'in_progress' | 'completed' | 'cancelled'
export type OnboardingTaskStatus = 'pending' | 'in_progress' | 'completed' | 'skipped'
export type OnboardingOwnerRole = 'hr' | 'it' | 'finance' | 'manager' | 'facilities' | 'other'

export interface OnboardingTask {
    id: string
    checklistId: string
    title: string
    description: string | null
    ownerRole: OnboardingOwnerRole
    ownerUserId: string | null
    dueOffsetDays: number
    dueDate: string | null
    status: OnboardingTaskStatus
    sortOrder: number
    completedAt: string | null
    completedByUserId: string | null
    completionNotes: string | null
    createdAt: string | null
    updatedAt: string | null
}

export interface OnboardingChecklistOfferLite {
    id: string
    referenceNumber?: string | null
    title?: string | null
    status?: string | null
}

export interface OnboardingChecklistEmployeeLite {
    id: string
    employeeCode?: string | null
    fullName?: string | null
}

export interface OnboardingChecklist {
    id: string
    offerId: string
    employeeId: string | null
    name: string
    status: ChecklistStatus
    totalTasks: number
    completedTasks: number
    progressPercent: number
    targetCompletionDate: string | null
    completedAt: string | null
    offer?: OnboardingChecklistOfferLite | null
    employee?: OnboardingChecklistEmployeeLite | null
    tasks?: OnboardingTask[]
    createdAt: string | null
    updatedAt: string | null
}

export interface ChecklistListQuery {
    page?: number
    limit?: number
    status?: ChecklistStatus | ''
    employeeId?: string
}

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

export const useOnboarding = () => {
    const api = useApi()

    return {
        listChecklists: (q: ChecklistListQuery = {}) =>
            api.get<PaginatedResponse<OnboardingChecklist>>(`onboarding-checklists${buildQuery(q as Record<string, unknown>)}`),

        showChecklist: (id: string) =>
            api.get<{ data: OnboardingChecklist }>(`onboarding-checklists/${id}`),

        transitionTask: (id: string, status: OnboardingTaskStatus, notes?: string | null) =>
            api.patch<{ data: OnboardingTask }>(`onboarding-tasks/${id}/status`, {
                status,
                ...(notes ? { notes } : {}),
            }),
    }
}

export const OWNER_ROLE_META: Record<OnboardingOwnerRole, { label: string; icon: string; tint: string }> = {
    hr:         { label: 'HR',         icon: 'ti-user-shield',    tint: 'badge-soft-primary' },
    it:         { label: 'IT',         icon: 'ti-device-laptop',  tint: 'badge-soft-info' },
    finance:    { label: 'Finance',    icon: 'ti-cash',           tint: 'badge-soft-success' },
    manager:    { label: 'Manager',    icon: 'ti-user-star',      tint: 'badge-soft-warning' },
    facilities: { label: 'Facilities', icon: 'ti-building',       tint: 'badge-soft-secondary' },
    other:      { label: 'Other',      icon: 'ti-circle-dot',     tint: 'badge-soft-secondary' },
}

export const TASK_STATUS_META: Record<OnboardingTaskStatus, { label: string; variant: 'primary' | 'secondary' | 'success' | 'warning' | 'danger' | 'info' }> = {
    pending:     { label: 'Pending',     variant: 'secondary' },
    in_progress: { label: 'In progress', variant: 'info' },
    completed:   { label: 'Completed',   variant: 'success' },
    skipped:     { label: 'Skipped',     variant: 'warning' },
}

export const CHECKLIST_STATUS_META: Record<ChecklistStatus, { label: string; variant: 'primary' | 'secondary' | 'success' | 'warning' | 'danger' | 'info' }> = {
    pending:     { label: 'Pending',     variant: 'secondary' },
    in_progress: { label: 'In progress', variant: 'info' },
    completed:   { label: 'Completed',   variant: 'success' },
    cancelled:   { label: 'Cancelled',   variant: 'danger' },
}
