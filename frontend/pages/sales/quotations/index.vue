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
                <div class="flex items-center gap-2">
                    <div class="flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1">
                        <button v-for="s in (['all', 'draft', 'won', 'lost'] as const)" :key="s"
                            class="px-3 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
                            :class="filterStatus === s ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                            @click="filterStatus = s">{{ s }}</button>
                    </div>
                    <!-- View toggle (grid / list) — persisted in localStorage. -->
                    <div class="flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1 shrink-0">
                        <button v-for="v in (['grid', 'list'] as const)" :key="v" type="button"
                            class="px-2 py-1 rounded text-xxs inline-flex items-center gap-1 transition-colors"
                            :class="viewMode === v ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                            :title="v === 'grid' ? 'Grid view' : 'List view'"
                            @click="setViewMode(v)">
                            <i :class="['ti text-base', v === 'grid' ? 'ti-layout-grid' : 'ti-list-details']" />
                        </button>
                    </div>
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

            <!-- Grid view -->
            <section v-else-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <NuxtLink v-for="q in filtered" :key="q.id" :to="`/sales/quotations/${q.id}`"
                    class="glass-card rounded-2xl p-5 flex flex-col gap-3 group hover:border-(--color-primary)/40 transition-colors">
                    <header class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3
                                class="text-sm font-semibold text-(--text-heading) truncate group-hover:text-(--color-primary)">
                                {{ q.quoteNumber }}
                            </h3>
                            <p class="text-xxs text-(--text-muted) truncate mt-0.5">
                                {{ q.customer?.name || (q.fromOpportunityId ? 'From Opportunity' : '—') }}
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

            <!-- List view -->
            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-(--bg-muted) text-xxs uppercase tracking-widest font-bold text-(--text-muted)">
                            <tr>
                                <th class="text-left px-4 py-3">Quote #</th>
                                <th class="text-left px-2 py-3 hidden md:table-cell">Customer</th>
                                <th class="text-left px-2 py-3 hidden lg:table-cell">Quote date</th>
                                <th class="text-left px-2 py-3 hidden lg:table-cell">Due</th>
                                <th class="text-right px-2 py-3">Total</th>
                                <th class="text-left px-2 py-3">Status</th>
                                <th class="text-right px-4 py-3 w-16"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="q in filtered" :key="q.id"
                                class="border-t border-(--border-color)/60 hover:bg-(--bg-muted)/40 transition-colors cursor-pointer"
                                @click="$router.push(`/sales/quotations/${q.id}`)">
                                <td class="px-4 py-3">
                                    <p class="font-mono font-semibold text-(--text-heading)">{{ q.quoteNumber }}</p>
                                    <p v-if="q.fromOpportunityId" class="text-xxs text-(--text-muted) mt-0.5 inline-flex items-center gap-1">
                                        <i class="ti ti-target" />From Opportunity
                                    </p>
                                </td>
                                <td class="px-2 py-3 hidden md:table-cell">
                                    <span v-if="q.customer?.name" class="text-(--text-body)">{{ q.customer.name }}</span>
                                    <span v-else class="text-(--text-muted) italic">No account yet</span>
                                </td>
                                <td class="px-2 py-3 hidden lg:table-cell font-mono text-(--text-body)">
                                    {{ q.quoteDate || '—' }}
                                </td>
                                <td class="px-2 py-3 hidden lg:table-cell font-mono text-(--text-body)">
                                    {{ q.dueDate || '—' }}
                                </td>
                                <td class="px-2 py-3 text-right font-mono font-semibold text-(--text-heading)">
                                    {{ fmt(q.totalAmount) }}
                                </td>
                                <td class="px-2 py-3">
                                    <Badge :variant="statusBadgeVariant(q.status)">{{ q.status }}</Badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <NuxtLink :to="`/sales/quotations/${q.id}`" class="action-btn" title="Open"
                                        @click.stop>
                                        <i class="ti ti-arrow-up-right" />
                                    </NuxtLink>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
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
const filterStatus = ref<'all' | 'draft' | 'won' | 'lost'>('all')

// View toggle — persisted in localStorage so the user's choice survives
// reloads and tenant switches.
type QuotationsViewMode = 'grid' | 'list'
const VIEW_STORAGE_KEY = 'sales.quotations.view'
const viewMode = ref<QuotationsViewMode>(
    (typeof window !== 'undefined' && (localStorage.getItem(VIEW_STORAGE_KEY) as QuotationsViewMode)) || 'grid'
)
const setViewMode = (mode: QuotationsViewMode) => {
    viewMode.value = mode
    if (typeof window !== 'undefined') localStorage.setItem(VIEW_STORAGE_KEY, mode)
}

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

<style scoped>
.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    color: var(--text-body);
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
.action-btn:hover {
    background: var(--bg-muted);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}
</style>
