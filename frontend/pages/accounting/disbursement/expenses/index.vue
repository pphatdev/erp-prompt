<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Expenses</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Pay-as-you-go spend that does not go through Bills. Posts DR Expense / CR Cash atomically; cancellation reverses the journal.</p>
                </div>
                <button v-if="canWrite" type="button" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />New Expense
                </button>
            </header>

            <!-- KPIs -->
            <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Total</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-primary flex items-center justify-center"><i class="ti ti-receipt-tax text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiCountAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">{{ postedCount }} posted · {{ cancelledCount }} cancelled</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Spent This Month</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-warning flex items-center justify-center"><i class="ti ti-arrow-down-right text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ formatMoney(kpiMonthAnim) }}</p>
                    <p class="text-xxs text-(--text-muted)">Across all accounts</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Today</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-info flex items-center justify-center"><i class="ti ti-calendar text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiTodayAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">Posted today</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Lines</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-success flex items-center justify-center"><i class="ti ti-list text-sm" /></span>
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
                <span class="text-xs text-(--text-muted)">Loading expenses...</span>
            </div>
            <div v-else-if="expenses.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-receipt-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No expenses yet</h4>
                <p class="text-xs text-(--text-muted) mt-1">Record pay-as-you-go spend that doesn't route through Bills.</p>
            </div>

            <!-- Table -->
            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-(--bg-muted)/40 text-(--text-muted)">
                            <tr>
                                <th class="w-8 px-3 py-3"></th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Expense #</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Payee</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Bank</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Paid On</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Method</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Status</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3">Total</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3 w-24">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="e in expenses" :key="e.id">
                                <tr class="border-t border-(--border-color) hover:bg-(--bg-muted)/40"
                                    :class="{ 'opacity-60': e.status === 'cancelled' }">
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(e.id)">
                                        <i class="ti" :class="expanded.has(e.id) ? 'ti-chevron-down' : 'ti-chevron-right'" />
                                    </td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(e.id)">
                                        <div class="font-mono font-semibold"
                                            :class="e.status === 'cancelled' ? 'text-(--text-muted) line-through' : 'text-(--text-heading)'">
                                            {{ e.expenseNumber }}
                                        </div>
                                        <div v-if="e.referenceNumber" class="text-xxs text-(--text-muted) font-mono mt-0.5">Ref: {{ e.referenceNumber }}</div>
                                    </td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(e.id)">
                                        <p class="text-(--text-heading) font-semibold truncate max-w-xs">{{ e.supplier?.name || 'Misc' }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-(--text-body) cursor-pointer" @click="toggle(e.id)">
                                        <p class="truncate max-w-xs">{{ e.bankAccount?.name || '—' }}</p>
                                        <p v-if="e.bankAccount?.bankName" class="text-xxs text-(--text-muted) truncate">{{ e.bankAccount.bankName }}</p>
                                    </td>
                                    <td class="px-3 py-3 font-mono cursor-pointer" @click="toggle(e.id)">{{ formatDate(e.paidOn) }}</td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(e.id)">
                                        <span v-if="e.paymentMethod" class="text-xxs px-1.5 py-0.5 rounded font-mono badge-soft-info">{{ formatMethod(e.paymentMethod) }}</span>
                                        <span v-else class="text-(--text-muted)">—</span>
                                    </td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(e.id)">
                                        <span class="text-xxs px-1.5 py-0.5 rounded font-mono" :class="statusBadge(e.status)">{{ e.status }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono font-semibold text-(--text-heading) cursor-pointer" @click="toggle(e.id)">
                                        {{ e.currency }} {{ e.total.toFixed(2) }}
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <button v-if="canWrite && e.isCancellable" type="button" class="action-btn action-btn-danger"
                                            title="Cancel expense" @click.stop="confirmCancel(e)">
                                            <i class="ti ti-rotate-2" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="expanded.has(e.id)" class="border-t border-(--border-color) bg-(--bg-muted)/20">
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
                                                <tr v-for="l in e.lines" :key="l.id" class="border-t border-(--border-color)/40">
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
                                                    <td class="text-right py-1.5">{{ e.total.toFixed(2) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <p v-if="e.notes" class="text-xxs text-(--text-muted) mt-3"><strong>Notes:</strong> {{ e.notes }}</p>
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
                    <h3 class="font-semibold text-sm">Record Expense</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="post">
                    <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                        <!-- Header -->
                        <div class="grid grid-cols-4 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Expense # *</label>
                                <input v-model="form.expense_number" type="text" required maxlength="64"
                                    placeholder="e.g. EXP-2026-001" class="form-control text-xs font-mono" />
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
                                    placeholder="Receipt / ref" class="form-control text-xs font-mono" />
                            </div>
                        </div>

                        <!-- Bank + Supplier -->
                        <div class="grid grid-cols-2 gap-3">
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
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Supplier (optional)</label>
                                <select v-model="form.supplier_id" class="form-control text-xs" :disabled="suppliersLoading">
                                    <option :value="null">— Misc / no supplier —</option>
                                    <option v-for="s in suppliers" :key="s.id" :value="s.id">{{ s.name }}</option>
                                </select>
                                <p class="text-xxs text-(--text-muted)">For traceability only — expense bypasses AP entirely.</p>
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
                                                placeholder="What was paid for" class="form-control text-xs" />
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
                                    <span class="text-(--text-muted) uppercase tracking-widest text-xxs">Total *</span>
                                    <input v-model.number="form.total" type="number" step="0.01" min="0.01" required
                                        class="form-control text-xs font-mono text-right w-32 font-bold" />
                                </div>
                                <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs"
                                    :class="totalMatches ? 'badge-soft-success' : 'badge-soft-warning'">
                                    <i class="ti" :class="totalMatches ? 'ti-check' : 'ti-alert-triangle'" />
                                    <span v-if="totalMatches">Balanced</span>
                                    <span v-else>Diff {{ (formSumLines - (Number(form.total) || 0)).toFixed(2) }}</span>
                                    <button type="button" class="ml-auto text-xxs underline" @click="form.total = Number(formSumLines.toFixed(2))">Use sum</button>
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
                    <h3 class="font-semibold text-sm">Cancel Expense</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="cancelTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <div class="p-3 rounded-lg badge-soft-warning text-xs flex items-start gap-2">
                        <i class="ti ti-alert-triangle mt-0.5" />
                        <div>
                            <p class="font-semibold">Posts a reversing journal entry</p>
                            <p class="text-xxs mt-0.5">Expense <span class="font-mono">{{ cancelTarget.expenseNumber }}</span> will be marked cancelled. The original posting stays in the audit log; a balanced reversal is appended.</p>
                        </div>
                    </div>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="cancelTarget = null">Keep</button>
                    <button type="button" class="btn btn-danger text-xs" :disabled="cancelling" @click="onConfirmCancel">
                        <i v-if="cancelling" class="ti ti-loader-2 animate-spin" />
                        Cancel Expense
                    </button>
                </footer>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useFinance } from '~/composables/useFinance'
import { useInventory } from '~/composables/useInventory'
import { useToast } from '~/composables/useToast'
import { useCountUp } from '~/composables/useCountUp'
import { useAuthStore } from '~/stores/auth'
import type {
    Account,
    BankAccount,
    Expense,
    ExpenseStatus,
    CreateExpensePayload,
    CreateExpenseLinePayload,
} from '~/types/finance'

definePageMeta({ breadcrumb: 'Expenses' })

interface SupplierLite { id: string; name: string }

const PAYMENT_METHODS = [
    { value: 'bank_transfer', label: 'Bank Transfer' },
    { value: 'cheque',        label: 'Cheque' },
    { value: 'cash',          label: 'Cash' },
    { value: 'wire',          label: 'Wire' },
    { value: 'credit_card',   label: 'Credit Card' },
]

const finance = useFinance()
const inventory = useInventory()
const toast = useToast()
const authStore = useAuthStore()

const canRead  = computed(() => authStore.hasPermission('fms.expenses.read'))
const canWrite = computed(() => authStore.hasPermission('fms.expenses.write'))

const loading    = ref(false)
const posting    = ref(false)
const cancelling = ref(false)

const expenses = ref<Expense[]>([])
const pagination = reactive({ page: 1, limit: 25, total: 0, totalPages: 1 })
const statusFilter = ref<'' | ExpenseStatus>('')
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
const statusBadge = (s: ExpenseStatus) => ({
    posted:    'badge-soft-success',
    cancelled: 'badge-soft-secondary',
}[s] || 'badge-soft-secondary')

// ---- KPIs --------------------------------------------------------------------

const postedCount    = computed(() => expenses.value.filter(e => e.status === 'posted').length)
const cancelledCount = computed(() => expenses.value.filter(e => e.status === 'cancelled').length)

const monthStart = (() => { const d = new Date(); d.setDate(1); return d.toISOString().slice(0, 10) })()
const spentThisMonth = computed(() => expenses.value
    .filter(e => e.status === 'posted' && e.paidOn >= monthStart)
    .reduce((s, e) => s + e.total, 0))
const todayCount = computed(() => expenses.value
    .filter(e => e.status === 'posted' && e.paidOn === today).length)
const totalLines = computed(() => expenses.value.reduce((s, e) => s + e.lines.length, 0))

const kpiCountAnim = useCountUp(() => expenses.value.length)
const kpiMonthAnim = useCountUp(() => spentThisMonth.value)
const kpiTodayAnim = useCountUp(() => todayCount.value)
const kpiLinesAnim = useCountUp(() => totalLines.value)

// ---- Load --------------------------------------------------------------------

const load = async () => {
    if (!canRead.value) return
    loading.value = true
    try {
        const res = await finance.expenses.list({
            page: pagination.page,
            limit: pagination.limit,
            status: statusFilter.value || undefined,
        })
        expenses.value = res.data
        const p = (res as any).pagination
        if (p) {
            pagination.total = p.total ?? 0
            pagination.totalPages = p.totalPages ?? 1
            pagination.page = p.page ?? 1
            pagination.limit = p.limit ?? pagination.limit
        }
    } catch (err: any) {
        toast.error('Failed to load expenses', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const goPage = (p: number) => {
    if (p < 1 || p > pagination.totalPages) return
    pagination.page = p
    load()
}

const setStatusFilter = (s: '' | ExpenseStatus) => {
    if (statusFilter.value === s) return
    statusFilter.value = s
    pagination.page = 1
    load()
}

// ---- Pickers (lazy-loaded on modal open) -------------------------------------

const banks = ref<BankAccount[]>([])
const banksLoading = ref(false)
const suppliers = ref<SupplierLite[]>([])
const suppliersLoading = ref(false)
const accountsList = ref<Account[]>([])
const accountsLoading = ref(false)
const expenseAccounts = computed(() => accountsList.value.filter(a => a.type === 'expense'))

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

const ensureSuppliersLoaded = async () => {
    if (suppliers.value.length || suppliersLoading.value) return
    suppliersLoading.value = true
    try {
        const res = await inventory.suppliers.list({ limit: 200 })
        suppliers.value = res.data.map((s: any) => ({ id: s.id, name: s.name }))
    } catch (err: any) {
        toast.error('Failed to load suppliers', err?.data?.message)
    } finally {
        suppliersLoading.value = false
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

const blankLine = (): CreateExpenseLinePayload => ({
    account_id: '',
    description: null,
    amount: 0,
})

const blankForm = (): CreateExpensePayload => ({
    expense_number: '',
    bank_account_id: '',
    supplier_id: null,
    paid_on: today,
    total: 0,
    currency: 'USD',
    payment_method: null,
    reference_number: null,
    notes: null,
    lines: [blankLine()],
})

const form = reactive<CreateExpensePayload>(blankForm())

const resetForm = () => Object.assign(form, blankForm())

const openCreateModal = () => {
    resetForm()
    form.lines = [blankLine()]
    showFormModal.value = true
    ensureBanksLoaded()
    ensureSuppliersLoaded()
    ensureAccountsLoaded()
}

const addLine = () => { form.lines.push(blankLine()) }
const removeLine = (idx: number) => { if (form.lines.length > 1) form.lines.splice(idx, 1) }

const onBankChange = () => {
    const b = banks.value.find(x => x.id === form.bank_account_id)
    if (b) form.currency = b.currency
}

const formSumLines = computed(() =>
    form.lines.reduce((s, l) => s + (Number(l.amount) || 0), 0)
)
const totalMatches = computed(() =>
    Math.abs(formSumLines.value - (Number(form.total) || 0)) < 0.005
)

const canSubmit = computed(() => {
    if (!form.expense_number.trim()) return false
    if (!form.bank_account_id) return false
    if (!form.paid_on) return false
    if (!form.total || form.total <= 0) return false
    if (!totalMatches.value) return false
    if (!form.lines.length) return false
    for (const l of form.lines) {
        if (!l.account_id) return false
        if (!l.amount || l.amount <= 0) return false
    }
    return true
})

const post = async () => {
    if (!canSubmit.value) return
    posting.value = true
    try {
        const payload: CreateExpensePayload = {
            ...form,
            expense_number: form.expense_number.trim(),
            reference_number: form.reference_number?.trim() || null,
            notes: form.notes?.trim() || null,
            lines: form.lines.map(l => ({
                account_id: l.account_id,
                description: l.description?.toString().trim() || null,
                amount: Number(l.amount),
            })),
        }
        const res = await finance.expenses.create(payload)
        toast.success('Expense posted', res.data.expenseNumber)
        showFormModal.value = false
        await load()
    } catch (err: any) {
        toast.error('Record failed', err?.data?.message)
    } finally {
        posting.value = false
    }
}

// ---- Cancel ------------------------------------------------------------------

const cancelTarget = ref<Expense | null>(null)
const confirmCancel = (e: Expense) => { cancelTarget.value = e }
const onConfirmCancel = async () => {
    if (!cancelTarget.value) return
    cancelling.value = true
    try {
        const res = await finance.expenses.cancel(cancelTarget.value.id)
        toast.success('Expense cancelled', res.data.expenseNumber)
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

.action-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
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
