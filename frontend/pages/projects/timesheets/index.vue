<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">My Timesheets</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Log time against tasks. Hours roll up by day and feed the project's budget actuals.</p>
                </div>
                <button v-if="canWrite" type="button" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />Log Time
                </button>
            </header>

            <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Total Hours</span>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiTotalAnim.toFixed(1) }}</p>
                    <p class="text-xxs text-(--text-muted)">in selected range</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Entries</span>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiEntriesAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">{{ daysWithEntries }} days logged</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Tasks</span>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiTasksAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">distinct in range</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Daily Avg</span>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ dailyAvg.toFixed(1) }}</p>
                    <p class="text-xxs text-(--text-muted)">across days with entries</p>
                </div>
            </section>

            <section class="glass-card rounded-2xl p-3 flex flex-col sm:flex-row items-stretch sm:items-center gap-3">
                <div class="flex items-center gap-2">
                    <button type="button" class="btn btn-ghost text-xxs" @click="shiftWeek(-1)" title="Previous week">
                        <i class="ti ti-chevron-left" />
                    </button>
                    <button type="button" class="btn btn-ghost text-xxs" @click="resetToCurrentWeek">Today</button>
                    <button type="button" class="btn btn-ghost text-xxs" @click="shiftWeek(1)" title="Next week">
                        <i class="ti ti-chevron-right" />
                    </button>
                </div>
                <div class="grid grid-cols-2 gap-2 sm:flex sm:items-center sm:gap-2 ml-auto">
                    <div class="space-y-1">
                        <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">From</label>
                        <input v-model="fromDate" type="date" class="form-control text-xs font-mono" @change="load" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">To</label>
                        <input v-model="toDate" type="date" class="form-control text-xs font-mono" @change="load" />
                    </div>
                </div>
            </section>

            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading timesheets...</span>
            </div>
            <div v-else-if="timesheets.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-clock-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No time logged in this range</h4>
                <p class="text-xs text-(--text-muted) mt-1">Click Log Time to add an entry.</p>
            </div>

            <section v-else class="space-y-3">
                <article v-for="day in groupedByDay" :key="day.date" class="glass-card rounded-2xl overflow-hidden">
                    <header class="flex items-center justify-between px-4 py-2 bg-(--bg-muted)/30 border-b border-(--border-color)">
                        <div class="flex items-center gap-2">
                            <i class="ti ti-calendar text-(--text-muted)" />
                            <span class="text-xs font-semibold text-(--text-heading) font-mono">{{ day.date }}</span>
                            <span class="text-xxs text-(--text-muted)">{{ day.weekday }}</span>
                            <span v-if="day.date === today" class="text-xxs px-1.5 py-0.5 rounded font-mono badge-soft-info">today</span>
                        </div>
                        <div class="text-xxs">
                            <span class="text-(--text-muted) font-mono">{{ day.entries.length }} entries / </span>
                            <span class="font-mono font-semibold"
                                :class="day.total > 16 ? 'text-(--color-danger)' : (day.total > 8 ? 'text-(--color-warning)' : 'text-(--text-heading)')">
                                {{ day.total.toFixed(1) }}h
                            </span>
                        </div>
                    </header>
                    <ul class="divide-y divide-(--border-color)">
                        <li v-for="ts in day.entries" :key="ts.id" class="flex items-start gap-3 px-4 py-3 hover:bg-(--bg-muted)/40">
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-(--text-heading) truncate">{{ ts.task?.title || 'Task' }}</p>
                                <p v-if="ts.task?.projectId" class="text-xxs text-(--text-muted) mt-0.5">
                                    <NuxtLink :to="`/projects/${ts.task.projectId}`" class="text-(--color-primary) hover:underline">
                                        Project
                                    </NuxtLink>
                                </p>
                                <p v-if="ts.notes" class="text-xxs text-(--text-body) mt-1 line-clamp-2">{{ ts.notes }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-bold text-(--text-heading) font-mono">{{ ts.hoursWorked.toFixed(2) }}h</p>
                                <div class="flex items-center justify-end gap-1 mt-1">
                                    <button v-if="canWrite" type="button" class="action-btn" title="Edit" @click="openEditModal(ts)">
                                        <i class="ti ti-pencil text-xs" />
                                    </button>
                                    <button v-if="canDelete" type="button" class="action-btn action-btn-danger" title="Delete"
                                        @click="confirmDelete(ts)">
                                        <i class="ti ti-trash text-xs" />
                                    </button>
                                </div>
                            </div>
                        </li>
                    </ul>
                </article>
            </section>
        </div>

        <div v-if="showFormModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-xl bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">{{ editingId ? 'Edit Time Log' : 'Log Time' }}</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="post">
                    <div class="p-5 space-y-4">
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Task *</label>
                            <select v-model="form.task_id" required class="form-control text-xs" :disabled="tasksLoading">
                                <option value="">Pick task</option>
                                <option v-for="t in taskOptions" :key="t.id" :value="t.id">
                                    {{ t.projectName }} / {{ t.title }}
                                </option>
                            </select>
                            <p v-if="taskOptions.length === 0 && !tasksLoading" class="text-xxs text-(--color-warning)">
                                No tasks found. Create a task in a project first.
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Date *</label>
                                <input v-model="form.log_date" type="date" required class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Hours *</label>
                                <input v-model.number="form.hours_worked" type="number" step="0.25" min="0.1" max="24" required
                                    class="form-control text-xs font-mono text-right" />
                                <p class="text-xxs text-(--text-muted)">Max 24h per entry. Backend caps at 24h.</p>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Notes</label>
                            <textarea v-model="form.notes" rows="3" class="form-control text-xs resize-none"
                                placeholder="What did you work on?" />
                        </div>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showFormModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="!canSubmit || posting">
                            <i v-if="posting" class="ti ti-loader-2 animate-spin" />
                            {{ editingId ? 'Save' : 'Log Time' }}
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Delete Time Log</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="deleteTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <p class="text-xs text-(--text-muted)">
                        Delete the <span class="font-mono font-semibold text-(--text-heading)">{{ deleteTarget.hoursWorked.toFixed(2) }}h</span>
                        entry on <span class="font-mono">{{ deleteTarget.logDate }}</span>?
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
import { computed, onMounted, reactive, ref } from 'vue'
import { useApi } from '~/composables/useApi'
import { useProjects } from '~/composables/useProjects'
import { useToast } from '~/composables/useToast'
import { useCountUp } from '~/composables/useCountUp'
import { useAuthStore } from '~/stores/auth'
import type {
    Task,
    Timesheet,
    CreateTimesheetPayload,
} from '~/types/projects'

definePageMeta({ breadcrumb: 'Timesheets' })

interface EmployeeMe { id: string; employeeId: string | null; fullName?: string | null }

const api = useApi()
const pm = useProjects()
const toast = useToast()
const authStore = useAuthStore()

const canRead   = computed(() => authStore.hasPermission('projects.timesheet.read'))
const canWrite  = computed(() => authStore.hasPermission('projects.timesheet.write'))
const canDelete = computed(() => authStore.hasPermission('projects.timesheet.delete'))

const loading = ref(false)
const posting = ref(false)
const deleting = ref(false)

const me = ref<EmployeeMe | null>(null)

const timesheets = ref<Timesheet[]>([])

const today = new Date().toISOString().slice(0, 10)

// ---- Date range (defaults to current week, Sun-Sat) ------------------------

const weekRange = (anchor: Date): { from: string; to: string } => {
    const day = anchor.getDay()
    const from = new Date(anchor)
    from.setDate(anchor.getDate() - day)
    const to = new Date(from)
    to.setDate(from.getDate() + 6)
    return { from: from.toISOString().slice(0, 10), to: to.toISOString().slice(0, 10) }
}

const initial = weekRange(new Date())
const fromDate = ref(initial.from)
const toDate = ref(initial.to)

const shiftWeek = (delta: number) => {
    const d = new Date(fromDate.value)
    d.setDate(d.getDate() + 7 * delta)
    const r = weekRange(d)
    fromDate.value = r.from
    toDate.value = r.to
    load()
}

const resetToCurrentWeek = () => {
    const r = weekRange(new Date())
    fromDate.value = r.from
    toDate.value = r.to
    load()
}

// ---- Load -----------------------------------------------------------------

const loadMe = async () => {
    try {
        const res = await api.get<{ data: EmployeeMe } | EmployeeMe>('/employees/me')
        me.value = ('data' in (res as any) ? (res as any).data : res) as EmployeeMe
    } catch (err: any) {
        toast.error('Failed to identify employee', err?.data?.message)
    }
}

const load = async () => {
    if (!canRead.value || !me.value) return
    loading.value = true
    try {
        const res = await pm.timesheets.list({
            employee_id: me.value.id,
            from: fromDate.value,
            to: toDate.value,
            limit: 200,
        })
        timesheets.value = res.data
    } catch (err: any) {
        toast.error('Failed to load timesheets', err?.data?.message)
    } finally {
        loading.value = false
    }
}

// ---- Group by day ---------------------------------------------------------

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

const groupedByDay = computed(() => {
    const map = new Map<string, Timesheet[]>()
    for (const ts of timesheets.value) {
        const date = (ts.logDate || '').slice(0, 10)
        if (!map.has(date)) map.set(date, [])
        map.get(date)!.push(ts)
    }
    const days = Array.from(map.entries())
        .map(([date, entries]) => ({
            date,
            weekday: WEEKDAYS[new Date(date).getDay()],
            entries: entries.sort((a, b) => (a.createdAt || '').localeCompare(b.createdAt || '')),
            total: entries.reduce((s, e) => s + e.hoursWorked, 0),
        }))
        .sort((a, b) => b.date.localeCompare(a.date))
    return days
})

const totalHours = computed(() => timesheets.value.reduce((s, t) => s + t.hoursWorked, 0))
const daysWithEntries = computed(() => groupedByDay.value.length)
const distinctTasks = computed(() => new Set(timesheets.value.map(t => t.taskId)).size)
const dailyAvg = computed(() => daysWithEntries.value > 0 ? totalHours.value / daysWithEntries.value : 0)

const kpiTotalAnim   = useCountUp(() => totalHours.value)
const kpiEntriesAnim = useCountUp(() => timesheets.value.length)
const kpiTasksAnim   = useCountUp(() => distinctTasks.value)

// ---- Task picker (lazy) ---------------------------------------------------

interface TaskOption { id: string; title: string; projectName: string }

const taskOptions = ref<TaskOption[]>([])
const tasksLoading = ref(false)
const ensureTasksLoaded = async () => {
    if (taskOptions.value.length || tasksLoading.value) return
    tasksLoading.value = true
    try {
        const res = await pm.tasks.list({ limit: 500 })
        taskOptions.value = res.data.map((t: Task) => ({
            id: t.id,
            title: t.title,
            projectName: t.project?.name || '-',
        }))
    } catch (err: any) {
        toast.error('Failed to load tasks', err?.data?.message)
    } finally {
        tasksLoading.value = false
    }
}

// ---- Form -----------------------------------------------------------------

const showFormModal = ref(false)
const editingId = ref<string | null>(null)

const blankForm = (): CreateTimesheetPayload => ({
    task_id: '',
    employee_id: me.value?.id || '',
    log_date: today,
    hours_worked: 1,
    notes: null,
})

const form = reactive<CreateTimesheetPayload>(blankForm())

const openCreateModal = () => {
    editingId.value = null
    Object.assign(form, blankForm())
    showFormModal.value = true
    ensureTasksLoaded()
}

const openEditModal = (ts: Timesheet) => {
    editingId.value = ts.id
    Object.assign(form, {
        task_id: ts.taskId,
        employee_id: ts.employeeId,
        log_date: ts.logDate,
        hours_worked: ts.hoursWorked,
        notes: ts.notes,
    })
    showFormModal.value = true
    ensureTasksLoaded()
}

const canSubmit = computed(() =>
    !!form.task_id && !!form.log_date && form.hours_worked > 0 && form.hours_worked <= 24
)

const post = async () => {
    if (!canSubmit.value) return
    if (!me.value) {
        toast.error('Employee identity not loaded')
        return
    }
    posting.value = true
    try {
        const payload: CreateTimesheetPayload = {
            ...form,
            employee_id: me.value.id,
            notes: form.notes?.toString().trim() || null,
        }
        if (editingId.value) {
            await pm.timesheets.update(editingId.value, payload)
            toast.success('Time updated')
        } else {
            await pm.timesheets.create(payload)
            toast.success('Time logged')
        }
        showFormModal.value = false
        await load()
    } catch (err: any) {
        toast.error('Save failed', err?.data?.message)
    } finally {
        posting.value = false
    }
}

const deleteTarget = ref<Timesheet | null>(null)
const confirmDelete = (ts: Timesheet) => { deleteTarget.value = ts }
const onConfirmDelete = async () => {
    if (!deleteTarget.value) return
    deleting.value = true
    try {
        await pm.timesheets.destroy(deleteTarget.value.id)
        toast.success('Entry deleted')
        deleteTarget.value = null
        await load()
    } catch (err: any) {
        toast.error('Delete failed', err?.data?.message)
    } finally {
        deleting.value = false
    }
}

onMounted(async () => {
    await loadMe()
    if (me.value) await load()
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
</style>
