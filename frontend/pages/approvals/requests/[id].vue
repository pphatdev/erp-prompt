<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Loading -->
            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading request details...</span>
            </div>

            <!-- Error -->
            <div v-else-if="error" class="glass-card rounded-2xl py-16 text-center max-w-lg mx-auto">
                <i class="ti ti-alert-circle text-4xl text-danger/80" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">Failed to load request</h4>
                <p class="text-xs text-(--text-muted) mt-1">{{ error }}</p>
                <NuxtLink to="/approvals/requests" class="btn btn-secondary text-xs mt-6">
                    Back to My Requests
                </NuxtLink>
            </div>

            <!-- Main Content Grid -->
            <div v-else-if="request" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                <!-- Left Column: Details -->
                <div class="lg:col-span-8 space-y-6">
                    
                    <!-- Header Card -->
                    <div class="glass-card rounded-2xl p-6 relative overflow-hidden">
                        <!-- Subtle Background Glow -->
                        <div class="absolute -right-20 -top-20 w-48 h-48 rounded-full bg-primary/5 blur-3xl pointer-events-none" />
                        
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <div>
                                <div class="flex items-center gap-3 flex-wrap">
                                    <span class="font-mono text-xs font-bold text-primary uppercase tracking-wide px-2.5 py-1 bg-primary/10 rounded-lg">
                                        Request ID: {{ request.id.split('-')[0].toUpperCase() }}
                                    </span>
                                    <Badge :variant="statusVariant(request.status)" :dot="true">{{ request.status.replace('_', ' ') }}</Badge>
                                </div>
                                <h1 class="text-xl font-bold text-(--text-heading) mt-3 flex items-center gap-2">
                                    <i class="ti ti-file-text text-(--color-primary)" />
                                    {{ requestableTypeLabel }}
                                </h1>
                            </div>
                            
                            <div class="flex items-center gap-2">
                                <!-- Review CTA — surfaced to anyone with approvals.actions.execute when
                                     the request is still pending. The backend re-checks that the caller
                                     is the current-level approver before letting them act. -->
                                <NuxtLink v-if="canReview && request.status === 'pending'"
                                    :to="`/approvals/review/${request.id}`"
                                    class="btn btn-primary text-xs inline-flex items-center gap-1.5">
                                    <i class="ti ti-shield-check" />
                                    Review this request
                                </NuxtLink>
                                <!-- Back Button -->
                                <NuxtLink to="/approvals/requests" class="btn btn-secondary text-xs inline-flex items-center gap-1.5 border border-(--border-color)">
                                    <i class="ti ti-arrow-left" />
                                    Back to List
                                </NuxtLink>
                            </div>
                        </div>
                    </div>

                    <!-- Requester details block -->
                    <div class="glass-card rounded-2xl p-5">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-(--text-muted) mb-4">Requester Profile</h3>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-(--color-primary)/10 text-(--color-primary) flex items-center justify-center font-bold text-sm shrink-0">
                                {{ request.requester?.name?.charAt(0) || '?' }}
                            </div>
                            <div class="min-w-0">
                                <span class="text-sm font-semibold text-(--text-heading) block">
                                    {{ request.requester?.name || 'Unknown User' }}
                                </span>
                                <span class="text-xs text-(--text-muted) block mt-0.5">{{ request.requester?.email || 'No email registered' }}</span>
                            </div>
                        </div>
                    </div>

                    <!-- Submitted Data -->
                    <div class="glass-card rounded-2xl overflow-hidden border border-(--border-color)">
                        <div class="bg-(--bg-muted) px-5 py-3 border-b border-(--border-color) flex justify-between items-center">
                            <h3 class="text-sm font-semibold text-(--text-heading)">Submitted Payload Details</h3>
                            <i class="ti ti-database text-(--text-muted)" />
                        </div>
                        <div class="p-6">
                            <!-- Leave Request Template -->
                            <div v-if="request.requestable && isLeave" class="grid grid-cols-1 md:grid-cols-2 gap-y-6 gap-x-8 text-sm">
                                <div class="flex flex-col">
                                    <span class="text-xs text-(--text-muted) font-medium">Employee Name</span>
                                    <span class="font-semibold text-(--text-heading) mt-1">
                                        {{ request.requestable.employee?.fullName || '—' }}
                                    </span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-xs text-(--text-muted) font-medium">Leave Category</span>
                                    <span class="font-semibold text-(--text-heading) mt-1">
                                        {{ request.requestable.leaveType?.name || 'Standard Leave' }}
                                    </span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-xs text-(--text-muted) font-medium">Start Date</span>
                                    <span class="font-mono font-semibold text-(--text-heading) mt-1">
                                        {{ formatDate(request.requestable.startDate) }}
                                    </span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-xs text-(--text-muted) font-medium">End Date</span>
                                    <span class="font-mono font-semibold text-(--text-heading) mt-1">
                                        {{ formatDate(request.requestable.endDate) }}
                                    </span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-xs text-(--text-muted) font-medium">Leave Duration</span>
                                    <span class="font-semibold text-(--text-heading) mt-1">
                                        {{ request.requestable.days }} day(s)
                                    </span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-xs text-(--text-muted) font-medium">Leave Session</span>
                                    <span class="font-semibold text-(--text-heading) mt-1 capitalize">
                                        {{ request.requestable.leaveSession?.replace('_', ' ') || '—' }}
                                    </span>
                                </div>
                                <div class="flex flex-col md:col-span-2 pt-4 border-t border-(--border-color)">
                                    <span class="text-xs text-(--text-muted) font-medium">Reason for Request</span>
                                    <span class="font-medium text-(--text-body) italic mt-1.5 bg-(--bg-muted) p-4 rounded-xl border border-(--border-color)">
                                        "{{ request.requestable.reason || 'No reason provided.' }}"
                                    </span>
                                </div>
                            </div>

                            <!-- Purchase Order Template -->
                            <div v-else-if="request.requestable && isPurchaseOrder" class="space-y-6 text-sm">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-y-6 gap-x-8">
                                    <div class="flex flex-col">
                                        <span class="text-xs text-(--text-muted) font-medium">Supplier</span>
                                        <span class="font-semibold text-(--text-heading) mt-1">
                                            {{ request.requestable.supplier?.name || 'Unknown' }}
                                        </span>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-xs text-(--text-muted) font-medium">Warehouse</span>
                                        <span class="font-semibold text-(--text-heading) mt-1">
                                            {{ request.requestable.warehouse?.name || 'Unknown' }}
                                        </span>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-xs text-(--text-muted) font-medium">PO Status</span>
                                        <span class="font-semibold text-(--text-heading) mt-1 capitalize">
                                            {{ request.requestable.status }}
                                        </span>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-xs text-(--text-muted) font-medium">Total Ordered Items</span>
                                        <span class="font-semibold text-(--text-heading) mt-1">
                                            {{ request.requestable.items?.length || 0 }} items
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Items Table -->
                                <div v-if="request.requestable.items && request.requestable.items.length > 0" class="border border-(--border-color) rounded-xl overflow-hidden mt-4 shadow-sm">
                                    <table class="w-full text-left text-xs">
                                        <thead class="bg-(--bg-muted) text-(--text-muted) border-b border-(--border-color)">
                                            <tr>
                                                <th class="px-4 py-3 font-semibold">Product SKU / Name</th>
                                                <th class="px-4 py-3 font-semibold text-right">Quantity</th>
                                                <th class="px-4 py-3 font-semibold text-right">Unit Cost</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-(--border-color)">
                                            <tr v-for="item in request.requestable.items" :key="item.id" class="hover:bg-(--bg-muted)/30 transition-colors">
                                                <td class="px-4 py-3">
                                                    <div class="font-semibold text-(--text-heading)">{{ item.variantSku || 'Product SKU' }}</div>
                                                    <div class="text-(--text-muted) text-xxs mt-0.5">{{ item.productName }}</div>
                                                </td>
                                                <td class="px-4 py-3 text-right text-(--text-heading) font-semibold">{{ item.orderedQty }}</td>
                                                <td class="px-4 py-3 text-right text-(--text-heading) font-semibold">${{ item.unitCost }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <!-- Generic Fallback -->
                            <div v-else-if="request.requestable" class="grid grid-cols-1 md:grid-cols-2 gap-y-6 gap-x-8 text-sm">
                                <div v-for="(val, key) in displayPayload" :key="key" class="flex flex-col">
                                    <span class="text-xs text-(--text-muted) capitalize">{{ displayKey(String(key)) }}</span>
                                    <span class="font-semibold text-(--text-heading) mt-1">
                                        {{ displayValue(String(key), val) }}
                                    </span>
                                </div>
                            </div>

                            <div v-else class="text-sm text-(--text-muted) italic text-center py-6">
                                Detailed data payload not available for this type.
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Right Column: Timeline -->
                <div class="lg:col-span-4 space-y-6">
                    <div class="glass-card rounded-2xl p-6">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-(--text-muted) mb-5">Approval Progress</h2>
                        <ApprovalTimeline :history="request.history || []" :current-level="currentLevel"
                            :requester="request.requester" :submitted-at="request.created_at" />
                    </div>
                </div>

            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute } from 'vue-router'
import { useApprovals, type ApprovalRequest } from '~/composables/useApprovals'
import { useBreadcrumbOverride } from '~/composables/useBreadcrumbOverride'
import { useDateFormat } from '~/composables/useDateFormat'
import { useAuthStore } from '~/stores/auth'
import ApprovalTimeline from '~/components/approvals/ApprovalTimeline.vue'
import Badge from '~/components/Badge.vue'

const route = useRoute()
const { getRequest } = useApprovals()
const breadcrumb = useBreadcrumbOverride()
const { formatDateTime, formatDate } = useDateFormat()
const authStore = useAuthStore()

const canReview = computed(() => authStore.hasPermission('approvals.actions.execute'))

const loading = ref(true)
const error = ref<string | null>(null)
const request = ref<ApprovalRequest | null>(null)

const isLeave = computed(() => request.value?.requestable_type?.endsWith('Leave'))
const isPurchaseOrder = computed(() => request.value?.requestable_type?.endsWith('PurchaseOrder'))

/**
 * Per-key label overrides. Keys not listed fall back to the original
 * camelCase/underscore transform applied in the template.
 */
const PAYLOAD_LABELS: Record<string, string> = {
    applicationId:   'Application Id',
    candidateCode:   'Candidate Code',
    employeeId:      'Employee Id',
    submittedBy:     'Submitted By',
    firstName:       'First Name',
    lastName:        'Last Name',
    fullName:        'Full Name',
    email:           'Email',
    phone:           'Phone',
    departmentId:    'Department',
    positionId:      'Position',
    managerId:       'Manager',
    startDate:       'Start Date',
    baseSalary:      'Base Salary',
    employmentType:  'Employment Type',
    notes:           'Notes',
    status:          'Status',
    processedAt:     'Processed At',
    createdAt:       'Created At',
    updatedAt:       'Updated At',
}

const displayKey = (key: string): string =>
    PAYLOAD_LABELS[key] ?? key.replace(/_/g, ' ')

/**
 * Per-key overrides for the generic payload renderer. Resolves UUIDs to the
 * project's standard codes (`PREFIX-...`) and dates to the project's
 * datetime format. Everything else falls through to the raw value.
 */
const displayValue = (key: string, val: any): string => {
    if (val == null) return '—'
    const req: any = request.value?.requestable
    // Never surface the raw UUID for these keys — fall back to em-dash if the
    // resolved identifier isn't loaded yet.
    if (key === 'applicationId') {
        return req?.candidateCode || req?.application?.candidateCode || '—'
    }
    if (key === 'submittedBy') {
        return request.value?.requester?.name || '—'
    }
    if (key === 'employeeId') {
        return req?.employee?.employeeId || '—'
    }
    if (key === 'reviewerId') {
        return req?.reviewer?.employeeId || '—'
    }
    if (key === 'departmentId') {
        return req?.department?.name || '—'
    }
    if (key === 'positionId') {
        return req?.position?.title || '—'
    }
    if (key === 'managerId') {
        return req?.manager?.fullName || '—'
    }
    // Project standard: any *At key is a datetime (e.g. createdAt, updatedAt,
    // processedAt, submittedAt); any *Date key is a date-only (e.g. startDate,
    // endDate, periodStart, periodEnd, hiredAt).
    if (/(At|_at)$/.test(key)) {
        return formatDateTime(val)
    }
    if (/(Date|_date)$/.test(key) || key === 'periodStart' || key === 'periodEnd' || key === 'hiredAt') {
        return formatDate(val)
    }
    if (typeof val === 'object') return JSON.stringify(val)
    return String(val)
}

const requestableTypeLabel = computed(() => {
    if (!request.value) return ''
    const parts = request.value.requestable_type.split('\\')
    return parts[parts.length - 1].replace(/([A-Z])/g, ' $1').trim()
})

const currentLevel = computed(() => {
    if (!request.value || request.value.status !== 'pending') return null
    if (!request.value.workflow || !request.value.workflow.levels) return null
    return request.value.workflow.levels.find(l => l.id === request.value?.current_level_id) || null
})

const displayPayload = computed(() => {
    if (!request.value || !request.value.requestable) return {}
    const obj = { ...request.value.requestable }
    delete obj.id
    delete obj.tenant_id
    delete obj.created_at
    delete obj.updated_at
    delete obj.deleted_at
    // Eager-loaded relation objects — their resolved values already render
    // under the corresponding *Id row via displayValue(), so suppress the raw
    // JSON dumps here.
    delete obj.department
    delete obj.position
    delete obj.manager
    delete obj.employee
    delete obj.reviewer
    delete obj.application
    delete obj.requester
    delete obj.vacancy
    delete obj.referrer
    delete obj.leaveType
    return obj
})

const statusVariant = (s: string): 'success' | 'warning' | 'danger' | 'info' => {
    if (s === 'approved') return 'success'
    if (s === 'pending') return 'warning'
    if (s === 'rejected') return 'danger'
    return 'info'
}

onMounted(async () => {
    const id = route.params.id as string
    if (!id) {
        error.value = 'Request ID is missing.'
        loading.value = false
        return
    }

    try {
        const res = await getRequest(id)
        request.value = res.data
        if (request.value) {
            breadcrumb.set(`${requestableTypeLabel.value} Details`)
        }
    } catch (e: any) {
        console.error(e)
        error.value = e.response?.data?.message || 'Failed to fetch request details.'
    } finally {
        loading.value = false
    }
})

onBeforeUnmount(() => {
    breadcrumb.clear()
})
</script>
