<template>
    <div class="flex items-start justify-between gap-4">
        <div class="flex-1 min-w-0">
            <p class="text-xxs font-bold text-(--text-muted) uppercase tracking-wide">{{ label }}</p>
            <p v-if="hint" class="text-xxs text-(--text-muted) mt-1">{{ hint }}</p>
        </div>
        <button type="button" :aria-pressed="active"
            class="relative inline-flex h-5 w-9 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none cursor-pointer mt-1"
            :class="active ? 'bg-(--color-primary)' : 'bg-(--bg-subtle) border border-(--border-color)'"
            @click="toggle">
            <span
                class="pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow transform transition duration-200"
                :class="active ? 'translate-x-4' : 'translate-x-0'" />
        </button>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = defineProps<{
    label: string
    modelValue: boolean | null | undefined
    hint?: string
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', value: boolean): void
}>()

const active = computed(() => props.modelValue === true)

const toggle = () => emit('update:modelValue', !active.value)
</script>
