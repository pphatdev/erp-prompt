<template>
    <NuxtLayout name="shop">
        <section class="space-y-6">
            <header class="flex items-center justify-between gap-3">
                <h1 class="text-xl font-semibold text-(--text-heading)">All products</h1>
                <div class="relative w-full max-w-sm">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                    <input v-model="search" type="search" placeholder="Search products..." class="form-control pl-9"
                        @input="onSearch" />
                </div>
            </header>

            <div v-if="loading" class="py-24 flex justify-center">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
            </div>

            <div v-else-if="products.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-mood-empty text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No products found</h4>
                <p class="text-xs text-(--text-muted) mt-1">Try a different search term.</p>
            </div>

            <div v-else class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 gap-4">
                <NuxtLink v-for="p in products" :key="p.id" :to="`/shop/products/${p.id}`"
                    class="glass-card rounded-2xl p-4 hover:border-(--color-primary)/40 transition-colors flex flex-col gap-3">
                    <div class="aspect-square rounded-xl bg-(--bg-muted) flex items-center justify-center overflow-hidden">
                        <img v-if="p.image_path" :src="p.image_path" :alt="p.name" class="w-full h-full object-cover" />
                        <i v-else class="ti ti-package text-3xl text-(--text-muted)" />
                    </div>
                    <div class="space-y-1">
                        <h3 class="text-sm font-semibold text-(--text-heading) truncate">{{ p.name }}</h3>
                        <p class="text-xs text-(--text-muted) truncate">{{ p.sku }}</p>
                        <p class="text-sm text-(--color-primary) font-mono mt-1">{{ formatMoney(p.unit_price) }}</p>
                    </div>
                </NuxtLink>
            </div>

            <div v-if="pagination && pagination.totalPages > 1" class="flex items-center justify-center gap-2">
                <button class="btn btn-soft-secondary text-xs" :disabled="page <= 1" @click="page--; load()">Prev</button>
                <span class="text-xs text-(--text-muted)">Page {{ page }} / {{ pagination.totalPages }}</span>
                <button class="btn btn-soft-secondary text-xs" :disabled="page >= pagination.totalPages" @click="page++; load()">Next</button>
            </div>
        </section>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useShop, type StorefrontProduct } from '~/composables/useShop'

definePageMeta({ layout: false })

const search = ref('')
const page = ref(1)
const loading = ref(true)
const products = ref<StorefrontProduct[]>([])
const pagination = ref<{ page: number; limit: number; total: number; totalPages: number } | null>(null)

const formatMoney = (n: number | null | undefined) =>
    (Number.isFinite(Number(n)) ? Number(n) : 0).toLocaleString(undefined, { style: 'currency', currency: 'USD' })

let timer: ReturnType<typeof setTimeout> | null = null
function onSearch() {
    if (timer) clearTimeout(timer)
    timer = setTimeout(() => { page.value = 1; load() }, 250)
}

async function load() {
    loading.value = true
    try {
        const res = await useShop().catalog.list({ search: search.value || undefined, page: page.value, limit: 24 })
        products.value = res.data ?? []
        pagination.value = res.pagination ?? null
    } finally {
        loading.value = false
    }
}

onMounted(load)
</script>
