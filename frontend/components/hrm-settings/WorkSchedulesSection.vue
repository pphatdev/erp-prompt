<template>
    <div class="space-y-5">
        <!-- Page header (mirrors Shop > All Products) -->
        <header>
            <h2 class="text-xl font-semibold text-(--text-heading) leading-tight">{{ pageTitle }}</h2>
            <p class="text-xs text-(--text-muted) mt-1">{{ pageHint }}</p>
        </header>

        <!-- Sticky toolbar -->
        <section
            class="sticky top-16 z-20 py-2 bg-(--bg-layout)/90 backdrop-blur">
            <div class="flex items-center gap-3 flex-wrap">
                <!-- Segmented control: scope -->
                <div class="segmented" role="group" aria-label="Schedule scope">
                    <button v-for="opt in scopeOptions" :key="opt.value" type="button" class="seg-btn"
                        :class="{ active: scope === opt.value }" :aria-pressed="scope === opt.value"
                        @click="onScopeChange(opt.value)">
                        <i :class="['ti', opt.icon]" /> {{ opt.label }}
                    </button>
                </div>

                <!-- Target picker (visible when scope != global) -->
                <div v-if="scope === 'department'" class="filter-select" :class="{ active: !!selectedTargetId }">
                    <i class="ti ti-building text-(--text-muted) text-sm" />
                    <select v-model="selectedTargetId" aria-label="Select department" @change="loadSnapshot">
                        <option value="">Select department...</option>
                        <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                    </select>
                    <i class="ti ti-chevron-down text-(--text-muted) text-[10px] pointer-events-none" />
                </div>
                <div v-else-if="scope === 'employee'" class="filter-select" :class="{ active: !!selectedTargetId }">
                    <i class="ti ti-user text-(--text-muted) text-sm" />
                    <select v-model="selectedTargetId" aria-label="Select employee" @change="loadSnapshot">
                        <option value="">Select employee...</option>
                        <option v-for="e in employees" :key="e.id" :value="e.id">
                            {{ e.fullName || `${e.firstName} ${e.lastName}` }}{{ e.employeeId ? ` (${e.employeeId})` : '' }}
                        </option>
                    </select>
                    <i class="ti ti-chevron-down text-(--text-muted) text-[10px] pointer-events-none" />
                </div>

                <!-- Action buttons -->
                <div class="ml-auto flex items-center gap-2 flex-wrap">
                    <button v-if="scope !== 'global' && selectedTargetId" type="button" class="btn btn-ghost text-xs"
                        :disabled="clearing" @click="onClearOverrides">
                        <i :class="['ti', clearing ? 'ti-loader-2 animate-spin' : 'ti-trash']" />
                        Clear overrides
                    </button>
                    <button class="btn text-xs"
                        :class="dirty ? 'text-(--text-body) border border-(--border-color) hover:bg-(--bg-muted)' : 'text-(--text-muted) cursor-not-allowed'"
                        :disabled="!dirty || saving || !canEdit" @click="loadSnapshot">
                        <i class="ti ti-restore" /> Revert
                    </button>
                    <button class="btn btn-primary text-xs" :disabled="!dirty || saving || !canEdit" @click="onSave">
                        <i :class="['ti', saving ? 'ti-loader-2 animate-spin' : 'ti-device-floppy']" />
                        {{ saving ? 'Saving...' : 'Save week' }}
                    </button>
                </div>
            </div>

            <!-- Active filter chips -->
            <div v-if="activeChips.length > 0" class="flex items-center gap-2 flex-wrap pt-3">
                <span class="text-xxs uppercase tracking-wider text-(--text-muted) font-semibold">Editing</span>
                <span v-for="c in activeChips" :key="c.key" class="active-filter-chip">
                    <span class="text-(--text-muted)">{{ c.label }}</span>
                    <span class="text-(--text-heading) font-semibold">{{ c.value }}</span>
                </span>
            </div>
        </section>

        <!-- Alert -->
        <div v-if="alert.msg" class="px-4 py-3 rounded-lg flex items-center justify-between text-xs font-semibold"
            :class="alert.type === 'success' ? 'badge-soft-success' : 'badge-soft-danger'">
            <span class="flex items-center gap-2">
                <i :class="['ti', alert.type === 'success' ? 'ti-check' : 'ti-alert-triangle']" />
                {{ alert.msg }}
            </span>
            <button class="text-current" @click="alert.msg = ''"><i class="ti ti-x" /></button>
        </div>

        <!-- Summary line: total weekly hours + source layer (mirrors Shop's resultsSummary) -->
        <div v-if="canEdit && !loading" class="flex items-center justify-between text-xxs text-(--text-muted)">
            <span>{{ summaryLine }}</span>
            <span class="font-mono">{{ totalWeeklyHours.toFixed(1) }}h / week</span>
        </div>

        <!-- Loading skeleton (mirrors Shop's grid skeleton) -->
        <div v-if="loading" class="space-y-2">
            <div v-for="i in 7" :key="i" class="glass-card rounded-2xl py-5 px-4">
                <div class="flex items-center gap-4">
                    <div class="h-3 w-20 bg-(--bg-muted) rounded animate-pulse" />
                    <div class="h-3 w-16 bg-(--bg-muted) rounded animate-pulse" />
                    <div class="flex-1 h-3 max-w-xs bg-(--bg-muted) rounded animate-pulse" />
                </div>
            </div>
        </div>

        <!-- Empty state -->
        <div v-else-if="scope !== 'global' && !selectedTargetId" class="glass-card rounded-2xl py-20 text-center">
            <i class="ti ti-calendar-time text-4xl text-(--text-muted)" />
            <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No {{ scope }} selected</h4>
            <p class="text-xs text-(--text-muted) mt-1">
                Pick a {{ scope }} from the toolbar to start editing its weekly schedule.
            </p>
        </div>

        <!-- Week editor cards (one card per day, like product cards) -->
        <section v-else class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4">
            <article v-for="(day, idx) in week" :key="day.dayOfWeek" class="day-card"
                :class="{ 'day-card-off': !day.isWorkDay }">
                <header class="flex items-center justify-between gap-2 mb-3">
                    <div>
                        <p class="text-sm font-semibold text-(--text-heading)">{{ dayName(day.dayOfWeek) }}</p>
                        <p class="text-xxs font-mono text-(--text-muted) uppercase tracking-widest">
                            {{ dayLabel(day.dayOfWeek) }}
                        </p>
                    </div>
                    <button type="button" :aria-pressed="day.isWorkDay" class="day-toggle"
                        :class="{ 'day-toggle-on': day.isWorkDay }"
                        @click="onToggleWorkDay(idx, !day.isWorkDay)">
                        <span class="day-toggle-handle"
                            :class="{ 'day-toggle-handle-on': day.isWorkDay }" />
                    </button>
                </header>

                <template v-if="day.isWorkDay">
                    <div class="space-y-2">
                        <div v-for="(interval, iIdx) in day.intervals" :key="iIdx" class="interval-row">
                            <input :value="interval.start" type="time" class="time-input"
                                @input="onIntervalChange(idx, iIdx, 'start', ($event.target as HTMLInputElement).value)" />
                            <span class="text-xxs text-(--text-muted)">to</span>
                            <input :value="interval.end" type="time" class="time-input"
                                @input="onIntervalChange(idx, iIdx, 'end', ($event.target as HTMLInputElement).value)" />
                            <button type="button" class="interval-remove" title="Remove interval"
                                @click="removeInterval(idx, iIdx)">
                                <i class="ti ti-x text-xs" />
                            </button>
                        </div>
                        <button type="button" class="interval-add" @click="addInterval(idx)">
                            <i class="ti ti-plus" /> Add interval
                        </button>
                    </div>
                    <footer class="mt-3 pt-3 border-t border-(--border-color)/60 flex items-center justify-between">
                        <span class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Total</span>
                        <span class="text-xs font-mono font-semibold text-(--text-heading)">
                            {{ totalHoursForDay(day).toFixed(1) }}h
                        </span>
                    </footer>
                </template>
                <div v-else class="day-off-marker">
                    <i class="ti ti-moon text-base" />
                    <span class="text-xs font-medium">Off day</span>
                </div>
            </article>
        </section>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useApi } from '~/composables/useApi'
import { useToast } from '~/composables/useToast'
import { useWorkSchedules, type WorkScheduleRow, type WorkScheduleTargetType } from '~/composables/useWorkSchedules'

interface DeptLite { id: string; name: string }
interface EmployeeLite { id: string; firstName: string; lastName: string; fullName?: string; employeeId?: string | null }

const api = useApi()
const toast = useToast()
const schedules = useWorkSchedules()

const scopeOptions: { value: WorkScheduleTargetType; label: string; icon: string }[] = [
    { value: 'global',     label: 'Global', icon: 'ti-world' },
    { value: 'department', label: 'Department', icon: 'ti-building' },
    { value: 'employee',   label: 'Employee', icon: 'ti-user' },
]

const scope = ref<WorkScheduleTargetType>('global')
const selectedTargetId = ref<string>('')

const loading = ref(false)
const saving = ref(false)
const clearing = ref(false)
const alert = reactive({ msg: '', type: 'success' as 'success' | 'danger' })

const pristine = ref<WorkScheduleRow[]>([])
const week = ref<WorkScheduleRow[]>([])

const dirty = computed(() => JSON.stringify(week.value) !== JSON.stringify(pristine.value))
const canEdit = computed(() => scope.value === 'global' || !!selectedTargetId.value)

const departments = ref<DeptLite[]>([])
const employees = ref<EmployeeLite[]>([])

const dayName = (dow: number) => ['', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'][dow]
const dayLabel = (dow: number) => ['', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT', 'SUN'][dow]

const pageTitle = computed(() => {
    if (scope.value === 'global') return 'Working hours'
    if (scope.value === 'department') {
        const d = departments.value.find(x => x.id === selectedTargetId.value)
        return d ? `Working hours . ${d.name}` : 'Working hours . Department'
    }
    const e = employees.value.find(x => x.id === selectedTargetId.value)
    if (e) return `Working hours . ${e.fullName || `${e.firstName} ${e.lastName}`}`
    return 'Working hours . Employee'
})

const pageHint = computed(() => {
    if (scope.value === 'global') {
        return 'The company default. Patched by department and employee overrides further down the hierarchy.'
    }
    if (scope.value === 'department') {
        return selectedTargetId.value
            ? 'Department override. Falls back to the global default for any day left unset.'
            : 'Override the global default for a specific department (e.g. a branch on a Sun-Thu week).'
    }
    return selectedTargetId.value
        ? 'Employee override. Wins over both the department and the global default.'
        : 'Override the resolved schedule for a single employee (e.g. a part-time engineer).'
})

const summaryLine = computed(() => {
    const workDays = week.value.filter(d => d.isWorkDay).length
    if (workDays === 0) return 'No working days configured'
    const noun = workDays === 1 ? 'day' : 'days'
    return `${workDays} working ${noun} per week`
})

const totalWeeklyHours = computed(() =>
    week.value.reduce((sum, day) => sum + totalHoursForDay(day), 0)
)

interface Chip { key: string; label: string; value: string }
const activeChips = computed<Chip[]>(() => {
    const chips: Chip[] = []
    if (scope.value === 'department' && selectedTargetId.value) {
        const d = departments.value.find(x => x.id === selectedTargetId.value)
        if (d) chips.push({ key: 'target', label: 'Department', value: d.name })
    }
    if (scope.value === 'employee' && selectedTargetId.value) {
        const e = employees.value.find(x => x.id === selectedTargetId.value)
        if (e) chips.push({ key: 'target', label: 'Employee', value: e.fullName || `${e.firstName} ${e.lastName}` })
    }
    return chips
})

const onScopeChange = (next: WorkScheduleTargetType) => {
    scope.value = next
    selectedTargetId.value = ''
    week.value = []
    pristine.value = []
    if (next === 'global') {
        loadSnapshot()
    }
}

const loadSnapshot = async () => {
    if (scope.value !== 'global' && !selectedTargetId.value) {
        week.value = []
        pristine.value = []
        return
    }
    loading.value = true
    try {
        const res = await schedules.snapshot(scope.value, selectedTargetId.value || null)
        const cloned: WorkScheduleRow[] = res.data.map((row: any) => ({
            id: row.id ?? null,
            dayOfWeek: row.dayOfWeek,
            isWorkDay: row.isWorkDay,
            intervals: Array.isArray(row.intervals)
                ? row.intervals.map((iv: any) => ({ start: iv.start, end: iv.end }))
                : [],
        }))
        week.value = cloned
        pristine.value = JSON.parse(JSON.stringify(cloned))
    } catch (err: any) {
        alert.msg = err?.data?.message || 'Failed to load schedule.'
        alert.type = 'danger'
    } finally {
        loading.value = false
    }
}

const onToggleWorkDay = (idx: number, value: boolean) => {
    week.value[idx].isWorkDay = value
    if (!value) {
        week.value[idx].intervals = []
    } else if (week.value[idx].intervals.length === 0) {
        week.value[idx].intervals = [{ start: '08:00', end: '17:00' }]
    }
}

const addInterval = (idx: number) => {
    week.value[idx].intervals.push({ start: '13:00', end: '17:00' })
}

const removeInterval = (idx: number, iIdx: number) => {
    week.value[idx].intervals.splice(iIdx, 1)
}

const onIntervalChange = (idx: number, iIdx: number, key: 'start' | 'end', value: string) => {
    week.value[idx].intervals[iIdx][key] = value
}

const minutesBetween = (a: string, b: string): number => {
    const [ah, am] = a.split(':').map(Number)
    const [bh, bm] = b.split(':').map(Number)
    if (![ah, am, bh, bm].every(n => Number.isFinite(n))) return 0
    const m = (bh * 60 + bm) - (ah * 60 + am)
    return m > 0 ? m : 0
}

const totalHoursForDay = (day: WorkScheduleRow): number => {
    if (!day.isWorkDay) return 0
    const total = day.intervals.reduce((sum, iv) => sum + minutesBetween(iv.start, iv.end), 0)
    return total / 60
}

const onSave = async () => {
    if (!dirty.value) return
    saving.value = true
    try {
        await schedules.upsertWeek({
            targetType: scope.value,
            targetId: scope.value === 'global' ? null : selectedTargetId.value,
            days: week.value.map(d => ({
                dayOfWeek: d.dayOfWeek,
                isWorkDay: d.isWorkDay,
                intervals: d.intervals,
            })),
        })
        pristine.value = JSON.parse(JSON.stringify(week.value))
        alert.msg = 'Work schedule saved.'
        alert.type = 'success'
    } catch (err: any) {
        const validationErrors = err?.data?.errors
        if (validationErrors && typeof validationErrors === 'object') {
            const firstField = Object.keys(validationErrors)[0]
            const firstMsg = validationErrors[firstField]?.[0]
            alert.msg = firstMsg || 'Validation failed.'
        } else {
            alert.msg = err?.data?.message || 'Failed to save schedule.'
        }
        alert.type = 'danger'
    } finally {
        saving.value = false
    }
}

const onClearOverrides = async () => {
    if (scope.value === 'global' || !selectedTargetId.value) return

    const targetLabel = scope.value === 'department'
        ? (departments.value.find(d => d.id === selectedTargetId.value)?.name || 'this department')
        : (() => {
            const e = employees.value.find(x => x.id === selectedTargetId.value)
            return e ? (e.fullName || `${e.firstName} ${e.lastName}`) : 'this employee'
        })()

    const ok = await toast.confirm({
        title: `Clear ${scope.value} overrides?`,
        description: `Removes every work-schedule override for ${targetLabel}. Once cleared, the resolver falls back to the parent layer (${scope.value === 'employee' ? 'department then global' : 'global default'}). Existing leave and attendance records are not modified.`,
        confirmLabel: 'Clear overrides',
        cancelLabel: 'Keep overrides',
        color: 'danger',
        icon: 'ti-trash',
    })
    if (!ok) return

    clearing.value = true
    try {
        const res = await schedules.clearOverrides(scope.value, selectedTargetId.value)
        alert.msg = res.message
        alert.type = 'success'
        await loadSnapshot()
    } catch (err: any) {
        alert.msg = err?.data?.message || 'Failed to clear overrides.'
        alert.type = 'danger'
    } finally {
        clearing.value = false
    }
}

const loadDepartments = async () => {
    try {
        const res = await api.get<{ data: DeptLite[] }>('/hrm/departments?limit=200')
        departments.value = res.data
    } catch { /* swallow */ }
}

const loadEmployees = async () => {
    try {
        const res = await api.get<{ data: EmployeeLite[] }>('/employees?limit=200')
        employees.value = res.data
    } catch { /* swallow */ }
}

watch(scope, (next) => {
    if (next === 'department' && departments.value.length === 0) loadDepartments()
    if (next === 'employee' && employees.value.length === 0) loadEmployees()
})

onMounted(() => {
    loadSnapshot()
})
</script>

<style scoped>

/* Segmented control (mirrors .segmented in Shop) */
.segmented {
    display: inline-flex;
    align-items: center;
    padding: 3px;
    border-radius: 999px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
}

.seg-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 999px;
    border: 0;
    background: transparent;
    font-size: 11px;
    color: var(--text-body);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}

.seg-btn:hover {
    color: var(--text-heading);
}

.seg-btn.active {
    background: rgb(var(--color-primary-rgb) / 0.12);
    color: var(--color-primary);
    box-shadow: inset 0 0 0 1px rgb(var(--color-primary-rgb) / 0.25);
}

/* Filter-select pill (mirrors .filter-select in Shop) */
.filter-select {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 32px;
    padding: 0 10px;
    border-radius: 999px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-body);
    font-size: 11px;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.filter-select:hover {
    background: var(--bg-muted);
    color: var(--text-heading);
}

.filter-select.active {
    background: rgb(var(--color-primary-rgb) / 0.08);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}

.filter-select.active i.ti {
    color: var(--color-primary);
}

.filter-select select {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background: transparent;
    border: 0;
    outline: none;
    font: inherit;
    color: inherit;
    padding-right: 4px;
    max-width: 220px;
    cursor: pointer;
}

.filter-select select:focus {
    outline: none;
}

/* Active-filter chip (mirrors Shop) */
.active-filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    font-size: 11px;
    color: var(--text-body);
}

/* Day card — mirrors ProductCard from Shop */
.day-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 1rem;
    padding: 1rem;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.day-card:hover {
    border-color: rgb(var(--color-primary-rgb) / 0.35);
    box-shadow: 0 2px 8px rgb(var(--color-primary-rgb) / 0.05);
}

.day-card-off {
    background: var(--bg-muted);
    opacity: 0.85;
}

.day-card-off:hover {
    border-color: var(--border-color);
    box-shadow: none;
}

/* Toggle (slim version of the Modules tab toggle) */
.day-toggle {
    position: relative;
    display: inline-flex;
    width: 36px;
    height: 20px;
    border-radius: 999px;
    border: 1px solid var(--border-color);
    background: var(--bg-muted);
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
}

.day-toggle-on {
    background: var(--color-primary);
    border-color: var(--color-primary);
}

.day-toggle-handle {
    display: inline-block;
    width: 14px;
    height: 14px;
    margin: 2px;
    border-radius: 999px;
    background: white;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.15);
    transform: translateX(0);
    transition: transform 0.15s ease;
}

.day-toggle-handle-on {
    transform: translateX(16px);
}

/* Interval row */
.interval-row {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 8px;
    border-radius: 8px;
    background: rgb(var(--color-primary-rgb) / 0.06);
    border: 1px solid rgb(var(--color-primary-rgb) / 0.18);
    width: 100%;
}

.time-input {
    background: transparent;
    border: 0;
    padding: 0 2px;
    font-size: 11px;
    font-family: var(--font-mono, monospace);
    color: var(--text-heading);
    width: 4.5rem;
}

.time-input:focus {
    outline: none;
}

.interval-remove {
    margin-left: auto;
    color: var(--text-muted);
    background: transparent;
    border: 0;
    border-radius: 999px;
    width: 18px;
    height: 18px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}

.interval-remove:hover {
    color: var(--color-danger);
    background: rgb(var(--color-danger-rgb) / 0.12);
}

.interval-add {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 6px 10px;
    border-radius: 8px;
    border: 1px dashed var(--border-color);
    background: transparent;
    color: var(--text-muted);
    font-size: 11px;
    cursor: pointer;
    transition: color 0.15s ease, border-color 0.15s ease;
    width: 100%;
    justify-content: center;
}

.interval-add:hover {
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}

/* Off-day marker */
.day-off-marker {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 1.5rem 0;
    color: var(--text-muted);
    font-style: italic;
}
</style>
