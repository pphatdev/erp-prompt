<template>
    <div>
        <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
            {{ label }}
        </label>
        <input :value="modelValue" type="number" :min="min" :max="max" :step="step" class="form-control font-mono"
            @input="onInput" />
        <p v-if="hint" class="text-xxs text-(--text-muted) mt-1">{{ hint }}</p>
    </div>
</template>

<script setup lang="ts">
type NumberLike = number | string | null | undefined

const props = defineProps<{
    label: string
    modelValue: NumberLike
    min?: NumberLike
    max?: NumberLike
    step?: NumberLike
    hint?: string
}>()

const emit = defineEmits<{
    (e: 'update:modelValue', value: number | null): void
}>()

const onInput = (e: Event) => {
    const target = e.target as HTMLInputElement
    if (target.value === '') {
        emit('update:modelValue', null)
        return
    }
    const n = Number(target.value)
    emit('update:modelValue', Number.isFinite(n) ? n : null)
}
</script>
