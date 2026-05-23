<template>
  <NuxtLayout name="default">
    <div v-if="loading" class="py-24 flex justify-center">
      <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
    </div>

    <div v-else-if="customer" class="space-y-6">
      <!-- Page header -->
      <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div class="flex items-center gap-4 min-w-0">
          <div class="w-14 h-14 rounded-xl flex items-center justify-center font-bold text-xl shrink-0 overflow-hidden"
            :style="customer.brandLogoUrl ? {} : avatarStyle(customer)">
            <img v-if="customer.brandLogoUrl" :src="customer.brandLogoUrl" :alt="customer.name"
              class="w-full h-full object-contain" />
            <span v-else>{{ customer.name.charAt(0).toUpperCase() }}</span>
          </div>
          <div class="min-w-0">
            <div class="flex items-center flex-wrap gap-2">
              <h1 class="text-xl font-semibold truncate">{{ customer.name }}</h1>
              <Badge :variant="customer.status === 'active' ? 'success' : 'secondary'">{{ customer.status }}</Badge>
              <span :class="typeChipClass(customer.customerType)"
                class="text-xxs px-1.5 py-0.5 rounded font-bold uppercase tracking-wide">
                {{ customer.customerType || 'individual' }}
              </span>
              <span v-if="customer.tier && customer.tier !== 'standard'"
                :class="tierChipClass(customer.tier)"
                class="text-xxs px-1.5 py-0.5 rounded font-bold uppercase tracking-wide">
                {{ customer.tier }}
              </span>
            </div>
            <p class="text-xs text-(--text-muted) truncate mt-0.5">{{ customer.companyName || customer.email }}</p>
          </div>
        </div>
        <div class="flex flex-wrap gap-2 shrink-0">
          <NuxtLink :to="`/sales/quotations?customer_id=${customer.id}`" class="btn btn-primary text-xs">
            <i class="ti ti-file-text" />New quote
          </NuxtLink>
          <NuxtLink :to="`/sales/customers/${customer.id}/edit`" class="btn btn-ghost text-xs">
            <i class="ti ti-pencil" />Edit
          </NuxtLink>
        </div>
      </header>

      <!-- Activity tiles -->
      <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
        <div class="glass-card rounded-xl p-4">
          <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Orders</p>
          <p class="text-xl font-semibold text-(--text-heading) mt-1">{{ orderCount }}</p>
        </div>
        <div class="glass-card rounded-xl p-4">
          <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Lifetime value</p>
          <p class="text-xl font-semibold text-(--color-primary) mt-1">{{ fmtMoney(lifetimeValue) }}</p>
        </div>
        <div class="glass-card rounded-xl p-4">
          <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Leads</p>
          <p class="text-xl font-semibold text-(--text-heading) mt-1">{{ leadCount }}</p>
        </div>
        <div class="glass-card rounded-xl p-4">
          <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Customer since</p>
          <p class="text-base font-semibold text-(--text-heading) mt-1">{{ formatDate(customer.createdAt) }}</p>
        </div>
      </section>

      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left column: contact + address + locale -->
        <div class="lg:col-span-2 space-y-4">

          <!-- Contact -->
          <section class="glass-card rounded-2xl p-5">
            <h3 class="section-title"><i class="ti ti-address-book" />Contact</h3>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 mt-4">
              <div>
                <dt class="detail-label">Email</dt>
                <dd class="detail-value break-all">{{ customer.email }}</dd>
              </div>
              <div>
                <dt class="detail-label">Phone</dt>
                <dd class="detail-value">{{ customer.phone || '—' }}</dd>
              </div>
              <div>
                <dt class="detail-label">Company</dt>
                <dd class="detail-value">{{ customer.companyName || '—' }}</dd>
              </div>
              <div>
                <dt class="detail-label">Website</dt>
                <dd class="detail-value">
                  <a v-if="customer.website" :href="customer.website" target="_blank" rel="noopener"
                    class="text-(--color-primary) hover:underline truncate block">{{ customer.website }}</a>
                  <span v-else>—</span>
                </dd>
              </div>
            </dl>
          </section>

          <!-- Classification & business -->
          <section class="glass-card rounded-2xl p-5">
            <h3 class="section-title"><i class="ti ti-tag" />Classification</h3>
            <dl class="grid grid-cols-2 md:grid-cols-3 gap-x-6 gap-y-4 mt-4">
              <div>
                <dt class="detail-label">Type</dt>
                <dd class="detail-value capitalize">{{ customer.customerType || 'individual' }}</dd>
              </div>
              <div>
                <dt class="detail-label">Tier</dt>
                <dd class="detail-value capitalize">{{ customer.tier || 'standard' }}</dd>
              </div>
              <div>
                <dt class="detail-label">External code</dt>
                <dd class="detail-value font-mono">{{ customer.externalCode || '—' }}</dd>
              </div>
              <div>
                <dt class="detail-label">Industry</dt>
                <dd class="detail-value">{{ customer.industry || '—' }}</dd>
              </div>
              <div>
                <dt class="detail-label">Tax ID</dt>
                <dd class="detail-value font-mono">{{ customer.taxId || '—' }}</dd>
              </div>
            </dl>
          </section>

          <!-- Address -->
          <section class="glass-card rounded-2xl p-5">
            <h3 class="section-title"><i class="ti ti-map-pin" />Address</h3>
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-4 mt-4">
              <div class="md:col-span-2">
                <dt class="detail-label">Street</dt>
                <dd class="detail-value whitespace-pre-line">{{ customer.address || '—' }}</dd>
              </div>
              <div>
                <dt class="detail-label">City</dt>
                <dd class="detail-value">{{ customer.billingCity || '—' }}</dd>
              </div>
              <div>
                <dt class="detail-label">State / Province</dt>
                <dd class="detail-value">{{ customer.billingState || '—' }}</dd>
              </div>
              <div>
                <dt class="detail-label">Postal code</dt>
                <dd class="detail-value">{{ customer.billingPostalCode || '—' }}</dd>
              </div>
              <div>
                <dt class="detail-label">Country</dt>
                <dd class="detail-value">{{ customer.billingCountry || '—' }}</dd>
              </div>
            </dl>
          </section>

          <!-- Order history -->
          <section class="glass-card rounded-2xl p-5">
            <h3 class="section-title"><i class="ti ti-shopping-cart" />Order history</h3>
            <div v-if="!customer.orders?.length" class="text-xs text-(--text-muted) italic mt-3">No orders yet.</div>
            <div v-else class="overflow-x-auto -mx-5 mt-3">
              <table class="w-full text-xs">
                <thead class="text-xxs uppercase tracking-widest text-(--text-muted) border-b border-(--border-color)">
                  <tr>
                    <th class="text-left px-5 py-2">Order #</th>
                    <th class="text-left px-2 py-2">Status</th>
                    <th class="text-right px-2 py-2">Total</th>
                    <th class="text-right px-5 py-2">Date</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="o in customer.orders" :key="o.id" class="border-b border-(--border-color)/40">
                    <td class="px-5 py-3">
                      <NuxtLink :to="`/sales/orders/${o.id}`" class="text-(--color-primary) hover:underline font-mono font-semibold">
                        {{ o.orderNumber }}
                      </NuxtLink>
                    </td>
                    <td class="px-2 py-3">
                      <Badge :variant="statusBadgeVariant(o.status)">{{ o.status }}</Badge>
                    </td>
                    <td class="px-2 py-3 text-right font-mono font-semibold">{{ fmtMoney(o.totalAmount) }}</td>
                    <td class="px-5 py-3 text-right text-(--text-muted)">{{ formatDate(o.createdAt) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>

          <!-- Leads -->
          <section v-if="customer.leads?.length" class="glass-card rounded-2xl p-5">
            <h3 class="section-title"><i class="ti ti-target" />Leads</h3>
            <div class="overflow-x-auto -mx-5 mt-3">
              <table class="w-full text-xs">
                <thead class="text-xxs uppercase tracking-widest text-(--text-muted) border-b border-(--border-color)">
                  <tr>
                    <th class="text-left px-5 py-2">Title</th>
                    <th class="text-left px-2 py-2">Status</th>
                    <th class="text-right px-5 py-2">Est. value</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="l in customer.leads" :key="l.id" class="border-b border-(--border-color)/40">
                    <td class="px-5 py-3 text-(--text-heading) font-medium">{{ l.title }}</td>
                    <td class="px-2 py-3 capitalize text-(--text-muted)">{{ l.status }}</td>
                    <td class="px-5 py-3 text-right font-mono font-semibold">{{ fmtMoney(l.estimatedValue) }}</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <!-- Right column: locale, notes, branding, account manager, tenant -->
        <div class="space-y-4">

          <!-- Locale -->
          <section class="glass-card rounded-2xl p-5">
            <h3 class="section-title"><i class="ti ti-world" />Locale</h3>
            <dl class="space-y-3 mt-4">
              <div>
                <dt class="detail-label">Currency</dt>
                <dd class="detail-value font-mono">{{ customer.currency }}</dd>
              </div>
              <div>
                <dt class="detail-label">Language</dt>
                <dd class="detail-value">{{ customer.language }}</dd>
              </div>
              <div>
                <dt class="detail-label">Timezone</dt>
                <dd class="detail-value">{{ customer.timezone }}</dd>
              </div>
            </dl>
          </section>

          <!-- Notes -->
          <section v-if="customer.notes" class="glass-card rounded-2xl p-5">
            <h3 class="section-title"><i class="ti ti-notes" />Notes</h3>
            <p class="text-xs text-(--text-body) whitespace-pre-line mt-3 leading-relaxed">{{ customer.notes }}</p>
          </section>

          <!-- Account manager -->
          <section class="glass-card rounded-2xl p-5">
            <h3 class="section-title"><i class="ti ti-user-check" />Account manager</h3>
            <div v-if="customer.accountManager" class="flex items-center gap-3 mt-4">
              <div class="w-9 h-9 rounded-full bg-(--color-primary-subtle) text-(--color-primary) flex items-center justify-center font-bold text-sm">
                {{ customer.accountManager.name.charAt(0).toUpperCase() }}
              </div>
              <div>
                <p class="text-xs font-semibold text-(--text-heading)">{{ customer.accountManager.name }}</p>
                <p class="text-xxs text-(--text-muted)">{{ customer.accountManager.email }}</p>
              </div>
            </div>
            <p v-else class="text-xs text-(--text-muted) italic mt-3">Unassigned</p>
          </section>

          <!-- Branding -->
          <section v-if="customer.brandPrimaryColor || customer.brandLogoUrl" class="glass-card rounded-2xl p-5">
            <h3 class="section-title"><i class="ti ti-palette" />Branding</h3>
            <div class="space-y-3 mt-4">
              <div v-if="customer.brandPrimaryColor">
                <p class="detail-label">Primary color</p>
                <div class="flex items-center gap-2 mt-1">
                  <div class="w-7 h-7 rounded border border-(--border-color)"
                    :style="{ background: `rgb(${customer.brandPrimaryColor})` }" />
                  <code class="text-xxs text-(--text-muted)">rgb({{ customer.brandPrimaryColor }})</code>
                </div>
              </div>
              <div v-if="customer.brandLogoUrl">
                <p class="detail-label">Logo</p>
                <img :src="customer.brandLogoUrl" class="mt-1 h-10 object-contain rounded border border-(--border-color) bg-(--bg-muted) p-1" alt="Brand logo" />
              </div>
            </div>
          </section>

          <!-- Tenant provisioning (only for tenant-type customers) -->
          <section v-if="customer.customerType === 'tenant'" class="glass-card rounded-2xl p-5">
            <h3 class="section-title"><i class="ti ti-server" />Tenant provisioning</h3>
            <div class="space-y-4 mt-4">

              <!-- Live subdomain card -->
              <div v-if="customer.provisionedSubdomain"
                class="rounded-xl border border-(--color-success)/40 bg-(--color-success)/8 p-4">
                <p class="text-xxs font-bold uppercase tracking-widest text-(--color-success) mb-2">
                  <i class="ti ti-circle-check-filled mr-1" />System is live
                </p>
                <p class="text-xxs text-(--text-muted) mb-2">Customer access URL:</p>
                <a :href="`https://${customer.provisionedSubdomain}`" target="_blank" rel="noopener"
                  class="flex items-center gap-2 font-mono text-sm font-semibold text-(--color-primary) hover:underline break-all">
                  <i class="ti ti-external-link shrink-0" />
                  {{ customer.provisionedSubdomain }}
                </a>
              </div>

              <!-- Pending state -->
              <div v-else class="rounded-xl border border-(--border-color) bg-(--bg-muted) p-4 flex items-start gap-3">
                <i class="ti ti-server-off text-xl text-(--text-muted) shrink-0 mt-0.5" />
                <div>
                  <p class="text-xs font-semibold text-(--text-heading)">Not yet provisioned</p>
                  <p class="text-xxs text-(--text-muted) mt-0.5">
                    A tenant database and subdomain will be created automatically when a subscription
                    for this customer is confirmed.
                  </p>
                  <p v-if="customer.tenantHandle" class="text-xxs font-mono text-(--color-primary) mt-1.5">
                    Reserved handle: @{{ customer.tenantHandle }}
                  </p>
                </div>
              </div>

              <!-- Metadata row -->
              <dl v-if="customer.tenantHandle || customer.provisionedAt" class="grid grid-cols-2 gap-3 text-xxs">
                <div v-if="customer.tenantHandle">
                  <dt class="detail-label">Handle</dt>
                  <dd class="font-mono text-(--text-heading) mt-0.5">@{{ customer.tenantHandle }}</dd>
                </div>
                <div v-if="customer.provisionedAt">
                  <dt class="detail-label">Provisioned</dt>
                  <dd class="text-(--text-body) mt-0.5">{{ formatDate(customer.provisionedAt) }}</dd>
                </div>
              </dl>

            </div>
          </section>

        </div>
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
import type { Customer, CustomerType, CustomerTier } from '~/types/sales'

const route = useRoute()
const sales = useSales()
const toast = useToast()
const crumb = useBreadcrumbOverride()

const customer = ref<Customer | null>(null)
const loading = ref(true)

const fmtMoney = (v: number) =>
  new Intl.NumberFormat('en-US', { style: 'currency', currency: customer.value?.currency ?? 'USD' }).format(v || 0)
const formatDate = (iso: string | null | undefined) =>
  iso ? new Date(iso).toLocaleDateString() : '—'

const orderCount = computed(() => customer.value?.orders?.length ?? 0)
const leadCount = computed(() => customer.value?.leads?.length ?? 0)
const lifetimeValue = computed(() =>
  (customer.value?.orders ?? []).reduce((sum, o) => sum + (o.totalAmount || 0), 0))

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

const load = async () => {
  loading.value = true
  try {
    const res = await sales.customers.show(route.params.id as string)
    customer.value = res.data
    crumb.set(res.data.name)
  } catch (err: any) {
    toast.error('Failed to load customer', err?.data?.message)
  } finally {
    loading.value = false
  }
}

onMounted(load)
onBeforeUnmount(() => crumb.clear())
</script>

<style scoped>
.section-title {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-size: 0.8rem;
  font-weight: 600;
  color: var(--text-heading);
}
.section-title i { color: var(--color-primary); }
.detail-label {
  font-size: 0.65rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.06em;
  color: var(--text-muted);
  margin-bottom: 0.25rem;
}
.detail-value {
  font-size: 0.75rem;
  color: var(--text-body);
}
</style>
