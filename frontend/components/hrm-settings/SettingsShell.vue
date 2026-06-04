<template>
    <div class="space-y-6">
        <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
            <div>
                <h1 class="text-xl font-semibold">{{ heading }}</h1>
                <p class="text-xs text-(--text-muted) mt-1">
                    {{ subheading }}
                    <span class="text-(--color-primary) font-semibold">{{ tenantStore.activeName }}</span>.
                </p>
            </div>
            <div class="flex items-center gap-2">
                <button class="btn text-xs"
                    :class="dirty ? 'text-(--text-body) border border-(--border-color) hover:bg-(--bg-muted)' : 'text-(--text-muted) cursor-not-allowed'"
                    :disabled="!dirty || saving" @click="$emit('reset')">
                    <i class="ti ti-restore" /> Revert
                </button>
                <button class="btn btn-primary text-xs"
                    :disabled="!dirty || saving || !canSave" @click="$emit('save')">
                    <i :class="['ti', saving ? 'ti-loader-2 animate-spin' : 'ti-device-floppy']" />
                    {{ saving ? 'Saving...' : 'Save changes' }}
                </button>
            </div>
        </header>

        <div v-if="alert.msg"
            class="px-4 py-3 rounded-lg flex items-center justify-between text-xs font-semibold"
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
        <slot v-else />
    </div>
</template>

<script setup lang="ts">
import { useTenantStore } from '~/stores/tenant'

defineProps<{
    heading: string
    subheading: string
    loading: boolean
    saving: boolean
    dirty: boolean
    canSave: boolean
    alert: { msg: string; type: 'success' | 'danger' }
}>()

defineEmits<{
    (e: 'save'): void
    (e: 'reset'): void
}>()

const tenantStore = useTenantStore()
</script>
