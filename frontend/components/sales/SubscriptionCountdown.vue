<template>
    <span v-if="endDate" class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-xxs font-mono"
        :class="cls">
        <i class="ti ti-clock" />
        <span>{{ label }}</span>
    </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{ endDate: string | null }>()

const daysRemaining = computed<number | null>(() => {
    if (!props.endDate) return null
    const end = new Date(props.endDate)
    if (Number.isNaN(end.getTime())) return null
    const now = new Date()
    const diff = end.getTime() - now.getTime()
    return Math.ceil(diff / (1000 * 60 * 60 * 24))
})

const cls = computed(() => {
    const d = daysRemaining.value
    if (d === null) return 'bg-(--bg-muted) text-(--text-muted)'
    if (d <= 0)    return 'bg-(--color-danger-subtle) text-(--color-danger)'
    if (d < 7)    return 'bg-(--color-danger-subtle) text-(--color-danger)'
    if (d <= 30)  return 'bg-(--color-warning-subtle) text-(--color-warning)'
    return 'bg-(--color-success-subtle) text-(--color-success)'
})

const label = computed(() => {
    const d = daysRemaining.value
    if (d === null) return 'No end date'
    if (d < 0)  return `Expired ${Math.abs(d)}d ago`
    if (d === 0) return 'Ends today'
    if (d === 1) return '1 day left'
    return `${d} days left`
})
</script>
