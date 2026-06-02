<template>
    <div class="min-h-screen flex flex-col bg-(--bg-page)">
        <header
            class="sticky top-0 z-30 backdrop-blur bg-(--bg-page)/90 border-b border-(--border-color)">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-4">
                <NuxtLink to="/shop" class="flex items-center gap-2 text-(--text-heading)">
                    <i class="ti ti-shopping-bag text-xl text-(--color-primary)" />
                    <span class="font-semibold text-sm">{{ brandName }}</span>
                </NuxtLink>

                <nav class="hidden md:flex items-center gap-1 text-xs">
                    <NuxtLink to="/shop/products"
                        class="px-3 py-2 rounded text-(--text-muted) hover:text-(--text-heading) hover:bg-(--bg-muted)">
                        Shop
                    </NuxtLink>
                    <NuxtLink v-if="shopAuth.isAuthenticated" to="/shop/account"
                        class="px-3 py-2 rounded text-(--text-muted) hover:text-(--text-heading) hover:bg-(--bg-muted)">
                        Account
                    </NuxtLink>
                </nav>

                <div class="flex items-center gap-2">
                    <NuxtLink to="/shop/cart" class="relative p-2 rounded hover:bg-(--bg-muted) text-(--text-heading)">
                        <i class="ti ti-shopping-cart text-lg" />
                        <span v-if="cartCount > 0"
                            class="absolute -top-1 -right-1 bg-(--color-primary) text-white text-[10px] font-semibold w-4 h-4 rounded-full flex items-center justify-center">
                            {{ cartCount }}
                        </span>
                    </NuxtLink>
                    <template v-if="shopAuth.isAuthenticated">
                        <button
                            class="text-xs px-3 py-2 rounded text-(--text-muted) hover:text-(--text-heading)"
                            @click="logout">Logout</button>
                    </template>
                    <template v-else>
                        <NuxtLink to="/shop/auth/login"
                            class="text-xs px-3 py-2 rounded text-(--text-muted) hover:text-(--text-heading)">Login</NuxtLink>
                        <NuxtLink to="/shop/auth/register"
                            class="btn btn-soft-primary text-xs">Register</NuxtLink>
                    </template>
                </div>
            </div>
        </header>

        <main class="flex-1 w-full max-w-6xl mx-auto px-4 sm:px-6 py-8">
            <slot />
        </main>

        <footer class="border-t border-(--border-color) py-6 text-center text-xxs text-(--text-muted)">
            &copy; {{ new Date().getFullYear() }} {{ brandName }} - Storefront powered by ERP.
        </footer>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useShopAuthStore } from '~/stores/shop-auth'
import { useShop } from '~/composables/useShop'

const shopAuth = useShopAuthStore()
const cartCount = ref(0)
const brandName = ref('Storefront')

onMounted(async () => {
    shopAuth.initFromStorage()
    if (shopAuth.accessToken) {
        await shopAuth.refreshMe()
    }
    try {
        const cart = await useShop().cart.show()
        cartCount.value = cart?.data?.itemCount ?? cart?.data?.items?.length ?? 0
    } catch {}
})

async function logout() {
    await shopAuth.logout()
    navigateTo('/shop')
}
</script>
