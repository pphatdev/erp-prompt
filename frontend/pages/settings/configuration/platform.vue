<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Page header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Platform &amp; Subdomains</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        View global system domain and subdomain formats.
                    </p>
                </div>
            </header>

            <div class="flex-1 min-w-0">
                <!-- Loading -->
                <div v-if="loading" class="py-16 flex justify-center">
                    <span
                        class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                </div>

                <!-- Platform -->
                <section v-else class="glass-card rounded-2xl p-6 space-y-6">
                    <div
                        class="rounded-xl border border-(--color-info)/30 bg-(--color-info)/8 px-4 py-3 text-xs text-(--color-info) flex items-start gap-2">
                        <i class="ti ti-info-circle shrink-0 mt-0.5" />
                        <span>
                            The system domain is set by <code class="font-mono">APP_SYSTEM_DOMAIN</code> in the
                            server's
                            <code class="font-mono">.env</code> file and applies to every tenant on this platform.
                            It cannot be changed per-tenant here.
                        </span>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
                                System domain
                            </label>
                            <div class="flex items-center gap-3">
                                <input :value="draft['platform.system_domain'] ?? '—'" type="text" readonly
                                    class="form-control bg-(--bg-muted) cursor-not-allowed font-mono" />
                            </div>
                            <p class="text-xxs text-(--text-muted) mt-1">
                                Wildcard DNS must resolve <code
                                    class="font-mono">*.{{ draft['platform.system_domain'] ?? '…' }}</code>
                                to this server's IP address.
                            </p>
                        </div>

                        <div>
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
                                Subdomain format
                            </label>
                            <div
                                class="rounded-lg bg-(--bg-muted) border border-(--border-color) px-4 py-3 font-mono text-sm text-(--text-heading)">
                                <span class="text-(--color-primary)">{tenant-handle}</span>.{{
                                    draft['platform.system_domain'] ??
                                    'systemdomain.app' }}
                            </div>
                            <p class="text-xxs text-(--text-muted) mt-1">
                                Each tenant customer gets a unique subdomain derived from their handle.
                            </p>
                        </div>

                        <div>
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
                                Example
                            </label>
                            <a :href="`https://acme-corp.${draft['platform.system_domain'] ?? 'systemdomain.app'}`"
                                target="_blank" rel="noopener"
                                class="inline-flex items-center gap-1.5 text-xs font-mono text-(--color-primary) hover:underline">
                                <i class="ti ti-external-link" />
                                acme-corp.{{ draft['platform.system_domain'] ?? 'systemdomain.app' }}
                            </a>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { useSettings, type SettingRow } from '~/composables/useSettings'
import { useAuthStore } from '~/stores/auth'

const authStore = useAuthStore()
const settingsApi = useSettings()

definePageMeta({
    breadcrumb: 'Platform & Subdomains',
    middleware: [
        function (to, from) {
            const authStore = useAuthStore()
            if (!authStore.isAdmin) return navigateTo('/dashboard')
        }
    ]
})

const loading = ref(true)
const draft = reactive<Record<string, unknown>>({})

const hydrate = (rows: SettingRow[]) => {
    const map = settingsApi.toMap(rows)
    for (const k of Object.keys(draft)) delete draft[k]
    
    Object.keys(map).forEach(k => {
        if (k.startsWith('platform.')) {
            draft[k] = map[k]
        }
    })
}

const load = async () => {
    loading.value = true
    try {
        const { data } = await settingsApi.list()
        hydrate(data)
    } catch (err: any) {
        // Silent error handling
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    load()
})
</script>
