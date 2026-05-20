<template>
  <!-- design.md §9.2 — overdue / high-priority indicator -->
  <div class="relative w-4 h-4 flex items-center justify-center">
    <span
      class="absolute w-full h-full rounded-full animate-ripple"
      :class="ringClass"
      :style="{ '--duration': duration, '--i': 1 } as Record<string, string>"
    />
    <span class="absolute w-2 h-2 rounded-full" :class="dotClass" />
  </div>
</template>

<script setup lang="ts">
interface Props {
  variant?: 'danger' | 'warning' | 'success' | 'primary' | 'info'
  duration?: string
}
const props = withDefaults(defineProps<Props>(), { variant: 'danger', duration: '1.5s' })

const ringMap = {
  danger:  'bg-(--color-danger)/20',
  warning: 'bg-(--color-warning)/20',
  success: 'bg-(--color-success)/20',
  primary: 'bg-(--color-primary)/20',
  info:    'bg-(--color-info)/20'
} as const

const dotMap = {
  danger:  'bg-(--color-danger)',
  warning: 'bg-(--color-warning)',
  success: 'bg-(--color-success)',
  primary: 'bg-(--color-primary)',
  info:    'bg-(--color-info)'
} as const

const ringClass = computed(() => ringMap[props.variant])
const dotClass = computed(() => dotMap[props.variant])
</script>
