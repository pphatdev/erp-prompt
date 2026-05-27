<template>
    <NuxtLayout name="default">
        <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
            <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
            <span class="text-xs text-(--text-muted)">Loading purchase order...</span>
        </div>

        <div v-else-if="!po" class="glass-card rounded-2xl py-20 text-center">
            <i class="ti ti-mood-empty text-4xl text-(--text-muted)" />
            <h4 class="text-sm font-semibold text-(--text-heading) mt-3">Purchase order not found</h4>
            <NuxtLink to="/inventory/purchase-orders" class="btn btn-ghost text-xs mt-4 inline-flex">
                <i class="ti ti-arrow-left" />Back to list
            </NuxtLink>
        </div>

        <div v-else class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col lg:flex-row justify-between items-start gap-3">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold font-mono">{{ po.poNumber }}</h1>
                        <Badge :variant="poStatusBadgeVariant(po.status)">{{ po.status }}</Badge>
                    </div>
                    <p class="text-xs text-(--text-muted) mt-1">
                        Created {{ formatDate(po.createdAt) }}
                        <span v-if="po.submittedAt"> · Submitted {{ formatDate(po.submittedAt) }}</span>
                        <span v-if="po.approvedAt"> · Approved {{ formatDate(po.approvedAt) }}</span>
                        <span v-if="po.receivedAt"> · Fully received {{ formatDate(po.receivedAt) }}</span>
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <NuxtLink to="/inventory/purchase-orders" class="btn btn-ghost text-xs">
                        <i class="ti ti-arrow-left" />Back
                    </NuxtLink>
                    <button v-if="canWrite && po.status === 'draft'" type="button"
                        class="btn btn-primary text-xs" :disabled="acting" @click="onSubmit">
                        <i v-if="acting" class="ti ti-loader-2 animate-spin" />
                        <i v-else class="ti ti-send" />Submit
                    </button>
                    <button v-if="canApprove && po.status === 'submitted'" type="button"
                        class="btn btn-success text-xs text-white" :disabled="acting" @click="onApprove">
                        <i v-if="acting" class="ti ti-loader-2 animate-spin" />
                        <i v-else class="ti ti-circle-check" />Approve
                    </button>
                    <button v-if="canWrite && canCancel" type="button"
                        class="btn btn-danger text-xs" @click="cancelOpen = true">
                        <i class="ti ti-x" />Cancel PO
                    </button>
                </div>
            </header>

            <!-- Summary -->
            <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="glass-card rounded-xl p-4 space-y-1">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Supplier</p>
                    <p class="text-sm font-semibold text-(--text-heading)">{{ po.supplier?.name || '—' }}</p>
                    <p v-if="po.supplier?.code" class="text-xxs text-(--text-muted) font-mono">{{ po.supplier.code }}</p>
                </div>
                <div class="glass-card rounded-xl p-4 space-y-1">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Receive Into</p>
                    <p class="text-sm font-semibold text-(--text-heading)">{{ po.warehouse?.name || '—' }}</p>
                    <p v-if="po.warehouse?.code" class="text-xxs text-(--text-muted) font-mono">{{ po.warehouse.code }}</p>
                </div>
                <div class="glass-card rounded-xl p-4 space-y-1">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Expected</p>
                    <p class="text-sm font-semibold text-(--text-heading)">{{ po.expectedAt ? formatDate(po.expectedAt) : '—' }}</p>
                    <p class="text-xxs text-(--text-muted)">Ordered {{ po.orderDate ? formatDate(po.orderDate) : '—' }}</p>
                </div>
            </section>

            <p v-if="po.notes" class="glass-card rounded-xl p-4 text-xs text-(--text-body) whitespace-pre-line">
                {{ po.notes }}
            </p>

            <!-- Cancel reason (visible if cancelled) -->
            <div v-if="po.status === 'cancelled' && po.cancelReason"
                class="glass-card rounded-xl p-4 border-l-4 border-(--color-danger)">
                <p class="text-xxs uppercase font-bold tracking-wider text-(--color-danger)">Cancellation Reason</p>
                <p class="text-xs text-(--text-body) mt-1 whitespace-pre-line">{{ po.cancelReason }}</p>
            </div>

            <!-- Line items / receive grid -->
            <section class="glass-card rounded-2xl overflow-hidden">
                <header class="flex items-center justify-between p-4 border-b border-(--border-color)">
                    <h3 class="text-xs font-bold text-(--text-heading) uppercase tracking-wider">Line Items</h3>
                    <div v-if="canReceive" class="flex items-center gap-2">
                        <button type="button" class="btn btn-ghost text-xxs" @click="fillRemaining">
                            <i class="ti ti-arrow-down-circle" />Receive all outstanding
                        </button>
                        <button type="button" class="btn btn-primary text-xs" :disabled="!hasReceiveQty || acting"
                            @click="onReceive">
                            <i v-if="acting" class="ti ti-loader-2 animate-spin" />
                            <i v-else class="ti ti-package-import" />Post Receipt
                        </button>
                    </div>
                </header>
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-(--bg-muted) text-xxs uppercase tracking-widest font-bold text-(--text-muted)">
                            <tr>
                                <th class="text-left px-4 py-3">Product</th>
                                <th class="text-right px-2 py-3">Ordered</th>
                                <th class="text-right px-2 py-3">Received</th>
                                <th class="text-right px-2 py-3">Outstanding</th>
                                <th class="text-right px-2 py-3">Unit Cost</th>
                                <th class="text-right px-2 py-3">Line Total</th>
                                <th v-if="canReceive" class="text-right px-2 py-3 w-32">Receive Now</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in po.items" :key="item.id"
                                class="border-t border-(--border-color)/60">
                                <td class="px-4 py-3">
                                    <p class="text-sm font-semibold text-(--text-heading) leading-tight">{{ item.productName }}</p>
                                    <p v-if="item.variantSku" class="text-xxs text-(--text-muted) font-mono mt-0.5">{{ item.variantSku }}</p>
                                    <p v-if="item.notes" class="text-xxs text-(--text-muted) mt-0.5">{{ item.notes }}</p>
                                </td>
                                <td class="px-2 py-3 text-right font-mono">{{ formatQty(item.orderedQty) }}</td>
                                <td class="px-2 py-3 text-right font-mono">{{ formatQty(item.receivedQty) }}</td>
                                <td class="px-2 py-3 text-right font-mono">
                                    <span :class="item.outstandingQty > 0 ? 'text-(--color-warning) font-semibold' : 'text-(--text-muted)'">
                                        {{ formatQty(item.outstandingQty) }}
                                    </span>
                                </td>
                                <td class="px-2 py-3 text-right font-mono">{{ formatCurrency(item.unitCost) }}</td>
                                <td class="px-2 py-3 text-right font-mono font-semibold">{{ formatCurrency(item.lineTotal) }}</td>
                                <td v-if="canReceive" class="px-2 py-3 text-right">
                                    <input v-model.number="receiveDraft[item.id]" type="number"
                                        :min="0" :max="item.outstandingQty" step="0.01"
                                        :disabled="item.outstandingQty === 0"
                                        class="form-control text-xs text-right" />
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-(--border-color) bg-(--bg-muted)/40">
                                <td colspan="5" class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-(--text-muted)">
                                    Subtotal
                                </td>
                                <td class="px-2 py-3 text-right font-mono font-bold text-(--text-heading) text-sm">
                                    {{ formatCurrency(po.subtotal) }}
                                </td>
                                <td v-if="canReceive" />
                            </tr>
                            <tr class="bg-(--bg-muted)/40">
                                <td colspan="5" class="px-4 py-3 text-right text-xs font-bold uppercase tracking-wider text-(--text-heading)">
                                    Total
                                </td>
                                <td class="px-2 py-3 text-right font-mono font-bold text-(--color-primary) text-base">
                                    {{ formatCurrency(po.totalAmount) }}
                                </td>
                                <td v-if="canReceive" />
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <!-- Receive notes (only when receivable) -->
            <section v-if="canReceive" class="glass-card rounded-xl p-4 space-y-2">
                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Receipt Notes (optional)</label>
                <textarea v-model="receiveNotes" rows="2" maxlength="500" placeholder="e.g. delivery #1234, condition notes..."
                    class="form-control text-xs resize-none" />
            </section>
        </div>

        <!-- Cancel Modal -->
        <div v-if="cancelOpen && po" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Cancel {{ po.poNumber }}</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="cancelOpen = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <p class="text-xs text-(--text-body)">Cancelling halts further receiving. Already-received quantities are not reversed.</p>
                    <div class="space-y-1">
                        <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Reason (optional)</label>
                        <textarea v-model="cancelReason" rows="2" maxlength="500" class="form-control text-xs resize-none" />
                    </div>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="cancelOpen = false">Keep</button>
                    <button type="button" class="btn btn-danger text-xs" :disabled="cancelling" @click="onCancel">
                        <i v-if="cancelling" class="ti ti-loader-2 animate-spin" />
                        Cancel PO
                    </button>
                </footer>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { useInventory, poStatusBadgeVariant } from '~/composables/useInventory'
import { useToast } from '~/composables/useToast'
import { useAuthStore } from '~/stores/auth'
import { useBreadcrumbOverride } from '~/composables/useBreadcrumbOverride'
import type { PurchaseOrder } from '~/types/inventory'

definePageMeta({ breadcrumb: 'Purchase Order' })

const route = useRoute()
const inventory = useInventory()
const toast = useToast()
const authStore = useAuthStore()
const crumb = useBreadcrumbOverride()

const canWrite = computed(() => authStore.hasPermission('inventory.procurement.write'))
const canApprove = computed(() => authStore.hasPermission('inventory.procurement.approve'))

const loading = ref(true)
const acting = ref(false)
const cancelling = ref(false)
const cancelOpen = ref(false)
const cancelReason = ref('')

const po = ref<PurchaseOrder | null>(null)
const receiveDraft = reactive<Record<string, number>>({})
const receiveNotes = ref('')

const canReceive = computed(() =>
    canWrite.value && !!po.value && ['approved', 'receiving'].includes(po.value.status)
)

const canCancel = computed(() =>
    !!po.value && !['cancelled', 'received', 'receiving'].includes(po.value.status)
)

const hasReceiveQty = computed(() =>
    Object.values(receiveDraft).some(q => Number(q) > 0)
)

const fillRemaining = () => {
    if (!po.value) return
    po.value.items.forEach(item => {
        receiveDraft[item.id] = Number(item.outstandingQty)
    })
}

const refreshReceiveDraft = () => {
    Object.keys(receiveDraft).forEach(k => { delete receiveDraft[k] })
    po.value?.items.forEach(item => { receiveDraft[item.id] = 0 })
}

const formatDate = (d: string) => new Date(d).toLocaleString('en-US', {
    month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit'
})
const formatCurrency = (v: number) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(v) || 0)
const formatQty = (v: number) =>
    new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 }).format(Number(v) || 0)

const load = async () => {
    loading.value = true
    try {
        const res = await inventory.purchaseOrders.show(route.params.id as string)
        po.value = res.data
        crumb.set(res.data.poNumber)
        refreshReceiveDraft()
    } catch (err: any) {
        toast.error('Failed to load PO', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const onSubmit = async () => {
    if (!po.value) return
    acting.value = true
    try {
        const res = await inventory.purchaseOrders.submit(po.value.id)
        po.value = res.data
        toast.success('PO submitted', po.value.poNumber)
    } catch (err: any) {
        toast.error('Submit failed', err?.data?.message)
    } finally {
        acting.value = false
    }
}

const onApprove = async () => {
    if (!po.value) return
    acting.value = true
    try {
        const res = await inventory.purchaseOrders.approve(po.value.id)
        po.value = res.data
        toast.success('PO approved', po.value.poNumber)
    } catch (err: any) {
        toast.error('Approve failed', err?.data?.message)
    } finally {
        acting.value = false
    }
}

const onReceive = async () => {
    if (!po.value || !hasReceiveQty.value) return
    acting.value = true
    try {
        const items = Object.entries(receiveDraft)
            .filter(([, qty]) => Number(qty) > 0)
            .map(([id, qty]) => ({ id, qty: Number(qty) }))

        const res = await inventory.purchaseOrders.receive(po.value.id, {
            items,
            notes: receiveNotes.value || null,
        })
        po.value = res.data
        receiveNotes.value = ''
        refreshReceiveDraft()
        toast.success('Receipt posted', po.value.poNumber)
    } catch (err: any) {
        toast.error('Receive failed', err?.data?.message)
    } finally {
        acting.value = false
    }
}

const onCancel = async () => {
    if (!po.value) return
    cancelling.value = true
    try {
        const res = await inventory.purchaseOrders.cancel(po.value.id, cancelReason.value || undefined)
        po.value = res.data
        toast.success('PO cancelled', po.value.poNumber)
        cancelOpen.value = false
    } catch (err: any) {
        toast.error('Cancel failed', err?.data?.message)
    } finally {
        cancelling.value = false
    }
}

onMounted(load)
onBeforeUnmount(() => crumb.clear())
</script>
