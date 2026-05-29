<template>
    <NuxtLayout name="default">
        <div v-if="loading" class="py-24 flex justify-center">
            <span
                class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
        </div>

        <div v-else-if="order" class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold">{{ order.orderNumber }}</h1>
                        <Badge :variant="statusBadgeVariant(order.status)">{{ order.status }}</Badge>
                    </div>
                    <p class="text-xs text-(--text-muted) mt-1">
                        Customer: <span class="text-(--text-body)">{{ order.customer?.name || '—' }}</span>
                        <span v-if="order.quotationId" class="ml-2">·
                            <NuxtLink :to="`/sales/quotations/${order.quotationId}`"
                                class="text-(--color-primary) hover:underline">From quote</NuxtLink>
                        </span>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button v-if="order.status === 'draft'" class="btn btn-primary text-xs" :disabled="acting"
                        @click="confirm">
                        <i class="ti ti-check" />Confirm &amp; fulfill
                    </button>
                    <button v-if="order.status === 'draft'"
                        class="btn text-xs text-(--color-danger) border border-(--color-danger)/20 hover:bg-(--color-danger-subtle)"
                        :disabled="acting" @click="showCancel = true">
                        <i class="ti ti-ban" />Cancel
                    </button>
                </div>
            </header>

            <!-- Confirm warning -->
            <div v-if="order.status === 'draft'"
                class="px-4 py-3 rounded-lg bg-(--color-warning-subtle) text-(--color-warning) text-xs">
                <i class="ti ti-alert-triangle" />
                Confirming this order will <b>atomically</b> create an Invoice, set up an active Subscription for software
                lines, and deduct hardware lines from stock. If the customer is a tenant-type and not yet provisioned,
                it will also <b>provision their tenant</b> (subdomain + admin user) after the transaction commits.
                Anything that fails inside the transaction rolls the entire order back to <code class="font-mono">draft</code>.
            </div>

            <!-- Tiles -->
            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Subtotal</p>
                    <p class="text-base font-semibold text-(--text-heading) mt-1">
                        <CountUp :value="order.subtotal" currency="USD" />
                    </p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Tax</p>
                    <p class="text-base font-semibold text-(--text-heading) mt-1">
                        <CountUp :value="order.taxAmount" currency="USD" />
                    </p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Total</p>
                    <p class="text-base font-semibold text-(--color-primary) mt-1">
                        <CountUp :value="order.totalAmount" currency="USD" />
                    </p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Due</p>
                    <p class="text-base font-semibold text-(--text-heading) mt-1">{{ order.dueDate || '—' }}</p>
                </div>
            </section>

            <!-- Downstream artifacts -->
            <section v-if="order.invoiceId || order.subscriptionId" class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <NuxtLink v-if="order.invoiceId" :to="`/sales/invoices/${order.invoiceId}`"
                    class="glass-card rounded-2xl p-5 flex items-center justify-between hover:border-(--color-primary)/40 transition-colors">
                    <div>
                        <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Invoice</p>
                        <p class="text-sm font-semibold text-(--text-heading) mt-1">Open AR record</p>
                    </div>
                    <i class="ti ti-receipt text-2xl text-(--color-primary)" />
                </NuxtLink>
                <NuxtLink v-if="order.subscriptionId" :to="`/sales/subscriptions/${order.subscriptionId}`"
                    class="glass-card rounded-2xl p-5 flex items-center justify-between hover:border-(--color-primary)/40 transition-colors">
                    <div>
                        <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Subscription</p>
                        <p class="text-sm font-semibold text-(--text-heading) mt-1">Software fulfillment</p>
                    </div>
                    <i class="ti ti-cloud text-2xl text-(--color-primary)" />
                </NuxtLink>
            </section>

            <!-- Items -->
            <section class="glass-card rounded-2xl p-5">
                <h3 class="text-sm font-semibold mb-4 flex items-center gap-2">
                    <i class="ti ti-list-details text-(--color-primary)" />
                    Line items
                </h3>
                <div class="overflow-x-auto -mx-5">
                    <table class="w-full text-xs">
                        <thead
                            class="text-xxs uppercase tracking-widest text-(--text-muted) border-b border-(--border-color)">
                            <tr>
                                <th class="text-left px-5 py-2">Product</th>
                                <th class="text-left px-2 py-2">Type</th>
                                <th class="text-left px-2 py-2">Variant</th>
                                <th class="text-right px-2 py-2">Qty</th>
                                <th class="text-right px-2 py-2">Unit</th>
                                <th class="text-right px-5 py-2">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in order.items" :key="line.id" class="border-b border-(--border-color)/40">
                                <td class="px-5 py-3 text-(--text-heading) font-medium">{{ line.productName }}</td>
                                <td class="px-2 py-3 capitalize">
                                    <span class="px-1.5 py-0.5 rounded text-xxs font-mono"
                                        :class="line.productType === 'software' ? 'badge-soft-primary' : 'badge-soft-info'">
                                        {{ line.productType || '—' }}
                                    </span>
                                </td>
                                <td class="px-2 py-3 font-mono text-xxs">{{ line.variantSku || '—' }}</td>
                                <td class="px-2 py-3 text-right font-mono">{{ line.quantity }}</td>
                                <td class="px-2 py-3 text-right font-mono">{{ fmt(line.unitPrice) }}</td>
                                <td class="px-5 py-3 text-right font-mono font-semibold">{{ fmt(line.total) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Trail -->
            <section v-if="order.confirmedAt || order.cancelledAt" class="glass-card rounded-2xl p-5">
                <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                    <i class="ti ti-history text-(--color-primary)" />
                    Trail
                </h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div v-if="order.confirmedAt">
                        <dt class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Confirmed</dt>
                        <dd>{{ formatDate(order.confirmedAt) }}</dd>
                    </div>
                    <div v-if="order.cancelledAt">
                        <dt class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Cancelled</dt>
                        <dd>{{ formatDate(order.cancelledAt) }}</dd>
                        <dd v-if="order.cancelReason" class="text-(--text-muted) italic">"{{ order.cancelReason }}"</dd>
                    </div>
                </dl>
            </section>
        </div>

        <!-- Cancel modal -->
        <div v-if="showCancel"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3>Cancel order</h3>
                    <button class="w-8 h-8 rounded-full hover:bg-(--bg-muted)" @click="showCancel = false"><i
                            class="ti ti-x" /></button>
                </header>
                <div class="p-5 space-y-3">
                    <p class="text-xs text-(--text-muted)">Only orders in <code class="font-mono">draft</code> status can
                        be cancelled. Confirmed orders require reversing their downstream artifacts first.</p>
                    <input v-model="cancelReason" type="text" maxlength="500" placeholder="Reason (optional)"
                        class="form-control text-xs" />
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button class="btn btn-ghost text-xs" @click="showCancel = false">Keep open</button>
                    <button class="btn btn-danger text-xs" :disabled="acting" @click="cancel">
                        <i class="ti ti-ban" />Cancel order
                    </button>
                </footer>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useSales, statusBadgeVariant } from '~/composables/useSales'
import { useToast } from '~/composables/useToast'
import type { Order } from '~/types/sales'
import CountUp from '~/components/CountUp.vue'

const route = useRoute()
const sales = useSales()
const toast = useToast()

const order = ref<Order | null>(null)
const loading = ref(true)
const acting = ref(false)
const showCancel = ref(false)
const cancelReason = ref('')

const fmt = (v: number) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v || 0)
const formatDate = (iso: string) => new Date(iso).toLocaleString()

const load = async () => {
    loading.value = true
    try {
        const res = await sales.orders.show(route.params.id as string)
        order.value = res.data
    } catch (err: any) {
        toast.error('Failed to load order', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const confirm = async () => {
    if (!order.value) return
    acting.value = true
    try {
        const res = await sales.orders.confirm(order.value.id)
        order.value = res.data
        toast.success('Order confirmed', 'Invoice, subscription, and stock movements are in place.')
    } catch (err: any) {
        toast.error('Confirm failed', err?.data?.message)
    } finally {
        acting.value = false
    }
}

const cancel = async () => {
    if (!order.value) return
    acting.value = true
    try {
        const res = await sales.orders.cancel(order.value.id, cancelReason.value || undefined)
        order.value = res.data
        showCancel.value = false
        toast.success('Order cancelled')
    } catch (err: any) {
        toast.error('Cancel failed', err?.data?.message)
    } finally {
        acting.value = false
    }
}

onMounted(load)
</script>
