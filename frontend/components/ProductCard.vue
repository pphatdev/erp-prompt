<template>
    <NuxtLink :to="detailLink"
        class="product-card glass-card rounded-2xl overflow-hidden flex flex-col group hover:border-(--color-primary)/40 transition-colors"
        :class="{ 'product-card--compact': compact, 'product-card--out': stockKnown && !inStock }">
        <div class="relative bg-(--bg-muted) overflow-hidden"
            :class="compact ? 'aspect-square' : 'aspect-video'">
            <img v-if="image" :src="image" :alt="product.name"
                loading="lazy" decoding="async"
                class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
                :class="{ 'grayscale opacity-70': stockKnown && !inStock }" />
            <div v-else class="w-full h-full flex items-center justify-center">
                <i class="ti ti-package text-(--text-muted)"
                    :class="compact ? 'text-2xl' : 'text-4xl'" />
            </div>


            <div class="bg-linear-0 to-white via-white/50 from-transparent h-32 absolute left-0 right-0 top-0 w-full"></div>

            <div v-if="!compact && product.categoryName"
                class="absolute top-2 left-2 max-w-[60%]">
                <span class="badge-soft-secondary text-[10px] px-1.5 py-0.5  rounded-full truncate inline-flex items-center gap-1">
                    <i class="ti ti-tag text-[9px]" />
                    {{ product.categoryName }}
                </span>
            </div>

            <span v-if="stockKnown"
                class="absolute top-2 right-2 px-1.5 py-0.5 inline-flex items-center rounded-full gap-1"
                :class="[stockBadgeClass, compact ? 'text-[9px]' : 'text-[10px]']">
                <i :class="['ti', stockIcon, compact ? 'text-[9px]' : 'text-[10px]']" />
                {{ stockLabel }}
            </span>

            <span v-if="!compact && variantCount > 0"
                class="absolute bottom-2 left-2 text-[10px] uppercase tracking-wider text-(--text-heading) bg-(--bg-card)/85 backdrop-blur px-1.5 py-0.5 rounded font-mono inline-flex items-center gap-1 border border-(--border-color)">
                <i class="ti ti-stack-2 text-[9px] text-(--color-primary)" />
                +{{ variantCount }}
            </span>
        </div>

        <div class="flex flex-col gap-1 min-w-0"
            :class="compact ? 'p-2.5' : 'p-3'">
            <p v-if="!compact" class="text-[10px] uppercase tracking-wider text-(--text-muted) font-mono truncate flex items-center gap-1">
                <span class="truncate">{{ product.sku }}</span>
                <span v-if="product.tenantName" class="ml-auto inline-flex items-center gap-1 text-(--color-primary) font-sans normal-case tracking-normal">
                    <i class="ti ti-building-store text-[10px]" />
                    <span class="truncate max-w-[80px]">{{ product.tenantName }}</span>
                </span>
            </p>
            <h3 class="font-semibold text-(--text-heading) leading-snug"
                :class="compact ? 'text-xs line-clamp-1' : 'text-sm line-clamp-2 min-h-[2.5rem]'">
                {{ product.name }}
            </h3>
            <p class="font-mono mt-auto"
                :class="[
                    compact ? 'text-xs' : 'text-sm pt-1',
                    stockKnown && !inStock ? 'text-(--text-muted) line-through' : 'text-(--color-primary)',
                ]">
                {{ formattedPrice }}
            </p>
        </div>
    </NuxtLink>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { StorefrontProduct } from '~/composables/useShop'

const props = withDefaults(defineProps<{
    product: StorefrontProduct
    compact?: boolean
    currency?: string
}>(), {
    compact: false,
    currency: 'USD',
})

const image = computed<string | null>(() => {
    // Prefer resolved URL fields — `imagePath` / `image_path` are raw
    // storage paths and would 404 if passed straight to <img>.
    const p: any = props.product
    return p?.imageUrl ?? p?.image_url ?? p?.imagePath ?? p?.image_path ?? null
})

const price = computed<number>(() => {
    const p: any = props.product
    return Number(p?.unitPrice ?? p?.unit_price ?? 0)
})

const formattedPrice = computed(() =>
    (Number.isFinite(price.value) ? price.value : 0).toLocaleString(undefined, {
        style: 'currency',
        currency: props.currency,
        maximumFractionDigits: props.compact ? 0 : 2,
    })
)

const variantCount = computed(() => props.product.variants?.length ?? 0)

const detailLink = computed(() => {
    const base = `/shop/products/${props.product.id}`
    return props.product.tenantHandle
        ? `${base}?tenant=${encodeURIComponent(props.product.tenantHandle)}`
        : base
})

// `inStock` is only present on responses from the updated public catalog
// (PublicCatalogController::transform); guard so older payloads don't
// render a misleading "Out of stock" pill.
const stockKnown = computed(() => typeof props.product.inStock === 'boolean')
const inStock = computed(() => props.product.inStock === true)

const stockBadgeClass = computed(() =>
    inStock.value ? 'badge-soft-success' : 'badge-soft-danger'
)
const stockIcon = computed(() =>
    inStock.value ? 'ti-circle-check-filled' : 'ti-circle-x-filled'
)
const stockLabel = computed(() =>
    inStock.value ? 'In stock' : 'Out of stock'
)
</script>

<style scoped>
.product-card:hover {
    box-shadow:
        0 12px 28px -16px rgb(var(--color-primary-rgb) / 0.25),
        0 1px 2px 0 rgb(var(--color-primary-rgb) / 0.08);
}

.product-card--out:hover {
    box-shadow: none;
}
</style>
