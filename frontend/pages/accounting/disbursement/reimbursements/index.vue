<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Reimbursements</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Pay employees back for out-of-pocket expenses. Posts DR Expense / CR Cash atomically; cancellation reverses the journal.</p>
                </div>
                <button v-if="canWrite" type="button" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />New Reimbursement
                </button>
            </header>

            <!-- KPIs -->
            <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Total</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-primary flex items-center justify-center"><i class="ti ti-receipt-2 text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiCountAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">{{ postedCount }} posted · {{ cancelledCount }} cancelled</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Paid This Month</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-success flex items-center justify-center"><i class="ti ti-arrow-down-right text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ formatMoney(kpiMonthAnim) }}</p>
                    <p class="text-xxs text-(--text-muted)">Across all employees</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Today</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-info flex items-center justify-center"><i class="ti ti-calendar text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiTodayAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">Reimbursed today</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Lines</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-warning flex items-center justify-center"><i class="ti ti-list text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiLinesAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">Total line items on page</p>
                </div>
            </section>

            <!-- Status filter chips -->
            <section class="flex items-center gap-2 flex-wrap">
                <button type="button" class="chip" :class="{ active: statusFilter === '' }" @click="setStatusFilter('')">All</button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'posted' }" @click="setStatusFilter('posted')">
                    <i class="ti ti-check" /> Posted
                </button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'cancelled' }" @click="setStatusFilter('cancelled')">
                    <i class="ti ti-x" /> Cancelled
                </button>
            </section>

            <!-- Loading / Empty -->
            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading reimbursements...</span>
            </div>
            <div v-else-if="reimbs.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-receipt-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No reimbursements yet</h4>
                <p class="text-xs text-(--text-muted) mt-1">Pay back an employee for out-of-pocket expenses.</p>
            </div>

            <!-- Table -->
            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-(--bg-muted)/40 text-(--text-muted)">
                            <tr>
                                <th class="w-8 px-3 py-3"></th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Reimb #</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Employee</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Bank</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Paid On</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Method</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Status</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3">Amount</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3 w-24">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="r in reimbs" :key="r.id">
                                <tr class="border-t border-(--border-color) hover:bg-(--bg-muted)/40"
                                    :class="{ 'opacity-60': r.status === 'cancelled' }">
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(r.id)">
                                        <i class="ti" :class="expanded.has(r.id) ? 'ti-chevron-down' : 'ti-chevron-right'" />
                                    </td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(r.id)">
                                        <div class="font-mono font-semibold"
                                            :class="r.status === 'cancelled' ? 'text-(--text-muted) line-through' : 'text-(--text-heading)'">
                                            {{ r.reimbursementNumber }}
                                        </div>
                                        <div v-if="r.referenceNumber" class="text-xxs text-(--text-muted) font-mono mt-0.5">Ref: {{ r.referenceNumber }}</div>
                                    </td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(r.id)">
                                        <p class="text-(--text-heading) font-semibold truncate max-w-xs">{{ r.employee?.fullName || '—' }}</p>
                                        <p v-if="r.employee?.employeeId" class="text-xxs text-(--text-muted) font-mono">{{ r.employee.employeeId }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-(--text-body) cursor-pointer" @click="toggle(r.id)">
                                        <p class="truncate max-w-xs">{{ r.bankAccount?.name || '—' }}</p>
                                        <p v-if="r.bankAccount?.bankName" class="text-xxs text-(--text-muted) truncate">{{ r.bankAccount.bankName }}</p>
                                    </td>
                                    <td class="px-3 py-3 font-mono cursor-pointer" @click="toggle(r.id)">{{ formatDate(r.paidOn) }}</td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(r.id)">
                                        <span v-if="r.paymentMethod" class="text-xxs px-1.5 py-0.5 rounded font-mono badge-soft-info">{{ formatMethod(r.paymentMethod) }}</span>
                                        <span v-else class="text-(--text-muted)">—</span>
                                    </td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(r.id)">
                                        <span class="text-xxs px-1.5 py-0.5 rounded font-mono" :class="statusBadge(r.status)">{{ r.status }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono font-semibold text-(--text-heading) cursor-pointer" @click="toggle(r.id)">
                                        {{ r.currency }} {{ r.amount.toFixed(2) }}
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <button v-if="canWrite && r.isCancellable" type="button" class="action-btn action-btn-danger"
                                            title="Cancel reimbursement" @click.stop="confirmCancel(r)">
                                            <i class="ti ti-rotate-2" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="expanded.has(r.id)" class="border-t border-(--border-color) bg-(--bg-muted)/20">
                                    <td colspan="9" class="px-4 py-3">
                                        <table class="w-full text-xxs font-mono">
                                            <thead class="text-(--text-muted)">
                                                <tr>
                                                    <th class="text-left font-bold uppercase tracking-widest py-1">Account</th>
                                                    <th class="text-left font-bold uppercase tracking-widest py-1">Description</th>
                                                    <th class="text-right font-bold uppercase tracking-widest py-1 w-32">Amount</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="l in r.lines" :key="l.id" class="border-t border-(--border-color)/40">
                                                    <td class="py-1.5">
                                                        <span v-if="l.account">
                                                            <span class="text-(--text-heading)">{{ l.account.code }}</span>
                                                            <span class="text-(--text-muted) ml-2">{{ l.account.name }}</span>
                                                        </span>
                                                        <span v-else class="text-(--text-muted)">—</span>
                                                    </td>
                                                    <td class="py-1.5 text-(--text-body)">{{ l.description || '—' }}</td>
                                                    <td class="text-right py-1.5 font-semibold text-(--text-heading)">{{ l.amount.toFixed(2) }}</td>
                                                </tr>
                                                <tr class="border-t border-(--border-color) font-bold text-(--text-heading)">
                                                    <td class="py-1.5 uppercase tracking-widest text-xxs" colspan="2">Total</td>
                                                    <td class="text-right py-1.5">{{ r.amount.toFixed(2) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <p v-if="r.notes" class="text-xxs text-(--text-muted) mt-3"><strong>Notes:</strong> {{ r.notes }}</p>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <footer class="flex items-center justify-between px-4 py-3 border-t border-(--border-color) text-xxs text-(--text-muted)">
                    <span>Page {{ pagination.page }} of {{ pagination.totalPages }} · {{ pagination.total }} total</span>
                    <div class="flex items-center gap-2">
                        <button type="button" class="btn btn-ghost text-xxs" :disabled="pagination.page <= 1" @click="goPage(pagination.page - 1)">Prev</button>
                        <button type="button" class="btn btn-ghost text-xxs" :disabled="pagination.page >= pagination.totalPages" @click="goPage(pagination.page + 1)">Next</button>
                    </div>
                </footer>
            </section>
        </div>

        <!-- Create Modal -->
        <div v-if="showFormModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-4xl bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Record Reimbursement</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="post">
                    <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                        <!-- Header -->
                        <div class="grid grid-cols-4 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Reimb # *</label>
                                <input v-model="form.reimbursement_number" type="text" required maxlength="64"
                                    placeholder="e.g. REIMB-2026-001" class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Paid On *</label>
                                <input v-model="form.paid_on" type="date" required class="form-control text-xs font-mono" />
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
                                    placeholder="Cheque / wire ref" class="form-control text-xs font-mono" />
                            </div>
                        </div>

                        <!-- Employee + Bank -->
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
                                <p v-if="banks.length === 0 && !banksLoading" class="text-xxs text-(--color-warning) flex items-center gap-1.5">
                                    <i class="ti ti-info-circle" />
                                    No bank accounts yet.
                                    <NuxtLink to="/accounting/bank" class="underline">Create one</NuxtLink>.
                                </p>
                            </div>
                        </div>

                        <!-- Lines -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Expense Lines *</label>
                                <button type="button" class="btn btn-ghost text-xxs" @click="addLine">
                                    <i class="ti ti-plus" />Add Line
                                </button>
                            </div>
                            <table class="w-full text-xs">
                                <thead class="text-(--text-muted)">
                                    <tr>
                                        <th class="text-left font-bold uppercase tracking-widest text-xxs py-1">Expense Account</th>
                                        <th class="text-left font-bold uppercase tracking-widest text-xxs py-1">Description</th>
                                        <th class="text-right font-bold uppercase tracking-widest text-xxs py-1 w-32">Amount</th>
                                        <th class="w-8"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(l, idx) in form.lines" :key="idx" class="border-t border-(--border-color)/40">
                                        <td class="py-1.5 pr-2">
                                            <select v-model="l.account_id" required class="form-control text-xs" :disabled="accountsLoading">
                                                <option value="">— pick expense account —</option>
                                                <option v-for="a in expenseAccounts" :key="a.id" :value="a.id">
                                                    {{ a.code }} · {{ a.name }}
                                                </option>
                                            </select>
                                        </td>
                                        <td class="py-1.5 pr-2">
                                            <input v-model="l.description" type="text" maxlength="500"
                                                placeholder="What was bought" class="form-control text-xs" />
                                        </td>
                                        <td class="py-1.5 pr-2">
                                            <input v-model.number="l.amount" type="number" step="0.01" min="0.01" required
                                                class="form-control text-xs font-mono text-right" />
                                        </td>
                                        <td class="py-1.5">
                                            <button type="button" class="action-btn action-btn-danger"
                                                :disabled="form.lines.length <= 1" @click="removeLine(idx)">
                                                <i class="ti ti-trash" />
                                            </button>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Totals -->
                        <div class="flex justify-end">
                            <div class="w-full sm:w-80 space-y-1.5 text-xs font-mono">
                                <div class="flex items-center justify-between">
                                    <span class="text-(--text-muted) uppercase tracking-widest text-xxs">Sum of Lines</span>
                                    <span class="font-semibold text-(--text-heading)">{{ formSumLines.toFixed(2) }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2 border-t border-(--border-color) pt-1.5 text-sm">
                                    <span class="text-(--text-muted) uppercase tracking-widest text-xxs">Reimburse</span>
                                    <input v-model.number="form.amount" type="number" step="0.01" min="0.01" required
                                        class="form-control text-xs font-mono text-right w-32 font-bold" />
                                </div>
                                <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs"
                                    :class="amountMatches ? 'badge-soft-success' : 'badge-soft-warning'">
                                    <i class="ti" :class="amountMatches ? 'ti-check' : 'ti-alert-triangle'" />
                                    <span v-if="amountMatches">Balanced</span>
                                    <span v-else>Diff {{ (formSumLines - (Number(form.amount) || 0)).toFixed(2) }}</span>
                                    <button type="button" class="ml-auto text-xxs underline" @click="form.amount = Number(formSumLines.toFixed(2))">Use sum</button>
                                </div>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Notes</label>
                            <textarea v-model="form.notes" rows="2" maxlength="2000"
                                class="form-control text-xs resize-none" />
                        </div>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showFormModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="!canSubmit || posting">
                            <i v-if="posting" class="ti ti-loader-2 animate-spin" />
                            Record & Post
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Cancel Modal -->
        <div v-if="cancelTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Cancel Reimbursement</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="cancelTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <div class="p-3 rounded-lg badge-soft-warning text-xs flex items-start gap-2">
                        <i class="ti ti-alert-triangle mt-0.5" />
                        <div>
                            <p class="font-semibold">Posts a reversing journal entry</p>
                            <p class="text-xxs mt-0.5">Reimbursement <span class="font-mono">{{ cancelTarget.reimbursementNumber }}</span> will be marked cancelled. The original posting stays in the audit log; a balanced reversal is appended.</p>
                        </div>
                    </div>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="cancelTarget = null">Keep</button>
                    <button type="button" class="btn btn-danger text-xs" :disabled="cancelling" @click="onConfirmCancel">
                        <i v-if="cancelling" class="ti ti-loader-2 animate-spin" />
                        Cancel Reimbursement
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
    Reimbursement,
    ReimbursementStatus,
    CreateReimbursementPayload,
    CreateReimbursementLinePayload,
} from '~/types/finance'

definePageMeta({ breadcrumb: 'Reimbursements' })

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

const canRead  = computed(() => authStore.hasPermission('fms.reimbursements.read'))
const canWrite = computed(() => authStore.hasPermission('fms.reimbursements.write'))

const loading    = ref(false)
const posting    = ref(false)
const cancelling = ref(false)

const reimbs = ref<Reimbursement[]>([])
const pagination = reactive({ page: 1, limit: 25, total: 0, totalPages: 1 })
const statusFilter = ref<'' | ReimbursementStatus>('')
const expanded = ref<Set<string>>(new Set())

const toggle = (id: string) => {
    if (expanded.value.has(id)) expanded.value.delete(id)
    else expanded.value.add(id)
    expanded.value = new Set(expanded.value)
}

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
const formatMethod = (m: string) => m.replace('_', ' ')
const statusBadge = (s: ReimbursementStatus) => ({
    posted:    'badge-soft-success',
    cancelled: 'badge-soft-secondary',
}[s] || 'badge-soft-secondary')

// ---- KPIs --------------------------------------------------------------------

const postedCount    = computed(() => reimbs.value.filter(r => r.status === 'posted').length)
const cancelledCount = computed(() => reimbs.value.filter(r => r.status === 'cancelled').length)

const monthStart = (() => { const d = new Date(); d.setDate(1); return d.toISOString().slice(0, 10) })()
const paidThisMonth = computed(() => reimbs.value
    .filter(r => r.status === 'posted' && r.paidOn >= monthStart)
    .reduce((s, r) => s + r.amount, 0))
const paidTodayCount = computed(() => reimbs.value
    .filter(r => r.status === 'posted' && r.paidOn === today).length)
const totalLines = computed(() => reimbs.value.reduce((s, r) => s + r.lines.length, 0))

const kpiCountAnim = useCountUp(() => reimbs.value.length)
const kpiMonthAnim = useCountUp(() => paidThisMonth.value)
const kpiTodayAnim = useCountUp(() => paidTodayCount.value)
const kpiLinesAnim = useCountUp(() => totalLines.value)

// ---- Load --------------------------------------------------------------------

const load = async () => {
    if (!canRead.value) return
    loading.value = true
    try {
        const res = await finance.reimbursements.list({
            page: pagination.page,
            limit: pagination.limit,
            status: statusFilter.value || undefined,
        })
        reimbs.value = res.data
        const p = (res as any).pagination
        if (p) {
            pagination.total = p.total ?? 0
            pagination.totalPages = p.totalPages ?? 1
            pagination.page = p.page ?? 1
            pagination.limit = p.limit ?? pagination.limit
        }
    } catch (err: any) {
        toast.error('Failed to load reimbursements', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const goPage = (p: number) => {
    if (p < 1 || p > pagination.totalPages) return
    pagination.page = p
    load()
}

const setStatusFilter = (s: '' | ReimbursementStatus) => {
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
const expenseAccounts = computed(() => accountsList.value.filter(a => a.type === 'expense'))

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

const showFormModal = ref(false)

const makeLine = (): CreateReimbursementLinePayload => ({
    account_id: '',
    description: null,
    amount: 0,
    receipt_attachment: null,
})

const blankForm = (): CreateReimbursementPayload => ({
    reimbursement_number: '',
    employee_id: '',
    bank_account_id: '',
    paid_on: today,
    amount: 0,
    currency: null,
    payment_method: null,
    reference_number: null,
    notes: null,
    lines: [makeLine()],
})

const form = reactive<CreateReimbursementPayload>(blankForm())

const resetForm = () => {
    Object.assign(form, blankForm())
    form.lines = [makeLine()]
}

const openCreateModal = () => {
    resetForm()
    showFormModal.value = true
    ensureEmployeesLoaded()
    ensureBanksLoaded()
    ensureAccountsLoaded()
}

const onBankChange = () => {
    const b = banks.value.find(x => x.id === form.bank_account_id)
    if (b && !form.currency) form.currency = b.currency
}

const addLine = () => form.lines.push(makeLine())
const removeLine = (idx: number) => {
    if (form.lines.length <= 1) return
    form.lines.splice(idx, 1)
}

const formSumLines = computed(() => form.lines.reduce(
    (s, l) => s + (Number(l.amount) || 0), 0))
const amountMatches = computed(() =>
    Math.abs(formSumLines.value - (Number(form.amount) || 0)) < 0.001 && formSumLines.value > 0)

const canSubmit = computed(() => {
    if (!form.reimbursement_number.trim()) return false
    if (!form.employee_id || !form.bank_account_id) return false
    if (!form.paid_on) return false
    if (!form.amount || form.amount <= 0) return false
    for (const l of form.lines) {
        if (!l.account_id) return false
        if (!(Number(l.amount) > 0)) return false
    }
    return amountMatches.value
})

const post = async () => {
    if (!canSubmit.value) return
    posting.value = true
    try {
        const payload: CreateReimbursementPayload = {
            ...form,
            reimbursement_number: form.reimbursement_number.trim(),
            reference_number: form.reference_number?.trim() || null,
            notes: form.notes?.trim() || null,
            lines: form.lines
                .filter(l => l.account_id && Number(l.amount) > 0)
                .map(l => ({
                    account_id: l.account_id,
                    description: l.description?.trim() || null,
                    amount: Number(l.amount),
                    receipt_attachment: l.receipt_attachment?.trim() || null,
                })),
        }
        const res = await finance.reimbursements.create(payload)
        toast.success('Reimbursement posted', res.data.reimbursementNumber)
        showFormModal.value = false
        await load()
    } catch (err: any) {
        toast.error('Post failed', err?.data?.message)
    } finally {
        posting.value = false
    }
}

// ---- Cancel ------------------------------------------------------------------

const cancelTarget = ref<Reimbursement | null>(null)
const confirmCancel = (r: Reimbursement) => { cancelTarget.value = r }
const onConfirmCancel = async () => {
    if (!cancelTarget.value) return
    cancelling.value = true
    try {
        const res = await finance.reimbursements.cancel(cancelTarget.value.id)
        toast.success('Reimbursement cancelled', res.data.reimbursementNumber)
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

.action-btn[disabled] { opacity: 0.3; cursor: not-allowed; }

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
