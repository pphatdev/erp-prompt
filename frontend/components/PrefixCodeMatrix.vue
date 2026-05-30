<template>
    <div class="space-y-6">
        <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <div>
                <h1 class="text-xl font-semibold">{{ heading }}</h1>
                <p class="text-xs text-(--text-muted) mt-1">
                    Customize the leading text on auto-generated codes for this system. Include any
                    separator (e.g. <span class="font-mono">TT-</span>). Changes only affect new
                    records; existing codes are not rewritten.
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

        <div v-if="alert.msg" class="px-4 py-3 rounded-lg flex items-center justify-between text-xs font-semibold"
            :class="alert.type === 'success' ? 'badge-soft-success' : 'badge-soft-danger'">
            <span class="flex items-center gap-2">
                <i :class="['ti', alert.type === 'success' ? 'ti-check' : 'ti-alert-triangle']" />
                {{ alert.msg }}
            </span>
            <button class="text-current" @click="alert.msg = ''"><i class="ti ti-x" /></button>
        </div>

        <div v-if="loading" class="py-16 flex justify-center">
            <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
        </div>

        <section v-else class="glass-card rounded-2xl p-6 space-y-8">
            <div v-for="module in modules" :key="module.id" class="space-y-3">
                <h5 class="flex items-center gap-2 text-xxs font-bold uppercase tracking-widest text-(--text-muted)">
                    <span class="w-1.5 h-3.5 rounded-sm bg-(--color-primary)" />
                    {{ module.label }} module
                </h5>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div v-for="entry in module.entries" :key="entry.key"
                        class="p-4 rounded-xl bg-(--bg-muted)/60 border border-(--border-color) space-y-2">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <div class="text-xs font-semibold text-(--text-heading)">{{ entry.label }}</div>
                                <div class="text-xxs font-mono text-(--text-muted) truncate">{{ entry.key }}</div>
                            </div>
                            <i :class="['ti', entry.icon, 'text-(--color-primary) text-base shrink-0']" />
                        </div>
                        <input v-model="draft[entry.key]" type="text" maxlength="16"
                            :placeholder="entry.placeholder" class="form-control font-mono text-xs" />
                        <p class="text-xxs text-(--text-muted)">
                            {{ entry.note }}
                            <span class="font-mono block">{{ buildExample(entry) }}</span>
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import type { PrefixEntry, PrefixModule } from '~/composables/usePrefixCodes'

defineProps<{
    heading: string
    modules: PrefixModule[]
    draft: Record<string, unknown>
    loading: boolean
    saving: boolean
    dirty: boolean
    alert: { msg: string; type: 'success' | 'danger' }
    save: () => Promise<void>
    reset: () => void
    buildExample: (entry: PrefixEntry) => string
}>()
</script>
