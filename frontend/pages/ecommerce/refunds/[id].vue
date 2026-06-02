<template>
    <NuxtLayout name="default">
        <div v-if="loading" class="py-24 flex justify-center">
            <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
        </div>

        <div v-else-if="refund" class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <NuxtLink to="/ecommerce/refunds" class="text-xs text-(--text-muted) hover:text-(--text-heading)">
                        <i class="ti ti-arrow-left" /> All refunds
                    </NuxtLink>
                    <h1 class="text-xl font-semibold mt-2">{{ refund.refundNumber }}</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        {{ refund.isPartial ? 'Partial' : 'Full' }} refund - requested {{ formatDate(refund.requestedAt) }}
                    </p>
                </div>
                <span :class="`badge-${ec.statusBadgeVariant(refund.status)}`">{{ refund.status }}</span>
            </header>

            <section v-if="refund.status === 'requested'" class="flex flex-wrap gap-2">
                <button class="btn btn-success text-xs" :disabled="busy" @click="approveModalOpen = true">
                    <i class="ti ti-check" /> Approve
                </button>
                <button class="btn btn-soft-danger text-xs" :disabled="busy" @click="rejectModalOpen = true">
                    <i class="ti ti-x" /> Reject
                </button>
            </section>

            <div v-if="error" class="text-xs px-3 py-2 rounded bg-(--color-danger)/10 text-(--color-danger)">{{ error }}</div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2 glass-card rounded-2xl p-5 space-y-3">
                    <h3 class="text-sm font-semibold text-(--text-heading)">Lines</h3>
                    <div v-for="item in refund.items" :key="item.id" class="flex justify-between text-xs py-2 border-b border-(--border-color) last:border-0">
                        <div>
                            <div class="text-(--text-heading)">qty {{ item.quantity }}</div>
                            <div class="text-(--text-muted) text-xxs">{{ item.restock ? 'will restock' : 'no restock' }}</div>
                        </div>
                        <div class="font-mono">{{ formatMoney(item.lineTotal) }}</div>
                    </div>
                </div>

                <aside class="glass-card rounded-2xl p-5 space-y-3 text-xs">
                    <h3 class="text-sm font-semibold text-(--text-heading)">Summary</h3>
                    <div class="flex justify-between"><span class="text-(--text-muted)">Amount</span><span class="font-mono text-(--color-primary)">{{ formatMoney(refund.amount) }}</span></div>
                    <div v-if="refund.reason"><div class="text-(--text-muted)">Reason</div><div class="mt-1">{{ refund.reason }}</div></div>
                    <div v-if="refund.rejectionReason"><div class="text-(--text-muted)">Rejection reason</div><div class="mt-1 text-(--color-danger)">{{ refund.rejectionReason }}</div></div>
                    <div v-if="refund.providerRefundId" class="pt-2 border-t border-(--border-color)">
                        <div class="text-(--text-muted)">Gateway refund id</div>
                        <div class="font-mono">{{ refund.providerRefundId }}</div>
                    </div>
                    <div v-if="refund.creditNoteId" class="pt-2 border-t border-(--border-color)">
                        <div class="text-(--text-muted)">Credit note posted</div>
                        <div class="font-mono">{{ refund.creditNoteId }}</div>
                    </div>
                    <NuxtLink :to="`/ecommerce/orders/${refund.orderId}`" class="btn btn-soft-secondary text-xs w-full">View order</NuxtLink>
                </aside>
            </div>

            <Teleport to="body">
                <div v-if="approveModalOpen" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="approveModalOpen = false">
                    <div class="glass-card rounded-2xl p-6 max-w-sm w-full space-y-4">
                        <h3 class="text-sm font-semibold text-(--text-heading)">Approve refund</h3>
                        <p class="text-xs text-(--text-muted)">Approval posts the reversing journal (or Credit Note when enabled), restocks any items flagged, and tells the payment gateway to refund.</p>
                        <div>
                            <label class="text-xs text-(--text-muted)">Gateway refund id (optional)</label>
                            <input v-model="providerRefundId" class="form-control text-sm mt-1" placeholder="e.g. re_1AbcXyz" />
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button class="btn btn-soft-secondary text-xs" @click="approveModalOpen = false">Cancel</button>
                            <button class="btn btn-success text-xs" :disabled="busy" @click="approve">
                                Approve refund
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>

            <Teleport to="body">
                <div v-if="rejectModalOpen" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="rejectModalOpen = false">
                    <div class="glass-card rounded-2xl p-6 max-w-sm w-full space-y-4">
                        <h3 class="text-sm font-semibold text-(--text-heading)">Reject refund</h3>
                        <div>
                            <label class="text-xs text-(--text-muted)">Reason (required)</label>
                            <textarea v-model="rejectReason" rows="3" required class="form-control text-sm mt-1" />
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button class="btn btn-soft-secondary text-xs" @click="rejectModalOpen = false">Keep open</button>
                            <button class="btn btn-danger text-xs" :disabled="busy || !rejectReason" @click="reject">
                                Reject refund
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>
        </div>

        <div v-else class="glass-card rounded-2xl py-20 text-center">
            <p class="text-sm text-(--text-muted)">Refund not found.</p>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useEcommerce, type EcomRefundAdmin } from '~/composables/useEcommerce'
import { useRoute } from 'vue-router'

const ec = useEcommerce()
const route = useRoute()

const refund = ref<EcomRefundAdmin | null>(null)
const loading = ref(true)
const busy = ref(false)
const error = ref('')
const approveModalOpen = ref(false)
const rejectModalOpen = ref(false)
const providerRefundId = ref('')
const rejectReason = ref('')

const formatMoney = (n: number | null | undefined) =>
    (Number.isFinite(Number(n)) ? Number(n) : 0).toLocaleString(undefined, { style: 'currency', currency: 'USD' })
const formatDate = (iso: string | null) => iso ? new Date(iso).toLocaleDateString() : '-'

async function load() {
    loading.value = true
    try {
        const res = await ec.refunds.show(String(route.params.id))
        refund.value = res.data
    } finally {
        loading.value = false
    }
}

async function approve() {
    if (!refund.value) return
    busy.value = true
    error.value = ''
    try {
        const res = await ec.refunds.approve(refund.value.id, providerRefundId.value || undefined)
        refund.value = res.data
        approveModalOpen.value = false
    } catch (e: any) {
        error.value = e?.data?.message || 'Approve failed.'
    } finally {
        busy.value = false
    }
}

async function reject() {
    if (!refund.value || !rejectReason.value) return
    busy.value = true
    error.value = ''
    try {
        const res = await ec.refunds.reject(refund.value.id, rejectReason.value)
        refund.value = res.data
        rejectModalOpen.value = false
    } catch (e: any) {
        error.value = e?.data?.message || 'Reject failed.'
    } finally {
        busy.value = false
    }
}

onMounted(load)
</script>
