<template>
  <NuxtLayout name="default">
    <div class="space-y-8">
      <!-- Welcome banner -->
      <section class="relative overflow-hidden rounded-2xl border border-(--border-color) bg-(--bg-card) p-6 sm:p-8 shadow-(--shadow-sm)">
        <div class="absolute -top-20 -right-16 w-72 h-72 rounded-full blur-3xl bg-(--color-primary)/10 pointer-events-none" />
        <div class="absolute -bottom-20 -left-20 w-72 h-72 rounded-full blur-3xl bg-(--color-info)/10 pointer-events-none" />

        <div class="relative z-10 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
          <div class="space-y-2 max-w-2xl">
            <Badge variant="primary" :dot="true">Portal Established</Badge>
            <h1 class="text-2xl font-bold tracking-tight">
              Welcome back, {{ authStore.user?.name || 'Administrator' }}
            </h1>
            <p class="text-xs text-(--text-body) leading-relaxed">
              You are logged in to the tenant workspace
              <b class="text-(--color-primary) font-mono">@{{ tenantStore.activeHandle }}</b>
              ({{ tenantStore.activeName }}).
              Your authorization boundaries permit ledger sync, database connection mapping, and role-scoped auditing.
            </p>
          </div>
          <div class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-(--bg-muted) border border-(--border-color)">
            <span class="relative flex h-2 w-2">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-(--color-success)/75" />
              <span class="relative inline-flex rounded-full h-2 w-2 bg-(--color-success)" />
            </span>
            <span class="text-xxs font-bold uppercase tracking-widest text-(--text-body) font-mono">Sys-Status: Operational</span>
          </div>
        </div>
      </section>

      <!-- Metrics row (§5.1) -->
      <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <MetricCard
          variant="primary"
          icon="ti-package"
          label="Products"
          value="2,240"
          sub="Active listings"
          sub-value="980"
          delta="+24 New"
        />
        <MetricCard
          variant="secondary"
          icon="ti-shopping-cart"
          label="Orders"
          value="8,014"
          sub="Total orders"
          sub-value="105K"
          delta="+120 New"
        />
        <MetricCard
          variant="success"
          icon="ti-currency-dollar"
          label="Today's sales"
          value="$17,854"
          sub="Today's target"
          sub-value="$156K"
          delta="+8.2%"
        />
        <MetricCard
          variant="info"
          icon="ti-users"
          label="Customers"
          value="3,209"
          sub="Total customers"
          sub-value="58,320"
          delta="+36 New"
        />
        <MetricCard
          variant="warning"
          icon="ti-chart-bar"
          label="Total revenue"
          value="$3.50M"
          sub="Gross margin"
          sub-value="$12.8M"
          delta="-4.5%"
          delta-direction="down"
        />
      </section>

      <!-- Two-column workspace -->
      <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <!-- Live ops card (col 2) -->
        <div class="xl:col-span-2 space-y-6">
          <div class="glass-card rounded-2xl p-6">
            <header class="flex flex-col sm:flex-row justify-between sm:items-center gap-3 pb-5 border-b border-(--border-color)">
              <div>
                <h3 class="flex items-center gap-2">
                  <span class="w-1.5 h-4 rounded-sm bg-(--color-primary)" />
                  Sprint velocity (Q4)
                </h3>
                <p class="text-xs text-(--text-muted) mt-0.5">Cross-tenant fulfillment rate across last 14 days</p>
              </div>
              <div class="flex items-center gap-2">
                <button class="btn btn-ghost text-xs"><i class="ti ti-filter" />Filter</button>
                <button class="btn btn-soft-primary text-xs"><i class="ti ti-download" />Export</button>
              </div>
            </header>

            <div class="mt-5 grid grid-cols-7 gap-2 items-end h-44">
              <div v-for="(bar, idx) in velocityBars" :key="idx" class="flex flex-col items-center gap-1 group">
                <div class="w-full bg-(--bg-muted) rounded-md relative overflow-hidden" :style="{ height: `${bar.height}%` }">
                  <div
                    class="absolute inset-0 bg-linear-to-t from-(--color-primary) to-(--color-info) opacity-90 group-hover:opacity-100 transition-opacity"
                  />
                </div>
                <span class="text-[10px] text-(--text-muted) font-mono">{{ bar.day }}</span>
              </div>
            </div>

            <footer class="mt-5 pt-5 border-t border-(--border-color) grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
              <div>
                <p class="text-xxs text-(--text-muted) uppercase tracking-widest">Throughput</p>
                <p class="text-(--text-heading) font-semibold font-mono">2,140<span class="text-(--color-success)"> +6%</span></p>
              </div>
              <div>
                <p class="text-xxs text-(--text-muted) uppercase tracking-widest">SLA</p>
                <p class="text-(--text-heading) font-semibold font-mono">99.81%</p>
              </div>
              <div>
                <p class="text-xxs text-(--text-muted) uppercase tracking-widest">Open tickets</p>
                <p class="text-(--text-heading) font-semibold font-mono">38</p>
              </div>
              <div>
                <p class="text-xxs text-(--text-muted) uppercase tracking-widest">Audits today</p>
                <p class="text-(--text-heading) font-semibold font-mono">1,204</p>
              </div>
            </footer>
          </div>

          <!-- Recent orders preview -->
          <div class="glass-card rounded-2xl">
            <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
              <h3 class="flex items-center gap-2">
                <span class="w-1.5 h-4 rounded-sm bg-(--color-primary)" />
                Recent orders
              </h3>
              <NuxtLink to="/products" class="text-xs font-semibold text-(--color-primary) hover:underline">View catalog →</NuxtLink>
            </header>
            <div class="overflow-x-auto">
              <table class="w-full text-left">
                <thead>
                  <tr class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                    <th class="px-5 py-3 font-semibold">Order</th>
                    <th class="px-5 py-3 font-semibold">Customer</th>
                    <th class="px-5 py-3 font-semibold text-right font-mono">Total</th>
                    <th class="px-5 py-3 font-semibold">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-(--border-color)">
                  <tr v-for="o in recentOrders" :key="o.id" class="hover:bg-(--bg-muted) transition-colors">
                    <td class="px-5 py-3 text-xs font-mono text-(--text-heading) font-semibold">{{ o.id }}</td>
                    <td class="px-5 py-3 text-xs">{{ o.customer }}</td>
                    <td class="px-5 py-3 text-xs text-right font-mono font-semibold text-(--text-heading)">${{ o.total.toFixed(2) }}</td>
                    <td class="px-5 py-3"><Badge :variant="o.statusVariant">{{ o.status }}</Badge></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Side column -->
        <div class="space-y-6">
          <MeteorCard variant="primary">
            <header class="flex items-center justify-between mb-3">
              <h4 class="text-xxs font-bold uppercase tracking-widest text-(--color-primary)">Tasks completion</h4>
              <span class="text-xxs font-mono text-(--text-muted)">today</span>
            </header>
            <div class="flex items-center gap-4">
              <OrbitLoader :size="72" :percent="78" />
              <div class="text-xs leading-relaxed">
                <p class="text-(--text-heading) font-semibold">78% sprint complete</p>
                <p class="text-(--text-muted) mt-1">12 of 16 critical release tasks signed off.</p>
              </div>
            </div>
          </MeteorCard>

          <div class="glass-card rounded-2xl p-6">
            <h3 class="flex items-center gap-2 mb-5">
              <span class="w-1.5 h-4 rounded-sm bg-(--color-primary)" />
              Live audit feed
            </h3>
            <ul class="space-y-3">
              <li
                v-for="log in auditLogs"
                :key="log.id"
                class="p-3 rounded-xl border border-(--border-color) bg-(--bg-muted)/60 flex gap-3 relative overflow-hidden"
              >
                <span
                  class="absolute left-0 top-0 bottom-0 w-1"
                  :class="log.type === 'security' ? 'bg-(--color-primary)' : 'bg-(--color-info)'"
                />
                <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                     :class="log.type === 'security' ? 'badge-soft-primary' : 'badge-soft-info'">
                  <i :class="['ti', log.type === 'auth' ? 'ti-key' : 'ti-shield']" />
                </div>
                <div class="flex-1 min-w-0">
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-xs font-semibold text-(--text-heading) truncate">{{ log.actor }}</span>
                    <span class="text-[10px] font-mono text-(--text-muted)">{{ log.time }}</span>
                  </div>
                  <p class="text-xxs text-(--text-body) mt-0.5 truncate">{{ log.action }}</p>
                </div>
              </li>
            </ul>
            <div class="mt-5 p-3 rounded-xl badge-soft-primary flex gap-3 items-start">
              <i class="ti ti-shield text-base" />
              <div class="text-xxs leading-relaxed">
                <h6 class="font-bold uppercase tracking-widest text-(--color-primary)">Governance Guard</h6>
                <p class="text-(--text-body) mt-1">
                  All API attempts include matching <b>X-Tenant-Handle</b> headers. Cross-tenant exposure is blocked.
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useTenantStore } from '~/stores/tenant'

const authStore = useAuthStore()
const tenantStore = useTenantStore()

const velocityBars = [
  { day: 'Mon', height: 38 },
  { day: 'Tue', height: 58 },
  { day: 'Wed', height: 72 },
  { day: 'Thu', height: 49 },
  { day: 'Fri', height: 84 },
  { day: 'Sat', height: 62 },
  { day: 'Sun', height: 95 }
]

const recentOrders = ref<{ id: string; customer: string; total: number; status: string; statusVariant: 'success' | 'warning' | 'danger' | 'info' }[]>([
  { id: '#ORD-10245', customer: 'Acme Logistics',     total: 1280.50, status: 'Paid',     statusVariant: 'success' },
  { id: '#ORD-10246', customer: 'Cyberdyne Industries', total: 540.00,  status: 'Pending',  statusVariant: 'warning' },
  { id: '#ORD-10247', customer: 'Globex Corp',         total: 2199.99, status: 'Refunded', statusVariant: 'danger'  },
  { id: '#ORD-10248', customer: 'Initech',             total: 89.95,   status: 'Shipping', statusVariant: 'info'    }
])

const auditLogs = ref([
  { id: '1', actor: 'admin@example.com',  type: 'auth',     action: 'Token successfully rotated (auth.refresh)', time: 'Just Now' },
  { id: '2', actor: 'System Seeder',      type: 'security', action: 'Synchronized 26 permissions to role: admin', time: '10m ago' },
  { id: '3', actor: 'admin@example.com',  type: 'auth',     action: 'Authenticated session established',         time: '15m ago' },
  { id: '4', actor: 'System Admin',       type: 'security', action: 'Database schema isolated for handle: test', time: '1h ago' }
])
</script>
