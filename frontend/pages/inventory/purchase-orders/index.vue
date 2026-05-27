<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Purchase Orders</h1>
                    <p class="text-xs text-(--text-muted) mt-1">P2P procurement lifecycle — draft → submit → approve → receive (GRN).</p>
                </div>
                <NuxtLink v-if="canWrite" to="/inventory/purchase-orders/create" class="btn btn-primary text-xs">
                    <i class="ti ti-plus" />New Purchase Order
                </NuxtLink>
            </header>

            <!-- Metrics -->
            <section class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Total</p>
                    <p class="text-xl font-semibold text-(--text-heading) mt-1">{{ list.length }}</p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Drafts</p>
                    <p class="text-xl font-semibold text-(--color-info) mt-1">{{ countOf('draft') }}</p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Awaiting Approval</p>
                    <p class="text-xl font-semibold text-(--color-warning) mt-1">{{ countOf('submitted') }}</p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Receiving</p>
                    <p class="text-xl font-semibold text-(--color-primary) mt-1">{{ countOf('approved') + countOf('receiving') }}</p>
                </div>
                <div class="glass-card rounded-xl p-4 col-span-2 md:col-span-1">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Open Spend</p>
                    <p class="text-xl font-semibold text-(--color-success) mt-1">{{ formatCurrency(openSpend) }}</p>
                </div>
            </section>

            <!-- Filters -->
            <section class="glass-card rounded-xl p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div class="relative w-full md:w-80 md:shrink-0">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                    <input v-model="search" type="search" placeholder="Search PO number..." class="form-control pl-9 text-xs" />
                </div>
                <div class="flex items-center justify-between gap-2">
                    <div class="relative flex-1 md:flex-initial">
                        <i class="ti ti-activity absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
                        <select v-model="filterStatus" class="form-control pl-9 text-xs appearance-none md:w-auto" @change="reload">
                            <option value="">All statuses</option>
                            <option v-for="s in statusOptions" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>
                    <div class="relative flex-1 md:flex-initial">
                        <i class="ti ti-truck-delivery absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
                        <select v-model="filterSupplier" class="form-control pl-9 text-xs appearance-none md:w-auto" @change="reload">
                            <option value="">All suppliers</option>
                            <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                        </select>
                    </div>
                    <div class="relative flex-1 md:flex-initial">
                        <i class="ti ti-building-warehouse absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
                        <select v-model="filterWarehouse" class="form-control pl-9 text-xs appearance-none md:w-auto" @change="reload">
                            <option value="">All warehouses</option>
                            <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.name }}</option>
                        </select>
                    </div>
                </div>
            </section>

            <!-- Loading -->
            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading purchase orders...</span>
            </div>

            <!-- Empty -->
            <div v-else-if="filteredList.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-shopping-bag text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No purchase orders</h4>
                <p class="text-xs text-(--text-muted) mt-1">Start by creating a new PO against an active supplier.</p>
            </div>

            <!-- Table -->
            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-(--bg-muted) text-xxs uppercase tracking-widest font-bold text-(--text-muted)">
                            <tr>
                                <th class="text-left px-4 py-3">PO #</th>
                                <th class="text-left px-2 py-3">Supplier</th>
                                <th class="text-left px-2 py-3 hidden md:table-cell">Warehouse</th>
                                <th class="text-left px-2 py-3 hidden lg:table-cell">Ordered</th>
                                <th class="text-left px-2 py-3 hidden lg:table-cell">Expected</th>
                                <th class="text-right px-2 py-3">Total</th>
                                <th class="text-left px-2 py-3">Status</th>
                                <th class="text-right px-4 py-3 w-44">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="po in filteredList" :key="po.id"
                                class="border-t border-(--border-color)/60 hover:bg-(--bg-muted)/40 transition-colors">
                                <td class="px-4 py-3">
                                    <NuxtLink :to="`/inventory/purchase-orders/${po.id}`"
                                        class="font-mono font-semibold text-(--text-heading) hover:text-(--color-primary)">
                                        {{ po.poNumber }}
                                    </NuxtLink>
                                </td>
                                <td class="px-2 py-3">
                                    <span v-if="po.supplier">{{ po.supplier.name }}</span>
                                    <span v-else class="text-(--text-muted)">—</span>
                                </td>
                                <td class="px-2 py-3 hidden md:table-cell">
                                    <span v-if="po.warehouse">{{ po.warehouse.name }}</span>
                                    <span v-else class="text-(--text-muted)">—</span>
                                </td>
                                <td class="px-2 py-3 hidden lg:table-cell">
                                    <span v-if="po.orderDate">{{ formatDate(po.orderDate) }}</span>
                                    <span v-else class="text-(--text-muted)">—</span>
                                </td>
                                <td class="px-2 py-3 hidden lg:table-cell">
                                    <span v-if="po.expectedAt">{{ formatDate(po.expectedAt) }}</span>
                                    <span v-else class="text-(--text-muted)">—</span>
                                </td>
                                <td class="px-2 py-3 text-right font-mono font-semibold">
                                    {{ formatCurrency(po.totalAmount) }}
                                </td>
                                <td class="px-2 py-3">
                                    <Badge :variant="poStatusBadgeVariant(po.status)">{{ po.status }}</Badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <NuxtLink :to="`/inventory/purchase-orders/${po.id}`"
                                            class="action-btn" title="View">
                                            <i class="ti ti-eye" />
                                        </NuxtLink>
                                        <button v-if="canWrite && po.status === 'draft'" type="button"
                                            class="btn btn-ghost text-xxs py-0.5 px-2 text-(--color-info) border border-(--color-info)/30"
                                            title="Submit for approval" :disabled="actingId === po.id"
                                            @click="onSubmit(po)">
                                            <i class="ti ti-send" />
                                        </button>
                                        <button v-if="canApprove && po.status === 'submitted'" type="button"
                                            class="btn btn-ghost text-xxs py-0.5 px-2 text-(--color-success) border border-(--color-success)/30"
                                            title="Approve" :disabled="actingId === po.id"
                                            @click="onApprove(po)">
                                            <i class="ti ti-circle-check" />
                                        </button>
                                        <button v-if="canWrite && canCancel(po)" type="button"
                                            class="action-btn action-btn-danger" title="Cancel"
                                            @click="openCancel(po)">
                                            <i class="ti ti-x" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Cancel Modal -->
        <div v-if="cancelTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Cancel {{ cancelTarget.poNumber }}</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="cancelTarget = null">
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
                    <button type="button" class="btn btn-ghost text-xs" @click="cancelTarget = null">Keep</button>
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
import { computed, onMounted, ref } from 'vue'
import { useInventory, poStatusBadgeVariant } from '~/composables/useInventory'
import { useToast } from '~/composables/useToast'
import { useAuthStore } from '~/stores/auth'
import type { PurchaseOrder, PurchaseOrderStatus, Supplier, Warehouse } from '~/types/inventory'

definePageMeta({ breadcrumb: 'Purchase Orders' })

const inventory = useInventory()
const toast = useToast()
const authStore = useAuthStore()

const canWrite = computed(() => authStore.hasPermission('inventory.procurement.write'))
const canApprove = computed(() => authStore.hasPermission('inventory.procurement.approve'))

const loading = ref(false)
const actingId = ref<string | null>(null)
const cancelling = ref(false)

const list = ref<PurchaseOrder[]>([])
const suppliers = ref<Supplier[]>([])
const warehouses = ref<Warehouse[]>([])

const statusOptions: PurchaseOrderStatus[] = [
    'draft', 'submitted', 'approved', 'receiving', 'received', 'cancelled'
]

const search = ref('')
const filterStatus = ref<string>('')
const filterSupplier = ref<string>('')
const filterWarehouse = ref<string>('')

const countOf = (s: PurchaseOrderStatus) => list.value.filter(p => p.status === s).length
const openSpend = computed(() =>
    list.value
        .filter(p => p.status !== 'cancelled' && p.status !== 'received')
        .reduce((sum, p) => sum + Number(p.totalAmount || 0), 0)
)

const filteredList = computed(() => list.value.filter(p => {
    const q = search.value.trim().toLowerCase()
    return !q || p.poNumber.toLowerCase().includes(q)
}))

const canCancel = (po: PurchaseOrder) =>
    !['cancelled', 'received', 'receiving'].includes(po.status)

const reload = async () => {
    loading.value = true
    try {
        const res = await inventory.purchaseOrders.list({
            limit: 100,
            status: filterStatus.value || undefined,
            supplier_id: filterSupplier.value || undefined,
            warehouse_id: filterWarehouse.value || undefined,
        })
        list.value = res.data
    } catch (err: any) {
        toast.error('Failed to load purchase orders', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const onSubmit = async (po: PurchaseOrder) => {
    actingId.value = po.id
    try {
        const res = await inventory.purchaseOrders.submit(po.id)
        const idx = list.value.findIndex(p => p.id === po.id)
        if (idx !== -1) list.value[idx] = res.data
        toast.success('PO submitted', po.poNumber)
    } catch (err: any) {
        toast.error('Submit failed', err?.data?.message)
    } finally {
        actingId.value = null
    }
}

const onApprove = async (po: PurchaseOrder) => {
    actingId.value = po.id
    try {
        const res = await inventory.purchaseOrders.approve(po.id)
        const idx = list.value.findIndex(p => p.id === po.id)
        if (idx !== -1) list.value[idx] = res.data
        toast.success('PO approved', po.poNumber)
    } catch (err: any) {
        toast.error('Approve failed', err?.data?.message)
    } finally {
        actingId.value = null
    }
}

const cancelTarget = ref<PurchaseOrder | null>(null)
const cancelReason = ref('')
const openCancel = (po: PurchaseOrder) => {
    cancelTarget.value = po
    cancelReason.value = ''
}

const onCancel = async () => {
    if (!cancelTarget.value) return
    cancelling.value = true
    try {
        const res = await inventory.purchaseOrders.cancel(cancelTarget.value.id, cancelReason.value || undefined)
        const idx = list.value.findIndex(p => p.id === cancelTarget.value!.id)
        if (idx !== -1) list.value[idx] = res.data
        toast.success('PO cancelled', cancelTarget.value.poNumber)
        cancelTarget.value = null
    } catch (err: any) {
        toast.error('Cancel failed', err?.data?.message)
    } finally {
        cancelling.value = false
    }
}

const formatDate = (d: string) => new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
const formatCurrency = (v: number) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(v) || 0)

onMounted(async () => {
    loading.value = true
    try {
        const [pRes, sRes, wRes] = await Promise.all([
            inventory.purchaseOrders.list({ limit: 100 }),
            inventory.suppliers.list({ limit: 200 }),
            inventory.warehouses.list({ limit: 200 }),
        ])
        list.value = pRes.data
        suppliers.value = sRes.data
        warehouses.value = wRes.data
    } catch (err: any) {
        toast.error('Failed to load purchase orders', err?.data?.message)
    } finally {
        loading.value = false
    }
})
</script>

<style scoped>
.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    color: var(--text-body);
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.action-btn:hover {
    background: var(--bg-muted);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}

.action-btn-danger:hover {
    color: var(--color-danger);
    border-color: rgb(var(--color-danger-rgb) / 0.4);
}
</style>
