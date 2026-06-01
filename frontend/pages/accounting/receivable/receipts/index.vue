<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Receipts</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Settle one or many invoices from a single customer in one banking event. Posts DR Cash / CR Accounts Receivable atomically; cancellation reverses the journal and rolls invoice statuses back.</p>
                </div>
                <button v-if="canWrite" type="button" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />New Receipt
                </button>
            </header>

            <!-- KPIs -->
            <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Total</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-primary flex items-center justify-center"><i class="ti ti-cash text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiCountAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">{{ postedCount }} posted · {{ cancelledCount }} cancelled</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Received This Month</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-success flex items-center justify-center"><i class="ti ti-arrow-down-right text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ formatMoney(kpiMonthAnim) }}</p>
                    <p class="text-xxs text-(--text-muted)">Across all customers</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Today</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-info flex items-center justify-center"><i class="ti ti-calendar text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiTodayAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">Received today</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Invoices Settled</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-warning flex items-center justify-center"><i class="ti ti-file-check text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiAppsAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">Applications on page</p>
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
                <span class="text-xs text-(--text-muted)">Loading receipts...</span>
            </div>
            <div v-else-if="receipts.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-cash-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No receipts yet</h4>
                <p class="text-xs text-(--text-muted) mt-1">Record cash, cheque, or bank transfer received from a customer.</p>
            </div>

            <!-- Table -->
            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-(--bg-muted)/40 text-(--text-muted)">
                            <tr>
                                <th class="w-8 px-3 py-3"></th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Receipt #</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Customer</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Bank</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Received On</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Method</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Status</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3">Amount</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3 w-24">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="r in receipts" :key="r.id">
                                <tr class="border-t border-(--border-color) hover:bg-(--bg-muted)/40"
                                    :class="{ 'opacity-60': r.status === 'cancelled' }">
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(r.id)">
                                        <i class="ti" :class="expanded.has(r.id) ? 'ti-chevron-down' : 'ti-chevron-right'" />
                                    </td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(r.id)">
                                        <div class="font-mono font-semibold"
                                            :class="r.status === 'cancelled' ? 'text-(--text-muted) line-through' : 'text-(--text-heading)'">
                                            {{ r.receiptNumber }}
                                        </div>
                                        <div v-if="r.referenceNumber" class="text-xxs text-(--text-muted) font-mono mt-0.5">Ref: {{ r.referenceNumber }}</div>
                                    </td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(r.id)">
                                        <p class="text-(--text-heading) font-semibold truncate max-w-xs">{{ r.customer?.name || '—' }}</p>
                                    </td>
                                    <td class="px-3 py-3 text-(--text-body) cursor-pointer" @click="toggle(r.id)">
                                        <p class="truncate max-w-xs">{{ r.bankAccount?.name || '—' }}</p>
                                        <p v-if="r.bankAccount?.bankName" class="text-xxs text-(--text-muted) truncate">{{ r.bankAccount.bankName }}</p>
                                    </td>
                                    <td class="px-3 py-3 font-mono cursor-pointer" @click="toggle(r.id)">{{ formatDate(r.receivedOn) }}</td>
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
                                            title="Cancel receipt" @click.stop="confirmCancel(r)">
                                            <i class="ti ti-rotate-2" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="expanded.has(r.id)" class="border-t border-(--border-color) bg-(--bg-muted)/20">
                                    <td colspan="9" class="px-4 py-3">
                                        <table class="w-full text-xxs font-mono">
                                            <thead class="text-(--text-muted)">
                                                <tr>
                                                    <th class="text-left font-bold uppercase tracking-widest py-1">Invoice</th>
                                                    <th class="text-left font-bold uppercase tracking-widest py-1">Status</th>
                                                    <th class="text-right font-bold uppercase tracking-widest py-1 w-32">Invoice Total</th>
                                                    <th class="text-right font-bold uppercase tracking-widest py-1 w-32">Applied</th>
                                                    <th class="text-right font-bold uppercase tracking-widest py-1 w-32">Outstanding</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="a in r.applications" :key="a.id" class="border-t border-(--border-color)/40">
                                                    <td class="py-1.5">
                                                        <span class="text-(--text-heading) font-semibold">{{ a.invoice?.invoiceNumber || '—' }}</span>
                                                        <span v-if="a.invoice?.dueDate" class="text-(--text-muted) ml-2">due {{ formatDate(a.invoice.dueDate) }}</span>
                                                    </td>
                                                    <td class="py-1.5">
                                                        <span v-if="a.invoice?.status" class="text-xxs px-1.5 py-0.5 rounded font-mono"
                                                            :class="invoiceStatusBadge(a.invoice.status)">{{ a.invoice.status }}</span>
                                                    </td>
                                                    <td class="text-right py-1.5">{{ a.invoice?.totalAmount?.toFixed(2) ?? '—' }}</td>
                                                    <td class="text-right py-1.5 font-semibold text-(--text-heading)">{{ a.appliedAmount.toFixed(2) }}</td>
                                                    <td class="text-right py-1.5"
                                                        :class="(a.invoice?.outstandingAmount ?? 0) > 0 ? 'text-(--color-warning)' : 'text-(--color-success)'">
                                                        {{ a.invoice?.outstandingAmount?.toFixed(2) ?? '—' }}
                                                    </td>
                                                </tr>
                                                <tr class="border-t border-(--border-color) font-bold text-(--text-heading)">
                                                    <td class="py-1.5 uppercase tracking-widest text-xxs" colspan="3">Total Applied</td>
                                                    <td class="text-right py-1.5">{{ r.amount.toFixed(2) }}</td>
                                                    <td></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                        <p v-if="r.arAccount" class="text-xxs text-(--text-muted) mt-3"><strong>AR Account:</strong> <span class="font-mono">{{ r.arAccount.code }}</span> · {{ r.arAccount.name }}</p>
                                        <p v-if="r.notes" class="text-xxs text-(--text-muted) mt-1"><strong>Notes:</strong> {{ r.notes }}</p>
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
                    <h3 class="font-semibold text-sm">Record Receipt</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="post">
                    <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                        <!-- Header -->
                        <div class="grid grid-cols-4 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Receipt # *</label>
                                <input v-model="form.receipt_number" type="text" required maxlength="64"
                                    placeholder="e.g. RCPT-2026-001" class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Received On *</label>
                                <input v-model="form.received_on" type="date" required class="form-control text-xs font-mono" />
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

                        <!-- Customer + Bank -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Customer *</label>
                                <select v-model="form.customer_id" required class="form-control text-xs" :disabled="customersLoading"
                                    @change="onCustomerChange">
                                    <option value="">— Pick customer —</option>
                                    <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
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

                        <!-- AR Account -->
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">AR Account (Asset) *</label>
                            <select v-model="form.ar_account_id" required class="form-control text-xs" :disabled="accountsLoading">
                                <option value="">— Pick AR account —</option>
                                <option v-for="a in assetAccounts" :key="a.id" :value="a.id">
                                    {{ a.code }} · {{ a.name }}
                                </option>
                            </select>
                            <p class="text-xxs text-(--text-muted)">The Accounts Receivable account each CR line lands on. Default is whatever `fms.ar_account_code` resolves to in Chart of Accounts.</p>
                        </div>

                        <!-- Open Invoices -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">
                                    Open Invoices
                                    <span v-if="form.customer_id" class="ml-2 text-(--text-muted) normal-case">for selected customer</span>
                                </label>
                                <span v-if="openInvoicesLoading" class="text-xxs text-(--text-muted)">Loading...</span>
                            </div>
                            <div v-if="!form.customer_id" class="glass-card rounded-lg p-4 text-center text-xxs text-(--text-muted)">
                                Pick a customer to see their open invoices.
                            </div>
                            <div v-else-if="!openInvoicesLoading && openInvoices.length === 0"
                                class="glass-card rounded-lg p-4 text-center text-xxs text-(--text-muted)">
                                No open invoices for this customer.
                            </div>
                            <table v-else-if="openInvoices.length > 0" class="w-full text-xs">
                                <thead class="text-(--text-muted)">
                                    <tr>
                                        <th class="w-8"></th>
                                        <th class="text-left font-bold uppercase tracking-widest text-xxs py-1">Invoice</th>
                                        <th class="text-left font-bold uppercase tracking-widest text-xxs py-1">Due</th>
                                        <th class="text-right font-bold uppercase tracking-widest text-xxs py-1 w-32">Total</th>
                                        <th class="text-right font-bold uppercase tracking-widest text-xxs py-1 w-32">Outstanding</th>
                                        <th class="text-right font-bold uppercase tracking-widest text-xxs py-1 w-32">Apply</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="inv in openInvoices" :key="inv.id" class="border-t border-(--border-color)/40">
                                        <td class="py-1.5 pr-2">
                                            <input type="checkbox" :checked="isApplied(inv.id)" @change="toggleApply(inv)" />
                                        </td>
                                        <td class="py-1.5 font-mono text-(--text-heading)">{{ inv.invoiceNumber }}</td>
                                        <td class="py-1.5 font-mono"
                                            :class="dueColor(inv.dueDate)">{{ formatDate(inv.dueDate) }}</td>
                                        <td class="py-1.5 text-right font-mono">{{ inv.totalAmount.toFixed(2) }}</td>
                                        <td class="py-1.5 text-right font-mono font-semibold text-(--color-warning)">{{ inv.outstandingAmount.toFixed(2) }}</td>
                                        <td class="py-1.5 text-right">
                                            <input v-if="isApplied(inv.id)" v-model.number="getApply(inv.id)!.applied_amount"
                                                type="number" step="0.01" min="0.01" :max="inv.outstandingAmount"
                                                class="form-control text-xs font-mono text-right" />
                                            <span v-else class="text-(--text-muted)">—</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Totals -->
                        <div class="flex justify-end">
                            <div class="w-full sm:w-80 space-y-1.5 text-xs font-mono">
                                <div class="flex items-center justify-between">
                                    <span class="text-(--text-muted) uppercase tracking-widest text-xxs">Sum Applied</span>
                                    <span class="font-semibold text-(--text-heading)">{{ formSumApplied.toFixed(2) }}</span>
                                </div>
                                <div class="flex items-center justify-between gap-2 border-t border-(--border-color) pt-1.5 text-sm">
                                    <span class="text-(--text-muted) uppercase tracking-widest text-xxs">Receipt Amount *</span>
                                    <input v-model.number="form.amount" type="number" step="0.01" min="0.01" required
                                        class="form-control text-xs font-mono text-right w-32 font-bold" />
                                </div>
                                <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg text-xs"
                                    :class="amountMatches ? 'badge-soft-success' : 'badge-soft-warning'">
                                    <i class="ti" :class="amountMatches ? 'ti-check' : 'ti-alert-triangle'" />
                                    <span v-if="amountMatches">Balanced</span>
                                    <span v-else>Diff {{ (formSumApplied - (Number(form.amount) || 0)).toFixed(2) }}</span>
                                    <button type="button" class="ml-auto text-xxs underline" @click="form.amount = Number(formSumApplied.toFixed(2))">Use sum</button>
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
                    <h3 class="font-semibold text-sm">Cancel Receipt</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="cancelTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <div class="p-3 rounded-lg badge-soft-warning text-xs flex items-start gap-2">
                        <i class="ti ti-alert-triangle mt-0.5" />
                        <div>
                            <p class="font-semibold">Posts a reversing journal entry</p>
                            <p class="text-xxs mt-0.5">Receipt <span class="font-mono">{{ cancelTarget.receiptNumber }}</span> will be marked cancelled. The original posting stays in the audit log; each linked invoice's paid balance will roll back by the applied amount, downgrading <em>paid</em> → <em>confirmed</em> if needed.</p>
                        </div>
                    </div>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="cancelTarget = null">Keep</button>
                    <button type="button" class="btn btn-danger text-xs" :disabled="cancelling" @click="onConfirmCancel">
                        <i v-if="cancelling" class="ti ti-loader-2 animate-spin" />
                        Cancel Receipt
                    </button>
                </footer>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useFinance } from '~/composables/useFinance'
import { useSales } from '~/composables/useSales'
import { useToast } from '~/composables/useToast'
import { useCountUp } from '~/composables/useCountUp'
import { useAuthStore } from '~/stores/auth'
import type {
    Account,
    BankAccount,
    Receipt,
    ReceiptStatus,
    ReceiptOpenInvoice,
    CreateReceiptPayload,
    CreateReceiptApplicationPayload,
} from '~/types/finance'

definePageMeta({ breadcrumb: 'Receipts' })

interface CustomerLite { id: string; name: string }

const PAYMENT_METHODS = [
    { value: 'bank_transfer', label: 'Bank Transfer' },
    { value: 'cheque',        label: 'Cheque' },
    { value: 'cash',          label: 'Cash' },
    { value: 'wire',          label: 'Wire' },
]

const finance = useFinance()
const sales = useSales()
const toast = useToast()
const authStore = useAuthStore()

const canRead  = computed(() => authStore.hasPermission('fms.receipts.read'))
const canWrite = computed(() => authStore.hasPermission('fms.receipts.write'))

const loading    = ref(false)
const posting    = ref(false)
const cancelling = ref(false)

const receipts = ref<Receipt[]>([])
const pagination = reactive({ page: 1, limit: 25, total: 0, totalPages: 1 })
const statusFilter = ref<'' | ReceiptStatus>('')
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
const statusBadge = (s: ReceiptStatus) => ({
    posted:    'badge-soft-success',
    cancelled: 'badge-soft-secondary',
}[s] || 'badge-soft-secondary')
const invoiceStatusBadge = (s: string) => ({
    new:       'badge-soft-info',
    confirmed: 'badge-soft-warning',
    paid:      'badge-soft-success',
    cancelled: 'badge-soft-secondary',
}[s] || 'badge-soft-secondary')
const dueColor = (due: string | null) => {
    if (!due) return 'text-(--text-muted)'
    const days = (new Date(due).getTime() - new Date(today).getTime()) / 86_400_000
    if (days < 0) return 'text-(--color-danger)'
    if (days < 7) return 'text-(--color-warning)'
    return 'text-(--text-body)'
}

// ---- KPIs --------------------------------------------------------------------

const postedCount    = computed(() => receipts.value.filter(r => r.status === 'posted').length)
const cancelledCount = computed(() => receipts.value.filter(r => r.status === 'cancelled').length)

const monthStart = (() => { const d = new Date(); d.setDate(1); return d.toISOString().slice(0, 10) })()
const receivedThisMonth = computed(() => receipts.value
    .filter(r => r.status === 'posted' && r.receivedOn >= monthStart)
    .reduce((s, r) => s + r.amount, 0))
const todayCount = computed(() => receipts.value
    .filter(r => r.status === 'posted' && r.receivedOn === today).length)
const totalApps = computed(() => receipts.value.reduce((s, r) => s + r.applications.length, 0))

const kpiCountAnim = useCountUp(() => receipts.value.length)
const kpiMonthAnim = useCountUp(() => receivedThisMonth.value)
const kpiTodayAnim = useCountUp(() => todayCount.value)
const kpiAppsAnim  = useCountUp(() => totalApps.value)

// ---- Load --------------------------------------------------------------------

const load = async () => {
    if (!canRead.value) return
    loading.value = true
    try {
        const res = await finance.receipts.list({
            page: pagination.page,
            limit: pagination.limit,
            status: statusFilter.value || undefined,
        })
        receipts.value = res.data
        const p = (res as any).pagination
        if (p) {
            pagination.total = p.total ?? 0
            pagination.totalPages = p.totalPages ?? 1
            pagination.page = p.page ?? 1
            pagination.limit = p.limit ?? pagination.limit
        }
    } catch (err: any) {
        toast.error('Failed to load receipts', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const goPage = (p: number) => {
    if (p < 1 || p > pagination.totalPages) return
    pagination.page = p
    load()
}

const setStatusFilter = (s: '' | ReceiptStatus) => {
    if (statusFilter.value === s) return
    statusFilter.value = s
    pagination.page = 1
    load()
}

// ---- Pickers (lazy-loaded on modal open) -------------------------------------

const customers = ref<CustomerLite[]>([])
const customersLoading = ref(false)
const banks = ref<BankAccount[]>([])
const banksLoading = ref(false)
const accountsList = ref<Account[]>([])
const accountsLoading = ref(false)
const assetAccounts = computed(() => accountsList.value.filter(a => a.type === 'asset'))

const ensureCustomersLoaded = async () => {
    if (customers.value.length || customersLoading.value) return
    customersLoading.value = true
    try {
        const res = await sales.catalogue.listCustomers({ limit: 200 })
        customers.value = res.data.map((c: any) => ({ id: c.id, name: c.name }))
    } catch (err: any) {
        toast.error('Failed to load customers', err?.data?.message)
    } finally {
        customersLoading.value = false
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
        // Best-effort default AR pick: code 1200 (matches InvoiceService DEFAULT_AR_CODE).
        if (!form.ar_account_id) {
            const def = flat.find(a => a.type === 'asset' && a.code === '1200')
            if (def) form.ar_account_id = def.id
        }
    } catch (err: any) {
        toast.error('Failed to load accounts', err?.data?.message)
    } finally {
        accountsLoading.value = false
    }
}

// ---- Open invoices -----------------------------------------------------------

const openInvoices = ref<ReceiptOpenInvoice[]>([])
const openInvoicesLoading = ref(false)

const loadOpenInvoices = async (customerId: string) => {
    if (!customerId) { openInvoices.value = []; return }
    openInvoicesLoading.value = true
    try {
        const res = await finance.receipts.openInvoicesForCustomer(customerId)
        openInvoices.value = res.data
    } catch (err: any) {
        toast.error('Failed to load open invoices', err?.data?.message)
        openInvoices.value = []
    } finally {
        openInvoicesLoading.value = false
    }
}

// ---- Form --------------------------------------------------------------------

const showFormModal = ref(false)

const blankForm = (): CreateReceiptPayload => ({
    receipt_number: '',
    customer_id: '',
    bank_account_id: '',
    ar_account_id: '',
    received_on: today,
    amount: 0,
    currency: 'USD',
    payment_method: null,
    reference_number: null,
    notes: null,
    applications: [],
})

const form = reactive<CreateReceiptPayload>(blankForm())

const resetForm = () => {
    Object.assign(form, blankForm())
    form.applications = []
}

const openCreateModal = () => {
    resetForm()
    openInvoices.value = []
    showFormModal.value = true
    ensureCustomersLoaded()
    ensureBanksLoaded()
    ensureAccountsLoaded()
}

const onCustomerChange = () => {
    form.applications = []
    loadOpenInvoices(form.customer_id)
}

const onBankChange = () => {
    const b = banks.value.find(x => x.id === form.bank_account_id)
    if (b) form.currency = b.currency
}

const isApplied = (invoiceId: string) =>
    form.applications.some(a => a.invoice_id === invoiceId)

const getApply = (invoiceId: string): CreateReceiptApplicationPayload | undefined =>
    form.applications.find(a => a.invoice_id === invoiceId)

const toggleApply = (inv: ReceiptOpenInvoice) => {
    const idx = form.applications.findIndex(a => a.invoice_id === inv.id)
    if (idx >= 0) {
        form.applications.splice(idx, 1)
    } else {
        form.applications.push({ invoice_id: inv.id, applied_amount: inv.outstandingAmount })
    }
}

const formSumApplied = computed(() =>
    form.applications.reduce((s, a) => s + (Number(a.applied_amount) || 0), 0)
)
const amountMatches = computed(() =>
    Math.abs(formSumApplied.value - (Number(form.amount) || 0)) < 0.005
)

const canSubmit = computed(() => {
    if (!form.receipt_number.trim()) return false
    if (!form.customer_id || !form.bank_account_id || !form.ar_account_id) return false
    if (!form.received_on) return false
    if (!form.amount || form.amount <= 0) return false
    if (!form.applications.length) return false
    if (!amountMatches.value) return false
    for (const a of form.applications) {
        const inv = openInvoices.value.find(i => i.id === a.invoice_id)
        if (!inv) return false
        if (!a.applied_amount || a.applied_amount <= 0) return false
        if (a.applied_amount > inv.outstandingAmount + 0.001) return false
    }
    return true
})

const post = async () => {
    if (!canSubmit.value) return
    posting.value = true
    try {
        const payload: CreateReceiptPayload = {
            ...form,
            receipt_number: form.receipt_number.trim(),
            reference_number: form.reference_number?.trim() || null,
            notes: form.notes?.trim() || null,
            applications: form.applications.map(a => ({
                invoice_id: a.invoice_id,
                applied_amount: Number(a.applied_amount),
            })),
        }
        const res = await finance.receipts.create(payload)
        toast.success('Receipt posted', res.data.receiptNumber)
        showFormModal.value = false
        await load()
    } catch (err: any) {
        toast.error('Record failed', err?.data?.message)
    } finally {
        posting.value = false
    }
}

// ---- Cancel ------------------------------------------------------------------

const cancelTarget = ref<Receipt | null>(null)
const confirmCancel = (r: Receipt) => { cancelTarget.value = r }
const onConfirmCancel = async () => {
    if (!cancelTarget.value) return
    cancelling.value = true
    try {
        const res = await finance.receipts.cancel(cancelTarget.value.id)
        toast.success('Receipt cancelled', res.data.receiptNumber)
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
