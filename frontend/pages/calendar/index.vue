<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">My Calendar</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Holidays the company observes plus your personal schedule of leaves. Read-only here - manage entries from HRM or the Leaves page.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="shiftMonth(-1)"><i class="ti ti-chevron-left" /></button>
                    <button type="button" class="btn btn-ghost text-xs" @click="goToday">Today</button>
                    <button type="button" class="btn btn-ghost text-xs" @click="shiftMonth(1)"><i class="ti ti-chevron-right" /></button>
                    <span class="text-sm font-semibold font-mono ml-2 min-w-[110px] text-center">{{ monthLabel }}</span>
                </div>
            </header>

            <section class="flex items-center gap-3 flex-wrap text-xxs">
                <span class="legend-chip"><span class="legend-dot bg-(--color-success)" /> Public holiday</span>
                <span class="legend-chip"><span class="legend-dot bg-(--color-primary)" /> Company</span>
                <span class="legend-chip"><span class="legend-dot bg-(--color-info)" /> Optional</span>
                <span class="legend-chip"><span class="legend-dot bg-(--color-warning)" /> My leave (pending)</span>
                <span class="legend-chip"><span class="legend-dot bg-(--color-success)" /> My leave (approved)</span>
                <span class="ml-auto text-(--text-muted)">{{ feed?.holidays.length || 0 }} holidays / {{ feed?.personalLeaves.length || 0 }} my leaves</span>
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
                        }">
                        <div class="day-head flex items-center justify-between">
                            <span class="day-num">{{ cell.day }}</span>
                            <span v-if="cell.events.length" class="text-xxs text-(--text-muted) font-mono">{{ cell.events.length }}</span>
                        </div>
                        <div class="day-events">
                            <div v-for="ev in cell.events" :key="ev.key"
                                class="event-pill"
                                :class="eventPillClass(ev)"
                                :title="ev.tooltip">
                                <span class="event-dot" :class="eventDotClass(ev)" />
                                <span class="truncate">{{ ev.label }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section v-if="upcomingHolidays.length || upcomingLeaves.length" class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <h3 class="text-xs font-bold uppercase tracking-widest text-(--text-muted)">Upcoming Holidays</h3>
                    <ul class="space-y-1">
                        <li v-for="h in upcomingHolidays" :key="`${h.id}-${h.date}`" class="flex items-center gap-2 text-xs">
                            <span class="font-mono w-20 text-(--text-heading)">{{ h.date }}</span>
                            <span class="event-dot" :class="holidayDot(h.type)" />
                            <span class="flex-1 truncate">{{ h.name }}</span>
                            <span v-if="h.isRecurring" class="text-xxs text-(--text-muted)">yearly</span>
                        </li>
                    </ul>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <h3 class="text-xs font-bold uppercase tracking-widest text-(--text-muted)">My Upcoming Leaves</h3>
                    <ul class="space-y-1">
                        <li v-for="l in upcomingLeaves" :key="l.id" class="flex items-center gap-2 text-xs">
                            <span class="font-mono w-20 text-(--text-heading)">{{ l.startDate }}</span>
                            <span class="text-xxs px-1.5 py-0.5 rounded font-mono" :class="leaveStatusBadge(l.status)">{{ l.status }}</span>
                            <span class="flex-1 truncate">{{ l.leaveTypeName || 'Leave' }}</span>
                            <span v-if="l.endDate && l.startDate && l.endDate !== l.startDate" class="text-xxs text-(--text-muted)">-> {{ l.endDate }}</span>
                        </li>
                    </ul>
                    <p v-if="upcomingLeaves.length === 0" class="text-xxs text-(--text-muted)">No upcoming leaves on file.</p>
                </div>
            </section>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useHrmCalendar } from '~/composables/useHrmCalendar'
import { useToast } from '~/composables/useToast'
import type {
    PersonalCalendarFeed,
    CalendarHoliday,
    PersonalLeave,
    HolidayType,
} from '~/types/hrm-calendar'

definePageMeta({ breadcrumb: 'Calendar' })

const hrm = useHrmCalendar()
const toast = useToast()

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']

const todayIso = new Date().toISOString().slice(0, 10)
const anchor = ref(new Date())
const monthLabel = computed(() => `${MONTHS[anchor.value.getMonth()]} ${anchor.value.getFullYear()}`)

const shiftMonth = (delta: number) => {
    const d = new Date(anchor.value)
    d.setDate(1)
    d.setMonth(d.getMonth() + delta)
    anchor.value = d
}
const goToday = () => { anchor.value = new Date() }

const loading = ref(false)
const feed = ref<PersonalCalendarFeed | null>(null)

const rangeForAnchor = (d: Date): { from: string; to: string } => {
    const first = new Date(d.getFullYear(), d.getMonth(), 1)
    const start = new Date(first)
    start.setDate(first.getDate() - first.getDay())
    const end = new Date(start)
    end.setDate(start.getDate() + 41)
    return { from: start.toISOString().slice(0, 10), to: end.toISOString().slice(0, 10) }
}

const load = async () => {
    loading.value = true
    try {
        const { from, to } = rangeForAnchor(anchor.value)
        const res = await hrm.calendar.myFeed(from, to)
        feed.value = res.data
    } catch (err: any) {
        toast.error('Failed to load calendar', err?.data?.message)
    } finally {
        loading.value = false
    }
}
watch(anchor, () => load())

interface CalendarEvent {
    key: string
    kind: 'holiday' | 'leave'
    label: string
    tooltip: string
    type?: HolidayType
    leaveStatus?: string
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
        })
    }
    for (const l of feed.value?.personalLeaves ?? []) {
        if (!l.startDate || !l.endDate) continue
        const s = new Date(l.startDate)
        const e = new Date(l.endDate)
        for (let t = s.getTime(); t <= e.getTime(); t += 86_400_000) {
            const iso = new Date(t).toISOString().slice(0, 10)
            push(iso, {
                key: `l-${l.id}-${iso}`,
                kind: 'leave',
                label: l.leaveTypeName || 'Leave',
                tooltip: `${l.leaveTypeName || 'Leave'} / ${l.status}${l.reason ? ' / ' + l.reason : ''}`,
                leaveStatus: l.status,
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
    if (ev.kind === 'leave') {
        return ev.leaveStatus === 'approved' ? 'event-leave-approved' : 'event-leave-pending'
    }
    return {
        public:   'event-public',
        company:  'event-company',
        optional: 'event-optional',
    }[ev.type as HolidayType] || 'event-public'
}
const eventDotClass = (ev: CalendarEvent) => {
    if (ev.kind === 'leave') {
        return ev.leaveStatus === 'approved' ? 'bg-(--color-success)' : 'bg-(--color-warning)'
    }
    return holidayDot(ev.type as HolidayType)
}
const holidayDot = (t: HolidayType) => ({
    public:   'bg-(--color-success)',
    company:  'bg-(--color-primary)',
    optional: 'bg-(--color-info)',
}[t] || 'bg-(--color-success)')

const leaveStatusBadge = (s: string) => ({
    pending:   'badge-soft-warning',
    approved:  'badge-soft-success',
    rejected:  'badge-soft-danger',
    cancelled: 'badge-soft-secondary',
}[s] || 'badge-soft-secondary')

const upcomingHolidays = computed<CalendarHoliday[]>(() =>
    (feed.value?.holidays ?? [])
        .filter(h => h.date >= todayIso)
        .sort((a, b) => a.date.localeCompare(b.date))
        .slice(0, 5)
)
const upcomingLeaves = computed<PersonalLeave[]>(() =>
    (feed.value?.personalLeaves ?? [])
        .filter(l => (l.endDate ?? '') >= todayIso)
        .sort((a, b) => (a.startDate ?? '').localeCompare(b.startDate ?? ''))
        .slice(0, 5)
)

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
    min-height: 96px;
    border-right: 1px solid var(--border-color);
    border-bottom: 1px solid var(--border-color);
    padding: 4px 6px;
    background: var(--bg-card);
    transition: background 0.1s ease;
}
.day-cell:nth-child(7n) { border-right: none; }
.day-cell.is-other-month { background: var(--bg-muted); color: var(--text-muted); }
.day-cell.is-weekend:not(.is-other-month) { background: color-mix(in srgb, var(--bg-muted) 30%, var(--bg-card)); }
.day-cell.is-today { box-shadow: inset 0 0 0 2px var(--color-primary); }

.day-head { margin-bottom: 4px; }
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
}
.event-dot {
    display: inline-block;
    width: 6px;
    height: 6px;
    border-radius: 9999px;
    flex-shrink: 0;
}

.event-public {
    background: rgb(var(--color-success-rgb) / 0.12);
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
.event-leave-pending {
    background: rgb(var(--color-warning-rgb) / 0.12);
    color: var(--color-warning);
}
.event-leave-approved {
    background: rgb(var(--color-success-rgb) / 0.18);
    color: var(--color-success);
    border-color: rgb(var(--color-success-rgb) / 0.3);
}
</style>
