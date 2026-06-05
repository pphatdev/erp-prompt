<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Tasks</h1>
                    <p class="text-xs text-(--text-muted) mt-1">All tasks across all projects. Filter by project, status, priority, or assignee. Switch to Board to drag-and-drop across statuses.</p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="view-toggle">
                        <button type="button" class="view-toggle-btn" :class="{ active: view === 'list' }" @click="setView('list')">
                            <i class="ti ti-list" /> List
                        </button>
                        <button type="button" class="view-toggle-btn" :class="{ active: view === 'board' }" @click="setView('board')">
                            <i class="ti ti-layout-board" /> Board
                        </button>
                    </div>
                    <button v-if="canWrite" type="button" class="btn btn-primary text-xs" @click="openCreateModal">
                        <i class="ti ti-plus" />New Task
                    </button>
                </div>
            </header>

            <!-- <section v-if="view === 'list'" class="flex items-center gap-2 flex-wrap max-sm:justify-center">
                <button type="button" class="chip" :class="{ active: statusFilter === '' }" @click="setStatusFilter('')">All</button>
                <button v-for="s in TASK_STATUSES" :key="s.value" type="button"
                    class="chip" :class="{ active: statusFilter === s.value }" @click="setStatusFilter(s.value)">
                    {{ s.label }}
                </button>
            </section> -->

            <section class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                <div class="space-y-1">
                    <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Project</label>
                    <select v-model="projectFilter" class="form-control text-xs" :disabled="projectsLoading"
                        @change="onFilterChange">
                        <option value="">All projects</option>
                        <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Assignee</label>
                    <select v-model="assigneeFilter" class="form-control text-xs" :disabled="employeesLoading"
                        @change="onFilterChange">
                        <option value="">All assignees</option>
                        <option :value="UNASSIGNED">Unassigned</option>
                        <option v-for="e in employees" :key="e.id" :value="e.id">
                            {{ e.fullName }}{{ e.employeeId ? ` (${e.employeeId})` : '' }}
                        </option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Priority</label>
                    <select v-model="priorityFilter" class="form-control text-xs" @change="onFilterChange">
                        <option value="">All priorities</option>
                        <option v-for="p in TASK_PRIORITIES" :key="p.value" :value="p.value">{{ p.label }}</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Search</label>
                    <input v-model.lazy="search" type="search" placeholder="title or description..."
                        class="form-control text-xs" @keyup.enter="load" @change="load" />
                </div>
            </section>

            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading tasks...</span>
            </div>
            <div v-else-if="tasks.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-checklist text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No tasks match your filters</h4>
                <p class="text-xs text-(--text-muted) mt-1">Clear filters or create a new task.</p>
            </div>

            <!-- LIST VIEW -->
            <section v-else-if="view === 'list'" class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-(--bg-muted)/40 text-(--text-muted)">
                            <tr>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Title</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3 w-40">Project</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3 w-32">Assignee</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3 w-24">Priority</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3 w-32">Status</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3 w-28">Due</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3 w-20">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="t in tasks" :key="t.id" class="border-t border-(--border-color) hover:bg-(--bg-muted)/40">
                                <td class="px-3 py-2">
                                    <p class="text-(--text-heading) font-semibold truncate max-w-sm">{{ t.title }}</p>
                                    <p v-if="t.description" class="text-xxs text-(--text-muted) line-clamp-1 max-w-sm">{{ t.description }}</p>
                                </td>
                                <td class="px-3 py-2">
                                    <NuxtLink v-if="t.project" :to="`/projects/${t.projectId}`" class="text-(--color-primary) hover:underline truncate inline-block max-w-xs">
                                        {{ t.project.name }}
                                    </NuxtLink>
                                    <span v-else class="text-(--text-muted)">-</span>
                                </td>
                                <td class="px-3 py-2 truncate max-w-xs">{{ t.assignee?.fullName || '-' }}</td>
                                <td class="px-3 py-2">
                                    <span class="text-xxs px-1.5 py-0.5 rounded font-mono" :class="priorityBadge(t.priority)">{{ t.priority }}</span>
                                </td>
                                <td class="px-3 py-2">
                                    <select :value="t.status" :disabled="!canWrite"
                                        class="form-control form-control-sm text-xxs font-mono w-full"
                                        @change="(ev) => onStatusChangeFromEvent(t, ev)">
                                        <option v-for="s in TASK_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
                                    </select>
                                </td>
                                <td class="px-3 py-2 font-mono" :class="dueColor(t.dueDate)">{{ formatDate(t.dueDate) }}</td>
                                <td class="px-3 py-2 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button v-if="canWrite" type="button" class="action-btn" title="Edit" @click="openEditModal(t)">
                                            <i class="ti ti-pencil text-xs" />
                                        </button>
                                        <button v-if="canDelete" type="button" class="action-btn action-btn-danger" title="Delete"
                                            @click="confirmDelete(t)">
                                            <i class="ti ti-trash text-xs" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-if="pagination.total > 0" :page="pagination.page" :limit="pagination.limit"
                    :total="pagination.total" :total-pages="pagination.totalPages"
                    @update:page="(p) => { pagination.page = p; load() }"
                    @update:limit="(l) => { pagination.limit = l; pagination.page = 1; load() }" />
            </section>

            <!-- BOARD VIEW -->
            <section v-else class="kanban-scroller -mx-2 px-2 pb-4 overflow-x-auto">
                <div class="flex gap-4 min-w-max">
                    <div v-for="col in boardColumns" :key="col.status" class="kanban-column flex flex-col gap-3"
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

                                <NuxtLink v-if="t.project" :to="`/projects/${t.projectId}`"
                                    class="text-xxs text-(--color-primary) hover:underline truncate inline-block max-w-full mb-2"
                                    @click.stop>
                                    <i class="ti ti-folder text-[11px]" /> {{ t.project.name }}
                                </NuxtLink>

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

                            <div v-if="col.tasks.length === 0"
                                class="kanban-empty rounded-xl border border-dashed border-(--border-color) text-(--text-muted) text-xxs py-6 text-center">
                                Drop here to move to <span class="font-semibold">{{ col.label }}</span>
                            </div>
                        </div>
                    </div>
                </div>
                <p v-if="pagination.total > tasks.length" class="text-xxs text-(--text-muted) mt-3 text-center">
                    Showing {{ tasks.length }} of {{ pagination.total }}. Narrow filters or switch to List for paginated browsing.
                </p>
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
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Project *</label>
                            <select v-model="form.project_id" required class="form-control text-xs" :disabled="projectsLoading">
                                <option value="">Pick project</option>
                                <option v-for="p in projects" :key="p.id" :value="p.id">{{ p.name }}</option>
                            </select>
                        </div>
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
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showFormModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="!canSubmit || posting">
                            <i v-if="posting" class="ti ti-loader-2 animate-spin" />
                            {{ editingId ? 'Save' : 'Create' }}
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Delete Task</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="deleteTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <p class="text-xs text-(--text-muted)">
                        Delete task <span class="font-semibold text-(--text-heading)">{{ deleteTarget.title }}</span>?
                        Its timesheets will cascade.
                    </p>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="deleteTarget = null">Cancel</button>
                    <button type="button" class="btn btn-danger text-xs" :disabled="deleting" @click="onConfirmDelete">
                        <i v-if="deleting" class="ti ti-loader-2 animate-spin" />
                        Delete
                    </button>
                </footer>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
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

definePageMeta({ breadcrumb: 'Tasks' })

interface EmployeeLite { id: string; employeeId: string | null; fullName: string }
interface Paginated<T> { data: T[]; pagination?: { page: number; limit: number; total: number; totalPages: number } }

const UNASSIGNED = '__unassigned__'

type ViewKey = 'list' | 'board'
const VALID_VIEWS: ViewKey[] = ['list', 'board']

interface KanbanColumn {
    status: TaskStatus
    label: string
    tasks: Task[]
}

const route = useRoute()
const router = useRouter()
const api = useApi()
const pm = useProjects()
const toast = useToast()
const authStore = useAuthStore()

const initialView: ViewKey = (typeof route.query.view === 'string' && (VALID_VIEWS as string[]).includes(route.query.view))
    ? route.query.view as ViewKey
    : 'list'
const view = ref<ViewKey>(initialView)
const setView = (next: ViewKey) => {
    if (view.value === next) return
    view.value = next
    router.replace({ query: { ...route.query, view: next } })
    // Board needs the full set so drag-and-drop reflects all matching tasks.
    if (next === 'board') {
        pagination.page = 1
        pagination.limit = 500
    } else {
        pagination.limit = 15
    }
    load()
}

const canRead   = computed(() => authStore.hasPermission('projects.task.read'))
const canWrite  = computed(() => authStore.hasPermission('projects.task.write'))
const canDelete = computed(() => authStore.hasPermission('projects.task.delete'))

const loading = ref(false)
const posting = ref(false)
const deleting = ref(false)

const tasks = ref<Task[]>([])
const pagination = reactive({ page: 1, limit: initialView === 'board' ? 500 : 15, total: 0, totalPages: 1 })
const statusFilter = ref<'' | TaskStatus>('')
const projectFilter = ref<string>('')
const assigneeFilter = ref<string>('')
const priorityFilter = ref<'' | TaskPriority>('')
const search = ref('')

const today = new Date().toISOString().slice(0, 10)
const formatDate = (s: string | null) => {
    if (!s) return '-'
    const d = new Date(s)
    return isNaN(d.getTime()) ? s : d.toISOString().slice(0, 10)
}
const priorityBadge = (p: TaskPriority) =>
    TASK_PRIORITIES.find(x => x.value === p)?.badge ?? 'badge-soft-secondary'
const dueColor = (due: string | null) => {
    if (!due) return 'text-(--text-muted)'
    const d = new Date(due).getTime()
    const t = new Date(today).getTime()
    const days = (d - t) / 86_400_000
    if (days < 0) return 'text-(--color-danger)'
    if (days < 3) return 'text-(--color-warning)'
    return 'text-(--text-body)'
}
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

const boardColumns = computed<KanbanColumn[]>(() => {
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

// ---- Drag and Drop (board view) -------------------------------------------

const draggingId = ref<string | null>(null)
const draggingFrom = ref<TaskStatus | null>(null)
const dragOverColumn = ref<TaskStatus | null>(null)
const movingId = ref<string | null>(null)
const pendingDropStatus = ref<TaskStatus | null>(null)

const onCardDragStart = (t: Task, ev: DragEvent) => {
    if (!canWrite.value) { ev.preventDefault(); return }
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

const onColumnDragOver = (status: TaskStatus, ev: DragEvent) => {
    if (!draggingFrom.value || draggingFrom.value === status) {
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

const load = async () => {
    if (!canRead.value) return
    loading.value = true
    try {
        const res = await pm.tasks.list({
            page: pagination.page,
            limit: pagination.limit,
            status: statusFilter.value || undefined,
            project_id: projectFilter.value || undefined,
            assignee_id: assigneeFilter.value === UNASSIGNED ? undefined : (assigneeFilter.value || undefined),
            priority: priorityFilter.value || undefined,
            search: search.value.trim() || undefined,
        })
        // Client-side filter for "unassigned" since the backend doesn't expose null-only filter yet.
        tasks.value = assigneeFilter.value === UNASSIGNED
            ? res.data.filter(t => !t.assigneeId)
            : res.data
        const p = (res as any).pagination
        if (p) {
            pagination.total = p.total ?? 0
            pagination.totalPages = p.totalPages ?? 1
            pagination.page = p.page ?? 1
            pagination.limit = p.limit ?? pagination.limit
        }
    } catch (err: any) {
        toast.error('Failed to load tasks', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const setStatusFilter = (s: '' | TaskStatus) => {
    if (statusFilter.value === s) return
    statusFilter.value = s
    pagination.page = 1
    load()
}

const onFilterChange = () => {
    pagination.page = 1
    load()
}

const onStatusChange = async (t: Task, status: TaskStatus) => {
    const prev = t.status
    t.status = status
    try {
        await pm.tasks.updateStatus(t.id, status)
        toast.success('Status updated')
    } catch (err: any) {
        t.status = prev
        toast.error('Update failed', err?.data?.message)
    }
}

const onStatusChangeFromEvent = (t: Task, ev: Event) => {
    const select = ev.target as HTMLSelectElement
    onStatusChange(t, select.value as TaskStatus)
}

const projects = ref<Project[]>([])
const projectsLoading = ref(false)
const ensureProjectsLoaded = async () => {
    if (projects.value.length || projectsLoading.value) return
    projectsLoading.value = true
    try {
        const res = await pm.projects.list({ limit: 200 })
        projects.value = res.data
    } catch (err: any) {
        toast.error('Failed to load projects', err?.data?.message)
    } finally {
        projectsLoading.value = false
    }
}

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

const showFormModal = ref(false)
const editingId = ref<string | null>(null)

const blankForm = (): CreateTaskPayload => ({
    project_id: '',
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
    ensureProjectsLoaded()
    ensureEmployeesLoaded()
}

const openEditModal = (t: Task) => {
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
    ensureProjectsLoaded()
    ensureEmployeesLoaded()
}

const canSubmit = computed(() => !!form.project_id && !!form.title.trim())

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
        await load()
    } catch (err: any) {
        toast.error('Save failed', err?.data?.message)
    } finally {
        posting.value = false
    }
}

const deleteTarget = ref<Task | null>(null)
const confirmDelete = (t: Task) => { deleteTarget.value = t }
const onConfirmDelete = async () => {
    if (!deleteTarget.value) return
    deleting.value = true
    try {
        await pm.tasks.destroy(deleteTarget.value.id)
        toast.success('Task deleted')
        deleteTarget.value = null
        await load()
    } catch (err: any) {
        toast.error('Delete failed', err?.data?.message)
    } finally {
        deleting.value = false
    }
}

onMounted(() => {
    ensureProjectsLoaded()
    ensureEmployeesLoaded()
    load()
})
</script>

<style scoped>
.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    color: var(--text-body);
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.action-btn:hover {
    background: var(--bg-muted);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}

.action-btn-danger:hover {
    color: var(--color-danger);
    border-color: rgb(var(--color-danger-rgb) / 0.4);
}

.chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    font-size: 11px;
    color: var(--text-body);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.chip:hover { background: var(--bg-muted); }
.chip.active {
    background: rgb(var(--color-primary-rgb) / 0.12);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}

.view-toggle {
    display: inline-flex;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 2px;
}
.view-toggle-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 6px;
    font-size: 15px;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}
.view-toggle-btn:hover { color: var(--text-heading); }
.view-toggle-btn.active {
    background: rgb(var(--color-primary-rgb) / 0.12);
    color: var(--color-primary);
}

/* Kanban (board view) */
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
</style>
