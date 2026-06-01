<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Pay Bill</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Settle one or many vendor bills in a single banking event. Posts DR AP / CR Cash atomically; cancellation reverses the journal.</p>
                </div>
                <button v-if="canWrite" type="button" class="btn btn-primary text-xs" @click="openPayModal">
                    <i class="ti ti-plus" />Record Payment
                </button>
            </header>

            <!-- KPIs -->
            <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Payments</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-primary flex items-center justify-center"><i class="ti ti-cash-register text-sm" /></span>
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
                    <p class="text-xxs text-(--text-muted)">Across all vendors</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Today</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-info flex items-center justify-center"><i class="ti ti-calendar text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiTodayAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">Payments today</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Bills Settled</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-warning flex items-center justify-center"><i class="ti ti-file-check text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiAppsAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">Application rows on page</p>
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
                <span class="text-xs text-(--text-muted)">Loading payments...</span>
            </div>
            <div v-else-if="payments.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-cash-register text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No payments yet</h4>
                <p class="text-xs text-(--text-muted) mt-1">Record your first vendor payment to settle approved bills.</p>
            </div>

            <!-- Table -->
            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-(--bg-muted)/40 text-(--text-muted)">
                            <tr>
                                <th class="w-8 px-3 py-3"></th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Payment #</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Vendor</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Bank</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Paid On</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Method</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Status</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3">Amount</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3 w-24">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="p in payments" :key="p.id">
                                <tr class="border-t border-(--border-color) hover:bg-(--bg-muted)/40"
                                    :class="{ 'opacity-60': p.status === 'cancelled' }">
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(p.id)">
                                        <i class="ti" :class="expanded.has(p.id) ? 'ti-chevron-down' : 'ti-chevron-right'" />
                                    </td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(p.id)">
                                        <div class="font-mono font-semibold"
                                            :class="p.status === 'cancelled' ? 'text-(--text-muted) line-through' : 'text-(--text-heading)'">
                                            {{ p.paymentNumber }}
                                        </div>
                                        <div v-if="p.referenceNumber" class="text-xxs text-(--text-muted) font-mono mt-0.5">Ref: {{ p.referenceNumber }}</div>
                                    </td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(p.id)">
                                        <p class="text-(--text-heading) font-semibold truncate max-w-xs">{{ p.supplier?.name || '—' }}</p>
                                        <p v-if="p.supplier?.code" class="text-xxs text-(--text-muted) font-mono">{{ p.supplier.code }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-(--text-body) cursor-pointer" @click="toggle(p.id)">
                                        <p class="truncate max-w-xs">{{ p.bankAccount?.name || '—' }}</p>
                                        <p v-if="p.bankAccount?.bankName" class="text-xxs text-(--text-muted) truncate">{{ p.bankAccount.bankName }}</p>
                                    </td>
                                    <td class="px-3 py-3 font-mono cursor-pointer" @click="toggle(p.id)">{{ formatDate(p.paidOn) }}</td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(p.id)">
                                        <span v-if="p.paymentMethod" class="text-xxs px-1.5 py-0.5 rounded font-mono badge-soft-info">{{ formatMethod(p.paymentMethod) }}</span>
                                        <span v-else class="text-(--text-muted)">—</span>
                                    </td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(p.id)">
                                        <span class="text-xxs px-1.5 py-0.5 rounded font-mono" :class="statusBadge(p.status)">{{ p.status }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono font-semibold text-(--text-heading) cursor-pointer" @click="toggle(p.id)">
                                        {{ p.currency }} {{ p.amount.toFixed(2) }}
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <button v-if="canWrite && p.isCancellable" type="button" class="action-btn action-btn-danger"
                                            title="Cancel payment" @click.stop="confirmCancel(p)">
                                            <i class="ti ti-rotate-2" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="expanded.has(p.id)" class="border-t border-(--border-color) bg-(--bg-muted)/20">
                                    <td colspan="9" class="px-4 py-3">
                                        <table class="w-full text-xxs font-mono">
                                            <thead class="text-(--text-muted)">
                                                <tr>
                                                    <th class="text-left font-bold uppercase tracking-widest py-1">Bill</th>
                                                    <th class="text-right font-bold uppercase tracking-widest py-1 w-32">Bill Total</th>
                                                    <th class="text-right font-bold uppercase tracking-widest py-1 w-32">Applied</th>
                                                    <th class="text-right font-bold uppercase tracking-widest py-1 w-32">Outstanding</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="a in p.applications" :key="a.id" class="border-t border-(--border-color)/40">
                                                    <td class="py-1.5">
                                                        <span class="text-(--text-heading)">{{ a.bill?.billNumber || `#${a.billId.slice(0, 8)}` }}</span>
                                                        <span v-if="a.bill?.status" class="text-xxs ml-2 px-1 py-0.5 rounded" :class="statusBadgeForBill(a.bill.status)">{{ a.bill.status.replace('_', ' ') }}</span>
                                                    </td>
                                                    <td class="text-right py-1.5">{{ a.bill ? a.bill.total.toFixed(2) : '—' }}</td>
                                                    <td class="text-right py-1.5 font-semibold text-(--text-heading)">{{ a.appliedAmount.toFixed(2) }}</td>
                                                    <td class="text-right py-1.5">{{ a.bill ? a.bill.outstandingAmount.toFixed(2) : '—' }}</td>
                                                </tr>
                                                <tr class="border-t border-(--border-color) font-bold text-(--text-heading)">
                                                    <td class="py-1.5 uppercase tracking-widest text-xxs" colspan="2">Payment Total</td>
                                                    <td class="text-right py-1.5">{{ p.amount.toFixed(2) }}</td>
                                                    <td></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <p v-if="p.notes" class="text-xxs text-(--text-muted) mt-3"><strong>Notes:</strong> {{ p.notes }}</p>
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

        <!-- Pay Modal -->
        <div v-if="showPayModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-4xl bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Record Bill Payment</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showPayModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="post">
                    <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                        <!-- Header row -->
                        <div class="grid grid-cols-4 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Payment # *</label>
                                <input v-model="form.payment_number" type="text" required maxlength="64"
                                    placeholder="e.g. PAY-2026-001" class="form-control text-xs font-mono" />
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

                        <!-- Vendor + Bank -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Vendor *</label>
                                <select v-model="form.supplier_id" required class="form-control text-xs" :disabled="vendorsLoading"
                                    @change="onVendorChange">
                                    <option value="">— Pick vendor —</option>
                                    <option v-for="v in vendors" :key="v.id" :value="v.id">
                                        {{ v.code ? `${v.code} · ` : '' }}{{ v.name }}
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

                        <!-- Open bills picker -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Apply Against Open Bills *</label>
                                <span v-if="openBills.length > 0" class="text-xxs text-(--text-muted)">{{ openBills.length }} open bill(s) for this vendor</span>
                            </div>

                            <div v-if="!form.supplier_id" class="py-6 text-center text-xxs text-(--text-muted) border border-dashed border-(--border-color) rounded-lg">
                                Pick a vendor to see their open bills.
                            </div>
                            <div v-else-if="openBillsLoading" class="py-6 text-center text-xxs text-(--text-muted) border border-dashed border-(--border-color) rounded-lg">
                                <i class="ti ti-loader-2 animate-spin" /> Loading open bills...
                            </div>
                            <div v-else-if="openBills.length === 0" class="py-6 text-center text-xxs text-(--text-muted) border border-dashed border-(--border-color) rounded-lg">
                                This vendor has no open bills (approved or partially paid).
                            </div>
                            <table v-else class="w-full text-xs">
                                <thead class="text-(--text-muted)">
                                    <tr>
                                        <th class="w-8"></th>
                                        <th class="text-left font-bold uppercase tracking-widest text-xxs py-1">Bill #</th>
                                        <th class="text-left font-bold uppercase tracking-widest text-xxs py-1">Issued / Due</th>
                                        <th class="text-right font-bold uppercase tracking-widest text-xxs py-1 w-32">Outstanding</th>
                                        <th class="text-right font-bold uppercase tracking-widest text-xxs py-1 w-32">Apply</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="b in openBills" :key="b.id" class="border-t border-(--border-color)/40">
                                        <td class="py-1.5">
                                            <input type="checkbox" :checked="!!appliedMap[b.id]" @change="toggleBillApplication(b)" class="rounded border-(--border-color)" />
                                        </td>
                                        <td class="py-1.5">
                                            <span class="font-mono font-semibold text-(--text-heading)">{{ b.billNumber }}</span>
                                            <span class="text-xxs ml-2 px-1 py-0.5 rounded font-mono badge-soft-info">{{ b.status.replace('_', ' ') }}</span>
                                        </td>
                                        <td class="py-1.5 text-xxs font-mono text-(--text-body)">
                                            {{ formatDate(b.issueDate) }}
                                            <span v-if="b.dueDate" class="text-(--text-muted)"> · due {{ b.dueDate }}</span>
                                        </td>
                                        <td class="py-1.5 text-right font-mono">{{ b.outstandingAmount.toFixed(2) }}</td>
                                        <td class="py-1.5">
                                            <input v-model.number="appliedMap[b.id]" type="number" step="0.01" min="0" :max="b.outstandingAmount"
                                                :disabled="appliedMap[b.id] === undefined"
                                                placeholder="0.00"
                                                class="form-control text-xs font-mono text-right" />
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Totals + balance check -->
                        <div class="flex justify-end">
                            <div class="w-full sm:w-80 space-y-1.5 text-xs font-mono">
                                <div class="flex items-center justify-between">
                                    <span class="text-(--text-muted) uppercase tracking-widest text-xxs">Sum Applied</span>
                                    <span class="font-semibold text-(--text-heading)">{{ sumApplied.toFixed(2) }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2 border-t border-(--border-color) pt-1.5 text-sm">
                                    <span class="text-(--text-muted) uppercase tracking-widest text-xxs">Payment Amount</span>
                                    <input v-model.number="form.amount" type="number" step="0.01" min="0.01" required
                                        class="form-control text-xs font-mono text-right w-32 font-bold" />
                                </div>
                                <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs"
                                    :class="amountMatches ? 'badge-soft-success' : 'badge-soft-warning'">
                                    <i class="ti" :class="amountMatches ? 'ti-check' : 'ti-alert-triangle'" />
                                    <span v-if="amountMatches">Balanced</span>
                                    <span v-else>Diff {{ (sumApplied - (Number(form.amount) || 0)).toFixed(2) }}</span>
                                    <button type="button" class="ml-auto text-xxs underline" @click="form.amount = Number(sumApplied.toFixed(2))">Use sum</button>
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
                        <button type="button" class="btn btn-ghost text-xs" @click="showPayModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="!canSubmit || posting">
                            <i v-if="posting" class="ti ti-loader-2 animate-spin" />
                            Record & Post
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Cancel Payment Modal -->
        <div v-if="cancelTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Cancel Payment</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="cancelTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <div class="p-3 rounded-lg badge-soft-warning text-xs flex items-start gap-2">
                        <i class="ti ti-alert-triangle mt-0.5" />
                        <div>
                            <p class="font-semibold">Posts a reversing journal entry</p>
                            <p class="text-xxs mt-0.5">Payment <span class="font-mono">{{ cancelTarget.paymentNumber }}</span> will be marked cancelled. Each linked bill's <span class="font-mono">paid_amount</span> is decremented and the status downgraded (paid → partially_paid → approved as appropriate). Audit history is preserved.</p>
                        </div>
                    </div>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="cancelTarget = null">Keep</button>
                    <button type="button" class="btn btn-danger text-xs" :disabled="cancelling" @click="onConfirmCancel">
                        <i v-if="cancelling" class="ti ti-loader-2 animate-spin" />
                        Cancel Payment
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
    BankAccount,
    Bill,
    BillStatus,
    BillPayment,
    BillPaymentStatus,
    CreateBillPaymentPayload,
} from '~/types/finance'
import type { Supplier } from '~/types/inventory'

definePageMeta({ breadcrumb: 'Pay Bill' })

const PAYMENT_METHODS = [
    { value: 'bank_transfer', label: 'Bank Transfer' },
    { value: 'cheque',        label: 'Cheque' },
    { value: 'cash',          label: 'Cash' },
    { value: 'wire',          label: 'Wire' },
]

const finance = useFinance()
const inventory = useInventory()
const toast = useToast()
const authStore = useAuthStore()

const canRead  = computed(() => authStore.hasPermission('fms.bill_payments.read'))
const canWrite = computed(() => authStore.hasPermission('fms.bill_payments.write'))

const loading    = ref(false)
const posting    = ref(false)
const cancelling = ref(false)

const payments = ref<BillPayment[]>([])
const pagination = reactive({ page: 1, limit: 25, total: 0, totalPages: 1 })
const statusFilter = ref<'' | BillPaymentStatus>('')
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

const statusBadge = (s: BillPaymentStatus) => ({
    posted:    'badge-soft-success',
    cancelled: 'badge-soft-secondary',
}[s] || 'badge-soft-secondary')

const statusBadgeForBill = (s: BillStatus) => ({
    draft:          'badge-soft-info',
    approved:       'badge-soft-primary',
    partially_paid: 'badge-soft-warning',
    paid:           'badge-soft-success',
    cancelled:      'badge-soft-secondary',
}[s] || 'badge-soft-secondary')

// ---- KPIs --------------------------------------------------------------------

const postedCount    = computed(() => payments.value.filter(p => p.status === 'posted').length)
const cancelledCount = computed(() => payments.value.filter(p => p.status === 'cancelled').length)

const monthStart = (() => { const d = new Date(); d.setDate(1); return d.toISOString().slice(0, 10) })()
const paidThisMonth = computed(() => payments.value
    .filter(p => p.status === 'posted' && p.paidOn >= monthStart)
    .reduce((s, p) => s + p.amount, 0))
const paidTodayCount = computed(() => payments.value
    .filter(p => p.status === 'posted' && p.paidOn === today).length)
const totalApplications = computed(() => payments.value.reduce((s, p) => s + p.applications.length, 0))

const kpiCountAnim = useCountUp(() => payments.value.length)
const kpiMonthAnim = useCountUp(() => paidThisMonth.value)
const kpiTodayAnim = useCountUp(() => paidTodayCount.value)
const kpiAppsAnim  = useCountUp(() => totalApplications.value)

// ---- Load --------------------------------------------------------------------

const load = async () => {
    if (!canRead.value) return
    loading.value = true
    try {
        const res = await finance.billPayments.list({
            page: pagination.page,
            limit: pagination.limit,
            status: statusFilter.value || undefined,
        })
        payments.value = res.data
        const p = (res as any).pagination
        if (p) {
            pagination.total = p.total ?? 0
            pagination.totalPages = p.totalPages ?? 1
            pagination.page = p.page ?? 1
            pagination.limit = p.limit ?? pagination.limit
        }
    } catch (err: any) {
        toast.error('Failed to load payments', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const goPage = (p: number) => {
    if (p < 1 || p > pagination.totalPages) return
    pagination.page = p
    load()
}

const setStatusFilter = (s: '' | BillPaymentStatus) => {
    if (statusFilter.value === s) return
    statusFilter.value = s
    pagination.page = 1
    load()
}

// ---- Pickers (lazy-loaded on modal open) -------------------------------------

const vendors = ref<Supplier[]>([])
const vendorsLoading = ref(false)
const banks = ref<BankAccount[]>([])
const banksLoading = ref(false)

const ensureVendorsLoaded = async () => {
    if (vendors.value.length || vendorsLoading.value) return
    vendorsLoading.value = true
    try {
        const res = await inventory.suppliers.list({ limit: 200, vendor_only: true })
        vendors.value = res.data
    } catch (err: any) {
        toast.error('Failed to load vendors', err?.data?.message)
    } finally {
        vendorsLoading.value = false
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

// ---- Open bills for chosen vendor --------------------------------------------

const openBills = ref<Bill[]>([])
const openBillsLoading = ref(false)
const appliedMap = reactive<Record<string, number>>({})

const loadOpenBillsForVendor = async (supplierId: string) => {
    openBills.value = []
    Object.keys(appliedMap).forEach(k => delete appliedMap[k])
    if (!supplierId) return
    openBillsLoading.value = true
    try {
        const res = await finance.bills.list({ supplier_id: supplierId, open_only: true, limit: 100 })
        openBills.value = res.data
    } catch (err: any) {
        toast.error('Failed to load open bills', err?.data?.message)
    } finally {
        openBillsLoading.value = false
    }
}

const toggleBillApplication = (b: Bill) => {
    if (appliedMap[b.id] !== undefined) {
        delete appliedMap[b.id]
    } else {
        // Default the applied amount to the bill's full outstanding balance.
        appliedMap[b.id] = Number(b.outstandingAmount.toFixed(2))
    }
}

// ---- Form --------------------------------------------------------------------

const showPayModal = ref(false)

const blankForm = (): CreateBillPaymentPayload => ({
    payment_number: '',
    bank_account_id: '',
    supplier_id: '',
    paid_on: today,
    amount: 0,
    currency: null,
    payment_method: null,
    reference_number: null,
    notes: null,
    applications: [],
})

const form = reactive<CreateBillPaymentPayload>(blankForm())

const resetForm = () => {
    Object.assign(form, blankForm())
    openBills.value = []
    Object.keys(appliedMap).forEach(k => delete appliedMap[k])
}

const openPayModal = () => {
    resetForm()
    showPayModal.value = true
    ensureVendorsLoaded()
    ensureBanksLoaded()
}

const onVendorChange = async () => {
    await loadOpenBillsForVendor(form.supplier_id)
}

const onBankChange = () => {
    const b = banks.value.find(x => x.id === form.bank_account_id)
    if (b && !form.currency) form.currency = b.currency
}

const sumApplied = computed(() => Object.values(appliedMap).reduce((s, v) => s + (Number(v) || 0), 0))
const amountMatches = computed(() => Math.abs(sumApplied.value - (Number(form.amount) || 0)) < 0.001 && sumApplied.value > 0)

const canSubmit = computed(() => {
    if (!form.payment_number.trim()) return false
    if (!form.supplier_id || !form.bank_account_id) return false
    if (!form.paid_on) return false
    if (!form.amount || form.amount <= 0) return false

    // Every applied row must have a positive amount that fits the bill.
    let valid = false
    for (const b of openBills.value) {
        const amt = Number(appliedMap[b.id])
        if (amt === undefined || isNaN(amt)) continue
        if (amt <= 0) return false
        if (amt > b.outstandingAmount + 0.001) return false
        valid = true
    }
    if (!valid) return false
    return amountMatches.value
})

const post = async () => {
    if (!canSubmit.value) return
    posting.value = true
    try {
        const applications = Object.entries(appliedMap)
            .filter(([, v]) => Number(v) > 0)
            .map(([bill_id, applied_amount]) => ({ bill_id, applied_amount: Number(applied_amount) }))

        const payload: CreateBillPaymentPayload = {
            ...form,
            payment_number: form.payment_number.trim(),
            reference_number: form.reference_number?.trim() || null,
            notes: form.notes?.trim() || null,
            applications,
        }
        const res = await finance.billPayments.create(payload)
        toast.success('Payment posted', res.data.paymentNumber)
        showPayModal.value = false
        await load()
    } catch (err: any) {
        toast.error('Post failed', err?.data?.message)
    } finally {
        posting.value = false
    }
}

// ---- Cancel ------------------------------------------------------------------

const cancelTarget = ref<BillPayment | null>(null)
const confirmCancel = (p: BillPayment) => { cancelTarget.value = p }

const onConfirmCancel = async () => {
    if (!cancelTarget.value) return
    cancelling.value = true
    try {
        const res = await finance.billPayments.cancel(cancelTarget.value.id)
        toast.success('Payment cancelled', res.data.paymentNumber)
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
