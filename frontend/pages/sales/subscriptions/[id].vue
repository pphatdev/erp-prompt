<template>
    <NuxtLayout name="default">
        <div v-if="loading" class="py-24 flex justify-center">
            <span
                class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
        </div>

        <div v-else-if="sub" class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold">{{ sub.subscriptionNumber }}</h1>
                        <Badge :variant="statusBadgeVariant(sub.status)">{{ sub.status }}</Badge>
                        <SubscriptionCountdown v-if="sub.status === 'active'" :end-date="sub.endDate" />
                    </div>
                    <p class="text-xs text-(--text-muted) mt-1">
                        Customer: <span class="text-(--text-body)">{{ sub.customer?.name || '—' }}</span>
                        ·
                        <NuxtLink :to="`/sales/orders/${sub.orderId}`" class="text-(--color-primary) hover:underline">
                            From order</NuxtLink>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <template v-if="sub.status === 'active'">
                        <button class="btn btn-primary text-xs" :disabled="acting" @click="showRenew = true">
                            <i class="ti ti-refresh" />Renew
                        </button>
                        <button class="btn btn-soft-primary text-xs" :disabled="acting"
                            @click="openChangePlan('upgrade')">
                            <i class="ti ti-arrow-up" />Upgrade
                        </button>
                        <button class="btn btn-ghost text-xs" :disabled="acting" @click="openChangePlan('downgrade')">
                            <i class="ti ti-arrow-down" />Downgrade
                        </button>
                        <button
                            class="btn text-xs text-(--color-danger) border border-(--color-danger)/20 hover:bg-(--color-danger-subtle)"
                            :disabled="acting" @click="showCancel = true">
                            <i class="ti ti-ban" />Cancel
                        </button>
                    </template>
                    <button v-else-if="sub.status === 'expired'" class="btn btn-primary text-xs"
                        :disabled="acting" @click="showRenew = true">
                        <i class="ti ti-refresh" />Renew
                    </button>
                </div>
            </header>

            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Cycle</p>
                    <p class="text-base font-semibold text-(--text-heading) mt-1 capitalize">{{
                        sub.billingCycle.replace('_', ' ') }}</p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Total</p>
                    <p class="text-base font-semibold text-(--color-primary) mt-1">
                        <CountUp :value="sub.totalAmount" currency="USD" />
                    </p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Start</p>
                    <p class="text-base font-semibold text-(--text-heading) mt-1">{{ formatDate(sub.startDate) }}</p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">End</p>
                    <p class="text-base font-semibold text-(--text-heading) mt-1">{{ sub.endDate ? formatDate(sub.endDate) : 'Open-ended' }}</p>
                </div>
            </section>

            <!-- Live access URL (replaces the opaque "provisioned tenant id" block) -->
            <section v-if="sub.liveAccessUrl"
                class="glass-card rounded-2xl p-5 border border-(--color-success)/40 bg-(--color-success)/8">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <p class="text-xxs font-bold uppercase tracking-widest text-(--color-success)">
                            <i class="ti ti-circle-check-filled mr-1" />Tenant is live
                        </p>
                        <p class="text-xxs text-(--text-muted) mt-1">Customer access URL:</p>
                        <a :href="sub.liveAccessUrl" target="_blank" rel="noopener"
                            class="mt-1 inline-flex items-center gap-2 font-mono text-sm font-semibold text-(--color-primary) hover:underline break-all">
                            <i class="ti ti-external-link shrink-0" />{{ sub.liveAccessUrl }}
                        </a>
                        <p v-if="sub.provisionedAt" class="text-xxs text-(--text-muted) mt-1.5">
                            Provisioned at {{ formatDateTime(sub.provisionedAt) }}
                        </p>
                    </div>
                    <button class="btn btn-ghost text-xxs shrink-0" :disabled="copying" @click="copyAccessUrl">
                        <i :class="copied ? 'ti ti-check' : 'ti ti-copy'" />{{ copied ? 'Copied' : 'Copy URL' }}
                    </button>
                </div>
            </section>

            <!-- Pending state — tenant customer with a subscription but provisioning hasn't completed yet. -->
            <section v-else-if="sub.status === 'active'"
                class="glass-card rounded-2xl p-5 border border-(--color-warning)/40 bg-(--color-warning)/8">
                <div class="flex items-start gap-3">
                    <i class="ti ti-server-off text-xl text-(--color-warning) shrink-0 mt-0.5" />
                    <div>
                        <p class="text-xs font-semibold text-(--text-heading)">Tenant provisioning pending</p>
                        <p class="text-xxs text-(--text-muted) mt-0.5">
                            The customer's access URL will appear here once provisioning completes.
                            <template v-if="sub.tenantHandle">
                                Reserved handle: <span class="font-mono text-(--color-primary)">@{{ sub.tenantHandle }}</span>
                            </template>
                        </p>
                    </div>
                </div>
            </section>

            <section class="glass-card rounded-2xl p-5">
                <h3 class="text-sm font-semibold mb-4 flex items-center gap-2">
                    <i class="ti ti-list-details text-(--color-primary)" />
                    Software lines
                </h3>
                <div class="overflow-x-auto -mx-5">
                    <table class="w-full text-xs">
                        <thead
                            class="text-xxs uppercase tracking-widest text-(--text-muted) border-b border-(--border-color)">
                            <tr>
                                <th class="text-left px-5 py-2">Product</th>
                                <th class="text-left px-2 py-2">Variant</th>
                                <th class="text-right px-2 py-2">Qty</th>
                                <th class="text-right px-2 py-2">Unit</th>
                                <th class="text-right px-5 py-2">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in sub.items" :key="line.id" class="border-b border-(--border-color)/40">
                                <td class="px-5 py-3 text-(--text-heading) font-medium">{{ line.productName }}</td>
                                <td class="px-2 py-3 font-mono text-xxs">{{ line.variantSku || '—' }}</td>
                                <td class="px-2 py-3 text-right font-mono">{{ line.quantity }}</td>
                                <td class="px-2 py-3 text-right font-mono">{{ fmt(line.unitPrice) }}</td>
                                <td class="px-5 py-3 text-right font-mono font-semibold">{{ fmt(line.lineTotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Renew modal -->
        <div v-if="showRenew"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3>Renew subscription</h3>
                    <button class="w-8 h-8 rounded-full hover:bg-(--bg-muted)" @click="showRenew = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <p class="text-xs text-(--text-muted)">Extends the end date by one cycle and issues a renewal invoice.</p>
                    <div>
                        <label class="form-label">Billing cycle</label>
                        <select v-model="renewCycle" class="form-control">
                            <option value="">Keep current ({{ sub?.billingCycle }})</option>
                            <option value="monthly">Monthly</option>
                            <option value="annual">Annual</option>
                        </select>
                    </div>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button class="btn btn-ghost text-xs" @click="showRenew = false">Close</button>
                    <button class="btn btn-primary text-xs" :disabled="acting" @click="renew">
                        <i class="ti ti-refresh" />Renew now
                    </button>
                </footer>
            </div>
        </div>

        <!-- Change plan modal -->
        <div v-if="showChangePlan"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-lg bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3>{{ changePlanAction === 'upgrade' ? 'Upgrade' : 'Downgrade' }} plan</h3>
                    <button class="w-8 h-8 rounded-full hover:bg-(--bg-muted)" @click="showChangePlan = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <p class="text-xs text-(--text-muted)">
                        Swaps the existing line to a new product/variant. {{ changePlanAction === 'upgrade'
                            ? 'An upgrade delta invoice is billed immediately.'
                            : 'A downgrade credit (negative invoice) is emitted to apply on the next cycle.' }}
                    </p>
                    <div>
                        <label class="form-label">Replace line</label>
                        <select v-model="changePlanTargetId" class="form-control">
                            <option v-for="line in sub?.items || []" :key="line.id" :value="line.productId">
                                {{ line.productName }} ({{ fmt(line.unitPrice) }})
                            </option>
                        </select>
                    </div>
                    <div>
                        <label class="form-label">New product</label>
                        <select v-model="changePlanProductId" class="form-control">
                            <option value="" disabled>Select software product…</option>
                            <option v-for="p in catalogue" :key="p.id" :value="p.id">
                                {{ p.name }} — {{ fmt(p.unit_price) }}
                            </option>
                        </select>
                    </div>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button class="btn btn-ghost text-xs" @click="showChangePlan = false">Close</button>
                    <button class="btn btn-primary text-xs" :disabled="acting || !changePlanProductId" @click="changePlan">
                        <i :class="changePlanAction === 'upgrade' ? 'ti ti-arrow-up' : 'ti ti-arrow-down'" />
                        {{ changePlanAction === 'upgrade' ? 'Upgrade' : 'Downgrade' }}
                    </button>
                </footer>
            </div>
        </div>

        <!-- Cancel modal -->
        <div v-if="showCancel"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3>Cancel subscription</h3>
                    <button class="w-8 h-8 rounded-full hover:bg-(--bg-muted)" @click="showCancel = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <p class="text-xs text-(--text-muted)">Cancelling deactivates the subscription. The customer's
                        tenant remains until deprovisioning lands (out of scope for now).</p>
                    <input v-model="cancelReason" type="text" maxlength="500" placeholder="Reason (optional)"
                        class="form-control text-xs" />
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button class="btn btn-ghost text-xs" @click="showCancel = false">Keep active</button>
                    <button class="btn btn-danger text-xs" :disabled="acting" @click="cancel">
                        <i class="ti ti-ban" />Cancel subscription
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
import { useDateFormat } from '~/composables/useDateFormat'
import type { BillingCycle, ProductLite, Subscription } from '~/types/sales'
import SubscriptionCountdown from '~/components/sales/SubscriptionCountdown.vue'

const route = useRoute()
const sales = useSales()
const toast = useToast()

const sub = ref<Subscription | null>(null)
const loading = ref(true)
const acting = ref(false)

const showCancel = ref(false)
const cancelReason = ref('')

const copying = ref(false)
const copied = ref(false)
const copyAccessUrl = async () => {
    if (!sub.value?.liveAccessUrl) return
    copying.value = true
    try {
        await navigator.clipboard.writeText(sub.value.liveAccessUrl)
        copied.value = true
        setTimeout(() => { copied.value = false }, 2000)
    } catch {
        toast.error('Copy failed')
    } finally {
        copying.value = false
    }
}

const showRenew = ref(false)
const renewCycle = ref<'' | BillingCycle>('')

const showChangePlan = ref(false)
const changePlanAction = ref<'upgrade' | 'downgrade'>('upgrade')
const changePlanProductId = ref('')
const changePlanTargetId = ref('')

const catalogue = ref<ProductLite[]>([])

const fmt = (v: number) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v || 0)
const { formatDate, formatDateTime } = useDateFormat()

const load = async () => {
    loading.value = true
    try {
        const res = await sales.subscriptions.show(route.params.id as string)
        sub.value = res.data
    } catch (err: any) {
        toast.error('Failed to load subscription', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const loadCatalogue = async () => {
    try {
        const res = await sales.catalogue.listProducts()
        catalogue.value = res.data.filter((p) => p.product_type === 'software' && p.is_active)
    } catch {
        // non-fatal — modal stays empty
    }
}

const renew = async () => {
    if (!sub.value) return
    acting.value = true
    try {
        const res = await sales.subscriptions.renew(sub.value.id, renewCycle.value ? { cycle: renewCycle.value as BillingCycle } : {})
        sub.value = res.data
        showRenew.value = false
        renewCycle.value = ''
        toast.success('Subscription renewed', 'End date extended; renewal invoice issued.')
    } catch (err: any) {
        toast.error('Renew failed', err?.data?.message)
    } finally {
        acting.value = false
    }
}

const openChangePlan = (action: 'upgrade' | 'downgrade') => {
    changePlanAction.value = action
    changePlanProductId.value = ''
    changePlanTargetId.value = sub.value?.items[0]?.productId || ''
    showChangePlan.value = true
    if (catalogue.value.length === 0) loadCatalogue()
}

const changePlan = async () => {
    if (!sub.value || !changePlanProductId.value) return
    acting.value = true
    try {
        const res = await sales.subscriptions.changePlan(sub.value.id, {
            product_id: changePlanProductId.value,
            target_product_id: changePlanTargetId.value || null,
            action: changePlanAction.value,
        })
        sub.value = res.data
        showChangePlan.value = false
        toast.success(
            changePlanAction.value === 'upgrade' ? 'Upgrade applied' : 'Downgrade applied',
            changePlanAction.value === 'upgrade'
                ? 'Delta invoice issued.'
                : 'Credit invoice issued (applies on next cycle).',
        )
    } catch (err: any) {
        toast.error('Plan change failed', err?.data?.message)
    } finally {
        acting.value = false
    }
}

const cancel = async () => {
    if (!sub.value) return
    acting.value = true
    try {
        const res = await sales.subscriptions.cancel(sub.value.id, cancelReason.value || undefined)
        sub.value = res.data
        showCancel.value = false
        toast.success('Subscription cancelled')
    } catch (err: any) {
        toast.error('Cancel failed', err?.data?.message)
    } finally {
        acting.value = false
    }
}

onMounted(load)
</script>
