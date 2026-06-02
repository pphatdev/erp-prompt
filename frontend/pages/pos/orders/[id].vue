<template>
    <NuxtLayout name="default">
        <div v-if="loading" class="py-24 flex justify-center">
            <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
        </div>

        <div v-else-if="order" class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <NuxtLink to="/pos/orders" class="text-xs text-(--text-muted) hover:text-(--text-heading)">
                        <i class="ti ti-arrow-left" /> All sales
                    </NuxtLink>
                    <h1 class="text-xl font-semibold mt-2">{{ order.orderNumber }}</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        {{ order.cashierName || 'cashier' }} - {{ formatDateTime(order.placedAt) }}
                    </p>
                    <p class="text-xs text-(--text-muted) mt-0.5">
                        Customer:
                        <span class="text-(--text-heading)">{{ order.customerName || 'Walk-in' }}</span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Badge :variant="statusMeta(order.status).variant" :icon="statusMeta(order.status).icon">
                        {{ statusMeta(order.status).label }}
                    </Badge>
                    <button v-if="order.status === 'paid'" class="btn btn-soft-danger text-xs" @click="voidOrder">
                        <i class="ti ti-x" /> Void
                    </button>
                </div>
            </header>

            <div v-if="error" class="text-xs px-3 py-2 rounded bg-(--color-danger)/10 text-(--color-danger)">{{ error }}</div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2 glass-card rounded-2xl p-5 space-y-3">
                    <h3 class="text-sm font-semibold text-(--text-heading)">Items</h3>
                    <div v-for="item in order.items" :key="item.id" class="flex justify-between text-xs py-2 border-b border-(--border-color) last:border-0">
                        <div>
                            <div class="text-(--text-heading)">{{ item.productName }}</div>
                            <div class="text-(--text-muted) text-xxs font-mono">{{ item.variantSku || item.productSku }} - {{ item.quantity }} x {{ formatMoney(item.unitPrice) }}</div>
                        </div>
                        <div class="font-mono">{{ formatMoney(item.lineTotal) }}</div>
                    </div>
                </div>

                <aside class="glass-card rounded-2xl p-5 space-y-3 text-xs">
                    <h3 class="text-sm font-semibold text-(--text-heading)">Summary</h3>
                    <div class="flex justify-between"><span class="text-(--text-muted)">Subtotal</span><span class="font-mono">{{ formatMoney(order.subtotal) }}</span></div>
                    <div class="flex justify-between"><span class="text-(--text-muted)">Discount</span><span class="font-mono">- {{ formatMoney(order.discountTotal) }}</span></div>
                    <div class="flex justify-between"><span class="text-(--text-muted)">Tax</span><span class="font-mono">{{ formatMoney(order.taxTotal) }}</span></div>
                    <div class="flex justify-between text-sm font-semibold pt-2 border-t border-(--border-color)">
                        <span>Total</span><span class="font-mono text-(--color-primary)">{{ formatMoney(order.grandTotal) }}</span>
                    </div>
                </aside>
            </div>

            <div class="glass-card rounded-2xl p-5 space-y-2 text-xs">
                <h3 class="text-sm font-semibold text-(--text-heading)">Tenders</h3>
                <div v-for="p in order.payments" :key="p.id" class="flex justify-between py-1 border-b border-(--border-color) last:border-0">
                    <div>
                        <div class="text-(--text-heading) capitalize">{{ p.paymentMethod }}</div>
                        <div v-if="p.referenceNumber" class="text-(--text-muted) text-xxs font-mono">{{ p.referenceNumber }}</div>
                    </div>
                    <div class="text-right">
                        <div class="font-mono">{{ formatMoney(p.amount) }}</div>
                        <div v-if="p.changeDue > 0" class="text-(--text-muted) text-xxs">change {{ formatMoney(p.changeDue) }}</div>
                    </div>
                </div>
            </div>

            <div v-if="order.voidedAt" class="glass-card rounded-2xl p-5 text-xs space-y-1 border border-(--color-danger)/30">
                <h3 class="text-sm font-semibold text-(--color-danger)">Voided</h3>
                <p class="text-(--text-muted)">Voided {{ formatDateTime(order.voidedAt) }}</p>
                <p v-if="order.voidReason" class="text-(--text-muted)">Reason: {{ order.voidReason }}</p>
            </div>
        </div>

        <div v-else class="glass-card rounded-2xl py-20 text-center">
            <p class="text-sm text-(--text-muted)">Order not found.</p>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { usePos, type PosOrder } from '~/composables/usePos'
import { useToast } from '~/composables/useToast'
import { useRoute } from 'vue-router'

const pos = usePos()
const route = useRoute()
const toast = useToast()

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

const order = ref<PosOrder | null>(null)
const loading = ref(true)
const error = ref('')

import { formatDateTime } from '~/composables/useDateFormat'

const formatMoney = (n: number | null | undefined) =>
    (Number.isFinite(Number(n)) ? Number(n) : 0).toLocaleString(undefined, { style: 'currency', currency: 'USD' })

async function load() {
    loading.value = true
    try {
        const res = await pos.orders.show(String(route.params.id))
        order.value = res.data
    } finally {
        loading.value = false
    }
}

async function voidOrder() {
    if (!order.value) return
    const ok = await toast.confirm({
        title: `Void ${order.value.orderNumber}?`,
        description: 'This will restock the items, reverse the journal entry, and lock the order. Cannot be undone.',
        confirmLabel: 'Void sale',
        color: 'danger',
    })
    if (!ok) return
    try {
        const res = await pos.orders.void(order.value.id)
        order.value = res.data
        toast.success('Order voided')
    } catch (e: any) {
        error.value = e?.data?.message || 'Void failed.'
    }
}

onMounted(load)
</script>
