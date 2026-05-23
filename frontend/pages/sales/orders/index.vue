<template>
  <NuxtLayout name="default">
    <div class="space-y-6">
      <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
          <h1 class="text-xl font-semibold">Sales orders</h1>
          <p class="text-xs text-(--text-muted) mt-1">Confirmed quotes become orders. Confirming an order fans out into Invoice + Subscription + Stock deductions.</p>
        </div>
        <NuxtLink to="/sales/quotations" class="btn btn-soft-primary text-xs">
          <i class="ti ti-file-text" />New quotation
        </NuxtLink>
      </header>

      <section class="glass-card rounded-xl p-4 flex flex-col md:flex-row gap-3 items-center justify-between">
        <div class="relative w-full md:w-80">
          <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
          <input v-model="search" type="search" placeholder="Search order # or customer..." class="form-control pl-9" />
        </div>
        <div class="flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1">
          <button v-for="s in (['all','new','confirmed','cancelled'] as const)" :key="s"
            class="px-3 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
            :class="filterStatus === s ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
            @click="filterStatus = s">{{ s }}</button>
        </div>
      </section>

      <div v-if="loading" class="py-24 flex justify-center">
        <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
      </div>
      <div v-else-if="filtered.length === 0" class="glass-card rounded-2xl py-20 text-center">
        <i class="ti ti-shopping-cart-off text-4xl text-(--text-muted)" />
        <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No sales orders</h4>
        <p class="text-xs text-(--text-muted) mt-1">Convert a confirmed quotation to create one.</p>
      </div>

      <section v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <NuxtLink v-for="o in filtered" :key="o.id" :to="`/sales/orders/${o.id}`"
          class="glass-card rounded-2xl p-5 flex flex-col gap-3 group hover:border-(--color-primary)/40 transition-colors">
          <header class="flex items-start justify-between gap-3">
            <div class="min-w-0">
              <h3 class="text-sm font-semibold text-(--text-heading) truncate group-hover:text-(--color-primary)">
                {{ o.orderNumber }}
              </h3>
              <p class="text-xxs text-(--text-muted) truncate mt-0.5">
                {{ o.customer?.name || '—' }}
              </p>
            </div>
            <Badge :variant="statusBadgeVariant(o.status)">{{ o.status }}</Badge>
          </header>

          <div v-if="o.invoiceId || o.subscriptionId" class="flex flex-wrap gap-1.5 text-xxs">
            <span v-if="o.invoiceId" class="px-2 py-0.5 rounded badge-soft-info"><i class="ti ti-receipt" /> Invoice</span>
            <span v-if="o.subscriptionId" class="px-2 py-0.5 rounded badge-soft-primary"><i class="ti ti-cloud" /> Subscription</span>
          </div>

          <div class="flex items-end justify-between mt-auto pt-3 border-t border-(--border-color)">
            <div>
              <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Total</p>
              <p class="text-base font-semibold text-(--text-heading)">{{ fmt(o.totalAmount) }}</p>
            </div>
            <div class="text-right">
              <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Due</p>
              <p class="text-xs text-(--text-body)">{{ o.dueDate || '—' }}</p>
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
import type { Order } from '~/types/sales'

const sales = useSales()
const toast = useToast()

const loading = ref(false)
const orders = ref<Order[]>([])
const search = ref('')
const filterStatus = ref<'all' | 'new' | 'confirmed' | 'cancelled'>('all')

const filtered = computed(() => orders.value.filter(o => {
  const matchSearch = !search.value ||
    o.orderNumber.toLowerCase().includes(search.value.toLowerCase()) ||
    (o.customer?.name?.toLowerCase().includes(search.value.toLowerCase()) ?? false)
  const matchStatus = filterStatus.value === 'all' || o.status === filterStatus.value
  return matchSearch && matchStatus
}))

const fmt = (v: number) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v || 0)

const load = async () => {
  loading.value = true
  try {
    const res = await sales.orders.list({ limit: 50 })
    orders.value = res.data
  } catch (err: any) {
    toast.error('Failed to load orders', err?.data?.message)
  } finally {
    loading.value = false
  }
}

onMounted(load)
</script>
