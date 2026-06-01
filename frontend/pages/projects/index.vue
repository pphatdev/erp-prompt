<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Projects</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Track project portfolios across teams. Status drives the lifecycle (planning, active, on_hold, completed); budget and hours come from the linked tasks and timesheets.</p>
                </div>
                <button v-if="canWrite" type="button" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />New Project
                </button>
            </header>

            <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Active</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-success flex items-center justify-center"><i class="ti ti-bolt text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiActiveAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">{{ kpis?.total ?? 0 }} total / {{ kpis?.completed ?? 0 }} completed</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Over Budget</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-danger flex items-center justify-center"><i class="ti ti-alert-triangle text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold font-mono"
                        :class="(kpis?.overBudget ?? 0) > 0 ? 'text-(--color-danger)' : 'text-(--text-heading)'">
                        {{ kpiOverBudgetAnim }}
                    </p>
                    <p class="text-xxs text-(--text-muted)">at flat 50/hr labour</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Unassigned Tasks</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-warning flex items-center justify-center"><i class="ti ti-user-question text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiUnassignedAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">Open tasks with no assignee</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Hours This Month</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-info flex items-center justify-center"><i class="ti ti-clock-hour-3 text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ formatHours(kpiHoursAnim) }}</p>
                    <p class="text-xxs text-(--text-muted)">Logged across all projects</p>
                </div>
            </section>

            <section class="flex items-center gap-2 flex-wrap">
                <button type="button" class="chip" :class="{ active: statusFilter === '' }" @click="setStatusFilter('')">All</button>
                <button v-for="s in PROJECT_STATUSES" :key="s.value" type="button"
                    class="chip" :class="{ active: statusFilter === s.value }" @click="setStatusFilter(s.value)">
                    {{ s.label }}
                </button>
                <div class="ml-auto">
                    <input v-model.lazy="search" type="search" placeholder="Search projects..."
                        class="form-control text-xs w-64" @keyup.enter="load" @change="load" />
                </div>
            </section>

            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading projects...</span>
            </div>
            <div v-else-if="projects.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-folder-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No projects yet</h4>
                <p class="text-xs text-(--text-muted) mt-1">Create your first project to start tracking tasks and time.</p>
            </div>

            <section v-else class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                <article v-for="p in projects" :key="p.id"
                    class="glass-card rounded-2xl p-5 space-y-3 cursor-pointer hover:border-(--color-primary)/40 transition"
                    @click="goToProject(p.id)">
                    <header class="flex items-start justify-between gap-2">
                        <div class="min-w-0">
                            <h3 class="text-sm font-semibold text-(--text-heading) truncate">{{ p.name }}</h3>
                            <p class="text-xxs text-(--text-muted) mt-0.5">
                                {{ formatDate(p.startDate) }}
                                <span class="mx-1">to</span>
                                {{ formatDate(p.endDate) }}
                            </p>
                        </div>
                        <span class="text-xxs px-1.5 py-0.5 rounded font-mono shrink-0" :class="statusBadge(p.status)">{{ p.status }}</span>
                    </header>

                    <p v-if="p.description" class="text-xxs text-(--text-body) line-clamp-2">{{ p.description }}</p>

                    <div class="grid grid-cols-2 gap-2 text-xxs">
                        <div>
                            <p class="font-bold uppercase tracking-widest text-(--text-muted)">Manager</p>
                            <p class="text-(--text-body) truncate">{{ p.manager?.fullName || 'Unassigned' }}</p>
                        </div>
                        <div>
                            <p class="font-bold uppercase tracking-widest text-(--text-muted)">Budget</p>
                            <p class="text-(--text-body) font-mono">{{ p.budget > 0 ? p.budget.toFixed(2) : '-' }}</p>
                        </div>
                    </div>

                    <div class="space-y-1">
                        <div class="flex items-center justify-between text-xxs">
                            <span class="font-bold uppercase tracking-widest text-(--text-muted)">Tasks</span>
                            <span class="font-mono">{{ p.tasksCount ?? 0 }}</span>
                        </div>
                    </div>

                    <footer v-if="canWrite || canDelete" class="flex items-center justify-end gap-1 pt-2 border-t border-(--border-color)/40">
                        <button v-if="canWrite" type="button" class="action-btn" title="Edit"
                            @click.stop="openEditModal(p)">
                            <i class="ti ti-pencil text-xs" />
                        </button>
                        <button v-if="canDelete" type="button" class="action-btn action-btn-danger" title="Delete"
                            @click.stop="confirmDelete(p)">
                            <i class="ti ti-trash text-xs" />
                        </button>
                    </footer>
                </article>
            </section>

            <Pagination v-if="projects.length > 0" :page="pagination.page" :limit="pagination.limit"
                :total="pagination.total" :total-pages="pagination.totalPages"
                @update:page="(p) => { pagination.page = p; load() }"
                @update:limit="(l) => { pagination.limit = l; pagination.page = 1; load() }" />
        </div>

        <div v-if="showFormModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-2xl bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">{{ editingId ? 'Edit Project' : 'New Project' }}</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="post">
                    <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Name *</label>
                            <input v-model="form.name" type="text" required maxlength="255"
                                placeholder="Q1 Marketing Campaign" class="form-control text-xs" />
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Description</label>
                            <textarea v-model="form.description" rows="3" class="form-control text-xs resize-none" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Start Date</label>
                                <input v-model="form.start_date" type="date" class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">End Date</label>
                                <input v-model="form.end_date" type="date" class="form-control text-xs font-mono" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Budget</label>
                                <input v-model.number="form.budget" type="number" step="0.01" min="0"
                                    class="form-control text-xs font-mono text-right" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Status</label>
                                <select v-model="form.status" class="form-control text-xs">
                                    <option v-for="s in PROJECT_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Manager</label>
                            <select v-model="form.manager_id" class="form-control text-xs" :disabled="employeesLoading">
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
                    <h3 class="font-semibold text-sm">Delete Project</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="deleteTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <div class="p-3 rounded-lg badge-soft-danger text-xs flex items-start gap-2">
                        <i class="ti ti-alert-triangle mt-0.5" />
                        <div>
                            <p class="font-semibold">Soft delete this project</p>
                            <p class="text-xxs mt-0.5">Project <span class="font-mono">{{ deleteTarget.name }}</span> and all its tasks/timesheets will be soft-deleted. The data stays on disk and can be restored.</p>
                        </div>
                    </div>
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
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '~/composables/useApi'
import { useProjects, PROJECT_STATUSES } from '~/composables/useProjects'
import { useToast } from '~/composables/useToast'
import { useCountUp } from '~/composables/useCountUp'
import { useAuthStore } from '~/stores/auth'
import type {
    Project,
    ProjectStatus,
    ProjectsKpis,
    CreateProjectPayload,
} from '~/types/projects'

definePageMeta({ breadcrumb: 'Projects' })

interface EmployeeLite { id: string; employeeId: string | null; fullName: string }
interface Paginated<T> { data: T[]; pagination?: { page: number; limit: number; total: number; totalPages: number } }

const api = useApi()
const finance = useProjects()
const toast = useToast()
const router = useRouter()
const authStore = useAuthStore()

const canRead   = computed(() => authStore.hasPermission('projects.project.read'))
const canWrite  = computed(() => authStore.hasPermission('projects.project.write'))
const canDelete = computed(() => authStore.hasPermission('projects.project.delete'))

const loading = ref(false)
const posting = ref(false)
const deleting = ref(false)

const projects = ref<Project[]>([])
const pagination = reactive({ page: 1, limit: 15, total: 0, totalPages: 1 })
const statusFilter = ref<'' | ProjectStatus>('')
const search = ref('')
const kpis = ref<ProjectsKpis | null>(null)

const formatDate = (s: string | null) => {
    if (!s) return '-'
    const d = new Date(s)
    return isNaN(d.getTime()) ? s : d.toISOString().slice(0, 10)
}
const formatHours = (h: number) => {
    if (h >= 1000) return `${(h / 1000).toFixed(1)}k`
    return h.toFixed(0)
}
const statusBadge = (s: ProjectStatus) =>
    PROJECT_STATUSES.find(x => x.value === s)?.badge ?? 'badge-soft-secondary'

const kpiActiveAnim     = useCountUp(() => kpis.value?.active ?? 0)
const kpiOverBudgetAnim = useCountUp(() => kpis.value?.overBudget ?? 0)
const kpiUnassignedAnim = useCountUp(() => kpis.value?.unassignedTasks ?? 0)
const kpiHoursAnim      = useCountUp(() => kpis.value?.hoursThisMonth ?? 0)

const load = async () => {
    if (!canRead.value) return
    loading.value = true
    try {
        const [res, k] = await Promise.all([
            finance.projects.list({
                page: pagination.page,
                limit: pagination.limit,
                status: statusFilter.value || undefined,
                search: search.value.trim() || undefined,
            }),
            finance.projects.kpis().catch(() => ({ data: null as any })),
        ])
        projects.value = res.data
        const p = (res as any).pagination
        if (p) {
            pagination.total = p.total ?? 0
            pagination.totalPages = p.totalPages ?? 1
            pagination.page = p.page ?? 1
            pagination.limit = p.limit ?? pagination.limit
        }
        if (k?.data) kpis.value = k.data
    } catch (err: any) {
        toast.error('Failed to load projects', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const setStatusFilter = (s: '' | ProjectStatus) => {
    if (statusFilter.value === s) return
    statusFilter.value = s
    pagination.page = 1
    load()
}

const goToProject = (id: string) => {
    router.push(`/projects/${id}`)
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

const blankForm = (): CreateProjectPayload => ({
    name: '',
    description: null,
    start_date: null,
    end_date: null,
    budget: 0,
    status: 'planning',
    manager_id: null,
})

const form = reactive<CreateProjectPayload>(blankForm())

const openCreateModal = () => {
    editingId.value = null
    Object.assign(form, blankForm())
    showFormModal.value = true
    ensureEmployeesLoaded()
}

const openEditModal = (p: Project) => {
    editingId.value = p.id
    Object.assign(form, {
        name: p.name,
        description: p.description,
        start_date: p.startDate,
        end_date: p.endDate,
        budget: p.budget,
        status: p.status,
        manager_id: p.managerId,
    })
    showFormModal.value = true
    ensureEmployeesLoaded()
}

const canSubmit = computed(() => {
    if (!form.name.trim()) return false
    if (form.start_date && form.end_date && form.start_date > form.end_date) return false
    return true
})

const post = async () => {
    if (!canSubmit.value) return
    posting.value = true
    try {
        const payload: CreateProjectPayload = {
            ...form,
            name: form.name.trim(),
            description: form.description?.toString().trim() || null,
            budget: form.budget ?? 0,
        }
        if (editingId.value) {
            const res = await finance.projects.update(editingId.value, payload)
            toast.success('Project updated', res.data.name)
        } else {
            const res = await finance.projects.create(payload)
            toast.success('Project created', res.data.name)
        }
        showFormModal.value = false
        await load()
    } catch (err: any) {
        toast.error('Save failed', err?.data?.message)
    } finally {
        posting.value = false
    }
}

const deleteTarget = ref<Project | null>(null)
const confirmDelete = (p: Project) => { deleteTarget.value = p }
const onConfirmDelete = async () => {
    if (!deleteTarget.value) return
    deleting.value = true
    try {
        await finance.projects.destroy(deleteTarget.value.id)
        toast.success('Project deleted', deleteTarget.value.name)
        deleteTarget.value = null
        await load()
    } catch (err: any) {
        toast.error('Delete failed', err?.data?.message)
    } finally {
        deleting.value = false
    }
}

onMounted(load)
</script>

<style scoped>
.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
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
</style>
