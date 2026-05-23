<template>
    <nav v-if="totalPages > 1 || showMeta"
        class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 text-xs" aria-label="Pagination">
        <div class="text-(--text-muted) font-mono">
            <span v-if="total > 0">
                Showing <span class="text-(--text-heading) font-semibold">{{ rangeStart }}</span>–<span
                    class="text-(--text-heading) font-semibold">{{ rangeEnd }}</span>
                of <span class="text-(--text-heading) font-semibold">{{ total }}</span>
            </span>
            <span v-else>No results</span>
        </div>

        <div class="flex items-center gap-1">
            <button type="button" class="page-btn" :disabled="page <= 1" @click="$emit('update:page', 1)"
                title="First page">
                <i class="ti ti-chevrons-left" />
            </button>
            <button type="button" class="page-btn" :disabled="page <= 1" @click="$emit('update:page', page - 1)"
                title="Previous">
                <i class="ti ti-chevron-left" />
            </button>

            <button v-for="p in pageWindow" :key="p" type="button" class="page-btn min-w-[28px]"
                :class="p === page ? 'page-btn-active' : ''" @click="$emit('update:page', p)">
                {{ p }}
            </button>

            <button type="button" class="page-btn" :disabled="page >= totalPages"
                @click="$emit('update:page', page + 1)" title="Next">
                <i class="ti ti-chevron-right" />
            </button>
            <button type="button" class="page-btn" :disabled="page >= totalPages"
                @click="$emit('update:page', totalPages)" title="Last page">
                <i class="ti ti-chevrons-right" />
            </button>

            <select v-if="limitOptions.length" :value="limit" class="form-control form-control-sm ml-2 w-[88px]"
                @change="$emit('update:limit', Number(($event.target as HTMLSelectElement).value))">
                <option v-for="opt in limitOptions" :key="opt" :value="opt">{{ opt }} / page</option>
            </select>
        </div>
    </nav>
</template>

<script setup lang="ts">
import { computed } from 'vue'

const props = withDefaults(defineProps<{
    page: number
    limit: number
    total: number
    totalPages: number
    limitOptions?: number[]
    showMeta?: boolean
}>(), {
    limitOptions: () => [10, 15, 25, 50, 100],
    showMeta: true
})

defineEmits<{
    (event: 'update:page', value: number): void
    (event: 'update:limit', value: number): void
}>()

const rangeStart = computed(() => props.total === 0 ? 0 : (props.page - 1) * props.limit + 1)
const rangeEnd = computed(() => Math.min(props.page * props.limit, props.total))

const pageWindow = computed(() => {
    const span = 5
    const half = Math.floor(span / 2)
    let start = Math.max(1, props.page - half)
    let end = Math.min(props.totalPages, start + span - 1)
    if (end - start + 1 < span) start = Math.max(1, end - span + 1)
    const out: number[] = []
    for (let i = start; i <= end; i++) out.push(i)
    return out
})
</script>

<style scoped>
    .page-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        height: 28px;
        padding: 0 0.5rem;
        border-radius: 0.375rem;
        font-size: 0.75rem;
        font-weight: 600;
        color: var(--text-body);
        background: transparent;
        transition: background 0.15s ease, color 0.15s ease;
    }

    .page-btn:hover:not(:disabled) {
        background: var(--bg-muted);
        color: var(--text-heading);
    }

    .page-btn:disabled {
        opacity: 0.4;
        cursor: not-allowed;
    }

    .page-btn-active {
        background: var(--color-primary-subtle);
        color: var(--color-primary) !important;
    }
</style>
