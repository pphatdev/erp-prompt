import { useApi } from '~/composables/useApi'
import type {
    Project,
    ProjectStatus,
    Task,
    Timesheet,
    ProjectsKpis,
    ProjectBudgetStatus,
    CreateProjectPayload,
    UpdateProjectPayload,
    CreateTaskPayload,
    UpdateTaskPayload,
    CreateTimesheetPayload,
    UpdateTimesheetPayload,
    ProjectsListQuery,
    TasksListQuery,
    TimesheetsListQuery,
    TaskStatus,
} from '~/types/projects'

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

export const useProjects = () => {
    const api = useApi()

    const projects = {
        list: (q: ProjectsListQuery = {}) =>
            api.get<PaginatedResponse<Project>>(`projects${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: Project }>(`projects/${id}`),

        create: (body: CreateProjectPayload) =>
            api.post<{ data: Project }>('projects', body),

        update: (id: string, body: UpdateProjectPayload) =>
            api.put<{ data: Project }>(`projects/${id}`, body),

        destroy: (id: string) =>
            api.delete(`projects/${id}`),

        kpis: () =>
            api.get<{ data: ProjectsKpis }>('projects/kpis'),

        budgetStatus: (id: string) =>
            api.get<{ data: ProjectBudgetStatus }>(`projects/${id}/budget-status`),
    }

    const tasks = {
        list: (q: TasksListQuery = {}) =>
            api.get<PaginatedResponse<Task>>(`tasks${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: Task }>(`tasks/${id}`),

        create: (body: CreateTaskPayload) =>
            api.post<{ data: Task }>('tasks', body),

        update: (id: string, body: UpdateTaskPayload) =>
            api.put<{ data: Task }>(`tasks/${id}`, body),

        destroy: (id: string) =>
            api.delete(`tasks/${id}`),

        updateStatus: (id: string, status: TaskStatus) =>
            api.patch<{ data: Task }>(`tasks/${id}/status`, { status }),
    }

    const timesheets = {
        list: (q: TimesheetsListQuery = {}) =>
            api.get<PaginatedResponse<Timesheet>>(`timesheets${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: Timesheet }>(`timesheets/${id}`),

        create: (body: CreateTimesheetPayload) =>
            api.post<{ data: Timesheet }>('timesheets', body),

        update: (id: string, body: UpdateTimesheetPayload) =>
            api.put<{ data: Timesheet }>(`timesheets/${id}`, body),

        destroy: (id: string) =>
            api.delete(`timesheets/${id}`),
    }

    return { projects, tasks, timesheets }
}

export const PROJECT_STATUSES: { value: ProjectStatus; label: string; badge: string }[] = [
    { value: 'planning',  label: 'Planning',  badge: 'badge-soft-info' },
    { value: 'active',    label: 'Active',    badge: 'badge-soft-success' },
    { value: 'on_hold',   label: 'On Hold',   badge: 'badge-soft-warning' },
    { value: 'completed', label: 'Completed', badge: 'badge-soft-secondary' },
]

export const TASK_PRIORITIES = [
    { value: 'low',    label: 'Low',    badge: 'badge-soft-secondary' },
    { value: 'medium', label: 'Medium', badge: 'badge-soft-info' },
    { value: 'high',   label: 'High',   badge: 'badge-soft-warning' },
    { value: 'urgent', label: 'Urgent', badge: 'badge-soft-danger' },
] as const

export const TASK_STATUSES = [
    { value: 'todo',        label: 'To Do',       badge: 'badge-soft-secondary' },
    { value: 'in_progress', label: 'In Progress', badge: 'badge-soft-info' },
    { value: 'review',      label: 'Review',      badge: 'badge-soft-warning' },
    { value: 'done',        label: 'Done',        badge: 'badge-soft-success' },
] as const
