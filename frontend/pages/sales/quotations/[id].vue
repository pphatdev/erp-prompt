<template>
    <NuxtLayout name="default">
        <div v-if="loading" class="py-24 flex justify-center">
            <span
                class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
        </div>

        <div v-else-if="quote" class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold">{{ quote.quoteNumber }}</h1>
                        <Badge :variant="statusBadgeVariant(quote.status)">{{ quote.status }}</Badge>
                    </div>
                    <p class="text-xs text-(--text-muted) mt-1">
                        Customer: <span class="text-(--text-body)">{{ quote.customer?.name || '—' }}</span>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button v-if="quote.status === 'new'" class="btn btn-primary text-xs" :disabled="acting"
                        @click="confirm">
                        <i class="ti ti-check" />Confirm quote
                    </button>
                    <button v-if="quote.status === 'confirmed' && !quote.orderId" class="btn btn-primary text-xs"
                        :disabled="acting" @click="convert">
                        <i class="ti ti-arrow-right" />Convert to order
                    </button>
                    <button v-if="quote.status !== 'cancelled' && !quote.orderId"
                        class="btn text-xs text-(--color-danger) border border-(--color-danger)/20 hover:bg-(--color-danger-subtle)"
                        :disabled="acting" @click="cancelConfirm">
                        <i class="ti ti-ban" />Cancel
                    </button>
                    <NuxtLink v-if="quote.orderId" class="btn btn-soft-primary text-xs"
                        :to="`/sales/orders/${quote.orderId}`">
                        <i class="ti ti-link" />Open Sales Order
                    </NuxtLink>
                </div>
            </header>

            <!-- Summary tiles -->
            <section class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Subtotal</p>
                    <p class="text-base font-semibold text-(--text-heading) mt-1">{{ fmt(quote.subtotal) }}</p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Tax</p>
                    <p class="text-base font-semibold text-(--text-heading) mt-1">{{ fmt(quote.taxAmount) }}</p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Total</p>
                    <p class="text-base font-semibold text-(--color-primary) mt-1">{{ fmt(quote.totalAmount) }}</p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Due</p>
                    <p class="text-base font-semibold text-(--text-heading) mt-1">{{ quote.dueDate || '—' }}</p>
                </div>
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
                            <tr v-for="line in quote.items" :key="line.id" class="border-b border-(--border-color)/40">
                                <td class="px-5 py-3 text-(--text-heading) font-medium">{{ line.productName }}</td>
                                <td class="px-2 py-3 capitalize">{{ line.productType }}</td>
                                <td class="px-2 py-3 font-mono text-xxs">{{ line.variantSku || '—' }}</td>
                                <td class="px-2 py-3 text-right font-mono">{{ line.quantity }}</td>
                                <td class="px-2 py-3 text-right font-mono">{{ fmt(line.unitPrice) }}</td>
                                <td class="px-5 py-3 text-right font-mono font-semibold">{{ fmt(line.lineTotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <!-- Audit trail -->
            <section v-if="quote.confirmedAt || quote.cancelledAt || quote.notes" class="glass-card rounded-2xl p-5">
                <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                    <i class="ti ti-history text-(--color-primary)" />
                    Trail
                </h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div v-if="quote.confirmedAt">
                        <dt class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Confirmed</dt>
                        <dd>{{ formatDate(quote.confirmedAt) }}</dd>
                    </div>
                    <div v-if="quote.cancelledAt">
                        <dt class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Cancelled</dt>
                        <dd>{{ formatDate(quote.cancelledAt) }}</dd>
                        <dd v-if="quote.cancelReason" class="text-(--text-muted) italic">"{{ quote.cancelReason }}"</dd>
                    </div>
                    <div v-if="quote.notes" class="sm:col-span-2">
                        <dt class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Notes</dt>
                        <dd class="whitespace-pre-line">{{ quote.notes }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <!-- Cancel reason prompt -->
        <div v-if="showCancel"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3>Cancel quotation</h3>
                    <button class="w-8 h-8 rounded-full hover:bg-(--bg-muted)" @click="showCancel = false"><i
                            class="ti ti-x" /></button>
                </header>
                <div class="p-5 space-y-3">
                    <p class="text-xs text-(--text-muted)">This will close the lead. The quote stays in the audit trail
                        but can't be confirmed afterwards.</p>
                    <input v-model="cancelReason" type="text" maxlength="500" placeholder="Reason (optional)"
                        class="form-control text-xs" />
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button class="btn btn-ghost text-xs" @click="showCancel = false">Keep open</button>
                    <button class="btn btn-danger text-xs" :disabled="acting" @click="cancel">
                        <i class="ti ti-ban" />Cancel quote
                    </button>
                </footer>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useSales, statusBadgeVariant } from '~/composables/useSales'
import { useToast } from '~/composables/useToast'
import type { Quotation } from '~/types/sales'

const route = useRoute()
const router = useRouter()
const sales = useSales()
const toast = useToast()

const quote = ref<Quotation | null>(null)
const loading = ref(true)
const acting = ref(false)
const showCancel = ref(false)
const cancelReason = ref('')

const fmt = (v: number) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v || 0)
const formatDate = (iso: string) => new Date(iso).toLocaleString()

const load = async () => {
    loading.value = true
    try {
        const res = await sales.quotations.show(route.params.id as string)
        quote.value = res.data
    } catch (err: any) {
        toast.error('Failed to load quotation', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const confirm = async () => {
    if (!quote.value) return
    acting.value = true
    try {
        const res = await sales.quotations.confirm(quote.value.id)
        quote.value = res.data
        toast.success('Quotation confirmed', 'Ready to convert into a Sales Order.')
    } catch (err: any) {
        toast.error('Confirm failed', err?.data?.message)
    } finally {
        acting.value = false
    }
}

const convert = async () => {
    if (!quote.value) return
    acting.value = true
    try {
        const res = await sales.quotations.convertToOrder(quote.value.id)
        toast.success('Sales Order created', res.data.orderNumber)
        router.push(`/sales/orders/${res.data.id}`)
    } catch (err: any) {
        toast.error('Conversion failed', err?.data?.message)
    } finally {
        acting.value = false
    }
}

const cancelConfirm = () => {
    cancelReason.value = ''
    showCancel.value = true
}

const cancel = async () => {
    if (!quote.value) return
    acting.value = true
    try {
        const res = await sales.quotations.cancel(quote.value.id, cancelReason.value || undefined)
        quote.value = res.data
        showCancel.value = false
        toast.success('Quotation cancelled')
    } catch (err: any) {
        toast.error('Cancel failed', err?.data?.message)
    } finally {
        acting.value = false
    }
}

onMounted(load)
</script>
