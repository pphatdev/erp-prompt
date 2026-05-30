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
                        Customer: <span class="text-(--text-body)">{{ quote.customer?.name || 'Prospect — no account yet' }}</span>
                    </p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button v-if="quote.status === 'draft'" class="btn btn-primary text-xs" :disabled="acting"
                        @click="win">
                        <i class="ti ti-trophy" />Mark won
                    </button>
                    <button v-if="quote.status === 'draft'"
                        class="btn text-xs text-(--color-danger) border border-(--color-danger)/20 hover:bg-(--color-danger-subtle)"
                        :disabled="acting" @click="showLose = true">
                        <i class="ti ti-mood-sad" />Mark lost
                    </button>
                    <NuxtLink v-if="quote.orderId" class="btn btn-soft-primary text-xs"
                        :to="`/sales/orders/${quote.orderId}`">
                        <i class="ti ti-link" />Open Sales Order
                    </NuxtLink>
                </div>
            </header>

            <div v-if="quote.status === 'draft'"
                class="px-4 py-3 rounded-lg bg-(--color-info-subtle) text-(--color-info) text-xs">
                <i class="ti ti-info-circle" />
                Marking <strong>won</strong> converts the linked Lead into a Customer (if new) and auto-creates a draft Sale Order.
                Marking <strong>lost</strong> requires a reason and closes the Lead as unqualified.
            </div>

            <!-- Summary tiles -->
            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Subtotal</p>
                    <p class="text-base font-semibold text-(--text-heading) mt-1">
                        <CountUp :value="quote.subtotal" currency="USD" />
                    </p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Tax</p>
                    <p class="text-base font-semibold text-(--text-heading) mt-1">
                        <CountUp :value="quote.taxAmount" currency="USD" />
                    </p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Total</p>
                    <p class="text-base font-semibold text-(--color-primary) mt-1">
                        <CountUp :value="quote.totalAmount" currency="USD" />
                    </p>
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
            <section v-if="quote.wonAt || quote.lostAt || quote.notes" class="glass-card rounded-2xl p-5">
                <h3 class="text-sm font-semibold mb-3 flex items-center gap-2">
                    <i class="ti ti-history text-(--color-primary)" />
                    Trail
                </h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                    <div v-if="quote.wonAt">
                        <dt class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Won</dt>
                        <dd>{{ formatDate(quote.wonAt) }}</dd>
                    </div>
                    <div v-if="quote.lostAt">
                        <dt class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Lost</dt>
                        <dd>{{ formatDate(quote.lostAt) }}</dd>
                        <dd v-if="quote.lossReason" class="text-(--text-muted) italic">"{{ quote.lossReason }}"</dd>
                    </div>
                    <div v-if="quote.notes" class="sm:col-span-2">
                        <dt class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Notes</dt>
                        <dd class="whitespace-pre-line">{{ quote.notes }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <!-- Lose reason modal -->
        <div v-if="showLose"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3>Mark quotation lost</h3>
                    <button class="w-8 h-8 rounded-full hover:bg-(--bg-muted)" @click="showLose = false"><i
                            class="ti ti-x" /></button>
                </header>
                <div class="p-5 space-y-3">
                    <p class="text-xs text-(--text-muted)">Captured on the originating Lead for funnel analytics. Required.</p>
                    <input v-model="lossReason" type="text" maxlength="1000" placeholder="Why was the deal lost?"
                        class="form-control text-xs" />
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button class="btn btn-ghost text-xs" @click="showLose = false">Keep draft</button>
                    <button class="btn btn-danger text-xs" :disabled="acting || !lossReason.trim()" @click="lose">
                        <i class="ti ti-mood-sad" />Mark lost
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
import CountUp from '~/components/CountUp.vue'

const route = useRoute()
const router = useRouter()
const sales = useSales()
const toast = useToast()

const quote = ref<Quotation | null>(null)
const loading = ref(true)
const acting = ref(false)
const showLose = ref(false)
const lossReason = ref('')

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

const win = async () => {
    if (!quote.value) return
    acting.value = true
    try {
        const res = await sales.quotations.win(quote.value.id)
        quote.value = res.data
        toast.success('Quotation won', 'Draft Sale Order created — open it to confirm fulfillment.')
        if (res.data.orderId) {
            router.push(`/sales/orders/${res.data.orderId}`)
        }
    } catch (err: any) {
        toast.error('Mark won failed', err?.data?.message)
    } finally {
        acting.value = false
    }
}

const lose = async () => {
    if (!quote.value || !lossReason.value.trim()) return
    acting.value = true
    try {
        const res = await sales.quotations.lose(quote.value.id, lossReason.value.trim())
        quote.value = res.data
        showLose.value = false
        toast.success('Quotation marked lost')
    } catch (err: any) {
        toast.error('Mark lost failed', err?.data?.message)
    } finally {
        acting.value = false
    }
}

onMounted(load)
</script>
