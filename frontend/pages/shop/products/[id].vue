<template>
    <NuxtLayout name="shop">
        <div v-if="loading" class="py-24 flex justify-center">
            <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
        </div>

        <article v-else-if="product" class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div class="glass-card rounded-2xl aspect-square flex items-center justify-center overflow-hidden">
                <img v-if="product.image_path" :src="product.image_path" :alt="product.name" class="w-full h-full object-cover" />
                <i v-else class="ti ti-package text-6xl text-(--text-muted)" />
            </div>

            <div class="space-y-4">
                <header class="space-y-2">
                    <p class="text-xxs uppercase tracking-widest text-(--text-muted)">{{ product.sku }}</p>
                    <h1 class="text-2xl font-bold text-(--text-heading)">{{ product.name }}</h1>
                    <p class="text-lg text-(--color-primary) font-mono">{{ formatMoney(price) }}</p>
                </header>
                <p v-if="product.description" class="text-sm text-(--text-muted) leading-relaxed">{{ product.description }}</p>

                <div v-if="product.variants && product.variants.length > 0" class="space-y-2">
                    <label class="text-xs text-(--text-muted)">Variant</label>
                    <select v-model="variantId" class="form-control text-sm">
                        <option :value="null">Base</option>
                        <option v-for="v in product.variants" :key="v.id" :value="v.id">
                            {{ v.sku }} <span v-if="v.price">- {{ formatMoney(v.price) }}</span>
                        </option>
                    </select>
                </div>

                <div class="flex items-center gap-2">
                    <label class="text-xs text-(--text-muted)">Qty</label>
                    <input v-model.number="quantity" type="number" min="1" max="999" class="form-control w-24 text-sm" />
                </div>

                <div v-if="message" class="text-xs px-3 py-2 rounded" :class="messageOk ? 'bg-(--color-success)/10 text-(--color-success)' : 'bg-(--color-danger)/10 text-(--color-danger)'">
                    {{ message }}
                </div>

                <div class="flex flex-wrap gap-2 pt-2">
                    <button class="btn btn-primary inline-flex items-center gap-2" :disabled="adding" @click="addToCart">
                        <i class="ti" :class="adding ? 'ti-loader animate-spin' : 'ti-shopping-cart-plus'" />
                        Add to cart
                    </button>
                    <NuxtLink to="/shop/products" class="btn btn-soft-secondary text-xs">Back to catalog</NuxtLink>
                </div>
            </div>
        </article>

        <div v-else class="glass-card rounded-2xl py-20 text-center">
            <i class="ti ti-package-off text-4xl text-(--text-muted)" />
            <p class="text-sm text-(--text-muted) mt-3">Product not found.</p>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useShop, type StorefrontProduct } from '~/composables/useShop'
import { useRoute } from 'vue-router'

definePageMeta({ layout: false })

const route = useRoute()
const product = ref<StorefrontProduct | null>(null)
const loading = ref(true)
const variantId = ref<string | null>(null)
const quantity = ref(1)
const adding = ref(false)
const message = ref('')
const messageOk = ref(true)

const price = computed(() => {
    if (!product.value) return 0
    if (variantId.value) {
        const v = product.value.variants?.find(x => x.id === variantId.value)
        if (v?.price) return v.price
    }
    return product.value.unit_price
})

const formatMoney = (n: number | null | undefined) =>
    (Number.isFinite(Number(n)) ? Number(n) : 0).toLocaleString(undefined, { style: 'currency', currency: 'USD' })

async function addToCart() {
    if (!product.value) return
    adding.value = true
    message.value = ''
    try {
        await useShop().cart.addItem({
            product_id: product.value.id,
            variant_id: variantId.value || undefined,
            quantity: quantity.value,
        })
        messageOk.value = true
        message.value = `Added ${quantity.value} to cart.`
    } catch (e: any) {
        messageOk.value = false
        message.value = e?.data?.message || 'Could not add to cart.'
    } finally {
        adding.value = false
    }
}

onMounted(async () => {
    try {
        const res = await useShop().catalog.show(String(route.params.id))
        product.value = res.data
    } finally {
        loading.value = false
    }
})
</script>
