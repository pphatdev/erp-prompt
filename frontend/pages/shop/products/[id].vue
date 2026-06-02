<template>
    <NuxtLayout name="shop">
        <div v-if="loading" class="py-24 flex justify-center">
            <span class="w-10 h-10 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
        </div>

        <div v-else-if="!product" class="glass-card rounded-2xl py-24 text-center">
            <i class="ti ti-package-off text-4xl text-(--text-muted)" />
            <h2 class="text-base font-semibold text-(--text-heading) mt-3">Product not found</h2>
            <p class="text-xs text-(--text-muted) mt-1">It may have been delisted or moved.</p>
            <NuxtLink to="/shop/products" class="btn btn-soft-primary text-xs mt-4 inline-flex rounded-full">
                <i class="ti ti-arrow-left" />
                Back to catalog
            </NuxtLink>
        </div>

        <article v-else class="space-y-10">
            <nav class="flex items-center gap-1.5 text-xxs text-(--text-muted) flex-wrap">
                <NuxtLink to="/shop/products" class="hover:text-(--text-heading)">Catalog</NuxtLink>
                <i class="ti ti-chevron-right text-[10px]" />
                <NuxtLink v-if="product.categoryId" :to="`/shop/products?category_ids=${product.categoryId}`"
                    class="hover:text-(--text-heading)">
                    {{ product.categoryName || 'Category' }}
                </NuxtLink>
                <i v-if="product.categoryId" class="ti ti-chevron-right text-[10px]" />
                <span class="text-(--text-heading) font-semibold truncate max-w-[60vw]">{{ product.name }}</span>
            </nav>

            <section class="grid grid-cols-1 md:grid-cols-12 gap-6">
                <div class="md:col-span-7 space-y-3">
                    <div class="glass-card rounded-2xl overflow-hidden relative group aspect-[16/10]">
                        <img v-if="heroImage" :src="heroImage" :alt="product.name"
                            class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-105" />
                        <div v-else class="w-full h-full flex items-center justify-center bg-(--bg-muted)">
                            <i class="ti ti-package text-6xl text-(--text-muted)" />
                        </div>
                        <button
                            class="absolute top-3 right-3 p-2 rounded-full bg-(--bg-card)/80 backdrop-blur border border-(--border-color) opacity-0 group-hover:opacity-100 transition-opacity"
                            :class="favorited ? 'text-(--color-danger)' : 'text-(--text-muted)'"
                            @click="favorited = !favorited"
                            :title="favorited ? 'Remove from saved' : 'Save for later'">
                            <i :class="['ti', favorited ? 'ti-heart-filled' : 'ti-heart']" />
                        </button>
                    </div>
                    <div v-if="heroImage" class="grid grid-cols-4 gap-2">
                        <div class="aspect-video rounded-lg overflow-hidden border-2 border-(--color-primary)">
                            <img :src="heroImage" :alt="product.name" class="w-full h-full object-cover" />
                        </div>
                    </div>
                </div>

                <div class="md:col-span-5 space-y-6">
                    <header class="space-y-2">
                        <p class="text-xxs uppercase tracking-wider text-(--text-muted) font-mono">
                            SKU: {{ activeSku }}
                            <span v-if="product.productType"
                                class="badge-soft-info text-xxs px-2 py-0.5 rounded ml-2 uppercase">
                                {{ product.productType }}
                            </span>
                        </p>
                        <h1 class="text-2xl font-bold text-(--text-heading) leading-tight">{{ product.name }}</h1>
                        <p class="text-2xl font-mono text-(--color-primary)">
                            {{ formatMoney(activePrice) }}
                        </p>

                        <div class="flex flex-wrap items-center gap-2">
                            <div class="inline-flex items-center gap-2 text-xs px-2.5 py-1 rounded-full"
                                :class="stockBadgeClass">
                                <i :class="['ti', stockIcon, stockState === 'loading' ? 'animate-spin' : '']" />
                                <span>{{ stockLabel }}</span>
                            </div>
                            <div v-if="isAggregatorView && product.tenantName"
                                class="inline-flex items-center gap-2 text-xs px-2.5 py-1 rounded-full badge-soft-primary">
                                <i class="ti ti-building-store" />
                                <span>Sold by {{ product.tenantName }}</span>
                            </div>
                        </div>

                        <p v-if="product.description"
                            class="text-sm text-(--text-muted) leading-relaxed">
                            {{ product.description }}
                        </p>

                        <div v-if="isAggregatorView"
                            class="text-xxs text-(--text-muted) italic flex items-center gap-1.5 mt-1">
                            <i class="ti ti-info-circle text-[12px]" />
                            Browse-only on this marketplace view. Visit
                            <span class="font-semibold text-(--text-heading)">{{ product.tenantName || aggregatorTenant }}</span>
                            's shop to add to cart.
                        </div>
                    </header>

                    <section v-if="product.variants && product.variants.length > 0" class="space-y-2">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xxs font-semibold uppercase tracking-wider text-(--text-heading)">
                                Configuration
                            </h3>
                            <span class="text-xxs text-(--text-muted) font-mono">
                                {{ product.variants.length }} options
                            </span>
                        </div>
                        <div class="space-y-2">
                            <button v-for="v in product.variants" :key="v.id" type="button"
                                class="glass-card rounded-xl p-4 w-full text-left transition-all"
                                :class="variantId === v.id
                                    ? 'border-(--color-primary) shadow-sm ring-1 ring-(--color-primary)/30'
                                    : 'hover:border-(--color-primary)/40'"
                                @click="variantId = v.id">
                                <div class="flex justify-between items-center gap-2">
                                    <span class="text-sm font-semibold text-(--text-heading)">
                                        {{ v.name || v.sku }}
                                    </span>
                                    <span v-if="variantId === v.id" class="badge-soft-primary text-xxs px-2 py-0.5 rounded inline-flex items-center gap-1">
                                        <i class="ti ti-check text-[10px]" />
                                        Selected
                                    </span>
                                    <span v-else-if="v.price !== null && v.price !== undefined"
                                        class="text-xs font-mono text-(--color-primary)">
                                        {{ formatMoney(v.price) }}
                                    </span>
                                </div>
                                <div v-if="v.attributes && Object.keys(v.attributes).length > 0"
                                    class="text-xxs text-(--text-muted) mt-2 space-y-0.5">
                                    <p v-for="(val, key) in v.attributes" :key="String(key)" class="font-mono">
                                        {{ key }}: {{ val }}
                                    </p>
                                </div>
                            </button>
                        </div>
                    </section>

                    <section class="space-y-2">
                        <h3 class="text-xxs font-semibold uppercase tracking-wider text-(--text-heading)">Quantity</h3>
                        <div class="inline-flex items-center border border-(--border-color) rounded-full overflow-hidden bg-(--bg-card)">
                            <button class="px-3 py-2 hover:bg-(--bg-muted) text-(--text-heading) disabled:opacity-30 rounded-l-full"
                                :disabled="quantity <= 1"
                                @click="quantity = Math.max(1, quantity - 1)">
                                <i class="ti ti-minus" />
                            </button>
                            <input v-model.number="quantity" type="number" min="1" :max="maxQuantity"
                                class="w-16 text-center bg-transparent border-0 focus:ring-0 text-sm font-mono text-(--text-heading) no-spin" />
                            <button class="px-3 py-2 hover:bg-(--bg-muted) text-(--text-heading) disabled:opacity-30 rounded-r-full"
                                :disabled="quantity >= maxQuantity"
                                @click="quantity = Math.min(maxQuantity, quantity + 1)">
                                <i class="ti ti-plus" />
                            </button>
                        </div>
                    </section>

                    <div v-if="message" class="text-xs px-3 py-2 rounded inline-flex items-center gap-2"
                        :class="messageOk
                            ? 'badge-soft-success'
                            : 'badge-soft-danger'">
                        <i :class="['ti', messageOk ? 'ti-check' : 'ti-alert-circle']" />
                        {{ message }}
                    </div>

                    <div class="flex flex-col gap-2">
                        <button class="btn btn-primary w-full inline-flex justify-center gap-2 rounded-full"
                            :disabled="!canAddToCart" @click="addToCart">
                            <i :class="['ti', addToCartIcon, adding ? 'animate-spin' : '']" />
                            {{ addToCartLabel }}
                        </button>
                        <NuxtLink to="/shop/products"
                            class="btn btn-ghost w-full inline-flex justify-center gap-2 rounded-full">
                            <i class="ti ti-arrow-left" />
                            Continue browsing
                        </NuxtLink>
                    </div>

                    <div class="border-t border-(--border-color) divide-y divide-(--border-color)">
                        <details class="group" open>
                            <summary class="flex justify-between items-center py-3 cursor-pointer list-none">
                                <span class="text-xxs font-semibold uppercase tracking-wider text-(--text-heading)">
                                    Specifications
                                </span>
                                <i class="ti ti-chevron-down transition-transform group-open:rotate-180 text-(--text-muted)" />
                            </summary>
                            <div class="pb-3 text-xs text-(--text-muted) leading-relaxed whitespace-pre-line">
                                <p v-if="product.descriptionLong">{{ product.descriptionLong }}</p>
                                <p v-else-if="product.description">{{ product.description }}</p>
                                <p v-else class="italic">No detailed specifications provided.</p>
                            </div>
                        </details>
                        <details class="group">
                            <summary class="flex justify-between items-center py-3 cursor-pointer list-none"
                                @click="loadAvailabilityOnce">
                                <span class="text-xxs font-semibold uppercase tracking-wider text-(--text-heading)">
                                    Availability
                                </span>
                                <i class="ti ti-chevron-down transition-transform group-open:rotate-180 text-(--text-muted)" />
                            </summary>
                            <div class="pb-3">
                                <div v-if="availabilityLoading" class="py-2">
                                    <span class="w-4 h-4 inline-block rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                                </div>
                                <div v-else-if="availability">
                                    <p class="text-xs text-(--text-muted) mb-2">
                                        Total available across warehouses:
                                        <span class="text-(--text-heading) font-mono font-semibold">
                                            {{ availability.totalAvailable.toLocaleString() }} units
                                        </span>
                                    </p>
                                    <ul v-if="availability.warehouseBreakdown.length > 0"
                                        class="space-y-1 text-xs">
                                        <li v-for="w in availability.warehouseBreakdown" :key="w.warehouseId"
                                            class="flex justify-between border-b border-(--border-color)/40 py-1 last:border-0">
                                            <span class="text-(--text-body)">{{ w.warehouseName }}</span>
                                            <span class="text-(--text-heading) font-mono">
                                                {{ w.availableStock.toLocaleString() }}
                                            </span>
                                        </li>
                                    </ul>
                                    <p v-else class="text-xxs text-(--text-muted) italic">
                                        Out of stock in all warehouses.
                                    </p>
                                </div>
                                <p v-else class="text-xxs text-(--text-muted) italic">
                                    Expand to load availability.
                                </p>
                            </div>
                        </details>
                    </div>
                </div>
            </section>

            <section v-if="related.length > 0" class="space-y-4">
                <header class="flex items-baseline justify-between">
                    <div>
                        <h2 class="text-base font-semibold text-(--text-heading)">Complete the setup</h2>
                        <p class="text-xs text-(--text-muted)">
                            {{ product.categoryName ? `More from ${product.categoryName}` : 'You may also like' }}
                        </p>
                    </div>
                    <NuxtLink to="/shop/products" class="text-xs text-(--color-primary) hover:underline">
                        Browse catalog
                    </NuxtLink>
                </header>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                    <ProductCard v-for="p in related" :key="p.id" :product="p" compact />
                </div>
            </section>
        </article>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useShop, type StorefrontProduct, type StorefrontAvailability } from '~/composables/useShop'
import { useTenantStore } from '~/stores/tenant'
import { useCartStateStore } from '~/stores/cart-state'

definePageMeta({ layout: false })

useHead({ title: 'Product | Storefront' })

const route = useRoute()
const shop = useShop()
const tenantStore = useTenantStore()
const cartState = useCartStateStore()

const loading = ref(true)
const product = ref<StorefrontProduct | null>(null)
const related = ref<StorefrontProduct[]>([])
const variantId = ref<string | null>(null)
const quantity = ref(1)
const adding = ref(false)
const message = ref('')
const messageOk = ref(true)
const favorited = ref(false)

const availability = ref<StorefrontAvailability | null>(null)
const availabilityLoading = ref(false)
let availabilityFetched = false

const LOW_STOCK_THRESHOLD = 10

const stockState = computed<'loading' | 'in_stock' | 'low_stock' | 'out_of_stock'>(() => {
    if (availabilityLoading.value || !availability.value) return 'loading'
    const n = Number(availability.value.totalAvailable ?? 0)
    if (!Number.isFinite(n) || n <= 0) return 'out_of_stock'
    if (n <= LOW_STOCK_THRESHOLD) return 'low_stock'
    return 'in_stock'
})

const stockLabel = computed(() => {
    if (stockState.value === 'loading') return 'Checking stock...'
    if (stockState.value === 'out_of_stock') return 'Out of stock'
    const n = Number(availability.value?.totalAvailable ?? 0)
    if (stockState.value === 'low_stock') return `Only ${n.toLocaleString()} left`
    return `In stock (${n.toLocaleString()})`
})

const stockBadgeClass = computed(() => {
    if (stockState.value === 'loading') return 'badge-soft-secondary'
    if (stockState.value === 'out_of_stock') return 'badge-soft-danger'
    if (stockState.value === 'low_stock') return 'badge-soft-warning'
    return 'badge-soft-success'
})

const stockIcon = computed(() => {
    if (stockState.value === 'loading') return 'ti-loader-2'
    if (stockState.value === 'out_of_stock') return 'ti-circle-x'
    if (stockState.value === 'low_stock') return 'ti-alert-triangle'
    return 'ti-circle-check'
})

const maxQuantity = computed(() => {
    if (stockState.value === 'out_of_stock') return 0
    const n = Number(availability.value?.totalAvailable ?? 0)
    if (!Number.isFinite(n) || n <= 0) return 999
    return Math.min(999, Math.floor(n))
})

const canAddToCart = computed(() => {
    if (adding.value) return false
    if (isAggregatorView.value) return false
    if (stockState.value === 'loading') return false
    if (stockState.value === 'out_of_stock') return false
    return quantity.value > 0
})

const addToCartIcon = computed(() => {
    if (adding.value) return 'ti-loader-2'
    if (isAggregatorView.value) return 'ti-building-store'
    if (stockState.value === 'out_of_stock') return 'ti-shopping-cart-off'
    return 'ti-shopping-cart-plus'
})

const addToCartLabel = computed(() => {
    if (adding.value) return 'Adding...'
    if (isAggregatorView.value) return `View on ${product.value?.tenantName ?? 'seller'}'s shop`
    if (stockState.value === 'loading') return 'Checking stock...'
    if (stockState.value === 'out_of_stock') return 'Out of stock'
    return 'Add to cart'
})

const heroImage = computed(() => productImage(product.value))

const activeVariant = computed(() => {
    if (!product.value || !variantId.value) return null
    return product.value.variants?.find(v => v.id === variantId.value) ?? null
})

const activeSku = computed(() => activeVariant.value?.sku ?? product.value?.sku ?? '')

const activePrice = computed(() => {
    const v = activeVariant.value
    if (v && v.price !== null && v.price !== undefined) return Number(v.price)
    return productPrice(product.value)
})

function formatMoney(n: number | null | undefined): string {
    return (Number.isFinite(Number(n)) ? Number(n) : 0).toLocaleString(undefined, {
        style: 'currency', currency: 'USD',
    })
}

function productImage(p: any): string | null {
    // Prefer resolved URLs — raw storage paths would 404.
    return p?.imageUrl ?? p?.image_url ?? p?.imagePath ?? p?.image_path ?? null
}

function productPrice(p: any): number {
    return Number(p?.unitPrice ?? p?.unit_price ?? 0)
}

/**
 * Aggregator routing. The `?tenant=` query is set by ProductCard whenever
 * a product carries an explicit owning-tenant handle. We only treat the
 * detail page as a "cross-tenant marketplace listing" — i.e., block the
 * cart and surface the "Visit X's shop" CTA — when the owning tenant
 * differs from the tenant the shopper is currently on. Same-tenant
 * clicks (demo aggregator → demo product) fall through to the regular
 * Add-to-cart flow because the cart can in fact hold them.
 */
const aggregatorTenant = computed(() => route.query.tenant ? String(route.query.tenant) : null)
const isAggregatorView = computed(() => {
    const t = aggregatorTenant.value
    if (!t) return false
    return t !== tenantStore.activeHandle
})

async function addToCart() {
    if (!product.value || !canAddToCart.value || isAggregatorView.value) return
    adding.value = true
    message.value = ''
    try {
        const res = await shop.cart.addItem({
            product_id: product.value.id,
            variant_id: variantId.value || undefined,
            quantity: quantity.value,
        })
        cartState.applyCart(res?.data)
        messageOk.value = true
        message.value = `Added ${quantity.value} × ${product.value.name} to cart.`
        try {
            availability.value = await shop.catalog.availability(product.value.id)
        } catch {}
    } catch (e: any) {
        messageOk.value = false
        message.value = e?.data?.message || 'Could not add to cart.'
    } finally {
        adding.value = false
    }
}

async function loadProduct(id: string) {
    loading.value = true
    availability.value = null
    availabilityFetched = false
    message.value = ''
    const tenant = aggregatorTenant.value
    try {
        const res = await shop.catalog.show(id, tenant)
        product.value = res.data
        variantId.value = null
    } catch {
        product.value = null
    } finally {
        loading.value = false
    }

    if (product.value) {
        const pid = product.value.id
        availabilityLoading.value = true
        availabilityFetched = false
        try {
            // Related pulls go through the LOCAL tenant context (so the
            // shopper continues browsing the aggregator catalog from the
            // detail page). Availability follows the product to its owning
            // tenant via the same `tenant` query param.
            const [rel, avail] = await Promise.allSettled([
                shop.catalog.list({
                    category_ids: product.value.categoryId && !tenant ? [product.value.categoryId] : undefined,
                    limit: 5,
                }),
                shop.catalog.availability(pid, tenant),
            ])
            if (rel.status === 'fulfilled') {
                related.value = (rel.value.data ?? []).filter(p => p.id !== pid).slice(0, 4)
            } else {
                related.value = []
            }
            if (avail.status === 'fulfilled') {
                availability.value = avail.value
                availabilityFetched = true
            }
        } finally {
            availabilityLoading.value = false
        }
    }
}

async function loadAvailabilityOnce() {
    if (availabilityFetched || !product.value) return
    availabilityFetched = true
    availabilityLoading.value = true
    try {
        availability.value = await shop.catalog.availability(product.value.id, aggregatorTenant.value)
    } catch {
        availability.value = null
    } finally {
        availabilityLoading.value = false
    }
}

// Re-load when either the path id or the ?tenant= query changes.
watch([() => route.params.id, () => route.query.tenant], ([id]) => {
    if (id) loadProduct(String(id))
})

onMounted(() => loadProduct(String(route.params.id)))
</script>

<style scoped>
.no-spin::-webkit-outer-spin-button,
.no-spin::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
}

.no-spin {
    -moz-appearance: textfield;
}
</style>
