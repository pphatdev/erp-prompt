<template>
    <!-- design.md §9.2 — premium meteor-background card -->
    <div
        class="relative overflow-hidden p-5 rounded-xl border border-(--border-color) bg-(--bg-card) shadow-(--shadow-sm)">
        <span v-for="(meteor, idx) in count" :key="idx"
            class="absolute h-px w-[120px] bg-linear-to-r animate-meteor pointer-events-none" :class="streakClass"
            :style="streakStyle(idx)" />
        <slot />
    </div>
</template>

<script setup lang="ts">
interface Props {
    count?: number
    variant?: 'primary' | 'success' | 'warning' | 'danger' | 'info'
}
const props = withDefaults(defineProps<Props>(), { count: 3, variant: 'primary' })

const streakMap = {
    primary: 'from-(--color-primary) to-transparent',
    success: 'from-(--color-success) to-transparent',
    warning: 'from-(--color-warning) to-transparent',
    danger: 'from-(--color-danger) to-transparent',
    info: 'from-(--color-info) to-transparent'
} as const

const streakClass = computed(() => streakMap[props.variant])

const streakStyle = (idx: number) => {
    const tops = ['0%', '30%', '60%']
    const rights = ['0%', '20%', '60%']
    return {
        top: tops[idx % tops.length],
        right: rights[idx % rights.length],
        '--angle': '-45deg',
        '--duration': `${3 + idx * 1.5}s`,
        animationDelay: `${idx * 0.6}s`
    } as Record<string, string>
}
</script>
