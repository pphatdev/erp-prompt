<template>
    <div>
        <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
            {{ label }}
        </label>
        <input :value="modelValue ?? ''" type="text" :placeholder="placeholder" class="form-control font-mono"
            @input="onInput" />
        <p v-if="hint" class="text-xxs text-(--text-muted) mt-1">{{ hint }}</p>
    </div>
</template>

<script setup lang="ts">
defineProps<{
    label: string
    modelValue: string | null | undefined
    placeholder?: string
    hint?: string
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void
}>()

const onInput = (e: Event) => {
    emit('update:modelValue', (e.target as HTMLInputElement).value)
}
</script>
