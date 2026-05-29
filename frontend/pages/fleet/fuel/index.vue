<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Page header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Fuel Logs</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        <span v-if="logs.length">
                            {{ logs.length.toLocaleString() }} fill record{{ logs.length === 1 ? '' : 's' }} —
                            track fuel cost, consumption, and per-driver fills.
                        </span>
                        <span v-else>Fuel register — log fills, track consumption, and watch fleet cost trends.</span>
                    </p>
                </div>
                <button v-if="canWrite" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />Log fill
                </button>
            </header>

            <!-- KPI strip -->
            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-4 gap-4">
                <article v-for="card in kpiCards" :key="card.key"
                    class="glass-card rounded-2xl p-4 space-y-2 col-span-1">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">{{ card.label }}</span>
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center"
                            :class="`badge-soft-${card.tone}`">
                            <i :class="['ti', card.icon, 'text-sm']" />
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ card.value }}</p>
                    <p class="text-xxs text-(--text-muted)">{{ card.subtext }}</p>
                </article>
            </section>

            <!-- Filters -->
            <section class="glass-card rounded-xl p-4">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="relative md:col-span-6">
                        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                        <input v-model="filters.search" type="search"
                            placeholder="Search by registration, make, or driver..." class="form-control pl-9" />
                    </div>
                    <div class="relative md:col-span-6">
                        <i class="ti ti-truck absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
                        <select v-model="filters.vehicleId" class="form-control pl-9 appearance-none">
                            <option :value="''">All vehicles</option>
                            <option v-for="v in vehicles" :key="v.id" :value="v.id">
                                {{ v.registrationNumber }} — {{ v.make }} {{ v.model }}
                            </option>
                        </select>
                    </div>
                </div>
            </section>

            <!-- Loading -->
            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span
                    class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading fuel logs...</span>
            </div>

            <!-- Empty -->
            <div v-else-if="filtered.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-gas-station text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">
                    {{ logs.length === 0 ? 'No fuel records yet' : 'No records match' }}
                </h4>
                <p class="text-xs text-(--text-muted) mt-1">
                    {{ logs.length === 0
                        ? 'Log a fill to start tracking fleet fuel cost and consumption.'
                        : 'Adjust your filters or clear the search.' }}
                </p>
            </div>

            <!-- Table -->
            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                <th class="px-4 py-3 font-semibold">Vehicle</th>
                                <th class="px-4 py-3 font-semibold font-mono">Fill date</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Liters</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Mileage</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Cost</th>
                                <th class="px-4 py-3 font-semibold hidden lg:table-cell">Driver</th>
                                <th class="px-4 py-3 font-semibold text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-color)">
                            <tr v-for="log in paged" :key="log.id" class="hover:bg-(--bg-muted) transition-colors">
                                <td class="px-4 py-3">
                                    <div class="text-xs font-semibold text-(--text-heading)">
                                        {{ vehicleFor(log)?.registrationNumber || '—' }}
                                    </div>
                                    <div class="text-xxs text-(--text-muted)">
                                        {{ vehicleFor(log)
                                            ? `${vehicleFor(log)!.make} ${vehicleFor(log)!.model}`
                                            : 'Vehicle archived' }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-mono text-xxs text-(--text-muted)">
                                    {{ formatDate(log.fillDate) }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-right">
                                    {{ log.liters.toLocaleString(undefined, { minimumFractionDigits: 1, maximumFractionDigits: 2 }) }}<span class="text-xxs text-(--text-muted) ml-1">L</span>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-right">
                                    {{ log.mileageAtFill.toLocaleString() }}<span class="text-xxs text-(--text-muted) ml-1">km</span>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-right">{{ formatMoney(log.cost) }}</td>
                                <td class="px-4 py-3 text-xs hidden lg:table-cell">
                                    <span v-if="driverFor(log)" class="text-(--text-body)">
                                        {{ driverFor(log)!.fullName }}
                                        <span class="text-xxs text-(--text-muted) font-mono ml-1">{{ driverFor(log)!.employeeId }}</span>
                                    </span>
                                    <span v-else class="text-(--text-muted)">—</span>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button class="action-trigger"
                                        :class="{ 'action-trigger-open': actionMenu.open && actionMenu.log?.id === log.id }"
                                        title="Actions" @click.stop="openActionMenu(log, $event)">
                                        <i class="ti ti-dots-vertical" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :page="pagination.page" :limit="pagination.limit" :total="filtered.length"
                    :total-pages="totalPages"
                    @update:page="(p) => { pagination.page = p }"
                    @update:limit="(l) => { pagination.limit = l; pagination.page = 1 }" />
            </section>

            <!-- Form modal (create + edit) -->
            <div v-if="showFormModal"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="glass-card rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                    <header class="flex items-center justify-between mb-5">
                        <h3 class="text-base font-semibold text-(--text-heading)">
                            {{ editing ? 'Edit fuel record' : 'Log fill' }}
                        </h3>
                        <button class="topbar-btn" @click="closeFormModal"><i class="ti ti-x" /></button>
                    </header>

                    <form class="form-grid" @submit.prevent="saveLog">
                        <div class="form-grid-full">
                            <label class="form-label form-label-required">
                                Vehicle
                                <span v-if="editing" class="text-xxs text-(--text-muted) ml-1 normal-case">(immutable)</span>
                            </label>
                            <select v-model="form.vehicle_id" required class="form-control" :disabled="!!editing">
                                <option value="" disabled>Select vehicle...</option>
                                <option v-for="v in vehicles" :key="v.id" :value="v.id">
                                    {{ v.registrationNumber }} — {{ v.make }} {{ v.model }}
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label form-label-required">Fill date</label>
                            <input v-model="form.fill_date" type="date" required class="form-control" />
                        </div>
                        <div>
                            <label class="form-label">Driver</label>
                            <select v-model="form.driver_id" class="form-control">
                                <option :value="''">— Unassigned</option>
                                <option v-for="e in employees" :key="e.id" :value="e.id">
                                    {{ e.fullName }} ({{ e.employeeId }})
                                </option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label form-label-required">Liters</label>
                            <input v-model.number="form.liters" type="number" step="0.01" min="0" required
                                class="form-control font-mono" />
                        </div>
                        <div>
                            <label class="form-label form-label-required">Cost</label>
                            <input v-model.number="form.cost" type="number" step="0.01" min="0" required
                                class="form-control font-mono" />
                        </div>

                        <div class="form-grid-full">
                            <label class="form-label form-label-required">
                                Mileage at fill (km)
                                <span v-if="editing" class="text-xxs text-(--text-muted) ml-1 normal-case">(immutable)</span>
                            </label>
                            <input v-model.number="form.mileage_at_fill" type="number" min="0" required
                                class="form-control font-mono" :disabled="!!editing" />
                            <span v-if="!editing" class="form-hint">
                                Must be ≥ the vehicle's current mileage — the backend enforces the monotonic check.
                            </span>
                        </div>

                        <div v-if="formError" class="form-grid-full form-error">{{ formError }}</div>

                        <footer class="form-grid-full pt-4 border-t border-(--border-color) flex justify-end gap-2">
                            <button type="button" class="btn btn-ghost text-xs" @click="closeFormModal">Cancel</button>
                            <button type="submit" class="btn btn-primary text-xs" :disabled="saving">
                                <i class="ti ti-device-floppy" />
                                {{ saving
                                    ? 'Saving...'
                                    : (editing ? 'Save changes' : 'Log fill') }}
                            </button>
                        </footer>
                    </form>
                </div>
            </div>

            <!-- Details modal -->
            <div v-if="detailsOpen && detailsLog"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="glass-card rounded-2xl w-full max-w-lg max-h-[80vh] overflow-y-auto p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                    <header class="flex items-center justify-between mb-5">
                        <div>
                            <h3 class="text-base font-semibold text-(--text-heading)">
                                {{ vehicleFor(detailsLog)
                                    ? `${vehicleFor(detailsLog)!.make} ${vehicleFor(detailsLog)!.model}`
                                    : 'Fill record' }}
                            </h3>
                            <p class="text-xxs text-(--text-muted) mt-1 font-mono">
                                {{ vehicleFor(detailsLog)?.registrationNumber || 'Vehicle archived' }}
                            </p>
                        </div>
                        <button class="topbar-btn" @click="detailsOpen = false"><i class="ti ti-x" /></button>
                    </header>

                    <dl class="text-xs space-y-3">
                        <div class="flex justify-between gap-3">
                            <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Fill date</dt>
                            <dd class="text-(--text-body) font-mono">{{ formatDate(detailsLog.fillDate) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Liters</dt>
                            <dd class="text-(--text-body) font-mono">
                                {{ detailsLog.liters.toLocaleString(undefined, { minimumFractionDigits: 1, maximumFractionDigits: 2 }) }} L
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Cost</dt>
                            <dd class="text-(--text-body) font-mono">{{ formatMoney(detailsLog.cost) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Mileage at fill</dt>
                            <dd class="text-(--text-body) font-mono">{{ detailsLog.mileageAtFill.toLocaleString() }} km</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Driver</dt>
                            <dd class="text-(--text-body)">
                                <span v-if="driverFor(detailsLog)">
                                    {{ driverFor(detailsLog)!.fullName }}
                                    <span class="text-xxs text-(--text-muted) font-mono ml-1">{{ driverFor(detailsLog)!.employeeId }}</span>
                                </span>
                                <span v-else class="text-(--text-muted)">—</span>
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3 pt-2 border-t border-(--border-color)">
                            <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Recorded</dt>
                            <dd class="text-(--text-muted) font-mono text-xxs">{{ formatDateTime(detailsLog.createdAt) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Last updated</dt>
                            <dd class="text-(--text-muted) font-mono text-xxs">{{ formatDateTime(detailsLog.updatedAt) }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Action dropdown -->
            <div v-if="actionMenu.open && actionMenu.log"
                class="fixed z-50 glass-card rounded-lg shadow-(--shadow-lg) bg-(--bg-card) border border-(--border-color) py-1 min-w-[180px]"
                :style="{ top: actionMenu.y + 'px', left: actionMenu.x + 'px' }" @click.stop>
                <button class="action-item" @click="actionView">
                    <i class="ti ti-eye" /> View details
                </button>
                <button v-if="canWrite" class="action-item" @click="actionEdit">
                    <i class="ti ti-pencil" /> Edit
                </button>
                <template v-if="canDelete">
                    <hr class="my-1 border-(--border-color)" />
                    <button class="action-item action-item-danger" @click="actionDelete">
                        <i class="ti ti-trash" /> Delete
                    </button>
                </template>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useFleet, type FuelLog, type Vehicle } from '~/composables/useFleet'
import { useApi } from '~/composables/useApi'
import { formatDate, formatDateTime } from '~/composables/useDateFormat'
import { useCountUp } from '~/composables/useCountUp'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import Pagination from '~/components/Pagination.vue'

interface EmployeeLite { id: string; employeeId: string; fullName: string }
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

const fleet = useFleet()
const api = useApi()
const authStore = useAuthStore()
const toast = useToast()
const canWrite = computed(() => authStore.hasPermission('fleet.fuel.write'))
const canDelete = computed(() => authStore.hasPermission('fleet.fuel.delete'))

const logs = ref<FuelLog[]>([])
const vehicles = ref<Vehicle[]>([])
const employees = ref<EmployeeLite[]>([])
const loading = ref(false)

// Same batched-fetch trick as maintenance/vehicles — load up to 500 so KPIs
// + client-side filter/paginate stay accurate. Switches to a server-side
// aggregation endpoint once tenants exceed that window.
const PAGE_BATCH = 500

const filters = reactive({ search: '', vehicleId: '' })
const pagination = reactive({ page: 1, limit: 15 })

const showFormModal = ref(false)
const editing = ref<FuelLog | null>(null)
const saving = ref(false)
const formError = ref<string | null>(null)
const todayISO = new Date().toISOString().slice(0, 10)
const form = reactive({
    vehicle_id: '',
    fill_date: todayISO,
    liters: 0,
    cost: 0,
    mileage_at_fill: 0,
    driver_id: '',
})

const detailsOpen = ref(false)
const detailsLog = ref<FuelLog | null>(null)

const actionMenu = reactive({
    open: false,
    x: 0,
    y: 0,
    log: null as FuelLog | null,
})

const formatMoney = (n: number) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(n)

// id→entity maps for O(1) per-row lookups during render.
const vehicleMap = computed(() => {
    const m = new Map<string, Vehicle>()
    for (const v of vehicles.value) m.set(v.id, v)
    return m
})
const employeeMap = computed(() => {
    const m = new Map<string, EmployeeLite>()
    for (const e of employees.value) m.set(e.id, e)
    return m
})
const vehicleFor = (log: FuelLog) => vehicleMap.value.get(log.vehicleId) ?? null
const driverFor = (log: FuelLog) =>
    log.driverId ? (employeeMap.value.get(log.driverId) ?? null) : null

const filtered = computed(() => {
    const q = filters.search.trim().toLowerCase()
    return logs.value.filter(log => {
        if (filters.vehicleId && log.vehicleId !== filters.vehicleId) return false
        if (!q) return true
        const veh = vehicleFor(log)
        const driver = driverFor(log)
        const haystacks = [
            veh?.registrationNumber ?? '',
            veh?.make ?? '',
            veh?.model ?? '',
            driver?.fullName ?? '',
            driver?.employeeId ?? '',
        ]
        return haystacks.some(s => s.toLowerCase().includes(q))
    })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / pagination.limit)))

const paged = computed(() => {
    const start = (pagination.page - 1) * pagination.limit
    return filtered.value.slice(start, start + pagination.limit)
})

watch([() => filters.search, () => filters.vehicleId], () => {
    pagination.page = 1
})

// KPIs derived from the full loaded set (not the filtered view) — header
// strip represents the whole fleet, not the user's current filter.
const kpis = computed(() => {
    const now = new Date()
    const monthKey = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
    let thisMonth = 0
    let totalCost = 0
    let totalLiters = 0
    for (const log of logs.value) {
        if (log.fillDate?.startsWith(monthKey)) thisMonth++
        totalCost += Number(log.cost) || 0
        totalLiters += Number(log.liters) || 0
    }
    return {
        total: logs.value.length,
        thisMonth,
        totalCost,
        totalLiters,
    }
})

// Animated counters mirroring the maintenance page — integers for counts,
// decimals:2 for currency and liters so the trailing digits roll smoothly.
const totalCount = useCountUp(() => kpis.value.total)
const thisMonthCount = useCountUp(() => kpis.value.thisMonth)
const totalCostCount = useCountUp(() => kpis.value.totalCost, { decimals: 2 })
const totalLitersCount = useCountUp(() => kpis.value.totalLiters, { decimals: 2 })

const kpiCards = computed(() => [
    { key: 'total',       label: 'Total fills',  value: totalCount.value.toLocaleString(),                                                                                  icon: 'ti-gas-station',    tone: 'primary', subtext: 'Lifetime records' },
    { key: 'thisMonth',   label: 'This month',   value: thisMonthCount.value.toLocaleString(),                                                                              icon: 'ti-calendar-event', tone: 'info',    subtext: 'Recent activity' },
    { key: 'totalCost',   label: 'Total cost',   value: formatMoney(totalCostCount.value),                                                                                  icon: 'ti-cash',           tone: 'warning', subtext: 'Lifetime fuel spend' },
    { key: 'totalLiters', label: 'Total liters', value: `${totalLitersCount.value.toLocaleString(undefined, { minimumFractionDigits: 1, maximumFractionDigits: 2 })} L`,    icon: 'ti-droplet',        tone: 'success', subtext: 'Pumped to date' },
])

const loadAll = async () => {
    loading.value = true
    try {
        const [logsRes, vehiclesRes, employeesRes] = await Promise.all([
            fleet.getFuelLogs({ limit: PAGE_BATCH }),
            fleet.getVehicles({ limit: PAGE_BATCH }),
            // Employees aren't part of useFleet — fetched directly so we can
            // show driver names in the table and the create modal's select.
            api.get<Paginated<EmployeeLite>>('/employees?limit=200').catch(() => ({ data: [] as EmployeeLite[] })),
        ])
        logs.value = logsRes.data
        vehicles.value = vehiclesRes.data
        employees.value = (employeesRes as Paginated<EmployeeLite>).data ?? []
    } catch (err) {
        console.error('Failed to load fuel logs', err)
        logs.value = []
        vehicles.value = []
        employees.value = []
    } finally {
        loading.value = false
    }
}

const resetForm = () => {
    Object.assign(form, {
        vehicle_id: filters.vehicleId || '',
        fill_date: todayISO,
        liters: 0,
        cost: 0,
        mileage_at_fill: 0,
        driver_id: '',
    })
    formError.value = null
}

const openCreateModal = () => {
    editing.value = null
    resetForm()
    showFormModal.value = true
}

const openEditModal = (log: FuelLog) => {
    editing.value = log
    Object.assign(form, {
        vehicle_id: log.vehicleId,
        fill_date: log.fillDate ?? todayISO,
        liters: Number(log.liters) || 0,
        cost: Number(log.cost) || 0,
        mileage_at_fill: log.mileageAtFill,
        driver_id: log.driverId ?? '',
    })
    formError.value = null
    showFormModal.value = true
}

const closeFormModal = () => {
    if (saving.value) return
    showFormModal.value = false
    editing.value = null
}

const saveLog = async () => {
    saving.value = true
    formError.value = null
    try {
        if (editing.value) {
            // Backend rejects vehicle_id + mileage_at_fill on PUT — both
            // identify the recorded fact. Only send the editable columns.
            const payload: Record<string, unknown> = {
                fill_date: form.fill_date,
                liters: form.liters,
                cost: form.cost,
                driver_id: form.driver_id || null,
            }
            await fleet.updateFuelLog(editing.value.id, payload)
        } else {
            await fleet.createFuelLog({
                vehicle_id: form.vehicle_id,
                fill_date: form.fill_date,
                liters: form.liters,
                cost: form.cost,
                mileage_at_fill: form.mileage_at_fill,
                driver_id: form.driver_id || null,
            })
        }
        const wasEditing = !!editing.value
        showFormModal.value = false
        editing.value = null
        await loadAll()
        toast.success(
            wasEditing ? 'Fuel record updated.' : 'Fill logged.',
            wasEditing ? 'Changes saved.' : 'The fuel record is now in the history.'
        )
    } catch (err: any) {
        const errors = err?.data?.errors
        if (errors && typeof errors === 'object') {
            formError.value = (Object.values(errors).flat()[0] as string) || 'Validation failed.'
        } else {
            formError.value = err?.data?.message || 'Failed to save fuel record.'
        }
    } finally {
        saving.value = false
    }
}

const deleteLog = async (log: FuelLog) => {
    const veh = vehicleFor(log)
    const ok = await toast.confirm({
        title: 'Delete this fuel record?',
        description: `Removes the fill${veh ? ` for ${veh.registrationNumber}` : ''} on ${formatDate(log.fillDate)}. The vehicle's current mileage stays where it is — we don't roll it back.`,
        confirmLabel: 'Delete',
        color: 'danger',
        icon: 'ti-trash',
    })
    if (!ok) return
    try {
        await fleet.deleteFuelLog(log.id)
        await loadAll()
        toast.success('Fuel record deleted.', 'Fuel history updated.')
    } catch (err: any) {
        toast.error('Failed to delete record.', err?.data?.message)
    }
}

const openActionMenu = (log: FuelLog, ev: MouseEvent) => {
    const rect = (ev.currentTarget as HTMLElement).getBoundingClientRect()
    const menuWidth = 180
    const menuMaxHeight = 160
    const left = Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8)
    const wouldOverflow = rect.bottom + menuMaxHeight + 8 > window.innerHeight
    actionMenu.log = log
    actionMenu.x = Math.max(8, left)
    actionMenu.y = wouldOverflow ? rect.top - menuMaxHeight - 6 : rect.bottom + 6
    actionMenu.open = true
}

const closeActionMenu = () => {
    actionMenu.open = false
    actionMenu.log = null
}

const actionView = () => {
    const log = actionMenu.log
    closeActionMenu()
    if (log) {
        detailsLog.value = log
        detailsOpen.value = true
    }
}

const actionEdit = () => {
    const log = actionMenu.log
    closeActionMenu()
    if (log) openEditModal(log)
}

const actionDelete = () => {
    const log = actionMenu.log
    closeActionMenu()
    if (log) deleteLog(log)
}

onMounted(() => {
    if (import.meta.client) {
        document.addEventListener('click', closeActionMenu)
    }
    loadAll()
})

onBeforeUnmount(() => {
    if (import.meta.client) {
        document.removeEventListener('click', closeActionMenu)
    }
})
</script>

<style scoped>
.topbar-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    color: var(--text-muted);
    cursor: pointer;
}

.topbar-btn:hover {
    background: var(--bg-muted);
    color: var(--text-heading);
}

.action-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    color: var(--text-muted);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}

.action-trigger:hover {
    background: var(--bg-muted);
    color: var(--text-heading);
}

.action-trigger-open {
    background: var(--bg-muted);
    color: var(--color-primary);
}

.action-item {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
    color: var(--text-heading);
    text-align: left;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}

.action-item:hover {
    background: var(--bg-muted);
}

.action-item-danger {
    color: var(--color-danger);
}

.action-item-danger:hover {
    background: var(--color-danger-subtle);
}
</style>
