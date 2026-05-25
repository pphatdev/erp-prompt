<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Page header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Quotations</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Top of the hybrid sales funnel — quote → confirm →
                        convert to order.</p>
                </div>
                <NuxtLink to="/sales/quotations/new" class="btn btn-primary text-xs">
                    <i class="ti ti-file-plus" />New quotation
                </NuxtLink>
            </header>

            <!-- Filter strip -->
            <section class="glass-card rounded-xl p-4 flex flex-col md:flex-row gap-3 items-center justify-between">
                <div class="relative w-full md:w-80">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                    <input v-model="search" type="search" placeholder="Search quote # or customer..."
                        class="form-control pl-9" />
                </div>
                <div class="flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1">
                    <button v-for="s in (['all', 'new', 'confirmed', 'cancelled'] as const)" :key="s"
                        class="px-3 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
                        :class="filterStatus === s ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                        @click="filterStatus = s">{{ s }}</button>
                </div>
            </section>

            <!-- Loading / empty -->
            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span
                    class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading quotations...</span>
            </div>
            <div v-else-if="filtered.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-file-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No quotations</h4>
                <p class="text-xs text-(--text-muted) mt-1">Start a new quote from the button above.</p>
            </div>

            <!-- Card grid -->
            <section v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <NuxtLink v-for="q in filtered" :key="q.id" :to="`/sales/quotations/${q.id}`"
                    class="glass-card rounded-2xl p-5 flex flex-col gap-3 group hover:border-(--color-primary)/40 transition-colors">
                    <header class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3
                                class="text-sm font-semibold text-(--text-heading) truncate group-hover:text-(--color-primary)">
                                {{ q.quoteNumber }}
                            </h3>
                            <p class="text-xxs text-(--text-muted) truncate mt-0.5">
                                {{ q.customer?.name || '—' }}
                            </p>
                        </div>
                        <Badge :variant="statusBadgeVariant(q.status)">{{ q.status }}</Badge>
                    </header>
                    <div class="flex items-end justify-between mt-auto pt-3 border-t border-(--border-color)">
                        <div>
                            <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Total</p>
                            <p class="text-base font-semibold text-(--text-heading)">{{ fmt(q.totalAmount) }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Due</p>
                            <p class="text-xs text-(--text-body)">{{ q.dueDate || '—' }}</p>
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
import type { Quotation } from '~/types/sales'

const sales = useSales()
const toast = useToast()

const loading = ref(false)
const quotes = ref<Quotation[]>([])

const search = ref('')
const filterStatus = ref<'all' | 'new' | 'confirmed' | 'cancelled'>('all')

const filtered = computed(() => quotes.value.filter(q => {
    const matchSearch = !search.value ||
        q.quoteNumber.toLowerCase().includes(search.value.toLowerCase()) ||
        (q.customer?.name?.toLowerCase().includes(search.value.toLowerCase()) ?? false)
    const matchStatus = filterStatus.value === 'all' || q.status === filterStatus.value
    return matchSearch && matchStatus
}))

const fmt = (v: number) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v || 0)

const load = async () => {
    loading.value = true
    try {
        const res = await sales.quotations.list({ limit: 50 })
        quotes.value = res.data
    } catch (err: any) {
        toast.error('Failed to load quotations', err?.data?.message)
    } finally {
        loading.value = false
    }
}

onMounted(load)
</script>
