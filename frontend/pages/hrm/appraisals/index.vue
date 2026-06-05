<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Page header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Performance appraisals</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        <span v-if="pagination.total">
                            Tracking {{ pagination.total.toLocaleString() }} appraisal{{ pagination.total === 1 ? '' : 's' }}
                            across cycles — ratings, strengths, growth areas, and OKR goals.
                        </span>
                        <span v-else>Cycle reviews with ratings, strengths, growth areas, and OKR goals.</span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <!-- View toggle: persisted in localStorage so refresh keeps the user's choice. -->
                    <div class="inline-flex items-center bg-(--bg-card) border border-(--border-color) rounded-lg p-1">
                        <button v-for="opt in (['table', 'grid'] as const)" :key="opt" type="button"
                            class="px-3 py-1.5 rounded-md text-xs font-semibold inline-flex items-center gap-1.5 transition-colors"
                            :class="view === opt
                                ? 'bg-(--color-primary-subtle) text-(--color-primary)'
                                : 'text-(--text-muted) hover:text-(--text-heading)'" @click="setView(opt)">
                            <i :class="['ti', opt === 'table' ? 'ti-list' : 'ti-layout-grid']" />
                            {{ opt === 'table' ? 'List' : 'Grid' }}
                        </button>
                    </div>

                    <button class="btn btn-ghost text-xs" disabled>
                        <i class="ti ti-download" />Export
                    </button>
                    <NuxtLink v-if="canWrite" to="/approvals/forms/appraisal" class="btn btn-primary text-xs">
                        <i class="ti ti-external-link" />Submit via eApprovals
                    </NuxtLink>
                </div>
            </header>

            <!-- Filters -->
            <section class="glass-card rounded-xl p-4">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="relative md:col-span-5">
                        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                        <input v-model="filters.cycle" type="search" placeholder="Search cycle (e.g. 2026-Q2)..."
                            class="form-control pl-9" />
                    </div>

                    <div class="relative md:col-span-3">
                        <i class="ti ti-user absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
                        <select v-model="filters.employeeId" class="form-control pl-9 appearance-none">
                            <option :value="''">All employees</option>
                            <option v-for="e in employees" :key="e.id" :value="e.id">
                                {{ e.fullName }} ({{ e.employeeId }})
                            </option>
                        </select>
                    </div>

                    <div class="md:col-span-4 flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1 overflow-x-auto">
                        <button v-for="s in (['', 'draft', 'submitted', 'reviewed', 'closed'] as const)"
                            :key="s || 'all'" type="button"
                            class="flex-1 px-3 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
                            :class="filters.status === s ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                            @click="filters.status = s">
                            {{ s || 'all' }}
                        </button>
                    </div>
                </div>
            </section>

            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span
                    class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading appraisals...</span>
            </div>

            <div v-else-if="appraisals.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-clipboard-list text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No appraisals</h4>
                <p class="text-xs text-(--text-muted) mt-1">Open the first review cycle for an employee.</p>
            </div>

            <!-- Table view (default) -->
            <section v-else-if="view === 'table'" class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr
                                class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                <th class="px-4 py-3 font-semibold">Employee</th>
                                <th class="px-4 py-3 font-semibold">Reviewer</th>
                                <th class="px-4 py-3 font-semibold font-mono">Cycle</th>
                                <th class="px-4 py-3 font-semibold">Period</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Rating</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-color)">
                            <tr v-for="a in appraisals" :key="a.id" class="hover:bg-(--bg-muted) transition-colors">
                                <td class="px-4 py-3 text-xs">
                                    <div class="font-semibold text-(--text-heading)">
                                        {{ a.employee?.fullName || '—' }}
                                    </div>
                                    <div class="text-xxs text-(--text-muted) font-mono">
                                        {{ a.employee?.employeeId || '' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-xs">
                                    <div class="font-semibold text-(--text-heading)">
                                        {{ a.reviewer?.fullName || '—' }}
                                    </div>
                                    <div class="text-xxs text-(--text-muted) font-mono">
                                        {{ a.reviewer?.employeeId || '' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">{{ a.cycle }}</td>
                                <td class="px-4 py-3 text-xxs font-mono whitespace-nowrap">
                                    <span>{{ formatDate(a.periodStart) }}</span>
                                    <span class="text-(--text-muted)"> → {{ formatDate(a.periodEnd) }}</span>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-right">
                                    <span v-if="a.overallRating != null" class="font-semibold"
                                        :class="ratingColor(a.overallRating)">
                                        {{ a.overallRating.toFixed(2) }}
                                    </span>
                                    <span v-else class="text-(--text-muted)">
                                        {{ a.status === 'reviewed' || a.status === 'closed' ? '••••' : '—' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge :variant="statusVariant(a.status)" :dot="true">
                                        {{ a.status }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button" class="action-trigger"
                                        :class="{ 'action-trigger-open': actionMenu.open && actionMenu.appraisal?.id === a.id }"
                                        title="Actions" @click.stop="openActionMenu(a, $event)">
                                        <i class="ti ti-dots-vertical" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Grid view -->
            <section v-else-if="view === 'grid'" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <div v-for="a in appraisals" :key="a.id"
                    class="glass-card rounded-2xl p-5 flex flex-col justify-between relative transition-all duration-150 border border-(--border-color) hover:border-(--color-primary)/40">
                    <header class="flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full bg-(--color-primary-subtle) text-(--color-primary) flex items-center justify-center font-bold text-sm shrink-0">
                                {{ initials(a.employee?.fullName) }}
                            </div>
                            <div class="min-w-0">
                                <h3 class="text-xs font-semibold text-(--text-heading) truncate">{{ a.employee?.fullName || '—' }}</h3>
                                <p class="text-xxs text-(--text-muted) font-mono truncate">{{ a.employee?.employeeId || '' }}</p>
                            </div>
                        </div>
                        <button type="button" class="action-trigger shrink-0"
                            :class="{ 'action-trigger-open': actionMenu.open && actionMenu.appraisal?.id === a.id }"
                            title="Actions" @click.stop="openActionMenu(a, $event)">
                            <i class="ti ti-dots-vertical" />
                        </button>
                    </header>

                    <div class="my-4 space-y-2 border-t border-(--border-color)/50 pt-3">
                        <div class="flex justify-between text-xxs gap-2">
                            <span class="text-(--text-muted) shrink-0">Reviewer:</span>
                            <span class="font-semibold text-(--text-heading) truncate">{{ a.reviewer?.fullName || 'Self Review' }}</span>
                        </div>
                        <div class="flex justify-between text-xxs">
                            <span class="text-(--text-muted)">Cycle:</span>
                            <span class="font-mono text-(--text-heading)">{{ a.cycle }}</span>
                        </div>
                        <div class="flex justify-between text-xxs">
                            <span class="text-(--text-muted)">Period:</span>
                            <span class="font-mono text-(--text-heading) whitespace-nowrap">
                                {{ formatDate(a.periodStart) }} → {{ formatDate(a.periodEnd) }}
                            </span>
                        </div>
                    </div>

                    <footer class="flex items-center justify-between border-t border-(--border-color)/50 pt-3 mt-auto">
                        <Badge :variant="statusVariant(a.status)" :dot="true">{{ a.status }}</Badge>
                        <div class="text-right">
                            <p class="text-[10px] text-(--text-muted) uppercase font-bold tracking-wider leading-none mb-1">Rating</p>
                            <span v-if="a.overallRating != null" class="font-mono text-xs font-semibold"
                                :class="ratingColor(a.overallRating)">
                                {{ a.overallRating.toFixed(2) }}
                            </span>
                            <span v-else class="text-xxs text-(--text-muted)">
                                {{ a.status === 'reviewed' || a.status === 'closed' ? '••••' : '—' }}
                            </span>
                        </div>
                    </footer>
                </div>
            </section>

            <!-- Pagination (rendered below list/grid views) -->
            <Pagination :page="pagination.page" :limit="pagination.limit" :total="pagination.total"
                :total-pages="pagination.totalPages" @update:page="(p) => { pagination.page = p; loadAppraisals() }"
                @update:limit="(l) => { pagination.limit = l; pagination.page = 1; loadAppraisals() }" />


            <!-- Create / edit modal -->
            <div v-if="showModal"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div
                    class="glass-card rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                    <header class="flex items-center justify-between mb-5">
                        <h3 class="text-base font-semibold text-(--text-heading)">Edit appraisal</h3>
                        <button class="topbar-btn" @click="closeModal"><i class="ti ti-x" /></button>
                    </header>

                    <form class="form-grid" @submit.prevent="saveAppraisal">
                        <div>
                            <label class="form-label form-label-required">Employee</label>
                            <select v-model="form.employee_id" required class="form-control" disabled>
                                <option value="" disabled>Select employee...</option>
                                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.fullName }} ({{
                                    e.employeeId }})</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Reviewer</label>
                            <select v-model="form.reviewer_id" class="form-control">
                                <option :value="''">— Self review</option>
                                <option v-for="e in employees" :key="e.id" :value="e.id"
                                    :disabled="e.id === form.employee_id">
                                    {{ e.fullName }} ({{ e.employeeId }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label form-label-required">Cycle</label>
                            <input v-model="form.cycle" type="text" required class="form-control font-mono"
                                placeholder="2026-Q2" />
                        </div>
                        <div></div>

                        <div>
                            <label class="form-label form-label-required">Period start</label>
                            <input v-model="form.period_start" type="date" required class="form-control" />
                        </div>
                        <div>
                            <label class="form-label form-label-required">Period end</label>
                            <input v-model="form.period_end" type="date" required class="form-control" />
                        </div>

                        <div class="form-grid-full">
                            <label class="form-label">Strengths</label>
                            <textarea v-model="form.strengths" rows="3" class="form-control"
                                placeholder="Consistent shipping, mentors juniors..." />
                        </div>

                        <div class="form-grid-full">
                            <label class="form-label">Areas for improvement</label>
                            <textarea v-model="form.improvements" rows="3" class="form-control"
                                placeholder="Cross-team alignment, deeper specs..." />
                        </div>

                        <div class="form-grid-full">
                            <div class="flex items-center justify-between mb-2">
                                <label class="form-label mb-0">Goals (OKR-style)</label>
                                <button type="button" class="btn btn-ghost text-xs px-2 py-1" @click="addGoal">
                                    <i class="ti ti-plus" />Add goal
                                </button>
                            </div>
                            <div v-if="form.goals.length === 0"
                                class="rounded-lg bg-(--bg-muted) border border-dashed border-(--border-color) p-4 text-xxs text-(--text-muted) text-center">
                                No goals yet. Add one to track OKRs.
                            </div>
                            <div v-else class="space-y-2">
                                <div v-for="(goal, idx) in form.goals" :key="idx"
                                    class="rounded-lg border border-(--border-color) p-3 grid grid-cols-12 gap-2 items-center">
                                    <input v-model="goal.title" type="text" required placeholder="Goal title"
                                        class="form-control col-span-6" />
                                    <select v-model="goal.status" class="form-control col-span-3">
                                        <option value="pending">Pending</option>
                                        <option value="in_progress">In progress</option>
                                        <option value="achieved">Achieved</option>
                                        <option value="missed">Missed</option>
                                    </select>
                                    <input v-model="goal.due" type="date" class="form-control col-span-2" />
                                    <button type="button"
                                        class="col-span-1 text-(--color-danger) hover:text-(--color-danger) p-2"
                                        @click="removeGoal(idx)" title="Remove">
                                        <i class="ti ti-trash" />
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div v-if="formError" class="form-grid-full form-error">{{ formError }}</div>

                        <footer class="form-grid-full pt-4 border-t border-(--border-color) flex justify-end gap-2">
                            <button type="button" class="btn btn-ghost text-xs" @click="closeModal">Cancel</button>
                            <button type="submit" class="btn btn-primary text-xs" :disabled="saving">
                                <i class="ti ti-device-floppy" />{{ saving ? 'Saving...' : 'Save' }}
                            </button>
                        </footer>
                    </form>
                </div>
            </div>

            <!-- Review modal -->
            <div v-if="showReviewModal"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="glass-card rounded-2xl w-full max-w-lg p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                    <header class="flex items-center justify-between mb-5">
                        <div>
                            <h3 class="text-base font-semibold text-(--text-heading)">Reviewer summary</h3>
                            <p class="text-xxs text-(--text-muted) mt-1">{{ reviewing?.employee?.fullName }} · {{
                                reviewing?.cycle }}</p>
                        </div>
                        <button class="topbar-btn" @click="showReviewModal = false"><i class="ti ti-x" /></button>
                    </header>

                    <form class="form-stack" @submit.prevent="submitReview">
                        <div>
                            <label class="form-label form-label-required">Overall rating (0.00 – 5.00)</label>
                            <input v-model.number="reviewForm.overall_rating" type="number" min="0" max="5" step="0.01"
                                required class="form-control font-mono" />
                        </div>
                        <div>
                            <label class="form-label">Strengths (reviewer-confirmed)</label>
                            <textarea v-model="reviewForm.strengths" rows="3" class="form-control" />
                        </div>
                        <div>
                            <label class="form-label">Growth areas</label>
                            <textarea v-model="reviewForm.improvements" rows="3" class="form-control" />
                        </div>

                        <div v-if="reviewError" class="form-error">{{ reviewError }}</div>

                        <footer class="pt-4 border-t border-(--border-color) flex justify-end gap-2">
                            <button type="button" class="btn btn-ghost text-xs"
                                @click="showReviewModal = false">Cancel</button>
                            <button type="submit" class="btn btn-primary text-xs"
                                :disabled="reviewing === null || reviewSaving">
                                <i class="ti ti-stars" />{{ reviewSaving ? 'Saving...' : 'Submit review' }}
                            </button>
                        </footer>
                    </form>
                </div>
            </div>

            <!-- Action dropdown -->
            <div v-if="actionMenu.open && actionMenu.appraisal"
                class="fixed z-50 glass-card rounded-lg shadow-(--shadow-lg) bg-(--bg-card) border border-(--border-color) py-1 min-w-[180px]"
                :style="{ top: actionMenu.y + 'px', left: actionMenu.x + 'px' }" @click.stop>
                <button class="action-item" @click="actionEdit">
                    <i class="ti ti-pencil" /> Edit / view
                </button>
                <template v-if="canWrite && actionMenu.appraisal.status === 'draft'">
                    <hr class="my-1 border-(--border-color)" />
                    <button class="action-item action-item-primary" @click="actionSubmit">
                        <i class="ti ti-send" /> Submit
                    </button>
                </template>
                <template v-if="canWrite && actionMenu.appraisal.status === 'submitted'">
                    <hr class="my-1 border-(--border-color)" />
                    <button class="action-item action-item-info" @click="actionReview">
                        <i class="ti ti-stars" /> Review
                    </button>
                </template>
                <template v-if="canWrite && actionMenu.appraisal.status === 'reviewed'">
                    <hr class="my-1 border-(--border-color)" />
                    <button class="action-item action-item-warning" @click="actionClose">
                        <i class="ti ti-lock" /> Close
                    </button>
                </template>
                <template v-if="canWrite && actionMenu.appraisal.status !== 'closed'">
                    <hr class="my-1 border-(--border-color)" />
                    <button class="action-item action-item-danger" @click="actionArchive">
                        <i class="ti ti-trash" /> Archive
                    </button>
                </template>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'
import { useDateFormat } from '~/composables/useDateFormat'
import { useToast } from '~/composables/useToast'

interface EmployeeLite { id: string; employeeId: string; fullName: string }

interface Goal { title: string; status?: 'pending' | 'in_progress' | 'achieved' | 'missed'; due?: string | null }

type AppraisalStatus = 'draft' | 'submitted' | 'reviewed' | 'closed'

// View toggle: 'table' (default — denser) or 'grid' (card layout). Persisted
// in localStorage so a refresh keeps the user's last choice.
type View = 'table' | 'grid'
const VIEW_KEY = 'appraisals_view'
const view = ref<View>('table')

const setView = (v: View) => {
    view.value = v
    if (import.meta.client) localStorage.setItem(VIEW_KEY, v)
}

const initials = (name: string | null | undefined): string => {
    if (!name) return '?'
    const parts = name.trim().split(/\s+/).filter(Boolean).slice(0, 2)
    return parts.map(p => p[0]?.toUpperCase() || '').join('') || '?'
}

interface Appraisal {
    id: string
    employeeId: string
    reviewerId: string | null
    cycle: string
    periodStart: string
    periodEnd: string
    overallRating: number | null
    strengths: string | null
    improvements: string | null
    goals: Goal[] | null
    status: AppraisalStatus
    submittedAt: string | null
    reviewedAt: string | null
    employee?: EmployeeLite
    reviewer?: EmployeeLite
}
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

const api = useApi()
const authStore = useAuthStore()
const toast = useToast()
const { formatDate } = useDateFormat()
const canWrite = computed(() => authStore.hasPermission('hrm.performance.write'))

const appraisals = ref<Appraisal[]>([])
const employees = ref<EmployeeLite[]>([])
const loading = ref(false)

const pagination = reactive({ page: 1, limit: 15, total: 0, totalPages: 1 })
const filters = reactive({
    employeeId: '',
    cycle: '',
    status: '' as '' | AppraisalStatus
})

const showModal = ref(false)
const editing = ref<Appraisal | null>(null)
const saving = ref(false)
const formError = ref<string | null>(null)
const form = reactive({
    employee_id: '',
    reviewer_id: '',
    cycle: '',
    period_start: '',
    period_end: '',
    strengths: '',
    improvements: '',
    goals: [] as Goal[]
})

const showReviewModal = ref(false)
const reviewing = ref<Appraisal | null>(null)
const reviewSaving = ref(false)
const reviewError = ref<string | null>(null)
const reviewForm = reactive({
    overall_rating: 0,
    strengths: '',
    improvements: ''
})

const actionMenu = reactive({
    open: false,
    x: 0,
    y: 0,
    appraisal: null as Appraisal | null
})

const statusVariant = (s: AppraisalStatus): 'secondary' | 'info' | 'warning' | 'success' => {
    if (s === 'submitted') return 'info'
    if (s === 'reviewed') return 'warning'
    if (s === 'closed') return 'success'
    return 'secondary'
}

const ratingColor = (n: number) => {
    if (n >= 4) return 'text-(--color-success)'
    if (n >= 3) return 'text-(--color-primary)'
    if (n >= 2) return 'text-(--color-warning)'
    return 'text-(--color-danger)'
}

const loadLookups = async () => {
    try {
        const e = await api.get<Paginated<EmployeeLite>>('/employees?limit=100')
        employees.value = e.data
    } catch (err) {
        console.error('Failed to load employees', err)
    }
}

const loadAppraisals = async () => {
    loading.value = true
    try {
        const q = new URLSearchParams({ page: String(pagination.page), limit: String(pagination.limit) })
        if (filters.employeeId) q.set('employeeId', filters.employeeId)
        if (filters.cycle) q.set('cycle', filters.cycle)
        if (filters.status) q.set('status', filters.status)

        const res = await api.get<Paginated<Appraisal>>(`/appraisals?${q.toString()}`)
        appraisals.value = res.data
        pagination.total = res.pagination.total
        pagination.totalPages = res.pagination.totalPages
    } catch (err) {
        console.error('Failed to load appraisals', err)
        appraisals.value = []
    } finally {
        loading.value = false
    }
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(() => [filters.employeeId, filters.cycle, filters.status], () => {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        pagination.page = 1
        loadAppraisals()
    }, 300)
})

const openEditModal = (a: Appraisal) => {
    editing.value = a
    Object.assign(form, {
        employee_id: a.employeeId,
        reviewer_id: a.reviewerId ?? '',
        cycle: a.cycle,
        period_start: a.periodStart,
        period_end: a.periodEnd,
        strengths: a.strengths ?? '',
        improvements: a.improvements ?? '',
        goals: Array.isArray(a.goals) ? a.goals.map(g => ({ ...g })) : []
    })
    formError.value = null
    showModal.value = true
}

const closeModal = () => { showModal.value = false; editing.value = null }

const addGoal = () => { form.goals.push({ title: '', status: 'pending', due: '' }) }
const removeGoal = (idx: number) => { form.goals.splice(idx, 1) }

const saveAppraisal = async () => {
    if (!editing.value) return
    saving.value = true
    formError.value = null
    try {
        const payload: Record<string, any> = {
            cycle: form.cycle,
            period_start: form.period_start,
            period_end: form.period_end,
            strengths: form.strengths || null,
            improvements: form.improvements || null,
            reviewer_id: form.reviewer_id || null,
            goals: form.goals.filter(g => g.title).map(g => ({
                title: g.title,
                status: g.status || 'pending',
                due: g.due || null
            }))
        }

        await api.put(`/appraisals/${editing.value.id}`, payload)
        showModal.value = false
        await loadAppraisals()
    } catch (err: any) {
        formError.value = err.data?.message || 'Failed to save appraisal.'
    } finally {
        saving.value = false
    }
}

const submit = async (a: Appraisal) => {
    if (!confirm(`Submit ${a.employee?.fullName}'s appraisal for review?`)) return
    try {
        await api.post(`/appraisals/${a.id}/submit`)
        await loadAppraisals()
    } catch (err: any) {
        toast.error('Failed to submit appraisal.', err?.data?.message)
    }
}

const openReviewModal = (a: Appraisal) => {
    reviewing.value = a
    reviewForm.overall_rating = a.overallRating ?? 0
    reviewForm.strengths = a.strengths ?? ''
    reviewForm.improvements = a.improvements ?? ''
    reviewError.value = null
    showReviewModal.value = true
}

const submitReview = async () => {
    if (!reviewing.value) return
    reviewSaving.value = true
    reviewError.value = null
    try {
        await api.post(`/appraisals/${reviewing.value.id}/review`, {
            overall_rating: reviewForm.overall_rating,
            strengths: reviewForm.strengths || null,
            improvements: reviewForm.improvements || null
        })
        showReviewModal.value = false
        reviewing.value = null
        await loadAppraisals()
    } catch (err: any) {
        reviewError.value = err.data?.message || 'Failed to submit review.'
    } finally {
        reviewSaving.value = false
    }
}

const closeAppraisal = async (a: Appraisal) => {
    if (!confirm(`Close ${a.employee?.fullName}'s appraisal? Closed records are immutable.`)) return
    try {
        await api.post(`/appraisals/${a.id}/close`)
        await loadAppraisals()
    } catch (err: any) {
        toast.error('Failed to close appraisal.', err?.data?.message)
    }
}

const archive = async (a: Appraisal) => {
    if (!confirm(`Archive ${a.employee?.fullName}'s appraisal?`)) return
    try {
        await api.delete(`/appraisals/${a.id}`)
        await loadAppraisals()
    } catch (err: any) {
        toast.error('Failed to archive appraisal.', err?.data?.message)
    }
}

const openActionMenu = (a: Appraisal, ev: MouseEvent) => {
    const rect = (ev.currentTarget as HTMLElement).getBoundingClientRect()
    const menuWidth = 200
    const menuMaxHeight = 280
    const left = Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8)
    const wouldOverflow = rect.bottom + menuMaxHeight + 8 > window.innerHeight
    actionMenu.appraisal = a
    actionMenu.x = Math.max(8, left)
    actionMenu.y = wouldOverflow ? rect.top - menuMaxHeight - 6 : rect.bottom + 6
    actionMenu.open = true
}

const closeActionMenu = () => { actionMenu.open = false; actionMenu.appraisal = null }

const actionEdit = () => { const a = actionMenu.appraisal; closeActionMenu(); if (a) openEditModal(a) }
const actionSubmit = async () => { const a = actionMenu.appraisal; closeActionMenu(); if (a) await submit(a) }
const actionReview = () => { const a = actionMenu.appraisal; closeActionMenu(); if (a) openReviewModal(a) }
const actionClose = async () => { const a = actionMenu.appraisal; closeActionMenu(); if (a) await closeAppraisal(a) }
const actionArchive = async () => { const a = actionMenu.appraisal; closeActionMenu(); if (a) await archive(a) }

onMounted(async () => {
    if (import.meta.client) {
        document.addEventListener('click', closeActionMenu)
        const saved = localStorage.getItem('appraisals_view')
        if (saved === 'table' || saved === 'grid') {
            view.value = saved
        }
    }
    await Promise.all([loadLookups(), loadAppraisals()])
})

onBeforeUnmount(() => {
    if (import.meta.client) {
        document.removeEventListener('click', closeActionMenu)
    }
})
</script>

<style scoped>
.topbar-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    color: var(--text-muted);
    cursor: pointer;
}

.topbar-btn:hover {
    background: var(--bg-muted);
    color: var(--text-heading);
}

.action-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    color: var(--text-muted);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}

.action-trigger:hover {
    background: var(--bg-muted);
    color: var(--text-heading);
}

.action-trigger-open {
    background: var(--bg-muted);
    color: var(--color-primary);
}

.action-item {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
    color: var(--text-heading);
    text-align: left;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}

.action-item:hover {
    background: var(--bg-muted);
}

.action-item-primary {
    color: var(--color-primary);
    font-weight: 600;
}

.action-item-primary:hover {
    background: var(--color-primary-subtle);
}

.action-item-info {
    color: var(--color-info, var(--color-primary));
}

.action-item-info:hover {
    background: var(--color-info-subtle, var(--color-primary-subtle));
}

.action-item-warning {
    color: var(--color-warning);
}

.action-item-warning:hover {
    background: var(--color-warning-subtle, rgb(var(--color-warning-rgb, 250 173 20) / 0.1));
}

.action-item-danger {
    color: var(--color-danger);
}

.action-item-danger:hover {
    background: var(--color-danger-subtle);
}

</style>
