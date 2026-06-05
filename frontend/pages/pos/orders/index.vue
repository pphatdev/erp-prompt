<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header>
                <h1 class="text-xl font-semibold">POS Sales</h1>
                <p class="text-xs text-(--text-muted) mt-1">Receipts taken on register terminals. Void to compensate stock + reverse the journal.</p>
            </header>

            <!-- Filter chips + search -->
            <section class="flex items-center gap-2 flex-wrap max-sm:justify-center">
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
                <i class="ti ti-receipt-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No sales</h4>
                <p class="text-xs text-(--text-muted) mt-1">Take a checkout on the register to see receipts here.</p>
            </div>

            <section v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <NuxtLink v-for="o in orders" :key="o.id" :to="`/pos/orders/${o.id}`"
                    class="glass-card rounded-2xl p-5 flex flex-col gap-3 hover:border-(--color-primary)/40 transition-colors group relative overflow-hidden">
                    <div class="absolute -right-8 -top-8 w-20 h-20 rounded-full bg-(--color-primary)/10 blur-xl pointer-events-none group-hover:scale-150 transition-transform duration-500" />
                    <div class="relative z-10 space-y-3">
                        <header class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-(--text-heading) truncate group-hover:text-(--color-primary)">{{ o.orderNumber }}</h3>
                                <p class="text-xxs text-(--text-muted) truncate">{{ o.customerName || 'Walk-in' }} - {{ o.cashierName || 'cashier' }}</p>
                                <p class="text-xxs text-(--text-muted)">{{ formatDateTime(o.placedAt) }}</p>
                            </div>
                            <Badge :variant="statusMeta(o.status).variant" :icon="statusMeta(o.status).icon">
                                {{ statusMeta(o.status).label }}
                            </Badge>
                        </header>
                        <div class="flex items-end justify-between">
                            <p class="text-xxs text-(--text-muted)">Total</p>
                            <p class="text-sm font-mono text-(--text-heading)">{{ formatMoney(o.grandTotal) }}</p>
                        </div>
                    </div>
                </NuxtLink>
            </section>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { usePos, type PosOrder } from '~/composables/usePos'

definePageMeta({ title: 'Sales' })

const pos = usePos()

const ORDER_STATUSES = [
    { value: 'paid', label: 'Paid', icon: 'ti-check' },
    { value: 'voided', label: 'Voided', icon: 'ti-x' },
    { value: 'refunded', label: 'Refunded', icon: 'ti-receipt-refund' },
] as const

// Single source of truth for the order status badge (design.md §10.2 / §10.4).
type OrderVariant = 'success' | 'danger' | 'secondary'
const ORDER_STATUS_META: Record<string, { variant: OrderVariant; icon: string; label: string }> = {
    paid: { variant: 'success', icon: 'ti-circle-check', label: 'Paid' },
    voided: { variant: 'danger', icon: 'ti-circle-x', label: 'Voided' },
    refunded: { variant: 'secondary', icon: 'ti-receipt-refund', label: 'Refunded' },
}
function statusMeta(status: string) {
    return ORDER_STATUS_META[status]
        ?? { variant: 'secondary' as OrderVariant, icon: 'ti-help', label: status }
}

const orders = ref<PosOrder[]>([])
const loading = ref(true)
const search = ref('')
const filterStatus = ref<'all' | string>('all')

import { formatDateTime } from '~/composables/useDateFormat'

const formatMoney = (n: number | null | undefined) =>
    (Number.isFinite(Number(n)) ? Number(n) : 0).toLocaleString(undefined, { style: 'currency', currency: 'USD' })

async function load() {
    loading.value = true
    try {
        const res = await pos.orders.list({
            status: filterStatus.value === 'all' ? undefined : filterStatus.value,
            limit: 30,
        })
        let rows = res.data ?? []
        if (search.value) {
            const needle = search.value.toLowerCase()
            rows = rows.filter(o => o.orderNumber.toLowerCase().includes(needle))
        }
        orders.value = rows
    } finally {
        loading.value = false
    }
}

onMounted(load)
</script>

<style scoped>
.chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 12px; border-radius: 999px;
    border: 1px solid var(--border-color); background: var(--bg-card);
    font-size: 11px; color: var(--text-body); cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
.chip:hover { background: var(--bg-muted); }
.chip.active {
    background: rgb(var(--color-primary-rgb) / 0.12);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}
</style>
