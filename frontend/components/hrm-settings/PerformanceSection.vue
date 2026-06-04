<template>
    <div class="space-y-5">
        <header>
            <h2 class="text-xl font-semibold text-(--text-heading) leading-tight">Performance Appraisals</h2>
            <p class="text-xs text-(--text-muted) mt-1">
                Weights applied to self-evaluation and manager-evaluation when computing the final appraisal score.
            </p>
        </header>

        <GuidancePanel>
            <template #title>Notes</template>
            <p>
                Self + manager weights must sum to <strong>100%</strong>. Misconfigured pairs are
                ignored at runtime and PerformanceService falls back to the 20 / 80 default with
                a logged warning - the appraisal cycle never blocks because of bad weights.
            </p>
        </GuidancePanel>

        <section class="glass-card rounded-2xl p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <NumberField label="Self-evaluation weight (%)" min="0" max="100"
                    hint="Contribution of the employee self-review to the final score."
                    v-model="draft['hrm.appraisal.self_evaluation_weight']"
                    @update:model-value="onSelfWeightChanged" />
                <NumberField label="Manager-evaluation weight (%)" min="0" max="100"
                    hint="Contribution of the direct manager review to the final score."
                    v-model="draft['hrm.appraisal.manager_evaluation_weight']"
                    @update:model-value="onManagerWeightChanged" />
            </div>

            <div class="flex items-center gap-3 text-xs px-3 py-2.5 rounded-lg"
                :class="weightSum === 100 ? 'badge-soft-success' : 'badge-soft-warning'">
                <i :class="['ti', weightSum === 100 ? 'ti-check' : 'ti-alert-triangle']" />
                <span>
                    Total weight: <strong class="font-mono">{{ weightSum }}%</strong>
                    <span v-if="weightSum !== 100">
                        - {{ weightSum > 100 ? 'over' : 'under' }} 100%, will fall back to defaults.
                    </span>
                </span>
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import GuidancePanel from '~/components/hrm-settings/GuidancePanel.vue'
import NumberField from '~/components/hrm-settings/NumberField.vue'

const props = defineProps<{
    draft: Record<string, unknown>
}>()

const weightSum = computed(() => {
    const self = Number(props.draft['hrm.appraisal.self_evaluation_weight'] ?? 0)
    const mgr = Number(props.draft['hrm.appraisal.manager_evaluation_weight'] ?? 0)
    return Math.round((isFinite(self) ? self : 0) + (isFinite(mgr) ? mgr : 0))
})

const onSelfWeightChanged = (v: number | string | null) => {
    const n = Number(v)
    if (!Number.isFinite(n) || n < 0 || n > 100) return
    props.draft['hrm.appraisal.manager_evaluation_weight'] = 100 - Math.round(n)
}
const onManagerWeightChanged = (v: number | string | null) => {
    const n = Number(v)
    if (!Number.isFinite(n) || n < 0 || n > 100) return
    props.draft['hrm.appraisal.self_evaluation_weight'] = 100 - Math.round(n)
}
</script>
