<template>
    <NuxtLayout name="default">
        <div class="max-w-4xl mx-auto space-y-8 pb-12">
            <!-- Hero banner -->
            <section class="relative overflow-hidden rounded-2xl border border-(--border-color) bg-(--bg-card) p-6 sm:p-8 shadow-(--shadow-sm)">
                <div class="absolute -top-20 -right-16 w-72 h-72 rounded-full blur-3xl bg-(--color-secondary)/15 pointer-events-none" />
                <div class="absolute -bottom-20 -left-20 w-72 h-72 rounded-full blur-3xl bg-(--color-info)/10 pointer-events-none" />

                <div class="relative z-10 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                    <div class="flex items-start gap-4">
                        <button @click="router.back()"
                            class="w-10 h-10 mt-1 rounded-full bg-(--bg-muted) flex items-center justify-center hover:bg-(--color-primary)/10 hover:text-(--color-primary) transition-colors shrink-0">
                            <i class="ti ti-arrow-left text-xl"></i>
                        </button>
                        <div class="space-y-2 max-w-2xl">
                            <Badge variant="secondary" :dot="true">eApprovals · Performance</Badge>
                            <h1 class="text-2xl font-bold tracking-tight text-(--text-heading)">
                                New Appraisal Request
                            </h1>
                            <p class="text-xs text-(--text-body) leading-relaxed">
                                Open a review cycle. Strengths, growth areas, and OKR goals feed the reviewer summary.
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            <div v-if="loading" class="glass-card rounded-2xl p-16 flex flex-col items-center justify-center gap-3 border border-(--border-color)">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading form details...</span>
            </div>

            <form v-else @submit.prevent="submitForm" class="space-y-6">

                <!-- Participant Details -->
                <section class="glass-card rounded-2xl p-6 border border-(--border-color) space-y-5">
                    <header>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-(--text-muted) flex items-center gap-2">
                            <i class="ti ti-users text-sm" />Participant Details
                        </h3>
                        <p class="text-xxs text-(--text-muted) mt-1">Specify who is being reviewed and the reviewer.</p>
                    </header>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <!-- Employee -->
                        <div>
                            <label class="form-label form-label-required">Employee</label>
                            <div v-if="isAdmin" class="input-with-icon">
                                <i class="ti ti-user input-icon"></i>
                                <select v-model="form.employee_id" required class="form-control bg-(--bg-muted) border-transparent focus:border-(--color-primary) focus:ring-1 focus:ring-(--color-primary) focus:bg-transparent appearance-none">
                                    <option value="" disabled>Select employee...</option>
                                    <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.fullName }} ({{ e.employeeId }})</option>
                                </select>
                            </div>
                            <div v-else class="border border-(--border-color) rounded-xl p-4 flex items-center gap-3 bg-(--bg-muted)/50">
                                <div class="w-10 h-10 rounded-full bg-(--color-primary)/10 text-(--color-primary) flex items-center justify-center">
                                    <i class="ti ti-user text-lg"></i>
                                </div>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-(--text-heading) truncate">{{ currentEmployee?.fullName || authStore.user?.name || 'Current user' }}</p>
                                    <p class="text-xxs text-(--text-muted) font-mono">{{ currentEmployee?.employeeId || authStore.user?.email || '—' }}</p>
                                </div>
                            </div>
                            <p class="form-hint">
                                <i class="ti ti-info-circle mr-1"></i>
                                <span v-if="isAdmin">Defaults to your record.</span>
                                <span v-else>Submitting as your linked employee record.</span>
                            </p>
                        </div>

                        <!-- Reviewer -->
                        <div>
                            <label class="form-label">Reviewer</label>
                            <div class="input-with-icon">
                                <i class="ti ti-user-star input-icon"></i>
                                <select v-model="form.reviewer_id" class="form-control bg-(--bg-muted) border-transparent focus:border-(--color-primary) focus:ring-1 focus:ring-(--color-primary) focus:bg-transparent appearance-none">
                                    <option :value="''">— Self review</option>
                                    <option v-for="e in employees" :key="e.id" :value="e.id" :disabled="e.id === form.employee_id">
                                        {{ e.fullName }} ({{ e.employeeId }})
                                    </option>
                                </select>
                            </div>
                            <p class="form-hint">
                                <i class="ti ti-info-circle mr-1"></i>Leave blank to self-review.
                            </p>
                        </div>
                    </div>
                </section>

                <!-- Review Period -->
                <section class="glass-card rounded-2xl p-6 border border-(--border-color) space-y-5">
                    <header>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-(--text-muted) flex items-center gap-2">
                            <i class="ti ti-calendar-stats text-sm" />Review Period
                        </h3>
                        <p class="text-xxs text-(--text-muted) mt-1">Define the cycle and timeframe for this appraisal.</p>
                    </header>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <div>
                            <label class="form-label form-label-required">Cycle</label>
                            <input v-model="form.cycle" type="text" placeholder="2026-Q2" class="form-control font-mono bg-(--bg-muted) border-transparent focus:border-(--color-primary) focus:ring-1 focus:ring-(--color-primary) focus:bg-transparent" required />
                        </div>
                        <div>
                            <label class="form-label form-label-required">Period start</label>
                            <div class="input-with-icon">
                                <i class="ti ti-calendar input-icon"></i>
                                <input v-model="form.period_start" type="date" class="form-control bg-(--bg-muted) border-transparent focus:border-(--color-primary) focus:ring-1 focus:ring-(--color-primary) focus:bg-transparent" required />
                            </div>
                        </div>
                        <div>
                            <label class="form-label form-label-required">Period end</label>
                            <div class="input-with-icon">
                                <i class="ti ti-calendar-event input-icon"></i>
                                <input v-model="form.period_end" type="date" :min="form.period_start" class="form-control bg-(--bg-muted) border-transparent focus:border-(--color-primary) focus:ring-1 focus:ring-(--color-primary) focus:bg-transparent" required />
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Qualitative Feedback -->
                <section class="glass-card rounded-2xl p-6 border border-(--border-color) space-y-5">
                    <header>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-(--text-muted) flex items-center gap-2">
                            <i class="ti ti-message-report text-sm" />Qualitative Feedback
                        </h3>
                        <p class="text-xxs text-(--text-muted) mt-1">Document key achievements and growth areas.</p>
                    </header>

                    <div class="space-y-6">
                        <!-- Strengths -->
                        <div>
                            <label class="form-label">Strengths</label>
                            <textarea v-model="form.strengths" rows="3" placeholder="Consistent shipping, mentors juniors..." class="form-control bg-(--bg-muted) border-transparent focus:border-(--color-primary) focus:ring-1 focus:ring-(--color-primary) focus:bg-transparent resize-none"></textarea>
                        </div>

                        <!-- Areas for improvement -->
                        <div>
                            <label class="form-label">Areas for improvement</label>
                            <textarea v-model="form.improvements" rows="3" placeholder="Cross-team alignment, deeper specs..." class="form-control bg-(--bg-muted) border-transparent focus:border-(--color-primary) focus:ring-1 focus:ring-(--color-primary) focus:bg-transparent resize-none"></textarea>
                        </div>
                    </div>
                </section>

                <!-- OKR Goals -->
                <section class="glass-card rounded-2xl p-6 border border-(--border-color) space-y-5">
                    <header class="flex items-center justify-between">
                        <div>
                            <h3 class="text-xs font-semibold uppercase tracking-widest text-(--text-muted) flex items-center gap-2">
                                <i class="ti ti-target text-sm" />Goals (OKR-style)
                            </h3>
                            <p class="text-xxs text-(--text-muted) mt-1">Track specific, measurable objectives.</p>
                        </div>
                        <button type="button" class="btn btn-secondary text-xs px-3 py-1.5" @click="addGoal">
                            <i class="ti ti-plus" />Add Goal
                        </button>
                    </header>

                    <div>
                        <div v-if="form.goals.length === 0"
                            class="rounded-xl bg-(--bg-muted)/30 border border-dashed border-(--border-color) p-8 text-sm text-(--text-muted) text-center flex flex-col items-center justify-center gap-2">
                            <i class="ti ti-target text-2xl text-(--text-muted)/50"></i>
                            No goals yet. Add one to track OKRs.
                        </div>
                        <div v-else class="space-y-3">
                            <div v-for="(goal, idx) in form.goals" :key="idx"
                                class="rounded-xl border border-(--border-color) p-3 grid grid-cols-12 gap-3 items-center bg-(--bg-card) shadow-sm">
                                <input v-model="goal.title" type="text" required placeholder="Goal title" class="form-control col-span-12 sm:col-span-5 bg-(--bg-muted)/50 border-transparent focus:border-(--color-primary) focus:ring-1 focus:ring-(--color-primary) focus:bg-transparent" />
                                <select v-model="goal.status" class="form-control col-span-6 sm:col-span-3 bg-(--bg-muted)/50 border-transparent focus:border-(--color-primary) focus:ring-1 focus:ring-(--color-primary) focus:bg-transparent appearance-none">
                                    <option value="pending">Pending</option>
                                    <option value="in_progress">In progress</option>
                                    <option value="achieved">Achieved</option>
                                    <option value="missed">Missed</option>
                                </select>
                                <input v-model="goal.due" type="date" class="form-control col-span-6 sm:col-span-3 bg-(--bg-muted)/50 border-transparent focus:border-(--color-primary) focus:ring-1 focus:ring-(--color-primary) focus:bg-transparent" />
                                <button type="button" class="col-span-12 sm:col-span-1 text-(--text-muted) hover:text-(--color-danger) p-2 flex items-center justify-center rounded-lg hover:bg-(--color-danger)/10 transition-colors"
                                    @click="removeGoal(idx)" title="Remove goal">
                                    <i class="ti ti-trash text-lg" />
                                </button>
                            </div>
                        </div>
                    </div>
                </section>

                <div v-if="formError" class="text-sm text-(--color-danger) bg-(--color-danger-subtle) px-4 py-3 rounded-xl border border-(--color-danger)/20">
                    <i class="ti ti-alert-circle mr-1"></i> {{ formError }}
                </div>

                <!-- Sticky-feeling action footer -->
                <div class="appointment-footer">
                    <p class="text-xxs text-(--text-muted) sm:flex-1">
                        <i class="ti ti-shield-check mr-1 text-(--color-success)" />
                        This appraisal request is subject to review workflow.
                    </p>
                    <div class="flex items-center gap-3 shrink-0">
                        <button type="button" @click="router.back()" class="btn btn-secondary px-6">Cancel</button>
                        <button type="submit" class="btn btn-primary px-8 flex items-center gap-2" :disabled="isSubmitting || !form.employee_id">
                            <i v-if="isSubmitting" class="ti ti-loader animate-spin"></i>
                            <i v-else class="ti ti-send"></i>
                            <span>{{ isSubmitting ? 'Submitting...' : 'Submit Request' }}</span>
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'

interface EmployeeLite { id: string; employeeId: string; fullName: string }
interface Goal { title: string; status?: 'pending' | 'in_progress' | 'achieved' | 'missed'; due?: string | null }
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

const router = useRouter()
const api = useApi()
const authStore = useAuthStore()

const isAdmin = computed(() => authStore.hasPermission('hrm.performance.write'))

const employees = ref<EmployeeLite[]>([])
const currentEmployee = ref<EmployeeLite | null>(null)
const loading = ref(true)
const isSubmitting = ref(false)
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

const addGoal = () => { form.goals.push({ title: '', status: 'pending', due: '' }) }
const removeGoal = (idx: number) => { form.goals.splice(idx, 1) }

const loadLookups = async () => {
    loading.value = true
    try {
        const [me, list] = await Promise.all([
            api.get<{ data: EmployeeLite }>('/employees/me').catch(() => null),
            api.get<Paginated<EmployeeLite>>('/employees?limit=200').catch(() => null)
        ])

        if (me?.data) {
            currentEmployee.value = me.data
            form.employee_id = me.data.id
        }
        if (list?.data) {
            employees.value = list.data
        }
    } catch (err) {
        console.error('Failed to load lookups', err)
        formError.value = 'Failed to load form requirements.'
    } finally {
        loading.value = false
    }
}

const submitForm = async () => {
    isSubmitting.value = true
    formError.value = null

    try {
        const payload: Record<string, any> = {
            employee_id: form.employee_id,
            reviewer_id: form.reviewer_id || null,
            cycle: form.cycle,
            period_start: form.period_start,
            period_end: form.period_end,
            strengths: form.strengths || null,
            improvements: form.improvements || null,
            goals: form.goals.filter(g => g.title).map(g => ({
                title: g.title,
                status: g.status || 'pending',
                due: g.due || null
            }))
        }

        await api.post('/appraisals', payload)

        router.push('/approvals/requests')
    } catch (err: any) {
        console.error('Error submitting form:', err)
        formError.value = err.data?.message || 'Failed to submit appraisal request.'
    } finally {
        isSubmitting.value = false
    }
}

onMounted(() => {
    loadLookups()
})
</script>

<style scoped>
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
