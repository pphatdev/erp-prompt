<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Cash Advances</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Money issued to employees against future expenses. Posts DR Employee Advances Receivable / CR Cash. Settle via the Advance Settlement page once expenses are reported.</p>
                </div>
                <button v-if="canWrite" type="button" class="btn btn-primary text-xs" @click="openIssueModal">
                    <i class="ti ti-plus" />Issue Advance
                </button>
            </header>

            <!-- KPIs -->
            <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Outstanding</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-warning flex items-center justify-center"><i class="ti ti-wallet text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ formatMoney(kpiOutstandingAnim) }}</p>
                    <p class="text-xxs text-(--text-muted)">{{ openCount }} open · {{ partialCount }} partial</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Issued This Month</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-info flex items-center justify-center"><i class="ti ti-arrow-down-right text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ formatMoney(kpiMonthAnim) }}</p>
                    <p class="text-xxs text-(--text-muted)">Across all employees</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Closed</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-success flex items-center justify-center"><i class="ti ti-circle-check text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiClosedAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">Fully settled advances</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Total Advances</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-primary flex items-center justify-center"><i class="ti ti-list text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiTotalAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">All-time on page</p>
                </div>
            </section>

            <!-- Status filter chips -->
            <section class="flex items-center gap-2 flex-wrap max-sm:justify-center">
                <button type="button" class="chip" :class="{ active: statusFilter === '' }" @click="setStatusFilter('')">All</button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'open' }" @click="setStatusFilter('open')">
                    <i class="ti ti-circle" /> Open
                </button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'partially_settled' }" @click="setStatusFilter('partially_settled')">
                    <i class="ti ti-progress" /> Partially Settled
                </button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'closed' }" @click="setStatusFilter('closed')">
                    <i class="ti ti-circle-check" /> Closed
                </button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'cancelled' }" @click="setStatusFilter('cancelled')">
                    <i class="ti ti-x" /> Cancelled
                </button>
            </section>

            <!-- Loading / Empty -->
            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading cash advances...</span>
            </div>
            <div v-else-if="advances.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-wallet-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No cash advances yet</h4>
                <p class="text-xs text-(--text-muted) mt-1">Issue an advance to an employee for upcoming expenses.</p>
            </div>

            <!-- Table -->
            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-(--bg-muted)/40 text-(--text-muted)">
                            <tr>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Advance #</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Employee</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Purpose</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Issued</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Status</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3">Amount</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3">Settled</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3">Outstanding</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3 w-24">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="a in advances" :key="a.id"
                                class="border-t border-(--border-color) hover:bg-(--bg-muted)/40"
                                :class="{ 'opacity-60': a.status === 'cancelled' }">
                                <td class="px-3 py-3">
                                    <div class="font-mono font-semibold"
                                        :class="a.status === 'cancelled' ? 'text-(--text-muted) line-through' : 'text-(--text-heading)'">
                                        {{ a.advanceNumber }}
                                    </div>
                                    <div v-if="a.referenceNumber" class="text-xxs text-(--text-muted) font-mono mt-0.5">Ref: {{ a.referenceNumber }}</div>
                                </td>
                                <td class="px-3 py-3">
                                    <p class="text-(--text-heading) font-semibold truncate max-w-xs">{{ a.employee?.fullName || '—' }}</p>
                                    <p v-if="a.employee?.employeeId" class="text-xxs text-(--text-muted) font-mono">{{ a.employee.employeeId }}</p>
                                </td>
                                <td class="px-3 py-3 text-(--text-body) truncate max-w-xs">{{ a.purpose || '—' }}</td>
                                <td class="px-3 py-3 font-mono">{{ formatDate(a.issuedOn) }}</td>
                                <td class="px-3 py-3">
                                    <span class="text-xxs px-1.5 py-0.5 rounded font-mono" :class="statusBadge(a.status)">{{ a.status.replace('_', ' ') }}</span>
                                </td>
                                <td class="px-3 py-3 text-right font-mono">{{ a.currency }} {{ a.amount.toFixed(2) }}</td>
                                <td class="px-3 py-3 text-right font-mono"
                                    :class="a.settledAmount > 0 ? 'text-(--color-success)' : 'text-(--text-muted)'">
                                    {{ a.settledAmount.toFixed(2) }}
                                </td>
                                <td class="px-3 py-3 text-right font-mono font-semibold"
                                    :class="a.outstandingAmount > 0 ? 'text-(--text-heading)' : 'text-(--text-muted)'">
                                    {{ a.outstandingAmount.toFixed(2) }}
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <button v-if="canWrite && a.isCancellable" type="button" class="action-btn action-btn-danger"
                                        title="Cancel (only if no settlements)" @click="confirmCancel(a)">
                                        <i class="ti ti-rotate-2" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :page="pagination.page" :limit="pagination.limit"
                    :total="pagination.total" :total-pages="pagination.totalPages"
                    @update:page="(p) => { pagination.page = p; load() }"
                    @update:limit="(l) => { pagination.limit = l; pagination.page = 1; load() }" />
            </section>
        </div>

        <!-- Issue Modal -->
        <div v-if="showIssueModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-2xl bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Issue Cash Advance</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showIssueModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="post">
                    <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                        <div class="grid grid-cols-3 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Advance # *</label>
                                <input v-model="form.advance_number" type="text" required maxlength="64"
                                    placeholder="e.g. CASHADV-2026-001" class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Issued On *</label>
                                <input v-model="form.issued_on" type="date" required class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Amount *</label>
                                <input v-model.number="form.amount" type="number" step="0.01" min="0.01" required
                                    class="form-control text-xs font-mono text-right" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Employee *</label>
                                <select v-model="form.employee_id" required class="form-control text-xs" :disabled="employeesLoading">
                                    <option value="">— Pick employee —</option>
                                    <option v-for="e in employees" :key="e.id" :value="e.id">
                                        {{ e.fullName }}{{ e.employeeId ? ` (${e.employeeId})` : '' }}
                                    </option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Bank Account *</label>
                                <select v-model="form.bank_account_id" required class="form-control text-xs" :disabled="banksLoading"
                                    @change="onBankChange">
                                    <option value="">— Pick bank —</option>
                                    <option v-for="b in banks" :key="b.id" :value="b.id">
                                        {{ b.name }} ({{ b.currency }}){{ b.isDefault ? ' · default' : '' }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Receivable Account (Asset) *</label>
                            <select v-model="form.receivable_account_id" required class="form-control text-xs" :disabled="accountsLoading">
                                <option value="">— Pick receivable account —</option>
                                <option v-for="a in assetAccounts" :key="a.id" :value="a.id">
                                    {{ a.code }} · {{ a.name }}
                                </option>
                            </select>
                            <p class="text-xxs text-(--text-muted)">Typically an "Employee Advances Receivable" sub-account. Until the advance is settled, this is what carries the balance on your books.</p>
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Currency</label>
                                <input v-model="form.currency" type="text" maxlength="3"
                                    class="form-control text-xs font-mono uppercase"
                                    @input="form.currency = (form.currency || '').toUpperCase()" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Method</label>
                                <select v-model="form.payment_method" class="form-control text-xs">
                                    <option :value="null">— None —</option>
                                    <option v-for="m in PAYMENT_METHODS" :key="m.value" :value="m.value">{{ m.label }}</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Reference #</label>
                                <input v-model="form.reference_number" type="text" maxlength="64"
                                    class="form-control text-xs font-mono" />
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Purpose</label>
                            <input v-model="form.purpose" type="text" maxlength="500"
                                placeholder="e.g. Site visit to Siem Reap" class="form-control text-xs" />
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Notes</label>
                            <textarea v-model="form.notes" rows="2" maxlength="2000"
                                class="form-control text-xs resize-none" />
                        </div>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showIssueModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="!canSubmit || posting">
                            <i v-if="posting" class="ti ti-loader-2 animate-spin" />
                            Issue & Post
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Cancel Modal -->
        <div v-if="cancelTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Cancel Cash Advance</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="cancelTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <div class="p-3 rounded-lg badge-soft-warning text-xs flex items-start gap-2">
                        <i class="ti ti-alert-triangle mt-0.5" />
                        <div>
                            <p class="font-semibold">Posts a reversing journal entry</p>
                            <p class="text-xxs mt-0.5">Advance <span class="font-mono">{{ cancelTarget.advanceNumber }}</span> will be marked cancelled. The original posting stays in the audit log. Cancellation is only allowed when no settlements have been applied.</p>
                        </div>
                    </div>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="cancelTarget = null">Keep</button>
                    <button type="button" class="btn btn-danger text-xs" :disabled="cancelling" @click="onConfirmCancel">
                        <i v-if="cancelling" class="ti ti-loader-2 animate-spin" />
                        Cancel Advance
                    </button>
                </footer>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useApi } from '~/composables/useApi'
import { useFinance } from '~/composables/useFinance'
import { useToast } from '~/composables/useToast'
import { useCountUp } from '~/composables/useCountUp'
import { useAuthStore } from '~/stores/auth'
import type {
    Account,
    BankAccount,
    CashAdvance,
    CashAdvanceStatus,
    CreateCashAdvancePayload,
} from '~/types/finance'

definePageMeta({ breadcrumb: 'Cash Advances' })

interface EmployeeLite { id: string; employeeId: string | null; fullName: string }
interface Paginated<T> { data: T[]; pagination?: { page: number; limit: number; total: number; totalPages: number } }

const PAYMENT_METHODS = [
    { value: 'bank_transfer', label: 'Bank Transfer' },
    { value: 'cheque',        label: 'Cheque' },
    { value: 'cash',          label: 'Cash' },
    { value: 'wire',          label: 'Wire' },
]

const api = useApi()
const finance = useFinance()
const toast = useToast()
const authStore = useAuthStore()

const canRead  = computed(() => authStore.hasPermission('fms.cash_advances.read'))
const canWrite = computed(() => authStore.hasPermission('fms.cash_advances.write'))

const loading    = ref(false)
const posting    = ref(false)
const cancelling = ref(false)

const advances = ref<CashAdvance[]>([])
const pagination = reactive({ page: 1, limit: 25, total: 0, totalPages: 1 })
const statusFilter = ref<'' | CashAdvanceStatus>('')

const today = new Date().toISOString().slice(0, 10)
const formatDate = (s: string | null) => {
    if (!s) return '—'
    const d = new Date(s)
    return isNaN(d.getTime()) ? s : d.toISOString().slice(0, 10)
}
const formatMoney = (n: number) => {
    const abs = Math.abs(n)
    if (abs >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`
    if (abs >= 1_000)     return `${(n / 1_000).toFixed(1)}K`
    return n.toFixed(2)
}
const statusBadge = (s: CashAdvanceStatus) => ({
    open:              'badge-soft-info',
    partially_settled: 'badge-soft-warning',
    closed:            'badge-soft-success',
    cancelled:         'badge-soft-secondary',
}[s] || 'badge-soft-secondary')

// ---- KPIs --------------------------------------------------------------------

const openCount    = computed(() => advances.value.filter(a => a.status === 'open').length)
const partialCount = computed(() => advances.value.filter(a => a.status === 'partially_settled').length)
const closedCount  = computed(() => advances.value.filter(a => a.status === 'closed').length)
const totalOutstanding = computed(() => advances.value
    .filter(a => a.status === 'open' || a.status === 'partially_settled')
    .reduce((s, a) => s + a.outstandingAmount, 0))

const monthStart = (() => { const d = new Date(); d.setDate(1); return d.toISOString().slice(0, 10) })()
const issuedThisMonth = computed(() => advances.value
    .filter(a => a.status !== 'cancelled' && a.issuedOn >= monthStart)
    .reduce((s, a) => s + a.amount, 0))

const kpiOutstandingAnim = useCountUp(() => totalOutstanding.value)
const kpiMonthAnim       = useCountUp(() => issuedThisMonth.value)
const kpiClosedAnim      = useCountUp(() => closedCount.value)
const kpiTotalAnim       = useCountUp(() => advances.value.length)

// ---- Load --------------------------------------------------------------------

const load = async () => {
    if (!canRead.value) return
    loading.value = true
    try {
        const res = await finance.cashAdvances.list({
            page: pagination.page,
            limit: pagination.limit,
            status: statusFilter.value || undefined,
        })
        advances.value = res.data
        const p = (res as any).pagination
        if (p) {
            pagination.total = p.total ?? 0
            pagination.totalPages = p.totalPages ?? 1
            pagination.page = p.page ?? 1
            pagination.limit = p.limit ?? pagination.limit
        }
    } catch (err: any) {
        toast.error('Failed to load advances', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const setStatusFilter = (s: '' | CashAdvanceStatus) => {
    if (statusFilter.value === s) return
    statusFilter.value = s
    pagination.page = 1
    load()
}

// ---- Pickers (lazy-loaded on modal open) -------------------------------------

const employees = ref<EmployeeLite[]>([])
const employeesLoading = ref(false)
const banks = ref<BankAccount[]>([])
const banksLoading = ref(false)
const accountsList = ref<Account[]>([])
const accountsLoading = ref(false)
const assetAccounts = computed(() => accountsList.value.filter(a => a.type === 'asset'))

const ensureEmployeesLoaded = async () => {
    if (employees.value.length || employeesLoading.value) return
    employeesLoading.value = true
    try {
        const res = await api.get<Paginated<EmployeeLite>>('/employees?limit=200')
        employees.value = res.data
    } catch (err: any) {
        toast.error('Failed to load employees', err?.data?.message)
    } finally {
        employeesLoading.value = false
    }
}
const ensureBanksLoaded = async () => {
    if (banks.value.length || banksLoading.value) return
    banksLoading.value = true
    try {
        const res = await finance.bankAccounts.list({ limit: 100, is_active: true })
        banks.value = res.data
    } catch (err: any) {
        toast.error('Failed to load bank accounts', err?.data?.message)
    } finally {
        banksLoading.value = false
    }
}
const ensureAccountsLoaded = async () => {
    if (accountsList.value.length || accountsLoading.value) return
    accountsLoading.value = true
    try {
        const res = await finance.accounts.tree()
        const flat: Account[] = []
        const walk = (nodes: Account[]) => nodes.forEach(n => { flat.push(n); if (n.children?.length) walk(n.children) })
        walk(res.data)
        flat.sort((a, b) => a.code.localeCompare(b.code))
        accountsList.value = flat
    } catch (err: any) {
        toast.error('Failed to load accounts', err?.data?.message)
    } finally {
        accountsLoading.value = false
    }
}

// ---- Form --------------------------------------------------------------------

const showIssueModal = ref(false)

const blankForm = (): CreateCashAdvancePayload => ({
    advance_number: '',
    employee_id: '',
    bank_account_id: '',
    receivable_account_id: '',
    issued_on: today,
    amount: 0,
    currency: 'USD',
    payment_method: null,
    reference_number: null,
    purpose: null,
    notes: null,
})

const form = reactive<CreateCashAdvancePayload>(blankForm())

const resetForm = () => Object.assign(form, blankForm())

const openIssueModal = () => {
    resetForm()
    showIssueModal.value = true
    ensureEmployeesLoaded()
    ensureBanksLoaded()
    ensureAccountsLoaded()
}

const onBankChange = () => {
    const b = banks.value.find(x => x.id === form.bank_account_id)
    if (b) form.currency = b.currency
}

const canSubmit = computed(() => {
    if (!form.advance_number.trim()) return false
    if (!form.employee_id || !form.bank_account_id || !form.receivable_account_id) return false
    if (!form.issued_on) return false
    if (!form.amount || form.amount <= 0) return false
    return true
})

const post = async () => {
    if (!canSubmit.value) return
    posting.value = true
    try {
        const payload: CreateCashAdvancePayload = {
            ...form,
            advance_number: form.advance_number.trim(),
            reference_number: form.reference_number?.trim() || null,
            purpose: form.purpose?.trim() || null,
            notes: form.notes?.trim() || null,
        }
        const res = await finance.cashAdvances.create(payload)
        toast.success('Advance issued', res.data.advanceNumber)
        showIssueModal.value = false
        await load()
    } catch (err: any) {
        toast.error('Issue failed', err?.data?.message)
    } finally {
        posting.value = false
    }
}

// ---- Cancel ------------------------------------------------------------------

const cancelTarget = ref<CashAdvance | null>(null)
const confirmCancel = (a: CashAdvance) => { cancelTarget.value = a }
const onConfirmCancel = async () => {
    if (!cancelTarget.value) return
    cancelling.value = true
    try {
        const res = await finance.cashAdvances.cancel(cancelTarget.value.id)
        toast.success('Advance cancelled', res.data.advanceNumber)
        cancelTarget.value = null
        await load()
    } catch (err: any) {
        toast.error('Cancel failed', err?.data?.message)
    } finally {
        cancelling.value = false
    }
}

onMounted(load)
</script>

<style scoped>
.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px;
    height: 28px;
    border-radius: 6px;
    color: var(--text-body);
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.action-btn:hover {
    background: var(--bg-muted);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}

.action-btn-danger:hover {
    color: var(--color-danger);
    border-color: rgb(var(--color-danger-rgb) / 0.4);
}

.chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    font-size: 11px;
    color: var(--text-body);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.chip:hover { background: var(--bg-muted); }
.chip.active {
    background: rgb(var(--color-primary-rgb) / 0.12);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}
</style>
