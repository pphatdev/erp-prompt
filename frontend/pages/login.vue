<template>
    <NuxtLayout name="auth">
        <div class="glass-card rounded-2xl p-8 shadow-(--shadow-lg) relative">
            <div class="flex flex-col items-center mb-8">
                <div
                    class="w-12 h-12 rounded-xl bg-linear-to-tr from-(--color-primary) to-(--color-info) flex items-center justify-center font-bold text-white shadow-(--shadow-md) mb-4">
                    <i class="ti ti-chart-pie text-xl" />
                </div>
                <h2 class="text-2xl font-bold tracking-tight text-gradient mb-1">Welcome back</h2>
                <p class="text-xs text-(--text-muted) font-medium">
                    Access {{ hasSubdomain ? tenantStore.activeName : 'your enterprise dashboard' }}
                </p>
            </div>

            <div v-if="errorMsg"
                class="mb-6 px-4 py-3 rounded-lg badge-soft-danger flex items-center gap-2 text-xs font-semibold">
                <i class="ti ti-alert-triangle" />
                <span>{{ errorMsg }}</span>
            </div>

            <form @submit.prevent="submitLogin" class="space-y-5">
                <div v-if="!hasSubdomain">
                    <label class="block text-[10px] font-bold text-(--text-muted) uppercase tracking-wider mb-2">Company
                        Handle</label>
                    <div class="relative">
                        <i class="ti ti-at absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm"></i>
                        <input v-model="handle" type="text" required placeholder="company-handle"
                            class="form-control pl-8" />
                    </div>
                    <span class="text-[10px] text-(--text-muted) mt-1 block">
                        Use <b class="font-mono text-(--text-body)">demo</b> for the local seeder tenant
                    </span>
                </div>

                <div>
                    <label class="block text-[10px] font-bold text-(--text-muted) uppercase tracking-wider mb-2">Email
                        Address</label>
                    <input v-model="email" type="email" required placeholder="admin@example.com" class="form-control" />
                </div>

                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label
                            class="block text-[10px] font-bold text-(--text-muted) uppercase tracking-wider">Password</label>
                        <a class="text-[10px] text-(--color-primary) font-semibold hover:underline cursor-pointer">Forgot
                            password?</a>
                    </div>
                    <input v-model="password" type="password" required placeholder="••••••••" class="form-control" />
                </div>

                <label class="flex items-center gap-2 text-xs text-(--text-body) select-none">
                    <input type="checkbox" class="w-4 h-4 rounded border-(--border-color) text-(--color-primary)" />
                    Remember this browser
                </label>

                <button type="submit" :disabled="loading" class="btn btn-primary w-full py-3">
                    <span v-if="loading"
                        class="w-4 h-4 rounded-full border-2 border-white/30 border-t-white animate-spin" />
                    <span>{{ loading ? 'Securing connection...' : 'Establish session' }}</span>
                </button>
            </form>

            <div
                class="mt-8 text-center text-[10px] text-(--text-muted) border-t border-(--border-color) pt-6 font-mono uppercase tracking-widest">
                Protected by Enterprise TLS & OAuth2
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '~/stores/auth'
import { useTenantStore } from '~/stores/tenant'

definePageMeta({ layout: false })

const router = useRouter()
const authStore = useAuthStore()
const tenantStore = useTenantStore()

const handle = ref('demo')
const email = ref('admin@example.com')
const password = ref('password')
const loading = ref(false)
const errorMsg = ref('')

const hasSubdomain = ref(false)
const detectedHandle = ref('')

onMounted(() => {
    const hostname = window.location.hostname
    const parts = hostname.split('.')

    // Exclude IP addresses
    const isIP = /^(?:[0-9]{1,3}\.){3}[0-9]{1,3}$/.test(hostname)

    if (!isIP) {
        // Example: acme.localhost -> parts: ['acme', 'localhost']
        // Example: acme.erp-apps.com -> parts: ['acme', 'erp-apps', 'com']
        if (parts.length > 2 || (parts.length === 2 && parts[1] === 'localhost')) {
            const subdomain = parts[0].toLowerCase()
            // Exclude standard structural subdomains
            if (subdomain !== 'www' && subdomain !== 'app' && subdomain !== 'dev') {
                hasSubdomain.value = true
                detectedHandle.value = subdomain
                handle.value = subdomain
                tenantStore.setTenantByHandle(subdomain)
            }
        }
    }
})

const submitLogin = async () => {
    loading.value = true
    errorMsg.value = ''
    try {
        tenantStore.setTenantByHandle(handle.value)
        const success = await authStore.login(email.value, password.value)
        if (success) router.push('/dashboard')
    } catch (err: any) {
        errorMsg.value = err.data?.message || err.message || 'Access denied. Please check your credentials.'
    } finally {
        loading.value = false
    }
}
</script>
