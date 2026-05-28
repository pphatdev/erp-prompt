<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Payroll periods</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Create monthly periods, process payslips, and lock for
                        compliance.</p>
                </div>
                <button v-if="canWrite" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />New period
                </button>
            </header>

            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span
                    class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading payroll periods...</span>
            </div>

            <div v-else-if="periods.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-cash text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No payroll periods</h4>
                <p class="text-xs text-(--text-muted) mt-1">Create your first period to start running payroll.</p>
            </div>

            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr
                                class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                <th class="px-4 py-3 font-semibold">Period</th>
                                <th class="px-4 py-3 font-semibold">Dates</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Payslips</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-color)">
                            <tr v-for="p in periods" :key="p.id" class="hover:bg-(--bg-muted) transition-colors">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="w-8 h-8 rounded-lg bg-(--color-primary-subtle) text-(--color-primary) flex items-center justify-center">
                                            <i class="ti ti-calendar text-sm" />
                                        </span>
                                        <span class="text-xs font-semibold text-(--text-heading)">{{ p.name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-xs font-mono">
                                    <div>{{ p.startDate }}</div>
                                    <div class="text-(--text-muted) text-xxs">→ {{ p.endDate }}</div>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-right">{{ p.payslipCount ?? 0 }}</td>
                                <td class="px-4 py-3">
                                    <Badge :variant="statusVariant(p.status)" :dot="true">{{ p.status }}</Badge>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button" class="action-trigger"
                                        :class="{ 'action-trigger-open': actionMenu.open && actionMenu.period?.id === p.id }"
                                        title="Actions" @click.stop="openActionMenu(p, $event)">
                                        <i class="ti ti-dots-vertical" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :page="pagination.page" :limit="pagination.limit" :total="pagination.total"
                    :total-pages="pagination.totalPages" @update:page="(p) => { pagination.page = p; loadPeriods() }"
                    @update:limit="(l) => { pagination.limit = l; pagination.page = 1; loadPeriods() }" />
            </section>

            <!-- Create period modal -->
            <div v-if="showCreateModal"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4"
                @click.self="closeCreateModal">
                <div class="glass-card rounded-2xl w-full max-w-md p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                    <header class="flex items-center justify-between mb-5">
                        <div>
                            <h3 class="text-base font-semibold text-(--text-heading)">New payroll period</h3>
                            <p class="text-xxs text-(--text-muted) mt-1">
                                Periods start in <span class="font-mono">draft</span>. Process from the list to generate
                                payslips.
                            </p>
                        </div>
                        <button class="topbar-btn" @click="closeCreateModal"><i class="ti ti-x" /></button>
                    </header>

                    <form class="form-grid" @submit.prevent="createPeriod">
                        <div class="form-grid-full">
                            <label class="form-label form-label-required">Name</label>
                            <input v-model="form.name" type="text" required class="form-control"
                                placeholder="May 2026" />
                            <span class="form-hint">Human-readable label shown on payslips and the period list.</span>
                        </div>

                        <div>
                            <label class="form-label form-label-required">Start date</label>
                            <input v-model="form.start_date" type="date" required class="form-control"
                                :max="form.end_date || undefined" />
                        </div>
                        <div>
                            <label class="form-label form-label-required">End date</label>
                            <input v-model="form.end_date" type="date" required class="form-control"
                                :min="form.start_date || undefined" />
                        </div>

                        <div v-if="formError" class="form-grid-full form-error">{{ formError }}</div>

                        <footer class="form-grid-full pt-4 border-t border-(--border-color) flex justify-end gap-2">
                            <button type="button" class="btn btn-ghost text-xs" :disabled="saving"
                                @click="closeCreateModal">Cancel</button>
                            <button type="submit" class="btn btn-primary text-xs" :disabled="saving || !canSubmitForm">
                                <i :class="['ti', saving ? 'ti-loader animate-spin' : 'ti-device-floppy']" />
                                {{ saving ? 'Saving...' : 'Create period' }}
                            </button>
                        </footer>
                    </form>
                </div>
            </div>

            <!-- Payslips modal -->
            <div v-if="showPayslipsModal"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div
                    class="glass-card rounded-2xl w-full max-w-3xl max-h-[80vh] flex flex-col p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                    <header class="flex items-center justify-between mb-5 shrink-0">
                        <div>
                            <h3 class="text-base font-semibold text-(--text-heading)">Payslips · {{ activePeriod?.name
                                }}</h3>
                            <p class="text-xxs text-(--text-muted) mt-1">{{ activePeriod?.startDate }} → {{
                                activePeriod?.endDate }}</p>
                        </div>
                        <button class="topbar-btn" @click="showPayslipsModal = false"><i class="ti ti-x" /></button>
                    </header>

                    <div v-if="payslipsLoading" class="py-10 text-center">
                        <span
                            class="w-6 h-6 inline-block rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                    </div>
                    <div v-else-if="payslips.length === 0" class="py-10 text-center text-xs text-(--text-muted)">
                        No payslips generated yet. Process the period to generate them.
                    </div>
                    <div v-else class="overflow-auto flex-1">
                        <table class="w-full text-left">
                            <thead class="sticky top-0 bg-(--bg-card) z-10">
                                <tr
                                    class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                    <th class="px-4 py-2 font-semibold">Employee</th>
                                    <th class="px-4 py-2 font-semibold font-mono text-right">Gross</th>
                                    <th class="px-4 py-2 font-semibold font-mono text-right">Deductions</th>
                                    <th class="px-4 py-2 font-semibold font-mono text-right">Net</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-(--border-color)">
                                <tr v-for="ps in payslips" :key="ps.id">
                                    <td class="px-4 py-2.5 text-xs">
                                        <div class="font-semibold text-(--text-heading)">{{ ps.employee?.fullName || '—'
                                            }}</div>
                                        <div class="text-xxs text-(--text-muted) font-mono">{{ ps.employee?.employeeId
                                            || '' }}</div>
                                    </td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-right">
                                        {{ ps.grossSalary != null ? formatMoney(ps.grossSalary) : '••••' }}
                                    </td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-right text-(--color-danger)">
                                        {{ ps.deductions ? `-${formatMoney(sumValues(ps.deductions))}` : '••••' }}
                                    </td>
                                    <td class="px-4 py-2.5 font-mono text-xs text-right font-semibold">
                                        {{ ps.netSalary != null ? formatMoney(ps.netSalary) : '••••' }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <footer v-if="!canReadPayroll && payslips.length"
                        class="pt-4 mt-4 border-t border-(--border-color) text-xxs text-(--text-muted)">
                        <i class="ti ti-lock mr-1" />Amounts are masked because your role lacks the <code
                            class="font-mono text-(--text-body)">hrm.payroll.read</code> permission.
                    </footer>
                </div>
            </div>

            <!-- Action dropdown -->
            <div v-if="actionMenu.open && actionMenu.period"
                class="fixed z-50 glass-card rounded-lg shadow-(--shadow-lg) bg-(--bg-card) border border-(--border-color) py-1 min-w-[180px]"
                :style="{ top: actionMenu.y + 'px', left: actionMenu.x + 'px' }" @click.stop>
                <button class="action-item" @click="actionViewPayslips">
                    <i class="ti ti-receipt-2" /> View payslips
                </button>
                <template v-if="canWrite && actionMenu.period.status === 'draft'">
                    <hr class="my-1 border-(--border-color)" />
                    <button class="action-item action-item-primary" @click="actionProcess">
                        <i class="ti ti-player-play" /> Process payroll
                    </button>
                </template>
                <template v-if="canWrite && actionMenu.period.status === 'processed'">
                    <hr class="my-1 border-(--border-color)" />
                    <button class="action-item action-item-warning" @click="actionClose">
                        <i class="ti ti-lock" /> Close period
                    </button>
                </template>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'

interface PayrollPeriod {
    id: string
    name: string
    startDate: string
    endDate: string
    status: 'draft' | 'processed' | 'closed'
    payslipCount?: number
}
interface EmployeeLite { id: string; employeeId: string; fullName: string }
interface Payslip {
    id: string
    employeeId: string
    payrollPeriodId: string
    grossSalary: number | null
    netSalary: number | null
    earnings: Record<string, number> | null
    deductions: Record<string, number> | null
    employee?: EmployeeLite
}
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

const api = useApi()
const authStore = useAuthStore()
const toast = useToast()
const canWrite = computed(() => authStore.hasPermission('hrm.payroll.write'))
const canReadPayroll = computed(() => authStore.hasPermission('hrm.payroll.read'))

const periods = ref<PayrollPeriod[]>([])
const loading = ref(false)
const pagination = reactive({ page: 1, limit: 15, total: 0, totalPages: 1 })

const showCreateModal = ref(false)
const saving = ref(false)
const formError = ref<string | null>(null)
const form = reactive({ name: '', start_date: '', end_date: '' })

const showPayslipsModal = ref(false)
const payslipsLoading = ref(false)
const payslips = ref<Payslip[]>([])
const activePeriod = ref<PayrollPeriod | null>(null)

const actionMenu = reactive({
    open: false,
    x: 0,
    y: 0,
    period: null as PayrollPeriod | null
})

const statusVariant = (s: string): 'secondary' | 'success' | 'warning' =>
    s === 'closed' ? 'warning' : s === 'processed' ? 'success' : 'secondary'

const formatMoney = (n: number) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(n)

const sumValues = (obj: Record<string, number> | null): number =>
    obj ? Object.values(obj).reduce((acc, v) => acc + (Number(v) || 0), 0) : 0

const loadPeriods = async () => {
    loading.value = true
    try {
        const res = await api.get<Paginated<PayrollPeriod>>(`/hrm/payroll-periods?page=${pagination.page}&limit=${pagination.limit}`)
        periods.value = res.data
        pagination.total = res.pagination.total
        pagination.totalPages = res.pagination.totalPages
    } catch (err) {
        console.error('Failed to load periods', err)
        periods.value = []
    } finally {
        loading.value = false
    }
}

const canSubmitForm = computed(() => {
    if (!form.name.trim() || !form.start_date || !form.end_date) return false
    return form.start_date <= form.end_date
})

const openCreateModal = () => {
    Object.assign(form, { name: '', start_date: '', end_date: '' })
    formError.value = null
    showCreateModal.value = true
}

const closeCreateModal = () => {
    if (saving.value) return
    showCreateModal.value = false
}

const createPeriod = async () => {
    if (!canSubmitForm.value || saving.value) return
    saving.value = true
    formError.value = null
    try {
        await api.post('/hrm/payroll-periods', form)
        showCreateModal.value = false
        toast.success('Period created', `${form.name} is ready to process.`)
        await loadPeriods()
    } catch (err: any) {
        formError.value = err.data?.message || 'Failed to create period.'
    } finally {
        saving.value = false
    }
}

const processPeriod = async (p: PayrollPeriod) => {
    const ok = await toast.confirm({
        title: `Process payroll for ${p.name}?`,
        description: 'A payslip will be generated for every active employee. This step cannot be re-run for the same period — review the dates before confirming.',
        confirmLabel: 'Process payroll',
        color: 'primary',
        icon: 'ti-player-play'
    })
    if (!ok) return
    try {
        await api.post(`/hrm/payroll-periods/${p.id}/process`)
        toast.success('Payroll processed', `${p.name} payslips generated.`)
        await loadPeriods()
    } catch (err: any) {
        toast.error('Failed to process payroll.', err?.data?.message)
    }
}

const closePeriod = async (p: PayrollPeriod) => {
    const ok = await toast.confirm({
        title: `Close ${p.name} for compliance?`,
        description: 'Closing locks the period — payslips become immutable and a balanced accrual journal is posted to the General Ledger. This is the final step in the payroll cycle and cannot be undone.',
        confirmLabel: 'Close period',
        color: 'warning',
        icon: 'ti-lock'
    })
    if (!ok) return
    try {
        await api.post(`/hrm/payroll-periods/${p.id}/close`)
        toast.success('Period closed', `${p.name} is locked and the journal has been posted.`)
        await loadPeriods()
    } catch (err: any) {
        toast.error('Failed to close period.', err?.data?.message)
    }
}

const viewPayslips = async (p: PayrollPeriod) => {
    activePeriod.value = p
    showPayslipsModal.value = true
    payslipsLoading.value = true
    payslips.value = []
    try {
        const res = await api.get<Paginated<Payslip>>(`/payslips?payrollPeriodId=${p.id}&limit=100`)
        payslips.value = res.data
    } catch (err) {
        console.error('Failed to load payslips', err)
    } finally {
        payslipsLoading.value = false
    }
}

const openActionMenu = (p: PayrollPeriod, ev: MouseEvent) => {
    const rect = (ev.currentTarget as HTMLElement).getBoundingClientRect()
    const menuWidth = 200
    const menuMaxHeight = 200
    const left = Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8)
    const wouldOverflow = rect.bottom + menuMaxHeight + 8 > window.innerHeight
    actionMenu.period = p
    actionMenu.x = Math.max(8, left)
    actionMenu.y = wouldOverflow ? rect.top - menuMaxHeight - 6 : rect.bottom + 6
    actionMenu.open = true
}

const closeActionMenu = () => { actionMenu.open = false; actionMenu.period = null }

const actionViewPayslips = async () => {
    const p = actionMenu.period
    closeActionMenu()
    if (p) await viewPayslips(p)
}

const actionProcess = async () => {
    const p = actionMenu.period
    closeActionMenu()
    if (p) await processPeriod(p)
}

const actionClose = async () => {
    const p = actionMenu.period
    closeActionMenu()
    if (p) await closePeriod(p)
}

onMounted(() => {
    if (import.meta.client) {
        document.addEventListener('click', closeActionMenu)
    }
    loadPeriods()
})
</script>

<style scoped>
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

.action-item-primary {
    color: var(--color-primary);
    font-weight: 600;
}

.action-item-primary:hover {
    background: var(--color-primary-subtle);
}

.action-item-warning {
    color: var(--color-warning);
}

.action-item-warning:hover {
    background: var(--color-warning-subtle, rgb(var(--color-warning-rgb, 250 173 20) / 0.1));
}
</style>
