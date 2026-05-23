<template>
    <!-- design.md §9.2 — orbital task completion -->
    <div class="relative rounded-full border border-(--color-primary)/15 flex items-center justify-center"
        :style="{ width: `${size}px`, height: `${size}px` }">
        <span v-for="i in count" :key="i" class="absolute text-(--color-primary) animate-orbit" :style="orbitStyle(i)">
            <i class="ti ti-bolt text-xs" />
        </span>
        <span class="text-xs font-bold text-gradient-primary font-mono">{{ percent }}%</span>
    </div>
</template>

<script setup lang="ts">
interface Props {
    size?: number
    count?: number
    duration?: string
    percent?: number
}
const props = withDefaults(defineProps<Props>(), {
    size: 56,
    count: 2,
    duration: '8s',
    percent: 78
})

const orbitStyle = (i: number) => ({
    '--angle': `${(360 / props.count) * (i - 1)}`,
    '--radius': `${(props.size / 2) - 6}`,
    '--duration': props.duration
} as Record<string, string>)
</script>
