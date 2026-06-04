import { useApi } from '~/composables/useApi'

export type WorkflowColor = 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'secondary'

export interface WorkflowStatus {
    id: string
    module: string
    key: string
    label: string
    color: WorkflowColor | null
    icon: string | null
    sequence: number
    isInitial: boolean
    isTerminal: boolean
    allowedNext: string[]
    metadata: Record<string, unknown> | null
    createdAt: string | null
    updatedAt: string | null
}

export interface WorkflowStatusUpsertPayload {
    module: string
    key: string
    label: string
    color?: WorkflowColor | null
    icon?: string | null
    sequence?: number | null
    isInitial?: boolean
    isTerminal?: boolean
    allowedNext?: string[]
    metadata?: Record<string, unknown> | null
}

interface Paginated<T> {
    data: T[]
    pagination: { page: number; limit: number; total: number; totalPages: number }
}

/**
 * Translate the camelCase frontend payload into the snake_case shape the
 * FormRequest validates. Kept here so pages can stay in camelCase and the
 * composable owns the boundary translation.
 */
const toSnakePayload = (p: WorkflowStatusUpsertPayload): Record<string, unknown> => ({
    module:        p.module,
    key:           p.key,
    label:         p.label,
    color:         p.color ?? null,
    icon:          p.icon ?? null,
    sequence:      p.sequence ?? 0,
    is_initial:    p.isInitial ?? false,
    is_terminal:   p.isTerminal ?? false,
    allowed_next:  p.allowedNext ?? [],
    metadata:      p.metadata ?? null,
})

export const useWorkflowStatuses = () => {
    const api = useApi()

    const modules = () => api.get<{ data: string[] }>('workflow-statuses/modules')

    const list = (module?: string, limit = 200) => {
        const qs = new URLSearchParams({ limit: String(limit) })
        if (module) qs.set('module', module)
        return api.get<Paginated<WorkflowStatus>>(`workflow-statuses?${qs.toString()}`)
    }

    const create = (payload: WorkflowStatusUpsertPayload) =>
        api.post<{ data: WorkflowStatus }>('workflow-statuses', toSnakePayload(payload))

    const update = (id: string, payload: WorkflowStatusUpsertPayload) =>
        api.put<{ data: WorkflowStatus }>(`workflow-statuses/${id}`, toSnakePayload(payload))

    const remove = (id: string) =>
        api.delete<{ message: string }>(`workflow-statuses/${id}`)

    return { modules, list, create, update, remove }
}
