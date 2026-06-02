<template>
    <div v-if="loading" class="py-12 flex justify-center">
        <span class="w-7 h-7 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
    </div>

    <div v-else-if="orders.length === 0" class="py-12 text-center">
        <i class="ti ti-package-off text-4xl text-(--text-muted)" />
        <p class="text-xs text-(--text-muted) mt-3">No orders yet.</p>
    </div>

    <ul v-else class="space-y-2">
        <li v-for="o in orders" :key="o.id">
            <NuxtLink :to="`/shop/order/${o.id}`"
                class="order-row flex items-center gap-3 p-3 rounded-xl border border-(--border-color) hover:border-(--color-primary)/40 hover:bg-(--bg-muted)/50 transition-colors">
                <span class="w-10 h-10 rounded-full bg-(--color-primary)/10 text-(--color-primary) flex items-center justify-center shrink-0">
                    <i :class="['ti', statusMeta(o.status).icon, 'text-base']" />
                </span>
                <div class="flex-1 min-w-0">
                    <p class="text-xs font-mono text-(--text-heading) truncate">{{ o.orderNumber }}</p>
                    <p class="text-xxs text-(--text-muted)">{{ formatDate(o.placedAt) }}</p>
                </div>
                <div class="flex items-center gap-3 shrink-0">
                    <span class="text-xxs px-2 py-0.5 rounded-full inline-flex items-center gap-1"
                        :class="statusMeta(o.status).class">
                        <i :class="['ti', statusMeta(o.status).icon, 'text-[10px]']" />
                        {{ statusMeta(o.status).label }}
                    </span>
                    <span class="text-sm font-mono font-semibold text-(--color-primary) tabular-nums">
                        {{ formatMoney(o.totalAmount) }}
                    </span>
                    <i class="ti ti-chevron-right text-(--text-muted)" />
                </div>
            </NuxtLink>
        </li>
    </ul>
</template>

<script setup lang="ts">
import type { EcomOrder } from '~/composables/useShop'

defineProps<{
    orders: EcomOrder[]
    loading?: boolean
}>()

const ORDER_STATUS_META: Record<string, { icon: string; class: string; label: string }> = {
    pending:    { icon: 'ti-clock',           class: 'badge-soft-warning',   label: 'Pending' },
    paid:       { icon: 'ti-credit-card',     class: 'badge-soft-info',      label: 'Paid' },
    processing: { icon: 'ti-package',         class: 'badge-soft-info',      label: 'Processing' },
    shipped:    { icon: 'ti-truck',           class: 'badge-soft-info',      label: 'Shipped' },
    delivered:  { icon: 'ti-circle-check',    class: 'badge-soft-success',   label: 'Delivered' },
    cancelled:  { icon: 'ti-circle-x',        class: 'badge-soft-danger',    label: 'Cancelled' },
    refunded:   { icon: 'ti-receipt-refund',  class: 'badge-soft-secondary', label: 'Refunded' },
}

function statusMeta(status: string) {
    return ORDER_STATUS_META[status] ?? { icon: 'ti-circle-dot', class: 'badge-soft-secondary', label: status }
}

function formatMoney(n: number | null | undefined): string {
    return (Number.isFinite(Number(n)) ? Number(n) : 0).toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
    })
}

function formatDate(iso: string | null | undefined): string {
    if (!iso) return '-'
    const d = new Date(iso)
    return Number.isFinite(d.getTime())
        ? d.toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' })
        : '-'
}
</script>
