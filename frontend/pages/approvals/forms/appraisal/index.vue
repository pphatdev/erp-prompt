<template>
    <NuxtLayout name="default">
        <div class="max-w-3xl mx-auto py-8">
            <!-- Header -->
            <div class="mb-8 flex items-center gap-4">
                <button @click="router.back()" class="w-10 h-10 rounded-full bg-(--bg-muted) flex items-center justify-center hover:bg-primary/10 hover:text-primary transition-colors">
                    <i class="ti ti-arrow-left text-xl"></i>
                </button>
                <div>
                    <h1 class="text-2xl font-bold">New Appraisal Request</h1>
                    <p class="text-sm text-(--text-muted) mt-1">Open a review cycle. Strengths, growth areas, and OKR goals feed the reviewer summary.</p>
                </div>
            </div>

            <!-- Form Card -->
            <div class="glass-card rounded-2xl p-6 sm:p-8 border border-(--border-color) shadow-sm">

                <div v-if="loading" class="py-12 flex flex-col items-center justify-center gap-3">
                    <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                    <span class="text-xs text-(--text-muted) font-medium">Loading form details...</span>
                </div>

                <form v-else @submit.prevent="submitForm" class="space-y-6">

                    <!-- Employee — picker for admins, locked to current user otherwise -->
                    <div>
                        <label class="form-label form-label-required">Employee</label>
                        <div v-if="isAdmin" class="input-with-icon">
                            <i class="ti ti-user input-icon"></i>
                            <select v-model="form.employee_id" required class="form-control bg-(--bg-muted) border-transparent focus:border-primary focus:bg-transparent appearance-none">
                                <option value="" disabled>Select employee...</option>
                                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.fullName }} ({{ e.employeeId }})</option>
                            </select>
                        </div>
                        <div v-else class="border border-(--border-color) rounded-xl p-4 flex items-center gap-3 bg-(--bg-muted)/50">
                            <div class="w-10 h-10 rounded-full bg-primary/10 text-primary flex items-center justify-center">
                                <i class="ti ti-user text-lg"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-(--text-heading) truncate">{{ currentEmployee?.fullName || authStore.user?.name || 'Current user' }}</p>
                                <p class="text-xxs text-(--text-muted) font-mono">{{ currentEmployee?.employeeId || authStore.user?.email || '—' }}</p>
                            </div>
                        </div>
                        <p class="form-hint">
                            <i class="ti ti-info-circle mr-1"></i>
                            <span v-if="isAdmin">Defaults to your record. Switch to open a cycle on behalf of another employee.</span>
                            <span v-else>Submitting as your linked employee record.</span>
                        </p>
                    </div>

                    <!-- Reviewer -->
                    <div>
                        <label class="form-label">Reviewer</label>
                        <div class="input-with-icon">
                            <i class="ti ti-users input-icon"></i>
                            <select v-model="form.reviewer_id" class="form-control bg-(--bg-muted) border-transparent focus:border-primary focus:bg-transparent appearance-none">
                                <option :value="''">— Self review</option>
                                <option v-for="e in employees" :key="e.id" :value="e.id" :disabled="e.id === form.employee_id">
                                    {{ e.fullName }} ({{ e.employeeId }})
                                </option>
                            </select>
                        </div>
                        <p class="form-hint">
                            <i class="ti ti-info-circle mr-1"></i>Leave blank to self-review. The reviewer can later submit ratings and growth areas.
                        </p>
                    </div>

                    <!-- Cycle + Period -->
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                        <div>
                            <label class="form-label form-label-required">Cycle</label>
                            <input v-model="form.cycle" type="text" placeholder="2026-Q2" class="form-control font-mono bg-(--bg-muted) border-transparent focus:border-primary focus:bg-transparent" required />
                        </div>
                        <div>
                            <label class="form-label form-label-required">Period start</label>
                            <div class="input-with-icon">
                                <i class="ti ti-calendar input-icon"></i>
                                <input v-model="form.period_start" type="date" class="form-control bg-(--bg-muted) border-transparent focus:border-primary focus:bg-transparent" required />
                            </div>
                        </div>
                        <div>
                            <label class="form-label form-label-required">Period end</label>
                            <div class="input-with-icon">
                                <i class="ti ti-calendar-event input-icon"></i>
                                <input v-model="form.period_end" type="date" :min="form.period_start" class="form-control bg-(--bg-muted) border-transparent focus:border-primary focus:bg-transparent" required />
                            </div>
                        </div>
                    </div>

                    <!-- Strengths -->
                    <div>
                        <label class="form-label">Strengths</label>
                        <textarea v-model="form.strengths" rows="3" placeholder="Consistent shipping, mentors juniors..." class="form-control bg-(--bg-muted) border-transparent focus:border-primary focus:bg-transparent resize-none"></textarea>
                    </div>

                    <!-- Areas for improvement -->
                    <div>
                        <label class="form-label">Areas for improvement</label>
                        <textarea v-model="form.improvements" rows="3" placeholder="Cross-team alignment, deeper specs..." class="form-control bg-(--bg-muted) border-transparent focus:border-primary focus:bg-transparent resize-none"></textarea>
                    </div>

                    <!-- Goals -->
                    <div>
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
                                <input v-model="goal.title" type="text" required placeholder="Goal title" class="form-control col-span-6" />
                                <select v-model="goal.status" class="form-control col-span-3">
                                    <option value="pending">Pending</option>
                                    <option value="in_progress">In progress</option>
                                    <option value="achieved">Achieved</option>
                                    <option value="missed">Missed</option>
                                </select>
                                <input v-model="goal.due" type="date" class="form-control col-span-2" />
                                <button type="button" class="col-span-1 text-(--color-danger) hover:text-(--color-danger) p-2"
                                    @click="removeGoal(idx)" title="Remove">
                                    <i class="ti ti-trash" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div v-if="formError" class="text-sm text-(--color-danger) bg-(--color-danger-subtle) px-4 py-3 rounded-xl border border-(--color-danger)/20">
                        <i class="ti ti-alert-circle mr-1"></i> {{ formError }}
                    </div>

                    <!-- Actions -->
                    <div class="pt-4 border-t border-(--border-color) flex justify-end gap-3">
                        <button type="button" @click="router.back()" class="btn btn-secondary px-6">Cancel</button>
                        <button type="submit" class="btn btn-primary px-8 flex items-center gap-2" :disabled="isSubmitting || !form.employee_id">
                            <i v-if="isSubmitting" class="ti ti-loader animate-spin"></i>
                            <i v-else class="ti ti-send"></i>
                            <span>{{ isSubmitting ? 'Submitting...' : 'Submit Request' }}</span>
                        </button>
                    </div>
                </form>
            </div>
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
