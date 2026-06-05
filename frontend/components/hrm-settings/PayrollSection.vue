<template>
    <div class="space-y-5">
        <header>
            <h2 class="text-xl font-semibold text-(--text-heading) leading-tight">Payroll &amp; FMS Posting</h2>
            <p class="text-xs text-(--text-muted) mt-1">
                Standard work hours, default payday, FMS posting toggle, and chart-of-accounts mapping.
            </p>
        </header>

        <GuidancePanel>
            <template #title>Notes</template>
            <p>
                Account codes below must exist in the
                <NuxtLink to="/accounting/accounts" class="text-(--color-primary) font-semibold hover:underline">
                    Chart of Accounts
                </NuxtLink>
                before the FMS posting toggle is turned on, otherwise
                <code class="font-mono">closePeriod()</code> returns 422.
            </p>
        </GuidancePanel>

        <section class="glass-card rounded-2xl p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <NumberField label="Standard monthly work hours" min="1" max="744" hint="Hourly rate = base salary / standard work hours." v-model="draft['hrm.payroll.monthly_work_hours_standard']" />
                <NumberField label="Default payday (day of month)" min="1" max="31" hint="Calendar day for auto-generating draft periods." v-model="draft['hrm.payroll.default_payday']" />
                <ToggleField label="Post payroll close to FMS ledger" hint="When on, closePeriod() publishes a balanced accrual journal automatically." v-model="draft['hrm.payroll.fms_posting_enabled']" class="md:col-span-2" />
            </div>

            <div class="border-t border-(--border-color) pt-5 space-y-4">
                <h3 class="text-sm font-semibold text-(--text-heading)">
                    FMS account code mapping
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <TextField label="Wages expense" placeholder="EXP-WAGES" v-model="draft['hrm.payroll.account_wages_expense']" />
                    <TextField label="Tax payable" placeholder="LIA-TAX" v-model="draft['hrm.payroll.account_tax_payable']" />
                    <TextField label="Social security (NSSF) payable" placeholder="LIA-NSSF" v-model="draft['hrm.payroll.account_social_security_payable']" />
                    <TextField label="Net wages payable" placeholder="LIA-WAGES" v-model="draft['hrm.payroll.account_wages_payable']" />
                </div>
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import GuidancePanel from '~/components/hrm-settings/GuidancePanel.vue'
import NumberField from '~/components/hrm-settings/NumberField.vue'
import TextField from '~/components/hrm-settings/TextField.vue'
import ToggleField from '~/components/hrm-settings/ToggleField.vue'

defineProps<{
    draft: Record<string, any>
}>()
</script>
