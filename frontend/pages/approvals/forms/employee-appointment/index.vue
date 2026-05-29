<template>
    <NuxtLayout name="default">
        <div class="max-w-5xl mx-auto space-y-8 pb-12">

            <!-- Hero banner -->
            <section class="relative overflow-hidden rounded-2xl border border-(--border-color) bg-(--bg-card) p-6 sm:p-8 shadow-(--shadow-sm)">
                <div class="absolute -top-20 -right-16 w-72 h-72 rounded-full blur-3xl bg-(--color-info)/15 pointer-events-none" />
                <div class="absolute -bottom-20 -left-20 w-72 h-72 rounded-full blur-3xl bg-(--color-primary)/10 pointer-events-none" />

                <div class="relative z-10 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                    <div class="flex items-start gap-4">
                        <button @click="router.back()"
                            class="w-10 h-10 mt-1 rounded-full bg-(--bg-muted) flex items-center justify-center hover:bg-primary/10 hover:text-primary transition-colors shrink-0">
                            <i class="ti ti-arrow-left text-xl"></i>
                        </button>
                        <div class="space-y-2 max-w-2xl">
                            <Badge variant="info" :dot="true">eApprovals · Recruitment</Badge>
                            <h1 class="text-2xl font-bold tracking-tight text-(--text-heading)">
                                Request Appointment of Employee
                            </h1>
                            <p class="text-xs text-(--text-body) leading-relaxed">
                                Confirm the hire terms below for HR approval. Once approved, an Employee record is
                                created automatically from the candidate profile and the values you set here.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Process stepper -->
                <ol class="relative z-10 mt-8 grid grid-cols-4 gap-2 text-xxs">
                    <li v-for="(step, idx) in steps" :key="step.key"
                        class="flex flex-col items-center gap-2 text-center">
                        <div class="flex items-center w-full">
                            <div :class="['h-1 flex-1 rounded-full transition-colors',
                                idx === 0 ? 'bg-transparent' : (idx <= activeStepIndex ? 'bg-(--color-info)' : 'bg-(--border-color)')]" />
                            <div :class="['shrink-0 w-8 h-8 rounded-full grid place-items-center text-xs font-bold transition-colors',
                                idx < activeStepIndex ? 'bg-(--color-success) text-white' :
                                idx === activeStepIndex ? 'bg-(--color-info) text-white shadow-(--shadow-sm)' :
                                'bg-(--bg-muted) text-(--text-muted) border border-(--border-color)']">
                                <i v-if="idx < activeStepIndex" class="ti ti-check text-sm" />
                                <span v-else>{{ idx + 1 }}</span>
                            </div>
                            <div :class="['h-1 flex-1 rounded-full transition-colors',
                                idx === steps.length - 1 ? 'bg-transparent' : (idx < activeStepIndex ? 'bg-(--color-info)' : 'bg-(--border-color)')]" />
                        </div>
                        <div>
                            <p :class="['font-semibold uppercase tracking-widest', idx === activeStepIndex ? 'text-(--color-info)' : 'text-(--text-muted)']">
                                {{ step.label }}
                            </p>
                            <p class="text-(--text-muted) mt-0.5 hidden sm:block">{{ step.hint }}</p>
                        </div>
                    </li>
                </ol>
            </section>

            <!-- Loading / picker / error states -->
            <div v-if="loading" class="glass-card rounded-2xl p-16 flex flex-col items-center justify-center gap-3 border border-(--border-color)">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-info)/20 border-t-(--color-info) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading candidate...</span>
            </div>

            <!-- Candidate picker — shown when no applicationId and the caller can start an appointment -->
            <section v-else-if="showPicker" class="glass-card rounded-2xl border border-(--border-color) overflow-hidden">
                <header class="px-6 py-5 border-b border-(--border-color) flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="space-y-1">
                        <h3 class="text-sm font-semibold text-(--text-heading) flex items-center gap-2">
                            <i class="ti ti-users-group text-(--color-info)" />Pick a hired candidate
                        </h3>
                        <p class="text-xxs text-(--text-muted)">
                            Showing candidates currently in <Badge variant="success" :dot="true">Hired</Badge>
                            without an active appointment.
                        </p>
                    </div>
                    <div class="w-full sm:w-72 relative">
                        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                        <input v-model="pickerSearch" type="search" placeholder="Search name, code, vacancy..."
                            class="form-control pl-9 text-xs" />
                    </div>
                </header>

                <div v-if="loadingPicker" class="py-16 flex flex-col items-center justify-center gap-3">
                    <span class="w-8 h-8 rounded-full border-2 border-(--color-info)/20 border-t-(--color-info) animate-spin" />
                    <span class="text-xs text-(--text-muted) font-medium">Loading hired candidates...</span>
                </div>

                <div v-else-if="filteredEligibleCandidates.length === 0" class="py-16 text-center">
                    <div class="w-14 h-14 mx-auto rounded-full bg-(--bg-muted) text-(--text-muted) grid place-items-center">
                        <i class="ti ti-user-off text-2xl" />
                    </div>
                    <h4 class="text-sm font-semibold text-(--text-heading) mt-4">
                        {{ pickerSearch ? 'No matching candidates' : 'No hired candidates ready for appointment' }}
                    </h4>
                    <p class="text-xs text-(--text-muted) mt-1 max-w-md mx-auto">
                        <span v-if="pickerSearch">Try a different search term, or clear the filter.</span>
                        <span v-else>Move a candidate to the Hired column from the Recruitment Kanban to start an appointment.</span>
                    </p>
                    <NuxtLink v-if="!pickerSearch" to="/hrm/recruitments/candidates"
                        class="btn btn-secondary text-xs mt-5 inline-flex items-center gap-1">
                        <i class="ti ti-users" />Open recruitment Kanban
                    </NuxtLink>
                </div>

                <ul v-else class="divide-y divide-(--border-color)">
                    <li v-for="c in filteredEligibleCandidates" :key="c.id">
                        <button type="button" @click="selectCandidate(c.id)"
                            class="picker-row w-full px-5 py-4 flex items-center gap-4 text-left">
                            <div class="picker-avatar shrink-0">
                                <span>{{ pickerInitials(c.applicantName) }}</span>
                            </div>
                            <div class="flex-1 min-w-0 space-y-1">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="text-sm font-semibold text-(--text-heading) truncate">{{ c.applicantName }}</span>
                                    <Badge variant="success" :dot="true">Hired</Badge>
                                </div>
                                <div class="flex items-center gap-2 text-xxs text-(--text-muted) flex-wrap">
                                    <span v-if="c.candidateCode" class="font-mono">{{ c.candidateCode }}</span>
                                    <span v-if="c.candidateCode && c.vacancy?.title">·</span>
                                    <span v-if="c.vacancy?.title" class="inline-flex items-center gap-1">
                                        <i class="ti ti-briefcase text-[11px]" />{{ c.vacancy.title }}
                                    </span>
                                    <span v-if="c.applicantEmail">·</span>
                                    <span v-if="c.applicantEmail" class="truncate">{{ c.applicantEmail }}</span>
                                </div>
                            </div>
                            <div class="picker-cta shrink-0">
                                <span class="text-xxs font-bold uppercase tracking-wider">Appoint</span>
                                <i class="ti ti-arrow-right text-sm" />
                            </div>
                        </button>
                    </li>
                </ul>
            </section>

            <div v-else-if="loadError" class="glass-card rounded-2xl p-12 text-center border border-(--border-color)">
                <div class="w-14 h-14 mx-auto rounded-full bg-(--color-danger)/10 text-(--color-danger) grid place-items-center">
                    <i class="ti ti-alert-triangle text-2xl" />
                </div>
                <h4 class="text-sm font-semibold text-(--text-heading) mt-4">{{ loadError }}</h4>
                <p class="text-xs text-(--text-muted) mt-1 max-w-md mx-auto">
                    Open this form from a hired candidate's card on the Recruitment Kanban,
                    or from the candidate profile page.
                </p>
                <NuxtLink to="/hrm/recruitments/candidates"
                    class="btn btn-primary text-xs mt-5 inline-flex items-center gap-1">
                    <i class="ti ti-users" />Open recruitment Kanban
                </NuxtLink>
            </div>

            <form v-else @submit.prevent="submitForm" class="space-y-6">

                <!-- Candidate snapshot -->
                <section class="glass-card rounded-2xl p-6 border border-(--border-color) overflow-hidden relative">
                    <div class="absolute -right-10 -top-10 w-32 h-32 rounded-full bg-(--color-info)/10 blur-2xl pointer-events-none" />
                    <header class="flex items-center justify-between mb-5 relative z-10">
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-(--text-muted) flex items-center gap-2">
                            <i class="ti ti-user-circle text-sm" />Candidate Profile
                        </h3>
                        <Badge v-if="application" variant="success" :dot="true">Hired</Badge>
                    </header>

                    <div class="flex flex-col sm:flex-row gap-5 sm:items-center relative z-10">
                        <div class="candidate-avatar shrink-0">
                            <span>{{ candidateInitials }}</span>
                        </div>
                        <div class="flex-1 min-w-0 space-y-1">
                            <p class="text-lg font-bold text-(--text-heading) truncate">{{ application?.applicantName || '—' }}</p>
                            <p class="text-xs text-(--text-muted) inline-flex items-center gap-1.5">
                                <i class="ti ti-briefcase text-[12px]" />
                                <span class="truncate">{{ application?.vacancy?.title || 'No vacancy linked' }}</span>
                            </p>
                            <div class="flex flex-wrap gap-2 mt-3">
                                <span v-if="application?.candidateCode" class="candidate-chip">
                                    <i class="ti ti-hash text-[11px]" />
                                    <span class="font-mono">{{ application.candidateCode }}</span>
                                </span>
                                <a v-if="application?.applicantEmail" :href="`mailto:${application.applicantEmail}`"
                                    class="candidate-chip candidate-chip--link">
                                    <i class="ti ti-mail text-[11px]" />
                                    <span class="truncate max-w-[180px]">{{ application.applicantEmail }}</span>
                                </a>
                                <a v-if="application?.applicantPhone" :href="`tel:${application.applicantPhone}`"
                                    class="candidate-chip candidate-chip--link">
                                    <i class="ti ti-phone text-[11px]" />
                                    <span>{{ application.applicantPhone }}</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Role & Department -->
                <section class="glass-card rounded-2xl p-6 border border-(--border-color) space-y-5">
                    <header class="flex items-start justify-between">
                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-widest text-(--text-muted) flex items-center gap-2">
                                <i class="ti ti-building text-sm" />Role &amp; Department
                            </h3>
                            <p class="text-xxs text-(--text-muted) mt-1">Defaults are inherited from the vacancy. Override only when the hire deviates.</p>
                        </div>
                    </header>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="form-label">Department</label>
                            <div class="input-with-icon">
                                <i class="ti ti-building input-icon"></i>
                                <select v-model="form.department_id"
                                    class="form-control bg-(--bg-muted) border-transparent focus:border-primary focus:bg-transparent appearance-none">
                                    <option :value="''">— Unassigned</option>
                                    <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Position</label>
                            <div class="input-with-icon">
                                <i class="ti ti-id-badge input-icon"></i>
                                <select v-model="form.position_id"
                                    class="form-control bg-(--bg-muted) border-transparent focus:border-primary focus:bg-transparent appearance-none">
                                    <option :value="''">— Unassigned</option>
                                    <option v-for="p in positions" :key="p.id" :value="p.id">{{ p.title }}</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="form-label form-label-required">Employment type</label>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                            <button v-for="opt in employmentTypes" :key="opt.value" type="button"
                                @click="form.employment_type = opt.value"
                                :class="['employment-pill', form.employment_type === opt.value ? 'employment-pill--active' : '']">
                                <i :class="['ti', opt.icon, 'text-base']" />
                                <span>{{ opt.label }}</span>
                            </button>
                        </div>
                    </div>
                </section>

                <!-- Schedule & Compensation -->
                <section class="glass-card rounded-2xl p-6 border border-(--border-color) space-y-5">
                    <header>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-(--text-muted) flex items-center gap-2">
                            <i class="ti ti-calendar-time text-sm" />Schedule &amp; Compensation
                        </h3>
                        <p class="text-xxs text-(--text-muted) mt-1">First day on the job and starting salary.</p>
                    </header>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="form-label form-label-required">Start date</label>
                            <div class="input-with-icon">
                                <i class="ti ti-calendar input-icon"></i>
                                <input type="date" v-model="form.start_date"
                                    class="form-control bg-(--bg-muted) border-transparent focus:border-primary focus:bg-transparent"
                                    required />
                            </div>
                            <p class="form-hint">
                                <i class="ti ti-info-circle mr-1" />Becomes the employee's <code class="text-xxs">hired_at</code>.
                            </p>
                        </div>
                        <div>
                            <label class="form-label">Base salary</label>
                            <div class="input-with-icon">
                                <i class="ti ti-cash input-icon"></i>
                                <input type="number" v-model.number="form.base_salary" step="0.01" min="0"
                                    placeholder="0.00"
                                    class="form-control font-mono bg-(--bg-muted) border-transparent focus:border-primary focus:bg-transparent" />
                            </div>
                            <p class="form-hint">
                                <i class="ti ti-info-circle mr-1" />
                                <span v-if="application?.expectedSalary != null">
                                    Candidate expected <span class="font-mono">{{ formatMoney(application.expectedSalary) }}</span>.
                                </span>
                                <span v-else>Encrypted at rest. Visible only to HR.</span>
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Reporting & Notes -->
                <section class="glass-card rounded-2xl p-6 border border-(--border-color) space-y-5">
                    <header>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-(--text-muted) flex items-center gap-2">
                            <i class="ti ti-user-star text-sm" />Reporting &amp; Notes
                        </h3>
                        <p class="text-xxs text-(--text-muted) mt-1">Who they report to, and anything the approver should know.</p>
                    </header>

                    <div>
                        <label class="form-label">Reporting manager</label>
                        <div class="input-with-icon">
                            <i class="ti ti-user-star input-icon"></i>
                            <select v-model="form.manager_id"
                                class="form-control bg-(--bg-muted) border-transparent focus:border-primary focus:bg-transparent appearance-none">
                                <option :value="''">— No manager</option>
                                <option v-for="e in employees" :key="e.id" :value="e.id">
                                    {{ e.fullName }} ({{ e.employeeId }})
                                </option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Justification / notes</label>
                        <textarea v-model="form.notes" rows="4"
                            placeholder="Why this appointment? Anything the approver should know..."
                            class="form-control bg-(--bg-muted) border-transparent focus:border-primary focus:bg-transparent resize-none"></textarea>
                        <p class="form-hint">
                            <i class="ti ti-info-circle mr-1" />Surfaced to the approver alongside the candidate profile.
                        </p>
                    </div>
                </section>

                <!-- Summary preview -->
                <section class="rounded-2xl border border-(--color-info)/30 p-5 sm:p-6 bg-(--color-info)/5">
                    <div class="flex items-start gap-4">
                        <div class="w-10 h-10 rounded-xl bg-(--color-info)/15 text-(--color-info) grid place-items-center shrink-0">
                            <i class="ti ti-sparkles text-lg" />
                        </div>
                        <div class="flex-1 min-w-0 space-y-1.5">
                            <p class="text-xs font-bold uppercase tracking-widest text-(--color-info)">On Approval</p>
                            <p class="text-sm text-(--text-heading) leading-relaxed">
                                A new Employee record will be created for
                                <strong>{{ application?.applicantName || 'the candidate' }}</strong>
                                <span v-if="selectedPositionName"> as <strong>{{ selectedPositionName }}</strong></span>
                                <span v-if="selectedDepartmentName"> in <strong>{{ selectedDepartmentName }}</strong></span>,
                                starting <strong>{{ formatDate(form.start_date) }}</strong>
                                ({{ employmentTypeLabel }})<span v-if="selectedManagerName">, reporting to <strong>{{ selectedManagerName }}</strong></span>.
                            </p>
                            <p class="text-xxs text-(--text-muted)">
                                Approver: anyone with the <span class="font-mono">admin</span> role. You can track this request under
                                <NuxtLink to="/approvals/requests" class="text-(--color-info) hover:underline">My Requests</NuxtLink>.
                            </p>
                        </div>
                    </div>
                </section>

                <div v-if="formError"
                    class="text-sm text-(--color-danger) bg-(--color-danger-subtle) px-4 py-3 rounded-xl border border-(--color-danger)/20">
                    <i class="ti ti-alert-circle mr-1" /> {{ formError }}
                </div>

                <!-- Sticky-feeling action footer -->
                <div class="appointment-footer">
                    <p class="text-xxs text-(--text-muted) sm:flex-1">
                        <i class="ti ti-shield-check mr-1 text-(--color-success)" />
                        Salary fields are encrypted at rest. The audit log captures who submitted.
                    </p>
                    <div class="flex items-center gap-3 shrink-0">
                        <button type="button" @click="router.back()" class="btn btn-secondary px-6">Cancel</button>
                        <button type="submit" class="btn btn-primary px-8 flex items-center gap-2" :disabled="isSubmitting">
                            <i v-if="isSubmitting" class="ti ti-loader animate-spin"></i>
                            <i v-else class="ti ti-send"></i>
                            <span>{{ isSubmitting ? 'Submitting...' : 'Submit for Approval' }}</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'
import { useDateFormat } from '~/composables/useDateFormat'
import Badge from '~/components/Badge.vue'

interface EmployeeLite { id: string; employeeId: string; fullName: string }
interface DepartmentLite { id: string; name: string }
interface PositionLite { id: string; title: string }
interface VacancyLite {
    id: string
    title: string
    departmentId?: string | null
    positionId?: string | null
}
interface ApplicationView {
    id: string
    candidateCode: string | null
    applicantName: string
    applicantEmail: string
    applicantPhone: string | null
    expectedSalary: number | null
    status: string
    employeeId: string | null
    vacancy?: VacancyLite | null
    pendingAppointmentRequest?: { id: string; status: string } | null
}
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

type EmploymentType = 'full_time' | 'part_time' | 'contract' | 'intern'

const route = useRoute()
const router = useRouter()
const api = useApi()
const authStore = useAuthStore()
const { formatDate } = useDateFormat()

const applicationId = ref((route.query.applicationId as string) || '')

const canPick = computed(() => authStore.hasPermission('hrm.recruitment.write'))

const application = ref<ApplicationView | null>(null)
const employees = ref<EmployeeLite[]>([])
const departments = ref<DepartmentLite[]>([])
const positions = ref<PositionLite[]>([])
const loading = ref(true)
const loadError = ref<string | null>(null)
const isSubmitting = ref(false)
const formError = ref<string | null>(null)

// Picker state — surfaced when no applicationId is in the URL but the user
// has permission to start a new appointment request.
const showPicker = ref(false)
const eligibleCandidates = ref<ApplicationView[]>([])
const loadingPicker = ref(false)
const pickerSearch = ref('')

const form = reactive({
    application_id: applicationId.value,
    start_date: new Date().toISOString().slice(0, 10),
    employment_type: 'full_time' as EmploymentType,
    base_salary: null as number | null,
    department_id: '' as string,
    position_id: '' as string,
    manager_id: '' as string,
    notes: ''
})

const employmentTypes: { value: EmploymentType; label: string; icon: string }[] = [
    { value: 'full_time', label: 'Full-time', icon: 'ti-clock-hour-8' },
    { value: 'part_time', label: 'Part-time', icon: 'ti-clock-hour-4' },
    { value: 'contract',  label: 'Contract',  icon: 'ti-file-text' },
    { value: 'intern',    label: 'Intern',    icon: 'ti-school' }
]

const steps = [
    { key: 'hired',   label: 'Hired',    hint: 'Candidate accepted' },
    { key: 'request', label: 'Request',  hint: 'Confirm hire terms' },
    { key: 'review',  label: 'HR Review', hint: 'Pending approval' },
    { key: 'onboard', label: 'Onboarding', hint: 'Employee created' }
]
const activeStepIndex = 1

const candidateInitials = computed(() => {
    const name = application.value?.applicantName?.trim() || ''
    if (!name) return '—'
    const parts = name.split(/\s+/).filter(Boolean).slice(0, 2)
    return parts.map(p => p[0]?.toUpperCase() || '').join('') || '—'
})

const formatMoney = (n: number) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(n)

const employmentTypeLabel = computed(() =>
    employmentTypes.find(t => t.value === form.employment_type)?.label || form.employment_type
)

const selectedDepartmentName = computed(() =>
    departments.value.find(d => d.id === form.department_id)?.name || ''
)
const selectedPositionName = computed(() =>
    positions.value.find(p => p.id === form.position_id)?.title || ''
)
const selectedManagerName = computed(() =>
    employees.value.find(e => e.id === form.manager_id)?.fullName || ''
)

const filteredEligibleCandidates = computed(() => {
    const q = pickerSearch.value.trim().toLowerCase()
    if (!q) return eligibleCandidates.value
    return eligibleCandidates.value.filter(c =>
        c.applicantName.toLowerCase().includes(q) ||
        c.applicantEmail.toLowerCase().includes(q) ||
        (c.candidateCode ?? '').toLowerCase().includes(q) ||
        (c.vacancy?.title ?? '').toLowerCase().includes(q)
    )
})

const pickerInitials = (name: string) => {
    const parts = (name || '').split(/\s+/).filter(Boolean).slice(0, 2)
    return parts.map(p => p[0]?.toUpperCase() || '').join('') || '—'
}

const loadEligibleCandidates = async () => {
    loadingPicker.value = true
    try {
        const res = await api.get<Paginated<ApplicationView>>('/applications?status=hired&limit=100')
        eligibleCandidates.value = (res.data || []).filter(
            a => !a.employeeId && !a.pendingAppointmentRequest
        )
    } catch (err) {
        console.error('Failed to load hired candidates', err)
        eligibleCandidates.value = []
    } finally {
        loadingPicker.value = false
    }
}

const selectCandidate = async (id: string) => {
    showPicker.value = false
    applicationId.value = id
    form.application_id = id
    router.replace({ query: { ...route.query, applicationId: id } })
    await loadEverything()
}

const loadEverything = async () => {
    loading.value = true
    loadError.value = null

    if (!applicationId.value) {
        loading.value = false
        if (canPick.value) {
            showPicker.value = true
            await loadEligibleCandidates()
        } else {
            loadError.value = 'No candidate selected.'
        }
        return
    }

    showPicker.value = false

    try {
        const [app, deps, pos, emps] = await Promise.all([
            api.get<{ data: ApplicationView }>(`/applications/${applicationId.value}`),
            api.get<Paginated<DepartmentLite>>('/departments?limit=100').catch(() => null),
            api.get<Paginated<PositionLite>>('/positions?limit=100').catch(() => null),
            api.get<Paginated<EmployeeLite>>('/employees?limit=200').catch(() => null)
        ])

        application.value = app.data
        departments.value = deps?.data ?? []
        positions.value = pos?.data ?? []
        employees.value = emps?.data ?? []

        if (app.data.status !== 'hired') {
            loadError.value = 'Only hired candidates can be appointed.'
            return
        }
        if (app.data.employeeId) {
            loadError.value = 'This candidate is already linked to an employee.'
            return
        }
        if (app.data.pendingAppointmentRequest) {
            loadError.value = 'An appointment request is already pending for this candidate.'
            return
        }

        // Prefill from candidate / vacancy
        form.base_salary = app.data.expectedSalary ?? null
        form.department_id = app.data.vacancy?.departmentId ?? ''
        form.position_id = app.data.vacancy?.positionId ?? ''
    } catch (err: any) {
        console.error('Failed to load appointment form', err)
        loadError.value = err?.data?.message || 'Failed to load candidate details.'
    } finally {
        loading.value = false
    }
}

const submitForm = async () => {
    isSubmitting.value = true
    formError.value = null

    try {
        const payload: Record<string, any> = {
            application_id: form.application_id,
            start_date: form.start_date,
            employment_type: form.employment_type,
            base_salary: form.base_salary ?? null,
            department_id: form.department_id || null,
            position_id: form.position_id || null,
            manager_id: form.manager_id || null,
            notes: form.notes || null
        }

        await api.post('/employee-appointments', payload)

        router.push('/approvals/requests')
    } catch (err: any) {
        console.error('Error submitting appointment request', err)
        formError.value = err.data?.message || 'Failed to submit appointment request.'
    } finally {
        isSubmitting.value = false
    }
}

onMounted(() => {
    loadEverything()
})
</script>

<style scoped>
.candidate-avatar {
    width: 64px;
    height: 64px;
    border-radius: 18px;
    display: grid;
    place-items: center;
    font-size: 1.25rem;
    font-weight: 700;
    letter-spacing: 0.02em;
    color: var(--color-info);
    background: linear-gradient(135deg,
        color-mix(in srgb, var(--color-info) 18%, transparent),
        color-mix(in srgb, var(--color-primary) 14%, transparent));
    border: 1px solid color-mix(in srgb, var(--color-info) 25%, transparent);
    box-shadow: 0 6px 16px -8px color-mix(in srgb, var(--color-info) 40%, transparent);
}

.candidate-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.25rem 0.625rem;
    border-radius: 9999px;
    background: var(--bg-muted);
    border: 1px solid var(--border-color);
    color: var(--text-body);
    font-size: 0.6875rem;
    font-weight: 500;
    text-decoration: none;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.candidate-chip--link:hover {
    color: var(--color-info);
    border-color: color-mix(in srgb, var(--color-info) 40%, transparent);
    background: color-mix(in srgb, var(--color-info) 8%, var(--bg-muted));
}

.employment-pill {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.4rem;
    padding: 0.75rem 0.875rem;
    border-radius: 0.75rem;
    background: var(--bg-muted);
    border: 1.5px solid var(--border-color);
    color: var(--text-muted);
    font-size: 0.75rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease, transform 0.1s ease;
}

.employment-pill:hover {
    color: var(--text-heading);
    border-color: color-mix(in srgb, var(--color-info) 35%, var(--border-color));
}

.employment-pill:active {
    transform: scale(0.98);
}

.employment-pill--active {
    color: var(--color-info);
    background: color-mix(in srgb, var(--color-info) 10%, var(--bg-card));
    border-color: var(--color-info);
    box-shadow: 0 4px 12px -6px color-mix(in srgb, var(--color-info) 50%, transparent);
}

.picker-row {
    background: transparent;
    border: none;
    cursor: pointer;
    transition: background 0.15s ease;
}

.picker-row:hover {
    background: color-mix(in srgb, var(--color-info) 6%, var(--bg-card));
}

.picker-row:hover .picker-cta {
    color: var(--color-info);
    transform: translateX(2px);
}

.picker-avatar {
    width: 44px;
    height: 44px;
    border-radius: 12px;
    display: grid;
    place-items: center;
    font-size: 0.875rem;
    font-weight: 700;
    color: var(--color-info);
    background: linear-gradient(135deg,
        color-mix(in srgb, var(--color-info) 18%, transparent),
        color-mix(in srgb, var(--color-primary) 14%, transparent));
    border: 1px solid color-mix(in srgb, var(--color-info) 25%, transparent);
}

.picker-cta {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    color: var(--text-muted);
    transition: color 0.15s ease, transform 0.15s ease;
}

.appointment-footer {
    position: sticky;
    bottom: 0.5rem;
    z-index: 10;
    display: flex;
    flex-direction: column;
    gap: 0.75rem;
    padding: 0.875rem 1rem;
    border-radius: 1rem;
    border: 1px solid var(--border-color);
    background: color-mix(in srgb, var(--bg-card) 92%, transparent);
    backdrop-filter: blur(8px);
    box-shadow: 0 12px 24px -16px rgb(0 0 0 / 0.25);
}

@media (min-width: 640px) {
    .appointment-footer {
        flex-direction: row;
        align-items: center;
    }
}
</style>
