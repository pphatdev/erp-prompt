<template>
    <NuxtLayout name="shop">
        <section class="space-y-10">
            <div class="glass-card rounded-2xl p-8 sm:p-12 relative overflow-hidden">
                <span class="absolute -right-12 -top-12 w-64 h-64 rounded-full bg-(--color-primary)/10 blur-3xl pointer-events-none" />
                <div class="relative z-10 max-w-2xl space-y-4">
                    <span class="badge-soft-primary inline-flex items-center gap-1.5 text-xxs uppercase tracking-wider px-2 py-1 rounded">
                        <i class="ti ti-sparkles text-[10px]" />
                        New arrivals
                    </span>
                    <h1 class="text-3xl sm:text-4xl font-bold text-(--text-heading) leading-tight">
                        Precision tools for your next build.
                    </h1>
                    <p class="text-sm text-(--text-muted) max-w-xl">
                        Browse the catalog, add to cart, and check out securely. Orders ship straight from our
                        warehouses.
                    </p>
                    <div class="flex items-center gap-2 pt-2">
                        <NuxtLink to="/shop/products" class="btn btn-primary inline-flex items-center gap-2 rounded-full">
                            <i class="ti ti-arrow-right" />
                            Start browsing
                        </NuxtLink>
                        <NuxtLink to="/shop/cart" class="btn btn-ghost inline-flex items-center gap-2 rounded-full">
                            <i class="ti ti-shopping-cart" />
                            View cart
                        </NuxtLink>
                    </div>
                </div>
            </div>

            <section v-if="categories.length > 0">
                <header class="flex items-baseline justify-between mb-4">
                    <div>
                        <h2 class="text-base font-semibold text-(--text-heading)">Browse by category</h2>
                        <p class="text-xs text-(--text-muted)">Filter the catalog by what you need.</p>
                    </div>
                    <NuxtLink to="/shop/products" class="text-xs text-(--color-primary) hover:underline">
                        All categories
                    </NuxtLink>
                </header>
                <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-3">
                    <NuxtLink v-for="c in categories.slice(0, 8)" :key="c.id"
                        :to="`/shop/products?category_ids=${c.id}`"
                        class="glass-card rounded-xl p-4 flex items-center justify-between hover:border-(--color-primary)/40 hover:-translate-y-0.5 transition-all">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-(--text-heading) truncate">{{ c.name }}</p>
                            <p class="text-xxs text-(--text-muted) font-mono">{{ c.productCount }} items</p>
                        </div>
                        <i class="ti ti-arrow-up-right text-(--color-primary)" />
                    </NuxtLink>
                </div>
            </section>

            <section>
                <header class="flex items-baseline justify-between mb-4">
                    <div>
                        <h2 class="text-base font-semibold text-(--text-heading)">Featured products</h2>
                        <p class="text-xs text-(--text-muted)">A handful of recent additions to the catalog.</p>
                    </div>
                    <NuxtLink to="/shop/products" class="text-xs text-(--color-primary) hover:underline">
                        View all
                    </NuxtLink>
                </header>

                <div v-if="loadingProducts" class="py-16 flex justify-center">
                    <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                </div>

                <div v-else-if="featured.length === 0"
                    class="glass-card rounded-2xl py-16 text-center">
                    <i class="ti ti-package-off text-3xl text-(--text-muted)" />
                    <p class="text-sm text-(--text-muted) mt-2">
                        No products listed yet. Add products from the admin to see them here.
                    </p>
                </div>

                <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                    <ProductCard v-for="p in featured" :key="p.id" :product="p" />
                </div>
            </section>
        </section>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useShop, type StorefrontProduct, type StorefrontCategory } from '~/composables/useShop'

definePageMeta({ layout: false })

useHead({ title: 'Storefront' })

const loadingProducts = ref(true)
const featured = ref<StorefrontProduct[]>([])
const categories = ref<StorefrontCategory[]>([])

onMounted(async () => {
    const shop = useShop()
    try {
        const [products, cats] = await Promise.allSettled([
            shop.catalog.list({ limit: 8 }),
            shop.catalog.categories(),
        ])
        if (products.status === 'fulfilled') featured.value = products.value.data ?? []
        if (cats.status === 'fulfilled') categories.value = cats.value.data ?? []
    } finally {
        loadingProducts.value = false
    }
})
</script>
