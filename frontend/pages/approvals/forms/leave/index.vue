<template>
    <NuxtLayout name="default">
        <div class="max-w-3xl mx-auto py-8">
            <!-- Header -->
            <div class="mb-8 flex items-center gap-4">
                <button @click="router.back()" class="w-10 h-10 rounded-full bg-(--bg-muted) flex items-center justify-center hover:bg-primary/10 hover:text-primary transition-colors">
                    <i class="ti ti-arrow-left text-xl"></i>
                </button>
                <div>
                    <h1 class="text-2xl font-bold">New Leave Request</h1>
                    <p class="text-sm text-(--text-muted) mt-1">Submit your request for annual, sick, or unpaid leave.</p>
                </div>
            </div>

            <!-- Form Card -->
            <div class="glass-card rounded-2xl p-6 sm:p-8 border border-(--border-color) shadow-sm">
                
                <div v-if="loading" class="py-12 flex flex-col items-center justify-center gap-3">
                    <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                    <span class="text-xs text-(--text-muted) font-medium">Loading form details...</span>
                </div>

                <form v-else @submit.prevent="submitForm" class="space-y-6">
                    
                    <!-- Leave Type -->
                    <div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            <label v-for="type in leaveTypes" :key="type.id" 
                                :class="[
                                    'cursor-pointer border-2 rounded-xl p-4 flex flex-col items-center gap-2 transition-all',
                                    form.leave_type_id === type.id ? 'border-primary bg-primary/5' : 'border-(--border-color) hover:border-primary/30'
                                ]"
                            >
                                <input type="radio" :value="type.id" v-model="form.leave_type_id" class="hidden" required />
                                <div :class="['w-10 h-10 rounded-full flex items-center justify-center', form.leave_type_id === type.id ? 'bg-primary/10 text-primary' : 'bg-(--bg-muted) text-(--text-muted)']">
                                    <i class="ti ti-calendar-event text-xl"></i>
                                </div>
                                <span class="font-medium text-sm text-center">{{ type.name }}</span>
                                <span class="text-xxs text-(--text-muted)">{{ type.annualAllowance }} days/yr</span>
                            </label>
                        </div>
                    </div>

                    <!-- Date Range -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                        <div>
                            <label class="form-label form-label-required">Start Date</label>
                            <div class="input-with-icon">
                                <i class="ti ti-calendar input-icon"></i>
                                <input type="date" v-model="form.start_date" class="form-control bg-(--bg-muted) border-transparent focus:border-primary focus:bg-transparent" required />
                            </div>
                        </div>
                        <div>
                            <label class="form-label form-label-required">End Date</label>
                            <div class="input-with-icon">
                                <i class="ti ti-calendar-event input-icon"></i>
                                <input type="date" v-model="form.end_date" :min="form.start_date" :required="form.leave_session === 'full_day'" :disabled="form.leave_session !== 'full_day'" class="form-control bg-(--bg-muted) border-transparent focus:border-primary focus:bg-transparent" />
                            </div>
                        </div>
                    </div>

                    <!-- Job Handover -->
                    <div>
                        <label class="form-label">Job Handover (Optional)</label>
                        <div class="input-with-icon">
                            <i class="ti ti-user input-icon"></i>
                            <select v-model="form.handover_employee_id" class="form-control bg-(--bg-muted) border-transparent focus:border-primary focus:bg-transparent appearance-none">
                                <option value="">Select a colleague to cover your tasks...</option>
                                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.fullName }} ({{ e.employeeId }})</option>
                            </select>
                        </div>
                        <p class="form-hint">
                            <i class="ti ti-info-circle mr-1"></i>Select the colleague who will handle your responsibilities while you are away.
                        </p>
                    </div>

                    <!-- Session -->
                    <div>
                        <label class="form-label form-label-required">Session</label>
                        <div class="flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1">
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

                    <!-- Reason -->
                    <div>
                        <label class="form-label form-label-required">Reason</label>
                        <textarea v-model="form.reason" rows="4" placeholder="Briefly describe the reason for your leave..." class="form-control bg-(--bg-muted) border-transparent focus:border-primary focus:bg-transparent resize-none" required></textarea>
                    </div>

                    <!-- Attachment -->
                    <div>
                        <label class="form-label">Attachment (Optional)</label>
                        
                        <div v-if="form.attachment" class="border border-(--border-color) rounded-xl p-4 flex items-center gap-4 bg-(--bg-card) shadow-sm">
                            <div class="w-14 h-14 rounded-lg overflow-hidden shrink-0 bg-(--bg-muted) flex items-center justify-center border border-(--border-color)">
                                <img v-if="attachmentPreview" :src="attachmentPreview" class="w-full h-full object-cover" />
                                <i v-else class="ti ti-file-text text-2xl text-(--text-muted)"></i>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-(--text-heading) truncate">{{ form.attachment.name }}</p>
                                <p class="text-xs text-(--text-muted)">{{ (form.attachment.size / 1024 / 1024).toFixed(2) }} MB</p>
                            </div>
                            <button type="button" @click="clearAttachment" class="w-8 h-8 rounded-full bg-danger/10 text-danger flex items-center justify-center hover:bg-danger/20 transition-colors" title="Remove file">
                                <i class="ti ti-trash"></i>
                            </button>
                        </div>

                        <div v-else class="border-2 border-dashed border-(--border-color) hover:border-primary/50 transition-colors rounded-xl p-6 flex flex-col items-center justify-center bg-(--bg-muted)/50 cursor-pointer relative group">
                            <input type="file" @change="handleFileUpload" class="absolute inset-0 w-full h-full opacity-0 cursor-pointer" accept=".pdf,.png,.jpg,.jpeg" />
                            <div class="w-12 h-12 rounded-full bg-primary/10 text-primary flex items-center justify-center mb-3 group-hover:scale-110 transition-transform">
                                <i class="ti ti-upload text-xl"></i>
                            </div>
                            <span class="text-sm font-medium mb-1">Click to upload or drag and drop</span>
                            <span class="text-xs text-(--text-muted)">PNG, JPG or PDF (max. 5MB)</span>
                        </div>
                    </div>

                    <div v-if="formError" class="text-sm text-(--color-danger) bg-(--color-danger-subtle) px-4 py-3 rounded-xl border border-(--color-danger)/20">
                        <i class="ti ti-alert-circle mr-1"></i> {{ formError }}
                    </div>

                    <!-- Actions -->
                    <div class="pt-4 border-t border-(--border-color) flex justify-end gap-3">
                        <button type="button" @click="router.back()" class="btn btn-secondary px-6">Cancel</button>
                        <button type="submit" class="btn btn-primary px-8 flex items-center gap-2" :disabled="isSubmitting || !form.leave_type_id">
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
import { ref, onMounted, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'

interface EmployeeLite { id: string; employeeId: string; fullName: string }
interface LeaveType { id: string; name: string; annualAllowance: number }
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

const router = useRouter()
const api = useApi()
const authStore = useAuthStore()

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
        
        if (authStore.hasPermission('approvals.requests.read')) {
            router.push('/approvals/requests')
        } else {
            router.push('/hrm/timeoff/leaves')
        }
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
