<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Page header (§3 typography) -->
            <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-(--text-heading) leading-tight">POS Shifts</h1>
                    <p class="text-xs text-(--text-muted) mt-1">{{ pageHint }}</p>
                </div>
                <div class="flex items-center gap-2">
                    <button v-if="myShift" class="btn btn-soft-warning text-xs inline-flex items-center gap-2" @click="openCloseModal">
                        <i class="ti ti-lock" /> Close my shift
                    </button>
                    <button v-else class="btn btn-primary text-xs inline-flex items-center gap-2"
                        :disabled="terminals.length === 0" @click="openOpenModal">
                        <i class="ti ti-cash-register" /> Open shift
                    </button>
                </div>
            </header>

            <!-- Active shift hero (§5.1 meteor-card style; only when the actor has one) -->
            <section v-if="myShift"
                class="glass-card rounded-2xl p-5 sm:p-6 relative overflow-hidden"
                :class="myShift.isOverride ? 'border border-(--color-warning)/40' : 'border border-(--color-primary)/30'">
                <span class="absolute top-0 right-0 h-[2px] w-[120px] bg-linear-to-r pointer-events-none"
                    :class="myShift.isOverride ? 'from-(--color-warning) to-transparent' : 'from-(--color-primary) to-transparent'" />
                <div class="absolute -right-16 -top-16 w-56 h-56 rounded-full blur-3xl pointer-events-none"
                    :class="myShift.isOverride ? 'bg-(--color-warning)/10' : 'bg-(--color-primary)/10'" />
                <div class="relative z-10 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                    <div class="space-y-2 md:col-span-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <Badge :variant="statusMeta(myShift.status).variant" :icon="statusMeta(myShift.status).icon">
                                {{ statusMeta(myShift.status).label }}
                            </Badge>
                            <Badge v-if="myShift.isOverride" variant="warning" icon="ti-shield-check">
                                Admin override
                            </Badge>
                        </div>
                        <h3 class="text-base font-semibold text-(--text-heading)">
                            <template v-if="myShift.isOverride">
                                Acting on
                                <span class="font-mono">{{ myShift.terminal?.code || myShift.terminalId.slice(0, 8) }}</span>
                                for <span class="text-(--color-warning)">{{ myShift.cashierName || 'another cashier' }}</span>
                            </template>
                            <template v-else>
                                You are on
                                <span class="font-mono">{{ myShift.terminal?.code || myShift.terminalId.slice(0, 8) }}</span>
                                - {{ myShift.terminal?.name || 'terminal' }}
                            </template>
                        </h3>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-(--text-muted)">
                            <span class="inline-flex items-center gap-1.5">
                                <i class="ti ti-clock text-xxs" />
                                Opened <span class="text-(--text-heading)">{{ formatDateTime(myShift.openedAt) }}</span>
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <i class="ti ti-cash text-xxs" />
                                Opening float
                                <span class="font-mono text-(--text-heading)">{{ formatMoney(myShift.openingFloat) }}</span>
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2">
                        <NuxtLink to="/pos/register"
                            class="btn btn-primary text-xs inline-flex items-center justify-center gap-2">
                            <i class="ti ti-cash-register" /> Open register
                        </NuxtLink>
                    </div>
                </div>
            </section>

            <!-- KPI metrics row (§5.1) -->
            <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <article v-for="kpi in kpis" :key="kpi.label" class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">{{ kpi.label }}</span>
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center" :class="kpi.badgeClass">
                            <i class="ti text-sm" :class="kpi.icon" />
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono leading-none" :class="kpi.toneClass">
                        {{ kpi.value }}
                    </p>
                    <p class="text-xxs text-(--text-muted)">{{ kpi.sub }}</p>
                </article>
            </section>

            <!-- Filter toolbar (§5.2): status chips + cashier search -->
            <section class="flex items-center gap-2 flex-wrap max-sm:justify-center">
                <button type="button" class="chip" :class="{ active: filterStatus === 'all' }"
                    @click="filterStatus = 'all'; load()">
                    <i class="ti ti-list" /> All
                </button>
                <button v-for="s in SHIFT_STATUSES" :key="s.value" type="button"
                    class="chip" :class="{ active: filterStatus === s.value }"
                    @click="filterStatus = s.value; load()">
                    <i class="ti" :class="s.icon" /> {{ s.label }}
                </button>
                <div class="ml-auto">
                    <input v-model.lazy="search" type="search" placeholder="Search terminal or cashier..."
                        class="form-control text-xs w-64" @keyup.enter="load" @change="load" />
                </div>
            </section>

            <!-- Loading -->
            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading shifts...</span>
            </div>

            <!-- Empty -->
            <div v-else-if="filtered.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-clock-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No shifts in this bucket</h4>
                <p class="text-xs text-(--text-muted) mt-1">
                    {{ filterStatus === 'all' ? 'Open the first shift on an active terminal.' : 'Try clearing the filter.' }}
                </p>
            </div>

            <!-- High-density data table (§5.3) -->
            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead>
                            <tr class="text-xxs font-bold uppercase tracking-widest text-(--text-muted) border-b border-(--border-color)">
                                <th class="px-4 py-3 text-left">Status</th>
                                <th class="px-4 py-3 text-left">Terminal</th>
                                <th class="px-4 py-3 text-left">Cashier</th>
                                <th class="px-4 py-3 text-left">Opened</th>
                                <th class="px-4 py-3 text-left">Closed</th>
                                <th class="px-4 py-3 text-right">Opening</th>
                                <th class="px-4 py-3 text-right">Expected</th>
                                <th class="px-4 py-3 text-right">Counted</th>
                                <th class="px-4 py-3 text-right">Variance</th>
                                <th class="px-4 py-3 text-right w-16">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in filtered" :key="s.id"
                                class="border-b border-(--border-color) last:border-0 hover:bg-(--bg-muted)/40 transition-colors">
                                <td class="px-4 py-3">
                                    <Badge :variant="statusMeta(s.status).variant" :icon="statusMeta(s.status).icon">
                                        {{ statusMeta(s.status).label }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="font-mono text-(--text-heading)">{{ s.terminal?.code || s.terminalId.slice(0, 8) }}</div>
                                    <div class="text-xxs text-(--text-muted) truncate max-w-[140px]">{{ s.terminal?.name || '-' }}</div>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="w-7 h-7 rounded-full bg-(--color-primary)/10 text-(--color-primary) flex items-center justify-center text-xxs font-bold">
                                            {{ (s.cashierName || '?').slice(0, 1).toUpperCase() }}
                                        </span>
                                        <span class="text-(--text-heading) truncate max-w-[120px]">{{ s.cashierName || 'unknown' }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-(--text-body) whitespace-nowrap">{{ formatDateTime(s.openedAt) }}</td>
                                <td class="px-4 py-3 text-(--text-body) whitespace-nowrap">{{ formatDateTime(s.closedAt) }}</td>
                                <td class="px-4 py-3 text-right font-mono text-(--text-heading)">
                                    {{ formatMoney(s.openingFloat) }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-(--text-heading)">
                                    {{ s.expectedCash !== null ? formatMoney(s.expectedCash) : '-' }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-(--text-heading)">
                                    {{ s.closingCash !== null ? formatMoney(s.closingCash) : '-' }}
                                </td>
                                <td class="px-4 py-3 text-right font-mono"
                                    :class="varianceToneClass(s.variance)">
                                    {{ s.variance !== null ? formatMoney(s.variance) : '-' }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button type="button"
                                        class="action-trigger w-7 h-7 rounded-full inline-flex items-center justify-center"
                                        :class="actionMenu.shiftId === s.id && actionMenu.open ? 'action-trigger-open' : ''"
                                        @click.stop="openActionMenu(s, $event)">
                                        <i class="ti ti-dots-vertical" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Row action floating menu (§14.1) -->
        <Teleport to="body">
            <div v-if="actionMenu.open" class="action-menu"
                :style="{ left: actionMenu.x + 'px', top: actionMenu.y + 'px' }"
                @click.stop>
                <button class="action-item" @click="viewShift(actionMenu.shift); closeActionMenu()">
                    <i class="ti ti-eye" /> View detail
                </button>
                <button v-if="canReconcile(actionMenu.shift)" class="action-item action-item-info"
                    @click="openReconcile(actionMenu.shift!); closeActionMenu()">
                    <i class="ti ti-stamp" /> Reconcile variance
                </button>
            </div>
        </Teleport>

        <!-- Open shift modal -->
        <Teleport to="body">
            <div v-if="openModalOpen" class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4" @click.self="openModalOpen = false">
                <div class="glass-card rounded-2xl max-w-sm w-full p-6 space-y-4">
                    <header class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-(--text-heading)">Open shift</h3>
                        <button class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="openModalOpen = false">
                            <i class="ti ti-x" />
                        </button>
                    </header>
                    <form @submit.prevent="submitOpen" class="space-y-3">
                        <div>
                            <label class="text-xxs font-bold uppercase tracking-wider text-(--text-muted)">Terminal *</label>
                            <select v-model="openForm.terminalId" required class="form-control text-sm mt-1">
                                <option value="">Choose terminal</option>
                                <option v-for="t in activeTerminals" :key="t.id" :value="t.id">
                                    {{ t.code }} - {{ t.name }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xxs font-bold uppercase tracking-wider text-(--text-muted)">Opening float *</label>
                            <input v-model.number="openForm.openingFloat" type="number" step="0.01" min="0" required
                                class="form-control text-sm mt-1 font-mono text-right" />
                            <p class="text-xxs text-(--text-muted) mt-1">Cash counted in the drawer before the first sale.</p>
                        </div>
                        <div v-if="error" class="text-xs text-(--color-danger)">{{ error }}</div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" class="btn btn-soft-secondary text-xs" @click="openModalOpen = false">Cancel</button>
                            <button type="submit" class="btn btn-primary text-xs" :disabled="saving">
                                {{ saving ? 'Opening...' : 'Open shift' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Close shift modal -->
        <Teleport to="body">
            <div v-if="closeModalOpen" class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4" @click.self="closeModalOpen = false">
                <div class="glass-card rounded-2xl max-w-sm w-full p-6 space-y-4">
                    <header class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-(--text-heading)">Close shift</h3>
                        <button class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="closeModalOpen = false">
                            <i class="ti ti-x" />
                        </button>
                    </header>
                    <form @submit.prevent="submitClose" class="space-y-3">
                        <div>
                            <label class="text-xxs font-bold uppercase tracking-wider text-(--text-muted)">Counted cash *</label>
                            <input v-model.number="closeForm.closingCash" type="number" step="0.01" min="0" required
                                class="form-control text-sm mt-1 font-mono text-right" />
                            <p class="text-xxs text-(--text-muted) mt-1">If this doesn't match expected, the shift moves to variance-pending for supervisor review.</p>
                        </div>
                        <div>
                            <label class="text-xxs font-bold uppercase tracking-wider text-(--text-muted)">Notes</label>
                            <textarea v-model="closeForm.notes" rows="2" class="form-control text-xs mt-1" />
                        </div>
                        <div v-if="error" class="text-xs text-(--color-danger)">{{ error }}</div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" class="btn btn-soft-secondary text-xs" @click="closeModalOpen = false">Cancel</button>
                            <button type="submit" class="btn btn-warning text-xs" :disabled="saving">
                                {{ saving ? 'Closing...' : 'Close shift' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Reconcile variance modal -->
        <Teleport to="body">
            <div v-if="reconcileModalOpen && reconcileShift" class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4" @click.self="reconcileModalOpen = false">
                <div class="glass-card rounded-2xl max-w-md w-full p-6 space-y-4">
                    <header class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-(--text-heading)">Reconcile shift variance</h3>
                        <button class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="reconcileModalOpen = false">
                            <i class="ti ti-x" />
                        </button>
                    </header>
                    <div class="rounded-xl p-4 text-xs space-y-2 bg-(--bg-muted) border border-(--border-color)">
                        <div class="flex justify-between"><span class="text-(--text-muted)">Expected cash</span><span class="font-mono">{{ formatMoney(reconcileShift.expectedCash) }}</span></div>
                        <div class="flex justify-between"><span class="text-(--text-muted)">Counted cash</span><span class="font-mono">{{ formatMoney(reconcileShift.closingCash) }}</span></div>
                        <div class="flex justify-between pt-2 border-t border-(--border-color) font-semibold">
                            <span>Variance ({{ reconcileShift.variance && reconcileShift.variance > 0 ? 'over' : 'short' }})</span>
                            <span class="font-mono" :class="reconcileShift.variance && reconcileShift.variance > 0 ? 'text-(--color-success)' : 'text-(--color-warning)'">
                                {{ formatMoney(reconcileShift.variance) }}
                            </span>
                        </div>
                    </div>
                    <p class="text-xxs text-(--text-muted)">
                        Approval posts a Cash Over/Short journal against the terminal's drawer account. Cannot be undone.
                    </p>
                    <div>
                        <label class="text-xxs font-bold uppercase tracking-wider text-(--text-muted)">Notes</label>
                        <textarea v-model="reconcileNotes" rows="2" class="form-control text-xs mt-1" />
                    </div>
                    <div v-if="error" class="text-xs text-(--color-danger)">{{ error }}</div>
                    <div class="flex justify-end gap-2 pt-2">
                        <button class="btn btn-soft-secondary text-xs" @click="reconcileModalOpen = false">Cancel</button>
                        <button class="btn btn-info text-xs" :disabled="saving" @click="submitReconcile">
                            {{ saving ? 'Reconciling...' : 'Approve & post' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, reactive } from 'vue'
import { usePos, type PosShift, type PosTerminal } from '~/composables/usePos'
import { useToast } from '~/composables/useToast'
import { useAuthStore } from '~/stores/auth'
import { formatDateTime } from '~/composables/useDateFormat'

definePageMeta({ title: 'Shifts' })

const pos = usePos()
const toast = useToast()
const auth = useAuthStore()
const canApprove = computed(() => auth.hasPermission('pos.shift.approve'))

const SHIFT_STATUSES = [
    { value: 'open', label: 'Open', icon: 'ti-clock-play' },
    { value: 'closed', label: 'Closed', icon: 'ti-check' },
    { value: 'variance_pending', label: 'Variance', icon: 'ti-alert-triangle' },
    { value: 'reconciled', label: 'Reconciled', icon: 'ti-stamp' },
] as const

// Status badge map per design.md §10.2 / §10.4 - variant + icon + label.
// Single source of truth; the chip filter row keeps its own short labels.
type ShiftVariant = 'success' | 'secondary' | 'warning' | 'info'
const SHIFT_STATUS_META: Record<string, { variant: ShiftVariant; icon: string; label: string }> = {
    open: { variant: 'success', icon: 'ti-clock-play', label: 'Open' },
    closed: { variant: 'secondary', icon: 'ti-check', label: 'Closed' },
    variance_pending: { variant: 'warning', icon: 'ti-alert-triangle', label: 'Variance pending' },
    reconciled: { variant: 'info', icon: 'ti-stamp', label: 'Reconciled' },
}
function statusMeta(status: string) {
    return SHIFT_STATUS_META[status]
        ?? { variant: 'secondary' as ShiftVariant, icon: 'ti-help', label: status }
}

const myShift = ref<PosShift | null>(null)
const shifts = ref<PosShift[]>([])
const terminals = ref<PosTerminal[]>([])
const loading = ref(true)
const filterStatus = ref<'all' | string>('all')
const search = ref('')

const openModalOpen = ref(false)
const closeModalOpen = ref(false)
const saving = ref(false)
const error = ref('')

const openForm = ref({ terminalId: '', openingFloat: 0 })
const closeForm = ref({ closingCash: 0, notes: '' })
const reconcileModalOpen = ref(false)
const reconcileShift = ref<PosShift | null>(null)
const reconcileNotes = ref('')

// Floating row action menu state (§14.1)
const actionMenu = reactive<{
    open: boolean
    shift: PosShift | null
    shiftId: string
    x: number
    y: number
}>({
    open: false, shift: null, shiftId: '', x: 0, y: 0,
})

const formatMoney = (n: number | null | undefined) =>
    (Number.isFinite(Number(n)) ? Number(n) : 0).toLocaleString(undefined, { style: 'currency', currency: 'USD' })

const activeTerminals = computed(() => terminals.value.filter(t => t.status === 'active'))

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase()
    if (!term) return shifts.value
    return shifts.value.filter(s =>
        (s.terminal?.code || '').toLowerCase().includes(term) ||
        (s.terminal?.name || '').toLowerCase().includes(term) ||
        (s.cashierName || '').toLowerCase().includes(term)
    )
})

const pageHint = computed(() => {
    if (myShift.value) {
        return 'Your shift is open. Take sales from the register, then close to count the drawer.'
    }
    return 'Cashier register sessions. Open one to start taking sales, close it to count the drawer.'
})

// KPI tiles (§5.1)
const kpis = computed(() => {
    const open = shifts.value.filter(s => s.status === 'open').length
    const variancePending = shifts.value.filter(s => s.status === 'variance_pending').length
    const today = new Date().toISOString().slice(0, 10)
    const reconciledToday = shifts.value.filter(s =>
        s.status === 'reconciled' && (s.reconciledAt ?? '').startsWith(today)
    ).length
    const netVarianceToday = shifts.value
        .filter(s => (s.closedAt ?? '').startsWith(today) && s.variance !== null)
        .reduce((sum, s) => sum + Number(s.variance ?? 0), 0)

    return [
        {
            label: 'Open shifts',
            value: String(open),
            sub: open === 1 ? 'cashier on the clock' : 'cashiers on the clock',
            icon: 'ti-clock-play',
            badgeClass: 'badge-soft-success',
            toneClass: '',
        },
        {
            label: 'Variance pending',
            value: String(variancePending),
            sub: 'awaiting supervisor reconcile',
            icon: 'ti-alert-triangle',
            badgeClass: 'badge-soft-warning',
            toneClass: variancePending > 0 ? 'text-(--color-warning)' : '',
        },
        {
            label: 'Reconciled today',
            value: String(reconciledToday),
            sub: 'variance entries cleared',
            icon: 'ti-clipboard-check',
            badgeClass: 'badge-soft-info',
            toneClass: '',
        },
        {
            label: 'Today net variance',
            value: formatMoney(netVarianceToday),
            sub: netVarianceToday > 0 ? 'cash drawers running over' : netVarianceToday < 0 ? 'cash drawers running short' : 'drawers balanced',
            icon: 'ti-scale',
            badgeClass: netVarianceToday === 0 ? 'badge-soft-success' : 'badge-soft-warning',
            toneClass: netVarianceToday === 0
                ? 'text-(--color-success)'
                : netVarianceToday > 0 ? 'text-(--color-success)' : 'text-(--color-warning)',
        },
    ]
})

function varianceToneClass(variance: number | null | undefined): string {
    if (variance === null || variance === undefined) return 'text-(--text-muted)'
    if (Math.abs(variance) < 0.005) return 'text-(--color-success)'
    return variance > 0 ? 'text-(--color-success)' : 'text-(--color-warning)'
}

function canReconcile(s: PosShift | null): boolean {
    return !!s && s.status === 'variance_pending' && canApprove.value
}

async function loadMe() {
    try {
        const res = await pos.shifts.me()
        myShift.value = res.data ?? null
    } catch { myShift.value = null }
}

async function loadTerminals() {
    try {
        const res = await pos.terminals.list({ status: 'active', limit: 100 })
        terminals.value = res.data ?? []
    } catch { terminals.value = [] }
}

async function load() {
    loading.value = true
    try {
        const res = await pos.shifts.list({
            status: filterStatus.value === 'all' ? undefined : filterStatus.value,
            limit: 50,
        })
        shifts.value = res.data ?? []
    } finally {
        loading.value = false
    }
}

function openOpenModal() {
    openForm.value = { terminalId: activeTerminals.value[0]?.id || '', openingFloat: 0 }
    error.value = ''
    openModalOpen.value = true
}

async function submitOpen() {
    saving.value = true
    error.value = ''
    try {
        await pos.shifts.open({ terminal_id: openForm.value.terminalId, opening_float: openForm.value.openingFloat })
        toast.success('Shift opened')
        openModalOpen.value = false
        await Promise.all([loadMe(), load()])
    } catch (e: any) {
        error.value = e?.data?.message || 'Open failed.'
    } finally { saving.value = false }
}

function openCloseModal() {
    closeForm.value = { closingCash: 0, notes: '' }
    error.value = ''
    closeModalOpen.value = true
}

async function submitClose() {
    if (!myShift.value) return
    saving.value = true
    error.value = ''
    try {
        await pos.shifts.close(myShift.value.id, { closing_cash: closeForm.value.closingCash, notes: closeForm.value.notes || null })
        toast.success('Shift closed')
        closeModalOpen.value = false
        await Promise.all([loadMe(), load()])
    } catch (e: any) {
        error.value = e?.data?.message || 'Close failed.'
    } finally { saving.value = false }
}

function openReconcile(s: PosShift) {
    reconcileShift.value = s
    reconcileNotes.value = ''
    error.value = ''
    reconcileModalOpen.value = true
}

async function submitReconcile() {
    if (!reconcileShift.value) return
    saving.value = true
    error.value = ''
    try {
        await pos.shifts.reconcile(reconcileShift.value.id, reconcileNotes.value || null)
        toast.success('Variance reconciled')
        reconcileModalOpen.value = false
        await load()
    } catch (e: any) {
        error.value = e?.data?.message || 'Reconcile failed.'
    } finally { saving.value = false }
}

// Kebab dropdown positioning (§14.1)
function openActionMenu(s: PosShift, ev: MouseEvent) {
    const rect = (ev.currentTarget as HTMLElement).getBoundingClientRect()
    const menuWidth = 200
    const menuMaxHeight = 120
    const left = Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8)
    const wouldOverflow = rect.bottom + menuMaxHeight + 8 > window.innerHeight
    actionMenu.shift = s
    actionMenu.shiftId = s.id
    actionMenu.x = Math.max(8, left)
    actionMenu.y = wouldOverflow ? rect.top - menuMaxHeight - 6 : rect.bottom + 6
    actionMenu.open = true
}

function closeActionMenu() {
    actionMenu.open = false
    actionMenu.shift = null
    actionMenu.shiftId = ''
}

function viewShift(_s: PosShift | null) {
    // Detail page is part of a later phase; for now, scroll to row + flash.
    toast.info('Shift detail', 'Detail page lands in the next iteration.')
}

if (import.meta.client) {
    const dismiss = () => actionMenu.open && closeActionMenu()
    document.addEventListener('click', dismiss)
    onBeforeUnmount(() => document.removeEventListener('click', dismiss))
}

onMounted(async () => {
    await Promise.all([loadMe(), loadTerminals(), load()])
})
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

/* Row action trigger + floating menu (§14.1) */
.action-trigger {
    color: var(--text-muted);
    transition: background 0.12s ease, color 0.12s ease;
}
.action-trigger:hover { background: var(--bg-muted); color: var(--text-heading); }
.action-trigger-open { background: rgb(var(--color-primary-rgb) / 0.1); color: var(--color-primary); }

.action-menu {
    position: fixed;
    z-index: 60;
    min-width: 200px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 12px;
    box-shadow: var(--shadow-md);
    padding: 4px;
    display: flex; flex-direction: column;
}
.action-item {
    display: flex; align-items: center; gap: 8px;
    padding: 8px 10px; border-radius: 8px;
    font-size: 12px; color: var(--text-body);
    background: transparent; border: 0; cursor: pointer; text-align: left;
    transition: background 0.12s ease, color 0.12s ease;
}
.action-item:hover { background: var(--bg-muted); color: var(--text-heading); }
.action-item-info { color: var(--color-info); }
.action-item-info:hover { background: rgb(var(--color-info-rgb) / 0.08); }
</style>
