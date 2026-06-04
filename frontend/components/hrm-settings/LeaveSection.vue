<template>
    <div class="space-y-5">
        <header>
            <h2 class="text-xl font-semibold text-(--text-heading) leading-tight">Leave &amp; Time Off</h2>
            <p class="text-xs text-(--text-muted) mt-1">
                Accrual cycle, carryover cap, negative-balance policy, and request rules applied to every leave type.
            </p>
        </header>

        <GuidancePanel>
            <template #title>Notes</template>
            <p>
                Annual allowance per leave type (Annual, Sick, Maternity ...) is configured in
                <NuxtLink :to="{ path: '/settings/apps/hrm', query: { tab: 'leave-types' } }"
                    class="text-(--color-primary) font-semibold hover:underline">
                    Leave Types
                </NuxtLink>. The settings below apply to <em>all</em> types.
            </p>
        </GuidancePanel>

        <!-- Standard working days — derived from Work Schedules (read-only). -->
        <section class="glass-card rounded-2xl p-6 space-y-4">
            <header class="flex items-start justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-(--text-heading)">Standard working days</h3>
                    <p class="text-xxs text-(--text-muted) mt-1">
                        Read from the company's global
                        <NuxtLink :to="{ path: '/settings/apps/hrm', query: { tab: 'work-schedules' } }"
                            class="text-(--color-primary) font-semibold hover:underline">
                            Work Schedules
                        </NuxtLink>. LeaveService skips non-working days when computing request duration.
                    </p>
                </div>
                <NuxtLink :to="{ path: '/settings/apps/hrm', query: { tab: 'work-schedules' } }"
                    class="btn btn-ghost text-xxs whitespace-nowrap">
                    <i class="ti ti-edit" /> Edit hours
                </NuxtLink>
            </header>

            <div v-if="scheduleLoading" class="flex items-center gap-2 text-xxs text-(--text-muted)">
                <span class="w-3 h-3 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                Loading global schedule...
            </div>

            <div v-else-if="scheduleError"
                class="px-3 py-2 rounded-lg text-xxs badge-soft-danger flex items-center gap-2">
                <i class="ti ti-alert-triangle" />
                {{ scheduleError }}
            </div>

            <template v-else>
                <div class="flex flex-wrap gap-2">
                    <span v-for="d in weekdays" :key="d.iso" class="day-chip"
                        :class="workingByIso[d.iso] ? 'day-chip-on' : 'day-chip-off'">
                        <span class="font-semibold">{{ d.label }}</span>
                        <span v-if="workingByIso[d.iso]" class="font-mono text-xxs">
                            {{ hoursByIso[d.iso].toFixed(1) }}h
                        </span>
                        <i v-else class="ti ti-moon text-xs" />
                    </span>
                </div>

                <div class="flex items-center justify-between text-xxs text-(--text-muted) pt-1">
                    <span>
                        {{ workingDaysCount }} working
                        {{ workingDaysCount === 1 ? 'day' : 'days' }} per week
                    </span>
                    <span class="font-mono">{{ totalWeeklyHours.toFixed(1) }}h / week</span>
                </div>
            </template>
        </section>

        <section class="glass-card rounded-2xl p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
                        Accrual cycle
                    </label>
                    <select v-model="draft['hrm.leave.accrual_cycle_start']" class="form-control">
                        <option value="calendar_year">Calendar year (Jan 1)</option>
                        <option value="fiscal_year">Fiscal year (Oct 1)</option>
                        <option value="hire_date">Hire-date anniversary</option>
                    </select>
                    <p class="text-xxs text-(--text-muted) mt-1">
                        Resets unused leave to the carryover cap on the cycle boundary.
                    </p>
                </div>
                <NumberField label="Max carryover days" min="0" max="365" step="0.5"
                    hint="Unused days transferred to the next cycle. Excess is forfeited."
                    v-model="draft['hrm.leave.max_carryover_days']" />
                <ToggleField label="Allow negative leave balance"
                    hint="When on, requests can exceed the remaining balance (e.g. emergency leave)."
                    v-model="draft['hrm.leave.allow_negative_balance']" class="md:col-span-2" />
            </div>
        </section>

        <section class="glass-card rounded-2xl p-6 space-y-5">
            <header>
                <h3 class="text-sm font-semibold text-(--text-heading)">Request policy</h3>
                <p class="text-xxs text-(--text-muted) mt-1">
                    Enforced by LeaveService at submission time. Set any value to 0 to disable the check.
                </p>
            </header>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <NumberField label="Minimum notice (days)" min="0" max="365" step="1"
                    hint="Reject requests whose start date is fewer than this many days away. 0 = no minimum."
                    v-model="draft['hrm.leave.min_notice_days']" />
                <NumberField label="Max consecutive days" min="0" max="365" step="1"
                    hint="Cap on a single request's working-day length. 0 = unlimited."
                    v-model="draft['hrm.leave.max_consecutive_days']" />
                <NumberField label="Attachment required from (days)" min="0" max="365" step="1"
                    hint="Requests this long or longer must attach a document (e.g. medical certificate). 0 = never required."
                    v-model="draft['hrm.leave.attachment_required_days']" />
                <NumberField label="Auto-approve up to (days)" min="0" max="365" step="1"
                    hint="Requests this long or shorter skip the approval queue and post as approved. 0 = always require approval."
                    v-model="draft['hrm.leave.auto_approve_days']" />
            </div>
        </section>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import GuidancePanel from '~/components/hrm-settings/GuidancePanel.vue'
import NumberField from '~/components/hrm-settings/NumberField.vue'
import ToggleField from '~/components/hrm-settings/ToggleField.vue'
import { useWorkSchedules, type WorkScheduleRow } from '~/composables/useWorkSchedules'

defineProps<{
    draft: Record<string, unknown>
}>()

const weekdays = [
    { iso: 1, label: 'Mon' },
    { iso: 2, label: 'Tue' },
    { iso: 3, label: 'Wed' },
    { iso: 4, label: 'Thu' },
    { iso: 5, label: 'Fri' },
    { iso: 6, label: 'Sat' },
    { iso: 7, label: 'Sun' },
]

const schedules = useWorkSchedules()
const scheduleLoading = ref(false)
const scheduleError = ref('')
const scheduleRows = ref<WorkScheduleRow[]>([])

const minutesBetween = (a: string, b: string): number => {
    const [ah, am] = a.split(':').map(Number)
    const [bh, bm] = b.split(':').map(Number)
    if (![ah, am, bh, bm].every(n => Number.isFinite(n))) return 0
    const m = (bh * 60 + bm) - (ah * 60 + am)
    return m > 0 ? m : 0
}

const hoursForRow = (row: WorkScheduleRow): number => {
    if (!row.isWorkDay) return 0
    const m = (row.intervals || []).reduce((sum, iv) => sum + minutesBetween(iv.start, iv.end), 0)
    return m / 60
}

const workingByIso = computed<Record<number, boolean>>(() => {
    const map: Record<number, boolean> = {}
    for (const row of scheduleRows.value) {
        map[row.dayOfWeek] = !!row.isWorkDay
    }
    return map
})

const hoursByIso = computed<Record<number, number>>(() => {
    const map: Record<number, number> = {}
    for (const row of scheduleRows.value) {
        map[row.dayOfWeek] = hoursForRow(row)
    }
    return map
})

const workingDaysCount = computed(() =>
    scheduleRows.value.filter(r => r.isWorkDay).length
)

const totalWeeklyHours = computed(() =>
    scheduleRows.value.reduce((sum, r) => sum + hoursForRow(r), 0)
)

const loadGlobalSchedule = async () => {
    scheduleLoading.value = true
    scheduleError.value = ''
    try {
        const res = await schedules.snapshot('global', null)
        scheduleRows.value = (res.data || []).map((row: any) => ({
            id: row.id ?? null,
            dayOfWeek: row.dayOfWeek,
            isWorkDay: row.isWorkDay,
            intervals: Array.isArray(row.intervals)
                ? row.intervals.map((iv: any) => ({ start: iv.start, end: iv.end }))
                : [],
        }))
    } catch (err: any) {
        scheduleError.value = err?.data?.message || 'Failed to load global work schedule.'
    } finally {
        scheduleLoading.value = false
    }
}

onMounted(loadGlobalSchedule)
</script>

<style scoped>
.day-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    padding: 0.375rem 0.625rem;
    border-radius: 0.5rem;
    font-size: 0.6875rem;
    line-height: 1;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    transition: background 0.15s ease, border-color 0.15s ease;
}

.day-chip-on {
    background: rgb(var(--color-primary-rgb) / 0.08);
    border-color: rgb(var(--color-primary-rgb) / 0.35);
    color: var(--color-primary);
}

.day-chip-off {
    color: var(--text-muted);
    opacity: 0.7;
}
</style>
