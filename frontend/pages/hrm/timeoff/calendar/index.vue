<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Calendar</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Month view combining holidays and approved/pending leaves. Click a day to add a holiday, click an event to edit.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="shiftMonth(-1)"><i class="ti ti-chevron-left" /></button>
                    <button type="button" class="btn btn-ghost text-xs" @click="goToday">Today</button>
                    <button type="button" class="btn btn-ghost text-xs" @click="shiftMonth(1)"><i class="ti ti-chevron-right" /></button>
                    <span class="text-sm font-semibold font-mono ml-2 min-w-[110px] text-center">{{ monthLabel }}</span>
                </div>
            </header>

            <section class="flex items-center gap-3 flex-wrap text-xxs">
                <span class="legend-chip"><span class="legend-dot bg-(--color-success)" /> Public</span>
                <span class="legend-chip"><span class="legend-dot bg-(--color-primary)" /> Company</span>
                <span class="legend-chip"><span class="legend-dot bg-(--color-info)" /> Optional</span>
                <span class="legend-chip"><span class="legend-dot bg-(--color-warning)" /> Leave</span>
                <span class="ml-auto text-(--text-muted)">{{ feed?.holidays.length || 0 }} holidays / {{ feed?.leaves.length || 0 }} leave records</span>
            </section>

            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading calendar...</span>
            </div>

            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="grid grid-cols-7 bg-(--bg-muted)/40 text-(--text-muted) text-xxs font-bold uppercase tracking-widest">
                    <div v-for="w in WEEKDAYS" :key="w" class="px-2 py-2 text-center">{{ w }}</div>
                </div>
                <div class="grid grid-cols-7 border-t border-(--border-color)">
                    <div v-for="cell in cells" :key="cell.iso"
                        class="day-cell"
                        :class="{
                            'is-other-month': !cell.inMonth,
                            'is-today': cell.iso === todayIso,
                            'is-weekend': cell.weekday === 0 || cell.weekday === 6
                        }"
                        @click="canWrite && cell.inMonth ? openCreateForDate(cell.iso) : null">
                        <div class="day-head flex items-center justify-between">
                            <span class="day-num">{{ cell.day }}</span>
                            <span v-if="cell.events.length" class="text-xxs text-(--text-muted) font-mono">{{ cell.events.length }}</span>
                        </div>
                        <div class="day-events">
                            <button v-for="ev in cell.events" :key="ev.key"
                                type="button" class="event-pill"
                                :class="eventPillClass(ev)"
                                :title="ev.tooltip"
                                @click.stop="onEventClick(ev)">
                                <span class="event-dot" :class="eventDotClass(ev)" />
                                <span class="truncate">{{ ev.label }}</span>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div v-if="showFormModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-xl bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">{{ editingId ? 'Edit Holiday' : 'New Holiday' }}</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="post">
                    <div class="p-5 space-y-4">
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Name *</label>
                            <input v-model="form.name" type="text" required maxlength="200" class="form-control text-xs" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Date *</label>
                                <input v-model="form.date" type="date" required class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Type</label>
                                <select v-model="form.type" class="form-control text-xs">
                                    <option v-for="t in HOLIDAY_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                                </select>
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-xs cursor-pointer">
                            <input v-model="form.is_recurring" type="checkbox" />
                            <span>Recurring (yearly on the same month/day)</span>
                        </label>
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Notes</label>
                            <textarea v-model="form.notes" rows="2" maxlength="2000"
                                class="form-control text-xs resize-none" />
                        </div>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-between gap-2">
                        <button v-if="editingId && canDelete" type="button" class="btn btn-ghost text-xs text-(--color-danger)"
                            @click="onDelete">
                            <i class="ti ti-trash" /> Delete
                        </button>
                        <span v-else></span>
                        <div class="flex items-center gap-2">
                            <button type="button" class="btn btn-ghost text-xs" @click="showFormModal = false">Cancel</button>
                            <button type="submit" class="btn btn-primary text-xs" :disabled="!canSubmit || posting">
                                <i v-if="posting" class="ti ti-loader-2 animate-spin" />
                                {{ editingId ? 'Save' : 'Create' }}
                            </button>
                        </div>
                    </footer>
                </form>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useHrmCalendar, HOLIDAY_TYPES } from '~/composables/useHrmCalendar'
import { useToast } from '~/composables/useToast'
import { useAuthStore } from '~/stores/auth'
import type {
    CalendarFeed,
    CalendarHoliday,
    CalendarLeave,
    CreateHolidayPayload,
    HolidayType,
} from '~/types/hrm-calendar'

definePageMeta({ breadcrumb: 'Calendar' })

const hrm = useHrmCalendar()
const toast = useToast()
const authStore = useAuthStore()

const canRead   = computed(() => authStore.hasPermission('hrm.holiday.read'))
const canWrite  = computed(() => authStore.hasPermission('hrm.holiday.write'))
const canDelete = computed(() => authStore.hasPermission('hrm.holiday.delete'))

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']

const todayIso = new Date().toISOString().slice(0, 10)
const anchor = ref(new Date()) // The month being viewed (we use its year/month)

const monthLabel = computed(() => `${MONTHS[anchor.value.getMonth()]} ${anchor.value.getFullYear()}`)

const shiftMonth = (delta: number) => {
    const d = new Date(anchor.value)
    d.setDate(1)
    d.setMonth(d.getMonth() + delta)
    anchor.value = d
}
const goToday = () => { anchor.value = new Date() }

const loading = ref(false)
const feed = ref<CalendarFeed | null>(null)

const rangeForAnchor = (d: Date): { from: string; to: string } => {
    const first = new Date(d.getFullYear(), d.getMonth(), 1)
    // First Sunday at or before the 1st (so the grid starts with a Sunday row).
    const start = new Date(first)
    start.setDate(first.getDate() - first.getDay())
    // Render 6 rows of 7 = 42 cells to keep height stable.
    const end = new Date(start)
    end.setDate(start.getDate() + 41)
    return { from: start.toISOString().slice(0, 10), to: end.toISOString().slice(0, 10) }
}

const load = async () => {
    if (!canRead.value) return
    loading.value = true
    try {
        const { from, to } = rangeForAnchor(anchor.value)
        const res = await hrm.calendar.feed(from, to)
        feed.value = res.data
    } catch (err: any) {
        toast.error('Failed to load calendar', err?.data?.message)
    } finally {
        loading.value = false
    }
}

watch(anchor, () => load())

// ---- Cells + events --------------------------------------------------------

interface CalendarEvent {
    key: string
    kind: 'holiday' | 'leave'
    label: string
    tooltip: string
    type?: HolidayType
    holiday?: CalendarHoliday
    leave?: CalendarLeave
}

interface DayCell {
    iso: string
    day: number
    weekday: number
    inMonth: boolean
    events: CalendarEvent[]
}

const cells = computed<DayCell[]>(() => {
    const out: DayCell[] = []
    const { from } = rangeForAnchor(anchor.value)
    const start = new Date(from)
    const viewMonth = anchor.value.getMonth()

    // Pre-bucket events by ISO date.
    const buckets = new Map<string, CalendarEvent[]>()
    const push = (iso: string, ev: CalendarEvent) => {
        if (!buckets.has(iso)) buckets.set(iso, [])
        buckets.get(iso)!.push(ev)
    }

    for (const h of feed.value?.holidays ?? []) {
        push(h.date, {
            key: `h-${h.id}-${h.date}`,
            kind: 'holiday',
            label: h.name,
            tooltip: `${h.name}${h.isRecurring ? ' (yearly)' : ''}${h.notes ? ' / ' + h.notes : ''}`,
            type: h.type,
            holiday: h,
        })
    }
    for (const l of feed.value?.leaves ?? []) {
        if (!l.startDate || !l.endDate) continue
        const s = new Date(l.startDate)
        const e = new Date(l.endDate)
        for (let t = s.getTime(); t <= e.getTime(); t += 86_400_000) {
            const iso = new Date(t).toISOString().slice(0, 10)
            push(iso, {
                key: `l-${l.id}-${iso}`,
                kind: 'leave',
                label: l.employeeName || 'Employee',
                tooltip: `${l.employeeName || 'Employee'} / ${l.leaveTypeName || 'leave'} / ${l.status}`,
                leave: l,
            })
        }
    }

    for (let i = 0; i < 42; i++) {
        const d = new Date(start)
        d.setDate(start.getDate() + i)
        const iso = d.toISOString().slice(0, 10)
        out.push({
            iso,
            day: d.getDate(),
            weekday: d.getDay(),
            inMonth: d.getMonth() === viewMonth,
            events: buckets.get(iso) ?? [],
        })
    }
    return out
})

const eventPillClass = (ev: CalendarEvent) => {
    if (ev.kind === 'leave') return 'event-leave'
    return {
        public:   'event-public',
        company:  'event-company',
        optional: 'event-optional',
    }[ev.type as HolidayType] || 'event-public'
}
const eventDotClass = (ev: CalendarEvent) => {
    if (ev.kind === 'leave') return 'bg-(--color-warning)'
    return {
        public:   'bg-(--color-success)',
        company:  'bg-(--color-primary)',
        optional: 'bg-(--color-info)',
    }[ev.type as HolidayType] || 'bg-(--color-success)'
}

// ---- Holiday modal ---------------------------------------------------------

const showFormModal = ref(false)
const editingId = ref<string | null>(null)
const posting = ref(false)

const blankForm = (date?: string): CreateHolidayPayload => ({
    name: '',
    date: date || new Date().toISOString().slice(0, 10),
    type: 'public',
    is_recurring: false,
    notes: null,
})

const form = reactive<CreateHolidayPayload>(blankForm())

const openCreateForDate = (iso: string) => {
    if (!canWrite.value) return
    editingId.value = null
    Object.assign(form, blankForm(iso))
    showFormModal.value = true
}

const openEditHoliday = (h: CalendarHoliday) => {
    editingId.value = h.id
    Object.assign(form, {
        name: h.name,
        date: h.date,
        type: h.type,
        is_recurring: h.isRecurring,
        notes: h.notes,
    })
    showFormModal.value = true
}

const onEventClick = (ev: CalendarEvent) => {
    if (ev.kind === 'holiday' && ev.holiday && canWrite.value) {
        openEditHoliday(ev.holiday)
    }
    // Leaves are not editable from the calendar; their owner page handles it.
}

const canSubmit = computed(() => !!form.name.trim() && !!form.date)

const post = async () => {
    if (!canSubmit.value) return
    posting.value = true
    try {
        const payload: CreateHolidayPayload = {
            ...form,
            name: form.name.trim(),
            notes: form.notes?.toString().trim() || null,
        }
        if (editingId.value) {
            await hrm.holidays.update(editingId.value, payload)
            toast.success('Holiday updated')
        } else {
            await hrm.holidays.create(payload)
            toast.success('Holiday created')
        }
        showFormModal.value = false
        await load()
    } catch (err: any) {
        toast.error('Save failed', err?.data?.message)
    } finally {
        posting.value = false
    }
}

const onDelete = async () => {
    if (!editingId.value) return
    if (!confirm('Delete this holiday?')) return
    posting.value = true
    try {
        await hrm.holidays.destroy(editingId.value)
        toast.success('Holiday deleted')
        showFormModal.value = false
        await load()
    } catch (err: any) {
        toast.error('Delete failed', err?.data?.message)
    } finally {
        posting.value = false
    }
}

onMounted(load)
</script>

<style scoped>
.legend-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 2px 8px;
    border-radius: 999px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-body);
}
.legend-dot {
    display: inline-block;
    width: 8px;
    height: 8px;
    border-radius: 9999px;
}

.day-cell {
    min-height: 92px;
    border-right: 1px solid var(--border-color);
    border-bottom: 1px solid var(--border-color);
    padding: 4px 6px;
    background: var(--bg-card);
    cursor: pointer;
    transition: background 0.1s ease;
}
.day-cell:nth-child(7n) { border-right: none; }
.day-cell:hover { background: var(--bg-muted); }
.day-cell.is-other-month { background: var(--bg-muted); color: var(--text-muted); cursor: default; }
.day-cell.is-other-month:hover { background: var(--bg-muted); }
.day-cell.is-weekend:not(.is-other-month) { background: color-mix(in srgb, var(--bg-muted) 30%, var(--bg-card)); }
.day-cell.is-today { box-shadow: inset 0 0 0 2px var(--color-primary); }

.day-head {
    margin-bottom: 4px;
}
.day-num {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-heading);
}
.day-cell.is-other-month .day-num { color: var(--text-muted); }

.day-events {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.event-pill {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 1px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 500;
    border: 1px solid transparent;
    text-align: left;
    width: 100%;
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
}
.event-pill:hover { border-color: var(--border-strong); }

.event-dot {
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 9999px;
    flex-shrink: 0;
}

.event-public {
    background: color-mix(in srgb, var(--color-success-subtle, rgb(var(--color-success-rgb) / 0.12)) 100%, transparent);
    color: var(--color-success);
}
.event-company {
    background: var(--color-primary-subtle);
    color: var(--color-primary);
}
.event-optional {
    background: rgb(var(--color-info-rgb) / 0.12);
    color: var(--color-info);
}
.event-leave {
    background: rgb(var(--color-warning-rgb) / 0.12);
    color: var(--color-warning);
}
</style>
