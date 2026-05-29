<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Page header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Maintenance</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        <span v-if="logs.length">
                            {{ logs.length.toLocaleString() }} service record{{ logs.length === 1 ? '' : 's' }} —
                            track routine maintenance, repairs, and TCO.
                        </span>
                        <span v-else>Service register — log routine maintenance, repairs, and total cost of ownership.</span>
                    </p>
                </div>
                <button v-if="canWrite" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />Log service
                </button>
            </header>

            <!-- KPI strip -->
            <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <article v-for="card in kpiCards" :key="card.key"
                    class="glass-card rounded-2xl p-4 border border-(--border-color) flex items-center gap-3">
                    <div class="w-10 h-10 rounded-xl flex items-center justify-center shrink-0"
                        :class="card.tintClass">
                        <i :class="['ti', card.icon, 'text-base']" />
                    </div>
                    <div class="min-w-0">
                        <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">{{ card.label }}</p>
                        <p class="text-lg font-semibold font-mono text-(--text-heading) leading-tight">
                            {{ card.value }}
                        </p>
                    </div>
                </article>
            </section>

            <!-- Filters -->
            <section class="glass-card rounded-xl p-4">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="relative md:col-span-6">
                        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                        <input v-model="filters.search" type="search"
                            placeholder="Search service type or notes..." class="form-control pl-9" />
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
                <span class="text-xs text-(--text-muted) font-medium">Loading maintenance logs...</span>
            </div>

            <!-- Empty -->
            <div v-else-if="filtered.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-tool text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">
                    {{ logs.length === 0 ? 'No service records yet' : 'No records match' }}
                </h4>
                <p class="text-xs text-(--text-muted) mt-1">
                    {{ logs.length === 0
                        ? 'Log a service event to start tracking maintenance history.'
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
                                <th class="px-4 py-3 font-semibold">Service</th>
                                <th class="px-4 py-3 font-semibold font-mono">Date</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Mileage</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Cost</th>
                                <th class="px-4 py-3 font-semibold hidden lg:table-cell">Notes</th>
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
                                <td class="px-4 py-3 text-xs capitalize">
                                    {{ (log.serviceType || '').replace(/_/g, ' ') }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xxs text-(--text-muted)">
                                    {{ formatDate(log.serviceDate) }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-right">
                                    {{ log.mileageAtService.toLocaleString() }}<span class="text-xxs text-(--text-muted) ml-1">km</span>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-right">{{ formatMoney(log.cost) }}</td>
                                <td class="px-4 py-3 text-xs text-(--text-muted) hidden lg:table-cell truncate max-w-[240px]">
                                    {{ log.notes || '—' }}
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
                            {{ editing ? 'Edit service record' : 'Log service' }}
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
                            <label class="form-label form-label-required">Service type</label>
                            <input v-model="form.service_type" type="text" required class="form-control"
                                list="service-type-options" placeholder="oil_change" />
                            <datalist id="service-type-options">
                                <option value="oil_change" />
                                <option value="tire_rotation" />
                                <option value="brake_service" />
                                <option value="inspection" />
                                <option value="repair" />
                                <option value="battery_replacement" />
                            </datalist>
                        </div>
                        <div>
                            <label class="form-label form-label-required">Service date</label>
                            <input v-model="form.service_date" type="date" required class="form-control" />
                        </div>

                        <div>
                            <label class="form-label form-label-required">
                                Mileage at service (km)
                                <span v-if="editing" class="text-xxs text-(--text-muted) ml-1 normal-case">(immutable)</span>
                            </label>
                            <input v-model.number="form.mileage_at_service" type="number" min="0" required
                                class="form-control font-mono" :disabled="!!editing" />
                            <span v-if="!editing" class="form-hint">
                                Must be ≥ the vehicle's current mileage — the backend enforces the monotonic check.
                            </span>
                        </div>
                        <div>
                            <label class="form-label form-label-required">Cost</label>
                            <input v-model.number="form.cost" type="number" step="0.01" min="0" required
                                class="form-control font-mono" />
                        </div>

                        <div class="form-grid-full">
                            <label class="form-label">Notes</label>
                            <textarea v-model="form.notes" rows="3" class="form-control"
                                placeholder="Parts replaced, vendor, follow-up required..." />
                        </div>

                        <div v-if="formError" class="form-grid-full form-error">{{ formError }}</div>

                        <footer class="form-grid-full pt-4 border-t border-(--border-color) flex justify-end gap-2">
                            <button type="button" class="btn btn-ghost text-xs" @click="closeFormModal">Cancel</button>
                            <button type="submit" class="btn btn-primary text-xs" :disabled="saving">
                                <i class="ti ti-device-floppy" />
                                {{ saving
                                    ? 'Saving...'
                                    : (editing ? 'Save changes' : 'Log service') }}
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
                            <h3 class="text-base font-semibold text-(--text-heading) capitalize">
                                {{ (detailsLog.serviceType || '').replace(/_/g, ' ') }}
                            </h3>
                            <p class="text-xxs text-(--text-muted) mt-1 font-mono">
                                {{ vehicleFor(detailsLog)?.registrationNumber || 'Vehicle archived' }}
                            </p>
                        </div>
                        <button class="topbar-btn" @click="detailsOpen = false"><i class="ti ti-x" /></button>
                    </header>

                    <dl class="text-xs space-y-3">
                        <div class="flex justify-between gap-3">
                            <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Service date</dt>
                            <dd class="text-(--text-body) font-mono">{{ formatDate(detailsLog.serviceDate) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Mileage at service</dt>
                            <dd class="text-(--text-body) font-mono">{{ detailsLog.mileageAtService.toLocaleString() }} km</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Cost</dt>
                            <dd class="text-(--text-body) font-mono">{{ formatMoney(detailsLog.cost) }}</dd>
                        </div>
                        <div v-if="detailsLog.notes" class="pt-2 border-t border-(--border-color)">
                            <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold mb-1">Notes</dt>
                            <dd class="rounded-lg bg-(--bg-muted) p-3 text-(--text-body) whitespace-pre-wrap">{{ detailsLog.notes }}</dd>
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
import { useFleet, type MaintenanceLog, type Vehicle } from '~/composables/useFleet'
import { formatDate, formatDateTime } from '~/composables/useDateFormat'
import { useCountUp } from '~/composables/useCountUp'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import Pagination from '~/components/Pagination.vue'

const fleet = useFleet()
const authStore = useAuthStore()
const toast = useToast()
const canWrite = computed(() => authStore.hasPermission('fleet.maintenance.write'))
const canDelete = computed(() => authStore.hasPermission('fleet.maintenance.delete'))

const logs = ref<MaintenanceLog[]>([])
const vehicles = ref<Vehicle[]>([])
const loading = ref(false)

// Same batched-fetch trick as vehicles.vue — load up to 500 logs so KPIs and
// the client-side join with vehicles are accurate. Switches to a server-side
// aggregation endpoint once a tenant exceeds that window.
const PAGE_BATCH = 500

const filters = reactive({ search: '', vehicleId: '' })
const pagination = reactive({ page: 1, limit: 15 })

const showFormModal = ref(false)
const editing = ref<MaintenanceLog | null>(null)
const saving = ref(false)
const formError = ref<string | null>(null)
const todayISO = new Date().toISOString().slice(0, 10)
const form = reactive({
    vehicle_id: '',
    service_type: '',
    service_date: todayISO,
    mileage_at_service: 0,
    cost: 0,
    notes: '',
})

const detailsOpen = ref(false)
const detailsLog = ref<MaintenanceLog | null>(null)

const actionMenu = reactive({
    open: false,
    x: 0,
    y: 0,
    log: null as MaintenanceLog | null,
})

const formatMoney = (n: number) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(n)

// Build a id→Vehicle map once per change so per-row lookups are O(1).
const vehicleMap = computed(() => {
    const m = new Map<string, Vehicle>()
    for (const v of vehicles.value) m.set(v.id, v)
    return m
})
const vehicleFor = (log: MaintenanceLog) => vehicleMap.value.get(log.vehicleId) ?? null

const filtered = computed(() => {
    const q = filters.search.trim().toLowerCase()
    return logs.value.filter(log => {
        if (filters.vehicleId && log.vehicleId !== filters.vehicleId) return false
        if (!q) return true
        return [log.serviceType, log.notes ?? '']
            .some(s => s.toLowerCase().includes(q))
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

// KPIs derived from the full loaded set (not the filtered view) — counts are
// "across the whole fleet", which is what users expect a header strip to show.
const kpis = computed(() => {
    const now = new Date()
    const monthKey = `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
    let thisMonth = 0
    let totalCost = 0
    for (const log of logs.value) {
        if (log.serviceDate?.startsWith(monthKey)) thisMonth++
        totalCost += Number(log.cost) || 0
    }
    return {
        total: logs.value.length,
        thisMonth,
        totalCost,
        avgCost: logs.value.length ? totalCost / logs.value.length : 0,
    }
})

// Animated counters — integers for the counts, decimals:2 for the currency
// figures so the cents tick smoothly instead of snapping mid-animation.
const totalCount = useCountUp(() => kpis.value.total)
const thisMonthCount = useCountUp(() => kpis.value.thisMonth)
const totalCostCount = useCountUp(() => kpis.value.totalCost, { decimals: 2 })
const avgCostCount = useCountUp(() => kpis.value.avgCost, { decimals: 2 })

const kpiCards = computed(() => [
    { key: 'total',      label: 'Total services',  value: totalCount.value.toLocaleString(),     icon: 'ti-tool',           tintClass: 'bg-(--color-primary-subtle) text-(--color-primary)' },
    { key: 'thisMonth',  label: 'This month',      value: thisMonthCount.value.toLocaleString(), icon: 'ti-calendar-event', tintClass: 'bg-(--color-info-subtle) text-(--color-info)' },
    { key: 'totalCost',  label: 'Total cost',      value: formatMoney(totalCostCount.value),     icon: 'ti-cash',           tintClass: 'bg-(--color-warning-subtle) text-(--color-warning)' },
    { key: 'avgCost',    label: 'Avg per service', value: formatMoney(avgCostCount.value),       icon: 'ti-chart-bar',      tintClass: 'bg-(--color-success-subtle) text-(--color-success)' },
])

const loadAll = async () => {
    loading.value = true
    try {
        const [logsRes, vehiclesRes] = await Promise.all([
            fleet.getMaintenanceLogs({ limit: PAGE_BATCH }),
            fleet.getVehicles({ limit: PAGE_BATCH }),
        ])
        logs.value = logsRes.data
        vehicles.value = vehiclesRes.data
    } catch (err) {
        console.error('Failed to load maintenance logs', err)
        logs.value = []
        vehicles.value = []
    } finally {
        loading.value = false
    }
}

const resetForm = () => {
    Object.assign(form, {
        vehicle_id: filters.vehicleId || '',
        service_type: '',
        service_date: todayISO,
        mileage_at_service: 0,
        cost: 0,
        notes: '',
    })
    formError.value = null
}

const openCreateModal = () => {
    editing.value = null
    resetForm()
    showFormModal.value = true
}

const openEditModal = (log: MaintenanceLog) => {
    editing.value = log
    Object.assign(form, {
        vehicle_id: log.vehicleId,
        service_type: log.serviceType,
        service_date: log.serviceDate ?? todayISO,
        mileage_at_service: log.mileageAtService,
        cost: Number(log.cost) || 0,
        notes: log.notes ?? '',
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
            // Backend rejects vehicle_id + mileage_at_service on PUT — they
            // identify the recorded fact. Only send the editable columns.
            const payload: Record<string, unknown> = {
                service_type: form.service_type.trim(),
                service_date: form.service_date,
                cost: form.cost,
                notes: form.notes || null,
            }
            await fleet.updateMaintenanceLog(editing.value.id, payload)
        } else {
            await fleet.createMaintenanceLog({
                vehicle_id: form.vehicle_id,
                service_type: form.service_type.trim(),
                service_date: form.service_date,
                mileage_at_service: form.mileage_at_service,
                cost: form.cost,
                notes: form.notes || null,
            })
        }
        const wasEditing = !!editing.value
        showFormModal.value = false
        editing.value = null
        await loadAll()
        toast.success(
            wasEditing ? 'Service record updated.' : 'Service logged.',
            wasEditing ? 'Changes saved.' : 'The maintenance record is now in the history.'
        )
    } catch (err: any) {
        const errors = err?.data?.errors
        if (errors && typeof errors === 'object') {
            formError.value = (Object.values(errors).flat()[0] as string) || 'Validation failed.'
        } else {
            formError.value = err?.data?.message || 'Failed to save service record.'
        }
    } finally {
        saving.value = false
    }
}

const deleteLog = async (log: MaintenanceLog) => {
    const veh = vehicleFor(log)
    const ok = await toast.confirm({
        title: `Delete this service record?`,
        description: `Removes the ${log.serviceType.replace(/_/g, ' ')} entry${veh ? ` for ${veh.registrationNumber}` : ''} on ${formatDate(log.serviceDate)}. The vehicle's current mileage stays where it is — we don't roll it back.`,
        confirmLabel: 'Delete',
        color: 'danger',
        icon: 'ti-trash',
    })
    if (!ok) return
    try {
        await fleet.deleteMaintenanceLog(log.id)
        await loadAll()
        toast.success('Service record deleted.', 'Maintenance history updated.')
    } catch (err: any) {
        toast.error('Failed to delete record.', err?.data?.message)
    }
}

const openActionMenu = (log: MaintenanceLog, ev: MouseEvent) => {
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
