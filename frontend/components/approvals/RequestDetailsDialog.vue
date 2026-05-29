<template>
    <Teleport to="body">
        <transition name="dialog-fade">
            <div v-if="isVisible && request" class="dialog-overlay" role="dialog" aria-modal="true" @click.self="isVisible = false">
                <div class="dialog-card">
                    <!-- Header -->
                    <header class="dialog-header">
                        <h2 class="dialog-title">Request Details</h2>
                        <button type="button" class="dialog-close" aria-label="Close" @click="isVisible = false">
                            <i class="ti ti-x" />
                        </button>
                    </header>

                    <!-- Content -->
                    <div class="dialog-content space-y-6 scrollbar-custom">
                        <!-- Header Info -->
                        <div class="p-4 bg-(--bg-muted) rounded-xl flex flex-col sm:flex-row justify-between gap-4">
                            <div>
                                <div class="text-xs text-(--text-muted) uppercase tracking-wider mb-1">Request ID</div>
                                <div class="font-mono text-sm font-semibold text-primary">
                                    {{ request.id.split('-')[0].toUpperCase() }}
                                </div>
                            </div>
                            <div>
                                <div class="text-xs text-(--text-muted) uppercase tracking-wider mb-1">Type</div>
                                <div class="font-semibold text-sm">{{ requestableTypeLabel }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-(--text-muted) uppercase tracking-wider mb-1">Requester</div>
                                <div class="flex items-center gap-2">
                                    <div class="w-5 h-5 rounded-full bg-primary/20 flex items-center justify-center text-[10px] font-bold text-primary">
                                        {{ request.requester?.name?.charAt(0) || '?' }}
                                    </div>
                                    <span class="text-sm font-medium">{{ request.requester?.name || 'Unknown' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Dynamic Payload Data -->
                        <div class="border border-(--border-color) rounded-xl overflow-hidden">
                            <div class="bg-(--bg-muted) px-4 py-2 border-b border-(--border-color)">
                                <h3 class="text-sm font-semibold">Submitted Data</h3>
                            </div>
                            <div class="p-4">
                                <!-- Leave Request Template -->
                                <div v-if="request.requestable && isLeave" class="grid grid-cols-2 gap-y-4 gap-x-6 text-sm">
                                    <div class="flex flex-col">
                                        <span class="text-xs text-(--text-muted)">Employee</span>
                                        <span class="font-medium text-(--text-heading)">
                                            {{ request.requestable.employee?.fullName }}
                                        </span>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-xs text-(--text-muted)">Leave Type</span>
                                        <span class="font-medium text-(--text-heading)">
                                            {{ request.requestable.leaveType?.name || 'Standard Leave' }}
                                        </span>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-xs text-(--text-muted)">Start Date</span>
                                        <span class="font-semibold text-(--text-heading)">
                                            {{ request.requestable.startDate }}
                                        </span>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-xs text-(--text-muted)">End Date</span>
                                        <span class="font-semibold text-(--text-heading)">
                                            {{ request.requestable.endDate }}
                                        </span>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-xs text-(--text-muted)">Duration</span>
                                        <span class="font-medium text-(--text-heading)">
                                            {{ request.requestable.days }} day(s)
                                        </span>
                                    </div>
                                    <div class="flex flex-col">
                                        <span class="text-xs text-(--text-muted)">Session</span>
                                        <span class="font-medium text-(--text-heading) capitalize">
                                            {{ request.requestable.leaveSession?.replace('_', ' ') }}
                                        </span>
                                    </div>
                                    <div class="flex flex-col col-span-2 pt-2 border-t border-(--border-color)">
                                        <span class="text-xs text-(--text-muted)">Reason</span>
                                        <span class="font-medium text-(--text-body) italic mt-0.5">
                                            "{{ request.requestable.reason || 'No reason provided.' }}"
                                        </span>
                                    </div>
                                </div>

                                <!-- Purchase Order Template -->
                                <div v-else-if="request.requestable && isPurchaseOrder" class="space-y-4 text-sm">
                                    <div class="grid grid-cols-2 gap-y-4 gap-x-6">
                                        <div class="flex flex-col">
                                            <span class="text-xs text-(--text-muted)">Supplier</span>
                                            <span class="font-medium text-(--text-heading)">
                                                {{ request.requestable.supplier?.name || 'Unknown' }}
                                            </span>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-xs text-(--text-muted)">Warehouse</span>
                                            <span class="font-medium text-(--text-heading)">
                                                {{ request.requestable.warehouse?.name || 'Unknown' }}
                                            </span>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-xs text-(--text-muted)">Status</span>
                                            <span class="font-medium text-(--text-heading) capitalize">
                                                {{ request.requestable.status }}
                                            </span>
                                        </div>
                                        <div class="flex flex-col">
                                            <span class="text-xs text-(--text-muted)">Total Ordered Items</span>
                                            <span class="font-medium text-(--text-heading)">
                                                {{ request.requestable.items?.length || 0 }}
                                            </span>
                                        </div>
                                    </div>
                                    
                                    <!-- Items Table -->
                                    <div v-if="request.requestable.items && request.requestable.items.length > 0" class="border border-(--border-color) rounded-lg overflow-hidden mt-3">
                                        <table class="w-full text-left text-xs">
                                            <thead class="bg-(--bg-muted) text-(--text-muted) border-b border-(--border-color)">
                                                <tr>
                                                    <th class="px-3 py-2 font-semibold">Product SKU / Name</th>
                                                    <th class="px-3 py-2 font-semibold text-right">Quantity</th>
                                                    <th class="px-3 py-2 font-semibold text-right">Unit Cost</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-(--border-color)">
                                                <tr v-for="item in request.requestable.items" :key="item.id">
                                                    <td class="px-3 py-2">
                                                        <div class="font-semibold text-(--text-heading)">{{ item.variantSku || 'Product SKU' }}</div>
                                                        <div class="text-(--text-muted) text-xxs">{{ item.productName }}</div>
                                                    </td>
                                                    <td class="px-3 py-2 text-right text-(--text-heading)">{{ item.orderedQty }}</td>
                                                    <td class="px-3 py-2 text-right text-(--text-heading)">${{ item.unitCost }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </div>

                                <!-- Generic Fallback -->
                                <div v-else-if="request.requestable" class="grid grid-cols-2 gap-y-4 gap-x-6 text-sm">
                                    <div v-for="(val, key) in displayPayload" :key="key" class="flex flex-col">
                                        <span class="text-xs text-(--text-muted) capitalize">{{ String(key).replace(/_/g, ' ') }}</span>
                                        <span class="font-medium text-(--text-heading)">
                                            {{ typeof val === 'object' ? JSON.stringify(val) : val }}
                                        </span>
                                    </div>
                                </div>

                                <div v-else class="text-sm text-(--text-muted) italic">
                                    Detailed data payload not available.
                                </div>
                            </div>
                        </div>

                        <!-- Timeline -->
                        <div>
                            <h3 class="text-sm font-semibold mb-3">Approval Timeline</h3>
                            <ApprovalTimeline :history="request.history || []" :current-level="currentLevel"
                                :requester="request.requester" :submitted-at="request.created_at" />
                        </div>

                        <!-- Action Area (if approver mode) -->
                        <div v-if="mode === 'approver'" class="mt-6 border-t border-(--border-color) pt-4">
                            <label class="block text-xs font-semibold text-(--text-muted) mb-2 uppercase tracking-wider">
                                Comment (Required for Reject/Return)
                            </label>
                            <textarea v-model="comment" class="form-control w-full" rows="3" placeholder="Enter reason for decision..."></textarea>
                        </div>
                    </div>

                    <!-- Footer -->
                    <footer class="dialog-footer">
                        <button class="btn btn-secondary text-xs" @click="isVisible = false">Close</button>
                        <div v-if="mode === 'approver'" class="flex gap-2">
                            <button class="btn btn-secondary text-info border-info text-xs" @click="submitAction('sent_back')">
                                <i class="ti ti-arrow-back-up mr-1"></i> Send Back
                            </button>
                            <button class="btn btn-secondary text-danger border-danger text-xs" @click="submitAction('rejected')">
                                <i class="ti ti-x mr-1"></i> Reject
                            </button>
                            <button class="btn btn-primary text-xs" @click="submitAction('approved')">
                                <i class="ti ti-check mr-1"></i> Approve
                            </button>
                        </div>
                    </footer>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, computed, watch, onBeforeUnmount } from 'vue'
import type { ApprovalRequest } from '~/composables/useApprovals'
import ApprovalTimeline from './ApprovalTimeline.vue'

const props = defineProps<{
    modelValue: boolean
    request: ApprovalRequest | null
    mode?: 'readonly' | 'approver'
}>()

const emit = defineEmits(['update:modelValue', 'action'])

const isVisible = computed({
    get: () => props.modelValue,
    set: (val) => emit('update:modelValue', val)
})

const comment = ref('')

watch(isVisible, (isOpen) => {
    if (typeof document === 'undefined') return
    if (isOpen) {
        document.body.style.overflow = 'hidden'
        comment.value = ''
    } else {
        document.body.style.overflow = ''
    }
})

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = ''
    }
})

const isLeave = computed(() => props.request?.requestable_type?.endsWith('Leave'))
const isPurchaseOrder = computed(() => props.request?.requestable_type?.endsWith('PurchaseOrder'))

const requestableTypeLabel = computed(() => {
    if (!props.request) return ''
    const parts = props.request.requestable_type.split('\\')
    return parts[parts.length - 1].replace(/([A-Z])/g, ' $1').trim()
})

// Extract the current level if pending
const currentLevel = computed(() => {
    if (!props.request || props.request.status !== 'pending') return null
    if (!props.request.workflow || !props.request.workflow.levels) return null
    return props.request.workflow.levels.find(l => l.id === props.request?.current_level_id) || null
})

// Clean up the requestable object to only show displayable data
const displayPayload = computed(() => {
    if (!props.request || !props.request.requestable) return {}
    const obj = { ...props.request.requestable }
    // Remove internal IDs and timestamps
    delete obj.id
    delete obj.tenant_id
    delete obj.created_at
    delete obj.updated_at
    delete obj.deleted_at
    return obj
})

const submitAction = (action: 'approved' | 'rejected' | 'sent_back') => {
    emit('action', {
        requestId: props.request!.id,
        action,
        comment: comment.value
    })
}
</script>

<style scoped>
.dialog-overlay {
    position: fixed;
    inset: 0;
    z-index: 100;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1.5rem;
    background: rgba(15, 23, 42, 0.4);
    backdrop-filter: blur(8px) saturate(120%);
    -webkit-backdrop-filter: blur(8px) saturate(120%);
}

.dialog-card {
    position: relative;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    display: flex;
    flex-direction: column;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 1.25rem;
    box-shadow: var(--shadow-xl), 0 0 0 1px rgb(255 255 255 / 0.02) inset;
    overflow: hidden;
}

[data-bs-theme='dark'] .dialog-card {
    background: color-mix(in srgb, var(--bg-card) 92%, transparent);
    backdrop-filter: blur(16px) saturate(140%);
    -webkit-backdrop-filter: blur(16px) saturate(140%);
}

.dialog-header {
    padding: 1.25rem 1.5rem;
    border-bottom: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.dialog-title {
    font-size: 1.125rem;
    font-weight: 600;
    color: var(--text-heading);
}

.dialog-close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 0.5rem;
    color: var(--text-muted);
    background: transparent;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}

.dialog-close:hover {
    background: var(--bg-muted);
    color: var(--text-heading);
}

.dialog-content {
    padding: 1.5rem;
    overflow-y: auto;
    flex: 1;
}

.dialog-footer {
    padding: 1.25rem 1.5rem;
    border-t: 1px solid var(--border-color);
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: var(--bg-card-footer, transparent);
}

/* Scrollbar styling */
.scrollbar-custom::-webkit-scrollbar {
    width: 6px;
}
.scrollbar-custom::-webkit-scrollbar-track {
    background: transparent;
}
.scrollbar-custom::-webkit-scrollbar-thumb {
    background: var(--border-color);
    border-radius: 99px;
}
.scrollbar-custom::-webkit-scrollbar-thumb:hover {
    background: var(--text-muted);
}

/* Animations */
.dialog-fade-enter-active,
.dialog-fade-leave-active {
    transition: opacity 0.2s ease;
}

.dialog-fade-enter-active .dialog-card,
.dialog-fade-leave-active .dialog-card {
    transition: transform 0.25s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s ease;
}

.dialog-fade-enter-from {
    opacity: 0;
}

.dialog-fade-leave-to {
    opacity: 0;
}

.dialog-fade-enter-from .dialog-card {
    transform: scale(0.95) translateY(10px);
    opacity: 0;
}

.dialog-fade-leave-to .dialog-card {
    transform: scale(0.97) translateY(5px);
    opacity: 0;
}
</style>
