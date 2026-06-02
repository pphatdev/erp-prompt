<template>
    <NuxtLayout name="shop">
        <div v-if="loading" class="py-16 flex justify-center">
            <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
        </div>

        <div v-else-if="!cart || (cart.items?.length ?? 0) === 0"
            class="glass-card rounded-2xl py-20 text-center">
            <i class="ti ti-shopping-cart-off text-5xl text-(--text-muted)" />
            <h2 class="text-sm font-semibold text-(--text-heading) mt-3">Nothing to check out</h2>
            <p class="text-xs text-(--text-muted) mt-1">Your cart is empty. Add a product first.</p>
            <NuxtLink to="/shop/products" class="btn btn-soft-primary text-xs mt-4 inline-flex rounded-full">
                <i class="ti ti-arrow-right" />
                Start shopping
            </NuxtLink>
        </div>

        <section v-else class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            <div class="lg:col-span-7 space-y-8">
                <header>
                    <h1 class="text-xl font-semibold text-(--text-heading)">Checkout</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        Confirm your shipping, delivery, and payment details to place the order.
                    </p>
                </header>

                <section class="glass-card rounded-2xl p-5 space-y-4">
                    <header class="flex items-center gap-2">
                        <span class="badge-soft-primary text-xxs font-mono px-2 py-0.5 rounded">01</span>
                        <h2 class="text-sm font-semibold text-(--text-heading)">Shipping</h2>
                    </header>

                    <div v-if="!shopAuth.isAuthenticated" class="space-y-3">
                        <p class="text-xs text-(--text-muted)">
                            Continuing as a guest. Receipt and tracking go to the email below.
                            <NuxtLink to="/shop/auth/login?redirect=/shop/checkout"
                                class="text-(--color-primary) hover:underline">
                                Log in
                            </NuxtLink>
                            to use a saved address.
                        </p>
                        <div class="space-y-1.5">
                            <label class="text-xxs font-semibold uppercase tracking-wider text-(--text-muted)">
                                Email for receipt
                            </label>
                            <input v-model="form.guest_email" type="email" required
                                placeholder="you@example.com"
                                class="form-control text-sm w-full rounded-full" />
                        </div>
                    </div>

                    <div v-else-if="addresses.length === 0" class="space-y-3">
                        <div class="border border-dashed border-(--border-color) rounded-xl p-4 bg-(--bg-muted)/40">
                            <p class="text-xs text-(--text-muted)">
                                No saved shipping addresses on file.
                            </p>
                            <NuxtLink to="/shop/account"
                                class="btn btn-soft-primary text-xs mt-3 inline-flex rounded-full">
                                <i class="ti ti-plus" />
                                Add an address in your account
                            </NuxtLink>
                        </div>
                    </div>

                    <div v-else class="space-y-2">
                        <label v-for="a in addresses" :key="a.id"
                            class="flex items-start gap-3 p-4 rounded-xl border cursor-pointer transition-all"
                            :class="form.shipping_address_id === a.id
                                ? 'border-(--color-primary) bg-(--color-primary)/5'
                                : 'border-(--border-color) hover:border-(--color-primary)/40'">
                            <input v-model="form.shipping_address_id" :value="a.id" type="radio"
                                name="address" class="mt-0.5 form-radio" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-(--text-heading) flex items-center gap-2">
                                    {{ a.label || a.recipientName }}
                                    <span v-if="a.isDefaultShipping"
                                        class="badge-soft-success text-xxs px-2 py-0.5 rounded">
                                        Default
                                    </span>
                                </p>
                                <p class="text-xxs text-(--text-muted) mt-0.5">
                                    {{ a.line1 }}<span v-if="a.line2">, {{ a.line2 }}</span>
                                </p>
                                <p class="text-xxs text-(--text-muted)">
                                    {{ a.city }}<span v-if="a.state">, {{ a.state }}</span>
                                    <span v-if="a.postalCode"> {{ a.postalCode }}</span>
                                    <span v-if="a.country"> &bull; {{ a.country }}</span>
                                </p>
                            </div>
                        </label>
                        <NuxtLink to="/shop/account"
                            class="text-xxs text-(--color-primary) hover:underline inline-flex items-center gap-1 pt-1">
                            <i class="ti ti-plus text-[10px]" />
                            Manage addresses
                        </NuxtLink>
                    </div>
                </section>

                <section class="glass-card rounded-2xl p-5 space-y-4">
                    <header class="flex items-center gap-2">
                        <span class="badge-soft-primary text-xxs font-mono px-2 py-0.5 rounded">02</span>
                        <h2 class="text-sm font-semibold text-(--text-heading)">Delivery</h2>
                    </header>
                    <label class="flex items-center gap-3 p-4 rounded-xl border border-(--color-primary) bg-(--color-primary)/5">
                        <input type="radio" name="delivery" checked readonly class="form-radio" />
                        <div class="flex-1">
                            <p class="text-sm font-semibold text-(--text-heading)">Standard delivery</p>
                            <p class="text-xxs text-(--text-muted)">
                                Reserved from stock. Tracking number on confirmation.
                            </p>
                        </div>
                        <span class="badge-soft-success text-xxs px-2 py-0.5 rounded">FREE</span>
                    </label>
                    <p class="text-xxs text-(--text-muted) italic">
                        Express logistics will be wired in a future release. Sandbox checkout carries no real
                        shipping cost.
                    </p>
                </section>

                <section class="glass-card rounded-2xl p-5 space-y-4">
                    <header class="flex items-center gap-2">
                        <span class="badge-soft-primary text-xxs font-mono px-2 py-0.5 rounded">03</span>
                        <h2 class="text-sm font-semibold text-(--text-heading)">Payment method</h2>
                    </header>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                        <label v-for="p in providers" :key="p.value"
                            class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition-all"
                            :class="form.provider === p.value
                                ? 'border-(--color-primary) bg-(--color-primary)/5'
                                : 'border-(--border-color) hover:border-(--color-primary)/40'">
                            <input v-model="form.provider" :value="p.value" type="radio" name="provider"
                                class="form-radio" />
                            <i :class="['ti', p.icon, 'text-(--color-primary) text-lg']" />
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-(--text-heading)">{{ p.label }}</p>
                                <p class="text-xxs text-(--text-muted) truncate">{{ p.note }}</p>
                            </div>
                        </label>
                    </div>
                    <p class="text-xxs text-(--text-muted) italic inline-flex items-center gap-1.5">
                        <i class="ti ti-lock text-[12px]" />
                        Sandbox mode: payment is confirmed with a stub charge id. No card details are stored.
                    </p>
                </section>
            </div>

            <aside class="lg:col-span-5">
                <div class="glass-card rounded-2xl p-5 sticky top-20 space-y-4">
                    <header class="flex items-center justify-between border-b border-(--border-color) pb-3">
                        <h3 class="text-sm font-semibold text-(--text-heading)">Order summary</h3>
                        <span class="badge-soft-primary text-xxs px-2 py-0.5 rounded font-mono">
                            {{ itemCount }} {{ itemCount === 1 ? 'item' : 'items' }}
                        </span>
                    </header>

                    <div class="space-y-3 max-h-[360px] overflow-y-auto -mr-2 pr-2">
                        <div v-for="item in cart.items" :key="item.id" class="flex gap-3">
                            <div class="w-16 h-16 rounded-lg bg-(--bg-muted) overflow-hidden flex-shrink-0">
                                <img v-if="item.productImage" :src="item.productImage"
                                    :alt="item.productName || item.productSku"
                                    class="w-full h-full object-cover" />
                                <div v-else class="w-full h-full flex items-center justify-center">
                                    <i class="ti ti-package text-xl text-(--text-muted)" />
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-(--text-heading) truncate">
                                    {{ item.productName || item.productSku }}
                                </p>
                                <p v-if="variantSpec(item)" class="text-xxs text-(--text-muted) truncate">
                                    {{ variantSpec(item) }}
                                </p>
                                <p class="text-xxs text-(--text-muted) font-mono">Qty {{ item.quantity }}</p>
                                <p class="text-xs font-mono text-(--color-primary) mt-1">
                                    {{ formatMoney(item.lineTotal) }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="space-y-2 text-xs border-t border-(--border-color) pt-4">
                        <div class="flex justify-between">
                            <span class="text-(--text-muted)">Subtotal</span>
                            <span class="font-mono text-(--text-heading)">{{ formatMoney(cart.subtotal) }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-(--text-muted)">Delivery</span>
                            <span class="badge-soft-success text-xxs px-2 py-0.5 rounded">FREE</span>
                        </div>
                    </div>

                    <div class="flex justify-between items-baseline pt-3 border-t border-(--border-color)">
                        <span class="text-sm font-semibold text-(--text-heading)">Total</span>
                        <span class="text-base font-mono text-(--color-primary) font-semibold">
                            {{ formatMoney(cart.subtotal) }}
                        </span>
                    </div>

                    <div v-if="error"
                        class="badge-soft-danger px-3 py-2 rounded inline-flex items-center gap-2 text-xs w-full">
                        <i class="ti ti-alert-circle" />
                        <span class="flex-1">{{ error }}</span>
                    </div>

                    <button class="btn btn-primary w-full inline-flex justify-center gap-2 rounded-full"
                        :disabled="!canSubmit || submitting"
                        @click="placeOrder">
                        <i :class="['ti', submitting ? 'ti-loader-2 animate-spin' : 'ti-credit-card']" />
                        {{ submitting ? 'Placing order...' : 'Place order' }}
                    </button>

                    <p class="text-xxs text-(--text-muted) text-center inline-flex items-center justify-center gap-1.5 w-full">
                        <i class="ti ti-shield-check text-(--color-info)" />
                        Sandbox checkout. Items reserved from stock.
                    </p>
                </div>
            </aside>
        </section>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useShop, type Cart, type CartItem } from '~/composables/useShop'
import { useShopAuthStore } from '~/stores/shop-auth'
import { useCartStateStore } from '~/stores/cart-state'

definePageMeta({ layout: false })

useHead({ title: 'Checkout | Storefront' })

const shop = useShop()
const shopAuth = useShopAuthStore()
const cartState = useCartStateStore()

const cart = ref<Cart | null>(null)
const addresses = ref<any[]>([])
const loading = ref(true)
const submitting = ref(false)
const error = ref('')

const form = ref({
    provider: 'manual' as 'stripe' | 'aba' | 'wing' | 'manual',
    shipping_address_id: null as string | null,
    guest_email: '',
})

const providers = [
    { value: 'stripe', label: 'Stripe', icon: 'ti-brand-stripe', note: 'Card via Stripe' },
    { value: 'aba', label: 'ABA PayWay', icon: 'ti-qrcode', note: 'KH local gateway' },
    { value: 'wing', label: 'Wing', icon: 'ti-wallet', note: 'KH mobile wallet' },
    { value: 'manual', label: 'Bank transfer', icon: 'ti-building-bank', note: 'Manual reconciliation' },
] as const

const itemCount = computed(() => cart.value?.itemCount ?? cart.value?.items?.length ?? 0)

const canSubmit = computed(() => {
    if (!cart.value || (cart.value.items?.length ?? 0) === 0) return false
    if (shopAuth.isAuthenticated) return !!form.value.shipping_address_id
    return !!form.value.guest_email
})

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

async function placeOrder() {
    if (!canSubmit.value || submitting.value) return
    submitting.value = true
    error.value = ''
    try {
        const clientUuid = randomUUID()
        const res = await shop.checkout.initiate({
            client_uuid: clientUuid,
            provider: form.value.provider,
            shipping_address_id: form.value.shipping_address_id || undefined,
            billing_address_id: form.value.shipping_address_id || undefined,
            guest_email: !shopAuth.isAuthenticated ? form.value.guest_email : undefined,
        })
        await shop.checkout.confirmDirect(res.order.id, {
            charge_id: `sandbox_${clientUuid}`,
            gateway_fee: 0,
        })
        // Checkout consumed the cart server-side — refresh local state so
        // the badge zeros out before the order page paints.
        await cartState.refresh()
        navigateTo(`/shop/order/${res.order.id}`)
    } catch (e: any) {
        error.value = e?.data?.message || 'Checkout failed.'
    } finally {
        submitting.value = false
    }
}

onMounted(async () => {
    try {
        const c = await shop.cart.show()
        cart.value = c.data
    } catch {}

    if (shopAuth.isAuthenticated) {
        try {
            const a = await shop.addresses.list()
            addresses.value = a.data ?? []
            const def = addresses.value.find((x: any) => x.isDefaultShipping) ?? addresses.value[0]
            if (def) form.value.shipping_address_id = def.id
        } catch {}
    }

    loading.value = false
})
</script>
