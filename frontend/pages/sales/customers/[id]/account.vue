<template>
    <NuxtLayout name="default">
        <div v-if="loading" class="py-24 flex justify-center">
            <span
                class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
        </div>

        <div v-else-if="customer" class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div class="flex items-center gap-3 min-w-0">
                    <NuxtLink :to="`/sales/customers/${customer.id}`"
                        class="w-9 h-9 rounded-lg border border-(--border-color) bg-(--bg-card) hover:bg-(--bg-muted) flex items-center justify-center text-(--text-muted) hover:text-(--color-primary) transition-colors"
                        title="Back to customer">
                        <i class="ti ti-arrow-left" />
                    </NuxtLink>
                    <div class="min-w-0">
                        <h1 class="text-xl font-semibold truncate">{{ customer.name }} — Account</h1>
                        <p class="text-xs text-(--text-muted) mt-0.5">
                            Subscription dashboard. Renew, upgrade, or downgrade per product schedule.
                        </p>
                    </div>
                </div>
            </header>

            <!-- Access URL block -->
            <section class="glass-card rounded-2xl p-5">
                <h3 class="section-title"><i class="ti ti-link" />Access URL</h3>
                <div v-if="customer.provisionedSubdomain" class="mt-4 flex flex-col sm:flex-row items-start sm:items-center gap-3">
                    <a :href="`https://${customer.provisionedSubdomain}`" target="_blank" rel="noopener"
                        class="flex items-center gap-2 font-mono text-sm font-semibold text-(--color-primary) hover:underline break-all">
                        <i class="ti ti-external-link shrink-0" />{{ customer.provisionedSubdomain }}
                    </a>
                    <button class="btn btn-ghost text-xxs" :disabled="copying" @click="copyUrl">
                        <i :class="copied ? 'ti ti-check' : 'ti ti-copy'" />{{ copied ? 'Copied' : 'Copy' }}
                    </button>
                </div>
                <div v-else class="mt-4 text-xs text-(--text-muted) italic">
                    No subdomain yet — provisioning happens on the first Sale Order confirm with a software line.
                </div>
            </section>

            <!-- Tenant product schedule (active subscriptions) -->
            <section>
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold flex items-center gap-2">
                        <i class="ti ti-package text-(--color-primary)" />
                        Tenant product schedule
                    </h3>
                    <span class="text-xxs text-(--text-muted)">{{ activeSubs.length }} active</span>
                </div>

                <div v-if="activeSubs.length === 0 && otherSubs.length === 0"
                    class="glass-card rounded-2xl p-8 text-center text-xs text-(--text-muted)">
                    No subscriptions yet. Confirm a Sale Order with software lines to seed one.
                </div>

                <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div v-for="sub in activeSubs" :key="sub.id" class="glass-card rounded-2xl p-5 space-y-3">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-xs text-(--text-muted)">{{ sub.subscriptionNumber }}</p>
                                <p class="text-sm font-semibold text-(--text-heading) mt-0.5 truncate">
                                    {{ sub.items[0]?.productName || 'Subscription' }}
                                </p>
                            </div>
                            <SubscriptionCountdown :end-date="sub.endDate" />
                        </div>

                        <dl class="grid grid-cols-3 gap-2 text-xxs">
                            <div>
                                <dt class="text-(--text-muted) uppercase tracking-widest font-bold">Cycle</dt>
                                <dd class="capitalize text-(--text-body) mt-0.5">{{ sub.billingCycle.replace('_', ' ') }}</dd>
                            </div>
                            <div>
                                <dt class="text-(--text-muted) uppercase tracking-widest font-bold">Total</dt>
                                <dd class="font-mono text-(--color-primary) font-semibold mt-0.5">{{ fmt(sub.totalAmount) }}</dd>
                            </div>
                            <div>
                                <dt class="text-(--text-muted) uppercase tracking-widest font-bold">Ends</dt>
                                <dd class="text-(--text-body) mt-0.5">{{ sub.endDate || '—' }}</dd>
                            </div>
                        </dl>

                        <div class="pt-2 border-t border-(--border-color) flex flex-wrap gap-2">
                            <button class="btn btn-primary text-xxs" :disabled="acting"
                                @click="openRenew(sub)">
                                <i class="ti ti-refresh" />Renew
                            </button>
                            <button class="btn btn-soft-primary text-xxs" :disabled="acting"
                                @click="openChangePlan(sub, 'upgrade')">
                                <i class="ti ti-arrow-up" />Upgrade
                            </button>
                            <button class="btn btn-ghost text-xxs" :disabled="acting"
                                @click="openChangePlan(sub, 'downgrade')">
                                <i class="ti ti-arrow-down" />Downgrade
                            </button>
                            <button
                                class="btn text-xxs text-(--color-danger) border border-(--color-danger)/20 hover:bg-(--color-danger-subtle)"
                                :disabled="acting" @click="openCancel(sub)">
                                <i class="ti ti-ban" />Cancel
                            </button>
                            <NuxtLink :to="`/sales/subscriptions/${sub.id}`" class="btn btn-ghost text-xxs ml-auto">
                                <i class="ti ti-eye" />Detail
                            </NuxtLink>
                        </div>
                    </div>

                    <!-- Expired / cancelled — collapsed view -->
                    <div v-for="sub in otherSubs" :key="sub.id" class="glass-card rounded-2xl p-5 space-y-3 opacity-75">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="text-xs text-(--text-muted)">{{ sub.subscriptionNumber }}</p>
                                <p class="text-sm font-semibold text-(--text-heading) mt-0.5 truncate">
                                    {{ sub.items[0]?.productName || 'Subscription' }}
                                </p>
                            </div>
                            <Badge :variant="statusBadgeVariant(sub.status)">{{ sub.status }}</Badge>
                        </div>
                        <div class="pt-2 border-t border-(--border-color) flex flex-wrap gap-2">
                            <button v-if="sub.status === 'expired'" class="btn btn-primary text-xxs"
                                :disabled="acting" @click="openRenew(sub)">
                                <i class="ti ti-refresh" />Renew
                            </button>
                            <NuxtLink :to="`/sales/subscriptions/${sub.id}`" class="btn btn-ghost text-xxs ml-auto">
                                <i class="ti ti-eye" />Detail
                            </NuxtLink>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Renew modal -->
        <div v-if="activeModal === 'renew' && modalSub"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3>Renew {{ modalSub.subscriptionNumber }}</h3>
                    <button class="w-8 h-8 rounded-full hover:bg-(--bg-muted)" @click="closeModal">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <p class="text-xs text-(--text-muted)">Extends the end date by one cycle and issues a renewal invoice.</p>
                    <div>
                        <label class="form-label">Billing cycle</label>
                        <select v-model="renewCycle" class="form-control">
                            <option value="">Keep current ({{ modalSub.billingCycle }})</option>
                            <option value="monthly">Monthly</option>
                            <option value="annual">Annual</option>
                        </select>
                    </div>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button class="btn btn-ghost text-xs" @click="closeModal">Close</button>
                    <button class="btn btn-primary text-xs" :disabled="acting" @click="renew">
                        <i class="ti ti-refresh" />Renew now
                    </button>
                </footer>
            </div>
        </div>

        <!-- Change plan modal -->
        <div v-if="activeModal === 'changePlan' && modalSub"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-lg bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3>{{ changePlanAction === 'upgrade' ? 'Upgrade' : 'Downgrade' }} {{ modalSub.subscriptionNumber }}</h3>
                    <button class="w-8 h-8 rounded-full hover:bg-(--bg-muted)" @click="closeModal">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <p class="text-xs text-(--text-muted)">
                        {{ changePlanAction === 'upgrade'
                            ? 'Bills the price delta immediately.'
                            : 'Emits a credit (negative invoice) to apply on the next cycle.' }}
                    </p>
                    <div>
                        <label class="form-label">Replace line</label>
                        <select v-model="changePlanTargetId" class="form-control">
                            <option v-for="line in modalSub.items" :key="line.id" :value="line.productId">
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
                    <button class="btn btn-ghost text-xs" @click="closeModal">Close</button>
                    <button class="btn btn-primary text-xs" :disabled="acting || !changePlanProductId" @click="changePlan">
                        <i :class="changePlanAction === 'upgrade' ? 'ti ti-arrow-up' : 'ti ti-arrow-down'" />
                        {{ changePlanAction === 'upgrade' ? 'Upgrade' : 'Downgrade' }}
                    </button>
                </footer>
            </div>
        </div>

        <!-- Cancel modal -->
        <div v-if="activeModal === 'cancel' && modalSub"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3>Cancel {{ modalSub.subscriptionNumber }}</h3>
                    <button class="w-8 h-8 rounded-full hover:bg-(--bg-muted)" @click="closeModal">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <p class="text-xs text-(--text-muted)">Tenant database stays in place. Deprovisioning is a separate concern.</p>
                    <input v-model="cancelReason" type="text" maxlength="500" placeholder="Reason (optional)"
                        class="form-control text-xs" />
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button class="btn btn-ghost text-xs" @click="closeModal">Keep active</button>
                    <button class="btn btn-danger text-xs" :disabled="acting" @click="cancel">
                        <i class="ti ti-ban" />Cancel subscription
                    </button>
                </footer>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useSales, statusBadgeVariant } from '~/composables/useSales'
import { useToast } from '~/composables/useToast'
import { useBreadcrumbOverride } from '~/composables/useBreadcrumbOverride'
import type { BillingCycle, Customer, ProductLite, Subscription } from '~/types/sales'
import SubscriptionCountdown from '~/components/sales/SubscriptionCountdown.vue'

const route = useRoute()
const sales = useSales()
const toast = useToast()
const crumb = useBreadcrumbOverride()

const customer = ref<Customer | null>(null)
const subs = ref<Subscription[]>([])
const catalogue = ref<ProductLite[]>([])
const loading = ref(true)
const acting = ref(false)

const activeModal = ref<'renew' | 'changePlan' | 'cancel' | null>(null)
const modalSub = ref<Subscription | null>(null)

const renewCycle = ref<'' | BillingCycle>('')
const changePlanAction = ref<'upgrade' | 'downgrade'>('upgrade')
const changePlanProductId = ref('')
const changePlanTargetId = ref('')
const cancelReason = ref('')

const copying = ref(false)
const copied = ref(false)

const activeSubs = computed(() => subs.value.filter((s) => s.status === 'active'))
const otherSubs = computed(() => subs.value.filter((s) => s.status !== 'active'))

const fmt = (v: number) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v || 0)

const load = async () => {
    loading.value = true
    try {
        const id = route.params.id as string
        const [custRes, subsRes] = await Promise.all([
            sales.customers.show(id),
            sales.subscriptions.list({ customer_id: id, limit: 50 }),
        ])
        customer.value = custRes.data
        subs.value = subsRes.data
        crumb.setEntityName(`${customer.value.name} — Account`)
    } catch (err: any) {
        toast.error('Failed to load account', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const loadCatalogue = async () => {
    if (catalogue.value.length > 0) return
    try {
        const res = await sales.catalogue.listProducts()
        catalogue.value = res.data.filter((p) => p.product_type === 'software' && p.is_active)
    } catch {
        // non-fatal
    }
}

const copyUrl = async () => {
    if (!customer.value?.provisionedSubdomain) return
    copying.value = true
    try {
        await navigator.clipboard.writeText(`https://${customer.value.provisionedSubdomain}`)
        copied.value = true
        setTimeout(() => { copied.value = false }, 2000)
    } catch {
        toast.error('Copy failed')
    } finally {
        copying.value = false
    }
}

const closeModal = () => {
    activeModal.value = null
    modalSub.value = null
    renewCycle.value = ''
    changePlanProductId.value = ''
    changePlanTargetId.value = ''
    cancelReason.value = ''
}

const openRenew = (sub: Subscription) => {
    modalSub.value = sub
    activeModal.value = 'renew'
}

const openChangePlan = (sub: Subscription, action: 'upgrade' | 'downgrade') => {
    modalSub.value = sub
    changePlanAction.value = action
    changePlanTargetId.value = sub.items[0]?.productId || ''
    activeModal.value = 'changePlan'
    loadCatalogue()
}

const openCancel = (sub: Subscription) => {
    modalSub.value = sub
    activeModal.value = 'cancel'
}

const replaceSubInList = (updated: Subscription) => {
    const idx = subs.value.findIndex((s) => s.id === updated.id)
    if (idx !== -1) subs.value[idx] = updated
}

const renew = async () => {
    if (!modalSub.value) return
    acting.value = true
    try {
        const res = await sales.subscriptions.renew(
            modalSub.value.id,
            renewCycle.value ? { cycle: renewCycle.value as BillingCycle } : {},
        )
        replaceSubInList(res.data)
        closeModal()
        toast.success('Subscription renewed', 'Renewal invoice issued.')
    } catch (err: any) {
        toast.error('Renew failed', err?.data?.message)
    } finally {
        acting.value = false
    }
}

const changePlan = async () => {
    if (!modalSub.value || !changePlanProductId.value) return
    acting.value = true
    try {
        const res = await sales.subscriptions.changePlan(modalSub.value.id, {
            product_id: changePlanProductId.value,
            target_product_id: changePlanTargetId.value || null,
            action: changePlanAction.value,
        })
        replaceSubInList(res.data)
        closeModal()
        toast.success(changePlanAction.value === 'upgrade' ? 'Upgrade applied' : 'Downgrade applied')
    } catch (err: any) {
        toast.error('Plan change failed', err?.data?.message)
    } finally {
        acting.value = false
    }
}

const cancel = async () => {
    if (!modalSub.value) return
    acting.value = true
    try {
        const res = await sales.subscriptions.cancel(modalSub.value.id, cancelReason.value || undefined)
        replaceSubInList(res.data)
        closeModal()
        toast.success('Subscription cancelled')
    } catch (err: any) {
        toast.error('Cancel failed', err?.data?.message)
    } finally {
        acting.value = false
    }
}

onMounted(load)
onBeforeUnmount(() => crumb.clear())
</script>
