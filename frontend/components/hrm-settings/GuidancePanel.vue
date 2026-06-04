<template>
    <div class="glass-card rounded-2xl p-5 border-l-4 border-(--color-primary)">
        <div class="flex items-start gap-3">
            <div class="badge-soft-primary rounded-lg p-2 mt-0.5">
                <i class="ti ti-info-circle text-base" />
            </div>
            <div class="space-y-2 text-xs text-(--text-body) leading-relaxed flex-1 min-w-0">
                <p v-if="$slots.title" class="text-sm font-semibold text-(--text-heading)">
                    <slot name="title" />
                </p>
                <slot />
            </div>
        </div>
    </div>
</template>

<script setup lang="ts"></script>
