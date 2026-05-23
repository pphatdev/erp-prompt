<template>
  <NuxtLayout name="default">
    <div class="space-y-6">
      <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
          <h1 class="text-xl font-semibold">Invoices</h1>
          <p class="text-xs text-(--text-muted) mt-1">Confirming an invoice posts the balanced AR journal to the GL.</p>
        </div>
      </header>

      <section class="glass-card rounded-xl p-4 flex flex-col md:flex-row gap-3 items-center justify-between">
        <div class="relative w-full md:w-80">
          <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
          <input v-model="search" type="search" placeholder="Search invoice # or customer..." class="form-control pl-9" />
        </div>
        <div class="flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1">
          <button v-for="s in (['all','new','confirmed','paid','cancelled'] as const)" :key="s"
            class="px-3 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
            :class="filterStatus === s ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
            @click="filterStatus = s">{{ s }}</button>
        </div>
      </section>

      <div v-if="loading" class="py-24 flex justify-center">
        <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
      </div>
      <div v-else-if="filtered.length === 0" class="glass-card rounded-2xl py-20 text-center">
        <i class="ti ti-receipt-off text-4xl text-(--text-muted)" />
        <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No invoices</h4>
        <p class="text-xs text-(--text-muted) mt-1">Invoices are created automatically when a Sales Order is confirmed.</p>
      </div>

      <section v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <NuxtLink v-for="inv in filtered" :key="inv.id" :to="`/sales/invoices/${inv.id}`"
          class="glass-card rounded-2xl p-5 flex flex-col gap-3 group hover:border-(--color-primary)/40 transition-colors">
          <header class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <h3 class="text-sm font-semibold text-(--text-heading) truncate group-hover:text-(--color-primary)">
                {{ inv.invoiceNumber }}
              </h3>
              <p class="text-xxs text-(--text-muted) truncate mt-0.5">{{ inv.customer?.name || '—' }}</p>
            </div>
            <Badge :variant="statusBadgeVariant(inv.status)">{{ inv.status }}</Badge>
          </header>

          <div v-if="inv.journalEntryId" class="text-xxs">
            <span class="px-2 py-0.5 rounded badge-soft-success font-mono">
              <i class="ti ti-book-2" /> Posted to GL
            </span>
          </div>

          <div class="flex items-end justify-between mt-auto pt-3 border-t border-(--border-color)">
            <div>
              <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Total</p>
              <p class="text-base font-semibold text-(--text-heading)">{{ fmt(inv.totalAmount) }}</p>
            </div>
            <div class="text-right">
              <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Due</p>
              <p class="text-xs text-(--text-body)">{{ inv.dueDate || '—' }}</p>
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
import type { Invoice } from '~/types/sales'

const sales = useSales()
const toast = useToast()

const loading = ref(false)
const invoices = ref<Invoice[]>([])
const search = ref('')
const filterStatus = ref<'all' | 'new' | 'confirmed' | 'paid' | 'cancelled'>('all')

const filtered = computed(() => invoices.value.filter(i => {
  const matchSearch = !search.value ||
    i.invoiceNumber.toLowerCase().includes(search.value.toLowerCase()) ||
    (i.customer?.name?.toLowerCase().includes(search.value.toLowerCase()) ?? false)
  const matchStatus = filterStatus.value === 'all' || i.status === filterStatus.value
  return matchSearch && matchStatus
}))

const fmt = (v: number) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v || 0)

onMounted(async () => {
  loading.value = true
  try {
    const res = await sales.invoices.list({ limit: 50 })
    invoices.value = res.data
  } catch (err: any) {
    toast.error('Failed to load invoices', err?.data?.message)
  } finally {
    loading.value = false
  }
})
</script>
