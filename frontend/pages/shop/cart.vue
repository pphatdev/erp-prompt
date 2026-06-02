<template>
    <NuxtLayout name="shop">
        <section class="space-y-6">
            <header class="flex items-baseline justify-between gap-4 flex-wrap">
                <h1 class="text-xl font-semibold text-(--text-heading)">Your cart</h1>
                <NuxtLink to="/shop/products" class="text-xs text-(--color-primary) hover:underline inline-flex items-center gap-1">
                    <i class="ti ti-arrow-left text-[12px]" />
                    Continue browsing
                </NuxtLink>
            </header>

            <div v-if="loading" class="py-16 flex justify-center">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
            </div>

            <div v-else-if="!cart || (cart.items?.length ?? 0) === 0"
                class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-shopping-cart-off text-5xl text-(--text-muted)" />
                <h2 class="text-sm font-semibold text-(--text-heading) mt-3">Your cart is empty</h2>
                <p class="text-xs text-(--text-muted) mt-1">Add a product from the catalog to start checking out.</p>
                <NuxtLink to="/shop/products" class="btn btn-soft-primary text-xs mt-4 inline-flex rounded-full">
                    <i class="ti ti-arrow-right" />
                    Start shopping
                </NuxtLink>
            </div>

            <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-3">
                    <div v-for="item in cart.items" :key="item.id"
                        class="glass-card rounded-xl p-4 flex flex-col sm:flex-row gap-4 transition-opacity"
                        :class="{ 'opacity-50 pointer-events-none': pendingItemId === item.id }">
                        <div class="w-full sm:w-28 h-28 rounded-lg bg-(--bg-muted) overflow-hidden flex-shrink-0">
                            <img v-if="item.productImage" :src="item.productImage"
                                :alt="item.productName || item.productSku"
                                class="w-full h-full object-cover" />
                            <div v-else class="w-full h-full flex items-center justify-center">
                                <i class="ti ti-package text-3xl text-(--text-muted)" />
                            </div>
                        </div>

                        <div class="flex-1 min-w-0 flex flex-col gap-2">
                            <div class="flex justify-between items-start gap-3">
                                <div class="min-w-0">
                                    <p class="text-xxs uppercase tracking-wider text-(--text-muted) font-mono truncate">
                                        SKU: {{ item.productSku || '-' }}
                                    </p>
                                    <h3 class="text-sm font-semibold text-(--text-heading) truncate">
                                        {{ item.productName || item.productSku }}
                                    </h3>
                                    <p v-if="variantSpec(item)" class="text-xxs text-(--text-muted) truncate">
                                        {{ variantSpec(item) }}
                                    </p>
                                </div>
                                <p class="text-sm font-mono text-(--color-primary) whitespace-nowrap">
                                    {{ formatMoney(item.unitPrice) }}
                                </p>
                            </div>

                            <div class="flex flex-wrap items-end justify-between gap-3 mt-auto">
                                <div class="inline-flex items-center border border-(--border-color) rounded-full overflow-hidden bg-(--bg-card)">
                                    <button
                                        class="px-2.5 py-1.5 hover:bg-(--bg-muted) text-(--text-heading) disabled:opacity-30 rounded-l-full"
                                        :disabled="item.quantity <= 1 || pendingItemId === item.id"
                                        @click="updateQty(item.id, item.quantity - 1)">
                                        <i class="ti ti-minus text-sm" />
                                    </button>
                                    <span class="px-4 text-xs font-mono text-(--text-heading) tabular-nums">
                                        {{ item.quantity }}
                                    </span>
                                    <button
                                        class="px-2.5 py-1.5 hover:bg-(--bg-muted) text-(--text-heading) disabled:opacity-30 rounded-r-full"
                                        :disabled="pendingItemId === item.id"
                                        @click="updateQty(item.id, item.quantity + 1)">
                                        <i class="ti ti-plus text-sm" />
                                    </button>
                                </div>

                                <div class="flex items-center gap-3">
                                    <span class="text-xs font-mono text-(--text-heading)">
                                        {{ formatMoney(item.lineTotal) }}
                                    </span>
                                    <button
                                        class="text-xxs text-(--text-muted) hover:text-(--color-danger) inline-flex items-center gap-1"
                                        :disabled="pendingItemId === item.id"
                                        @click="remove(item.id)">
                                        <i class="ti ti-trash" />
                                        Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-if="errorMessage"
                        class="badge-soft-danger px-3 py-2 rounded inline-flex items-center gap-2 text-xs">
                        <i class="ti ti-alert-circle" />
                        {{ errorMessage }}
                    </div>
                </div>

                <aside class="lg:col-span-1">
                    <div class="glass-card rounded-2xl p-5 sticky top-20 space-y-4">
                        <header class="flex items-center justify-between border-b border-(--border-color) pb-3">
                            <h3 class="text-sm font-semibold text-(--text-heading)">Order summary</h3>
                            <span class="badge-soft-primary text-xxs px-2 py-0.5 rounded font-mono">
                                {{ itemCount }} {{ itemCount === 1 ? 'item' : 'items' }}
                            </span>
                        </header>
                        <div class="space-y-2 text-xs">
                            <div class="flex justify-between">
                                <span class="text-(--text-muted)">Subtotal</span>
                                <span class="font-mono text-(--text-heading)">{{ formatMoney(cart.subtotal) }}</span>
                            </div>
                            <div class="flex justify-between text-(--text-muted)">
                                <span>Shipping</span>
                                <span class="text-xxs">Calculated at checkout</span>
                            </div>
                            <div class="flex justify-between text-(--text-muted)">
                                <span>Tax</span>
                                <span class="text-xxs">Auto-calculated</span>
                            </div>
                        </div>
                        <div class="flex justify-between items-baseline pt-3 border-t border-(--border-color)">
                            <span class="text-sm font-semibold text-(--text-heading)">Total</span>
                            <span class="text-base font-mono text-(--color-primary) font-semibold">
                                {{ formatMoney(cart.subtotal) }}
                            </span>
                        </div>
                        <NuxtLink to="/shop/checkout"
                            class="btn btn-primary w-full inline-flex justify-center gap-2 rounded-full">
                            <i class="ti ti-credit-card" />
                            Checkout
                        </NuxtLink>
                        <ul class="text-xxs text-(--text-muted) space-y-1.5 pt-2 border-t border-(--border-color)">
                            <li class="inline-flex items-center gap-2">
                                <i class="ti ti-shield-check text-(--color-info)" />
                                Items reserved while you check out.
                            </li>
                            <li class="inline-flex items-center gap-2">
                                <i class="ti ti-truck-delivery text-(--color-info)" />
                                Ships from the closest active warehouse.
                            </li>
                        </ul>
                    </div>
                </aside>
            </div>
        </section>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useShop, type Cart, type CartItem } from '~/composables/useShop'
import { useCartStateStore } from '~/stores/cart-state'

definePageMeta({ layout: false })

useHead({ title: 'Cart | Storefront' })

const shop = useShop()
const cartState = useCartStateStore()

const cart = ref<Cart | null>(null)
const loading = ref(true)
const pendingItemId = ref<string | null>(null)
const errorMessage = ref('')

const itemCount = computed(() => cart.value?.itemCount ?? cart.value?.items?.length ?? 0)

function formatMoney(n: number | null | undefined): string {
    return (Number.isFinite(Number(n)) ? Number(n) : 0).toLocaleString(undefined, {
        style: 'currency', currency: 'USD',
    })
}

function variantSpec(item: CartItem): string {
    const parts: string[] = []
    if (item.variantName) parts.push(item.variantName)
    if (item.variantSku && item.variantSku !== item.productSku) parts.push(item.variantSku)
    if (item.variantAttributes && typeof item.variantAttributes === 'object') {
        for (const [k, v] of Object.entries(item.variantAttributes)) {
            if (v !== null && v !== undefined && v !== '') parts.push(`${k}: ${v}`)
        }
    }
    return parts.join(' / ')
}

async function load() {
    loading.value = true
    errorMessage.value = ''
    try {
        const res = await shop.cart.show()
        cart.value = res.data
        cartState.applyCart(res.data)
    } catch (e: any) {
        errorMessage.value = e?.data?.message || 'Unable to load cart.'
        cart.value = null
    } finally {
        loading.value = false
    }
}

async function updateQty(itemId: string, qty: number) {
    if (!qty || qty < 1) return
    pendingItemId.value = itemId
    errorMessage.value = ''
    try {
        const res = await shop.cart.updateItem(itemId, qty)
        cart.value = res.data
        cartState.applyCart(res.data)
    } catch (e: any) {
        errorMessage.value = e?.data?.message || 'Could not update quantity.'
    } finally {
        pendingItemId.value = null
    }
}

async function remove(itemId: string) {
    pendingItemId.value = itemId
    errorMessage.value = ''
    try {
        const res = await shop.cart.removeItem(itemId)
        cart.value = res.data
        cartState.applyCart(res.data)
    } catch (e: any) {
        errorMessage.value = e?.data?.message || 'Could not remove item.'
    } finally {
        pendingItemId.value = null
    }
}

onMounted(load)
</script>
