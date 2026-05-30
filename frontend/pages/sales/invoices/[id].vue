<template>
    <NuxtLayout name="default">
        <div v-if="loading" class="py-24 flex justify-center">
            <span
                class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
        </div>

        <div v-else-if="invoice" class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold">{{ invoice.invoiceNumber }}</h1>
                        <Badge :variant="statusBadgeVariant(invoice.status)">{{ invoice.status }}</Badge>
                    </div>
                    <p class="text-xs text-(--text-muted) mt-1">
                        Customer: <span class="text-(--text-body)">{{ invoice.customer?.name || '—' }}</span>
                        ·
                        <NuxtLink :to="`/sales/orders/${invoice.orderId}`"
                            class="text-(--color-primary) hover:underline">From order</NuxtLink>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button v-if="invoice.status === 'new'" class="btn btn-primary text-xs" :disabled="acting"
                        @click="confirm">
                        <i class="ti ti-check" />Confirm &amp; post to GL
                    </button>
                    <button v-if="invoice.status === 'new'"
                        class="btn text-xs text-(--color-danger) border border-(--color-danger)/20 hover:bg-(--color-danger-subtle)"
                        :disabled="acting" @click="showCancel = true">
                        <i class="ti ti-ban" />Cancel
                    </button>
                </div>
            </header>

            <div v-if="invoice.status === 'new'"
                class="px-4 py-3 rounded-lg bg-(--color-warning-subtle) text-(--color-warning) text-xs">
                <i class="ti ti-alert-triangle" />
                Confirming posts a balanced journal entry: <code class="font-mono">DR AR · CR Revenue · CR Tax</code>.
                Once posted, the invoice can no longer be cancelled directly — issue a credit note via FMS instead.
            </div>

            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Subtotal</p>
                    <p class="text-base font-semibold text-(--text-heading) mt-1">
                        <CountUp :value="invoice.subtotal" currency="USD" />
                    </p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Tax</p>
                    <p class="text-base font-semibold text-(--text-heading) mt-1">
                        <CountUp :value="invoice.taxAmount" currency="USD" />
                    </p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Total</p>
                    <p class="text-base font-semibold text-(--color-primary) mt-1">
                        <CountUp :value="invoice.totalAmount" currency="USD" />
                    </p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Paid</p>
                    <p class="text-base font-semibold text-(--text-heading) mt-1">
                        <CountUp :value="invoice.paidAmount" currency="USD" />
                    </p>
                </div>
            </section>

            <section v-if="invoice.journalEntryId" class="glass-card rounded-2xl p-5 flex items-center justify-between">
                <div>
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Journal entry</p>
                    <p class="text-sm font-mono font-semibold text-(--text-heading) mt-1">{{ invoice.journalEntryId }}
                    </p>
                    <p class="text-xxs text-(--text-muted) mt-0.5">AR posted; reverse via FMS credit-note flow.</p>
                </div>
                <i class="ti ti-book-2 text-2xl text-(--color-success)" />
            </section>

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
                                <th class="text-right px-2 py-2">Qty</th>
                                <th class="text-right px-2 py-2">Unit</th>
                                <th class="text-right px-5 py-2">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in invoice.items" :key="line.id"
                                class="border-b border-(--border-color)/40">
                                <td class="px-5 py-3 text-(--text-heading) font-medium">{{ line.productName }}</td>
                                <td class="px-2 py-3 capitalize">{{ line.productType || '—' }}</td>
                                <td class="px-2 py-3 text-right font-mono">{{ line.quantity }}</td>
                                <td class="px-2 py-3 text-right font-mono">{{ fmt(line.unitPrice) }}</td>
                                <td class="px-5 py-3 text-right font-mono font-semibold">{{ fmt(line.lineTotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Cancel modal -->
        <div v-if="showCancel"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3>Cancel invoice</h3>
                    <button class="w-8 h-8 rounded-full hover:bg-(--bg-muted)" @click="showCancel = false"><i
                            class="ti ti-x" /></button>
                </header>
                <div class="p-5 space-y-3">
                    <p class="text-xs text-(--text-muted)">Only invoices in <code class="font-mono">new</code> status
                        can be cancelled here. Confirmed invoices need a credit-note reversal via FMS.</p>
                    <input v-model="cancelReason" type="text" maxlength="500" placeholder="Reason (optional)"
                        class="form-control text-xs" />
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button class="btn btn-ghost text-xs" @click="showCancel = false">Keep open</button>
                    <button class="btn btn-danger text-xs" :disabled="acting" @click="cancel">
                        <i class="ti ti-ban" />Cancel invoice
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
import type { Invoice } from '~/types/sales'
import CountUp from '~/components/CountUp.vue'

const route = useRoute()
const sales = useSales()
const toast = useToast()

const invoice = ref<Invoice | null>(null)
const loading = ref(true)
const acting = ref(false)
const showCancel = ref(false)
const cancelReason = ref('')

const fmt = (v: number) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v || 0)

const load = async () => {
    loading.value = true
    try {
        const res = await sales.invoices.show(route.params.id as string)
        invoice.value = res.data
    } catch (err: any) {
        toast.error('Failed to load invoice', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const confirm = async () => {
    if (!invoice.value) return
    acting.value = true
    try {
        const res = await sales.invoices.confirm(invoice.value.id)
        invoice.value = res.data
        toast.success('Invoice confirmed', 'Balanced AR journal posted to GL.')
    } catch (err: any) {
        toast.error('Confirm failed', err?.data?.message)
    } finally {
        acting.value = false
    }
}

const cancel = async () => {
    if (!invoice.value) return
    acting.value = true
    try {
        const res = await sales.invoices.cancel(invoice.value.id, cancelReason.value || undefined)
        invoice.value = res.data
        showCancel.value = false
        toast.success('Invoice cancelled')
    } catch (err: any) {
        toast.error('Cancel failed', err?.data?.message)
    } finally {
        acting.value = false
    }
}

onMounted(load)
</script>
