<template>
    <NuxtLayout name="default">
        <div class="max-w-4xl mx-auto space-y-8 pb-12">
            <!-- Hero banner -->
            <section class="relative overflow-hidden rounded-2xl border border-(--border-color) bg-(--bg-card) p-6 sm:p-8 shadow-(--shadow-sm)">
                <div class="absolute -top-20 -right-16 w-72 h-72 rounded-full blur-3xl bg-(--color-primary)/15 pointer-events-none" />
                <div class="absolute -bottom-20 -left-20 w-72 h-72 rounded-full blur-3xl bg-(--color-info)/10 pointer-events-none" />

                <div class="relative z-10 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                    <div class="flex items-start gap-4">
                        <button @click="router.back()"
                            class="w-10 h-10 mt-1 rounded-full bg-(--bg-muted) flex items-center justify-center hover:bg-(--color-primary)/10 hover:text-(--color-primary) transition-colors shrink-0">
                            <i class="ti ti-arrow-left text-xl"></i>
                        </button>
                        <div class="space-y-2 max-w-2xl">
                            <Badge variant="primary" :dot="true">eApprovals · Time Off</Badge>
                            <h1 class="text-2xl font-bold tracking-tight text-(--text-heading)">
                                New Leave Request
                            </h1>
                            <p class="text-xs text-(--text-body) leading-relaxed">
                                Submit your request for annual, sick, or unpaid leave. Once submitted, it will be sent to your reporting manager for approval.
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
                
                <!-- Leave Classification -->
                <section class="glass-card rounded-2xl p-6 border border-(--border-color) space-y-5">
                    <header>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-(--text-muted) flex items-center gap-2">
                            <i class="ti ti-category text-sm" />Leave Classification
                        </h3>
                        <p class="text-xxs text-(--text-muted) mt-1">Select the type of leave you are requesting.</p>
                    </header>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <label v-for="type in leaveTypes" :key="type.id" 
                            :class="[
                                'cursor-pointer border-2 rounded-xl p-4 flex flex-col items-center gap-2 transition-all',
                                form.leave_type_id === type.id ? 'border-(--color-primary) bg-(--color-primary)/5 shadow-sm' : 'border-(--border-color) hover:border-(--color-primary)/30'
                            ]"
                        >
                            <input type="radio" :value="type.id" v-model="form.leave_type_id" class="hidden" required />
                            <div :class="['w-10 h-10 rounded-full flex items-center justify-center', form.leave_type_id === type.id ? 'bg-(--color-primary) text-white shadow-md' : 'bg-(--bg-muted) text-(--text-muted)']">
                                <i class="ti ti-calendar-event text-xl"></i>
                            </div>
                            <span class="font-semibold text-sm text-center" :class="form.leave_type_id === type.id ? 'text-(--color-primary)' : 'text-(--text-heading)'">{{ type.name }}</span>
                            <span class="text-xxs text-(--text-muted)">{{ type.annualAllowance }} days/yr</span>
                        </label>
                    </div>
                </section>

                <!-- Date & Duration -->
                <section class="glass-card rounded-2xl p-6 border border-(--border-color) space-y-5">
                    <header>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-(--text-muted) flex items-center gap-2">
                            <i class="ti ti-calendar-time text-sm" />Date &amp; Duration
                        </h3>
                        <p class="text-xxs text-(--text-muted) mt-1">Specify when you will be away and for how long.</p>
                    </header>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="form-label form-label-required">Start Date</label>
                            <div class="input-with-icon">
                                <i class="ti ti-calendar input-icon"></i>
                                <input type="date" v-model="form.start_date" class="form-control bg-(--bg-muted) border-transparent focus:border-(--color-primary) focus:ring-1 focus:ring-(--color-primary) focus:bg-transparent" required />
                            </div>
                        </div>
                        <div>
                            <label class="form-label form-label-required">End Date</label>
                            <div class="input-with-icon">
                                <i class="ti ti-calendar-event input-icon"></i>
                                <input type="date" v-model="form.end_date" :min="form.start_date" :required="form.leave_session === 'full_day'" :disabled="form.leave_session !== 'full_day'" class="form-control bg-(--bg-muted) border-transparent focus:border-(--color-primary) focus:ring-1 focus:ring-(--color-primary) focus:bg-transparent disabled:opacity-50" />
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="form-label form-label-required">Session</label>
                        <div class="flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1 max-w-md">
                            <button v-for="opt in (['full_day', 'morning', 'afternoon'] as const)" :key="opt"
                                type="button"
                                class="flex-1 px-4 py-2 rounded text-xs uppercase tracking-widest font-bold transition-colors"
                                :class="form.leave_session === opt ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                                @click="selectSession(opt)">
                                {{ opt.replace('_', ' ') }}
                            </button>
                        </div>
                        <p class="form-hint">
                            <i class="ti ti-info-circle mr-1"></i>
                            <span v-if="form.leave_session === 'full_day'">A full day consumes 1 day of your balance per selected date.</span>
                            <span v-else>Half-day requests are locked to the start date and consume 0.5 days.</span>
                        </p>
                    </div>
                </section>

                <!-- Handover & Reason -->
                <section class="glass-card rounded-2xl p-6 border border-(--border-color) space-y-5">
                    <header>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-(--text-muted) flex items-center gap-2">
                            <i class="ti ti-user-star text-sm" />Handover &amp; Reason
                        </h3>
                        <p class="text-xxs text-(--text-muted) mt-1">Provide context for your approver and assign cover.</p>
                    </header>

                    <div>
                        <label class="form-label">Job Handover (Optional)</label>
                        <div class="input-with-icon">
                            <i class="ti ti-user input-icon"></i>
                            <select v-model="form.handover_employee_id" class="form-control bg-(--bg-muted) border-transparent focus:border-(--color-primary) focus:ring-1 focus:ring-(--color-primary) focus:bg-transparent appearance-none">
                                <option value="">Select a colleague to cover your tasks...</option>
                                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.fullName }} ({{ e.employeeId }})</option>
                            </select>
                        </div>
                        <p class="form-hint">
                            <i class="ti ti-info-circle mr-1"></i>Select the colleague who will handle your responsibilities while you are away.
                        </p>
                    </div>

                    <div>
                        <label class="form-label form-label-required">Reason</label>
                        <textarea v-model="form.reason" rows="4" placeholder="Briefly describe the reason for your leave..." class="form-control bg-(--bg-muted) border-transparent focus:border-(--color-primary) focus:ring-1 focus:ring-(--color-primary) focus:bg-transparent resize-none" required></textarea>
                    </div>
                </section>

                <!-- Attachments -->
                <section class="glass-card rounded-2xl p-6 border border-(--border-color) space-y-5">
                    <header>
                        <h3 class="text-xs font-semibold uppercase tracking-widest text-(--text-muted) flex items-center gap-2">
                            <i class="ti ti-paperclip text-sm" />Attachments
                        </h3>
                        <p class="text-xxs text-(--text-muted) mt-1">Upload any supporting documents (e.g., medical certificates).</p>
                    </header>

                    <div>
                        <div v-if="form.attachment" class="border border-(--border-color) rounded-xl p-4 flex items-center gap-4 bg-(--bg-muted)/30 shadow-sm">
                            <div class="w-14 h-14 rounded-lg overflow-hidden shrink-0 bg-(--bg-card) flex items-center justify-center border border-(--border-color)">
                                <img v-if="attachmentPreview" :src="attachmentPreview" class="w-full h-full object-cover" />
                                <i v-else class="ti ti-file-text text-2xl text-(--color-primary)"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-(--text-heading) truncate">{{ form.attachment.name }}</p>
                                <p class="text-xs text-(--text-muted)">{{ (form.attachment.size / 1024 / 1024).toFixed(2) }} MB</p>
                            </div>
                            <button type="button" @click="clearAttachment" class="w-8 h-8 rounded-full bg-danger/10 text-danger flex items-center justify-center hover:bg-danger/20 transition-colors" title="Remove file">
                                <i class="ti ti-trash text-sm"></i>
                            </button>
                        </div>

                        <div v-else class="border-2 border-dashed border-(--border-color) hover:border-(--color-primary)/50 transition-colors rounded-xl p-8 flex flex-col items-center justify-center bg-(--bg-muted)/30 cursor-pointer relative group">
                            <input type="file" @change="handleFileUpload" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" accept=".pdf,.png,.jpg,.jpeg" />
                            <div class="w-12 h-12 rounded-full bg-(--color-primary)/10 text-(--color-primary) flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                <i class="ti ti-upload text-xl"></i>
                            </div>
                            <span class="text-sm font-medium mb-1 text-(--text-heading)">Click to upload or drag and drop</span>
                            <span class="text-xs text-(--text-muted)">PNG, JPG or PDF (max. 5MB)</span>
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
                        This request is subject to approval workflow.
                    </p>
                    <div class="flex items-center gap-3 shrink-0">
                        <button type="button" @click="router.back()" class="btn btn-secondary px-6">Cancel</button>
                        <button type="submit" class="btn btn-primary px-8 flex items-center gap-2" :disabled="isSubmitting || !form.leave_type_id">
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
import { ref, onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '~/composables/useApi'

interface EmployeeLite { id: string; employeeId: string; fullName: string }
interface LeaveType { id: string; name: string; annualAllowance: number }
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

const router = useRouter()
const api = useApi()

const employees = ref<EmployeeLite[]>([])
const leaveTypes = ref<LeaveType[]>([])
const loading = ref(true)
const isSubmitting = ref(false)
const formError = ref<string | null>(null)
const attachmentPreview = ref<string | null>(null)

type LeaveSession = 'full_day' | 'morning' | 'afternoon'

const form = reactive({
    leave_type_id: '',
    start_date: '',
    end_date: '',
    leave_session: 'full_day' as LeaveSession,
    reason: '',
    handover_employee_id: '',
    attachment: null as File | null
})

const selectSession = (opt: LeaveSession) => {
    form.leave_session = opt
    if (opt !== 'full_day' && form.start_date) {
        form.end_date = form.start_date
    }
}

const handleFileUpload = (event: Event) => {
    const target = event.target as HTMLInputElement
    if (target.files && target.files.length > 0) {
        const file = target.files[0]
        
        if (attachmentPreview.value) {
            URL.revokeObjectURL(attachmentPreview.value)
        }
        
        form.attachment = file
        if (file.type.startsWith('image/')) {
            attachmentPreview.value = URL.createObjectURL(file)
        } else {
            attachmentPreview.value = null
        }
    }
    target.value = ''
}

const clearAttachment = () => {
    form.attachment = null
    if (attachmentPreview.value) {
        URL.revokeObjectURL(attachmentPreview.value)
        attachmentPreview.value = null
    }
}

const loadLookups = async () => {
    loading.value = true
    try {
        const [e, t] = await Promise.all([
            api.get<Paginated<EmployeeLite>>('/employees?limit=100'),
            api.get<Paginated<LeaveType>>('/leave-types?limit=100')
        ])
        employees.value = e.data
        leaveTypes.value = t.data
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
        // Prepare payload
        const payload = {
            leave_type_id: form.leave_type_id,
            start_date: form.start_date,
            end_date: form.leave_session === 'full_day' ? form.end_date : form.start_date,
            leave_session: form.leave_session,
            reason: form.reason || null,
            handover_employee_id: form.handover_employee_id || null
        }
        
        await api.post('/hrm/timeoff/leaves', payload)
        
        // If there's an attachment, handle it here (requires multipart/form-data upload endpoint)
        // ...
        
        router.push('/approvals/requests')
    } catch (err: any) {
        console.error('Error submitting form:', err)
        formError.value = err.data?.message || 'Failed to submit leave request.'
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
