<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Ecommerce orders</h1>
                    <p class="text-xs text-(--text-muted) mt-1">B2C storefront orders. Fulfill, ship, and refund from here.</p>
                </div>
            </header>

            <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div v-for="kpi in kpis" :key="kpi.label" class="glass-card rounded-2xl p-4 flex items-center gap-3">
                    <span class="w-10 h-10 rounded-xl flex items-center justify-center" :class="kpi.iconClass">
                        <i :class="['ti', kpi.icon]" />
                    </span>
                    <div class="min-w-0">
                        <p class="text-xxs uppercase tracking-widest text-(--text-muted)">{{ kpi.label }}</p>
                        <p class="text-base font-mono text-(--text-heading)">{{ kpi.value }}</p>
                    </div>
                </div>
            </section>

            <!-- Filter chips + search -->
            <section class="flex items-center gap-2 flex-wrap">
                <button type="button" class="chip" :class="{ active: filterStatus === 'all' }"
                    @click="filterStatus = 'all'; load()">All</button>
                <button v-for="s in ORDER_STATUSES" :key="s.value" type="button"
                    class="chip" :class="{ active: filterStatus === s.value }"
                    @click="filterStatus = s.value; load()">
                    <i class="ti" :class="s.icon" /> {{ s.label }}
                </button>
                <div class="ml-auto">
                    <input v-model.lazy="search" type="search" placeholder="Search order #..."
                        class="form-control text-xs w-64" @keyup.enter="load" @change="load" />
                </div>
            </section>

            <div v-if="loading" class="py-24 flex justify-center">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
            </div>

            <div v-else-if="orders.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-shopping-cart-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No orders</h4>
                <p class="text-xs text-(--text-muted) mt-1">Shoppers haven't placed any yet.</p>
            </div>

            <section v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <NuxtLink v-for="o in orders" :key="o.id" :to="`/ecommerce/orders/${o.id}`"
                    class="glass-card rounded-2xl p-5 pb-4 flex flex-col gap-3 group hover:border-(--color-primary)/40 transition-all duration-150 relative overflow-hidden min-h-[160px]">
                    <div class="absolute -right-8 -top-8 w-20 h-20 rounded-full bg-(--color-primary)/10 blur-xl pointer-events-none group-hover:scale-150 transition-transform duration-500" />
                    <div class="space-y-3 relative z-10">
                        <header class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-(--text-heading) truncate group-hover:text-(--color-primary) transition-colors">
                                    {{ o.orderNumber }}
                                </h3>
                                <p class="text-xxs text-(--text-muted) truncate mt-0.5">
                                    {{ o.customer?.email || 'guest' }}
                                </p>
                            </div>
                            <span :class="`badge-${ec.statusBadgeVariant(o.status)} text-xxs`">{{ o.status }}</span>
                        </header>
                        <div class="text-xxs text-(--text-muted)">{{ formatDate(o.placedAt) }}</div>
                        <div class="flex items-end justify-between">
                            <p class="text-xxs text-(--text-muted)">Total</p>
                            <p class="text-sm font-mono text-(--text-heading)">{{ formatMoney(o.totalAmount) }}</p>
                        </div>
                    </div>
                </NuxtLink>
            </section>

            <div v-if="pagination && pagination.totalPages > 1" class="flex items-center justify-center gap-2">
                <button class="btn btn-soft-secondary text-xs" :disabled="page <= 1" @click="page--; load()">Prev</button>
                <span class="text-xs text-(--text-muted)">Page {{ page }} / {{ pagination.totalPages }}</span>
                <button class="btn btn-soft-secondary text-xs" :disabled="page >= pagination.totalPages" @click="page++; load()">Next</button>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useEcommerce, type EcomOrderAdmin } from '~/composables/useEcommerce'

const ec = useEcommerce()

const search = ref('')
const filterStatus = ref<'all' | string>('all')

const ORDER_STATUSES = [
    { value: 'pending_payment', label: 'Pending', icon: 'ti-clock' },
    { value: 'paid', label: 'Paid', icon: 'ti-check' },
    { value: 'fulfilling', label: 'Fulfilling', icon: 'ti-package' },
    { value: 'shipped', label: 'Shipped', icon: 'ti-truck' },
    { value: 'delivered', label: 'Delivered', icon: 'ti-package-export' },
    { value: 'cancelled', label: 'Cancelled', icon: 'ti-x' },
    { value: 'refunded', label: 'Refunded', icon: 'ti-receipt-refund' },
] as const

const page = ref(1)
const loading = ref(true)
const orders = ref<EcomOrderAdmin[]>([])
const pagination = ref<any>(null)

const formatMoney = (n: number | null | undefined) =>
    (Number.isFinite(Number(n)) ? Number(n) : 0).toLocaleString(undefined, { style: 'currency', currency: 'USD' })
const formatDate = (iso: string | null) => iso ? new Date(iso).toLocaleDateString() : '-'

const kpis = computed(() => {
    const total = pagination.value?.total ?? orders.value.length
    const paid = orders.value.filter(o => ['paid', 'fulfilling', 'shipped', 'delivered'].includes(o.status)).length
    const shipped = orders.value.filter(o => o.status === 'shipped').length
    const refunded = orders.value.filter(o => o.status === 'refunded').length
    return [
        { label: 'Total', value: total, icon: 'ti-receipt', iconClass: 'badge-soft-primary' },
        { label: 'Paid', value: paid, icon: 'ti-check', iconClass: 'badge-soft-success' },
        { label: 'Shipped', value: shipped, icon: 'ti-truck', iconClass: 'badge-soft-info' },
        { label: 'Refunded', value: refunded, icon: 'ti-receipt-refund', iconClass: 'badge-soft-secondary' },
    ]
})

async function load() {
    loading.value = true
    try {
        const res = await ec.orders.list({
            search: search.value || undefined,
            status: filterStatus.value === 'all' ? undefined : filterStatus.value,
            page: page.value,
            limit: 24,
        })
        orders.value = res.data ?? []
        pagination.value = res.pagination ?? null
    } finally {
        loading.value = false
    }
}

onMounted(load)
</script>

<style scoped>
.chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    font-size: 11px;
    color: var(--text-body);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.chip:hover {
    background: var(--bg-muted);
}

.chip.active {
    background: rgb(var(--color-primary-rgb) / 0.12);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}
</style>
