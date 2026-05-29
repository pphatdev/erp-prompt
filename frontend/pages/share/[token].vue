<template>
    <div class="min-h-screen flex items-center justify-center p-6 bg-(--bg-app)">
        <div class="glass-card rounded-2xl max-w-lg w-full p-8 space-y-5 shadow-(--shadow-lg)">
            <header class="text-center space-y-1">
                <i class="ti ti-link text-3xl text-(--color-primary)" />
                <h1 class="text-lg font-semibold">Shared Document</h1>
            </header>

            <div v-if="state === 'loading'" class="py-8 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading...</span>
            </div>

            <div v-else-if="state === 'password'" class="space-y-3">
                <p class="text-xs text-(--text-muted) text-center">This link is password-protected. Enter the password to continue.</p>
                <form @submit.prevent="loadShare" class="space-y-3">
                    <input v-model="password" type="password" required placeholder="Password"
                        class="form-control text-sm" />
                    <button type="submit" class="btn btn-primary text-xs w-full" :disabled="loading">
                        <i v-if="loading" class="ti ti-loader animate-spin" /><i v-else class="ti ti-lock-open" />
                        Unlock
                    </button>
                </form>
                <p v-if="error" class="text-xxs text-(--color-danger) text-center">{{ error }}</p>
            </div>

            <div v-else-if="state === 'ready' && info" class="space-y-3">
                <div class="border border-(--border-color) rounded-xl p-4 space-y-2">
                    <p class="text-xs text-(--text-muted) uppercase tracking-widest font-bold">{{ shortMime(info.mimeType) }}</p>
                    <h2 class="text-sm font-semibold break-words">{{ info.title }}</h2>
                    <p class="text-xxs text-(--text-muted) break-all">{{ info.filename }} · {{ formatBytes(info.sizeBytes) }}</p>
                    <p v-if="info.expiresAt" class="text-xxs text-(--text-muted)">Expires {{ formatDate(info.expiresAt) }}</p>
                    <p v-if="info.downloadsRemaining !== null" class="text-xxs text-(--text-muted)">{{ info.downloadsRemaining }} downloads remaining</p>
                </div>
                <button type="button" class="btn btn-primary text-xs w-full" :disabled="downloading" @click="downloadFile">
                    <i v-if="downloading" class="ti ti-loader animate-spin" /><i v-else class="ti ti-download" />
                    Download
                </button>
            </div>

            <div v-else-if="state === 'expired'" class="py-6 text-center space-y-2">
                <i class="ti ti-clock-cancel text-3xl text-(--color-danger)" />
                <p class="text-sm font-semibold">This link is no longer valid.</p>
                <p class="text-xxs text-(--text-muted)">It may have expired or been revoked by the owner.</p>
            </div>

            <div v-else-if="state === 'rate-limited'" class="py-6 text-center space-y-2">
                <i class="ti ti-exclamation-circle text-3xl text-(--color-warning)" />
                <p class="text-sm font-semibold">Download limit reached.</p>
                <p class="text-xxs text-(--text-muted)">The maximum number of downloads has been reached.</p>
            </div>

            <div v-else class="py-6 text-center space-y-2">
                <i class="ti ti-alert-circle text-3xl text-(--color-danger)" />
                <p class="text-sm font-semibold">Could not load the link.</p>
                <p v-if="error" class="text-xxs text-(--text-muted)">{{ error }}</p>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'

interface PublicShareInfo {
    title: string
    filename: string
    mimeType: string
    sizeBytes: number
    expiresAt: string | null
    downloadsRemaining: number | null
}

type ViewState = 'loading' | 'password' | 'ready' | 'expired' | 'rate-limited' | 'error'

const route = useRoute()
const router = useRouter()
const runtime = useRuntimeConfig()

const token = computed(() => String(route.params.token))
const tenantHandle = computed(() => String(route.query.tenant ?? ''))

const state = ref<ViewState>('loading')
const loading = ref(false)
const downloading = ref(false)
const password = ref('')
const error = ref('')
const info = ref<PublicShareInfo | null>(null)

const apiBase = (path: string, params: Record<string, string | undefined> = {}) => {
    const url = new URL(`${runtime.public.apiBase}${path}`, window.location.origin)
    for (const [k, v] of Object.entries(params)) {
        if (v) url.searchParams.set(k, v)
    }
    return url.toString()
}

const fetchShare = async (pwd?: string) => {
    const res = await fetch(apiBase(`/public/shares/${token.value}`, { password: pwd }), {
        headers: {
            'X-Tenant-Handle': tenantHandle.value,
            'Accept': 'application/json',
        },
    })
    return res
}

const loadShare = async () => {
    if (!tenantHandle.value) {
        state.value = 'error'
        error.value = 'Link is missing tenant context.'
        return
    }
    loading.value = true
    error.value = ''
    try {
        const res = await fetchShare(password.value || undefined)
        if (res.status === 403) {
            state.value = 'password'
            if (password.value) error.value = 'Incorrect password.'
            return
        }
        if (res.status === 410) {
            state.value = 'expired'
            return
        }
        if (res.status === 429) {
            state.value = 'rate-limited'
            return
        }
        if (!res.ok) {
            state.value = 'error'
            error.value = `HTTP ${res.status}`
            return
        }
        const body = await res.json()
        info.value = body.data
        state.value = 'ready'
    } catch (err: any) {
        state.value = 'error'
        error.value = err?.message ?? 'Network error'
    } finally {
        loading.value = false
    }
}

const downloadFile = async () => {
    downloading.value = true
    try {
        const url = apiBase(`/public/shares/${token.value}/download`, { password: password.value || undefined })
        const res = await fetch(url, {
            headers: { 'X-Tenant-Handle': tenantHandle.value },
        })
        if (res.status === 429) { state.value = 'rate-limited'; return }
        if (res.status === 410) { state.value = 'expired'; return }
        if (!res.ok) throw new Error(`HTTP ${res.status}`)
        const blob = await res.blob()
        const blobUrl = URL.createObjectURL(blob)
        const a = document.createElement('a')
        a.href = blobUrl
        a.download = info.value?.filename ?? 'download'
        document.body.appendChild(a)
        a.click()
        document.body.removeChild(a)
        setTimeout(() => URL.revokeObjectURL(blobUrl), 1000)
    } catch (err: any) {
        error.value = err?.message ?? 'Download failed'
    } finally {
        downloading.value = false
    }
}

const formatBytes = (bytes: number): string => {
    if (!bytes || bytes < 0) return '—'
    const units = ['B', 'KB', 'MB', 'GB', 'TB']
    let value = bytes
    let unitIdx = 0
    while (value >= 1024 && unitIdx < units.length - 1) { value /= 1024; unitIdx++ }
    return `${value.toFixed(unitIdx === 0 ? 0 : 1)} ${units[unitIdx]}`
}

const formatDate = (iso: string | null): string => {
    if (!iso) return '—'
    try {
        return new Date(iso).toLocaleString()
    } catch {
        return iso
    }
}

const shortMime = (mime: string): string => {
    if (!mime) return 'FILE'
    const parts = mime.split('/')
    return (parts[1] ?? parts[0]).toUpperCase()
}

onMounted(() => loadShare())

definePageMeta({ layout: false })
</script>
