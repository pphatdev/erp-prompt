<template>
    <div class="min-h-screen flex flex-col bg-(--bg-layout)">
        <header class="sticky top-0 z-30 backdrop-blur bg-(--bg-card)/85 border-b border-(--border-color)">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 h-16 flex items-center justify-between gap-4">
                <NuxtLink to="/shop" class="flex items-center gap-2 text-(--text-heading)">
                    <i class="ti ti-shopping-bag text-xl text-(--color-primary)" />
                    <span class="font-semibold text-sm">{{ brandName }}</span>
                </NuxtLink>

                <nav class="hidden md:flex items-center gap-1 text-xs">
                    <NuxtLink to="/shop"
                        class="shop-nav-link"
                        :class="isActive('/shop', true) ? 'shop-nav-link--active' : ''">
                        Home
                    </NuxtLink>
                    <NuxtLink to="/shop/products"
                        class="shop-nav-link"
                        :class="isActive('/shop/products') ? 'shop-nav-link--active' : ''">
                        Catalog
                    </NuxtLink>
                    <NuxtLink to="/shop/cart"
                        class="shop-nav-link"
                        :class="isActive('/shop/cart') ? 'shop-nav-link--active' : ''">
                        Cart
                    </NuxtLink>
                    <NuxtLink v-if="shopAuth.isAuthenticated" to="/shop/account"
                        class="shop-nav-link"
                        :class="isActive('/shop/account') ? 'shop-nav-link--active' : ''">
                        Account
                    </NuxtLink>
                </nav>

                <div class="flex items-center gap-1">
                    <NuxtLink to="/shop/products"
                        class="shop-icon-link"
                        :class="isActive('/shop/products') ? 'shop-icon-link--active' : ''"
                        title="Search catalog">
                        <i class="ti ti-search text-base" />
                    </NuxtLink>
                    <NuxtLink to="/shop/cart"
                        class="relative shop-icon-link"
                        :class="isActive('/shop/cart') ? 'shop-icon-link--active' : ''" title="Cart">
                        <i class="ti ti-shopping-cart text-base" />
                        <transition name="cart-badge">
                            <span v-if="cartState.count > 0" :key="cartState.count"
                                class="cart-badge absolute -top-0.5 -right-0.5 bg-(--color-primary) text-white text-[10px] font-semibold rounded-full flex items-center justify-center px-1 min-w-4 h-4">
                                {{ cartState.count > 99 ? '99+' : cartState.count }}
                            </span>
                        </transition>
                    </NuxtLink>
                    <template v-if="shopAuth.isAuthenticated">
                        <NuxtLink to="/shop/account"
                            class="shop-icon-link"
                            :class="isActive('/shop/account') ? 'shop-icon-link--active' : ''"
                            title="My account">
                            <i class="ti ti-user-circle text-base" />
                        </NuxtLink>
                        <button
                            class="text-xs px-3 py-2 rounded text-(--text-muted) hover:text-(--text-heading)"
                            @click="logout">Logout</button>
                    </template>
                    <template v-else>
                        <NuxtLink to="/shop/auth/login"
                            class="text-xs px-3 py-2 rounded text-(--text-muted) hover:text-(--text-heading)">
                            Login
                        </NuxtLink>
                        <NuxtLink to="/shop/auth/register"
                            class="btn btn-soft-primary text-xs rounded-full">Register</NuxtLink>
                    </template>
                </div>
            </div>
        </header>

        <main class="flex-1 w-full max-w-6xl mx-auto px-4 sm:px-6 py-8">
            <slot />
        </main>

        <footer class="border-t border-(--border-color) bg-(--bg-card)/40">
            <div class="max-w-6xl mx-auto px-4 sm:px-6 py-6 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
                <p class="text-xxs text-(--text-muted)">
                    &copy; {{ new Date().getFullYear() }} {{ brandName }} - Storefront powered by ERP.
                </p>
                <nav class="flex items-center gap-4 text-xxs text-(--text-muted)">
                    <NuxtLink to="/shop/products" class="hover:text-(--text-heading)">Catalog</NuxtLink>
                    <NuxtLink to="/shop/cart" class="hover:text-(--text-heading)">Cart</NuxtLink>
                    <NuxtLink v-if="!shopAuth.isAuthenticated" to="/shop/auth/login" class="hover:text-(--text-heading)">Sign in</NuxtLink>
                    <NuxtLink v-else to="/shop/account" class="hover:text-(--text-heading)">Account</NuxtLink>
                </nav>
            </div>
        </footer>
    </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { useShopAuthStore } from '~/stores/shop-auth'
import { useCartStateStore } from '~/stores/cart-state'

const shopAuth = useShopAuthStore()
const cartState = useCartStateStore()
const route = useRoute()
const brandName = ref('Storefront')

/**
 * Active-link matcher. `exact` is used for `/shop` (Home) — without it,
 * Home would match every nested shop route via the prefix rule and end
 * up always-active. Everything else uses prefix matching so a detail page
 * like `/shop/products/<id>` still lights up `Catalog`.
 */
function isActive(target: string, exact = false): boolean {
    if (exact) return route.path === target
    return route.path === target || route.path.startsWith(target + '/')
}

onMounted(async () => {
    shopAuth.initFromStorage()
    if (shopAuth.accessToken) {
        await shopAuth.refreshMe()
    }
    await cartState.refresh()
})

async function logout() {
    await shopAuth.logout()
    cartState.reset()
    navigateTo('/shop')
}
</script>

<style scoped>
.shop-nav-link {
    display: inline-flex;
    align-items: center;
    padding: 0.5rem 0.75rem;
    border-radius: 9999px;
    color: var(--text-muted);
    transition: background 0.15s ease, color 0.15s ease;
}

.shop-nav-link:hover {
    color: var(--text-heading);
    background: var(--bg-muted);
}

.shop-nav-link--active {
    color: var(--color-primary);
    background: rgb(var(--color-primary-rgb) / 0.12);
    font-weight: 600;
    box-shadow: inset 0 0 0 1px rgb(var(--color-primary-rgb) / 0.25);
}

.shop-icon-link {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem;
    border-radius: 9999px;
    color: var(--text-heading);
    transition: background 0.15s ease, color 0.15s ease;
}

.shop-icon-link:hover {
    background: var(--bg-muted);
}

.shop-icon-link--active {
    color: var(--color-primary);
    background: rgb(var(--color-primary-rgb) / 0.12);
    box-shadow: inset 0 0 0 1px rgb(var(--color-primary-rgb) / 0.25);
}

/* Pop-in feedback when the count changes — keyed by count so each
 * mutation triggers a fresh enter transition. */
.cart-badge-enter-active,
.cart-badge-leave-active {
    transition: transform 0.25s cubic-bezier(0.34, 1.56, 0.64, 1), opacity 0.15s ease;
}

.cart-badge-enter-from,
.cart-badge-leave-to {
    transform: scale(0.4);
    opacity: 0;
}
</style>
