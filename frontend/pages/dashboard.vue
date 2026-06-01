<template>
    <NuxtLayout name="default">
        <div class="space-y-8">

            <!-- Welcome banner -->
            <section
                class="relative overflow-hidden rounded-2xl border border-(--border-color) bg-(--bg-card) p-6 sm:p-8 shadow-(--shadow-sm)">
                <div
                    class="absolute -top-20 -right-16 w-72 h-72 rounded-full blur-3xl bg-(--color-primary)/10 pointer-events-none" />
                <div
                    class="absolute -bottom-20 -left-20 w-72 h-72 rounded-full blur-3xl bg-(--color-info)/10 pointer-events-none" />
                <div class="relative z-10 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                    <div class="space-y-1.5 max-w-2xl">
                        <Badge variant="primary" :dot="true">{{ authStore.isAdmin ? 'Administrator' : 'Workspace' }}
                        </Badge>
                        <h1 class="text-2xl font-bold tracking-tight">
                            Welcome back, {{ authStore.user?.name || 'User' }}
                        </h1>
                        <p class="text-xs text-(--text-body) leading-relaxed">
                            Tenant workspace
                            <b class="text-(--color-primary) font-mono">@{{ tenantStore.activeHandle }}</b>
                            ({{ tenantStore.activeName }}) ·
                            {{ today }}
                        </p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div
                            class="inline-flex items-center gap-2 px-3 py-2 rounded-xl bg-(--bg-muted) border border-(--border-color)">
                            <span class="relative flex h-2 w-2">
                                <span
                                    class="animate-ping absolute inline-flex h-full w-full rounded-full bg-(--color-success)/75" />
                                <span class="relative inline-flex rounded-full h-2 w-2 bg-(--color-success)" />
                            </span>
                            <span
                                class="text-xxs font-bold uppercase tracking-widest text-(--text-body) font-mono">Operational</span>
                        </div>
                        <button class="btn btn-soft-primary text-xs gap-1.5" :disabled="loading" @click="refresh">
                            <i class="ti ti-refresh text-sm" :class="loading ? 'animate-spin' : ''" />
                            Refresh
                        </button>
                    </div>
                </div>
            </section>

            <!-- Error state -->
            <div v-if="error && !loading"
                class="rounded-xl border border-(--color-danger)/30 bg-(--color-danger-subtle) px-5 py-4 flex items-center gap-3 text-xs text-(--color-danger)">
                <i class="ti ti-alert-triangle text-base shrink-0" />
                <span>{{ error }}</span>
                <button class="ml-auto font-semibold underline" @click="refresh">Retry</button>
            </div>

            <!-- ======================== ADMIN DASHBOARD ======================== -->
            <template v-if="authStore.isAdmin">

                <!-- KPI row -->
                <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
                    <!-- Employees -->
                    <div class="glass-card rounded-2xl p-4 space-y-2 col-span-1">
                        <div class="flex items-center justify-between">
                            <span
                                class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Employees</span>
                            <span class="w-7 h-7 rounded-lg badge-soft-primary flex items-center justify-center">
                                <i class="ti ti-users text-sm" />
                            </span>
                        </div>
                        <p class="text-2xl font-bold text-(--text-heading) font-mono">
                            <span v-if="loading" class="inline-block w-12 h-6 rounded dash-skeleton" />
                            <span v-else>{{ summary?.kpis?.employees?.active ?? '—' }}</span>
                        </p>
                        <p class="text-xxs text-(--text-muted)">
                            {{ summary?.kpis?.employees?.on_leave ?? 0 }} on leave ·
                            <span class="text-(--color-success)">+{{ summary?.kpis?.employees?.new_mtd ?? 0 }}
                                MTD</span>
                        </p>
                    </div>

                    <!-- Revenue MTD -->
                    <div class="glass-card rounded-2xl p-4 space-y-2 col-span-1">
                        <div class="flex items-center justify-between">
                            <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Revenue
                                MTD</span>
                            <span class="w-7 h-7 rounded-lg badge-soft-success flex items-center justify-center">
                                <i class="ti ti-currency-dollar text-sm" />
                            </span>
                        </div>
                        <p class="text-2xl font-bold text-(--text-heading) font-mono">
                            <span v-if="loading" class="inline-block w-16 h-6 rounded dash-skeleton" />
                            <span v-else>{{ formatCurrency(summary?.kpis?.sales?.revenue_mtd ?? 0) }}</span>
                        </p>
                        <p class="text-xxs" :class="revenueChangeClass">
                            <i class="ti text-[10px]"
                                :class="revenueChangePct >= 0 ? 'ti-trending-up' : 'ti-trending-down'" />
                            {{ revenueChangePct >= 0 ? '+' : '' }}{{ revenueChangePct }}% vs last month
                        </p>
                    </div>

                    <!-- Orders MTD -->
                    <div class="glass-card rounded-2xl p-4 space-y-2 col-span-1">
                        <div class="flex items-center justify-between">
                            <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Orders
                                MTD</span>
                            <span class="w-7 h-7 rounded-lg badge-soft-info flex items-center justify-center">
                                <i class="ti ti-shopping-cart text-sm" />
                            </span>
                        </div>
                        <p class="text-2xl font-bold text-(--text-heading) font-mono">
                            <span v-if="loading" class="inline-block w-10 h-6 rounded dash-skeleton" />
                            <span v-else>{{ summary?.kpis?.sales?.orders_mtd ?? '—' }}</span>
                        </p>
                        <p class="text-xxs text-(--text-muted)">
                            {{ summary?.kpis?.sales?.open_leads ?? 0 }} open leads
                        </p>
                    </div>

                    <!-- Customers -->
                    <div class="glass-card rounded-2xl p-4 space-y-2 col-span-1">
                        <div class="flex items-center justify-between">
                            <span
                                class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Customers</span>
                            <span class="w-7 h-7 rounded-lg badge-soft-warning flex items-center justify-center">
                                <i class="ti ti-address-book text-sm" />
                            </span>
                        </div>
                        <p class="text-2xl font-bold text-(--text-heading) font-mono">
                            <span v-if="loading" class="inline-block w-12 h-6 rounded dash-skeleton" />
                            <span v-else>{{ summary?.kpis?.sales?.active_customers ?? '—' }}</span>
                        </p>
                        <p class="text-xxs text-(--text-muted)">Active accounts</p>
                    </div>

                    <!-- Pending Leaves -->
                    <div class="glass-card rounded-2xl p-4 space-y-2 col-span-1">
                        <div class="flex items-center justify-between">
                            <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Pending
                                Leaves</span>
                            <span class="w-7 h-7 rounded-lg badge-soft-danger flex items-center justify-center">
                                <i class="ti ti-calendar-event text-sm" />
                            </span>
                        </div>
                        <p class="text-2xl font-bold text-(--text-heading) font-mono">
                            <span v-if="loading" class="inline-block w-8 h-6 rounded dash-skeleton" />
                            <span v-else>{{ summary?.kpis?.leave?.pending ?? '—' }}</span>
                        </p>
                        <p class="text-xxs text-(--text-muted)">
                            {{ summary?.kpis?.attendance?.present_today ?? 0 }} present today
                        </p>
                    </div>

                    <!-- Products / Inventory -->
                    <div class="glass-card rounded-2xl p-4 space-y-2 col-span-1">
                        <div class="flex items-center justify-between">
                            <span
                                class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Inventory</span>
                            <span class="w-7 h-7 rounded-lg badge-soft-secondary flex items-center justify-center">
                                <i class="ti ti-package text-sm" />
                            </span>
                        </div>
                        <p class="text-2xl font-bold text-(--text-heading) font-mono">
                            <span v-if="loading" class="inline-block w-10 h-6 rounded dash-skeleton" />
                            <span v-else>{{ summary?.kpis?.inventory?.total_products ?? '—' }}</span>
                        </p>
                        <p class="text-xxs"
                            :class="(summary?.kpis?.inventory?.low_stock ?? 0) > 0 ? 'text-(--color-warning)' : 'text-(--text-muted)'">
                            <i v-if="(summary?.kpis?.inventory?.low_stock ?? 0) > 0"
                                class="ti ti-alert-triangle text-[10px]" />
                            {{ summary?.kpis?.inventory?.low_stock ?? 0 }} low-stock alerts
                        </p>
                    </div>
                </section>

                <!-- Charts + Quick stats -->
                <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">

                    <!-- Revenue trend -->
                    <div class="xl:col-span-2 glass-card rounded-2xl p-6">
                        <header
                            class="flex flex-col sm:flex-row justify-between sm:items-center gap-3 pb-5 border-b border-(--border-color)">
                            <div>
                                <h3 class="flex items-center gap-2">
                                    <span class="w-1.5 h-4 rounded-sm bg-(--color-primary)" />
                                    Revenue trend (last 7 days)
                                </h3>
                                <p class="text-xs text-(--text-muted) mt-0.5">Daily paid invoice totals</p>
                            </div>
                            <NuxtLink to="/sales/invoices" class="btn btn-ghost text-xs gap-1.5">
                                <i class="ti ti-external-link text-sm" />View invoices
                            </NuxtLink>
                        </header>

                        <!-- Skeleton bars -->
                        <div v-if="loading" class="mt-5 grid grid-cols-7 gap-2 items-end h-44">
                            <div v-for="i in 7" :key="i" class="flex flex-col items-center gap-1">
                                <div class="w-full rounded-md dash-skeleton" :style="{ height: `${30 + i * 9}%` }" />
                                <span class="h-2.5 w-6 rounded dash-skeleton" />
                            </div>
                        </div>

                        <!-- Real bars -->
                        <div v-else class="mt-5 grid grid-cols-7 gap-2 items-end h-44">
                            <div v-for="bar in revenueBars" :key="bar.label"
                                class="flex flex-col items-center gap-1 group"
                                :title="`${bar.label}: ${formatCurrency(bar.amount)}`">
                                <div class="w-full bg-(--bg-muted) rounded-md relative overflow-hidden"
                                    :style="{ height: `${bar.height}%` }">
                                    <div
                                        class="absolute inset-0 bg-linear-to-t from-(--color-primary) to-(--color-info) opacity-90 group-hover:opacity-100 transition-opacity" />
                                </div>
                                <span class="text-[10px] text-(--text-muted) font-mono">{{ bar.label }}</span>
                            </div>
                        </div>

                        <footer
                            class="mt-5 pt-5 border-t border-(--border-color) grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                            <div>
                                <p class="text-xxs text-(--text-muted) uppercase tracking-widest">Revenue MTD</p>
                                <p class="text-(--text-heading) font-semibold font-mono">{{
                                    formatCurrency(summary?.kpis?.sales?.revenue_mtd ?? 0) }}</p>
                            </div>
                            <div>
                                <p class="text-xxs text-(--text-muted) uppercase tracking-widest">vs Last Month</p>
                                <p class="font-semibold font-mono" :class="revenueChangeClass">
                                    {{ revenueChangePct >= 0 ? '+' : '' }}{{ revenueChangePct }}%
                                </p>
                            </div>
                            <div>
                                <p class="text-xxs text-(--text-muted) uppercase tracking-widest">Orders MTD</p>
                                <p class="text-(--text-heading) font-semibold font-mono">{{
                                    summary?.kpis?.sales?.orders_mtd ?? '—' }}</p>
                            </div>
                            <div>
                                <p class="text-xxs text-(--text-muted) uppercase tracking-widest">Open Leads</p>
                                <p class="text-(--text-heading) font-semibold font-mono">{{
                                    summary?.kpis?.sales?.open_leads ?? '—' }}</p>
                            </div>
                        </footer>
                    </div>

                    <!-- Headcount + quick stats -->
                    <div class="space-y-6">

                        <!-- Headcount by department -->
                        <div class="glass-card rounded-2xl p-6">
                            <h3 class="flex items-center gap-2 mb-4">
                                <span class="w-1.5 h-4 rounded-sm bg-(--color-info)" />
                                Headcount by dept
                            </h3>
                            <div v-if="loading" class="space-y-3">
                                <div v-for="i in 4" :key="i" class="space-y-1">
                                    <div class="h-2.5 w-24 rounded dash-skeleton" />
                                    <div class="h-2 rounded dash-skeleton" :style="{ width: `${40 + i * 12}%` }" />
                                </div>
                            </div>
                            <div v-else-if="headcountBars.length === 0"
                                class="text-xs text-(--text-muted) py-4 text-center">No department data</div>
                            <div v-else class="space-y-3">
                                <div v-for="bar in headcountBars" :key="bar.label">
                                    <div class="flex justify-between text-xs mb-1">
                                        <span class="text-(--text-body) truncate flex-1 pr-2">{{ bar.label }}</span>
                                        <span class="font-mono font-semibold text-(--text-heading)">{{ bar.count
                                            }}</span>
                                    </div>
                                    <div class="h-2 rounded-full bg-(--bg-muted) overflow-hidden">
                                        <div class="h-full rounded-full bg-linear-to-r from-(--color-info) to-(--color-primary) transition-all duration-500"
                                            :style="{ width: `${bar.width}%` }" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Leave + Attendance quick card -->
                        <div class="glass-card rounded-2xl p-5 space-y-3">
                            <h3 class="flex items-center gap-2 text-sm">
                                <span class="w-1.5 h-4 rounded-sm bg-(--color-warning)" />
                                Today's pulse
                            </h3>
                            <div class="grid grid-cols-2 gap-3 text-center">
                                <div class="rounded-xl bg-(--bg-muted) py-3">
                                    <p class="text-xl font-bold font-mono text-(--color-success)">{{
                                        summary?.kpis?.attendance?.present_today ?? '—' }}</p>
                                    <p class="text-xxs text-(--text-muted) mt-0.5">Present</p>
                                </div>
                                <div class="rounded-xl bg-(--bg-muted) py-3">
                                    <p class="text-xl font-bold font-mono"
                                        :class="(summary?.kpis?.leave?.pending ?? 0) > 0 ? 'text-(--color-warning)' : 'text-(--text-heading)'">
                                        {{ summary?.kpis?.leave?.pending ?? '—' }}
                                    </p>
                                    <p class="text-xxs text-(--text-muted) mt-0.5">Pending leaves</p>
                                </div>
                            </div>
                            <div v-if="(summary?.kpis?.finance?.unposted_journals ?? 0) > 0"
                                class="flex items-center gap-2 rounded-lg badge-soft-warning px-3 py-2 text-xxs">
                                <i class="ti ti-alert-triangle text-sm shrink-0" />
                                <span>{{ summary?.kpis?.finance?.unposted_journals }} unposted journal{{
                                    (summary?.kpis?.finance?.unposted_journals ?? 0) !== 1 ? 's' : '' }}</span>
                                <NuxtLink to="/finance/journals" class="ml-auto font-semibold underline">View</NuxtLink>
                            </div>
                        </div>

                    </div>
                </section>

                <!-- Recent data tables -->
                <section class="grid grid-cols-1 xl:grid-cols-2 gap-6">

                    <!-- Recent orders -->
                    <div class="glass-card rounded-2xl">
                        <header class="flex items-center justify-between px-5 py-4 border-b border-(--border-color)">
                            <h3 class="flex items-center gap-2">
                                <span class="w-1.5 h-4 rounded-sm bg-(--color-primary)" />
                                Recent orders
                            </h3>
                            <NuxtLink to="/sales/orders"
                                class="text-xs font-semibold text-(--color-primary) hover:underline">View all →
                            </NuxtLink>
                        </header>
                        <div v-if="loading" class="p-5 space-y-3">
                            <div v-for="i in 5" :key="i" class="h-8 rounded dash-skeleton" />
                        </div>
                        <div v-else-if="!recentOrders.length" class="px-5 py-8 text-center text-xs text-(--text-muted)">
                            No orders yet</div>
                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr
                                        class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                        <th class="px-5 py-3 font-semibold">Order</th>
                                        <th class="px-5 py-3 font-semibold">Customer</th>
                                        <th class="px-5 py-3 font-semibold text-right font-mono">Total</th>
                                        <th class="px-5 py-3 font-semibold">Status</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-(--border-color)">
                                    <tr v-for="o in recentOrders" :key="o.id"
                                        class="hover:bg-(--bg-muted) transition-colors">
                                        <td class="px-5 py-3 text-xs font-mono text-(--text-heading) font-semibold">{{
                                            o.number }}</td>
                                        <td class="px-5 py-3 text-xs truncate max-w-[120px]">{{ o.customer_name }}</td>
                                        <td
                                            class="px-5 py-3 text-xs text-right font-mono font-semibold text-(--text-heading)">
                                            ${{ o.total.toFixed(2) }}</td>
                                        <td class="px-5 py-3">
                                            <Badge :variant="orderStatusVariant(o.status)">{{ o.status }}</Badge>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Pending leave requests -->
                    <div class="glass-card rounded-2xl">
                        <header class="flex items-center justify-between px-5 py-4 border-b border-(--border-color)">
                            <h3 class="flex items-center gap-2">
                                <span class="w-1.5 h-4 rounded-sm bg-(--color-warning)" />
                                Pending leave requests
                            </h3>
                            <NuxtLink to="/hrm/timeoff/leaves" class="text-xs font-semibold text-(--color-primary) hover:underline">
                                View all →</NuxtLink>
                        </header>
                        <div v-if="loading" class="p-5 space-y-3">
                            <div v-for="i in 5" :key="i" class="h-8 rounded dash-skeleton" />
                        </div>
                        <div v-else-if="!recentLeaves.length" class="px-5 py-8 text-center text-xs text-(--text-muted)">
                            No pending requests</div>
                        <div v-else class="overflow-x-auto">
                            <table class="w-full text-left">
                                <thead>
                                    <tr
                                        class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                        <th class="px-5 py-3 font-semibold">Employee</th>
                                        <th class="px-5 py-3 font-semibold">Type</th>
                                        <th class="px-5 py-3 font-semibold text-right font-mono">Days</th>
                                        <th class="px-5 py-3 font-semibold">Starts</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-(--border-color)">
                                    <tr v-for="l in recentLeaves" :key="l.id"
                                        class="hover:bg-(--bg-muted) transition-colors">
                                        <td class="px-5 py-3 text-xs font-semibold text-(--text-heading)">{{
                                            l.employee_name || '—' }}</td>
                                        <td class="px-5 py-3 text-xs">{{ l.type }}</td>
                                        <td
                                            class="px-5 py-3 text-xs text-right font-mono font-semibold text-(--text-heading)">
                                            {{ l.days }}d</td>
                                        <td class="px-5 py-3 text-xs text-(--text-muted) font-mono">{{ formatDate(l.start_date) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                </section>

            </template>

            <!-- ====================== CUSTOMER DASHBOARD ====================== -->
            <template v-else>

                <!-- Module-aware KPI row -->
                <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">

                    <!-- HRM block -->
                    <template v-if="hasModule('hrm')">
                        <div v-if="authStore.hasPermission('hrm.employee.read')" class="glass-card rounded-2xl p-5 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">My
                                    Team</span>
                                <span class="w-7 h-7 rounded-lg badge-soft-primary flex items-center justify-center">
                                    <i class="ti ti-users text-sm" />
                                </span>
                            </div>
                            <p class="text-2xl font-bold text-(--text-heading) font-mono">
                                <span v-if="loading" class="inline-block w-12 h-6 rounded dash-skeleton" />
                                <span v-else>{{ summary?.kpis?.employees?.active ?? '—' }}</span>
                            </p>
                            <p class="text-xxs text-(--text-muted)">{{ summary?.kpis?.employees?.on_leave ?? 0 }} on
                                leave today</p>
                        </div>
                        <div v-if="authStore.hasPermission('hrm.leave.read')" class="glass-card rounded-2xl p-5 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Leave
                                    Requests</span>
                                <span class="w-7 h-7 rounded-lg badge-soft-warning flex items-center justify-center">
                                    <i class="ti ti-calendar-event text-sm" />
                                </span>
                            </div>
                            <p class="text-2xl font-bold text-(--text-heading) font-mono">
                                <span v-if="loading" class="inline-block w-8 h-6 rounded dash-skeleton" />
                                <span v-else>{{ summary?.kpis?.leave?.pending ?? '—' }}</span>
                            </p>
                            <p class="text-xxs text-(--text-muted)">
                                {{ summary?.kpis?.leave?.approved_mtd ?? 0 }} approved MTD
                            </p>
                        </div>
                    </template>

                    <!-- Sales block -->
                    <template v-if="hasModule('sales')">
                        <div v-if="authStore.hasPermission('sales.orders.read')" class="glass-card rounded-2xl p-5 space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Revenue
                                    MTD</span>
                                <span class="w-7 h-7 rounded-lg badge-soft-success flex items-center justify-center">
                                    <i class="ti ti-currency-dollar text-sm" />
                                </span>
                            </div>
                            <p class="text-2xl font-bold text-(--text-heading) font-mono">
                                <span v-if="loading" class="inline-block w-16 h-6 rounded dash-skeleton" />
                                <span v-else>{{ formatCurrency(summary?.kpis?.sales?.revenue_mtd ?? 0) }}</span>
                            </p>
                            <p class="text-xxs" :class="revenueChangeClass">
                                {{ revenueChangePct >= 0 ? '+' : '' }}{{ revenueChangePct }}% vs last month
                            </p>
                        </div>
                        <div v-if="authStore.hasPermission('sales.customers.read')" class="glass-card rounded-2xl p-5 space-y-2">
                            <div class="flex items-center justify-between">
                                <span
                                    class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Customers</span>
                                <span class="w-7 h-7 rounded-lg badge-soft-info flex items-center justify-center">
                                    <i class="ti ti-address-book text-sm" />
                                </span>
                            </div>
                            <p class="text-2xl font-bold text-(--text-heading) font-mono">
                                <span v-if="loading" class="inline-block w-12 h-6 rounded dash-skeleton" />
                                <span v-else>{{ summary?.kpis?.sales?.active_customers ?? '—' }}</span>
                            </p>
                            <p class="text-xxs text-(--text-muted)">{{ summary?.kpis?.sales?.open_leads ?? 0 }} open
                                leads</p>
                        </div>
                    </template>

                    <!-- Fallback when no relevant modules active -->
                    <div v-if="!hasModule('hrm') && !hasModule('sales')"
                        class="col-span-full glass-card rounded-2xl p-8 text-center">
                        <i class="ti ti-puzzle-off text-3xl text-(--text-muted)" />
                        <p class="text-sm text-(--text-muted) mt-3">No active modules yet.</p>
                        <p class="text-xs text-(--text-muted) mt-1">Contact your administrator to enable modules for
                            this workspace.</p>
                    </div>

                </section>

                <!-- Revenue trend (sales module) -->
                <div v-if="hasModule('sales') && authStore.hasPermission('sales.orders.read')" class="glass-card rounded-2xl p-6">
                    <header
                        class="flex flex-col sm:flex-row justify-between sm:items-center gap-3 pb-5 border-b border-(--border-color)">
                        <div>
                            <h3 class="flex items-center gap-2">
                                <span class="w-1.5 h-4 rounded-sm bg-(--color-primary)" />
                                Revenue trend (last 7 days)
                            </h3>
                            <p class="text-xs text-(--text-muted) mt-0.5">Daily paid invoice totals</p>
                        </div>
                        <NuxtLink to="/sales/orders" class="btn btn-ghost text-xs gap-1.5">
                            <i class="ti ti-external-link text-sm" />View orders
                        </NuxtLink>
                    </header>
                    <div v-if="loading" class="mt-5 grid grid-cols-7 gap-2 items-end h-36">
                        <div v-for="i in 7" :key="i" class="flex flex-col items-center gap-1">
                            <div class="w-full rounded-md dash-skeleton" :style="{ height: `${30 + i * 9}%` }" />
                            <span class="h-2.5 w-6 rounded dash-skeleton" />
                        </div>
                    </div>
                    <div v-else class="mt-5 grid grid-cols-7 gap-2 items-end h-36">
                        <div v-for="bar in revenueBars" :key="bar.label" class="flex flex-col items-center gap-1 group"
                            :title="`${bar.label}: ${formatCurrency(bar.amount)}`">
                            <div class="w-full bg-(--bg-muted) rounded-md relative overflow-hidden"
                                :style="{ height: `${bar.height}%` }">
                                <div
                                    class="absolute inset-0 bg-linear-to-t from-(--color-primary) to-(--color-info) opacity-90 group-hover:opacity-100 transition-opacity" />
                            </div>
                            <span class="text-[10px] text-(--text-muted) font-mono">{{ bar.label }}</span>
                        </div>
                    </div>
                </div>

                <!-- HRM quick table -->
                <div v-if="hasModule('hrm') && authStore.hasPermission('hrm.leave.read')" class="glass-card rounded-2xl">
                    <header class="flex items-center justify-between px-5 py-4 border-b border-(--border-color)">
                        <h3 class="flex items-center gap-2">
                            <span class="w-1.5 h-4 rounded-sm bg-(--color-warning)" />
                            Pending leave requests
                        </h3>
                        <NuxtLink to="/hrm/timeoff/leaves" class="text-xs font-semibold text-(--color-primary) hover:underline">View
                            all →
                        </NuxtLink>
                    </header>
                    <div v-if="loading" class="p-5 space-y-3">
                        <div v-for="i in 5" :key="i" class="h-8 rounded dash-skeleton" />
                    </div>
                    <div v-else-if="!recentLeaves.length" class="px-5 py-8 text-center text-xs text-(--text-muted)">No
                        pending leave
                        requests</div>
                    <div v-else class="overflow-x-auto">
                        <table class="w-full text-left">
                            <thead>
                                <tr
                                    class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                    <th class="px-5 py-3 font-semibold">Employee</th>
                                    <th class="px-5 py-3 font-semibold">Type</th>
                                    <th class="px-5 py-3 font-semibold text-right">Days</th>
                                    <th class="px-5 py-3 font-semibold">Starts</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-(--border-color)">
                                <tr v-for="l in recentLeaves" :key="l.id"
                                    class="hover:bg-(--bg-muted) transition-colors">
                                    <td class="px-5 py-3 text-xs font-semibold text-(--text-heading)">{{ l.employee_name || '—' }}</td>
                                    <td class="px-5 py-3 text-xs">{{ l.type }}</td>
                                    <td
                                        class="px-5 py-3 text-xs text-right font-mono font-semibold text-(--text-heading)">
                                        {{ l.days }}d
                                    </td>
                                    <td class="px-5 py-3 text-xs text-(--text-muted) font-mono">{{ formatDate(l.start_date) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </template>

        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useTenantStore } from '~/stores/tenant'
import { useDateFormat } from '~/composables/useDateFormat'

const authStore = useAuthStore()
const { formatDate } = useDateFormat()
const tenantStore = useTenantStore()

const { summary, loading, error, load, hasModule, revenueBars, headcountBars, formatCurrency } = useDashboard()

const today = new Date().toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })

const recentOrders = computed(() => summary.value?.recent?.orders ?? [])
const recentLeaves = computed(() => summary.value?.recent?.leaves ?? [])

const revenueChangePct = computed(() => summary.value?.kpis?.sales?.revenue_change_pct ?? 0)
const revenueChangeClass = computed(() =>
    revenueChangePct.value >= 0 ? 'text-(--color-success)' : 'text-(--color-danger)'
)

const orderStatusVariant = (status: string): 'success' | 'warning' | 'danger' | 'info' | 'secondary' => {
    const map: Record<string, 'success' | 'warning' | 'danger' | 'info' | 'secondary'> = {
        confirmed: 'success',
        paid: 'success',
        new: 'info',
        pending: 'warning',
        cancelled: 'danger',
        refunded: 'danger',
        shipped: 'info',
        shipping: 'info',
    }
    return map[status] ?? 'secondary'
}

const refresh = () => load(true)

onMounted(() => load())
</script>

<style scoped>
.dash-skeleton {
    background: linear-gradient(90deg, var(--bg-muted) 25%, var(--border-color) 50%, var(--bg-muted) 75%);
    background-size: 200% 100%;
    animation: dash-shimmer 1.4s ease infinite;
    display: inline-block;
}

@keyframes dash-shimmer {
    0% {
        background-position: 200% 0;
    }

    100% {
        background-position: -200% 0;
    }
}
</style>
