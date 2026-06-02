<template>
    <NuxtLayout name="shop">
        <section class="space-y-10">
            <div class="glass-card rounded-3xl p-8 sm:p-12 relative overflow-hidden">
                <div class="absolute -right-12 -top-12 w-64 h-64 rounded-full bg-(--color-primary)/10 blur-3xl pointer-events-none" />
                <div class="relative z-10 max-w-xl space-y-4">
                    <span class="badge-soft-primary text-xxs uppercase tracking-widest">Now open</span>
                    <h1 class="text-3xl sm:text-4xl font-bold text-(--text-heading)">Shop our latest products</h1>
                    <p class="text-sm text-(--text-muted)">Browse the catalog, add to cart, and check out securely. Orders ship straight from our warehouse.</p>
                    <NuxtLink to="/shop/products" class="btn btn-primary inline-flex items-center gap-2">
                        <i class="ti ti-arrow-right" />Start browsing
                    </NuxtLink>
                </div>
            </div>

            <div v-if="!loading && featured.length > 0">
                <header class="flex items-baseline justify-between mb-4">
                    <h2 class="text-lg font-semibold text-(--text-heading)">Featured products</h2>
                    <NuxtLink to="/shop/products" class="text-xs text-(--color-primary) hover:underline">View all</NuxtLink>
                </header>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <NuxtLink v-for="p in featured" :key="p.id" :to="`/shop/products/${p.id}`"
                        class="glass-card rounded-2xl p-4 hover:border-(--color-primary)/40 transition-colors flex flex-col gap-3">
                        <div class="aspect-square rounded-xl bg-(--bg-muted) flex items-center justify-center overflow-hidden">
                            <img v-if="p.image_path" :src="p.image_path" :alt="p.name" class="w-full h-full object-cover" />
                            <i v-else class="ti ti-package text-3xl text-(--text-muted)" />
                        </div>
                        <div class="space-y-1">
                            <h3 class="text-sm font-semibold text-(--text-heading) truncate">{{ p.name }}</h3>
                            <p class="text-xs text-(--color-primary) font-mono">{{ formatMoney(p.unit_price) }}</p>
                        </div>
                    </NuxtLink>
                </div>
            </div>

            <div v-if="loading" class="py-24 flex justify-center">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
            </div>
        </section>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useShop, type StorefrontProduct } from '~/composables/useShop'

definePageMeta({ layout: false })

const loading = ref(true)
const featured = ref<StorefrontProduct[]>([])

const formatMoney = (n: number | null | undefined) =>
    (Number.isFinite(Number(n)) ? Number(n) : 0).toLocaleString(undefined, { style: 'currency', currency: 'USD' })

onMounted(async () => {
    try {
        const res = await useShop().catalog.list({ limit: 8 })
        featured.value = res.data ?? []
    } finally {
        loading.value = false
    }
})
</script>
