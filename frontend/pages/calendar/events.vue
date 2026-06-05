<template>
    <NuxtLayout name="default">
        <div class="space-y-5">
            <!-- Page header -->
            <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-(--text-heading) leading-tight">Unified Calendar</h1>
                    <p class="text-xs text-(--text-muted) mt-1">{{ pageHint }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="action-trigger w-9 h-9 rounded-xl inline-flex items-center justify-center" @click="shiftMonth(-1)" aria-label="Previous month">
                        <i class="ti ti-chevron-left" />
                    </button>
                    <button type="button" class="btn btn-soft-secondary text-xs" @click="goToday">Today</button>
                    <button type="button" class="action-trigger w-9 h-9 rounded-xl inline-flex items-center justify-center" @click="shiftMonth(1)" aria-label="Next month">
                        <i class="ti ti-chevron-right" />
                    </button>
                    <span class="text-sm font-semibold font-mono ml-2 min-w-[120px] text-center text-(--text-heading)">{{ monthLabel }}</span>
                    <button type="button" class="btn btn-primary text-xs inline-flex items-center gap-2" @click="openCreateModal">
                        <i class="ti ti-plus" /> New event
                    </button>
                </div>
            </header>

            <!-- Layer filter chips (per design.md §10.2 / kanban layer pattern) -->
            <section class="flex items-center gap-2 flex-wrap max-sm:justify-center">
                <span class="text-xxs uppercase tracking-widest text-(--text-muted) font-bold mr-1">Layers:</span>
                <button v-for="src in SOURCES" :key="src" type="button"
                    class="chip" :class="{ active: activeLayers.has(src) }"
                    @click="toggleLayer(src)">
                    <span class="legend-dot" :class="sourceMeta(src).dotClass" />
                    <i class="ti" :class="sourceMeta(src).icon" />
                    {{ sourceMeta(src).label }}
                </button>
                <span class="ml-auto text-xxs text-(--text-muted)">
                    {{ filteredEvents.length }} event{{ filteredEvents.length === 1 ? '' : 's' }} this period
                </span>
            </section>

            <!-- Loading -->
            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading calendar...</span>
            </div>

            <!-- Month grid -->
            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="grid grid-cols-7 bg-(--bg-muted)/40 text-(--text-muted) text-xxs font-bold uppercase tracking-widest">
                    <div v-for="w in WEEKDAYS" :key="w" class="px-2 py-2 text-center">{{ w }}</div>
                </div>
                <div class="grid grid-cols-7 border-t border-(--border-color)">
                    <button v-for="cell in cells" :key="cell.iso" type="button"
                        class="day-cell text-left"
                        :class="{
                            'is-other-month': !cell.inMonth,
                            'is-today': cell.iso === todayIso,
                            'is-weekend': cell.weekday === 0 || cell.weekday === 6
                        }"
                        @click="openDayEvents(cell)">
                        <div class="day-head flex items-center justify-between">
                            <span class="day-num">{{ cell.day }}</span>
                            <span v-if="cell.events.length" class="text-xxs text-(--text-muted) font-mono">{{ cell.events.length }}</span>
                        </div>
                        <div class="day-events">
                            <div v-for="ev in cell.events.slice(0, 3)" :key="ev.id"
                                class="event-pill"
                                :class="`is-${ev.source}`"
                                :title="ev.title"
                                @click.stop="openEventDrawer(ev)">
                                <span class="event-dot" :class="sourceMeta(ev.source).dotClass" />
                                <span class="truncate">{{ ev.title }}</span>
                            </div>
                            <div v-if="cell.events.length > 3" class="text-xxs text-(--text-muted) px-1">
                                + {{ cell.events.length - 3 }} more
                            </div>
                        </div>
                    </button>
                </div>
            </section>

            <!-- Upcoming agenda -->
            <section v-if="upcoming.length > 0" class="glass-card rounded-2xl p-5 space-y-3">
                <header class="flex items-center justify-between">
                    <h3 class="text-xxs uppercase tracking-widest text-(--text-muted) font-bold">
                        <i class="ti ti-list-details mr-1" /> Upcoming (next 14 days)
                    </h3>
                </header>
                <ul class="space-y-2">
                    <li v-for="ev in upcoming" :key="ev.id"
                        class="flex items-center gap-3 text-xs py-2 border-b border-(--border-color) last:border-0 cursor-pointer hover:bg-(--bg-muted)/30 rounded transition-colors px-2 -mx-2"
                        @click="openEventDrawer(ev)">
                        <span class="font-mono w-20 text-(--text-heading) text-xxs">{{ formatShortDate(ev.startTime) }}</span>
                        <span class="legend-dot" :class="sourceMeta(ev.source).dotClass" />
                        <span class="flex-1 truncate text-(--text-heading)">{{ ev.title }}</span>
                        <Badge :variant="sourceMeta(ev.source).variant">{{ sourceMeta(ev.source).label }}</Badge>
                    </li>
                </ul>
            </section>
        </div>

        <!-- Event detail drawer -->
        <Teleport to="body">
            <transition name="drawer">
                <aside v-if="drawerEvent" class="event-drawer">
                    <header class="flex items-start justify-between gap-3 p-5 border-b border-(--border-color)">
                        <div class="min-w-0">
                            <Badge :variant="sourceMeta(drawerEvent.source).variant" :icon="sourceMeta(drawerEvent.source).icon">
                                {{ sourceMeta(drawerEvent.source).label }}
                            </Badge>
                            <h3 class="text-base font-semibold text-(--text-heading) mt-2 truncate">{{ drawerEvent.title }}</h3>
                        </div>
                        <button class="action-trigger w-8 h-8 rounded-full inline-flex items-center justify-center" @click="drawerEvent = null">
                            <i class="ti ti-x" />
                        </button>
                    </header>
                    <div class="p-5 space-y-4">
                        <dl class="text-xs space-y-2">
                            <div class="flex items-start gap-2">
                                <dt class="text-(--text-muted) w-24"><i class="ti ti-clock mr-1" />When</dt>
                                <dd class="text-(--text-heading) flex-1">
                                    <div>{{ formatDateTime(drawerEvent.startTime) }}</div>
                                    <div v-if="drawerEvent.endTime && drawerEvent.endTime !== drawerEvent.startTime" class="text-(--text-muted)">
                                        to {{ formatDateTime(drawerEvent.endTime) }}
                                    </div>
                                    <span v-if="drawerEvent.isAllDay" class="text-xxs text-(--text-muted)">All day</span>
                                </dd>
                            </div>
                            <div v-if="drawerEvent.category" class="flex items-start gap-2">
                                <dt class="text-(--text-muted) w-24"><i class="ti ti-tag mr-1" />Category</dt>
                                <dd class="text-(--text-heading) flex-1 capitalize">{{ drawerEvent.category }}</dd>
                            </div>
                            <div v-if="drawerEvent.description" class="flex items-start gap-2">
                                <dt class="text-(--text-muted) w-24"><i class="ti ti-note mr-1" />Notes</dt>
                                <dd class="text-(--text-heading) flex-1 leading-relaxed">{{ drawerEvent.description }}</dd>
                            </div>
                            <div v-if="drawerEvent.meta?.overtimeMultiplier" class="flex items-start gap-2">
                                <dt class="text-(--text-muted) w-24"><i class="ti ti-clock-up mr-1" />OT multiplier</dt>
                                <dd class="text-(--text-heading) font-mono">{{ drawerEvent.meta.overtimeMultiplier }}x</dd>
                            </div>
                            <div v-if="drawerEvent.meta?.compensatoryFor" class="flex items-start gap-2">
                                <dt class="text-(--text-muted) w-24"><i class="ti ti-arrow-back-up mr-1" />Comp for</dt>
                                <dd class="text-(--text-heading) font-mono text-xxs truncate">{{ drawerEvent.meta.compensatoryFor }}</dd>
                            </div>
                        </dl>

                        <div v-if="drawerEvent.source === 'calendar'" class="flex gap-2 pt-2 border-t border-(--border-color)">
                            <button class="btn btn-soft-secondary text-xs flex-1" @click="openEditModal(drawerEvent)">
                                <i class="ti ti-edit" /> Edit
                            </button>
                            <button class="btn btn-soft-danger text-xs flex-1" @click="deleteEvent(drawerEvent)">
                                <i class="ti ti-trash" /> Delete
                            </button>
                        </div>
                    </div>
                </aside>
            </transition>
        </Teleport>

        <!-- Create / edit modal -->
        <Teleport to="body">
            <div v-if="formModalOpen" class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4" @click.self="formModalOpen = false">
                <div class="glass-card rounded-2xl max-w-md w-full p-6 space-y-4">
                    <header class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-(--text-heading)">
                            {{ form.id ? 'Edit event' : 'New event' }}
                        </h3>
                        <button class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="formModalOpen = false">
                            <i class="ti ti-x" />
                        </button>
                    </header>
                    <form @submit.prevent="saveEvent" class="space-y-3">
                        <div>
                            <label class="text-xxs font-bold uppercase tracking-wider text-(--text-muted)">Title *</label>
                            <input v-model="form.title" required maxlength="200" class="form-control text-sm mt-1" />
                        </div>
                        <div>
                            <label class="text-xxs font-bold uppercase tracking-wider text-(--text-muted)">Category</label>
                            <select v-model="form.category" class="form-control text-sm mt-1">
                                <option value="general">General</option>
                                <option value="meeting">Meeting</option>
                                <option value="training">Training</option>
                                <option value="company">Company</option>
                                <option value="personal">Personal</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="text-xxs font-bold uppercase tracking-wider text-(--text-muted)">Start *</label>
                                <input v-model="form.start_time" type="datetime-local" required class="form-control text-sm mt-1" />
                            </div>
                            <div>
                                <label class="text-xxs font-bold uppercase tracking-wider text-(--text-muted)">End *</label>
                                <input v-model="form.end_time" type="datetime-local" required class="form-control text-sm mt-1" />
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-xs">
                            <input v-model="form.is_all_day" type="checkbox" /> All day
                        </label>
                        <div>
                            <label class="text-xxs font-bold uppercase tracking-wider text-(--text-muted)">Description</label>
                            <textarea v-model="form.description" rows="3" maxlength="2000" class="form-control text-xs mt-1" />
                        </div>
                        <div v-if="error" class="text-xs text-(--color-danger)">{{ error }}</div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" class="btn btn-soft-secondary text-xs" @click="formModalOpen = false">Cancel</button>
                            <button type="submit" class="btn btn-primary text-xs" :disabled="saving">
                                {{ saving ? 'Saving...' : (form.id ? 'Save changes' : 'Create event') }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Day events modal -->
        <Teleport to="body">
            <div v-if="dayModal.open" class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4" @click.self="dayModal.open = false">
                <div class="glass-card rounded-2xl max-w-md w-full p-6 space-y-3 max-h-[80vh] overflow-y-auto">
                    <header class="flex items-center justify-between">
                        <div>
                            <span class="text-xxs uppercase tracking-widest text-(--text-muted) font-bold">Day overview</span>
                            <h3 class="text-base font-semibold text-(--text-heading)">{{ formatLongDate(dayModal.iso) }}</h3>
                        </div>
                        <button class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="dayModal.open = false">
                            <i class="ti ti-x" />
                        </button>
                    </header>
                    <ul v-if="dayModal.events.length" class="space-y-2">
                        <li v-for="ev in dayModal.events" :key="ev.id"
                            class="flex items-center gap-3 text-xs py-2 border-b border-(--border-color) last:border-0 cursor-pointer hover:bg-(--bg-muted)/30 rounded transition-colors px-2 -mx-2"
                            @click="dayModal.open = false; openEventDrawer(ev)">
                            <span class="legend-dot" :class="sourceMeta(ev.source).dotClass" />
                            <span class="flex-1 truncate text-(--text-heading)">{{ ev.title }}</span>
                            <Badge :variant="sourceMeta(ev.source).variant">{{ sourceMeta(ev.source).label }}</Badge>
                        </li>
                    </ul>
                    <p v-else class="text-xs text-(--text-muted) text-center py-6">Nothing scheduled.</p>
                    <button class="btn btn-soft-primary text-xs w-full inline-flex items-center justify-center gap-2"
                        @click="dayModal.open = false; openCreateModalForDay(dayModal.iso)">
                        <i class="ti ti-plus" /> Add event on this day
                    </button>
                </div>
            </div>
        </Teleport>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, reactive, onMounted, watch } from 'vue'
import { useCalendar, type CalendarEventSource, type CalendarFeedEvent } from '~/composables/useCalendar'
import { useToast } from '~/composables/useToast'
import { formatDateTime } from '~/composables/useDateFormat'

definePageMeta({ breadcrumb: 'Unified Calendar', title: 'Unified Calendar' })

const cal = useCalendar()
const toast = useToast()
const { sourceMeta } = cal

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December']
const SOURCES: CalendarEventSource[] = ['holiday', 'calendar', 'leave', 'shift', 'appointment']

const todayIso = new Date().toISOString().slice(0, 10)
const anchor = ref(new Date())
const monthLabel = computed(() => `${MONTHS[anchor.value.getMonth()]} ${anchor.value.getFullYear()}`)

const loading = ref(false)
const events = ref<CalendarFeedEvent[]>([])
const activeLayers = ref<Set<CalendarEventSource>>(new Set(SOURCES))
const drawerEvent = ref<CalendarFeedEvent | null>(null)
const formModalOpen = ref(false)
const saving = ref(false)
const error = ref('')

const dayModal = reactive<{ open: boolean; iso: string; events: CalendarFeedEvent[] }>({
    open: false, iso: '', events: [],
})

const form = ref({
    id: '' as string,
    title: '',
    description: '',
    start_time: '',
    end_time: '',
    category: 'general' as 'general' | 'meeting' | 'training' | 'company' | 'personal',
    is_all_day: false,
})

const pageHint = computed(() => {
    const total = events.value.length
    if (loading.value) return 'Loading events across holidays, leaves, shifts, meetings, and custom entries...'
    if (total === 0) return 'No events visible. Toggle a layer or create a custom event to get started.'
    return `${total} events from ${activeLayers.value.size} active layer${activeLayers.value.size === 1 ? '' : 's'}.`
})

const rangeForAnchor = (d: Date): { from: string; to: string } => {
    const first = new Date(d.getFullYear(), d.getMonth(), 1)
    const start = new Date(first)
    start.setDate(first.getDate() - first.getDay())
    const end = new Date(start)
    end.setDate(start.getDate() + 41)
    return {
        from: start.toISOString().slice(0, 10),
        to: end.toISOString().slice(0, 10),
    }
}

async function load() {
    loading.value = true
    try {
        const { from, to } = rangeForAnchor(anchor.value)
        const res = await cal.events.list({
            from,
            to,
            categories: Array.from(activeLayers.value),
        })
        events.value = res.data ?? []
    } catch (e: any) {
        toast.error('Calendar load failed', e?.data?.message || 'Unable to fetch events.')
        events.value = []
    } finally {
        loading.value = false
    }
}

watch(anchor, load)

function shiftMonth(delta: number) {
    const d = new Date(anchor.value)
    d.setDate(1)
    d.setMonth(d.getMonth() + delta)
    anchor.value = d
}

function goToday() {
    anchor.value = new Date()
}

function toggleLayer(s: CalendarEventSource) {
    if (activeLayers.value.has(s)) activeLayers.value.delete(s)
    else activeLayers.value.add(s)
    // Re-trigger reactivity (Set mutation doesn't trip Vue's watcher)
    activeLayers.value = new Set(activeLayers.value)
    load()
}

interface DayCell {
    iso: string
    day: number
    weekday: number
    inMonth: boolean
    events: CalendarFeedEvent[]
}

// Filter events to active layers (already requested from server but keep
// client-side guard for snappy toggling).
const filteredEvents = computed(() => events.value.filter(e => activeLayers.value.has(e.source)))

const cells = computed<DayCell[]>(() => {
    const out: DayCell[] = []
    const { from } = rangeForAnchor(anchor.value)
    const start = new Date(from)
    const viewMonth = anchor.value.getMonth()

    const buckets = new Map<string, CalendarFeedEvent[]>()
    for (const ev of filteredEvents.value) {
        if (!ev.startTime || !ev.endTime) continue
        const s = new Date(ev.startTime)
        const e = new Date(ev.endTime)
        for (let t = s.getTime(); t <= e.getTime(); t += 86_400_000) {
            const iso = new Date(t).toISOString().slice(0, 10)
            if (!buckets.has(iso)) buckets.set(iso, [])
            buckets.get(iso)!.push(ev)
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

const upcoming = computed(() => {
    const now = Date.now()
    const cutoff = now + 14 * 86_400_000
    return filteredEvents.value
        .filter(ev => {
            const t = ev.startTime ? new Date(ev.startTime).getTime() : 0
            return t >= now && t <= cutoff
        })
        .sort((a, b) => (a.startTime ?? '').localeCompare(b.startTime ?? ''))
        .slice(0, 10)
})

function openEventDrawer(ev: CalendarFeedEvent) {
    drawerEvent.value = ev
}

function openDayEvents(cell: DayCell) {
    if (cell.events.length === 0) {
        openCreateModalForDay(cell.iso)
        return
    }
    dayModal.iso = cell.iso
    dayModal.events = cell.events
    dayModal.open = true
}

function resetForm() {
    form.value = {
        id: '',
        title: '',
        description: '',
        start_time: '',
        end_time: '',
        category: 'general',
        is_all_day: false,
    }
    error.value = ''
}

function openCreateModal() {
    resetForm()
    const now = new Date()
    now.setMinutes(0, 0, 0)
    form.value.start_time = toLocalInput(now)
    now.setHours(now.getHours() + 1)
    form.value.end_time = toLocalInput(now)
    formModalOpen.value = true
}

function openCreateModalForDay(iso: string) {
    resetForm()
    form.value.start_time = `${iso}T09:00`
    form.value.end_time = `${iso}T10:00`
    formModalOpen.value = true
    drawerEvent.value = null
}

function openEditModal(ev: CalendarFeedEvent) {
    resetForm()
    form.value = {
        id: ev.id,
        title: ev.title,
        description: ev.description ?? '',
        start_time: ev.startTime ? toLocalInput(new Date(ev.startTime)) : '',
        end_time: ev.endTime ? toLocalInput(new Date(ev.endTime)) : '',
        category: (ev.category as any) ?? 'general',
        is_all_day: ev.isAllDay,
    }
    drawerEvent.value = null
    formModalOpen.value = true
}

async function saveEvent() {
    saving.value = true
    error.value = ''
    try {
        const payload: any = {
            title: form.value.title,
            description: form.value.description || null,
            start_time: new Date(form.value.start_time).toISOString(),
            end_time: new Date(form.value.end_time).toISOString(),
            category: form.value.category,
            is_all_day: form.value.is_all_day,
        }
        if (form.value.id) {
            await cal.events.update(form.value.id, payload)
            toast.success('Event updated')
        } else {
            await cal.events.create(payload)
            toast.success('Event created')
        }
        formModalOpen.value = false
        await load()
    } catch (e: any) {
        error.value = e?.data?.message || 'Save failed.'
    } finally {
        saving.value = false
    }
}

async function deleteEvent(ev: CalendarFeedEvent) {
    const ok = await (toast as any).confirm?.({
        title: `Delete "${ev.title}"?`,
        description: 'This permanently removes the custom calendar event.',
        confirmLabel: 'Delete',
        color: 'danger',
    }) ?? confirm(`Delete "${ev.title}"?`)
    if (!ok) return
    try {
        await cal.events.destroy(ev.id)
        toast.success('Event deleted')
        drawerEvent.value = null
        await load()
    } catch (e: any) {
        toast.error('Delete failed', e?.data?.message || '')
    }
}

function toLocalInput(d: Date): string {
    const pad = (n: number) => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

const formatShortDate = (iso: string | null) => {
    if (!iso) return '-'
    const d = new Date(iso)
    return `${d.getDate()} ${MONTHS[d.getMonth()].slice(0, 3)}`
}

const formatLongDate = (iso: string) => {
    if (!iso) return '-'
    const d = new Date(iso)
    return `${WEEKDAYS[d.getDay()]}, ${d.getDate()} ${MONTHS[d.getMonth()]} ${d.getFullYear()}`
}

onMounted(load)
</script>

<style scoped>
/* Filter chips (CoA pattern) */
.chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 12px; border-radius: 999px;
    border: 1px solid var(--border-color); background: var(--bg-card);
    font-size: 11px; color: var(--text-body); cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
.chip:hover { background: var(--bg-muted); }
.chip.active {
    background: rgb(var(--color-primary-rgb) / 0.12);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}

/* Action trigger (chevrons) */
.action-trigger {
    color: var(--text-muted);
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    transition: background 0.12s ease, color 0.12s ease, border-color 0.12s ease;
}
.action-trigger:hover { background: var(--bg-muted); color: var(--text-heading); border-color: var(--color-primary); }

/* Legend / event dots */
.legend-dot {
    display: inline-block; width: 8px; height: 8px; border-radius: 50%;
}
.event-dot {
    display: inline-block; width: 6px; height: 6px; border-radius: 50%; flex-shrink: 0;
}

/* Calendar grid */
.day-cell {
    min-height: 100px;
    padding: 6px 6px;
    background: var(--bg-card);
    border-right: 1px solid var(--border-color);
    border-bottom: 1px solid var(--border-color);
    display: flex; flex-direction: column; gap: 4px;
    transition: background 0.12s ease;
    cursor: pointer;
}
.day-cell:hover { background: var(--bg-muted); }
.day-cell.is-other-month { background: var(--bg-muted)/0.4; opacity: 0.55; }
.day-cell.is-weekend { background: rgb(var(--color-primary-rgb) / 0.02); }
.day-cell.is-today {
    background: rgb(var(--color-primary-rgb) / 0.06);
    box-shadow: inset 0 0 0 1px rgb(var(--color-primary-rgb) / 0.4);
}
.day-head {
    font-size: 11px;
}
.day-num {
    font-weight: 600;
    color: var(--text-heading);
    font-family: 'JetBrains Mono', ui-monospace, monospace;
}
.day-events {
    display: flex; flex-direction: column; gap: 2px;
    overflow: hidden;
}
.event-pill {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 5px;
    border-radius: 4px;
    font-size: 10px;
    background: var(--bg-muted);
    color: var(--text-body);
    cursor: pointer;
    transition: background 0.12s ease;
}
.event-pill:hover { background: var(--border-color); }
.event-pill.is-holiday   { background: rgb(var(--color-success-rgb) / 0.12); color: var(--color-success); }
.event-pill.is-leave     { background: rgb(var(--color-warning-rgb) / 0.12); color: var(--color-warning); }
.event-pill.is-shift     { background: rgb(var(--color-info-rgb) / 0.12);    color: var(--color-info); }
.event-pill.is-appointment { background: rgb(var(--color-primary-rgb) / 0.12); color: var(--color-primary); }

/* Event detail drawer */
.event-drawer {
    position: fixed; top: 0; right: 0; bottom: 0;
    width: 100%; max-width: 380px;
    background: var(--bg-card);
    border-left: 1px solid var(--border-color);
    box-shadow: var(--shadow-lg);
    z-index: 55;
    display: flex; flex-direction: column;
}

.drawer-enter-active, .drawer-leave-active { transition: transform 0.22s ease, opacity 0.22s ease; }
.drawer-enter-from, .drawer-leave-to { transform: translateX(100%); opacity: 0; }
</style>
