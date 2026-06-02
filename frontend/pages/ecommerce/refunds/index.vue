<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header>
                <h1 class="text-xl font-semibold">Refunds</h1>
                <p class="text-xs text-(--text-muted) mt-1">Admin queue for shopper refund requests. Approve to post the reversing journal and restock.</p>
            </header>

            <!-- Filter chips -->
            <section class="flex items-center gap-2 flex-wrap">
                <button type="button" class="chip" :class="{ active: filterStatus === 'all' }"
                    @click="filterStatus = 'all'; load()">All</button>
                <button v-for="s in REFUND_STATUSES" :key="s.value" type="button"
                    class="chip" :class="{ active: filterStatus === s.value }"
                    @click="filterStatus = s.value; load()">
                    <i class="ti" :class="s.icon" /> {{ s.label }}
                </button>
            </section>

            <div v-if="loading" class="py-24 flex justify-center">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
            </div>

            <div v-else-if="refunds.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-receipt-refund text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No refunds</h4>
                <p class="text-xs text-(--text-muted) mt-1">Nothing in the {{ filterStatus }} bucket.</p>
            </div>

            <section v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <NuxtLink v-for="r in refunds" :key="r.id" :to="`/ecommerce/refunds/${r.id}`"
                    class="glass-card rounded-2xl p-5 flex flex-col gap-3 hover:border-(--color-primary)/40 transition-colors">
                    <header class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="text-sm font-semibold text-(--text-heading)">{{ r.refundNumber }}</h3>
                            <p class="text-xxs text-(--text-muted) mt-0.5">{{ formatDate(r.requestedAt) }}</p>
                        </div>
                        <span :class="`badge-${ec.statusBadgeVariant(r.status)} text-xxs`">{{ r.status }}</span>
                    </header>
                    <div class="flex items-end justify-between">
                        <div>
                            <p class="text-xxs text-(--text-muted)">{{ r.isPartial ? 'Partial' : 'Full' }} refund</p>
                            <p v-if="r.reason" class="text-xxs text-(--text-muted) truncate max-w-[180px]">{{ r.reason }}</p>
                        </div>
                        <p class="text-sm font-mono text-(--text-heading)">{{ formatMoney(r.amount) }}</p>
                    </div>
                </NuxtLink>
            </section>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useEcommerce, type EcomRefundAdmin } from '~/composables/useEcommerce'

const ec = useEcommerce()

const refunds = ref<EcomRefundAdmin[]>([])
const loading = ref(true)
const filterStatus = ref<'all' | string>('requested')

const REFUND_STATUSES = [
    { value: 'requested', label: 'Requested', icon: 'ti-clock' },
    { value: 'approved', label: 'Approved', icon: 'ti-thumb-up' },
    { value: 'processing', label: 'Processing', icon: 'ti-loader' },
    { value: 'completed', label: 'Completed', icon: 'ti-check' },
    { value: 'rejected', label: 'Rejected', icon: 'ti-x' },
] as const

const formatMoney = (n: number | null | undefined) =>
    (Number.isFinite(Number(n)) ? Number(n) : 0).toLocaleString(undefined, { style: 'currency', currency: 'USD' })
const formatDate = (iso: string | null) => iso ? new Date(iso).toLocaleDateString() : '-'

async function load() {
    loading.value = true
    try {
        const res = await ec.refunds.list({
            status: filterStatus.value === 'all' ? undefined : filterStatus.value,
            limit: 30,
        })
        refunds.value = res.data ?? []
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
