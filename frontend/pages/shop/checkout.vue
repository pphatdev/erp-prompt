<template>
    <NuxtLayout name="shop">
        <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-6">
                <h1 class="text-xl font-semibold text-(--text-heading)">Checkout</h1>

                <div v-if="!shopAuth.isAuthenticated" class="glass-card rounded-xl p-4 space-y-3">
                    <h3 class="text-sm font-semibold text-(--text-heading)">Guest checkout</h3>
                    <input v-model="form.guest_email" type="email" required placeholder="Email for order receipt"
                        class="form-control text-sm" />
                    <p class="text-xxs text-(--text-muted)">
                        Have an account? <NuxtLink to="/shop/auth/login" class="text-(--color-primary)">Log in</NuxtLink>
                    </p>
                </div>

                <div v-else-if="addresses.length > 0" class="glass-card rounded-xl p-4 space-y-3">
                    <h3 class="text-sm font-semibold text-(--text-heading)">Shipping address</h3>
                    <select v-model="form.shipping_address_id" class="form-control text-sm">
                        <option :value="null">Select an address</option>
                        <option v-for="a in addresses" :key="a.id" :value="a.id">
                            {{ a.label || a.recipientName }} - {{ a.line1 }}, {{ a.city }}
                        </option>
                    </select>
                </div>

                <div v-else-if="shopAuth.isAuthenticated" class="glass-card rounded-xl p-4 text-xs text-(--text-muted)">
                    No saved addresses. <NuxtLink to="/shop/account" class="text-(--color-primary)">Add one in your account.</NuxtLink>
                </div>

                <div class="glass-card rounded-xl p-4 space-y-3">
                    <h3 class="text-sm font-semibold text-(--text-heading)">Payment method</h3>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                        <label v-for="p in providers" :key="p.value"
                            class="border border-(--border-color) rounded-lg p-3 cursor-pointer text-xs flex items-center gap-2"
                            :class="form.provider === p.value ? 'border-(--color-primary) bg-(--color-primary)/5' : ''">
                            <input v-model="form.provider" type="radio" :value="p.value" class="sr-only" />
                            <i :class="['ti', p.icon]" />{{ p.label }}
                        </label>
                    </div>
                </div>

                <div v-if="error" class="text-xs px-3 py-2 rounded bg-(--color-danger)/10 text-(--color-danger)">
                    {{ error }}
                </div>
            </div>

            <aside class="glass-card rounded-2xl p-5 h-fit space-y-4">
                <h3 class="text-sm font-semibold text-(--text-heading)">Order total</h3>
                <div class="space-y-2 text-xs">
                    <div class="flex justify-between"><span class="text-(--text-muted)">Items</span><span class="font-mono">{{ cart?.itemCount ?? cart?.items?.length ?? 0 }}</span></div>
                    <div class="flex justify-between"><span class="text-(--text-muted)">Subtotal</span><span class="font-mono">{{ formatMoney(cart?.subtotal ?? 0) }}</span></div>
                    <div class="flex justify-between text-sm font-semibold pt-2 border-t border-(--border-color)">
                        <span>Total</span>
                        <span class="font-mono text-(--color-primary)">{{ formatMoney(cart?.subtotal ?? 0) }}</span>
                    </div>
                </div>
                <button class="btn btn-primary w-full inline-flex justify-center items-center gap-2"
                    :disabled="submitting || !canSubmit" @click="placeOrder">
                    <i class="ti" :class="submitting ? 'ti-loader animate-spin' : 'ti-credit-card'" />
                    {{ submitting ? 'Placing order...' : 'Place order' }}
                </button>
                <p class="text-xxs text-(--text-muted) text-center">
                    This is a sandbox checkout. Production payments require webhook configuration.
                </p>
            </aside>
        </section>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useShop, type Cart } from '~/composables/useShop'
import { useShopAuthStore } from '~/stores/shop-auth'

definePageMeta({ layout: false })

const shopAuth = useShopAuthStore()
const shop = useShop()

const cart = ref<Cart | null>(null)
const addresses = ref<any[]>([])
const submitting = ref(false)
const error = ref('')

const form = ref({
    provider: 'manual' as 'stripe' | 'aba' | 'wing' | 'manual',
    shipping_address_id: null as string | null,
    billing_address_id: null as string | null,
    guest_email: '',
})

const providers = [
    { value: 'stripe', label: 'Stripe', icon: 'ti-brand-stripe' },
    { value: 'aba', label: 'ABA PayWay', icon: 'ti-credit-card' },
    { value: 'wing', label: 'Wing', icon: 'ti-wallet' },
    { value: 'manual', label: 'Bank transfer', icon: 'ti-building-bank' },
] as const

const canSubmit = computed(() => {
    if (!cart.value || (cart.value.items?.length ?? 0) === 0) return false
    if (!shopAuth.isAuthenticated && !form.value.guest_email) return false
    return true
})

const formatMoney = (n: number | null | undefined) =>
    (Number.isFinite(Number(n)) ? Number(n) : 0).toLocaleString(undefined, { style: 'currency', currency: 'USD' })

async function placeOrder() {
    submitting.value = true
    error.value = ''
    try {
        const clientUuid = randomUUID()
        const res = await shop.checkout.initiate({
            client_uuid: clientUuid,
            provider: form.value.provider,
            shipping_address_id: form.value.shipping_address_id || undefined,
            billing_address_id: form.value.billing_address_id || form.value.shipping_address_id || undefined,
            guest_email: !shopAuth.isAuthenticated ? form.value.guest_email : undefined,
        })
        // Sandbox: directly confirm with a stub charge_id so the demo flow completes.
        await shop.checkout.confirmDirect(res.order.id, {
            charge_id: `sandbox_${clientUuid}`,
            gateway_fee: 0,
        })
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
            const def = addresses.value.find(x => x.isDefaultShipping) ?? addresses.value[0]
            if (def) form.value.shipping_address_id = def.id
        } catch {}
    }
})
</script>
