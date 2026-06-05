<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Onboarding</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        Checklists materialised when an offer is accepted. Tick tasks off as each owner finishes their step.
                    </p>
                </div>
            </header>

            <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div v-for="kpi in kpis" :key="kpi.key" class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">{{ kpi.label }}</span>
                    <p class="text-2xl font-bold font-mono" :class="kpi.text">{{ kpi.value }}</p>
                </div>
            </section>

            <section class="flex items-center gap-2 flex-wrap max-sm:justify-center">
                <button type="button" class="chip" :class="{ active: statusFilter === '' }" @click="setStatusFilter('')">
                    All
                </button>
                <button v-for="s in (Object.keys(CHECKLIST_STATUS_META) as ChecklistStatus[])" :key="s" type="button"
                    class="chip" :class="{ active: statusFilter === s }" @click="setStatusFilter(s)">
                    {{ CHECKLIST_STATUS_META[s].label }}
                </button>
            </section>

            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading checklists...</span>
            </div>

            <div v-else-if="checklists.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-checklist text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No onboarding in flight</h4>
                <p class="text-xs text-(--text-muted) mt-1">
                    A checklist is seeded automatically the moment an offer is accepted.
                </p>
            </div>

            <div v-else class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- Checklist list -->
                <section class="lg:col-span-5 space-y-3">
                    <button v-for="c in checklists" :key="c.id" type="button"
                        class="w-full text-left glass-card rounded-2xl p-4 transition-colors"
                        :class="selectedId === c.id
                            ? 'border-(--color-primary)/60 ring-2 ring-(--color-primary)/15'
                            : 'hover:bg-(--bg-muted)/40'"
                        @click="select(c)">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-(--text-heading) truncate">
                                    {{ c.employee?.fullName || c.name }}
                                </p>
                                <p class="text-xxs text-(--text-muted) font-mono truncate">
                                    {{ c.offer?.referenceNumber || c.employee?.employeeCode || '' }}
                                </p>
                            </div>
                            <Badge :variant="CHECKLIST_STATUS_META[c.status].variant" :dot="true">
                                {{ CHECKLIST_STATUS_META[c.status].label }}
                            </Badge>
                        </div>
                        <div class="mt-3 space-y-1.5">
                            <div class="flex items-center justify-between text-xxs text-(--text-muted) font-mono">
                                <span>{{ c.completedTasks }} / {{ c.totalTasks }} tasks</span>
                                <span>{{ c.progressPercent }}%</span>
                            </div>
                            <div class="h-1.5 rounded-full bg-(--bg-muted) overflow-hidden">
                                <div class="h-full rounded-full transition-all"
                                    :class="progressTint(c.progressPercent)"
                                    :style="{ width: `${c.progressPercent}%` }" />
                            </div>
                        </div>
                        <div v-if="c.targetCompletionDate" class="mt-2 text-xxs text-(--text-muted) flex items-center gap-1">
                            <i class="ti ti-flag-2" />
                            Target {{ formatDate(c.targetCompletionDate) }}
                        </div>
                    </button>

                    <Pagination v-if="pagination.total > 0" :page="pagination.page" :limit="pagination.limit"
                        :total="pagination.total" :total-pages="pagination.totalPages"
                        @update:page="(p) => { pagination.page = p; load() }"
                        @update:limit="(l) => { pagination.limit = l; pagination.page = 1; load() }" />
                </section>

                <!-- Detail -->
                <section class="lg:col-span-7 space-y-4">
                    <div v-if="!selected" class="glass-card rounded-2xl py-20 text-center">
                        <i class="ti ti-arrow-left text-3xl text-(--text-muted)" />
                        <h4 class="text-sm font-semibold text-(--text-heading) mt-3">Pick a checklist</h4>
                        <p class="text-xs text-(--text-muted) mt-1">Select an employee on the left to see their tasks.</p>
                    </div>

                    <template v-else>
                        <article class="glass-card rounded-2xl p-5 space-y-3">
                            <header class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">
                                        Checklist
                                    </p>
                                    <h2 class="text-base font-semibold text-(--text-heading) truncate">
                                        {{ selected.name }}
                                    </h2>
                                    <p v-if="selected.employee?.fullName" class="text-xs text-(--text-muted) mt-0.5">
                                        Owner:
                                        <NuxtLink v-if="selected.employeeId"
                                            :to="`/hrm/employees/${selected.employeeId}`"
                                            class="text-(--color-primary) hover:underline">
                                            {{ selected.employee.fullName }}
                                        </NuxtLink>
                                        <span v-else>{{ selected.employee.fullName }}</span>
                                    </p>
                                </div>
                                <Badge :variant="CHECKLIST_STATUS_META[selected.status].variant" :dot="true">
                                    {{ CHECKLIST_STATUS_META[selected.status].label }}
                                </Badge>
                            </header>

                            <div class="grid grid-cols-3 gap-3 pt-2">
                                <div class="stat-tile">
                                    <p class="stat-label">Progress</p>
                                    <p class="stat-value text-(--color-primary) font-mono">{{ selected.progressPercent }}%</p>
                                </div>
                                <div class="stat-tile">
                                    <p class="stat-label">Done</p>
                                    <p class="stat-value text-(--color-success) font-mono">
                                        {{ selected.completedTasks }} / {{ selected.totalTasks }}
                                    </p>
                                </div>
                                <div class="stat-tile">
                                    <p class="stat-label">Target</p>
                                    <p class="stat-value text-(--text-heading) font-mono">
                                        {{ formatDate(selected.targetCompletionDate) }}
                                    </p>
                                </div>
                            </div>
                        </article>

                        <div v-if="loadingDetail" class="py-12 flex flex-col items-center gap-3">
                            <span class="w-6 h-6 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                            <span class="text-xxs text-(--text-muted)">Loading tasks...</span>
                        </div>

                        <article v-else v-for="group in groupedTasks" :key="group.role"
                            class="glass-card rounded-2xl p-5 space-y-3">
                            <header class="flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-(--text-heading) flex items-center gap-2">
                                    <i :class="['ti', OWNER_ROLE_META[group.role].icon]" />
                                    {{ OWNER_ROLE_META[group.role].label }}
                                </h3>
                                <span class="text-xxs text-(--text-muted) font-mono">
                                    {{ group.tasks.filter(t => t.status === 'completed').length }} / {{ group.tasks.length }}
                                </span>
                            </header>

                            <ul class="space-y-2">
                                <li v-for="t in group.tasks" :key="t.id"
                                    class="rounded-lg border border-(--border-color) bg-(--bg-muted)/30 p-3 flex items-start gap-3">
                                    <button type="button" class="task-checkbox"
                                        :class="taskCheckboxClass(t.status)"
                                        :disabled="!canWrite || t.status === 'completed' || busyTaskId === t.id"
                                        :title="canWrite ? 'Mark complete' : 'No permission'"
                                        @click="markComplete(t)">
                                        <i v-if="busyTaskId === t.id" class="ti ti-loader-2 animate-spin" />
                                        <i v-else-if="t.status === 'completed'" class="ti ti-check" />
                                        <i v-else-if="t.status === 'skipped'" class="ti ti-minus" />
                                    </button>

                                    <div class="flex-1 min-w-0">
                                        <p class="text-xs font-semibold text-(--text-heading)"
                                            :class="{ 'line-through text-(--text-muted)': t.status === 'completed' || t.status === 'skipped' }">
                                            {{ t.title }}
                                        </p>
                                        <p v-if="t.description" class="text-xxs text-(--text-muted) mt-0.5">
                                            {{ t.description }}
                                        </p>
                                        <div class="flex items-center gap-2 mt-1.5 flex-wrap">
                                            <Badge :variant="TASK_STATUS_META[t.status].variant" :dot="true">
                                                {{ TASK_STATUS_META[t.status].label }}
                                            </Badge>
                                            <span v-if="t.dueDate" class="text-xxs font-mono"
                                                :class="dueClass(t)">
                                                <i class="ti ti-calendar-event text-[10px]" />
                                                {{ formatDate(t.dueDate) }}
                                            </span>
                                        </div>
                                    </div>

                                    <button v-if="canWrite && t.status !== 'completed' && t.status !== 'skipped'"
                                        type="button" class="action-btn" title="Skip task"
                                        :disabled="busyTaskId === t.id"
                                        @click="markSkipped(t)">
                                        <i class="ti ti-player-skip-forward text-xs" />
                                    </button>
                                </li>
                            </ul>
                        </article>
                    </template>
                </section>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import Badge from '~/components/Badge.vue'
import {
    useOnboarding,
    OWNER_ROLE_META,
    TASK_STATUS_META,
    CHECKLIST_STATUS_META,
    type OnboardingChecklist,
    type OnboardingTask,
    type OnboardingOwnerRole,
    type ChecklistStatus,
} from '~/composables/useOnboarding'
import { useToast } from '~/composables/useToast'
import { useAuthStore } from '~/stores/auth'

definePageMeta({ breadcrumb: 'Onboarding' })

const onboarding = useOnboarding()
const toast = useToast()
const authStore = useAuthStore()

const canRead = computed(() => authStore.hasPermission('hrm.onboarding.read'))
const canWrite = computed(() => authStore.hasPermission('hrm.onboarding.write'))

const loading = ref(false)
const loadingDetail = ref(false)
const busyTaskId = ref<string | null>(null)
const checklists = ref<OnboardingChecklist[]>([])
const selected = ref<OnboardingChecklist | null>(null)
const selectedId = ref<string | null>(null)
const pagination = reactive({ page: 1, limit: 20, total: 0, totalPages: 1 })
const statusFilter = ref<'' | ChecklistStatus>('')

const kpis = computed(() => {
    const total = checklists.value.length
    const inProgress = checklists.value.filter(c => c.status === 'in_progress').length
    const done = checklists.value.filter(c => c.status === 'completed').length
    const overdue = checklists.value.filter(c => {
        if (!c.targetCompletionDate || c.status === 'completed') return false
        return new Date(c.targetCompletionDate).getTime() < Date.now()
    }).length
    return [
        { key: 'total',     label: 'Checklists', value: total,      text: 'text-(--text-heading)' },
        { key: 'progress',  label: 'In progress', value: inProgress, text: 'text-(--color-info)' },
        { key: 'completed', label: 'Completed',   value: done,       text: 'text-(--color-success)' },
        { key: 'overdue',   label: 'Overdue',     value: overdue,    text: 'text-(--color-danger)' },
    ]
})

const ROLE_ORDER: OnboardingOwnerRole[] = ['hr', 'it', 'finance', 'manager', 'facilities', 'other']

const groupedTasks = computed(() => {
    const tasks = selected.value?.tasks ?? []
    const groups: { role: OnboardingOwnerRole; tasks: OnboardingTask[] }[] = []
    for (const role of ROLE_ORDER) {
        const roleTasks = tasks
            .filter(t => t.ownerRole === role)
            .sort((a, b) => a.sortOrder - b.sortOrder)
        if (roleTasks.length) groups.push({ role, tasks: roleTasks })
    }
    return groups
})

const formatDate = (iso: string | null) => {
    if (!iso) return '—'
    const d = new Date(iso)
    return isNaN(d.getTime()) ? iso : d.toISOString().slice(0, 10)
}

const progressTint = (pct: number) => {
    if (pct >= 100) return 'bg-(--color-success)'
    if (pct >= 50) return 'bg-(--color-primary)'
    if (pct > 0) return 'bg-(--color-info)'
    return 'bg-(--text-muted)/40'
}

const taskCheckboxClass = (status: OnboardingTask['status']) => {
    if (status === 'completed') return 'task-checkbox--done'
    if (status === 'skipped') return 'task-checkbox--skipped'
    return 'task-checkbox--open'
}

const dueClass = (t: OnboardingTask) => {
    if (!t.dueDate || t.status === 'completed' || t.status === 'skipped') return 'text-(--text-muted)'
    const ms = new Date(t.dueDate).getTime() - Date.now()
    if (ms < 0) return 'text-(--color-danger)'
    if (ms < 3 * 86_400_000) return 'text-(--color-warning)'
    return 'text-(--text-muted)'
}

const load = async () => {
    if (!canRead.value) return
    loading.value = true
    try {
        const res = await onboarding.listChecklists({
            page: pagination.page,
            limit: pagination.limit,
            status: statusFilter.value || undefined,
        })
        checklists.value = res.data
        const p = res.pagination
        if (p) {
            pagination.total = p.total ?? 0
            pagination.totalPages = p.totalPages ?? 1
            pagination.page = p.page ?? 1
            pagination.limit = p.limit ?? pagination.limit
        }
        if (selectedId.value && !checklists.value.some(c => c.id === selectedId.value)) {
            selected.value = null
            selectedId.value = null
        } else if (!selectedId.value && checklists.value.length) {
            select(checklists.value[0]!)
        }
    } catch (err: any) {
        toast.error('Failed to load checklists', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const setStatusFilter = (s: '' | ChecklistStatus) => {
    if (statusFilter.value === s) return
    statusFilter.value = s
    pagination.page = 1
    load()
}

const select = async (c: OnboardingChecklist) => {
    selectedId.value = c.id
    selected.value = c
    loadingDetail.value = true
    try {
        const res = await onboarding.showChecklist(c.id)
        selected.value = res.data
        const idx = checklists.value.findIndex(x => x.id === res.data.id)
        if (idx >= 0) checklists.value[idx] = { ...checklists.value[idx]!, ...res.data, tasks: undefined }
    } catch (err: any) {
        toast.error('Failed to load tasks', err?.data?.message)
    } finally {
        loadingDetail.value = false
    }
}

const transition = async (t: OnboardingTask, status: 'completed' | 'skipped') => {
    if (!selected.value || !canWrite.value) return
    busyTaskId.value = t.id
    try {
        const res = await onboarding.transitionTask(t.id, status)
        const updated = res.data
        const tasks = selected.value.tasks ?? []
        const idx = tasks.findIndex(x => x.id === updated.id)
        if (idx >= 0) tasks[idx] = updated
        // Refresh checklist counters from the server so progress stays accurate.
        const fresh = await onboarding.showChecklist(selected.value.id)
        const incoming = fresh.data
        selected.value = { ...incoming, tasks }
        const listIdx = checklists.value.findIndex(x => x.id === incoming.id)
        if (listIdx >= 0) {
            const { tasks: _drop, ...rest } = incoming
            void _drop
            checklists.value[listIdx] = { ...checklists.value[listIdx]!, ...rest }
        }
        toast.success(status === 'completed' ? 'Task completed' : 'Task skipped')
    } catch (err: any) {
        toast.error('Update failed', err?.data?.message)
    } finally {
        busyTaskId.value = null
    }
}

const markComplete = (t: OnboardingTask) => transition(t, 'completed')
const markSkipped = (t: OnboardingTask) => transition(t, 'skipped')

onMounted(load)
</script>

<style scoped>
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
.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 26px;
    height: 26px;
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
.action-btn:disabled { opacity: 0.5; cursor: not-allowed; }
.stat-tile {
    padding: 0.75rem;
    border-radius: 0.5rem;
    background: var(--bg-muted);
    border: 1px solid var(--border-color);
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}
.stat-label {
    font-size: 0.625rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    font-weight: 700;
    color: var(--text-muted);
}
.stat-value {
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.25;
}
.task-checkbox {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 6px;
    border: 1.5px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-body);
    cursor: pointer;
    flex-shrink: 0;
    margin-top: 2px;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
.task-checkbox:hover:not(:disabled) {
    border-color: rgb(var(--color-success-rgb) / 0.6);
    color: var(--color-success);
}
.task-checkbox:disabled { cursor: not-allowed; }
.task-checkbox--done {
    background: var(--color-success);
    border-color: var(--color-success);
    color: #fff;
}
.task-checkbox--skipped {
    background: var(--bg-muted);
    border-color: var(--border-color);
    color: var(--text-muted);
}
</style>
