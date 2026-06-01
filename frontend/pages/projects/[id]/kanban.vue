<template>
    <NuxtLayout name="default">
        <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
            <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
            <span class="text-xs text-(--text-muted)">Loading board...</span>
        </div>
        <div v-else-if="!project" class="glass-card rounded-2xl py-20 text-center">
            <i class="ti ti-layout-board text-4xl text-(--text-muted)" />
            <h4 class="text-sm font-semibold text-(--text-heading) mt-3">Project not found</h4>
            <NuxtLink to="/projects" class="text-xs underline text-(--color-primary) mt-2 inline-block">Back to projects</NuxtLink>
        </div>
        <div v-else class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <NuxtLink :to="`/projects/${project.id}`" class="text-(--text-muted) hover:text-(--text-heading) text-xs">
                            <i class="ti ti-arrow-left" /> {{ project.name }}
                        </NuxtLink>
                    </div>
                    <h1 class="text-xl font-semibold mt-1">Kanban Board</h1>
                </div>
                <div class="flex items-center gap-2">
                    <select v-model="priorityFilter" class="form-control text-xs">
                        <option value="">All priorities</option>
                        <option v-for="p in TASK_PRIORITIES" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                    <select v-model="assigneeFilter" class="form-control text-xs" :disabled="employeesLoading">
                        <option value="">All assignees</option>
                        <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.fullName }}</option>
                    </select>
                    <button v-if="canWrite" type="button" class="btn btn-primary text-xs" @click="openCreateModal">
                        <i class="ti ti-plus" />Task
                    </button>
                </div>
            </header>

            <section class="kanban-scroller -mx-2 px-2 pb-4 overflow-x-auto">
                <div class="flex gap-4 min-w-max">
                    <div v-for="col in columns" :key="col.status" class="kanban-column flex flex-col gap-3"
                        :class="{ 'kanban-column--dragover': dragOverColumn === col.status }"
                        @dragover.prevent="onColumnDragOver(col.status, $event)"
                        @dragleave="onColumnDragLeave(col.status)"
                        @drop="onColumnDrop(col.status)">
                        <header class="flex items-center justify-between px-1">
                            <span class="text-xxs font-bold uppercase tracking-wider px-2.5 py-1 rounded-full border"
                                :class="columnHeaderClass(col.status)">
                                {{ col.label }}
                                <span class="opacity-70">({{ col.tasks.length }})</span>
                            </span>
                            <span v-if="movingId && col.status === pendingDropStatus"
                                class="text-xxs text-(--text-muted) inline-flex items-center gap-1">
                                <span class="w-3 h-3 rounded-full border-2 border-(--color-primary)/30 border-t-(--color-primary) animate-spin" />
                                Moving...
                            </span>
                        </header>

                        <div class="kanban-list flex flex-col p-1 gap-3 pr-1">
                            <article v-for="t in col.tasks" :key="t.id"
                                class="kanban-card glass-card rounded-xl p-3 shadow-sm transition-all cursor-grab"
                                :class="{
                                    'kanban-card--dragging': draggingId === t.id,
                                    'ring-1 ring-(--color-danger)/40': isOverdue(t.dueDate)
                                }"
                                draggable="true"
                                @dragstart="onCardDragStart(t, $event)"
                                @dragend="onCardDragEnd"
                                @click="openEditModal(t)">
                                <header class="flex items-start justify-between gap-2 mb-2">
                                    <h4 class="text-xs font-semibold text-(--text-heading) line-clamp-2 flex-1">{{ t.title }}</h4>
                                    <span class="text-xxs px-1.5 py-0.5 rounded font-mono shrink-0"
                                        :class="priorityBadge(t.priority)">{{ t.priority }}</span>
                                </header>

                                <p v-if="t.description" class="text-xxs text-(--text-muted) line-clamp-2 mb-3">{{ t.description }}</p>

                                <footer class="flex items-center justify-between text-xxs text-(--text-muted)">
                                    <span class="inline-flex items-center gap-1 truncate max-w-[55%]"
                                        :title="t.assignee?.fullName || 'Unassigned'">
                                        <i class="ti ti-user text-[11px]" />
                                        <span class="truncate">{{ t.assignee?.fullName || 'Unassigned' }}</span>
                                    </span>
                                    <span v-if="t.dueDate" class="inline-flex items-center gap-1"
                                        :class="isOverdue(t.dueDate) ? 'text-(--color-danger)' : ''">
                                        <i :class="['ti text-[11px]', isOverdue(t.dueDate) ? 'ti-alert-triangle' : 'ti-clock']" />
                                        {{ formatDate(t.dueDate) }}
                                    </span>
                                </footer>
                            </article>

                            <template v-if="col.tasks.length === 0">
                                <button v-if="canWrite && col.status === 'todo'" type="button"
                                    class="kanban-cta glass-card rounded-xl p-4 w-full text-left transition-all"
                                    @click="openCreateModal">
                                    <span class="kanban-cta-icon">
                                        <i class="ti ti-plus text-base" />
                                    </span>
                                    <span class="block text-xs font-semibold text-(--text-heading) mt-3">
                                        New Task
                                    </span>
                                    <span class="block text-xxs text-(--text-muted) mt-1">
                                        Start the backlog for this project.
                                    </span>
                                </button>
                                <div v-else
                                    class="kanban-empty rounded-xl border border-dashed border-(--border-color) text-(--text-muted) text-xxs py-6 text-center">
                                    Drop here to move to <span class="font-semibold">{{ col.label }}</span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div v-if="showFormModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-2xl bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">{{ editingId ? 'Edit Task' : 'New Task' }}</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="post">
                    <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Title *</label>
                            <input v-model="form.title" type="text" required maxlength="255" class="form-control text-xs" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Description</label>
                            <textarea v-model="form.description" rows="3" class="form-control text-xs resize-none" />
                        </div>
                        <div class="grid grid-cols-3 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Status</label>
                                <select v-model="form.status" class="form-control text-xs">
                                    <option v-for="s in TASK_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Priority</label>
                                <select v-model="form.priority" class="form-control text-xs">
                                    <option v-for="p in TASK_PRIORITIES" :key="p.value" :value="p.value">{{ p.label }}</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Due Date</label>
                                <input v-model="form.due_date" type="date" class="form-control text-xs font-mono" />
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Assignee</label>
                            <select v-model="form.assignee_id" class="form-control text-xs" :disabled="employeesLoading">
                                <option :value="null">Unassigned</option>
                                <option v-for="e in employees" :key="e.id" :value="e.id">
                                    {{ e.fullName }}{{ e.employeeId ? ` (${e.employeeId})` : '' }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-between gap-2">
                        <button v-if="editingId && canDelete" type="button" class="btn btn-ghost text-xs text-(--color-danger)"
                            @click="onDelete">
                            <i class="ti ti-trash" /> Delete
                        </button>
                        <span v-else></span>
                        <div class="flex items-center gap-2">
                            <button type="button" class="btn btn-ghost text-xs" @click="showFormModal = false">Cancel</button>
                            <button type="submit" class="btn btn-primary text-xs" :disabled="!canSubmit || posting">
                                <i v-if="posting" class="ti ti-loader-2 animate-spin" />
                                {{ editingId ? 'Save' : 'Create' }}
                            </button>
                        </div>
                    </footer>
                </form>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '~/composables/useApi'
import { useProjects, TASK_STATUSES, TASK_PRIORITIES } from '~/composables/useProjects'
import { useToast } from '~/composables/useToast'
import { useAuthStore } from '~/stores/auth'
import type {
    Project,
    Task,
    TaskStatus,
    TaskPriority,
    CreateTaskPayload,
} from '~/types/projects'

definePageMeta({ breadcrumb: 'Kanban' })

interface EmployeeLite { id: string; employeeId: string | null; fullName: string }
interface Paginated<T> { data: T[]; pagination?: { page: number; limit: number; total: number; totalPages: number } }
interface KanbanColumn {
    status: TaskStatus
    label: string
    tasks: Task[]
}

const route = useRoute()
const api = useApi()
const pm = useProjects()
const toast = useToast()
const authStore = useAuthStore()

const projectId = computed(() => route.params.id as string)

const canWrite  = computed(() => authStore.hasPermission('projects.task.write'))
const canDelete = computed(() => authStore.hasPermission('projects.task.delete'))

const loading = ref(false)
const posting = ref(false)

const project = ref<Project | null>(null)
const tasks = ref<Task[]>([])
const priorityFilter = ref<'' | TaskPriority>('')
const assigneeFilter = ref<string>('')

const today = new Date().toISOString().slice(0, 10)
const formatDate = (s: string | null) => {
    if (!s) return ''
    const d = new Date(s)
    return isNaN(d.getTime()) ? s : d.toISOString().slice(0, 10)
}
const priorityBadge = (p: TaskPriority) =>
    TASK_PRIORITIES.find(x => x.value === p)?.badge ?? 'badge-soft-secondary'
const columnHeaderClass = (s: TaskStatus) => ({
    todo:        'badge-soft-secondary',
    in_progress: 'badge-soft-info',
    review:      'badge-soft-warning',
    done:        'badge-soft-success',
}[s] || 'badge-soft-secondary')
const isOverdue = (due: string | null) => {
    if (!due) return false
    return new Date(due).getTime() < new Date(today).getTime()
}

const loadProject = async () => {
    loading.value = true
    try {
        const res = await pm.projects.show(projectId.value)
        project.value = res.data
    } catch (err: any) {
        toast.error('Failed to load project', err?.data?.message)
        project.value = null
    } finally {
        loading.value = false
    }
}

const loadTasks = async () => {
    try {
        const res = await pm.tasks.list({
            project_id: projectId.value,
            limit: 500,
            priority: priorityFilter.value || undefined,
            assignee_id: assigneeFilter.value || undefined,
        })
        tasks.value = res.data
    } catch (err: any) {
        toast.error('Failed to load tasks', err?.data?.message)
    }
}

const columns = computed<KanbanColumn[]>(() => {
    const groups: Record<TaskStatus, Task[]> = { todo: [], in_progress: [], review: [], done: [] }
    for (const t of tasks.value) {
        if (groups[t.status]) groups[t.status].push(t)
    }
    return TASK_STATUSES.map(s => ({
        status: s.value as TaskStatus,
        label: s.label,
        tasks: groups[s.value as TaskStatus] ?? [],
    }))
})

watch([priorityFilter, assigneeFilter], () => loadTasks())

// ---- Drag and Drop --------------------------------------------------------

const draggingId = ref<string | null>(null)
const draggingFrom = ref<TaskStatus | null>(null)
const dragOverColumn = ref<TaskStatus | null>(null)
const movingId = ref<string | null>(null)
const pendingDropStatus = ref<TaskStatus | null>(null)

const onCardDragStart = (t: Task, ev: DragEvent) => {
    if (!canWrite.value) {
        ev.preventDefault()
        return
    }
    draggingId.value = t.id
    draggingFrom.value = t.status
    ev.dataTransfer?.setData('text/plain', t.id)
    if (ev.dataTransfer) ev.dataTransfer.effectAllowed = 'move'
}

const onCardDragEnd = () => {
    draggingId.value = null
    draggingFrom.value = null
    dragOverColumn.value = null
}

const canDropOn = (target: TaskStatus) => {
    if (!draggingFrom.value) return false
    if (target === draggingFrom.value) return false
    return true
}

const onColumnDragOver = (status: TaskStatus, ev: DragEvent) => {
    if (!canDropOn(status)) {
        if (ev.dataTransfer) ev.dataTransfer.dropEffect = 'none'
        return
    }
    if (ev.dataTransfer) ev.dataTransfer.dropEffect = 'move'
    dragOverColumn.value = status
}

const onColumnDragLeave = (status: TaskStatus) => {
    if (dragOverColumn.value === status) dragOverColumn.value = null
}

const onColumnDrop = async (status: TaskStatus) => {
    const id = draggingId.value
    const from = draggingFrom.value
    dragOverColumn.value = null
    draggingId.value = null
    draggingFrom.value = null
    if (!id || !from || from === status) return

    const idx = tasks.value.findIndex(t => t.id === id)
    if (idx === -1) return
    const original = tasks.value[idx].status
    tasks.value[idx].status = status
    movingId.value = id
    pendingDropStatus.value = status

    try {
        await pm.tasks.updateStatus(id, status)
        toast.success('Moved')
    } catch (err: any) {
        tasks.value[idx].status = original
        toast.error('Failed to move task', err?.data?.message)
    } finally {
        movingId.value = null
        pendingDropStatus.value = null
    }
}

// ---- Employees picker -----------------------------------------------------

const employees = ref<EmployeeLite[]>([])
const employeesLoading = ref(false)
const ensureEmployeesLoaded = async () => {
    if (employees.value.length || employeesLoading.value) return
    employeesLoading.value = true
    try {
        const res = await api.get<Paginated<EmployeeLite>>('/employees?limit=200')
        employees.value = res.data
    } catch (err: any) {
        toast.error('Failed to load employees', err?.data?.message)
    } finally {
        employeesLoading.value = false
    }
}

// ---- Task modal -----------------------------------------------------------

const showFormModal = ref(false)
const editingId = ref<string | null>(null)

const blankForm = (): CreateTaskPayload => ({
    project_id: projectId.value,
    title: '',
    description: null,
    due_date: null,
    status: 'todo',
    priority: 'medium',
    assignee_id: null,
})

const form = reactive<CreateTaskPayload>(blankForm())

const openCreateModal = () => {
    editingId.value = null
    Object.assign(form, blankForm())
    showFormModal.value = true
    ensureEmployeesLoaded()
}

const openEditModal = (t: Task) => {
    if (movingId.value === t.id) return
    editingId.value = t.id
    Object.assign(form, {
        project_id: t.projectId,
        title: t.title,
        description: t.description,
        due_date: t.dueDate,
        status: t.status,
        priority: t.priority,
        assignee_id: t.assigneeId,
    })
    showFormModal.value = true
    ensureEmployeesLoaded()
}

const canSubmit = computed(() => !!form.title.trim())

const post = async () => {
    if (!canSubmit.value) return
    posting.value = true
    try {
        const payload: CreateTaskPayload = {
            ...form,
            title: form.title.trim(),
            description: form.description?.toString().trim() || null,
        }
        if (editingId.value) {
            await pm.tasks.update(editingId.value, payload)
            toast.success('Task updated')
        } else {
            await pm.tasks.create(payload)
            toast.success('Task created')
        }
        showFormModal.value = false
        await loadTasks()
    } catch (err: any) {
        toast.error('Save failed', err?.data?.message)
    } finally {
        posting.value = false
    }
}

const onDelete = async () => {
    if (!editingId.value) return
    if (!confirm('Delete this task? Its timesheets will cascade.')) return
    posting.value = true
    try {
        await pm.tasks.destroy(editingId.value)
        toast.success('Task deleted')
        showFormModal.value = false
        await loadTasks()
    } catch (err: any) {
        toast.error('Delete failed', err?.data?.message)
    } finally {
        posting.value = false
    }
}

onMounted(async () => {
    await loadProject()
    if (project.value) {
        ensureEmployeesLoaded()
        await loadTasks()
    }
})
</script>

<style scoped>
.kanban-scroller {
    scrollbar-width: thin;
    scrollbar-color: var(--border-strong) transparent;
}
.kanban-scroller::-webkit-scrollbar { height: 8px; }
.kanban-scroller::-webkit-scrollbar-thumb {
    background: var(--border-strong);
    border-radius: 4px;
}

.kanban-column {
    width: 300px;
    min-width: 300px;
    max-width: 300px;
    background: var(--bg-muted);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 0.75rem;
    transition: border-color 0.15s ease, background 0.15s ease;
}
.kanban-column--dragover {
    border-color: var(--color-primary);
    background: color-mix(in srgb, var(--color-primary-subtle) 70%, var(--bg-muted));
}

.kanban-list { min-height: 1px; }

.kanban-card { user-select: none; }
.kanban-card:hover { transform: translateY(-2px); }
.kanban-card:active { cursor: grabbing; }
.kanban-card--dragging { opacity: 0.45; transform: scale(0.98); }

.kanban-empty { background: color-mix(in srgb, var(--bg-card) 60%, transparent); }

.kanban-cta {
    display: block;
    cursor: pointer;
    background: linear-gradient(180deg,
        color-mix(in srgb, var(--color-primary-subtle) 80%, var(--bg-card)) 0%,
        var(--bg-card) 100%);
    border: 1px dashed rgb(var(--color-primary-rgb) / 0.4);
    box-shadow: 0 1px 2px 0 rgb(var(--color-primary-rgb) / 0.08);
    transition: transform 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}
.kanban-cta:hover {
    border-color: var(--color-primary);
    background: linear-gradient(180deg,
        color-mix(in srgb, var(--color-primary-subtle) 95%, var(--bg-card)) 0%,
        color-mix(in srgb, var(--color-primary-subtle) 30%, var(--bg-card)) 100%);
    box-shadow: 0 6px 16px -4px rgb(var(--color-primary-rgb) / 0.25);
}
.kanban-cta-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 9999px;
    background: var(--color-primary);
    color: #fff;
}
</style>
