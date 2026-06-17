<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-(--text-heading)">Leave allocations</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        Per-employee, per-year balance ledger. Used /
                        pending counters are owned by the leave engine;
                        the allocated total is the only manually-adjustable
                        column.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <select v-model.number="year" class="form-control text-xs w-28 font-mono">
                        <option v-for="y in yearOptions" :key="y" :value="y">{{ y }}</option>
                    </select>
                    <NuxtLink to="/settings/apps/hrm?tab=leave-types" class="btn btn-ghost text-xs">
                        <i class="ti ti-list" /> Leave types
                    </NuxtLink>
                </div>
            </header>

            <div class="grid grid-cols-12 gap-4">
                <!-- Left rail: employee picker -->
                <aside class="col-span-12 lg:col-span-4 xl:col-span-3 space-y-3">
                    <div class="relative">
                        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
                        <input v-model="employeeSearch" type="search" placeholder="Search employees..."
                            class="form-control pl-9 text-xs" />
                    </div>

                    <div class="glass-card rounded-2xl overflow-hidden">
                        <div v-if="loadingEmployees" class="py-10 text-center text-xxs text-(--text-muted)">
                            Loading employees...
                        </div>
                        <ul v-else-if="filteredEmployees.length > 0" class="divide-y divide-(--border-color) max-h-[68vh] overflow-y-auto">
                            <li v-for="e in filteredEmployees" :key="e.id">
                                <button type="button" class="w-full text-left px-3 py-2.5 hover:bg-(--bg-muted) transition-colors flex items-center gap-2"
                                    :class="{ 'bg-(--color-primary-subtle) text-(--color-primary)': selectedEmployeeId === e.id }"
                                    @click="selectEmployee(e.id)">
                                    <span class="w-7 h-7 rounded-full bg-(--bg-muted) text-(--text-heading) flex items-center justify-center text-xxs font-bold">
                                        {{ initials(e.fullName) }}
                                    </span>
                                    <span class="min-w-0">
                                        <span class="block text-xs font-semibold text-(--text-heading) truncate">{{ e.fullName }}</span>
                                        <span class="block text-xxs font-mono text-(--text-muted)">{{ e.employeeId }}</span>
                                    </span>
                                </button>
                            </li>
                        </ul>
                        <p v-else class="py-8 text-center text-xxs text-(--text-muted)">
                            No employees match.
                        </p>
                    </div>
                </aside>

                <!-- Right pane: balance grid + KPI strip -->
                <section class="col-span-12 lg:col-span-8 xl:col-span-9 space-y-4">
                    <div v-if="!selectedEmployeeId" class="glass-card rounded-2xl py-20 text-center">
                        <i class="ti ti-arrow-left text-4xl text-(--text-muted)" />
                        <h4 class="text-sm font-semibold text-(--text-heading) mt-3">Pick an employee</h4>
                        <p class="text-xs text-(--text-muted) mt-1">
                            Select someone from the list to view their {{ year }} allocations.
                        </p>
                    </div>

                    <template v-else>
                        <!-- KPI strip -->
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div class="glass-card rounded-2xl p-3">
                                <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Allocated</p>
                                <p class="text-lg font-mono font-semibold text-(--text-heading) mt-1">{{ totalAllocated.toFixed(1) }} d</p>
                            </div>
                            <div class="glass-card rounded-2xl p-3">
                                <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Used</p>
                                <p class="text-lg font-mono font-semibold text-(--color-success) mt-1">{{ totalUsed.toFixed(1) }} d</p>
                            </div>
                            <div class="glass-card rounded-2xl p-3">
                                <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Pending</p>
                                <p class="text-lg font-mono font-semibold text-(--color-warning) mt-1">{{ totalPending.toFixed(1) }} d</p>
                            </div>
                            <div class="glass-card rounded-2xl p-3">
                                <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Remaining</p>
                                <p class="text-lg font-mono font-semibold text-(--color-primary) mt-1">{{ totalRemaining.toFixed(1) }} d</p>
                            </div>
                        </div>

                        <div class="glass-card rounded-2xl p-4">
                            <header class="flex items-center justify-between mb-3">
                                <h3 class="text-sm font-semibold text-(--text-heading)">{{ year }} balance sheet</h3>
                                <span class="text-xxs text-(--text-muted)">
                                    Used + Pending updates automatically as leave moves through approval.
                                </span>
                            </header>

                            <div v-if="loadingBalance" class="py-10 text-center text-xxs text-(--text-muted)">
                                Loading balances...
                            </div>

                            <table v-else class="w-full text-xs">
                                <thead>
                                    <tr class="text-left text-xxs uppercase tracking-widest font-bold text-(--text-muted)">
                                        <th class="py-2 pr-3">Leave type</th>
                                        <th class="py-2 px-2 text-right">Allocated</th>
                                        <th class="py-2 px-2 text-right">Used</th>
                                        <th class="py-2 px-2 text-right">Pending</th>
                                        <th class="py-2 px-2 text-right">Remaining</th>
                                        <th class="py-2 pl-2 text-right"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-(--border-color)">
                                    <tr v-for="row in balanceRows" :key="row.leaveTypeId">
                                        <td class="py-2 pr-3">
                                            <div class="flex items-center gap-2">
                                                <span class="font-semibold text-(--text-heading)">{{ row.name }}</span>
                                                <span v-if="row.code" class="text-xxs font-mono px-1.5 py-0.5 rounded bg-(--bg-muted) text-(--text-muted)">
                                                    {{ row.code }}
                                                </span>
                                                <span v-if="!row.isPaid" class="state-chip badge-soft-warning text-[9px]">Unpaid</span>
                                                <span v-if="row.isAccrued" class="state-chip badge-soft-info text-[9px]">Accrued</span>
                                            </div>
                                        </td>
                                        <td class="py-2 px-2 text-right font-mono">{{ row.allocated.toFixed(1) }}</td>
                                        <td class="py-2 px-2 text-right font-mono text-(--color-success)">{{ row.used.toFixed(1) }}</td>
                                        <td class="py-2 px-2 text-right font-mono text-(--color-warning)">{{ row.pending.toFixed(1) }}</td>
                                        <td class="py-2 px-2 text-right font-mono font-semibold text-(--color-primary)">{{ row.remaining.toFixed(1) }}</td>
                                        <td class="py-2 pl-2 text-right">
                                            <button v-if="canAdjust" type="button" class="btn btn-ghost text-xxs"
                                                @click="openAdjustModal(row)">
                                                <i class="ti ti-edit" /> Adjust
                                            </button>
                                        </td>
                                    </tr>
                                    <tr v-if="balanceRows.length === 0">
                                        <td colspan="6" class="py-8 text-center text-xxs text-(--text-muted)">
                                            No leave types configured.
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </template>
                </section>
            </div>

            <!-- Adjust drawer -->
            <div v-if="adjustModal.open"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4"
                @click.self="closeAdjustModal">
                <div class="glass-card rounded-2xl w-full max-w-md p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                    <header class="flex items-center justify-between mb-4">
                        <div>
                            <h3 class="text-base font-semibold text-(--text-heading)">Adjust allocation</h3>
                            <p class="text-xxs text-(--text-muted) mt-1">
                                {{ adjustModal.row?.name }} - {{ year }}
                            </p>
                        </div>
                        <button class="topbar-btn" @click="closeAdjustModal"><i class="ti ti-x" /></button>
                    </header>

                    <form class="space-y-4" @submit.prevent="submitAdjust">
                        <div>
                            <label class="form-label">New allocated days</label>
                            <input v-model.number="adjustModal.allocatedDays" type="number" min="0" step="0.5" required
                                class="form-control font-mono" />
                            <p class="text-xxs text-(--text-muted) mt-1">
                                Current: <span class="font-mono">{{ adjustModal.row?.allocated.toFixed(1) }}</span> .
                                Used + pending: <span class="font-mono">{{ ((adjustModal.row?.used ?? 0) + (adjustModal.row?.pending ?? 0)).toFixed(1) }}</span>
                            </p>
                        </div>
                        <div>
                            <label class="form-label">Note (audit trail)</label>
                            <textarea v-model="adjustModal.note" rows="3" maxlength="500" class="form-control"
                                placeholder="e.g. Carryover from 2025, signing bonus, etc." />
                        </div>
                        <div v-if="adjustModal.error" class="text-xs text-(--color-danger) bg-(--color-danger-subtle) px-3 py-2 rounded">
                            {{ adjustModal.error }}
                        </div>
                        <footer class="pt-4 border-t border-(--border-color) flex justify-end gap-2">
                            <button type="button" class="btn btn-ghost text-xs" @click="closeAdjustModal">Cancel</button>
                            <button type="submit" class="btn btn-primary text-xs" :disabled="adjustModal.saving">
                                <i class="ti ti-device-floppy" />
                                {{ adjustModal.saving ? 'Saving...' : 'Save adjustment' }}
                            </button>
                        </footer>
                    </form>
                </div>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import { useLeaveAllocations, type BalanceRow } from '~/composables/useLeaveAllocations'

interface EmployeeLite {
    id: string
    employeeId: string
    fullName: string
}
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

const api = useApi()
const authStore = useAuthStore()
const toast = useToast()
const allocApi = useLeaveAllocations()

const canAdjust = computed(() => authStore.hasPermission('hrm.leave_allocation.write'))

const year = ref(new Date().getFullYear())
const yearOptions = computed(() => {
    const cur = new Date().getFullYear()
    return [cur - 1, cur, cur + 1]
})

const employees = ref<EmployeeLite[]>([])
const loadingEmployees = ref(false)
const employeeSearch = ref('')

const selectedEmployeeId = ref<string | null>(null)
const balanceRows = ref<BalanceRow[]>([])
const loadingBalance = ref(false)

const filteredEmployees = computed(() => {
    const term = employeeSearch.value.trim().toLowerCase()
    if (!term) return employees.value
    return employees.value.filter(e =>
        e.fullName.toLowerCase().includes(term)
        || e.employeeId.toLowerCase().includes(term)
    )
})

const totalAllocated = computed(() => balanceRows.value.reduce((s, r) => s + r.allocated, 0))
const totalUsed      = computed(() => balanceRows.value.reduce((s, r) => s + r.used, 0))
const totalPending   = computed(() => balanceRows.value.reduce((s, r) => s + r.pending, 0))
const totalRemaining = computed(() => balanceRows.value.reduce((s, r) => s + r.remaining, 0))

const initials = (name: string) =>
    name.split(/\s+/).filter(Boolean).slice(0, 2).map(p => p[0]!.toUpperCase()).join('')

const loadEmployees = async () => {
    loadingEmployees.value = true
    try {
        const res = await api.get<Paginated<EmployeeLite>>('/employees?limit=200')
        employees.value = res.data
    } catch (err) {
        toast.error('Failed to load employees.', err instanceof Error ? err.message : undefined)
    } finally {
        loadingEmployees.value = false
    }
}

const loadBalance = async () => {
    if (!selectedEmployeeId.value) {
        balanceRows.value = []
        return
    }
    loadingBalance.value = true
    try {
        const res = await allocApi.balanceSheet(selectedEmployeeId.value, year.value)
        balanceRows.value = res.data
    } catch (err: any) {
        toast.error('Failed to load balance sheet.', err?.data?.message)
        balanceRows.value = []
    } finally {
        loadingBalance.value = false
    }
}

const selectEmployee = (id: string) => {
    selectedEmployeeId.value = id
    loadBalance()
}

watch(year, () => { if (selectedEmployeeId.value) loadBalance() })

const adjustModal = reactive({
    open: false,
    row: null as BalanceRow | null,
    allocatedDays: 0,
    note: '',
    saving: false,
    error: '' as string | null | '',
})

const openAdjustModal = (row: BalanceRow) => {
    adjustModal.open = true
    adjustModal.row = row
    adjustModal.allocatedDays = row.allocated
    adjustModal.note = ''
    adjustModal.error = ''
}

const closeAdjustModal = () => {
    adjustModal.open = false
    adjustModal.row = null
}

const submitAdjust = async () => {
    if (!adjustModal.row || !selectedEmployeeId.value) return
    adjustModal.saving = true
    adjustModal.error = ''
    try {
        if (adjustModal.row.allocationId) {
            await allocApi.adjust(adjustModal.row.allocationId, {
                allocatedDays: adjustModal.allocatedDays,
                note: adjustModal.note || null,
            })
        } else {
            await allocApi.create({
                employeeId:    selectedEmployeeId.value,
                leaveTypeId:   adjustModal.row.leaveTypeId,
                year:          year.value,
                allocatedDays: adjustModal.allocatedDays,
                note:          adjustModal.note || null,
            })
        }
        adjustModal.open = false
        adjustModal.row = null
        await loadBalance()
    } catch (err: any) {
        adjustModal.error = err?.data?.message || 'Failed to save adjustment.'
    } finally {
        adjustModal.saving = false
    }
}

onMounted(loadEmployees)
</script>

<style scoped>
.state-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 6px;
    border-radius: 999px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    line-height: 1;
}

.form-label {
    display: block;
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 0.375rem;
}

.topbar-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 8px;
    color: var(--text-muted); cursor: pointer;
}
.topbar-btn:hover { background: var(--bg-muted); color: var(--text-heading); }
</style>
