<template>
    <NuxtLayout name="shop">
        <div class="max-w-md mx-auto py-12">
            <div class="glass-card rounded-2xl p-6 space-y-5">
                <header class="space-y-1 text-center">
                    <h1 class="text-xl font-semibold text-(--text-heading)">Create your account</h1>
                    <p class="text-xs text-(--text-muted)">Track orders and save addresses.</p>
                </header>

                <form class="space-y-3" @submit.prevent="submit">
                    <div class="grid grid-cols-2 gap-2">
                        <div>
                            <label class="text-xs text-(--text-muted)">First name</label>
                            <input v-model="form.first_name" type="text" class="form-control text-sm mt-1" />
                        </div>
                        <div>
                            <label class="text-xs text-(--text-muted)">Last name</label>
                            <input v-model="form.last_name" type="text" class="form-control text-sm mt-1" />
                        </div>
                    </div>
                    <div>
                        <label class="text-xs text-(--text-muted)">Email</label>
                        <input v-model="form.email" type="email" required class="form-control text-sm mt-1" />
                    </div>
                    <div>
                        <label class="text-xs text-(--text-muted)">Password</label>
                        <input v-model="form.password" type="password" required minlength="8" class="form-control text-sm mt-1" />
                    </div>
                    <div>
                        <label class="text-xs text-(--text-muted)">Phone (optional)</label>
                        <input v-model="form.phone" type="tel" class="form-control text-sm mt-1" />
                    </div>
                    <div v-if="error" class="text-xs text-(--color-danger)">{{ error }}</div>
                    <button class="btn btn-primary w-full" :disabled="loading" type="submit">
                        {{ loading ? 'Creating...' : 'Create account' }}
                    </button>
                </form>

                <p class="text-xs text-center text-(--text-muted)">
                    Already have an account? <NuxtLink to="/shop/auth/login" class="text-(--color-primary)">Sign in</NuxtLink>
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
const form = ref({ email: '', password: '', first_name: '', last_name: '', phone: '' })
const loading = ref(false)
const error = ref('')

onMounted(() => {
    shopAuth.initFromStorage()
    if (shopAuth.isAuthenticated) {
        navigateTo(destinationAfterRegister(), { replace: true })
    }
})

function destinationAfterRegister(): string {
    const raw = String(route.query.redirect ?? '/shop')
    return raw.startsWith('/shop') ? raw : '/shop'
}

async function submit() {
    loading.value = true
    error.value = ''
    try {
        await shopAuth.register({ ...form.value })
        navigateTo(destinationAfterRegister())
    } catch (e: any) {
        error.value = shopAuth.error || e?.data?.message || 'Registration failed.'
    } finally {
        loading.value = false
    }
}
</script>
