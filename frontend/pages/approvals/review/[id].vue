<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Loading -->
            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading request review details...</span>
            </div>

            <!-- Error -->
            <div v-else-if="error" class="glass-card rounded-2xl py-16 text-center max-w-lg mx-auto">
                <i class="ti ti-alert-circle text-4xl text-danger/80" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">Failed to load request</h4>
                <p class="text-xs text-(--text-muted) mt-1">{{ error }}</p>
                <NuxtLink to="/approvals/review" class="btn btn-secondary text-xs mt-6">
                    Back to Review Portal
                </NuxtLink>
            </div>

            <!-- Main Content Grid -->
            <div v-else-if="request" class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                
                <!-- Left Column: Details -->
                <div class="lg:col-span-8 space-y-6">
                    
                    <!-- Header Card -->
                    <div class="glass-card rounded-2xl p-6 relative overflow-hidden">
                        <!-- Subtle Background Glow -->
                        <div class="absolute -right-20 -top-20 w-48 h-48 rounded-full bg-warning/5 blur-3xl pointer-events-none" />
                        
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <div>
                                <div class="flex items-center gap-3 flex-wrap">
                                    <span class="font-mono text-xs font-bold text-primary uppercase tracking-wide px-2.5 py-1 bg-primary/10 rounded-lg">
                                        Request ID: {{ request.id.split('-')[0].toUpperCase() }}
                                    </span>
                                    <Badge :variant="statusVariant(request.status)" :dot="true">{{ request.status.replace('_', ' ') }}</Badge>
                                </div>
                                <h1 class="text-xl font-bold text-(--text-heading) mt-3 flex items-center gap-2">
                                    <i class="ti ti-checklist text-primary" />
                                    Review: {{ requestableTypeLabel }}
                                </h1>
                            </div>
                            
                            <!-- Back Button -->
                            <NuxtLink to="/approvals/review" class="btn btn-secondary text-xs inline-flex items-center gap-1.5 border border-(--border-color)">
                                <i class="ti ti-arrow-left" />
                                Back to Portal
                            </NuxtLink>
                        </div>
                    </div>

                    <!-- Requester profile block -->
                    <div class="glass-card rounded-2xl p-5">
                        <h3 class="text-xs font-bold uppercase tracking-wider text-(--text-muted) mb-4">Requester Details</h3>
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-bold text-sm shrink-0">
                                {{ request.requester?.name?.charAt(0) || '?' }}
                            </div>
                            <div class="min-w-0">
                                <span class="text-sm font-semibold text-(--text-heading) block">
                                    {{ request.requester?.name || 'Unknown User' }}
                                </span>
                                <span class="text-xs text-(--text-muted) block mt-0.5">{{ request.requester?.email || '—' }}</span>
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
                                        {{ request.requestable.startDate || '—' }}
                                    </span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-xs text-(--text-muted) font-medium">End Date</span>
                                    <span class="font-mono font-semibold text-(--text-heading) mt-1">
                                        {{ request.requestable.endDate || '—' }}
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
                                    <span class="text-xs text-(--text-muted) capitalize">{{ String(key).replace(/_/g, ' ') }}</span>
                                    <span class="font-semibold text-(--text-heading) mt-1">
                                        {{ typeof val === 'object' ? JSON.stringify(val) : val }}
                                    </span>
                                </div>
                            </div>

                            <div v-else class="text-sm text-(--text-muted) italic text-center py-6">
                                Detailed data payload not available for this type.
                            </div>
                        </div>
                    </div>

                    <!-- Action Panel for Active Approver -->
                    <div v-if="request.status === 'pending'" class="glass-card rounded-2xl p-6 border-2 border-(--color-primary)/20 relative overflow-hidden shadow-(--shadow-md)">
                        <!-- Accent Wash -->
                        <div class="absolute -right-20 -bottom-20 w-48 h-48 rounded-full bg-primary/5 blur-3xl pointer-events-none" />
                        
                        <h3 class="text-sm font-bold uppercase tracking-wider text-(--text-muted) mb-4 flex items-center gap-2">
                            <i class="ti ti-shield-check text-primary" />
                            Review Authorization
                        </h3>
                        
                        <div class="space-y-4">
                            <div>
                                <label class="block text-xs font-semibold text-(--text-heading) mb-2 uppercase tracking-wide">
                                    Decision Comment <span class="text-(--text-muted) font-normal">(Required for Reject / Return)</span>
                                </label>
                                <textarea v-model="comment" class="form-control w-full min-h-[90px] text-sm p-3 rounded-xl border border-(--border-color)" 
                                    placeholder="Provide comments regarding your decision..."></textarea>
                            </div>
                            
                            <!-- Action Control Buttons -->
                            <div class="flex flex-col sm:flex-row justify-end items-stretch sm:items-center gap-3 pt-2">
                                <button type="button" class="btn btn-secondary text-info border-info text-xs inline-flex items-center justify-center gap-1.5"
                                    :disabled="isSubmitting" @click="submitAction('sent_back')">
                                    <i class="ti ti-arrow-back-up text-sm" />
                                    Send Back
                                </button>
                                <button type="button" class="btn btn-secondary text-danger border-danger text-xs inline-flex items-center justify-center gap-1.5"
                                    :disabled="isSubmitting" @click="submitAction('rejected')">
                                    <i class="ti ti-x text-sm" />
                                    Reject Request
                                </button>
                                <button type="button" class="btn btn-primary text-xs inline-flex items-center justify-center gap-1.5 shadow-[0_4px_14px_rgba(var(--color-primary-rgb),0.3)]"
                                    :disabled="isSubmitting" @click="submitAction('approved')">
                                    <i class="ti ti-check text-sm" />
                                    Approve Request
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Already Processed Notice -->
                    <div v-else class="glass-card rounded-2xl p-5 border border-dashed border-(--border-color) bg-(--bg-muted)/40 flex items-center gap-3">
                        <i class="ti ti-lock text-lg text-(--text-muted)" />
                        <div class="text-xs text-(--text-muted)">
                            This approval request was already finalized as <span class="font-bold uppercase tracking-wider text-(--text-heading)">{{ request.status }}</span> and is locked from further actions.
                        </div>
                    </div>

                </div>

                <!-- Right Column: Timeline -->
                <div class="lg:col-span-4 space-y-6">
                    <div class="glass-card rounded-2xl p-6">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-(--text-muted) mb-5">Approval Progress</h2>
                        <ApprovalTimeline :history="request.history || []" :current-level="currentLevel" />
                    </div>
                </div>

            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApprovals, type ApprovalRequest } from '~/composables/useApprovals'
import { useBreadcrumbOverride } from '~/composables/useBreadcrumbOverride'
import { useToast } from '~/composables/useToast'
import ApprovalTimeline from '~/components/approvals/ApprovalTimeline.vue'
import Badge from '~/components/Badge.vue'

const route = useRoute()
const router = useRouter()
const { getRequest, processAction } = useApprovals()
const breadcrumb = useBreadcrumbOverride()
const toast = useToast()

const loading = ref(true)
const isSubmitting = ref(false)
const error = ref<string | null>(null)
const request = ref<ApprovalRequest | null>(null)
const comment = ref('')

const isLeave = computed(() => request.value?.requestable_type?.endsWith('Leave'))
const isPurchaseOrder = computed(() => request.value?.requestable_type?.endsWith('PurchaseOrder'))

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
    return obj
})

const statusVariant = (s: string): 'success' | 'warning' | 'danger' | 'info' => {
    if (s === 'approved') return 'success'
    if (s === 'pending') return 'warning'
    if (s === 'rejected') return 'danger'
    return 'info'
}

const submitAction = async (action: 'approved' | 'rejected' | 'sent_back') => {
    if ((action === 'rejected' || action === 'sent_back') && !comment.value.trim()) {
        toast.error('Validation Error', 'A comment is required for this action.', { duration: 3000 })
        return
    }

    isSubmitting.value = true
    try {
        await processAction(request.value!.id, action, comment.value)
        toast.success('Success', `Request has been ${action} successfully.`, { duration: 3000 })
        router.push('/approvals/review')
    } catch (e: any) {
        console.error(e)
        toast.error('Error', e.response?.data?.message || 'Failed to process approval request.', { duration: 5000 })
    } finally {
        isSubmitting.value = false
    }
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
            breadcrumb.set(`Review: ${requestableTypeLabel.value}`)
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
