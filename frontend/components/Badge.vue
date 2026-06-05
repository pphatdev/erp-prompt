<template>
    <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-full text-xxs font-bold uppercase tracking-wider"
        :class="classes">
        <i v-if="dot" class="ti ti-circle-filled text-[6px]" />
        <i v-else-if="icon" class="ti text-[10px]" :class="icon" />
        <slot />
    </span>
</template>

<script setup lang="ts">
interface Props {
    variant?: 'primary' | 'secondary' | 'success' | 'warning' | 'danger' | 'info'
    dot?: boolean
    icon?: string
}
const props = withDefaults(defineProps<Props>(), { variant: 'primary' })

const map = {
    primary: 'badge-soft-primary',
    secondary: 'badge-soft-secondary',
    success: 'badge-soft-success',
    warning: 'badge-soft-warning',
    danger: 'badge-soft-danger',
    info: 'badge-soft-info'
} as const

const classes = computed(() => map[props.variant])
</script>
