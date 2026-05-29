<template>
    <NuxtLayout name="default">
        <div class="max-w-3xl mx-auto py-8">
            <!-- Header -->
            <div class="mb-8 flex items-center gap-4">
                <button @click="router.back()" class="w-10 h-10 rounded-full bg-(--bg-muted) flex items-center justify-center hover:bg-primary/10 hover:text-primary transition-colors">
                    <i class="ti ti-arrow-left text-xl"></i>
                </button>
                <div>
                    <h1 class="text-2xl font-bold">New Overtime Request</h1>
                    <p class="text-sm text-(--text-muted) mt-1">Log extra hours worked. Approved hours feed the next payroll period.</p>
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
                            <span v-if="isAdmin">Defaults to your record. Switch to submit on behalf of another employee.</span>
                            <span v-else>Submitting as your linked employee record.</span>
                        </p>
                    </div>

                    <!-- Date + Hours -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="form-label form-label-required">Date</label>
                            <div class="input-with-icon">
                                <i class="ti ti-calendar input-icon"></i>
                                <input type="date" v-model="form.date" class="form-control bg-(--bg-muted) border-transparent focus:border-primary focus:bg-transparent" required />
                            </div>
                        </div>
                        <div>
                            <label class="form-label form-label-required">Hours</label>
                            <div class="input-with-icon">
                                <i class="ti ti-clock input-icon"></i>
                                <input type="number" v-model.number="form.hours" step="0.25" min="0.25" max="16" class="form-control font-mono bg-(--bg-muted) border-transparent focus:border-primary focus:bg-transparent" required />
                            </div>
                            <p class="form-hint">
                                <i class="ti ti-info-circle mr-1"></i>Increments of 0.25 hours, up to 16 hours per entry.
                            </p>
                        </div>
                    </div>

                    <!-- Rate multiplier -->
                    <div>
                        <label class="form-label form-label-required">Rate multiplier</label>
                        <div class="flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1">
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

                    <!-- Reason -->
                    <div>
                        <label class="form-label">Reason</label>
                        <textarea v-model="form.reason" rows="4" placeholder="Production support, release window, etc." class="form-control bg-(--bg-muted) border-transparent focus:border-primary focus:bg-transparent resize-none"></textarea>
                    </div>

                    <div v-if="formError" class="text-sm text-(--color-danger) bg-(--color-danger-subtle) px-4 py-3 rounded-xl border border-(--color-danger)/20">
                        <i class="ti ti-alert-circle mr-1"></i> {{ formError }}
                    </div>

                    <!-- Actions -->
                    <div class="pt-4 border-t border-(--border-color) flex justify-end gap-3">
                        <button type="button" @click="router.back()" class="btn btn-secondary px-6">Cancel</button>
                        <button type="submit" class="btn btn-primary px-8 flex items-center gap-2" :disabled="isSubmitting">
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
