<template>
    <div class="space-y-5">
        <header>
            <h2 class="text-xl font-semibold text-(--text-heading) leading-tight">Recruitment &amp; Hiring</h2>
            <p class="text-xs text-(--text-muted) mt-1">
                Probation period, conversion revert window, and public careers visibility for this tenant.
            </p>
        </header>

        <GuidancePanel>
            <template #title>Notes</template>
            <p>
                The recruitment pipeline columns (Applied, Screening, Interview, Hired ...) are
                <strong>not</strong> managed here. They live in the
                <NuxtLink to="/settings/workflow-statuses"
                    class="text-(--color-primary) font-semibold hover:underline">
                    Workflow Statuses
                </NuxtLink>
                registry under module key <code class="font-mono">hrm.application</code>.
            </p>
            <p>
                Candidate codes and employee IDs are configured under
                <NuxtLink to="/settings/apps/hrm/prefix-code"
                    class="text-(--color-primary) font-semibold hover:underline">
                    HRM Prefix Code
                </NuxtLink>.
            </p>
        </GuidancePanel>

        <section class="glass-card rounded-2xl p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <NumberField label="Probation period (months)" min="0" max="120"
                    hint="Applied during candidate-to-employee conversion when not overridden."
                    v-model="draft['hrm.recruitment.probation_period_default']" />
                <NumberField label="Revert window (days)" min="0" max="365"
                    hint="Bounded window in which a converted employee can be reverted to candidate status."
                    v-model="draft['hrm.recruitment.revert_window_days']" />
                <ToggleField label="Expose public careers portal"
                    hint="Global switch for the /public/job-vacancies surface."
                    v-model="draft['hrm.recruitment.enable_public_careers']" />
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import GuidancePanel from '~/components/hrm-settings/GuidancePanel.vue'
import NumberField from '~/components/hrm-settings/NumberField.vue'
import ToggleField from '~/components/hrm-settings/ToggleField.vue'

defineProps<{
    draft: Record<string, unknown>
}>()
</script>
