<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Page header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Branding Configuration</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        Manage primary accent color, logo, and theme preferences for
                        <span class="text-(--color-primary) font-semibold">{{ tenantStore.activeName }}</span>.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button class="btn text-xs"
                        :class="dirty ? 'text-(--text-body) border border-(--border-color) hover:bg-(--bg-muted)' : 'text-(--text-muted) cursor-not-allowed'"
                        :disabled="!dirty || saving" @click="reset">
                        <i class="ti ti-restore" /> Revert
                    </button>
                    <button class="btn btn-primary text-xs" :disabled="!dirty || saving" @click="save">
                        <i :class="['ti', saving ? 'ti-loader-2 animate-spin' : 'ti-device-floppy']" />
                        {{ saving ? 'Saving...' : 'Save changes' }}
                    </button>
                </div>
            </header>

            <!-- Alert -->
            <div v-if="alert.msg" class="px-4 py-3 rounded-lg flex items-center justify-between text-xs font-semibold"
                :class="alert.type === 'success' ? 'badge-soft-success' : 'badge-soft-danger'">
                <span class="flex items-center gap-2">
                    <i :class="['ti', alert.type === 'success' ? 'ti-check' : 'ti-alert-triangle']" />
                    {{ alert.msg }}
                </span>
                <button class="text-current" @click="alert.msg = ''"><i class="ti ti-x" /></button>
            </div>

            <div class="flex-1 min-w-0">
                <!-- Loading -->
                <div v-if="loading" class="py-16 flex justify-center">
                    <span
                        class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                </div>

                <!-- Branding -->
                <section v-else class="glass-card rounded-2xl p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-2">
                                Primary accent color
                            </label>
                            <div class="flex flex-wrap gap-2">
                                <button v-for="swatch in accentSwatches" :key="swatch.rgb" type="button"
                                    class="w-9 h-9 rounded-full border-2 transition-transform hover:scale-110"
                                    :class="draft['branding.primary_color'] === swatch.rgb
                                        ? 'border-(--text-heading)' : 'border-transparent'"
                                    :style="{ background: `rgb(${swatch.rgb})` }" :title="swatch.label"
                                    @click="setBranding('primary_color', swatch.rgb)" />
                            </div>
                            <p class="text-xxs text-(--text-muted) mt-2 font-mono">
                                {{ draft['branding.primary_color'] || '—' }}
                            </p>
                        </div>

                        <div>
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-2">
                                Default theme mode
                            </label>
                            <div class="grid grid-cols-3 gap-2">
                                <button v-for="mode in ['light', 'dark', 'system']" :key="mode" type="button"
                                    class="rounded-lg border px-2 py-2.5 text-xxs font-semibold flex flex-col items-center gap-1 transition-all"
                                    :class="draft['branding.theme_mode'] === mode
                                        ? 'border-(--color-primary) bg-(--color-primary-subtle) text-(--color-primary)'
                                        : 'border-(--border-color) text-(--text-body) hover:border-(--color-primary)/40'"
                                    @click="draft['branding.theme_mode'] = mode">
                                    {{ mode }}
                                </button>
                            </div>
                        </div>

                        <div class="md:col-span-2">
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
                                Logo URL
                            </label>
                            <input v-model="draft['branding.logo_url']" type="url"
                                placeholder="https://cdn.example.com/logo.svg" class="form-control" />
                            <p class="text-xxs text-(--text-muted) mt-1">Shown on the login screen and sidebar
                                header.</p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useSettings, type SettingRow } from '~/composables/useSettings'
import { useTenantStore } from '~/stores/tenant'

const tenantStore = useTenantStore()
const settingsApi = useSettings()

definePageMeta({
    breadcrumb: 'Branding Configuration'
})

const accentSwatches = [
    { label: 'Electric Indigo', rgb: '59 130 246' },
    { label: 'Sky', rgb: '14 165 233' },
    { label: 'Emerald', rgb: '16 185 129' },
    { label: 'Amber', rgb: '245 158 11' },
    { label: 'Crimson', rgb: '239 68 68' },
    { label: 'Violet', rgb: '139 92 246' }
]

const loading = ref(true)
const saving = ref(false)
const alert = reactive({ msg: '', type: 'success' as 'success' | 'danger' })

const pristine = ref<Record<string, unknown>>({})
const draft = reactive<Record<string, unknown>>({})

const valuesEqual = (a: unknown, b: unknown) => JSON.stringify(a) === JSON.stringify(b)

const dirty = computed(() =>
    Object.keys(draft).some(k => !valuesEqual(draft[k], pristine.value[k]))
)

const setBranding = (suffix: string, value: unknown) => {
    draft[`branding.${suffix}`] = value
}

const hydrate = (rows: SettingRow[]) => {
    const map = settingsApi.toMap(rows)
    // Only hydrate branding keys to avoid clutter
    pristine.value = {}
    for (const k of Object.keys(draft)) delete draft[k]
    
    Object.keys(map).forEach(k => {
        if (k.startsWith('branding.')) {
            pristine.value[k] = map[k]
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
        alert.msg = err?.data?.message || 'Failed to load settings'
        alert.type = 'danger'
    } finally {
        loading.value = false
    }
}

const save = async () => {
    if (!dirty.value) return
    saving.value = true
    try {
        const changed = Object.keys(draft)
            .filter(k => !valuesEqual(draft[k], pristine.value[k]))
            .map(k => ({ key: k, value: draft[k] }))

        if (changed.length) {
            const { data } = await settingsApi.update(changed)
            hydrate(data)
            alert.msg = 'Branding configuration saved successfully'
            alert.type = 'success'
        }
    } catch (err: any) {
        alert.msg = err?.data?.message || 'Failed to save changes'
        alert.type = 'danger'
    } finally {
        saving.value = false
    }
}

const reset = () => {
    for (const k of Object.keys(draft)) delete draft[k]
    Object.assign(draft, JSON.parse(JSON.stringify(pristine.value)))
    alert.msg = ''
}

onMounted(() => {
    load()
})
</script>
