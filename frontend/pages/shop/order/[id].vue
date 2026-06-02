<template>
    <NuxtLayout name="shop">
        <section v-if="loading" class="py-24 flex justify-center">
            <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
        </section>

        <section v-else-if="order" class="space-y-6">
            <div class="glass-card rounded-2xl p-6 sm:p-8 relative overflow-hidden">
                <div class="absolute -right-12 -top-12 w-48 h-48 rounded-full bg-(--color-success)/10 blur-3xl pointer-events-none" />
                <div class="relative z-10 space-y-3">
                    <span class="badge-soft-success text-xxs uppercase tracking-widest">{{ order.status }}</span>
                    <h1 class="text-2xl font-bold text-(--text-heading)">Thanks - your order is in.</h1>
                    <p class="text-sm text-(--text-muted)">Order <span class="font-mono">{{ order.orderNumber }}</span> placed {{ formatDate(order.placedAt) }}</p>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2 glass-card rounded-2xl p-5 space-y-3">
                    <h3 class="text-sm font-semibold text-(--text-heading)">Items</h3>
                    <div v-for="item in order.items" :key="item.id" class="flex justify-between text-xs border-b border-(--border-color) pb-2 last:border-0">
                        <div>
                            <div class="text-(--text-heading)">{{ item.productName }}</div>
                            <div class="text-(--text-muted) text-xxs">{{ item.variantSku || item.productSku }} - qty {{ item.quantity }}</div>
                        </div>
                        <div class="font-mono">{{ formatMoney(item.lineTotal) }}</div>
                    </div>
                </div>
                <aside class="glass-card rounded-2xl p-5 space-y-2 text-xs">
                    <h3 class="text-sm font-semibold text-(--text-heading)">Summary</h3>
                    <div class="flex justify-between"><span class="text-(--text-muted)">Subtotal</span><span class="font-mono">{{ formatMoney(order.subtotal) }}</span></div>
                    <div class="flex justify-between"><span class="text-(--text-muted)">Tax</span><span class="font-mono">{{ formatMoney(order.taxAmount) }}</span></div>
                    <div class="flex justify-between"><span class="text-(--text-muted)">Shipping</span><span class="font-mono">{{ formatMoney(order.shippingAmount) }}</span></div>
                    <div class="flex justify-between text-sm font-semibold pt-2 border-t border-(--border-color)">
                        <span>Total</span><span class="font-mono text-(--color-primary)">{{ formatMoney(order.totalAmount) }}</span>
                    </div>
                </aside>
            </div>

            <div v-if="order.trackingNumber" class="glass-card rounded-xl p-4 text-xs flex items-center gap-3">
                <i class="ti ti-truck text-(--color-primary) text-lg" />
                <div>
                    <div class="text-(--text-heading)">Shipped via {{ order.carrier }}</div>
                    <div class="text-(--text-muted) font-mono">{{ order.trackingNumber }}</div>
                </div>
            </div>

            <div class="flex gap-2">
                <NuxtLink to="/shop/products" class="btn btn-soft-secondary text-xs">Keep shopping</NuxtLink>
                <NuxtLink v-if="shopAuth.isAuthenticated" to="/shop/account" class="btn btn-soft-primary text-xs">My orders</NuxtLink>
            </div>
        </section>

        <section v-else class="glass-card rounded-2xl py-20 text-center">
            <p class="text-sm text-(--text-muted)">Order not found.</p>
        </section>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useShop, type EcomOrder } from '~/composables/useShop'
import { useShopAuthStore } from '~/stores/shop-auth'
import { useRoute } from 'vue-router'

definePageMeta({ layout: false })

const route = useRoute()
const shopAuth = useShopAuthStore()
const order = ref<EcomOrder | null>(null)
const loading = ref(true)

const formatMoney = (n: number | null | undefined) =>
    (Number.isFinite(Number(n)) ? Number(n) : 0).toLocaleString(undefined, { style: 'currency', currency: 'USD' })
const formatDate = (iso: string | null) => iso ? new Date(iso).toLocaleDateString() : '-'

onMounted(async () => {
    try {
        const res = await useShop().orders.show(String(route.params.id))
        order.value = res.data
    } finally {
        loading.value = false
    }
})
</script>
