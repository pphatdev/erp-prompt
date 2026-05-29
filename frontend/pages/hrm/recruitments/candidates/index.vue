<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Toolbar -->
            <header class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-(--text-heading)">Candidate Pipeline</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        <span v-if="activeVacancy">For <span class="font-semibold text-(--text-heading)">{{
                                activeVacancy.title }}</span>.</span>
                        <span v-else>Drag a card across columns to advance an applicant.</span>
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <div class="inline-flex items-center bg-(--bg-card) border border-(--border-color) rounded-lg p-1">
                        <button type="button"
                            class="px-3 py-1.5 rounded-md text-xs font-semibold inline-flex items-center gap-1.5 bg-(--color-primary-subtle) text-(--color-primary)">
                            <i class="ti ti-layout-kanban" /> Board
                        </button>
                        <NuxtLink to="/hrm/recruitments/applications"
                            class="px-3 py-1.5 rounded-md text-xs font-semibold inline-flex items-center gap-1.5 text-(--text-muted) hover:text-(--text-heading) transition-colors">
                            <i class="ti ti-list" /> List
                        </NuxtLink>
                    </div>

                    <NuxtLink v-if="canWrite" to="/hrm/applications/new" class="btn btn-primary text-xs">
                        <i class="ti ti-user-plus" /> Add candidate
                    </NuxtLink>
                    <NuxtLink v-if="canWrite" to="/hrm/recruitments/vacancies" class="btn btn-ghost text-xs">
                        <i class="ti ti-plus" /> Post New Job
                    </NuxtLink>
                </div>
            </header>

            <!-- Filters -->
            <section class="glass-card rounded-xl p-3">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="relative md:col-span-7">
                        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                        <input v-model="filters.search" type="search" placeholder="Search by name or email..."
                            class="form-control pl-9">
                    </div>
                    <div class="md:col-span-5">
                        <select v-model="filters.jobVacancyId" class="form-control">
                            <option :value="''">All vacancies</option>
                            <option v-for="v in vacancies" :key="v.id" :value="v.id">{{ v.title }}</option>
                        </select>
                    </div>
                </div>
            </section>

            <!-- Loading -->
            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span
                    class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading pipeline...</span>
            </div>

            <!-- Board -->
            <section v-else class="kanban-scroller -mx-2 px-2 pb-4 overflow-x-auto">
                <div class="flex gap-4 min-w-max">
                    <div v-for="col in columns" :key="col.status" class="kanban-column flex flex-col gap-3"
                        :class="{ 'kanban-column--dragover': dragOverColumn === col.status }"
                        @dragover.prevent="onColumnDragOver(col.status, $event)"
                        @dragleave="onColumnDragLeave(col.status)" @drop="onColumnDrop(col.status)">
                        <header class="flex items-center justify-between px-1">
                            <div class="flex items-center gap-2">
                                <span
                                    class="text-xxs font-bold uppercase tracking-wider px-2.5 py-1 rounded-full border"
                                    :class="columnHeaderClass(col.status)">
                                    {{ col.label }}
                                    <span class="opacity-70">({{ grouped[col.status]?.length || 0 }})</span>
                                </span>
                            </div>
                            <span v-if="movingId && col.status === pendingDropStatus"
                                class="text-xxs text-(--text-muted) inline-flex items-center gap-1">
                                <span
                                    class="w-3 h-3 rounded-full border-2 border-(--color-primary)/30 border-t-(--color-primary) animate-spin" />
                                Moving...
                            </span>
                        </header>

                        <div class="kanban-list flex flex-col p-1 gap-3 pr-1">
                            <article v-for="a in grouped[col.status]" :key="a.id"
                                class="kanban-card glass-card rounded-xl p-3 shadow-sm transition-all cursor-grab"
                                :class="{
                                    'kanban-card--dragging': draggingId === a.id,
                                    'ring-1 ring-(--color-danger)/40': isOverdue(a),
                                    'ring-1 ring-(--color-success)/40': col.status === 'offer' || col.status === 'hired'
                                }" draggable="true" @dragstart="onCardDragStart(a, $event)" @dragend="onCardDragEnd"
                                @click="openCard(a)">
                                <header class="flex items-start justify-between gap-2 mb-3">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xxs font-bold text-(--color-primary) bg-(--color-primary-subtle) border border-(--color-primary)/20 shrink-0"
                                            :title="a.applicantName">
                                            {{ initials(a.applicantName) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold text-(--text-heading) truncate">{{
                                                a.applicantName }}</p>
                                            <p class="text-xxs text-(--text-muted) truncate">
                                                <span v-if="a.candidateCode" class="font-mono text-(--color-primary)">{{
                                                    a.candidateCode }}</span>
                                                <span v-if="a.candidateCode && a.vacancy?.title" class="px-1">·</span>
                                                <span>{{ a.vacancy?.title || '—' }}</span>
                                            </p>
                                        </div>
                                    </div>

                                    <Badge v-if="cardBadge(a, col.status).show"
                                        :variant="cardBadge(a, col.status).variant">
                                        {{ cardBadge(a, col.status).label }}
                                    </Badge>
                                </header>

                                <div class="flex items-center gap-0.5 mb-3">
                                    <i v-for="n in 5" :key="n" class="ti text-[13px]"
                                        :class="n <= rating(a) ? 'ti-star-filled text-(--color-warning)' : 'ti-star text-(--border-strong)'" />
                                </div>

                                <div v-if="col.status === 'offer' && canSeeSalary && a.expectedSalary != null"
                                    class="rounded-lg bg-(--bg-muted) border border-(--border-color) px-2 py-1.5 mb-3 text-xxs text-(--text-body) font-mono">
                                    Offer: {{ formatMoney(a.expectedSalary) }}
                                </div>

                                <!-- Hired conditional slot: appointment-request / view-employee CTA.
                     Stops propagation so a button click does NOT also open the
                     details modal underneath, and dragstart doesn't trigger
                     a card drag when the user clicks the button. -->
                                <div v-if="col.status === 'hired'" class="mb-3" @click.stop @mousedown.stop
                                    @dragstart.stop.prevent>
                                    <NuxtLink v-if="a.employeeId" :to="`/employees?id=${a.employeeId}`"
                                        class="hired-chip hired-chip--linked" draggable="false">
                                        <i class="ti ti-user-check text-[12px]" />
                                        <span>View employee</span>
                                    </NuxtLink>
                                    <span v-else-if="a.pendingAppointmentRequest"
                                        class="hired-chip hired-chip--pending" draggable="false">
                                        <i class="ti ti-hourglass-high text-[12px]" />
                                        <span>Pending appointment review</span>
                                    </span>
                                    <NuxtLink v-else-if="canRequestAppointment"
                                        :to="`/approvals/forms/employee-appointment?applicationId=${a.id}`"
                                        class="hired-chip hired-chip--cta" draggable="false">
                                        <i class="ti ti-send text-[12px]" />
                                        <span>Request Appointment of Employee</span>
                                    </NuxtLink>

                                    <!-- Revert affordance — only within the 7-day window. -->
                                    <button v-if="canRevert(a)" type="button" class="hired-revert"
                                        :disabled="reverting === a.id"
                                        :title="`Undo conversion (within ${REVERT_WINDOW_DAYS_LABEL} of ${formatDateTime(a.convertedAt)})`"
                                        @click="revertConversion(a)">
                                        <i
                                            :class="['ti text-[11px]', reverting === a.id ? 'ti-loader animate-spin' : 'ti-arrow-back-up']" />
                                        <span>{{ reverting === a.id ? 'Reverting...' : 'Revert conversion' }}</span>
                                    </button>
                                </div>

                                <footer class="flex items-center justify-between text-xxs text-(--text-muted)">
                                    <span class="inline-flex items-center gap-1 truncate max-w-[55%]">
                                        <i :class="['ti text-[11px]', sourceIcon(a)]" />
                                        <span class="truncate">{{ sourceLabel(a) }}</span>
                                    </span>
                                    <span class="inline-flex items-center gap-1"
                                        :class="isOverdue(a) ? 'text-(--color-danger)' : ''">
                                        <i
                                            :class="['ti text-[11px]', isOverdue(a) ? 'ti-alert-triangle' : 'ti-clock']" />
                                        {{ relativeTime(a.appliedAt) }}
                                    </span>
                                </footer>
                            </article>

                            <!-- Empty-state slot: entry column gets a CTA card; other columns keep the drop-here hint -->
                            <template v-if="!grouped[col.status]?.length">
                                <NuxtLink v-if="col.status === 'applied' && canWrite"
                                    :to="{ path: '/hrm/applications/new', query: filters.jobVacancyId ? { vacancyId: filters.jobVacancyId } : {} }"
                                    class="kanban-cta glass-card rounded-xl p-4 w-full text-left transition-all">
                                    <span class="kanban-cta-icon">
                                        <i class="ti ti-user-plus text-base" />
                                    </span>
                                    <span class="block text-xs font-semibold text-(--text-heading) mt-3">
                                        New Application
                                    </span>
                                    <span class="block text-xxs text-(--text-muted) mt-1">
                                        Submit a candidate to start the pipeline.
                                    </span>
                                </NuxtLink>
                                <div v-else
                                    class="kanban-empty rounded-xl border border-dashed border-(--border-color) text-(--text-muted) text-xxs py-6 text-center">
                                    Drop here to move to <span class="font-semibold">{{ col.label }}</span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Submit application modal -->
            <div v-if="showSubmitModal"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4"
                @click.self="closeSubmitModal">
                <div
                    class="glass-card rounded-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                    <header class="flex items-center justify-between mb-5">
                        <div>
                            <h3 class="text-base font-semibold text-(--text-heading)">New application</h3>
                            <p class="text-xxs text-(--text-muted) mt-1">Submit a candidate into the Applied column.</p>
                        </div>
                        <button class="topbar-btn" @click="closeSubmitModal"><i class="ti ti-x" /></button>
                    </header>

                    <form class="form-grid" @submit.prevent="submitApplication">
                        <div class="form-grid-full">
                            <label class="form-label form-label-required">Vacancy</label>
                            <select v-model="form.job_vacancy_id" required class="form-control">
                                <option value="" disabled>Select vacancy...</option>
                                <option v-for="v in vacancies" :key="v.id" :value="v.id">{{ v.title }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label form-label-required">Applicant name</label>
                            <input v-model="form.applicant_name" type="text" required class="form-control">
                        </div>
                        <div>
                            <label class="form-label form-label-required">Email</label>
                            <input v-model="form.applicant_email" type="email" required class="form-control">
                        </div>

                        <div>
                            <label class="form-label">Phone</label>
                            <input v-model="form.applicant_phone" type="tel" class="form-control">
                        </div>
                        <div>
                            <label class="form-label">Expected salary</label>
                            <input v-model.number="form.expected_salary" type="number" min="0" step="0.01"
                                class="form-control font-mono">
                        </div>

                        <div class="form-grid-full">
                            <label class="form-label">Resume URL / path</label>
                            <input v-model="form.resume_path" type="text" class="form-control"
                                placeholder="storage/resumes/dara-kim.pdf">
                        </div>

                        <div class="form-grid-full">
                            <label class="form-label">Cover letter</label>
                            <textarea v-model="form.cover_letter" rows="4" class="form-control"
                                placeholder="Why this role?" />
                        </div>

                        <div class="form-grid-full">
                            <label class="form-label">Referrer (employee)</label>
                            <select v-model="form.referrer_employee_id" class="form-control">
                                <option :value="''">— No referrer</option>
                                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.fullName }} ({{
                                    e.employeeId }})
                                </option>
                            </select>
                        </div>

                        <div v-if="formError" class="form-grid-full form-error">{{ formError }}</div>

                        <footer class="form-grid-full pt-4 border-t border-(--border-color) flex justify-end gap-2">
                            <button type="button" class="btn btn-ghost text-xs"
                                @click="closeSubmitModal">Cancel</button>
                            <button type="submit" class="btn btn-primary text-xs" :disabled="saving">
                                <i class="ti ti-send" />{{ saving ? 'Submitting...' : 'Submit' }}
                            </button>
                        </footer>
                    </form>
                </div>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '~/composables/useApi'
import { formatDate, formatDateTime } from '~/composables/useDateFormat'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import Badge from '~/components/Badge.vue'

interface VacancyLite { id: string; title: string }
interface EmployeeLite { id: string; employeeId: string; fullName: string }

type ApplicationStatus = 'applied' | 'screening' | 'shortlisted' | 'interview' | 'offer' | 'hired' | 'rejected' | 'withdrawn'

const STATUS_FLOW: Record<ApplicationStatus, ApplicationStatus[]> = {
    applied: ['screening', 'rejected', 'withdrawn'],
    screening: ['shortlisted', 'interview', 'rejected', 'withdrawn'],
    shortlisted: ['interview', 'rejected', 'withdrawn'],
    interview: ['offer', 'rejected', 'withdrawn'],
    offer: ['hired', 'rejected', 'withdrawn'],
    hired: [],
    rejected: [],
    withdrawn: []
}

interface Application {
    id: string
    candidateCode: string | null
    jobVacancyId: string
    employeeId: string | null
    applicantName: string
    applicantEmail: string
    applicantPhone: string | null
    location: string | null
    linkedinUrl: string | null
    resumePath: string | null
    coverLetter: string | null
    workExperience: any[] | null
    education: any[] | null
    skills: string[] | null
    expectedSalary: number | null
    notes: string | null
    status: ApplicationStatus
    appliedAt: string | null
    convertedAt: string | null
    vacancy?: VacancyLite
    referrerEmployeeId?: string | null
    pendingAppointmentRequest?: { id: string; status: string; createdAt: string } | null
}
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

const api = useApi()
const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()
const canWrite = computed(() => authStore.hasPermission('hrm.recruitment.write'))
const canSeeSalary = computed(() => authStore.hasPermission('hrm.recruitment.read'))
const canRequestAppointment = computed(() =>
    authStore.hasPermission('hrm.recruitment.write')
)
const canRevertConversion = computed(() =>
    authStore.hasPermission('hrm.recruitment.write') &&
    authStore.hasPermission('hrm.employee.delete')
)

// Mirror of the backend's RecruitmentService::REVERT_CONVERSION_WINDOW_DAYS.
// Past this age the link is treated as settled; the button hides and the
// endpoint refuses with a 422.
const REVERT_WINDOW_MS = 7 * 24 * 60 * 60 * 1000
const REVERT_WINDOW_DAYS_LABEL = '7 days'

const isWithinRevertWindow = (app: Application): boolean => {
    if (!app.convertedAt) return false
    const ageMs = Date.now() - new Date(app.convertedAt).getTime()
    return ageMs >= 0 && ageMs <= REVERT_WINDOW_MS
}

const canRevert = (app: Application): boolean =>
    canRevertConversion.value &&
    app.status === 'hired' &&
    !!app.employeeId &&
    isWithinRevertWindow(app)

const COLUMNS: { status: ApplicationStatus; label: string }[] = [
    { status: 'applied', label: 'Applied' },
    { status: 'screening', label: 'Screening' },
    { status: 'shortlisted', label: 'Shortlisted' },
    { status: 'interview', label: 'Technical Interview' },
    { status: 'offer', label: 'Offer Sent' },
    { status: 'hired', label: 'Hired' }
]
const columns = COLUMNS

const applications = ref<Application[]>([])
const vacancies = ref<VacancyLite[]>([])
const employees = ref<EmployeeLite[]>([])
const loading = ref(false)

const filters = reactive({
    search: '',
    jobVacancyId: (route.query.vacancyId as string) || ''
})

const showSubmitModal = ref(false)
const saving = ref(false)
const formError = ref<string | null>(null)
const form = reactive({
    job_vacancy_id: (route.query.vacancyId as string) || '',
    applicant_name: '',
    applicant_email: '',
    applicant_phone: '',
    location: '',
    linkedin_url: '',
    resume_path: '',
    cover_letter: '',
    work_experience: [] as any[],
    education: [] as any[],
    skills: [] as string[],
    expected_salary: null as number | null,
    referrer_employee_id: ''
})

const draggingId = ref<string | null>(null)
const draggingFrom = ref<ApplicationStatus | null>(null)
const dragOverColumn = ref<ApplicationStatus | null>(null)
const movingId = ref<string | null>(null)
const pendingDropStatus = ref<ApplicationStatus | null>(null)

const grouped = computed<Record<ApplicationStatus, Application[]>>(() => {
    const seed: Record<ApplicationStatus, Application[]> = {
        applied: [], screening: [], shortlisted: [], interview: [], offer: [], hired: [], rejected: [], withdrawn: []
    }
    for (const a of applications.value) {
        if (seed[a.status]) seed[a.status].push(a)
    }
    return seed
})

const activeVacancy = computed(() =>
    vacancies.value.find(v => v.id === filters.jobVacancyId) || null
)

const initials = (name: string) =>
    name.split(/\s+/).filter(Boolean).slice(0, 2).map(p => p[0]!.toUpperCase()).join('')

const formatMoney = (n: number) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(n)

const relativeTime = (iso: string | null) => {
    if (!iso) return '—'
    const then = new Date(iso).getTime()
    const diffMs = Date.now() - then
    if (diffMs < 0) return 'just now'
    const min = Math.floor(diffMs / 60_000)
    if (min < 60) return `${min || 1}m ago`
    const hr = Math.floor(min / 60)
    if (hr < 24) return `${hr}h ago`
    const days = Math.floor(hr / 24)
    if (days < 7) return `${days}d ago`
    const wk = Math.floor(days / 7)
    if (wk < 4) return `${wk}w ago`
    return formatDate(iso)
}

const isOverdue = (a: Application) => {
    if (!a.appliedAt || a.status === 'hired' || a.status === 'rejected' || a.status === 'withdrawn') return false
    const ageDays = (Date.now() - new Date(a.appliedAt).getTime()) / 86_400_000
    if (a.status === 'applied' || a.status === 'screening') return ageDays >= 5
    if (a.status === 'interview' || a.status === 'offer') return ageDays >= 7
    return false
}

const rating = (a: Application) => {
    let r = 3
    if (a.coverLetter) r += 1
    if (a.resumePath) r += 1
    if (a.expectedSalary != null) r += 0
    if (a.referrerEmployeeId) r = Math.min(5, r + 1)
    return Math.max(1, Math.min(5, r))
}

type CardBadge = { show: boolean; variant: 'primary' | 'danger' | 'secondary' | 'success'; label: string }
const HIDDEN_BADGE: CardBadge = { show: false, variant: 'secondary', label: '' }
const cardBadge = (a: Application, status: ApplicationStatus): CardBadge => {
    if (isOverdue(a)) return { show: true, variant: 'danger', label: 'Urgent' }
    if (status === 'applied' && a.appliedAt) {
        const ageHrs = (Date.now() - new Date(a.appliedAt).getTime()) / 3_600_000
        if (ageHrs < 24) return { show: true, variant: 'primary', label: 'New' }
    }
    if (a.referrerEmployeeId) return { show: true, variant: 'secondary', label: 'Referral' }
    if (status === 'hired') return { show: true, variant: 'success', label: 'Hired' }
    return HIDDEN_BADGE
}

const statusVariant = (s: ApplicationStatus): 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'secondary' => {
    switch (s) {
        case 'applied': return 'secondary'
        case 'screening': return 'info'
        case 'shortlisted': return 'primary'
        case 'interview': return 'warning'
        case 'offer': return 'primary'
        case 'hired': return 'success'
        case 'rejected': return 'danger'
        case 'withdrawn': return 'secondary'
    }
}

const columnHeaderClass = (s: ApplicationStatus) => {
    switch (s) {
        case 'applied': return 'badge-soft-secondary'
        case 'screening': return 'badge-soft-info'
        case 'shortlisted': return 'badge-soft-primary'
        case 'interview': return 'badge-soft-warning'
        case 'offer': return 'badge-soft-primary'
        case 'hired': return 'badge-soft-success'
        default: return 'badge-soft-secondary'
    }
}

const sourceIcon = (a: Application) => {
    if (a.referrerEmployeeId) return 'ti-user-check'
    if (a.resumePath) return 'ti-file-cv'
    return 'ti-mail'
}
const sourceLabel = (a: Application) => {
    if (a.referrerEmployeeId) return 'Referral'
    if (a.resumePath) return 'Resume on file'
    return a.applicantEmail
}

const loadLookups = async () => {
    try {
        const [v, e] = await Promise.all([
            api.get<Paginated<VacancyLite>>('/job-vacancies?limit=100'),
            api.get<Paginated<EmployeeLite>>('/employees?limit=100')
        ])
        vacancies.value = v.data
        employees.value = e.data
    } catch (err) {
        console.error('Failed to load lookups', err)
    }
}

const loadApplications = async () => {
    loading.value = true
    try {
        const q = new URLSearchParams({ page: '1', limit: '200' })
        if (filters.search) q.set('search', filters.search)
        if (filters.jobVacancyId) q.set('jobVacancyId', filters.jobVacancyId)
        const res = await api.get<Paginated<Application>>(`/applications?${q.toString()}`)
        applications.value = res.data
    } catch (err) {
        console.error('Failed to load applications', err)
        applications.value = []
    } finally {
        loading.value = false
    }
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(() => [filters.search, filters.jobVacancyId], () => {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(loadApplications, 300)
})

const openCard = (a: Application) => {
    // Guard: the click event fires after dragend, so suppress navigation
    // when the user just released a drag on the same card.
    if (draggingId.value) return
    router.push(`/hrm/recruitments/candidates/${a.id}`)
}

const openSubmitModal = () => {
    Object.assign(form, {
        job_vacancy_id: filters.jobVacancyId || '',
        applicant_name: '',
        applicant_email: '',
        applicant_phone: '',
        location: '',
        linkedin_url: '',
        resume_path: '',
        cover_letter: '',
        work_experience: [] as any[],
        education: [] as any[],
        skills: [] as string[],
        expected_salary: null as number | null,
        referrer_employee_id: ''
    })
    formError.value = null
    showSubmitModal.value = true
}

const closeSubmitModal = () => {
    if (saving.value) return
    showSubmitModal.value = false
}

const submitApplication = async () => {
    saving.value = true
    formError.value = null
    try {
        const payload: Record<string, any> = { ...form }
        if (!payload.referrer_employee_id) payload.referrer_employee_id = null
        if (!payload.applicant_phone) payload.applicant_phone = null
        if (!payload.location) payload.location = null
        if (!payload.linkedin_url) payload.linkedin_url = null
        if (!payload.resume_path) payload.resume_path = null
        if (!payload.cover_letter) payload.cover_letter = null
        await api.post('/applications', payload)
        showSubmitModal.value = false
        await loadApplications()
    } catch (err: any) {
        formError.value = err?.data?.message || 'Failed to submit application.'
    } finally {
        saving.value = false
    }
}

const onCardDragStart = (a: Application, ev: DragEvent) => {
    if (!canWrite.value) {
        ev.preventDefault()
        return
    }
    if (!STATUS_FLOW[a.status].length) {
        ev.preventDefault()
        return
    }
    draggingId.value = a.id
    draggingFrom.value = a.status
    ev.dataTransfer?.setData('text/plain', a.id)
    if (ev.dataTransfer) ev.dataTransfer.effectAllowed = 'move'
}

const onCardDragEnd = () => {
    draggingId.value = null
    draggingFrom.value = null
    dragOverColumn.value = null
}

const canDropOn = (target: ApplicationStatus) => {
    if (!draggingFrom.value) return false
    if (target === draggingFrom.value) return false
    return STATUS_FLOW[draggingFrom.value].includes(target)
}

const onColumnDragOver = (status: ApplicationStatus, ev: DragEvent) => {
    if (!canDropOn(status)) {
        if (ev.dataTransfer) ev.dataTransfer.dropEffect = 'none'
        return
    }
    if (ev.dataTransfer) ev.dataTransfer.dropEffect = 'move'
    dragOverColumn.value = status
}

const onColumnDragLeave = (status: ApplicationStatus) => {
    if (dragOverColumn.value === status) dragOverColumn.value = null
}

const onColumnDrop = async (status: ApplicationStatus) => {
    const id = draggingId.value
    const from = draggingFrom.value
    dragOverColumn.value = null
    draggingId.value = null
    draggingFrom.value = null
    if (!id || !from || from === status || !STATUS_FLOW[from].includes(status)) return

    const idx = applications.value.findIndex(a => a.id === id)
    if (idx === -1) return
    const original = applications.value[idx].status
    applications.value[idx].status = status
    movingId.value = id
    pendingDropStatus.value = status

    try {
        await api.patch(`/applications/${id}/status`, { status })
    } catch (err: any) {
        applications.value[idx].status = original
        toast.error('Failed to update status.', err?.data?.message)
    } finally {
        movingId.value = null
        pendingDropStatus.value = null
    }
}

const reverting = ref<string | null>(null)

const router = useRouter()

const revertConversion = async (app: Application): Promise<void> => {
    if (!canRevert(app)) return
    const ok = await toast.confirm({
        title: `Revert conversion for ${app.applicantName}?`,
        description: `The linked Employee record will be archived (soft-deleted) and the application will become unlinked. Only available within ${REVERT_WINDOW_DAYS_LABEL} of conversion.`,
        confirmLabel: 'Revert conversion',
        color: 'danger',
        icon: 'ti-arrow-back-up'
    })
    if (!ok) return
    reverting.value = app.id
    try {
        await api.post(`/applications/${app.id}/revert-employee-conversion`)
        toast.success('Conversion reverted', `${app.applicantName} is no longer linked to an employee.`)
        // Optimistic local patch so the chip flips before the server round-trip.
        const idx = applications.value.findIndex(x => x.id === app.id)
        if (idx !== -1) {
            applications.value[idx].employeeId = null
            applications.value[idx].convertedAt = null
        }
        await loadApplications()
    } catch (err: any) {
        toast.error('Revert failed.', err?.data?.message)
    } finally {
        reverting.value = null
    }
}

onMounted(async () => {
    await Promise.all([loadLookups(), loadApplications()])
})
</script>

<style scoped>
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

.kanban-list {
    min-height: 1px;
}

.kanban-card {
    user-select: none;
}

.kanban-card:hover {
    transform: translateY(-2px);
}

.kanban-card:active {
    cursor: grabbing;
}

/* Hired conditional slot — chip that flips between a CTA and a "linked" pill. */
.hired-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    width: 100%;
    justify-content: center;
    padding: 0.4rem 0.625rem;
    border-radius: 0.5rem;
    font-size: 0.6875rem;
    font-weight: 600;
    letter-spacing: 0.01em;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease, transform 0.1s ease;
    text-decoration: none;
}

.hired-chip:active {
    transform: scale(0.98);
}

.hired-chip:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.hired-chip--cta {
    background: var(--color-primary);
    color: #fff;
    border: 1px solid transparent;
    box-shadow: 0 4px 10px -3px rgb(var(--color-primary-rgb) / 0.4);
}

.hired-chip--cta:hover:not(:disabled) {
    background: rgb(var(--color-primary-rgb) / 0.9);
}

.hired-chip--linked {
    background: var(--color-success-subtle);
    color: var(--color-success);
    border: 1px solid rgb(var(--color-success-rgb) / 0.25);
}

.hired-chip--linked:hover {
    background: rgb(var(--color-success-rgb) / 0.18);
    border-color: rgb(var(--color-success-rgb) / 0.4);
}

.hired-chip--pending {
    background: var(--color-warning-subtle, rgb(var(--color-warning-rgb, 250 173 20) / 0.12));
    color: var(--color-warning);
    border: 1px dashed rgb(var(--color-warning-rgb, 250 173 20) / 0.4);
    cursor: default;
}

/* Small, subdued affordance under the linked pill. Only renders within the
 * 7-day revert window — so it's intentionally low-key and won't compete with
 * the primary View employee link. */
.hired-revert {
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
    width: 100%;
    justify-content: center;
    margin-top: 0.375rem;
    padding: 0.25rem 0.5rem;
    background: transparent;
    border: 1px dashed rgb(var(--color-danger-rgb) / 0.25);
    border-radius: 0.375rem;
    font-size: 0.625rem;
    font-weight: 500;
    color: var(--text-muted);
    cursor: pointer;
    transition: color 0.15s ease, background 0.15s ease, border-color 0.15s ease;
}

.hired-revert:hover:not(:disabled) {
    color: var(--color-danger);
    background: var(--color-danger-subtle);
    border-color: rgb(var(--color-danger-rgb) / 0.4);
    border-style: solid;
}

.hired-revert:disabled {
    opacity: 0.6;
    cursor: not-allowed;
}

.kanban-card--dragging {
    opacity: 0.45;
    transform: scale(0.98);
}

.kanban-empty {
    background: color-mix(in srgb, var(--bg-card) 60%, transparent);
}

.kanban-scroller {
    scrollbar-width: thin;
    scrollbar-color: var(--border-strong) transparent;
}

.kanban-scroller::-webkit-scrollbar {
    height: 8px;
}

.kanban-scroller::-webkit-scrollbar-thumb {
    background: var(--border-strong);
    border-radius: 4px;
}

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

.kanban-cta:focus-visible {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgb(var(--color-primary-rgb) / 0.2);
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
    box-shadow: 0 4px 12px rgb(var(--color-primary-rgb) / 0.35);
}
</style>
