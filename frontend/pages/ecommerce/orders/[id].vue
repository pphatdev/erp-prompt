<template>
    <NuxtLayout name="default">
        <div v-if="loading" class="py-24 flex justify-center">
            <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
        </div>

        <div v-else-if="order" class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <NuxtLink to="/ecommerce/orders" class="text-xs text-(--text-muted) hover:text-(--text-heading)">
                        <i class="ti ti-arrow-left" /> All orders
                    </NuxtLink>
                    <h1 class="text-xl font-semibold mt-2">{{ order.orderNumber }}</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Placed {{ formatDate(order.placedAt) }} - {{ order.customer?.email || 'guest' }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <span :class="`badge-${ec.statusBadgeVariant(order.status)}`">{{ order.status }}</span>
                </div>
            </header>

            <section class="flex flex-wrap gap-2">
                <button v-if="order.status === 'paid'" class="btn btn-soft-primary text-xs" :disabled="busy" @click="act('markFulfilling')">
                    <i class="ti ti-package" /> Mark fulfilling
                </button>
                <button v-if="['paid', 'fulfilling'].includes(order.status)" class="btn btn-soft-info text-xs" @click="shipModalOpen = true">
                    <i class="ti ti-truck" /> Ship
                </button>
                <button v-if="order.status === 'shipped'" class="btn btn-soft-success text-xs" :disabled="busy" @click="act('markDelivered')">
                    <i class="ti ti-check" /> Mark delivered
                </button>
                <button v-if="['paid', 'fulfilling', 'shipped', 'delivered'].includes(order.status)" class="btn btn-soft-warning text-xs" @click="refundModalOpen = true">
                    <i class="ti ti-receipt-refund" /> Refund
                </button>
                <button v-if="order.status === 'pending_payment'" class="btn btn-soft-danger text-xs" @click="cancelModalOpen = true">
                    <i class="ti ti-x" /> Cancel
                </button>
            </section>

            <div v-if="error" class="text-xs px-3 py-2 rounded bg-(--color-danger)/10 text-(--color-danger)">{{ error }}</div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2 glass-card rounded-2xl p-5 space-y-3">
                    <h3 class="text-sm font-semibold text-(--text-heading)">Items</h3>
                    <div v-for="item in order.items" :key="item.id" class="flex justify-between text-xs py-2 border-b border-(--border-color) last:border-0">
                        <div>
                            <div class="text-(--text-heading)">{{ item.productName }}</div>
                            <div class="text-(--text-muted) text-xxs">{{ item.variantSku || item.productSku }} - qty {{ item.quantity }}</div>
                        </div>
                        <div class="font-mono">{{ formatMoney(item.lineTotal) }}</div>
                    </div>
                </div>

                <aside class="glass-card rounded-2xl p-5 space-y-3 text-xs">
                    <h3 class="text-sm font-semibold text-(--text-heading)">Summary</h3>
                    <div class="flex justify-between"><span class="text-(--text-muted)">Subtotal</span><span class="font-mono">{{ formatMoney(order.subtotal) }}</span></div>
                    <div class="flex justify-between"><span class="text-(--text-muted)">Tax</span><span class="font-mono">{{ formatMoney(order.taxAmount) }}</span></div>
                    <div class="flex justify-between"><span class="text-(--text-muted)">Shipping</span><span class="font-mono">{{ formatMoney(order.shippingAmount) }}</span></div>
                    <div class="flex justify-between text-sm font-semibold pt-2 border-t border-(--border-color)">
                        <span>Total</span><span class="font-mono text-(--color-primary)">{{ formatMoney(order.totalAmount) }}</span>
                    </div>
                </aside>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="glass-card rounded-2xl p-5 space-y-2 text-xs">
                    <h3 class="text-sm font-semibold text-(--text-heading)">Shipping address</h3>
                    <div v-if="order.shippingAddress" class="text-(--text-muted) leading-relaxed">
                        <div>{{ order.shippingAddress.recipient_name }}</div>
                        <div>{{ order.shippingAddress.line1 }}</div>
                        <div v-if="order.shippingAddress.line2">{{ order.shippingAddress.line2 }}</div>
                        <div>{{ order.shippingAddress.city }} {{ order.shippingAddress.postal_code }}</div>
                        <div>{{ order.shippingAddress.country }}</div>
                    </div>
                    <div v-else class="text-(--text-muted)">No shipping address.</div>
                    <div v-if="order.trackingNumber" class="pt-2 border-t border-(--border-color)">
                        <div>{{ order.carrier }}</div>
                        <div class="font-mono">{{ order.trackingNumber }}</div>
                    </div>
                </div>

                <div class="glass-card rounded-2xl p-5 space-y-2 text-xs">
                    <h3 class="text-sm font-semibold text-(--text-heading)">Payments</h3>
                    <div v-for="p in order.payments" :key="p.id" class="flex justify-between py-1 border-b border-(--border-color) last:border-0">
                        <div>
                            <div class="text-(--text-heading)">{{ p.provider }}</div>
                            <div class="text-(--text-muted) text-xxs">{{ p.status }}</div>
                        </div>
                        <div class="text-right">
                            <div class="font-mono">{{ formatMoney(p.amount) }}</div>
                            <div v-if="p.gatewayFee > 0" class="text-(--text-muted) text-xxs">fee {{ formatMoney(p.gatewayFee) }}</div>
                        </div>
                    </div>
                    <div v-if="!order.payments?.length" class="text-(--text-muted)">No payments recorded.</div>
                </div>
            </div>

            <div v-if="order.refunds?.length" class="glass-card rounded-2xl p-5 space-y-2 text-xs">
                <h3 class="text-sm font-semibold text-(--text-heading)">Refunds</h3>
                <NuxtLink v-for="r in order.refunds" :key="r.id" :to="`/ecommerce/refunds/${r.id}`"
                    class="flex justify-between py-1 border-b border-(--border-color) last:border-0 hover:text-(--color-primary)">
                    <div class="font-mono">{{ r.refundNumber }}</div>
                    <div class="flex items-center gap-2">
                        <span class="badge-soft-secondary text-xxs">{{ r.status }}</span>
                        <span class="font-mono">{{ formatMoney(r.amount) }}</span>
                    </div>
                </NuxtLink>
            </div>

            <!-- Ship modal -->
            <Teleport to="body">
                <div v-if="shipModalOpen" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="shipModalOpen = false">
                    <div class="glass-card rounded-2xl p-6 max-w-sm w-full space-y-4">
                        <h3 class="text-sm font-semibold text-(--text-heading)">Ship order</h3>
                        <div>
                            <label class="text-xs text-(--text-muted)">Carrier</label>
                            <input v-model="shipForm.carrier" required class="form-control text-sm mt-1" />
                        </div>
                        <div>
                            <label class="text-xs text-(--text-muted)">Tracking number</label>
                            <input v-model="shipForm.tracking" required class="form-control text-sm mt-1" />
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button class="btn btn-soft-secondary text-xs" @click="shipModalOpen = false">Cancel</button>
                            <button class="btn btn-primary text-xs" :disabled="busy || !shipForm.carrier || !shipForm.tracking" @click="ship">
                                Mark shipped
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>

            <!-- Refund modal -->
            <Teleport to="body">
                <div v-if="refundModalOpen" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="refundModalOpen = false">
                    <div class="glass-card rounded-2xl p-6 max-w-lg w-full space-y-4">
                        <h3 class="text-sm font-semibold text-(--text-heading)">Request refund</h3>
                        <div>
                            <label class="text-xs text-(--text-muted)">Reason</label>
                            <textarea v-model="refundForm.reason" rows="2" class="form-control text-sm mt-1" />
                        </div>
                        <div class="space-y-2 max-h-60 overflow-auto">
                            <div v-for="(line, idx) in refundForm.items" :key="line.order_item_id" class="flex items-center gap-2 text-xs">
                                <input v-model="refundForm.items[idx].include" type="checkbox" />
                                <div class="flex-1 truncate">{{ orderItemLabel(line.order_item_id) }}</div>
                                <input v-model.number="refundForm.items[idx].quantity" type="number" min="0" :max="orderItemMaxQty(line.order_item_id)" class="form-control w-20 text-xs" />
                                <label class="text-xxs flex items-center gap-1">
                                    <input v-model="refundForm.items[idx].restock" type="checkbox" /> Restock
                                </label>
                            </div>
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button class="btn btn-soft-secondary text-xs" @click="refundModalOpen = false">Cancel</button>
                            <button class="btn btn-warning text-xs" :disabled="busy || !hasSelectedLines" @click="requestRefund">
                                Create refund request
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>

            <!-- Cancel modal -->
            <Teleport to="body">
                <div v-if="cancelModalOpen" class="fixed inset-0 z-50 bg-black/40 flex items-center justify-center p-4" @click.self="cancelModalOpen = false">
                    <div class="glass-card rounded-2xl p-6 max-w-sm w-full space-y-4">
                        <h3 class="text-sm font-semibold text-(--text-heading)">Cancel order</h3>
                        <div>
                            <label class="text-xs text-(--text-muted)">Reason</label>
                            <textarea v-model="cancelReason" rows="2" class="form-control text-sm mt-1" />
                        </div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button class="btn btn-soft-secondary text-xs" @click="cancelModalOpen = false">Keep order</button>
                            <button class="btn btn-danger text-xs" :disabled="busy" @click="cancelOrder">
                                Cancel order
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>
        </div>

        <div v-else class="glass-card rounded-2xl py-20 text-center">
            <p class="text-sm text-(--text-muted)">Order not found.</p>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useEcommerce, type EcomOrderAdmin } from '~/composables/useEcommerce'
import { useRoute } from 'vue-router'

const ec = useEcommerce()
const route = useRoute()

const order = ref<EcomOrderAdmin | null>(null)
const loading = ref(true)
const busy = ref(false)
const error = ref('')

const shipModalOpen = ref(false)
const refundModalOpen = ref(false)
const cancelModalOpen = ref(false)
const shipForm = ref({ carrier: '', tracking: '' })
const cancelReason = ref('')
const refundForm = ref<{ reason: string; items: Array<{ order_item_id: string; include: boolean; quantity: number; restock: boolean }> }>({ reason: '', items: [] })

const formatMoney = (n: number | null | undefined) =>
    (Number.isFinite(Number(n)) ? Number(n) : 0).toLocaleString(undefined, { style: 'currency', currency: 'USD' })
const formatDate = (iso: string | null) => iso ? new Date(iso).toLocaleDateString() : '-'

const hasSelectedLines = computed(() => refundForm.value.items.some(i => i.include && i.quantity > 0))

function orderItemLabel(itemId: string) {
    const i = order.value?.items?.find(x => x.id === itemId)
    return i ? `${i.productName} (${i.variantSku || i.productSku})` : '-'
}

function orderItemMaxQty(itemId: string) {
    const i = order.value?.items?.find(x => x.id === itemId)
    return i?.quantity ?? 1
}

async function load() {
    loading.value = true
    try {
        const res = await ec.orders.show(String(route.params.id))
        order.value = res.data
        refundForm.value.items = (res.data.items ?? []).map(i => ({
            order_item_id: i.id,
            include: false,
            quantity: i.quantity,
            restock: true,
        }))
    } finally {
        loading.value = false
    }
}

async function act(action: 'markFulfilling' | 'markDelivered') {
    if (!order.value) return
    busy.value = true
    error.value = ''
    try {
        const res = await ec.orders[action](order.value.id)
        order.value = res.data
    } catch (e: any) {
        error.value = e?.data?.message || 'Action failed.'
    } finally {
        busy.value = false
    }
}

async function ship() {
    if (!order.value) return
    busy.value = true
    error.value = ''
    try {
        const res = await ec.orders.ship(order.value.id, shipForm.value.carrier, shipForm.value.tracking)
        order.value = res.data
        shipModalOpen.value = false
    } catch (e: any) {
        error.value = e?.data?.message || 'Ship failed.'
    } finally {
        busy.value = false
    }
}

async function cancelOrder() {
    if (!order.value) return
    busy.value = true
    error.value = ''
    try {
        const res = await ec.orders.cancel(order.value.id, cancelReason.value || undefined)
        order.value = res.data
        cancelModalOpen.value = false
    } catch (e: any) {
        error.value = e?.data?.message || 'Cancel failed.'
    } finally {
        busy.value = false
    }
}

async function requestRefund() {
    if (!order.value) return
    const lines = refundForm.value.items.filter(i => i.include && i.quantity > 0)
    if (lines.length === 0) return
    busy.value = true
    error.value = ''
    try {
        await ec.refunds.create({
            order_id: order.value.id,
            reason: refundForm.value.reason || undefined,
            items: lines.map(l => ({ order_item_id: l.order_item_id, quantity: l.quantity, restock: l.restock })),
        })
        refundModalOpen.value = false
        await load()
    } catch (e: any) {
        error.value = e?.data?.message || 'Refund request failed.'
    } finally {
        busy.value = false
    }
}

onMounted(load)
</script>
