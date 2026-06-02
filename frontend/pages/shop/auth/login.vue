<template>
    <NuxtLayout name="shop">
        <div class="max-w-md mx-auto py-12">
            <div class="glass-card rounded-2xl p-6 space-y-5">
                <header class="space-y-1 text-center">
                    <h1 class="text-xl font-semibold text-(--text-heading)">Welcome back</h1>
                    <p class="text-xs text-(--text-muted)">Log in to your storefront account.</p>
                </header>

                <form class="space-y-3" @submit.prevent="submit">
                    <div>
                        <label class="text-xs text-(--text-muted)">Email</label>
                        <input v-model="email" type="email" required class="form-control text-sm mt-1" />
                    </div>
                    <div>
                        <label class="text-xs text-(--text-muted)">Password</label>
                        <input v-model="password" type="password" required class="form-control text-sm mt-1" />
                    </div>
                    <div v-if="error" class="text-xs text-(--color-danger)">{{ error }}</div>
                    <button class="btn btn-primary w-full" :disabled="loading" type="submit">
                        {{ loading ? 'Signing in...' : 'Sign in' }}
                    </button>
                </form>

                <p class="text-xs text-center text-(--text-muted)">
                    New here? <NuxtLink to="/shop/auth/register" class="text-(--color-primary)">Create an account</NuxtLink>
                </p>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useShopAuthStore } from '~/stores/shop-auth'

definePageMeta({ layout: false })

const route = useRoute()
const shopAuth = useShopAuthStore()
const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

// Already logged in? Skip the login page entirely.
onMounted(() => {
    shopAuth.initFromStorage()
    if (shopAuth.isAuthenticated) {
        navigateTo(destinationAfterLogin(), { replace: true })
    }
})

/**
 * Bounce back to the URL the shop-auth middleware stashed in `?redirect=`
 * when it caught an unauthenticated request. Defaults to `/shop`. Strips
 * any URL that doesn't start with `/shop/` so a malicious referrer can't
 * use this as an open redirect.
 */
function destinationAfterLogin(): string {
    const raw = String(route.query.redirect ?? '/shop')
    return raw.startsWith('/shop') ? raw : '/shop'
}

async function submit() {
    loading.value = true
    error.value = ''
    try {
        await shopAuth.login(email.value, password.value)
        navigateTo(destinationAfterLogin())
    } catch (e: any) {
        error.value = shopAuth.error || e?.data?.message || 'Login failed.'
    } finally {
        loading.value = false
    }
}
</script>
