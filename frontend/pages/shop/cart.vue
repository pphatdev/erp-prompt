<template>
    <NuxtLayout name="shop">
        <section class="space-y-6">
            <h1 class="text-xl font-semibold text-(--text-heading)">Your cart</h1>

            <div v-if="loading" class="py-16 flex justify-center">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
            </div>

            <div v-else-if="!cart || (cart.items?.length ?? 0) === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-shopping-cart-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">Your cart is empty</h4>
                <NuxtLink to="/shop/products" class="btn btn-soft-primary text-xs mt-4 inline-flex">Start shopping</NuxtLink>
            </div>

            <div v-else class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-3">
                    <div v-for="item in cart.items" :key="item.id"
                        class="glass-card rounded-xl p-4 flex flex-col sm:flex-row gap-4">
                        <div class="flex-1 space-y-1">
                            <h3 class="text-sm font-semibold text-(--text-heading)">{{ item.productName || item.productSku }}</h3>
                            <p class="text-xxs text-(--text-muted)">{{ item.variantSku || item.productSku }}</p>
                            <p class="text-xs text-(--color-primary) font-mono">{{ formatMoney(item.unitPrice) }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <input :value="item.quantity" type="number" min="1" class="form-control w-20 text-sm"
                                @change="updateQty(item.id, ($event.target as HTMLInputElement).valueAsNumber)" />
                            <button class="text-xs text-(--color-danger) hover:underline" @click="remove(item.id)">
                                <i class="ti ti-trash" />
                            </button>
                        </div>
                        <div class="text-sm font-mono text-(--text-heading) sm:w-24 sm:text-right">
                            {{ formatMoney(item.lineTotal) }}
                        </div>
                    </div>
                </div>

                <aside class="glass-card rounded-2xl p-5 h-fit space-y-4">
                    <h3 class="text-sm font-semibold text-(--text-heading)">Order summary</h3>
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between">
                            <span class="text-(--text-muted)">Subtotal</span>
                            <span class="font-mono">{{ formatMoney(cart.subtotal) }}</span>
                        </div>
                        <div class="flex justify-between text-(--text-muted)">
                            <span>Shipping</span><span>calculated at checkout</span>
                        </div>
                    </div>
                    <NuxtLink to="/shop/checkout" class="btn btn-primary w-full inline-flex justify-center items-center gap-2">
                        <i class="ti ti-credit-card" /> Checkout
                    </NuxtLink>
                </aside>
            </div>
        </section>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useShop, type Cart } from '~/composables/useShop'

definePageMeta({ layout: false })

const cart = ref<Cart | null>(null)
const loading = ref(true)

const formatMoney = (n: number | null | undefined) =>
    (Number.isFinite(Number(n)) ? Number(n) : 0).toLocaleString(undefined, { style: 'currency', currency: 'USD' })

async function load() {
    loading.value = true
    try {
        const res = await useShop().cart.show()
        cart.value = res.data
    } finally {
        loading.value = false
    }
}

async function updateQty(itemId: string, qty: number) {
    if (!qty || qty < 1) return
    const res = await useShop().cart.updateItem(itemId, qty)
    cart.value = res.data
}

async function remove(itemId: string) {
    const res = await useShop().cart.removeItem(itemId)
    cart.value = res.data
}

onMounted(load)
</script>
