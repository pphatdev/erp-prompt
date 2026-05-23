<template>
    <div class="glass-card rounded-xl p-5 flex flex-col gap-4 relative overflow-hidden group">
        <!-- Faint accent glow keyed to variant -->
        <div class="absolute -top-6 -right-6 w-24 h-24 rounded-full blur-2xl opacity-50 pointer-events-none"
            :class="glowClass" />

        <header class="flex items-center justify-between relative z-10">
            <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">
                {{ label }}
            </span>
            <div class="w-9 h-9 rounded-full flex items-center justify-center" :class="iconWrapperClass">
                <i :class="['ti', icon, 'text-base']" />
            </div>
        </header>

        <div class="relative z-10">
            <div class="flex items-baseline gap-2 flex-wrap">
                <h3 class="text-2xl font-bold font-mono text-(--text-heading) tracking-tight">{{ value }}</h3>
                <span v-if="delta"
                    class="text-xxs font-bold uppercase tracking-wider px-1.5 py-0.5 rounded inline-flex items-center gap-1"
                    :class="deltaClass">
                    <i class="ti text-[10px]"
                        :class="deltaDirection === 'up' ? 'ti-trending-up' : 'ti-trending-down'" />
                    {{ delta }}
                </span>
            </div>
            <p v-if="sub" class="text-xs text-(--text-muted) mt-2 flex items-center justify-between gap-3">
                <span>{{ sub }}</span>
                <span v-if="subValue" class="font-mono font-semibold text-(--text-body)">{{ subValue }}</span>
            </p>
        </div>
    </div>
</template>

<script setup lang="ts">
interface Props {
    label: string
    value: string | number
    sub?: string
    subValue?: string | number
    delta?: string
    deltaDirection?: 'up' | 'down'
    icon: string                    // e.g. "ti-package"
    variant?: 'primary' | 'secondary' | 'success' | 'warning' | 'danger' | 'info'
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'primary',
    deltaDirection: 'up'
})

const glowMap = {
    primary: 'bg-(--color-primary-subtle)',
    secondary: 'bg-(--color-secondary-subtle)',
    success: 'bg-(--color-success-subtle)',
    warning: 'bg-(--color-warning-subtle)',
    danger: 'bg-(--color-danger-subtle)',
    info: 'bg-(--color-info-subtle)'
} as const

const iconMap = {
    primary: 'badge-soft-primary',
    secondary: 'badge-soft-secondary',
    success: 'badge-soft-success',
    warning: 'badge-soft-warning',
    danger: 'badge-soft-danger',
    info: 'badge-soft-info'
} as const

const glowClass = computed(() => glowMap[props.variant])
const iconWrapperClass = computed(() => iconMap[props.variant])
const deltaClass = computed(() =>
    props.deltaDirection === 'down' ? 'badge-soft-danger' : 'badge-soft-success'
)
</script>
