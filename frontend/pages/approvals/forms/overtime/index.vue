<template>
    <NuxtLayout name="default">
        <div class="max-w-4xl mx-auto space-y-8 pb-12">
            <!-- Hero banner -->
            <section class="relative overflow-hidden rounded-2xl border border-(--border-color) bg-(--bg-card) p-6 sm:p-8 shadow-(--shadow-sm)">
                <div class="absolute -top-20 -right-16 w-72 h-72 rounded-full blur-3xl bg-(--color-warning)/15 pointer-events-none" />
                <div class="absolute -bottom-20 -left-20 w-72 h-72 rounded-full blur-3xl bg-(--color-info)/10 pointer-events-none" />

                <div class="relative z-10 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                    <div class="flex items-start gap-4">
                        <button @click="router.back()"
                            class="w-10 h-10 mt-1 rounded-full bg-(--bg-muted) flex items-center justify-center hover:bg-(--color-primary)/10 hover:text-(--color-primary) transition-colors shrink-0">
                            <i class="ti ti-arrow-left text-xl"></i>
                        </button>
                        <div class="space-y-2 max-w-2xl">
                            <Badge variant="warning" :dot="true">eApprovals · Time Tracking</Badge>
                            <h1 class="text-2xl font-bold tracking-tight text-(--text-heading)">
                                New Overtime Request
                            </h1>
                            <p class="text-xs text-(--text-body) leading-relaxed">
                                Log extra hours worked. Approved hours feed the next payroll period.
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

                <!-- Requestor Details -->
                <section class="glass-card rounded-2xl p-6 border border-(--border-color) space-y-5">
                    <header>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-(--text-muted) flex items-center gap-2">
                            <i class="ti ti-user-circle text-sm" />Requestor Details
                        </h3>
                        <p class="text-xxs text-(--text-muted) mt-1">Specify who this overtime applies to.</p>
                    </header>
                    <!-- Employee — picker for admins, locked to current user otherwise -->
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
                            <span v-if="isAdmin">Defaults to your record. Switch to submit on behalf of another employee.</span>
                            <span v-else>Submitting as your linked employee record.</span>
                        </p>
                    </div>
                </section>

                <!-- Time & Rate -->
                <section class="glass-card rounded-2xl p-6 border border-(--border-color) space-y-5">
                    <header>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-(--text-muted) flex items-center gap-2">
                            <i class="ti ti-clock-hour-4 text-sm" />Time &amp; Rate
                        </h3>
                        <p class="text-xxs text-(--text-muted) mt-1">When did the overtime occur and at what rate?</p>
                    </header>

                    <!-- Date + Hours -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="form-label form-label-required">Date</label>
                            <div class="input-with-icon">
                                <i class="ti ti-calendar input-icon"></i>
                                <input type="date" v-model="form.date" class="form-control bg-(--bg-muted) border-transparent focus:border-(--color-primary) focus:ring-1 focus:ring-(--color-primary) focus:bg-transparent" required />
                            </div>
                        </div>
                        <div>
                            <label class="form-label form-label-required">Hours</label>
                            <div class="input-with-icon">
                                <i class="ti ti-clock input-icon"></i>
                                <input type="number" v-model.number="form.hours" step="0.25" min="0.25" max="16" class="form-control font-mono bg-(--bg-muted) border-transparent focus:border-(--color-primary) focus:ring-1 focus:ring-(--color-primary) focus:bg-transparent" required />
                            </div>
                            <p class="form-hint">
                                <i class="ti ti-info-circle mr-1"></i>Increments of 0.25 hours, up to 16 hours per entry.
                            </p>
                        </div>
                    </div>

                    <!-- Rate multiplier -->
                    <div>
                        <label class="form-label form-label-required">Rate multiplier</label>
                        <div class="flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1 max-w-lg">
                            <button v-for="opt in multiplierOptions" :key="opt.value"
                                type="button"
                                class="flex-1 px-4 py-2 rounded text-xs uppercase tracking-widest font-bold transition-colors"
                                :class="form.rate_multiplier === opt.value ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                                @click="form.rate_multiplier = opt.value">
                                {{ opt.label }}
                            </button>
                        </div>
                        <p class="form-hint">
                            <i class="ti ti-info-circle mr-1"></i>Weekend dates are auto-promoted to 2.0x server-side.
                        </p>
                    </div>
                </section>

                <!-- Justification -->
                <section class="glass-card rounded-2xl p-6 border border-(--border-color) space-y-5">
                    <header>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-(--text-muted) flex items-center gap-2">
                            <i class="ti ti-align-left text-sm" />Justification
                        </h3>
                        <p class="text-xxs text-(--text-muted) mt-1">Provide the business reason for the extra hours.</p>
                    </header>

                    <!-- Reason -->
                    <div>
                        <label class="form-label">Reason</label>
                        <textarea v-model="form.reason" rows="4" placeholder="Production support, release window, etc." class="form-control bg-(--bg-muted) border-transparent focus:border-(--color-primary) focus:ring-1 focus:ring-(--color-primary) focus:bg-transparent resize-none"></textarea>
                    </div>
                </section>

                <div v-if="formError" class="text-sm text-(--color-danger) bg-(--color-danger-subtle) px-4 py-3 rounded-xl border border-(--color-danger)/20">
                    <i class="ti ti-alert-circle mr-1"></i> {{ formError }}
                </div>

                <!-- Sticky-feeling action footer -->
                <div class="appointment-footer">
                    <p class="text-xxs text-(--text-muted) sm:flex-1">
                        <i class="ti ti-shield-check mr-1 text-(--color-success)" />
                        This request is subject to approval workflow.
                    </p>
                    <div class="flex items-center gap-3 shrink-0">
                        <button type="button" @click="router.back()" class="btn btn-secondary px-6">Cancel</button>
                        <button type="submit" class="btn btn-primary px-8 flex items-center gap-2" :disabled="isSubmitting">
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
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

const router = useRouter()
const api = useApi()
const authStore = useAuthStore()

const isAdmin = computed(() => authStore.hasPermission('hrm.overtime.read'))

const employees = ref<EmployeeLite[]>([])
const currentEmployee = ref<EmployeeLite | null>(null)
const loading = ref(true)
const isSubmitting = ref(false)
const formError = ref<string | null>(null)

const form = reactive({
    employee_id: '',
    date: '',
    hours: 1.0 as number,
    rate_multiplier: 1.5 as number,
    reason: ''
})

const multiplierOptions = [
    { value: 1.5, label: '1.5x weekday' },
    { value: 2.0, label: '2.0x weekend' },
    { value: 3.0, label: '3.0x holiday' }
]

const loadLookups = async () => {
    loading.value = true
    try {
        const meRequest = api.get<{ data: EmployeeLite }>('/employees/me').catch(() => null)
        const listRequest = isAdmin.value
            ? api.get<Paginated<EmployeeLite>>('/employees?limit=200')
            : Promise.resolve(null)

        const [me, list] = await Promise.all([meRequest, listRequest])

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
            date: form.date,
            hours: form.hours,
            rate_multiplier: form.rate_multiplier,
            reason: form.reason || null
        }
        if (form.employee_id) {
            payload.employee_id = form.employee_id
        }

        await api.post('/overtime-requests', payload)

        router.push('/approvals/requests')
    } catch (err: any) {
        console.error('Error submitting form:', err)
        formError.value = err.data?.message || 'Failed to submit overtime request.'
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
