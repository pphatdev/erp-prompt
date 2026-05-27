<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-(--text-heading)">CRM Schedules</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        Calendar of upcoming meetings, demos, and follow-ups. Distinct from the Sales Pipeline product schedule.
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex items-center bg-(--bg-card) border border-(--border-color) rounded-lg p-1">
                        <button v-for="v in (['agenda', 'week', 'month'] as const)" :key="v"
                            class="px-3 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
                            :class="view === v ? 'bg-(--color-primary-subtle) text-(--color-primary)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                            @click="view = v">{{ v }}</button>
                    </div>
                    <button class="btn btn-ghost text-xs" :disabled="loading" @click="shiftWindow(-1)">
                        <i class="ti ti-chevron-left" />
                    </button>
                    <span class="text-xs font-mono text-(--text-muted) min-w-[140px] text-center">{{ windowLabel }}</span>
                    <button class="btn btn-ghost text-xs" :disabled="loading" @click="shiftWindow(1)">
                        <i class="ti ti-chevron-right" />
                    </button>
                    <button class="btn btn-ghost text-xs" :disabled="loading" @click="resetToToday">
                        Today
                    </button>
                    <button class="btn btn-primary text-xs" @click="openCreate">
                        <i class="ti ti-plus" />New
                    </button>
                </div>
            </header>

            <!-- Status filter -->
            <section class="glass-card rounded-xl p-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1">
                        <button v-for="s in (['all', 'scheduled', 'completed', 'cancelled', 'no_show'] as const)" :key="s"
                            class="px-3 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
                            :class="filterStatus === s ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                            @click="filterStatus = s">{{ s.replace('_', ' ') }}</button>
                    </div>
                    <span class="text-xxs text-(--text-muted) font-mono">{{ filteredAppointments.length }} appointment(s)</span>
                </div>
            </section>

            <!-- Loading -->
            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading appointments…</span>
            </div>

            <!-- Empty -->
            <div v-else-if="filteredAppointments.length === 0"
                class="glass-card rounded-2xl p-16 text-center space-y-3">
                <i class="ti ti-calendar-off text-3xl text-(--text-muted)" />
                <p class="text-sm font-semibold text-(--text-heading)">Nothing on the calendar</p>
                <p class="text-xxs text-(--text-muted)">Schedule a meeting, demo, or follow-up to populate this view.</p>
                <button class="btn btn-primary text-xs" @click="openCreate">
                    <i class="ti ti-plus" />Schedule appointment
                </button>
            </div>

            <!-- Agenda -->
            <section v-else-if="view === 'agenda'" class="space-y-6">
                <div v-for="day in groupedByDay" :key="day.key" class="space-y-2">
                    <h3 class="text-xxs uppercase tracking-widest font-bold text-(--text-muted) px-1">
                        {{ day.label }} · {{ day.items.length }}
                    </h3>
                    <div class="space-y-2">
                        <button v-for="a in day.items" :key="a.id" type="button"
                            class="w-full glass-card rounded-xl p-4 text-left hover:border-(--color-primary)/40 transition-colors"
                            @click="openEdit(a)">
                            <div class="flex items-start gap-3">
                                <div class="text-center shrink-0">
                                    <p class="text-xxs uppercase font-bold tracking-widest text-(--text-muted)">{{ fmtTime(a.startsAt) }}</p>
                                    <p class="text-xxs text-(--text-muted)">→ {{ fmtTime(a.endsAt) }}</p>
                                </div>
                                <div class="w-1 self-stretch rounded-full" :class="statusBarClass(a.status)" />
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <p class="text-sm font-semibold text-(--text-heading) truncate">{{ a.subject }}</p>
                                        <Badge :variant="crmBadgeVariant(a.status)">{{ a.status.replace('_', ' ') }}</Badge>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1 text-xxs text-(--text-muted)">
                                        <span v-if="a.location" class="inline-flex items-center gap-1">
                                            <i class="ti ti-map-pin" />{{ a.location }}
                                        </span>
                                        <span v-if="a.opportunity" class="inline-flex items-center gap-1">
                                            <i class="ti ti-target" />{{ a.opportunity.title }}
                                        </span>
                                        <span v-else-if="a.lead" class="inline-flex items-center gap-1">
                                            <i class="ti ti-address-book" />{{ a.lead.title }}
                                        </span>
                                        <span v-if="a.attendees?.length" class="inline-flex items-center gap-1">
                                            <i class="ti ti-users" />{{ a.attendees.length }} attendee(s)
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </button>
                    </div>
                </div>
            </section>

            <!-- Week / Month — compact grid -->
            <section v-else class="glass-card rounded-2xl p-4 overflow-x-auto">
                <div class="grid gap-2 min-w-[640px]"
                    :class="view === 'week' ? 'grid-cols-7' : 'grid-cols-7'">
                    <div v-for="d in gridDays" :key="d.dateIso"
                        class="rounded-lg border border-(--border-color) bg-(--bg-card) p-2 min-h-[120px] flex flex-col gap-1.5"
                        :class="{ 'ring-1 ring-(--color-primary)/40': d.isToday }">
                        <header class="flex items-center justify-between">
                            <span class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">{{ d.dayLabel }}</span>
                            <span class="text-xxs font-mono"
                                :class="d.isToday ? 'text-(--color-primary) font-bold' : 'text-(--text-body)'">{{ d.dateNum }}</span>
                        </header>
                        <button v-for="a in d.items.slice(0, 4)" :key="a.id" type="button"
                            class="text-left rounded px-1.5 py-1 text-xxs hover:opacity-80 transition-opacity"
                            :class="statusChipClass(a.status)" @click="openEdit(a)">
                            <span class="font-mono">{{ fmtTime(a.startsAt) }}</span>
                            <span class="truncate ml-1">{{ a.subject }}</span>
                        </button>
                        <span v-if="d.items.length > 4" class="text-xxs text-(--text-muted) px-1.5">
                            +{{ d.items.length - 4 }} more
                        </span>
                    </div>
                </div>
            </section>
        </div>

        <!-- Schedule modal (create / edit) -->
        <div v-if="showModal"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-2xl bg-(--bg-card) shadow-(--shadow-lg) flex flex-col max-h-[90vh]">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color) shrink-0">
                    <div>
                        <h3 class="font-semibold text-sm">{{ isEdit ? 'Edit appointment' : 'Schedule appointment' }}</h3>
                        <p v-if="editTarget?.status" class="text-xxs text-(--text-muted) mt-0.5">Status: {{ editTarget.status }}</p>
                    </div>
                    <button class="w-8 h-8 rounded-full hover:bg-(--bg-muted)" @click="closeModal">
                        <i class="ti ti-x" />
                    </button>
                </header>

                <form class="p-5 space-y-4 overflow-y-auto" @submit.prevent="save">
                    <div>
                        <label class="form-label">Subject *</label>
                        <input v-model="form.subject" type="text" class="form-control" required
                            placeholder="e.g. Demo with Acme procurement" :disabled="locked" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Starts at *</label>
                            <input v-model="form.starts_at" type="datetime-local" class="form-control" required :disabled="locked" />
                        </div>
                        <div>
                            <label class="form-label">Ends at *</label>
                            <input v-model="form.ends_at" type="datetime-local" class="form-control" required :disabled="locked" />
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Location</label>
                        <input v-model="form.location" type="text" class="form-control"
                            placeholder="e.g. Zoom, Office HQ, Phone" :disabled="locked" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">Linked Opportunity</label>
                            <select v-model="form.opportunity_id" class="form-control" :disabled="locked || isEdit">
                                <option :value="null">— none —</option>
                                <option v-for="o in opportunities" :key="o.id" :value="o.id">{{ o.title }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Linked Lead</label>
                            <select v-model="form.lead_id" class="form-control" :disabled="locked || isEdit">
                                <option :value="null">— none —</option>
                                <option v-for="l in leads" :key="l.id" :value="l.id">{{ l.title }}</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Attendees</label>
                        <div class="space-y-2">
                            <div v-for="(att, i) in form.attendees" :key="i"
                                class="grid grid-cols-12 gap-2">
                                <input v-model="att.name" type="text" placeholder="Name *" class="form-control col-span-4 text-xs" :disabled="locked" />
                                <input v-model="att.email" type="email" placeholder="Email" class="form-control col-span-5 text-xs" :disabled="locked" />
                                <input v-model="att.role" type="text" placeholder="Role" class="form-control col-span-2 text-xs" :disabled="locked" />
                                <button type="button" class="col-span-1 text-(--color-danger) hover:bg-(--color-danger-subtle) rounded"
                                    :disabled="locked" @click="removeAttendee(i)">
                                    <i class="ti ti-trash" />
                                </button>
                            </div>
                            <button v-if="!locked" type="button" class="btn btn-ghost text-xxs" @click="addAttendee">
                                <i class="ti ti-plus" />Add attendee
                            </button>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Notes</label>
                        <textarea v-model="form.notes" rows="3" maxlength="2000"
                            class="form-control" :disabled="locked"
                            placeholder="Agenda, dial-in details, preparation notes…" />
                    </div>
                </form>

                <footer class="p-5 border-t border-(--border-color) flex justify-between gap-2 shrink-0">
                    <div class="flex gap-2">
                        <button v-if="isEdit && !locked" type="button" class="btn text-xs text-(--color-success) border border-(--color-success)/20 hover:bg-(--color-success-subtle)"
                            :disabled="acting" @click="complete">
                            <i class="ti ti-check" />Complete
                        </button>
                        <button v-if="isEdit && !locked" type="button" class="btn btn-ghost text-xs"
                            :disabled="acting" @click="markNoShow">
                            <i class="ti ti-user-x" />No-show
                        </button>
                        <button v-if="isEdit && !locked" type="button" class="btn text-xs text-(--color-danger) border border-(--color-danger)/20 hover:bg-(--color-danger-subtle)"
                            :disabled="acting" @click="cancel">
                            <i class="ti ti-ban" />Cancel
                        </button>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="closeModal">Close</button>
                        <button v-if="!locked" type="button" class="btn btn-primary text-xs" :disabled="acting" @click="save">
                            <i class="ti ti-device-floppy" />{{ isEdit ? 'Save changes' : 'Schedule' }}
                        </button>
                    </div>
                </footer>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useCrm, crmBadgeVariant } from '~/composables/useCrm'
import { useToast } from '~/composables/useToast'
import type {
    AppointmentStatus,
    CreateAppointmentPayload,
    CrmAppointment,
    Lead,
    Opportunity,
} from '~/types/crm'

const crm = useCrm()
const toast = useToast()

type View = 'agenda' | 'week' | 'month'

const view = ref<View>('agenda')
const loading = ref(false)
const acting = ref(false)
const filterStatus = ref<'all' | AppointmentStatus>('all')

const appointments = ref<CrmAppointment[]>([])
const opportunities = ref<Pick<Opportunity, 'id' | 'title'>[]>([])
const leads = ref<Pick<Lead, 'id' | 'title'>[]>([])

const anchor = ref<Date>(startOfToday())

const showModal = ref(false)
const isEdit = ref(false)
const editTarget = ref<CrmAppointment | null>(null)

const form = reactive<CreateAppointmentPayload>({
    subject: '',
    starts_at: '',
    ends_at: '',
    location: null,
    attendees: [],
    notes: null,
    opportunity_id: null,
    lead_id: null,
})

const locked = computed(() =>
    isEdit.value && editTarget.value !== null && editTarget.value.status !== 'scheduled'
)

// ── Window calculation ────────────────────────────────────────────────────
const window = computed(() => {
    const a = new Date(anchor.value)
    if (view.value === 'agenda' || view.value === 'week') {
        const day = a.getDay()
        const start = new Date(a); start.setDate(a.getDate() - day); start.setHours(0, 0, 0, 0)
        const end = new Date(start); end.setDate(start.getDate() + 6); end.setHours(23, 59, 59, 999)
        return { start, end }
    }
    // month
    const start = new Date(a.getFullYear(), a.getMonth(), 1, 0, 0, 0, 0)
    const end = new Date(a.getFullYear(), a.getMonth() + 1, 0, 23, 59, 59, 999)
    return { start, end }
})

const windowLabel = computed(() => {
    const { start, end } = window.value
    if (view.value === 'month') {
        return start.toLocaleDateString(undefined, { month: 'long', year: 'numeric' })
    }
    return `${start.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })} – ${end.toLocaleDateString(undefined, { month: 'short', day: 'numeric' })}`
})

const filteredAppointments = computed(() =>
    filterStatus.value === 'all'
        ? appointments.value
        : appointments.value.filter((a) => a.status === filterStatus.value)
)

const groupedByDay = computed(() => {
    const groups = new Map<string, CrmAppointment[]>()
    for (const a of filteredAppointments.value) {
        const d = new Date(a.startsAt)
        const key = d.toISOString().slice(0, 10)
        if (!groups.has(key)) groups.set(key, [])
        groups.get(key)!.push(a)
    }
    return Array.from(groups.entries())
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([key, items]) => ({
            key,
            label: new Date(key).toLocaleDateString(undefined, { weekday: 'long', month: 'short', day: 'numeric' }),
            items: items.sort((x, y) => x.startsAt.localeCompare(y.startsAt)),
        }))
})

const gridDays = computed(() => {
    const { start, end } = window.value
    const days: { dateIso: string; dateNum: number; dayLabel: string; isToday: boolean; items: CrmAppointment[] }[] = []
    const today = startOfToday().toISOString().slice(0, 10)
    const d = new Date(start)
    while (d <= end) {
        const iso = d.toISOString().slice(0, 10)
        days.push({
            dateIso: iso,
            dateNum: d.getDate(),
            dayLabel: d.toLocaleDateString(undefined, { weekday: 'short' }),
            isToday: iso === today,
            items: filteredAppointments.value.filter((a) => a.startsAt.slice(0, 10) === iso),
        })
        d.setDate(d.getDate() + 1)
    }
    return days
})

// ── Data loading ──────────────────────────────────────────────────────────
const load = async () => {
    loading.value = true
    try {
        const { start, end } = window.value
        const res = await crm.appointments.list({
            from: start.toISOString().slice(0, 10),
            to: end.toISOString().slice(0, 10),
        })
        appointments.value = (res as { data: CrmAppointment[] }).data
    } catch (err: any) {
        toast.error('Failed to load appointments', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const loadLinkables = async () => {
    try {
        const [opps, lds] = await Promise.all([
            crm.opportunities.list({ limit: 100 }),
            crm.leads.list({ limit: 100 }),
        ])
        opportunities.value = opps.data.map((o) => ({ id: o.id, title: o.title }))
        leads.value = lds.data.map((l) => ({ id: l.id, title: l.title }))
    } catch {
        // non-fatal — selects stay empty
    }
}

watch(view, load)
watch(anchor, load)

// ── Navigation ────────────────────────────────────────────────────────────
const shiftWindow = (direction: -1 | 1) => {
    const next = new Date(anchor.value)
    if (view.value === 'month') {
        next.setMonth(next.getMonth() + direction)
    } else {
        next.setDate(next.getDate() + direction * 7)
    }
    anchor.value = next
}
const resetToToday = () => { anchor.value = startOfToday() }

// ── Modal ─────────────────────────────────────────────────────────────────
const openCreate = () => {
    isEdit.value = false
    editTarget.value = null
    const now = new Date()
    const oneHourLater = new Date(now.getTime() + 60 * 60 * 1000)
    form.subject = ''
    form.starts_at = toLocalInput(now)
    form.ends_at = toLocalInput(oneHourLater)
    form.location = null
    form.attendees = []
    form.notes = null
    form.opportunity_id = null
    form.lead_id = null
    showModal.value = true
}

const openEdit = (appt: CrmAppointment) => {
    isEdit.value = true
    editTarget.value = appt
    form.subject = appt.subject
    form.starts_at = toLocalInput(new Date(appt.startsAt))
    form.ends_at = toLocalInput(new Date(appt.endsAt))
    form.location = appt.location
    form.attendees = appt.attendees ? [...appt.attendees] : []
    form.notes = appt.notes
    form.opportunity_id = appt.opportunityId
    form.lead_id = appt.leadId
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
    editTarget.value = null
}

const addAttendee = () => form.attendees!.push({ name: '', email: null, role: null })
const removeAttendee = (i: number) => form.attendees!.splice(i, 1)

const save = async () => {
    acting.value = true
    try {
        const payload: CreateAppointmentPayload = {
            ...form,
            starts_at: new Date(form.starts_at).toISOString(),
            ends_at: new Date(form.ends_at).toISOString(),
            attendees: (form.attendees || []).filter((a) => a.name?.trim()),
        }

        if (isEdit.value && editTarget.value) {
            const res = await crm.appointments.update(editTarget.value.id, payload)
            replaceInList(res.data)
            toast.success('Appointment updated')
        } else {
            const res = await crm.appointments.create(payload)
            appointments.value.push(res.data)
            toast.success('Appointment scheduled', res.data.subject)
        }
        closeModal()
    } catch (err: any) {
        toast.error('Save failed', err?.data?.message)
    } finally {
        acting.value = false
    }
}

const complete = async () => actOnTarget((id) => crm.appointments.complete(id), 'completed')
const markNoShow = async () => actOnTarget((id) => crm.appointments.markNoShow(id), 'marked no-show')
const cancel = async () => actOnTarget((id) => crm.appointments.cancel(id, undefined), 'cancelled')

const actOnTarget = async (fn: (id: string) => Promise<{ data: CrmAppointment }>, verb: string) => {
    if (!editTarget.value) return
    acting.value = true
    try {
        const res = await fn(editTarget.value.id)
        replaceInList(res.data)
        toast.success(`Appointment ${verb}`)
        closeModal()
    } catch (err: any) {
        toast.error('Action failed', err?.data?.message)
    } finally {
        acting.value = false
    }
}

const replaceInList = (updated: CrmAppointment) => {
    const idx = appointments.value.findIndex((a) => a.id === updated.id)
    if (idx !== -1) appointments.value[idx] = updated
}

// ── Helpers ───────────────────────────────────────────────────────────────
function startOfToday(): Date {
    const d = new Date(); d.setHours(0, 0, 0, 0); return d
}

function toLocalInput(d: Date): string {
    // YYYY-MM-DDTHH:mm for <input type=datetime-local>
    const pad = (n: number) => String(n).padStart(2, '0')
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`
}

const fmtTime = (iso: string) => new Date(iso).toLocaleTimeString(undefined, { hour: '2-digit', minute: '2-digit' })

const statusBarClass = (s: AppointmentStatus) => ({
    'scheduled': 'bg-(--color-info)',
    'completed': 'bg-(--color-success)',
    'cancelled': 'bg-(--color-danger)',
    'no_show':   'bg-(--color-warning)',
}[s] || 'bg-(--text-muted)')

const statusChipClass = (s: AppointmentStatus) => ({
    'scheduled': 'bg-(--color-info-subtle) text-(--color-info)',
    'completed': 'bg-(--color-success-subtle) text-(--color-success)',
    'cancelled': 'bg-(--color-danger-subtle) text-(--color-danger) line-through',
    'no_show':   'bg-(--color-warning-subtle) text-(--color-warning)',
}[s] || 'bg-(--bg-muted) text-(--text-muted)')

onMounted(() => {
    load()
    loadLinkables()
})
</script>
