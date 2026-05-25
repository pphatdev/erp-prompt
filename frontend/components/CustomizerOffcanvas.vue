<template>
    <!-- design.md §6 — Advanced Customizer Canvas (Offcanvas Menu) -->
    <Teleport to="body">
        <transition name="fade">
            <div v-if="modelValue" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-40"
                @click="$emit('update:modelValue', false)" />
        </transition>

        <transition name="slide">
            <aside v-if="modelValue"
                class="fixed top-0 right-0 h-full w-[340px] bg-(--bg-card) border-l border-(--border-color) z-50 shadow-(--shadow-lg) flex flex-col">
                <header class="h-14 px-5 border-b border-(--border-color) flex items-center justify-between">
                    <div>
                        <h4 class="text-(--text-heading) font-semibold">Customizer</h4>
                        <p class="text-xxs text-(--text-muted) font-mono uppercase tracking-widest">Realtime theme
                            tuning</p>
                    </div>
                    <button class="w-8 h-8 rounded-full hover:bg-(--bg-muted) text-(--text-body)"
                        @click="$emit('update:modelValue', false)">
                        <i class="ti ti-x" />
                    </button>
                </header>

                <div class="flex-1 overflow-y-auto custom-scrollbar p-5 space-y-7">
                    <!-- §6.1 — Skin Selectors -->
                    <section>
                        <h5 class="text-xxs font-bold uppercase tracking-widest text-(--text-muted) mb-3">Skin</h5>
                        <div class="grid grid-cols-3 gap-2">
                            <button v-for="skin in skins" :key="skin.key" type="button"
                                class="rounded-lg border p-2 text-xxs font-semibold transition-all" :class="activeSkin === skin.key
                                    ? 'border-(--color-primary) bg-(--color-primary-subtle) text-(--color-primary)'
                                    : 'border-(--border-color) text-(--text-body) hover:border-(--color-primary)/40'"
                                @click="activeSkin = skin.key">
                                <span class="block h-6 rounded mb-1.5" :style="{ background: skin.swatch }" />
                                {{ skin.label }}
                            </button>
                        </div>
                    </section>

                    <!-- §6.2 — Color Scheme -->
                    <section>
                        <h5 class="text-xxs font-bold uppercase tracking-widest text-(--text-muted) mb-3">Color scheme
                        </h5>
                        <div class="grid grid-cols-3 gap-2">
                            <button v-for="mode in modes" :key="mode.key" type="button"
                                class="rounded-lg border px-2 py-2.5 text-xxs font-semibold flex flex-col items-center gap-1 transition-all"
                                :class="themeMode === mode.key
                                    ? 'border-(--color-primary) bg-(--color-primary-subtle) text-(--color-primary)'
                                    : 'border-(--border-color) text-(--text-body) hover:border-(--color-primary)/40'"
                                @click="setMode(mode.key)">
                                <i :class="['ti', mode.icon, 'text-base']" />
                                {{ mode.label }}
                            </button>
                        </div>
                    </section>

                    <!-- §6.3 — Accent tuning -->
                    <section>
                        <h5 class="text-xxs font-bold uppercase tracking-widest text-(--text-muted) mb-3">Primary accent
                        </h5>
                        <div class="flex flex-wrap gap-2">
                            <button v-for="accent in accents" :key="accent.label" type="button"
                                class="w-8 h-8 rounded-full shadow-(--shadow-sm) border-2 transition-transform hover:scale-110"
                                :class="activeAccent === accent.rgb ? 'border-(--text-heading)' : 'border-transparent'"
                                :style="{ background: `rgb(${accent.rgb})` }" :title="accent.label"
                                @click="setAccent(accent.rgb)" />
                        </div>
                    </section>
                </div>

                <footer class="p-4 border-t border-(--border-color) flex gap-2">
                    <button class="btn btn-ghost flex-1" @click="reset">
                        <i class="ti ti-restore" /> Reset
                    </button>
                    <button class="btn btn-primary flex-1" @click="$emit('update:modelValue', false)">
                        <i class="ti ti-check" /> Done
                    </button>
                </footer>
            </aside>
        </transition>
    </Teleport>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue'
import { useSettings } from '~/composables/useSettings'
import { useAuthStore } from '~/stores/auth'

defineProps<{ modelValue: boolean }>()
defineEmits<{ (e: 'update:modelValue', v: boolean): void }>()

const settingsApi = useSettings()
const authStore = useAuthStore()

const skins = [
    { key: 'paces', label: 'Paces', swatch: 'linear-gradient(135deg,#3b82f6,#0ea5e9)' },
    { key: 'prism', label: 'Prism', swatch: 'linear-gradient(135deg,#a855f7,#3b82f6)' },
    { key: 'minimalist', label: 'Minimal', swatch: 'linear-gradient(135deg,#64748b,#94a3b8)' },
    { key: 'vivid', label: 'Vivid', swatch: 'linear-gradient(135deg,#ef4444,#f59e0b)' },
    { key: 'retro', label: 'Retro', swatch: 'linear-gradient(135deg,#10b981,#0ea5e9)' },
    { key: 'neon', label: 'Neon', swatch: 'linear-gradient(135deg,#22d3ee,#a855f7)' }
]

const modes = [
    { key: 'light', label: 'Light', icon: 'ti-sun' },
    { key: 'dark', label: 'Dark', icon: 'ti-moon' },
    { key: 'system', label: 'System', icon: 'ti-device-desktop' }
] as const

const accents = [
    { label: 'Electric Indigo', rgb: '59 130 246' },
    { label: 'Sky', rgb: '14 165 233' },
    { label: 'Emerald', rgb: '16 185 129' },
    { label: 'Amber', rgb: '245 158 11' },
    { label: 'Crimson', rgb: '239 68 68' },
    { label: 'Violet', rgb: '139 92 246' }
]

const activeSkin = ref('paces')
const themeMode = ref<'light' | 'dark' | 'system'>('light')
const activeAccent = ref('59 130 246')

const applyTheme = (mode: 'light' | 'dark' | 'system') => {
    const root = document.documentElement
    root.classList.add('no-transitions')
    const resolved = mode === 'system'
        ? (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light')
        : mode
    if (resolved === 'dark') root.setAttribute('data-bs-theme', 'dark')
    else root.removeAttribute('data-bs-theme')
    void root.offsetHeight
    setTimeout(() => root.classList.remove('no-transitions'), 20)
}

const setMode = (mode: 'light' | 'dark' | 'system') => {
    themeMode.value = mode
    localStorage.setItem('theme', mode)
    applyTheme(mode)
}

const setAccent = (rgb: string) => {
    activeAccent.value = rgb
    document.documentElement.style.setProperty('--color-primary-rgb', rgb)
    localStorage.setItem('accent', rgb)
    // Best-effort persistence to the tenant settings backend. Anonymous users
    // (login screen customizer) skip this since the endpoint requires auth.
    if (authStore.isAuthenticated) {
        settingsApi.update([{ key: 'branding.primary_color', value: rgb }])
            .catch(() => { /* keep local override even if remote save fails */ })
    }
}

const reset = () => {
    setMode('light')
    localStorage.removeItem('accent')
    activeAccent.value = '59 130 246'
    document.documentElement.style.removeProperty('--color-primary-rgb')
    activeSkin.value = 'paces'
}

onMounted(() => {
    const savedAccent = localStorage.getItem('accent')
    if (savedAccent) {
        activeAccent.value = savedAccent
        document.documentElement.style.setProperty('--color-primary-rgb', savedAccent)
    }
    const savedMode = (localStorage.getItem('theme') as 'light' | 'dark' | 'system') || 'light'
    themeMode.value = savedMode
})
</script>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}

.slide-enter-active,
.slide-leave-active {
    transition: transform 0.25s ease;
}

.slide-enter-from,
.slide-leave-to {
    transform: translateX(100%);
}
</style>
