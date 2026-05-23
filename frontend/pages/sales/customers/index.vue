<template>
  <NuxtLayout name="default">
    <div class="space-y-6">
      <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
          <h1 class="text-xl font-semibold">Customers</h1>
          <p class="text-xs text-(--text-muted) mt-1">Anchor of the sales funnel — every quote, order, invoice and subscription belongs to one.</p>
        </div>
        <NuxtLink to="/sales/customers/new" class="btn btn-primary text-xs">
          <i class="ti ti-user-plus" />New customer
        </NuxtLink>
      </header>

      <!-- Metrics row -->
      <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="glass-card rounded-xl p-4">
          <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Total</p>
          <p class="text-xl font-semibold text-(--text-heading) mt-1">{{ customers.length }}</p>
        </div>
        <div class="glass-card rounded-xl p-4">
          <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Tenant customers</p>
          <p class="text-xl font-semibold text-(--color-primary) mt-1">{{ tenantCount }}</p>
        </div>
        <div class="glass-card rounded-xl p-4">
          <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Provisioned</p>
          <p class="text-xl font-semibold text-(--color-success) mt-1">{{ provisionedCount }}</p>
        </div>
        <div class="glass-card rounded-xl p-4">
          <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Enterprise tier</p>
          <p class="text-xl font-semibold text-(--text-heading) mt-1">{{ enterpriseCount }}</p>
        </div>
      </section>

      <!-- Filters -->
      <section class="glass-card rounded-xl p-4 flex flex-col md:flex-row gap-3 items-center justify-between">
        <div class="relative w-full md:w-96">
          <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
          <input v-model="search" type="search" placeholder="Search name, email, company, external code..." class="form-control pl-9" />
        </div>
        <div class="flex flex-col sm:flex-row gap-2 items-center w-full md:w-auto">
          <select v-model="filterType" class="form-control text-xs py-1.5">
            <option value="all">All types</option>
            <option value="individual">Individual</option>
            <option value="business">Business</option>
            <option value="tenant">Tenant</option>
          </select>
          <div class="flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1 shrink-0">
            <button v-for="s in (['all','active','inactive'] as const)" :key="s" type="button"
              class="px-3 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
              :class="filterStatus === s ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
              @click="filterStatus = s">{{ s }}</button>
          </div>
        </div>
      </section>

      <!-- Loading -->
      <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
        <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
        <span class="text-xs text-(--text-muted)">Loading customers...</span>
      </div>

      <!-- Empty -->
      <div v-else-if="filtered.length === 0" class="glass-card rounded-2xl py-20 text-center">
        <i class="ti ti-user-off text-4xl text-(--text-muted)" />
        <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No customers</h4>
        <p class="text-xs text-(--text-muted) mt-1">Add your first customer to start a quotation.</p>
      </div>

      <!-- Cards -->
      <section v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <article v-for="c in filtered" :key="c.id" class="glass-card rounded-2xl p-5 flex flex-col gap-3 group">
          <header class="flex items-start justify-between gap-3">
            <NuxtLink :to="`/sales/customers/${c.id}`" class="flex items-center gap-3 min-w-0">
              <div class="w-11 h-11 rounded-lg flex items-center justify-center font-bold text-sm shrink-0 overflow-hidden"
                :style="c.brandLogoUrl ? {} : avatarStyle(c)">
                <img v-if="c.brandLogoUrl" :src="c.brandLogoUrl" :alt="c.name"
                  class="w-full h-full object-contain" />
                <span v-else>{{ c.name.charAt(0).toUpperCase() }}</span>
              </div>
              <div class="min-w-0">
                <h3 class="text-sm font-semibold text-(--text-heading) truncate group-hover:text-(--color-primary) transition-colors">{{ c.name }}</h3>
                <p class="text-xxs text-(--text-muted) truncate">{{ c.companyName || c.email }}</p>
              </div>
            </NuxtLink>
            <div class="flex flex-col items-end gap-1 shrink-0">
              <Badge :variant="c.status === 'active' ? 'success' : 'secondary'">{{ c.status }}</Badge>
              <span :class="typeChipClass(c.customerType)" class="text-xxs px-1.5 py-0.5 rounded font-bold uppercase tracking-wide">
                {{ c.customerType || 'individual' }}
              </span>
            </div>
          </header>

          <dl class="text-xxs space-y-1 text-(--text-body)">
            <div class="flex items-center gap-2 truncate">
              <i class="ti ti-mail text-(--text-muted) shrink-0" />
              <span class="truncate">{{ c.email }}</span>
            </div>
            <div v-if="c.phone" class="flex items-center gap-2 truncate">
              <i class="ti ti-phone text-(--text-muted) shrink-0" />
              <span class="truncate">{{ c.phone }}</span>
            </div>
            <div v-if="c.billingCity || c.billingCountry" class="flex items-center gap-2 truncate">
              <i class="ti ti-map-pin text-(--text-muted) shrink-0" />
              <span class="truncate">{{ [c.billingCity, c.billingCountry].filter(Boolean).join(', ') }}</span>
            </div>
            <div v-else-if="c.address" class="flex items-center gap-2 truncate">
              <i class="ti ti-map-pin text-(--text-muted) shrink-0" />
              <span class="truncate">{{ c.address }}</span>
            </div>
          </dl>

          <div class="flex flex-wrap gap-1">
            <span v-if="c.tier && c.tier !== 'standard'" :class="tierChipClass(c.tier)"
              class="text-xxs px-1.5 py-0.5 rounded font-bold uppercase tracking-wide">
              {{ c.tier }}
            </span>
            <span v-if="c.industry" class="text-xxs px-1.5 py-0.5 rounded bg-(--bg-muted) text-(--text-muted)">
              {{ c.industry }}
            </span>
            <span v-if="c.externalCode" class="text-xxs px-1.5 py-0.5 rounded bg-(--bg-muted) text-(--text-muted) font-mono">
              {{ c.externalCode }}
            </span>
          </div>

          <div v-if="c.customerType === 'tenant'" class="rounded-lg p-3 text-xxs space-y-1.5"
            :class="c.provisionedSubdomain ? 'bg-(--color-success)/10 border border-(--color-success)/30' : 'bg-(--bg-muted) border border-(--border-color)'">
            <div class="flex items-center gap-1.5 font-bold" :class="c.provisionedSubdomain ? 'text-(--color-success)' : 'text-(--text-muted)'">
              <i :class="c.provisionedSubdomain ? 'ti ti-circle-check-filled' : 'ti ti-server-off'" />
              {{ c.provisionedSubdomain ? 'Live' : 'Not provisioned' }}
            </div>
            <a v-if="c.provisionedSubdomain"
              :href="`https://${c.provisionedSubdomain}`" target="_blank" rel="noopener"
              class="flex items-center gap-1 text-(--color-primary) hover:underline font-mono"
              @click.stop>
              <i class="ti ti-external-link" />
              {{ c.provisionedSubdomain }}
            </a>
            <div v-else-if="c.tenantHandle" class="flex items-center gap-1 text-(--text-muted)">
              <i class="ti ti-at" />
              <span class="font-mono">{{ c.tenantHandle }}</span>
            </div>
          </div>

          <footer class="mt-auto pt-3 border-t border-(--border-color) flex items-center justify-between gap-2">
            <NuxtLink :to="`/sales/quotations?customer_id=${c.id}`" class="text-xxs text-(--color-primary) hover:underline">
              <i class="ti ti-file-text" /> New quote
            </NuxtLink>
            <div class="flex gap-1">
              <NuxtLink :to="`/sales/customers/${c.id}/edit`" class="action-btn" title="Edit"><i class="ti ti-pencil" /></NuxtLink>
              <button type="button" class="action-btn action-btn-danger" title="Archive" @click="confirmDelete(c)"><i class="ti ti-trash" /></button>
            </div>
          </footer>
        </article>
      </section>
    </div>

    <!-- Archive confirm -->
    <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
      <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
        <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
          <h3>Archive customer</h3>
          <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted)" @click="deleteTarget = null"><i class="ti ti-x" /></button>
        </header>
        <div class="p-5 space-y-2">
          <p class="text-xs text-(--text-body)">
            Archive <span class="font-semibold text-(--text-heading)">{{ deleteTarget.name }}</span>?
          </p>
          <p class="text-xxs text-(--text-muted)">Historical quotes, orders, invoices, and subscriptions stay intact.</p>
          <p v-if="deleteTarget.customerType === 'tenant' && deleteTarget.provisionedTenantId"
            class="text-xxs text-(--color-warning) font-semibold flex items-center gap-1">
            <i class="ti ti-alert-triangle" />
            This customer has an active tenant — archiving here does NOT deprovision it.
          </p>
        </div>
        <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
          <button type="button" class="btn btn-ghost text-xs" @click="deleteTarget = null">Keep</button>
          <button type="button" class="btn btn-danger text-xs" :disabled="archiving" @click="onConfirmDelete">
            <i :class="['ti', archiving ? 'ti-loader-2 animate-spin' : 'ti-trash']" />
            {{ archiving ? 'Archiving…' : 'Archive' }}
          </button>
        </footer>
      </div>
    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useSales } from '~/composables/useSales'
import { useToast } from '~/composables/useToast'
import type { Customer, CustomerType, CustomerTier } from '~/types/sales'

const sales = useSales()
const toast = useToast()

const loading = ref(false)
const archiving = ref(false)
const customers = ref<Customer[]>([])
const search = ref('')
const filterStatus = ref<'all' | 'active' | 'inactive'>('all')
const filterType = ref<'all' | CustomerType>('all')

// ───── Metrics ─────────────────────────────────────────────────
const tenantCount = computed(() => customers.value.filter(c => c.customerType === 'tenant').length)
const provisionedCount = computed(() => customers.value.filter(c => !!c.provisionedTenantId).length)
const enterpriseCount = computed(() => customers.value.filter(c => c.tier === 'enterprise').length)

// ───── Filter ───────────────────────────────────────────────────
const filtered = computed(() => customers.value.filter(c => {
  const q = search.value.toLowerCase()
  const matchSearch = !q ||
    c.name.toLowerCase().includes(q) ||
    c.email.toLowerCase().includes(q) ||
    (c.companyName?.toLowerCase().includes(q) ?? false) ||
    (c.externalCode?.toLowerCase().includes(q) ?? false)
  const matchStatus = filterStatus.value === 'all' || c.status === filterStatus.value
  const matchType = filterType.value === 'all' || c.customerType === filterType.value
  return matchSearch && matchStatus && matchType
}))

// ───── Styling helpers ──────────────────────────────────────────
const avatarStyle = (c: Customer) => {
  if (c.brandPrimaryColor) {
    return {
      background: `rgb(${c.brandPrimaryColor} / 0.15)`,
      color: `rgb(${c.brandPrimaryColor})`,
    }
  }
  return {
    background: 'rgb(var(--color-primary-rgb) / 0.12)',
    color: 'rgb(var(--color-primary-rgb))',
  }
}

const typeChipClass = (type: CustomerType | null | undefined) => {
  if (type === 'tenant') return 'bg-(--color-primary)/15 text-(--color-primary)'
  if (type === 'business') return 'bg-violet-500/15 text-violet-500'
  return 'bg-(--bg-muted) text-(--text-muted)'
}

const tierChipClass = (tier: CustomerTier) => {
  if (tier === 'enterprise') return 'bg-amber-500/15 text-amber-600'
  if (tier === 'premium') return 'bg-purple-500/15 text-purple-600'
  return 'bg-(--bg-muted) text-(--text-muted)'
}

// ───── Data ─────────────────────────────────────────────────────
const load = async () => {
  loading.value = true
  try {
    const res = await sales.customers.list({ limit: 200 })
    customers.value = res.data
  } catch (err: any) {
    toast.error('Failed to load customers', err?.data?.message)
  } finally {
    loading.value = false
  }
}

// ───── Archive ──────────────────────────────────────────────────
const deleteTarget = ref<Customer | null>(null)
const confirmDelete = (c: Customer) => { deleteTarget.value = c }

const onConfirmDelete = async () => {
  if (!deleteTarget.value) return
  archiving.value = true
  try {
    await sales.customers.destroy(deleteTarget.value.id)
    customers.value = customers.value.filter(c => c.id !== deleteTarget.value!.id)
    toast.success('Customer archived', deleteTarget.value.name)
    deleteTarget.value = null
  } catch (err: any) {
    toast.error('Archive failed', err?.data?.message)
  } finally {
    archiving.value = false
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
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
.action-btn:hover { background: var(--bg-muted); color: var(--color-primary); border-color: rgb(var(--color-primary-rgb) / 0.4); }
.action-btn-danger:hover { color: var(--color-danger); border-color: rgb(var(--color-danger-rgb) / 0.4); }
</style>
