<template>
    <NuxtLayout name="shop">
        <section v-if="!shopAuth.isAuthenticated"
            class="glass-card rounded-2xl py-16 px-6 text-center max-w-md mx-auto">
            <span class="w-14 h-14 mx-auto rounded-full bg-(--color-primary)/10 text-(--color-primary) inline-flex items-center justify-center">
                <i class="ti ti-user text-2xl" />
            </span>
            <h1 class="text-base font-semibold text-(--text-heading) mt-4">
                Sign in to view your account
            </h1>
            <p class="text-xs text-(--text-muted) mt-2 max-w-xs mx-auto">
                Your orders, addresses, and saved details live here once you're signed in.
            </p>
            <div class="flex items-center justify-center gap-2 mt-6">
                <NuxtLink to="/shop/auth/login?redirect=/shop/account"
                    class="btn btn-primary text-xs inline-flex items-center gap-2 rounded-full">
                    <i class="ti ti-login" />
                    Sign in
                </NuxtLink>
                <NuxtLink to="/shop/auth/register?redirect=/shop/account"
                    class="btn btn-ghost text-xs inline-flex items-center gap-2 rounded-full">
                    <i class="ti ti-user-plus" />
                    Create account
                </NuxtLink>
            </div>
        </section>

        <div v-else class="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-6">
            <aside class="space-y-3 lg:sticky lg:top-20 lg:self-start">
                <div class="glass-card rounded-2xl p-4 flex items-center gap-3">
                    <div class="account-avatar shrink-0">{{ initials }}</div>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-(--text-heading) truncate">{{ displayName }}</p>
                        <p class="text-xxs text-(--text-muted) truncate">{{ shopAuth.shopper?.email }}</p>
                        <p v-if="memberSince" class="text-[10px] text-(--text-muted) mt-0.5 truncate">
                            Member since {{ memberSince }}
                        </p>
                    </div>
                </div>

                <nav class="account-nav glass-card rounded-2xl p-2"
                    role="tablist" aria-label="Account sections">
                    <button v-for="t in tabs" :key="t.id"
                        type="button"
                        role="tab"
                        :aria-selected="activeTab === t.id"
                        class="account-nav-link text-left"
                        :class="activeTab === t.id ? 'account-nav-link--active' : ''"
                        @click="setActive(t.id)">
                        <i :class="['ti', t.icon, 'text-base']" />
                        <span class="flex-1 truncate">{{ t.label }}</span>
                        <span v-if="t.count !== undefined"
                            class="text-[10px] font-mono text-(--text-muted) px-1.5 py-0.5 rounded-full bg-(--bg-muted)">
                            {{ t.count }}
                        </span>
                    </button>
                </nav>

                <button class="btn btn-danger text-xs w-full inline-flex justify-center gap-2 rounded-full"
                    @click="logout">
                    <i class="ti ti-logout" />
                    Sign out
                </button>
            </aside>

            <main class="space-y-6 min-w-0">
                <section v-if="activeTab === 'overview'" class="space-y-6">
                    <header>
                        <h1 class="text-xl font-semibold text-(--text-heading)">Hello, {{ shopAuth.shopper?.firstName || 'there' }}</h1>
                        <p class="text-xs text-(--text-muted) mt-1">Here's a quick look at your account.</p>
                    </header>

                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <article v-for="kpi in kpis" :key="kpi.label"
                            class="glass-card rounded-2xl p-4 space-y-2 cursor-pointer hover:border-(--color-primary)/40 transition-colors"
                            @click="setActive(kpi.tabHint)">
                            <div class="flex items-center justify-between">
                                <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">{{ kpi.label }}</span>
                                <span class="w-7 h-7 rounded-lg flex items-center justify-center" :class="kpi.badgeClass">
                                    <i :class="['ti', kpi.icon, 'text-sm']" />
                                </span>
                            </div>
                            <p class="text-xl font-bold text-(--text-heading) font-mono leading-none">{{ kpi.value }}</p>
                            <p class="text-xxs text-(--text-muted)">{{ kpi.sub }}</p>
                        </article>
                    </div>

                    <div class="glass-card rounded-2xl p-5 space-y-4">
                        <header class="flex items-center justify-between">
                            <div>
                                <h2 class="text-sm font-semibold text-(--text-heading)">Latest orders</h2>
                                <p class="text-xxs text-(--text-muted) mt-0.5">{{ orderHint }}</p>
                            </div>
                            <button v-if="orders.length > 3" class="text-xxs text-(--color-primary) hover:underline"
                                @click="setActive('orders')">
                                View all orders
                            </button>
                        </header>
                        <OrderList :orders="orders.slice(0, 3)" :loading="loadingOrders" />
                    </div>
                </section>

                <section v-else-if="activeTab === 'orders'" class="space-y-6">
                    <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                        <div>
                            <h1 class="text-xl font-semibold text-(--text-heading)">Orders</h1>
                            <p class="text-xs text-(--text-muted) mt-1">{{ orderHint }}</p>
                        </div>
                        <NuxtLink to="/shop/products"
                            class="btn btn-soft-primary text-xs inline-flex items-center gap-2 rounded-full self-start">
                            <i class="ti ti-arrow-right" />
                            Keep shopping
                        </NuxtLink>
                    </header>

                    <section class="glass-card rounded-2xl p-5">
                        <OrderList :orders="orders" :loading="loadingOrders" />
                    </section>
                </section>

                <section v-else-if="activeTab === 'addresses'" class="space-y-6">
                    <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                        <div>
                            <h1 class="text-xl font-semibold text-(--text-heading)">Addresses</h1>
                            <p class="text-xs text-(--text-muted) mt-1">
                                {{ addresses.length }} on file. These prefill at checkout.
                            </p>
                        </div>
                        <button v-if="!showAddForm"
                            class="btn btn-primary text-xs inline-flex items-center gap-2 rounded-full self-start"
                            @click="openAddressForm">
                            <i class="ti ti-plus" />
                            New address
                        </button>
                        <button v-else
                            class="btn btn-ghost text-xs inline-flex items-center gap-2 rounded-full self-start"
                            @click="showAddForm = false">
                            <i class="ti ti-x" />
                            Cancel
                        </button>
                    </header>

                    <div v-if="loadingAddresses" class="py-12 flex justify-center">
                        <span class="w-7 h-7 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                    </div>

                    <div v-else-if="addresses.length === 0 && !showAddForm"
                        class="glass-card rounded-2xl py-16 text-center">
                        <i class="ti ti-map-pin-off text-4xl text-(--text-muted)" />
                        <p class="text-xs text-(--text-muted) mt-3">No saved addresses yet.</p>
                        <button class="btn btn-soft-primary text-xs mt-4 inline-flex items-center gap-2 rounded-full"
                            @click="openAddressForm">
                            <i class="ti ti-plus" />
                            Add your first address
                        </button>
                    </div>

                    <div v-else-if="addresses.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <article v-for="a in addresses" :key="a.id"
                            class="glass-card rounded-2xl p-4 space-y-2 relative">
                            <button
                                class="absolute top-3 right-3 w-7 h-7 rounded-full hover:bg-(--color-danger)/10 text-(--text-muted) hover:text-(--color-danger) inline-flex items-center justify-center"
                                title="Remove address" @click="removeAddress(a.id)">
                                <i class="ti ti-trash text-sm" />
                            </button>
                            <div class="pr-8">
                                <p class="text-sm font-semibold text-(--text-heading) truncate">
                                    {{ a.label || a.recipientName }}
                                </p>
                                <p class="text-xxs text-(--text-muted) leading-relaxed mt-1">
                                    {{ a.line1 }}<span v-if="a.line2">, {{ a.line2 }}</span><br />
                                    {{ a.city }}<span v-if="a.state">, {{ a.state }}</span>
                                    <span v-if="a.postalCode"> {{ a.postalCode }}</span>
                                    <span v-if="a.country"> &bull; {{ a.country }}</span>
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center gap-1">
                                <span v-if="a.isDefaultShipping"
                                    class="badge-soft-primary text-[10px] px-2 py-0.5 rounded-full inline-flex items-center gap-1">
                                    <i class="ti ti-truck text-[9px]" />
                                    Default shipping
                                </span>
                                <span v-if="a.isDefaultBilling"
                                    class="badge-soft-secondary text-[10px] px-2 py-0.5 rounded-full inline-flex items-center gap-1">
                                    <i class="ti ti-receipt text-[9px]" />
                                    Default billing
                                </span>
                            </div>
                        </article>
                    </div>

                    <form v-if="showAddForm" class="glass-card rounded-2xl p-5 space-y-3"
                        @submit.prevent="saveAddress">
                        <h2 class="text-sm font-semibold text-(--text-heading)">New address</h2>
                        <input v-model="newAddress.recipient_name" required placeholder="Recipient name"
                            class="form-control text-xs rounded-full" />
                        <input v-model="newAddress.line1" required placeholder="Street address"
                            class="form-control text-xs rounded-full" />
                        <div class="grid grid-cols-2 gap-2">
                            <input v-model="newAddress.city" required placeholder="City"
                                class="form-control text-xs rounded-full" />
                            <input v-model="newAddress.postal_code" placeholder="Postal code"
                                class="form-control text-xs rounded-full" />
                        </div>
                        <input v-model="newAddress.country" required maxlength="2"
                            placeholder="Country (2-letter ISO)"
                            class="form-control text-xs rounded-full uppercase" />
                        <label class="flex items-center gap-2 text-xs text-(--text-body) pt-1">
                            <input v-model="newAddress.is_default_shipping" type="checkbox" class="form-checkbox" />
                            Set as default shipping
                        </label>
                        <button class="btn btn-primary text-xs w-full inline-flex justify-center gap-2 rounded-full"
                            type="submit" :disabled="savingAddress">
                            <i :class="['ti', savingAddress ? 'ti-loader-2 animate-spin' : 'ti-check']" />
                            {{ savingAddress ? 'Saving...' : 'Save address' }}
                        </button>
                    </form>
                </section>

                <section v-else-if="activeTab === 'profile'" class="space-y-6">
                    <header>
                        <h1 class="text-xl font-semibold text-(--text-heading)">Profile</h1>
                        <p class="text-xs text-(--text-muted) mt-1">Personal details we'll use on orders + shipping.</p>
                    </header>

                    <form class="glass-card rounded-2xl p-5 space-y-4 max-w-lg" @submit.prevent="saveProfile">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-semibold uppercase tracking-wider text-(--text-muted)">First name</label>
                                <input v-model="profileForm.first_name" class="form-control text-sm rounded-full" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-semibold uppercase tracking-wider text-(--text-muted)">Last name</label>
                                <input v-model="profileForm.last_name" class="form-control text-sm rounded-full" />
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xxs font-semibold uppercase tracking-wider text-(--text-muted)">Email</label>
                            <input :value="shopAuth.shopper?.email" type="email" readonly
                                class="form-control text-sm rounded-full bg-(--bg-muted)/60 text-(--text-muted)" />
                            <p class="text-[10px] text-(--text-muted) italic">Email is the account identifier and can't be changed.</p>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xxs font-semibold uppercase tracking-wider text-(--text-muted)">Phone</label>
                            <input v-model="profileForm.phone" type="tel" class="form-control text-sm rounded-full" />
                        </div>
                        <p v-if="profileError"
                            class="badge-soft-danger px-3 py-2 rounded text-xs inline-flex items-center gap-2">
                            <i class="ti ti-alert-circle" />
                            {{ profileError }}
                        </p>
                        <p v-if="profileSaved"
                            class="badge-soft-success px-3 py-2 rounded text-xs inline-flex items-center gap-2">
                            <i class="ti ti-check" />
                            Profile updated.
                        </p>
                        <p v-if="!hasProfileEndpoint"
                            class="text-xxs text-(--text-muted) italic inline-flex items-center gap-1.5">
                            <i class="ti ti-info-circle text-[12px]" />
                            Profile editing requires a backend endpoint not yet shipped. Submit is a no-op for now.
                        </p>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="submit" class="btn btn-primary text-xs rounded-full"
                                :disabled="savingProfile">
                                <i :class="['ti', savingProfile ? 'ti-loader-2 animate-spin' : 'ti-check']" />
                                {{ savingProfile ? 'Saving...' : 'Save changes' }}
                            </button>
                        </div>
                    </form>
                </section>
            </main>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useShop, type EcomOrder } from '~/composables/useShop'
import { useShopAuthStore } from '~/stores/shop-auth'
import { useCartStateStore } from '~/stores/cart-state'

definePageMeta({ layout: false })

useHead({ title: 'My account | Storefront' })

const route = useRoute()
const router = useRouter()
const shopAuth = useShopAuthStore()
const cartState = useCartStateStore()
const shop = useShop()

type TabId = 'overview' | 'orders' | 'addresses' | 'profile'
const VALID_TABS: TabId[] = ['overview', 'orders', 'addresses', 'profile']

const orders = ref<EcomOrder[]>([])
const addresses = ref<any[]>([])
const loadingOrders = ref(false)
const loadingAddresses = ref(false)
const showAddForm = ref(false)
const savingAddress = ref(false)
const newAddress = ref({
    recipient_name: '',
    line1: '',
    city: '',
    postal_code: '',
    country: 'US',
    is_default_shipping: false,
})

const savingProfile = ref(false)
const profileError = ref('')
const profileSaved = ref(false)
const profileForm = ref({ first_name: '', last_name: '', phone: '' })

// No /shop/auth/profile PUT endpoint on the backend yet.
const hasProfileEndpoint = false

const activeTab = ref<TabId>('overview')

function hashToTab(hash: string): TabId {
    const stripped = (hash || '').replace(/^#/, '') as TabId
    return VALID_TABS.includes(stripped) ? stripped : 'overview'
}

function setActive(tab: TabId) {
    activeTab.value = tab
    router.replace({ hash: `#${tab}` })
}

watch(() => route.hash, (h) => { activeTab.value = hashToTab(h) })

const displayName = computed(() => {
    const s = shopAuth.shopper
    if (!s) return 'My account'
    const full = [s.firstName, s.lastName].filter(Boolean).join(' ').trim()
    return full || s.email || 'My account'
})

const initials = computed(() => {
    const s = shopAuth.shopper
    if (!s) return '?'
    const first = (s.firstName || '').trim()
    const last = (s.lastName || '').trim()
    if (first || last) {
        return `${(first[0] || '').toUpperCase()}${(last[0] || '').toUpperCase()}` || '?'
    }
    return (s.email || '?').charAt(0).toUpperCase()
})

const memberSince = computed(() => {
    const raw = (shopAuth.shopper as any)?.createdAt ?? (shopAuth.shopper as any)?.created_at
    if (!raw) return null
    const d = new Date(raw)
    return Number.isFinite(d.getTime())
        ? d.toLocaleDateString(undefined, { month: 'short', year: 'numeric' })
        : null
})

const totalSpent = computed(() =>
    orders.value.reduce((s, o) => s + Number(o.totalAmount ?? 0), 0)
)

const kpis = computed(() => [
    {
        label: 'Orders',
        value: orders.value.length.toLocaleString(),
        sub: orders.value.length === 1 ? 'placed' : 'placed',
        icon: 'ti-package',
        badgeClass: 'badge-soft-primary',
        tabHint: 'orders' as TabId,
    },
    {
        label: 'Total spent',
        value: formatMoney(totalSpent.value),
        sub: 'lifetime',
        icon: 'ti-coin',
        badgeClass: 'badge-soft-success',
        tabHint: 'orders' as TabId,
    },
    {
        label: 'Addresses',
        value: addresses.value.length.toLocaleString(),
        sub: addresses.value.length === 1 ? 'on file' : 'on file',
        icon: 'ti-map-pin',
        badgeClass: 'badge-soft-info',
        tabHint: 'addresses' as TabId,
    },
    {
        label: 'In cart',
        value: cartState.count.toLocaleString(),
        sub: cartState.count === 1 ? 'item ready' : 'items ready',
        icon: 'ti-shopping-cart',
        badgeClass: 'badge-soft-secondary',
        tabHint: 'overview' as TabId,
    },
])

const tabs = computed(() => [
    { id: 'overview' as TabId, label: 'Overview', icon: 'ti-layout-dashboard' },
    { id: 'orders' as TabId, label: 'Orders', icon: 'ti-package', count: orders.value.length },
    { id: 'addresses' as TabId, label: 'Addresses', icon: 'ti-map-pin', count: addresses.value.length },
    { id: 'profile' as TabId, label: 'Profile', icon: 'ti-user' },
])

const orderHint = computed(() => {
    const n = orders.value.length
    if (n === 0) return 'No orders yet. Browse the catalog to place your first.'
    if (n === 1) return 'Your only order so far.'
    return `${n} orders total.`
})

function formatMoney(n: number | null | undefined): string {
    return (Number.isFinite(Number(n)) ? Number(n) : 0).toLocaleString(undefined, {
        style: 'currency',
        currency: 'USD',
    })
}

function openAddressForm() {
    showAddForm.value = true
    newAddress.value = {
        recipient_name: '',
        line1: '',
        city: '',
        postal_code: '',
        country: 'US',
        is_default_shipping: addresses.value.length === 0,
    }
}

async function load() {
    loadingOrders.value = true
    loadingAddresses.value = true
    try {
        const o = await shop.orders.list({ limit: 50 })
        orders.value = o.data ?? []
    } catch { /* swallow */ }
    finally { loadingOrders.value = false }

    try {
        const a = await shop.addresses.list()
        addresses.value = a.data ?? []
    } catch { /* swallow */ }
    finally { loadingAddresses.value = false }
}

async function saveAddress() {
    savingAddress.value = true
    try {
        await shop.addresses.create({
            ...newAddress.value,
            country: newAddress.value.country.toUpperCase(),
        })
        showAddForm.value = false
        await load()
    } finally {
        savingAddress.value = false
    }
}

async function removeAddress(id: string) {
    if (!confirm('Remove this address?')) return
    await shop.addresses.destroy(id)
    await load()
}

async function saveProfile() {
    if (!hasProfileEndpoint) {
        profileSaved.value = true
        setTimeout(() => { profileSaved.value = false }, 2400)
        return
    }
    savingProfile.value = true
    // Wire to backend endpoint when shipped: PUT /shop/auth/profile
    savingProfile.value = false
}

async function logout() {
    await shopAuth.logout()
    cartState.reset()
    navigateTo('/shop/auth/login')
}

onMounted(async () => {
    activeTab.value = hashToTab(route.hash)
    if (shopAuth.accessToken && !shopAuth.shopper) {
        await shopAuth.refreshMe()
    }
    if (shopAuth.isAuthenticated) {
        profileForm.value = {
            first_name: shopAuth.shopper?.firstName ?? '',
            last_name: shopAuth.shopper?.lastName ?? '',
            phone: shopAuth.shopper?.phone ?? '',
        }
        await load()
    }
})
</script>


<style scoped>
.account-avatar {
    width: 48px;
    height: 48px;
    border-radius: 9999px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 15px;
    font-weight: 700;
    color: var(--color-primary);
    background: rgb(var(--color-primary-rgb) / 0.12);
    box-shadow: inset 0 0 0 1px rgb(var(--color-primary-rgb) / 0.3);
}

.account-nav {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

@media (max-width: 1023px) {
    .account-nav {
        flex-direction: row;
        overflow-x: auto;
        scrollbar-width: thin;
    }
}

.account-nav-link {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 8px 12px;
    border-radius: 9999px;
    border: 0;
    background: transparent;
    font-size: 13px;
    font-weight: 500;
    color: var(--text-body);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
    white-space: nowrap;
}

.account-nav-link:hover {
    background: var(--bg-muted);
    color: var(--text-heading);
}

.account-nav-link--active {
    background: rgb(var(--color-primary-rgb) / 0.12);
    color: var(--color-primary);
    font-weight: 600;
    box-shadow: inset 0 0 0 1px rgb(var(--color-primary-rgb) / 0.25);
}
</style>
