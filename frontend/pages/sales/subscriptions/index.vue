<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Subscriptions</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Software-fulfillment side of a Sales Order. Confirming
                        dispatches the tenant-provisioning event.</p>
                </div>
            </header>

            <section class="glass-card rounded-xl p-4 flex flex-col md:flex-row gap-3 items-center justify-between">
                <div class="relative w-full md:w-80">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                    <input v-model="search" type="search" placeholder="Search subscription # or customer..."
                        class="form-control pl-9" />
                </div>
                <div class="flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1">
                    <button v-for="s in (['all', 'active', 'expired', 'cancelled'] as const)" :key="s"
                        class="px-3 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
                        :class="filterStatus === s ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                        @click="filterStatus = s">{{ s }}</button>
                </div>
            </section>

            <div v-if="loading" class="py-24 flex justify-center">
                <span
                    class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
            </div>
            <div v-else-if="filtered.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-cloud-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No subscriptions</h4>
                <p class="text-xs text-(--text-muted) mt-1">Subscriptions are created automatically when a confirmed
                    order contains software lines.</p>
            </div>

            <section v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <NuxtLink v-for="s in filtered" :key="s.id" :to="`/sales/subscriptions/${s.id}`"
                    class="glass-card rounded-2xl p-5 pb-4 flex flex-col gap-3 group hover:border-(--color-primary)/40 transition-all duration-150 relative overflow-hidden min-h-[160px]">
                    
                    <!-- Glowing shape behind card -->
                    <div class="absolute -right-8 -top-8 w-20 h-20 rounded-full bg-(--color-primary)/10 blur-xl pointer-events-none group-hover:scale-150 transition-transform duration-500" />

                    <div class="space-y-3 relative z-10">
                        <header class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3
                                    class="text-sm font-semibold text-(--text-heading) truncate group-hover:text-(--color-primary) transition-colors">
                                    {{ s.subscriptionNumber }}
                                </h3>
                                <p class="text-xxs text-(--text-muted) truncate mt-0.5">{{ s.customer?.name || '—' }}</p>
                            </div>
                            <Badge :variant="statusBadgeVariant(s.status)">{{ s.status }}</Badge>
                        </header>

                        <div class="flex items-center gap-2 text-xxs">
                            <span class="px-2 py-0.5 rounded badge-soft-secondary font-mono uppercase tracking-widest">{{
                                s.billingCycle }}</span>
                            <span v-if="s.provisionedTenantId" class="px-2 py-0.5 rounded badge-soft-success">
                                <i class="ti ti-cloud-check" /> Provisioned
                            </span>
                        </div>
                    </div>

                    <div class="flex items-end justify-between mt-auto pt-3 border-t border-(--border-color)/50 relative z-10">
                        <div>
                            <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Total</p>
                            <p class="text-base font-semibold text-(--text-heading)">{{ fmt(s.totalAmount) }}</p>
                        </div>
                        
                        <!-- Hover action replaces date info -->
                        <div class="relative h-9 flex items-center justify-end">
                            <div class="absolute right-0 flex items-center gap-1.5 transition-all duration-300 opacity-0 group-hover:opacity-100 translate-x-2 group-hover:translate-x-0">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-(--color-primary)">Open details</span>
                                <div class="w-6 h-6 rounded-full bg-(--color-primary)/10 text-(--color-primary) flex items-center justify-center shrink-0">
                                    <i class="ti ti-arrow-right text-xs"></i>
                                </div>
                            </div>
                            <div class="text-right transition-all duration-300 opacity-100 group-hover:opacity-0 group-hover:translate-x-[-8px]">
                                <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Start</p>
                                <p class="text-xs text-(--text-body)">{{ formatDate(s.startDate) }}</p>
                            </div>
                        </div>
                    </div>
                </NuxtLink>
            </section>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useSales, statusBadgeVariant } from '~/composables/useSales'
import { useToast } from '~/composables/useToast'
import { useDateFormat } from '~/composables/useDateFormat'
import type { Subscription } from '~/types/sales'

const sales = useSales()
const toast = useToast()
const { formatDate } = useDateFormat()

const loading = ref(false)
const subs = ref<Subscription[]>([])
const search = ref('')
const filterStatus = ref<'all' | 'active' | 'expired' | 'cancelled'>('all')

const filtered = computed(() => subs.value.filter(s => {
    const matchSearch = !search.value ||
        s.subscriptionNumber.toLowerCase().includes(search.value.toLowerCase()) ||
        (s.customer?.name?.toLowerCase().includes(search.value.toLowerCase()) ?? false)
    const matchStatus = filterStatus.value === 'all' || s.status === filterStatus.value
    return matchSearch && matchStatus
}))

const fmt = (v: number) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v || 0)

onMounted(async () => {
    loading.value = true
    try {
        const res = await sales.subscriptions.list({ limit: 50 })
        subs.value = res.data
    } catch (err: any) {
        toast.error('Failed to load subscriptions', err?.data?.message)
    } finally {
        loading.value = false
    }
})
</script>
