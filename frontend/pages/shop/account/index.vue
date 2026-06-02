<template>
    <NuxtLayout name="shop">
        <section v-if="!shopAuth.isAuthenticated" class="py-12 text-center">
            <p class="text-sm text-(--text-muted)">
                Please <NuxtLink to="/shop/auth/login" class="text-(--color-primary)">log in</NuxtLink> to view your account.
            </p>
        </section>

        <section v-else class="space-y-6">
            <header>
                <h1 class="text-xl font-semibold text-(--text-heading)">My account</h1>
                <p class="text-xs text-(--text-muted) mt-1">{{ shopAuth.shopper?.email }}</p>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="glass-card rounded-2xl p-5 space-y-3">
                    <header class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-(--text-heading)">Recent orders</h3>
                        <span class="text-xxs text-(--text-muted)">{{ orders.length }} total</span>
                    </header>
                    <div v-if="loadingOrders" class="py-8 flex justify-center">
                        <span class="w-6 h-6 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                    </div>
                    <div v-else-if="orders.length === 0" class="text-xs text-(--text-muted) py-6 text-center">No orders yet.</div>
                    <NuxtLink v-for="o in orders.slice(0, 5)" :key="o.id" :to="`/shop/order/${o.id}`"
                        class="flex justify-between items-center text-xs py-2 border-b border-(--border-color) last:border-0 hover:text-(--color-primary)">
                        <div>
                            <div class="font-mono">{{ o.orderNumber }}</div>
                            <div class="text-(--text-muted) text-xxs">{{ formatDate(o.placedAt) }}</div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="badge-soft-secondary text-xxs">{{ o.status }}</span>
                            <span class="font-mono">{{ formatMoney(o.totalAmount) }}</span>
                        </div>
                    </NuxtLink>
                </div>

                <div class="glass-card rounded-2xl p-5 space-y-3">
                    <header class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-(--text-heading)">Addresses</h3>
                        <button class="text-xs text-(--color-primary) hover:underline" @click="showAddForm = !showAddForm">
                            <i class="ti ti-plus" />Add
                        </button>
                    </header>

                    <div v-if="loadingAddresses" class="py-8 flex justify-center">
                        <span class="w-6 h-6 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                    </div>

                    <div v-else-if="addresses.length === 0 && !showAddForm" class="text-xs text-(--text-muted) py-6 text-center">No saved addresses.</div>

                    <div v-for="a in addresses" :key="a.id"
                        class="text-xs py-2 border-b border-(--border-color) last:border-0 flex justify-between items-start">
                        <div>
                            <div class="text-(--text-heading)">{{ a.label || a.recipientName }}</div>
                            <div class="text-(--text-muted)">{{ a.line1 }}, {{ a.city }} {{ a.postalCode }}</div>
                            <div class="flex gap-1 mt-1">
                                <span v-if="a.isDefaultShipping" class="badge-soft-primary text-xxs">Default shipping</span>
                                <span v-if="a.isDefaultBilling" class="badge-soft-secondary text-xxs">Default billing</span>
                            </div>
                        </div>
                        <button class="text-(--color-danger) text-xs" @click="removeAddress(a.id)">
                            <i class="ti ti-trash" />
                        </button>
                    </div>

                    <form v-if="showAddForm" class="space-y-2 pt-2" @submit.prevent="saveAddress">
                        <input v-model="newAddress.recipient_name" required placeholder="Recipient name" class="form-control text-sm" />
                        <input v-model="newAddress.line1" required placeholder="Street address" class="form-control text-sm" />
                        <div class="grid grid-cols-2 gap-2">
                            <input v-model="newAddress.city" required placeholder="City" class="form-control text-sm" />
                            <input v-model="newAddress.postal_code" placeholder="Postal code" class="form-control text-sm" />
                        </div>
                        <input v-model="newAddress.country" required maxlength="2" placeholder="Country (2-letter ISO)" class="form-control text-sm uppercase" />
                        <label class="flex items-center gap-2 text-xs">
                            <input v-model="newAddress.is_default_shipping" type="checkbox" /> Make default shipping
                        </label>
                        <button class="btn btn-soft-primary text-xs w-full" type="submit" :disabled="savingAddress">
                            {{ savingAddress ? 'Saving...' : 'Save address' }}
                        </button>
                    </form>
                </div>
            </div>
        </section>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useShop, type EcomOrder } from '~/composables/useShop'
import { useShopAuthStore } from '~/stores/shop-auth'

definePageMeta({ layout: false })

const shopAuth = useShopAuthStore()
const shop = useShop()

const orders = ref<EcomOrder[]>([])
const addresses = ref<any[]>([])
const loadingOrders = ref(false)
const loadingAddresses = ref(false)
const showAddForm = ref(false)
const savingAddress = ref(false)
const newAddress = ref({ recipient_name: '', line1: '', city: '', postal_code: '', country: 'US', is_default_shipping: false })

const formatMoney = (n: number | null | undefined) =>
    (Number.isFinite(Number(n)) ? Number(n) : 0).toLocaleString(undefined, { style: 'currency', currency: 'USD' })
const formatDate = (iso: string | null) => iso ? new Date(iso).toLocaleDateString() : '-'

async function load() {
    loadingOrders.value = true
    loadingAddresses.value = true
    try {
        const o = await shop.orders.list({ limit: 10 })
        orders.value = o.data ?? []
    } catch {}
    finally { loadingOrders.value = false }
    try {
        const a = await shop.addresses.list()
        addresses.value = a.data ?? []
    } catch {}
    finally { loadingAddresses.value = false }
}

async function saveAddress() {
    savingAddress.value = true
    try {
        await shop.addresses.create({ ...newAddress.value, country: newAddress.value.country.toUpperCase() })
        showAddForm.value = false
        newAddress.value = { recipient_name: '', line1: '', city: '', postal_code: '', country: 'US', is_default_shipping: false }
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

onMounted(async () => {
    shopAuth.initFromStorage()
    if (shopAuth.accessToken) await shopAuth.refreshMe()
    if (shopAuth.isAuthenticated) await load()
})
</script>
