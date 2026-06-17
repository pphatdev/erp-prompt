import { useApi } from '~/composables/useApi'

export type WorkflowColor = 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'secondary'

/**
 * Kanban-specific metadata persisted under
 * `workflow_statuses.metadata.kanban`. Drives column visibility,
 * card sort order, and which fields each card surfaces. Used today
 * by `hrm.application` (the candidate pipeline) but the shape is
 * generic enough that other module Kanbans can adopt it.
 */
export type KanbanSortKey =
    | 'newest'      // appliedAt desc
    | 'oldest'      // appliedAt asc
    | 'name_asc'    // applicantName a-z
    | 'name_desc'   // applicantName z-a
    | 'rating_desc' // derived rating high -> low

export type KanbanDisplayField =
    | 'candidateName'
    | 'candidateCode'
    | 'vacancyTitle'
    | 'rating'
    | 'expectedSalary'
    | 'appliedAt'
    | 'source'

export interface KanbanStageConfig {
    visible: boolean
    sort: KanbanSortKey
    displayFields: KanbanDisplayField[]
    conversionEligible: boolean
}

export interface WorkflowStatusMetadata {
    kanban?: Partial<KanbanStageConfig>
    [key: string]: unknown
}

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
    metadata: WorkflowStatusMetadata | null
    createdAt: string | null
    updatedAt: string | null
}

export interface WorkflowStatusUpsertPayload {
    module: string
    key?: string  // optional — backend auto-slugs from label when missing
    label: string
    color?: WorkflowColor | null
    icon?: string | null
    sequence?: number | null
    isInitial?: boolean
    isTerminal?: boolean
    allowedNext?: string[]
    metadata?: WorkflowStatusMetadata | null
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
const toSnakePayload = (p: WorkflowStatusUpsertPayload): Record<string, unknown> => {
    const out: Record<string, unknown> = {
        module:        p.module,
        label:         p.label,
        color:         p.color ?? null,
        icon:          p.icon ?? null,
        sequence:      p.sequence ?? 0,
        is_initial:    p.isInitial ?? false,
        is_terminal:   p.isTerminal ?? false,
        allowed_next:  p.allowedNext ?? [],
        metadata:      p.metadata ?? null,
    }
    // Only include `key` when the caller provided one. Letting the
    // backend slug from label is the Pipeline Settings UI's default
    // path; sending an empty string would trip the regex rule.
    if (p.key && p.key.length > 0) {
        out.key = p.key
    }
    return out
}

/**
 * Default Kanban config applied when a status has no metadata.kanban
 * block yet. Shared with the frontend stage editor so newly-created
 * rows land on the same baseline shape persisted rows use after edit.
 */
export const DEFAULT_KANBAN_CONFIG: KanbanStageConfig = {
    visible: true,
    sort: 'newest',
    displayFields: ['candidateName', 'vacancyTitle', 'rating', 'appliedAt', 'source'],
    conversionEligible: false,
}

/**
 * Merge a partial stored config with the default. Returns the full
 * shape so callers can render without nullish guards.
 */
export const resolveKanbanConfig = (
    metadata: WorkflowStatusMetadata | null | undefined
): KanbanStageConfig => {
    const stored = metadata?.kanban ?? {}
    return {
        visible:            stored.visible ?? DEFAULT_KANBAN_CONFIG.visible,
        sort:               (stored.sort as KanbanSortKey | undefined) ?? DEFAULT_KANBAN_CONFIG.sort,
        displayFields:      Array.isArray(stored.displayFields) && stored.displayFields.length > 0
                                ? (stored.displayFields as KanbanDisplayField[])
                                : DEFAULT_KANBAN_CONFIG.displayFields,
        conversionEligible: stored.conversionEligible ?? DEFAULT_KANBAN_CONFIG.conversionEligible,
    }
}

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

    const reorder = (module: string, orderedKeys: string[]) =>
        api.patch<{ message: string }>(
            'workflow-statuses/reorder',
            { module, orderedKeys }
        )

    const setDefault = (id: string) =>
        api.post<{ message: string; data: WorkflowStatus }>(
            `workflow-statuses/${id}/set-default`,
            {}
        )

    return { modules, list, create, update, remove, reorder, setDefault }
}
