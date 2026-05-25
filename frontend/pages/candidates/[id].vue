<template>
    <NuxtLayout name="default">
        <!-- ============================ Loading ============================ -->
        <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
            <span
                class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
            <span class="text-xs text-(--text-muted) font-medium">Loading candidate profile...</span>
        </div>

        <!-- ============================ Not found ========================== -->
        <div v-else-if="!app" class="py-24 flex flex-col items-center justify-center gap-3">
            <i class="ti ti-user-question text-4xl text-(--text-muted)" />
            <p class="text-sm text-(--text-heading) font-semibold">Candidate not found</p>
            <p class="text-xs text-(--text-muted)">It may have been removed or you don't have access.</p>
            <NuxtLink to="/candidates" class="btn btn-soft-primary text-xs mt-2">
                <i class="ti ti-arrow-left" /> Back to pipeline
            </NuxtLink>
        </div>

        <!-- ============================ Profile ============================ -->
        <div v-else class="space-y-6">
            <!-- Breadcrumb -->
            <nav class="text-xxs text-(--text-muted) flex items-center gap-1.5">
                <NuxtLink to="/candidates" class="hover:text-(--color-primary)">Candidates</NuxtLink>
                <i class="ti ti-chevron-right text-[10px]" />
                <span class="text-(--text-body) truncate max-w-[260px]">{{ app.applicantName }}</span>
            </nav>

            <!-- ===== Hero header ===== -->
            <section class="glass-card rounded-2xl p-6 md:p-7">
                <div class="flex flex-col md:flex-row items-start md:items-center gap-5">
                    <!-- Avatar -->
                    <div class="w-16 h-16 md:w-20 md:h-20 rounded-2xl flex items-center justify-center text-xl md:text-2xl font-bold bg-(--color-primary-subtle) text-(--color-primary) shrink-0"
                        :title="app.applicantName">
                        {{ initials(app.applicantName) }}
                    </div>

                    <!-- Identity -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h1 class="text-lg md:text-xl font-semibold text-(--text-heading) truncate">
                                {{ app.applicantName }}
                            </h1>
                            <span v-if="app.candidateCode"
                                class="font-mono text-xxs text-(--text-muted) bg-(--bg-muted) px-1.5 py-0.5 rounded">
                                {{ app.candidateCode }}
                            </span>
                        </div>
                        <p class="text-xs text-(--text-muted) mt-1 truncate">
                            <span class="text-(--text-body)">{{ app.vacancy?.title || 'Open Position' }}</span>
                            <span v-if="app.location"> · {{ app.location }}</span>
                            <span> · Applied {{ relativeTime(app.appliedAt) }}</span>
                        </p>
                    </div>

                    <!-- Quick contact -->
                    <div class="flex items-center gap-1.5 self-start md:self-center">
                        <a v-if="app.applicantEmail" :href="`mailto:${app.applicantEmail}`" class="icon-btn"
                            title="Send email">
                            <i class="ti ti-mail" />
                        </a>
                        <a v-if="app.applicantPhone" :href="`tel:${app.applicantPhone}`" class="icon-btn" title="Call">
                            <i class="ti ti-phone" />
                        </a>
                        <a v-if="app.linkedinUrl" :href="app.linkedinUrl" target="_blank" rel="noopener"
                            class="icon-btn" title="LinkedIn">
                            <i class="ti ti-brand-linkedin" />
                        </a>
                    </div>
                </div>
            </section>

            <!-- ===== Quick actions ===== -->
            <section class="flex flex-wrap items-center gap-3 relative">
                <div class="relative">
                    <button v-if="canWrite" type="button" class="btn btn-primary text-xs"
                        :disabled="!nextStages.length || advancing" @click="openAdvanceMenu = !openAdvanceMenu">
                        <i :class="['ti', advancing ? 'ti-loader animate-spin' : 'ti-arrow-forward-up']" />
                        {{ advancing ? 'Updating...' : 'Advance stage' }}
                        <i class="ti ti-chevron-down text-[12px]" />
                    </button>

                    <div v-if="openAdvanceMenu && nextStages.length"
                        class="absolute top-full mt-2 left-0 z-30 glass-card rounded-xl p-1.5 shadow-(--shadow-lg) min-w-[200px]">
                        <button v-for="s in nextStages" :key="s" type="button"
                            class="w-full text-left text-xs px-3 py-2 rounded-md hover:bg-(--bg-muted) flex items-center gap-2"
                            @click="advanceTo(s)">
                            <Badge :variant="statusVariant(s)" :dot="true">{{ statusLabel(s) }}</Badge>
                        </button>
                    </div>
                </div>

                <a v-if="app.resumePath" :href="resumeHref" target="_blank" rel="noopener"
                    class="btn btn-ghost text-xs">
                    <i class="ti ti-file-cv" /> View resume
                </a>

                <button type="button" class="btn btn-ghost text-xs" @click="copyEmail">
                    <i class="ti ti-mail-share" /> {{ copied ? 'Copied!' : 'Copy email' }}
                </button>

                <div class="flex-1" />

                <Badge :variant="statusVariant(app.status)" :dot="true">
                    {{ statusLabel(app.status) }}
                </Badge>
            </section>

            <!-- ===== Bento grid ===== -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                <!-- ============== LEFT COLUMN ============== -->
                <div class="lg:col-span-8 space-y-6">
                    <!-- Cover letter / Summary -->
                    <article class="glass-card rounded-2xl p-6 space-y-4">
                        <header class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-(--text-heading) flex items-center gap-2">
                                <i class="ti ti-user-search text-(--color-primary)" /> Cover letter
                            </h2>
                            <button v-if="canWrite" type="button"
                                class="text-xxs text-(--color-primary) hover:underline" @click="goToEdit">
                                Edit
                            </button>
                        </header>
                        <p v-if="app.coverLetter"
                            class="text-xs text-(--text-body) leading-relaxed whitespace-pre-wrap">{{ app.coverLetter }}
                        </p>
                        <p v-else class="text-xs text-(--text-muted) italic">
                            No cover letter submitted.
                        </p>

                        <!-- Stat row -->
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 pt-3 border-t border-(--border-color)">
                            <div class="stat-tile">
                                <p class="stat-label"><i class="ti ti-star-filled text-[12px] text-(--color-warning)" />
                                    Rating</p>
                                <p class="stat-value text-(--color-warning)">{{ rating.toFixed(1) }}</p>
                            </div>
                            <div class="stat-tile">
                                <p class="stat-label"><i class="ti ti-calendar-time text-[12px] text-(--color-info)" />
                                    Days in pipeline</p>
                                <p class="stat-value text-(--color-info)">{{ daysInPipeline }}</p>
                            </div>
                            <div v-if="canSeeSalary" class="stat-tile">
                                <p class="stat-label"><i class="ti ti-coin text-[12px] text-(--color-primary)" />
                                    Expected</p>
                                <p class="stat-value text-(--color-primary) font-mono">
                                    {{ app.expectedSalary != null ? formatMoney(app.expectedSalary) : '—' }}
                                </p>
                            </div>
                            <div class="stat-tile">
                                <p class="stat-label"><i class="ti ti-route text-[12px] text-(--color-secondary)" />
                                    Source</p>
                                <p class="stat-value text-(--color-secondary)">{{ shortSource(app) }}</p>
                            </div>
                        </div>
                    </article>

                    <!-- Vacancy + Referrer + Documents -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Vacancy / Referrer -->
                        <article class="glass-card rounded-2xl p-6 space-y-4">
                            <h2 class="text-sm font-semibold text-(--text-heading) flex items-center gap-2">
                                <i class="ti ti-target text-(--color-secondary)" /> Pipeline context
                            </h2>

                            <div>
                                <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted) mb-2">Target
                                    vacancy</p>
                                <NuxtLink v-if="app.vacancy" :to="`/candidates?vacancyId=${app.vacancy.id}`"
                                    class="flex items-center gap-3 p-3 rounded-lg bg-(--bg-muted) border border-(--border-color) hover:border-(--color-primary)/40 transition-colors">
                                    <span
                                        class="w-10 h-10 rounded-lg bg-(--color-primary-subtle) text-(--color-primary) inline-flex items-center justify-center shrink-0">
                                        <i class="ti ti-briefcase text-lg" />
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold text-(--text-heading) truncate">{{
                                            app.vacancy.title }}</p>
                                        <p class="text-xxs text-(--text-muted)">Open pipeline · View board</p>
                                    </div>
                                    <i class="ti ti-arrow-up-right text-(--text-muted) ml-auto" />
                                </NuxtLink>
                                <p v-else class="text-xxs text-(--text-muted) italic">No vacancy linked.</p>
                            </div>

                            <div>
                                <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted) mb-2">
                                    Referred by</p>
                                <div v-if="referrer"
                                    class="flex items-center gap-3 p-3 rounded-lg bg-(--bg-muted) border border-(--border-color)">
                                    <span
                                        class="w-9 h-9 rounded-full bg-(--color-secondary-subtle) text-(--color-secondary) inline-flex items-center justify-center font-bold text-xxs shrink-0">
                                        {{ initials(referrer.fullName) }}
                                    </span>
                                    <div class="min-w-0">
                                        <p class="text-xs font-semibold text-(--text-heading) truncate">{{
                                            referrer.fullName }}</p>
                                        <p class="text-xxs text-(--text-muted) font-mono">{{ referrer.employeeId }}</p>
                                    </div>
                                </div>
                                <p v-else class="text-xxs text-(--text-muted) italic">No referrer — direct application.
                                </p>
                            </div>
                        </article>

                        <!-- Documents -->
                        <article class="glass-card rounded-2xl p-6 space-y-3">
                            <h2 class="text-sm font-semibold text-(--text-heading) flex items-center gap-2">
                                <i class="ti ti-folder text-(--color-info)" /> Documents
                            </h2>

                            <a v-if="app.resumePath" :href="resumeHref" target="_blank" rel="noopener" class="doc-row">
                                <span class="doc-icon bg-(--color-danger-subtle) text-(--color-danger)">
                                    <i class="ti ti-file-cv text-lg" />
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-xs font-semibold text-(--text-heading) truncate">Resume /
                                        CV</span>
                                    <span class="block text-xxs text-(--text-muted) font-mono truncate">{{
                                        app.resumePath }}</span>
                                </span>
                                <Badge variant="success" :dot="true">On file</Badge>
                            </a>
                            <div v-else class="doc-row doc-row--empty">
                                <span class="doc-icon bg-(--bg-muted) text-(--text-muted)">
                                    <i class="ti ti-file-off text-lg" />
                                </span>
                                <span class="flex-1 text-xs text-(--text-muted)">No resume uploaded</span>
                            </div>

                            <div class="doc-row">
                                <span class="doc-icon bg-(--color-primary-subtle) text-(--color-primary)">
                                    <i class="ti ti-file-description text-lg" />
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-xs font-semibold text-(--text-heading)">Cover letter</span>
                                    <span class="block text-xxs text-(--text-muted)">
                                        {{ app.coverLetter ? `${app.coverLetter.length} chars` : 'Not provided' }}
                                    </span>
                                </span>
                                <Badge :variant="app.coverLetter ? 'info' : 'secondary'" :dot="true">
                                    {{ app.coverLetter ? 'Submitted' : 'Missing' }}
                                </Badge>
                            </div>

                            <a v-if="app.linkedinUrl" :href="app.linkedinUrl" target="_blank" rel="noopener"
                                class="doc-row">
                                <span class="doc-icon bg-(--color-secondary-subtle) text-(--color-secondary)">
                                    <i class="ti ti-brand-linkedin text-lg" />
                                </span>
                                <span class="flex-1 min-w-0">
                                    <span class="block text-xs font-semibold text-(--text-heading)">LinkedIn
                                        profile</span>
                                    <span class="block text-xxs text-(--text-muted) font-mono truncate">{{
                                        app.linkedinUrl }}</span>
                                </span>
                                <i class="ti ti-arrow-up-right text-(--text-muted)" />
                            </a>
                        </article>
                    </div>

                    <!-- Work experience -->
                    <article class="glass-card rounded-2xl p-6 space-y-4">
                        <h2 class="text-sm font-semibold text-(--text-heading) flex items-center gap-2">
                            <i class="ti ti-briefcase-2 text-(--color-primary)" /> Work experience
                        </h2>

                        <div v-if="!workExperience.length"
                            class="rounded-lg border border-dashed border-(--border-color) text-xxs text-(--text-muted) py-6 text-center">
                            No work experience recorded for this candidate.
                        </div>

                        <ol v-else class="relative border-l-2 border-(--border-color) ml-2 space-y-5 pl-5">
                            <li v-for="(exp, idx) in workExperience" :key="idx" class="relative">
                                <span
                                    class="absolute -left-[26px] top-1 w-4 h-4 rounded-full bg-(--color-primary) border-2 border-(--bg-card)" />
                                <div class="flex flex-wrap items-baseline justify-between gap-2">
                                    <p class="text-xs font-bold text-(--text-heading)">{{ exp.title || '—' }}</p>
                                    <p class="text-xxs text-(--text-muted) font-mono">{{ exp.period || '—' }}</p>
                                </div>
                                <p class="text-xxs text-(--color-primary) font-semibold mt-0.5">
                                    {{ exp.company || '—' }}
                                    <span v-if="exp.location" class="text-(--text-muted) font-normal"> · {{ exp.location
                                        }}</span>
                                </p>
                                <p v-if="exp.description"
                                    class="text-xxs text-(--text-body) mt-2 leading-relaxed whitespace-pre-line">
                                    {{ exp.description }}
                                </p>
                            </li>
                        </ol>
                    </article>
                </div>

                <!-- ============== RIGHT COLUMN ============== -->
                <div class="lg:col-span-4 space-y-6">
                    <!-- Skills -->
                    <article class="glass-card rounded-2xl p-6 space-y-4">
                        <header class="flex items-center justify-between">
                            <h2 class="text-sm font-semibold text-(--text-heading) flex items-center gap-2">
                                <i class="ti ti-bolt text-(--color-primary)" /> Skills
                            </h2>
                            <span class="text-xxs text-(--text-muted)">{{ skills.length }}</span>
                        </header>

                        <div v-if="!skills.length" class="text-xxs text-(--text-muted) italic">
                            No skills listed.
                        </div>

                        <div v-else class="flex flex-wrap gap-1.5">
                            <span v-for="skill in skills" :key="skill"
                                class="px-2.5 py-1 rounded-full bg-(--color-primary-subtle) text-(--color-primary) border border-(--color-primary)/20 text-[11px] font-semibold">
                                {{ skill }}
                            </span>
                        </div>
                    </article>

                    <!-- Education -->
                    <article class="glass-card rounded-2xl p-6 space-y-4">
                        <h2 class="text-sm font-semibold text-(--text-heading) flex items-center gap-2">
                            <i class="ti ti-school text-(--color-info)" /> Education
                        </h2>

                        <div v-if="!education.length" class="text-xxs text-(--text-muted) italic">
                            No education recorded.
                        </div>

                        <ul v-else class="space-y-3">
                            <li v-for="(edu, idx) in education" :key="idx" class="flex items-start gap-3">
                                <span
                                    class="w-8 h-8 rounded-lg bg-(--color-info-subtle) text-(--color-info) inline-flex items-center justify-center shrink-0">
                                    <i class="ti ti-certificate" />
                                </span>
                                <div class="min-w-0">
                                    <p class="text-xs font-bold text-(--text-heading)">{{ edu.degree || '—' }}</p>
                                    <p class="text-xxs text-(--text-muted)">{{ edu.school || '—' }}</p>
                                </div>
                            </li>
                        </ul>
                    </article>

                    <!-- Timeline -->
                    <article class="glass-card rounded-2xl p-6 space-y-4">
                        <h2 class="text-sm font-semibold text-(--text-heading) flex items-center gap-2">
                            <i class="ti ti-timeline-event text-(--color-secondary)" /> Application timeline
                        </h2>

                        <ol
                            class="relative space-y-4 before:absolute before:left-[11px] before:top-2 before:bottom-2 before:w-[2px] before:bg-(--border-color)">
                            <li v-for="event in timeline" :key="event.key" class="relative pl-8">
                                <span
                                    class="absolute left-0 top-0.5 w-6 h-6 rounded-full inline-flex items-center justify-center border-2"
                                    :class="event.done ? 'bg-(--color-primary-subtle) border-(--color-primary)/40 text-(--color-primary)' : 'bg-(--bg-muted) border-(--border-color) text-(--text-muted)'">
                                    <i :class="['ti text-[12px]', event.icon]" />
                                </span>
                                <p class="text-xs font-semibold text-(--text-heading)">{{ event.label }}</p>
                                <p class="text-[11px] text-(--text-muted) mt-0.5">{{ event.detail }}</p>
                            </li>
                        </ol>
                    </article>

                    <!-- Conversion footer (if hired) -->
                    <article v-if="app.status === 'hired'"
                        class="glass-card rounded-2xl p-6 space-y-3 border border-(--color-success)/30">
                        <h2 class="text-sm font-semibold text-(--color-success) flex items-center gap-2">
                            <i class="ti ti-confetti" /> Hired!
                        </h2>
                        <p v-if="app.employeeId" class="text-xxs text-(--text-muted)">
                            This candidate is linked to an employee record.
                        </p>
                        <p v-else class="text-xxs text-(--text-muted)">
                            Convert this candidate into a full employee record to begin onboarding.
                        </p>
                        <NuxtLink v-if="app.employeeId" :to="`/employees?id=${app.employeeId}`"
                            class="btn btn-soft-primary text-xs w-full justify-center">
                            <i class="ti ti-user-check" /> View employee
                        </NuxtLink>
                    </article>
                </div>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '~/composables/useApi'
import { formatDate } from '~/composables/useDateFormat'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import Badge from '~/components/Badge.vue'

definePageMeta({
    breadcrumb: 'Candidate Profile'
})

type ApplicationStatus = 'applied' | 'screening' | 'interview' | 'offer' | 'hired' | 'rejected' | 'withdrawn'

interface VacancyLite { id: string; title: string }
interface ReferrerLite { id: string; employeeId: string; fullName: string }
interface WorkExperience { title?: string; company?: string; period?: string; location?: string; description?: string }
interface Education { degree?: string; school?: string }

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
    workExperience: WorkExperience[] | null
    education: Education[] | null
    skills: string[] | null
    expectedSalary: number | null
    notes: string | null
    status: ApplicationStatus
    appliedAt: string | null
    convertedAt: string | null
    vacancy?: VacancyLite
    referrer?: ReferrerLite
    referrerEmployeeId?: string | null
}

const STATUS_FLOW: Record<ApplicationStatus, ApplicationStatus[]> = {
    applied: ['screening', 'rejected', 'withdrawn'],
    screening: ['interview', 'rejected', 'withdrawn'],
    interview: ['offer', 'rejected', 'withdrawn'],
    offer: ['hired', 'rejected', 'withdrawn'],
    hired: [],
    rejected: [],
    withdrawn: []
}

const STAGE_INDEX: Record<ApplicationStatus, number> = {
    applied: 0, screening: 1, interview: 2, offer: 3, hired: 4,
    rejected: -1, withdrawn: -1
}

const route = useRoute()
const api = useApi()
const authStore = useAuthStore()
const toast = useToast()

const canWrite = computed(() => authStore.hasPermission('hrm.recruitment.write'))
const canSeeSalary = computed(() => authStore.hasPermission('hrm.recruitment.read'))

const loading = ref(true)
const app = ref<Application | null>(null)
const advancing = ref(false)
const openAdvanceMenu = ref(false)
const copied = ref(false)

const loadCandidate = async () => {
    const id = route.params.id as string
    if (!id) {
        loading.value = false
        return
    }
    loading.value = true
    try {
        const res = await api.get<{ data: Application } | Application>(`/applications/${id}`)
        app.value = (res as { data?: Application })?.data ?? (res as Application)
    } catch (err) {
        console.error('Failed to load candidate', err)
        app.value = null
    } finally {
        loading.value = false
    }
}

// ---------- helpers ----------
const initials = (name?: string | null): string =>
    (name || '?').split(/\s+/).filter(Boolean).slice(0, 2).map(p => p[0]!.toUpperCase()).join('') || '?'

const formatMoney = (n: number): string =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(n)

const relativeTime = (iso: string | null): string => {
    if (!iso) return '—'
    const diffMs = Date.now() - new Date(iso).getTime()
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

const statusLabel = (s: ApplicationStatus): string => {
    const labels: Record<ApplicationStatus, string> = {
        applied: 'Applied', screening: 'Screening', interview: 'Interview',
        offer: 'Offer sent', hired: 'Hired', rejected: 'Rejected', withdrawn: 'Withdrawn'
    }
    return labels[s]
}

const statusVariant = (s: ApplicationStatus): 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'secondary' => {
    switch (s) {
        case 'applied': return 'secondary'
        case 'screening': return 'info'
        case 'interview': return 'warning'
        case 'offer': return 'primary'
        case 'hired': return 'success'
        case 'rejected': return 'danger'
        case 'withdrawn': return 'secondary'
    }
}

const sourceIcon = (a: Application): string => {
    if (a.referrerEmployeeId || a.referrer) return 'ti-user-check'
    if (a.resumePath) return 'ti-file-cv'
    return 'ti-mail'
}
const sourceLabel = (a: Application): string => {
    if (a.referrerEmployeeId || a.referrer) return 'Employee referral'
    if (a.resumePath) return 'Resume submission'
    return 'Direct email'
}
const shortSource = (a: Application): string => {
    if (a.referrerEmployeeId || a.referrer) return 'Referral'
    if (a.resumePath) return 'Resume'
    return 'Direct'
}

// ---------- derived ----------
const skills = computed(() => app.value?.skills ?? [])
const education = computed(() => app.value?.education ?? [])
const workExperience = computed(() => app.value?.workExperience ?? [])
const referrer = computed(() => app.value?.referrer ?? null)

const currentStageIndex = computed(() => {
    if (!app.value) return -1
    return STAGE_INDEX[app.value.status] ?? -1
})

const nextStages = computed<ApplicationStatus[]>(() => {
    if (!app.value) return []
    return STATUS_FLOW[app.value.status] ?? []
})

const daysInPipeline = computed(() => {
    if (!app.value?.appliedAt) return 0
    const ms = Date.now() - new Date(app.value.appliedAt).getTime()
    return Math.max(0, Math.floor(ms / 86_400_000))
})

const rating = computed(() => {
    const a = app.value
    if (!a) return 0
    let r = 3
    if (a.coverLetter) r += 0.5
    if (a.resumePath) r += 0.5
    if (a.skills?.length) r += Math.min(0.5, a.skills.length * 0.1)
    if (a.referrerEmployeeId || a.referrer) r += 0.5
    return Math.max(1, Math.min(5, r))
})

const resumeHref = computed(() => {
    const path = app.value?.resumePath
    if (!path) return '#'
    return path
})

const timeline = computed(() => {
    const a = app.value
    if (!a) return []
    const idx = currentStageIndex.value
    const events: Array<{ key: string; label: string; detail: string; done: boolean; icon: string }> = [
        {
            key: 'applied',
            label: 'Application received',
            detail: a.appliedAt ? formatDate(a.appliedAt) : 'Not recorded',
            done: idx >= 0,
            icon: 'ti-send'
        },
        {
            key: 'screening',
            label: 'Screening',
            detail: idx >= 1 ? 'Reviewed by recruiter' : 'Awaiting screening',
            done: idx >= 1,
            icon: 'ti-search'
        },
        {
            key: 'interview',
            label: 'Interview stage',
            detail: idx >= 2 ? 'Advanced to interview' : 'Pending interview',
            done: idx >= 2,
            icon: 'ti-microphone-2'
        },
        {
            key: 'offer',
            label: 'Offer extended',
            detail: idx >= 3 ? 'Offer sent to candidate' : 'No offer yet',
            done: idx >= 3,
            icon: 'ti-file-certificate'
        }
    ]
    if (a.status === 'hired') {
        events.push({
            key: 'hired',
            label: 'Hired',
            detail: a.convertedAt ? `Converted ${formatDate(a.convertedAt)}` : 'Pending conversion',
            done: true,
            icon: 'ti-confetti'
        })
    } else if (a.status === 'rejected') {
        events.push({
            key: 'rejected', label: 'Rejected',
            detail: 'Candidate no longer in pipeline', done: true, icon: 'ti-x'
        })
    } else if (a.status === 'withdrawn') {
        events.push({
            key: 'withdrawn', label: 'Withdrawn',
            detail: 'Candidate withdrew from pipeline', done: true, icon: 'ti-arrow-back'
        })
    }
    return events
})

// ---------- actions ----------
const advanceTo = async (target: ApplicationStatus) => {
    if (!app.value || advancing.value) return
    openAdvanceMenu.value = false
    advancing.value = true
    const original = app.value.status
    app.value.status = target
    try {
        await api.patch(`/applications/${app.value.id}/status`, { status: target })
        toast.success('Stage updated', `${app.value.applicantName} → ${statusLabel(target)}`)
    } catch (err: any) {
        app.value.status = original
        toast.error('Failed to update stage.', err?.data?.message)
    } finally {
        advancing.value = false
    }
}

const copyEmail = async () => {
    if (!app.value?.applicantEmail) return
    try {
        await navigator.clipboard.writeText(app.value.applicantEmail)
        copied.value = true
        setTimeout(() => { copied.value = false }, 1500)
    } catch {
        toast.error('Failed to copy email')
    }
}

const goToEdit = () => {
    if (!app.value) return
    navigateTo(`/applications?id=${app.value.id}`)
}

onMounted(loadCandidate)
</script>

<style scoped>
.text-xxs {
    font-size: 0.6875rem;
    line-height: 1rem;
}

.icon-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 10px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    transition: background 0.15s ease, transform 0.1s ease, border-color 0.15s ease;
}

.icon-btn:hover {
    background: var(--bg-muted);
    border-color: var(--border-strong);
}

.icon-btn:active {
    transform: scale(0.95);
}

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
    display: inline-flex;
    align-items: center;
    gap: 0.25rem;
}

.stat-value {
    font-size: 1rem;
    font-weight: 700;
    line-height: 1.25;
}

.doc-row {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.625rem;
    border-radius: 0.625rem;
    background: color-mix(in srgb, var(--bg-muted) 60%, transparent);
    border: 1px solid var(--border-color);
    transition: background 0.15s ease, border-color 0.15s ease;
    text-decoration: none;
}

.doc-row:hover:not(.doc-row--empty) {
    background: var(--bg-muted);
    border-color: var(--border-strong);
}

.doc-row--empty {
    border-style: dashed;
    opacity: 0.85;
}

.doc-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px;
    height: 36px;
    border-radius: 0.5rem;
    flex-shrink: 0;
}
</style>
