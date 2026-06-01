<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Credit Notes</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Customer-side AR adjustment for returns, billing errors, or goodwill credits. Posts DR Sales Returns / CR Accounts Receivable. When linked to an invoice, rolls into its paid balance — closing outstanding without cash exchanging hands.</p>
                </div>
                <button v-if="canWrite" type="button" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />New Credit Note
                </button>
            </header>

            <!-- KPIs -->
            <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Total</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-primary flex items-center justify-center"><i class="ti ti-file-arrow-left text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiCountAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">{{ issuedCount }} issued · {{ cancelledCount }} cancelled</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Issued This Month</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-warning flex items-center justify-center"><i class="ti ti-arrow-up-right text-sm" /></span>
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
                    <p class="text-xxs text-(--text-muted)">Issued today</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Linked to Invoice</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-success flex items-center justify-center"><i class="ti ti-link text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiLinkedAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">{{ unlinkedCount }} unlinked</p>
                </div>
            </section>

            <!-- Status filter chips -->
            <section class="flex items-center gap-2 flex-wrap">
                <button type="button" class="chip" :class="{ active: statusFilter === '' }" @click="setStatusFilter('')">All</button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'issued' }" @click="setStatusFilter('issued')">
                    <i class="ti ti-check" /> Issued
                </button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'cancelled' }" @click="setStatusFilter('cancelled')">
                    <i class="ti ti-x" /> Cancelled
                </button>
            </section>

            <!-- Loading / Empty -->
            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading credit notes...</span>
            </div>
            <div v-else-if="notes.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-file-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No credit notes yet</h4>
                <p class="text-xs text-(--text-muted) mt-1">Issue a credit for a customer return, billing error, or goodwill.</p>
            </div>

            <!-- Table -->
            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-(--bg-muted)/40 text-(--text-muted)">
                            <tr>
                                <th class="w-8 px-3 py-3"></th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Credit #</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Customer</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Linked Invoice</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Issue Date</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Status</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3">Amount</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3 w-24">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="n in notes" :key="n.id">
                                <tr class="border-t border-(--border-color) hover:bg-(--bg-muted)/40"
                                    :class="{ 'opacity-60': n.status === 'cancelled' }">
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(n.id)">
                                        <i class="ti" :class="expanded.has(n.id) ? 'ti-chevron-down' : 'ti-chevron-right'" />
                                    </td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(n.id)">
                                        <div class="font-mono font-semibold"
                                            :class="n.status === 'cancelled' ? 'text-(--text-muted) line-through' : 'text-(--text-heading)'">
                                            {{ n.creditNoteNumber }}
                                        </div>
                                    </td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(n.id)">
                                        <p class="text-(--text-heading) font-semibold truncate max-w-xs">{{ n.customer?.name || '—' }}</p>
                                    </td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(n.id)">
                                        <span v-if="n.invoice" class="font-mono text-(--text-heading)">{{ n.invoice.invoiceNumber }}</span>
                                        <span v-else class="text-xxs px-1.5 py-0.5 rounded font-mono badge-soft-secondary">unlinked</span>
                                    </td>
                                    <td class="px-3 py-3 font-mono cursor-pointer" @click="toggle(n.id)">{{ formatDate(n.issueDate) }}</td>
                                    <td class="px-3 py-3 cursor-pointer" @click="toggle(n.id)">
                                        <span class="text-xxs px-1.5 py-0.5 rounded font-mono" :class="statusBadge(n.status)">{{ n.status }}</span>
                                    </td>
                                    <td class="px-3 py-3 text-right font-mono font-semibold text-(--text-heading) cursor-pointer" @click="toggle(n.id)">
                                        {{ n.currency }} {{ n.amount.toFixed(2) }}
                                    </td>
                                    <td class="px-3 py-3 text-right">
                                        <button v-if="canWrite && n.isCancellable" type="button" class="action-btn action-btn-danger"
                                            title="Cancel credit note" @click.stop="confirmCancel(n)">
                                            <i class="ti ti-rotate-2" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="expanded.has(n.id)" class="border-t border-(--border-color) bg-(--bg-muted)/20">
                                    <td colspan="8" class="px-4 py-3 space-y-2 text-xxs">
                                        <p class="text-(--text-body)"><strong class="text-(--text-muted) uppercase tracking-widest">Reason:</strong> {{ n.reason }}</p>
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 font-mono">
                                            <p class="text-(--text-muted)">
                                                <strong>DR:</strong>
                                                <span v-if="n.salesReturnsAccount" class="text-(--text-heading)">{{ n.salesReturnsAccount.code }}</span>
                                                <span v-if="n.salesReturnsAccount" class="ml-1">{{ n.salesReturnsAccount.name }}</span>
                                            </p>
                                            <p class="text-(--text-muted)">
                                                <strong>CR:</strong>
                                                <span v-if="n.arAccount" class="text-(--text-heading)">{{ n.arAccount.code }}</span>
                                                <span v-if="n.arAccount" class="ml-1">{{ n.arAccount.name }}</span>
                                            </p>
                                        </div>
                                        <div v-if="n.invoice" class="rounded-lg badge-soft-info p-2 flex items-center gap-2">
                                            <i class="ti ti-link" />
                                            <span class="font-mono">{{ n.invoice.invoiceNumber }}</span>
                                            <span>· total {{ n.invoice.totalAmount.toFixed(2) }}</span>
                                            <span>· paid {{ n.invoice.paidAmount.toFixed(2) }}</span>
                                            <span>· outstanding {{ n.invoice.outstandingAmount.toFixed(2) }}</span>
                                            <span class="ml-auto text-xxs px-1.5 py-0.5 rounded font-mono"
                                                :class="invoiceStatusBadge(n.invoice.status)">{{ n.invoice.status }}</span>
                                        </div>
                                        <p v-if="n.notes" class="text-(--text-muted)"><strong>Notes:</strong> {{ n.notes }}</p>
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

        <!-- Issue Modal -->
        <div v-if="showFormModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-2xl bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Issue Credit Note</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="post">
                    <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                        <div class="grid grid-cols-3 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Credit # *</label>
                                <input v-model="form.credit_note_number" type="text" required maxlength="64"
                                    placeholder="e.g. CN-2026-001" class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Issue Date *</label>
                                <input v-model="form.issue_date" type="date" required class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Amount *</label>
                                <input v-model.number="form.amount" type="number" step="0.01" min="0.01" required
                                    class="form-control text-xs font-mono text-right" />
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Customer *</label>
                            <select v-model="form.customer_id" required class="form-control text-xs" :disabled="customersLoading"
                                @change="onCustomerChange">
                                <option value="">— Pick customer —</option>
                                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Linked Invoice (optional)</label>
                            <select v-model="form.invoice_id" class="form-control text-xs"
                                :disabled="!form.customer_id || openInvoicesLoading">
                                <option :value="null">— Unlinked (standing credit) —</option>
                                <option v-for="inv in openInvoices" :key="inv.id" :value="inv.id">
                                    {{ inv.invoiceNumber }} — outstanding {{ inv.outstandingAmount.toFixed(2) }}
                                </option>
                            </select>
                            <p v-if="!form.customer_id" class="text-xxs text-(--text-muted)">Pick a customer to see their open invoices.</p>
                            <p v-else-if="!openInvoicesLoading && openInvoices.length === 0" class="text-xxs text-(--text-muted)">No open invoices for this customer. Issue an unlinked credit instead.</p>
                            <p v-if="selectedInvoice" class="text-xxs text-(--text-muted)">
                                Outstanding:
                                <span class="font-mono font-semibold text-(--text-heading)">{{ selectedInvoice.outstandingAmount.toFixed(2) }}</span>
                                · Issue:
                                <span class="font-mono">{{ formatDate(selectedInvoice.invoiceDate) }}</span>
                                · Due:
                                <span class="font-mono">{{ formatDate(selectedInvoice.dueDate) }}</span>
                            </p>
                            <p v-if="exceedsOutstanding" class="text-xxs text-(--color-danger) flex items-center gap-1.5">
                                <i class="ti ti-x" />
                                Amount exceeds invoice outstanding by {{ (form.amount - (selectedInvoice?.outstandingAmount ?? 0)).toFixed(2) }}.
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Sales Returns Account (DR) *</label>
                                <select v-model="form.sales_returns_account_id" required class="form-control text-xs" :disabled="accountsLoading">
                                    <option value="">— Pick DR account —</option>
                                    <option v-for="a in salesReturnsAccounts" :key="a.id" :value="a.id">
                                        {{ a.code }} · {{ a.name }} ({{ a.type }})
                                    </option>
                                </select>
                                <p class="text-xxs text-(--text-muted)">Revenue (contra) or expense type.</p>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">AR Account (CR) *</label>
                                <select v-model="form.ar_account_id" required class="form-control text-xs" :disabled="accountsLoading">
                                    <option value="">— Pick AR account —</option>
                                    <option v-for="a in assetAccounts" :key="a.id" :value="a.id">
                                        {{ a.code }} · {{ a.name }}
                                    </option>
                                </select>
                                <p class="text-xxs text-(--text-muted)">Defaults to code 1200 (matches Invoice posting).</p>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Currency</label>
                            <input v-model="form.currency" type="text" maxlength="3"
                                class="form-control text-xs font-mono uppercase w-24"
                                @input="form.currency = (form.currency || '').toUpperCase()" />
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Reason *</label>
                            <textarea v-model="form.reason" rows="2" required maxlength="500"
                                placeholder="Return of defective items / billing error / goodwill credit / ..."
                                class="form-control text-xs resize-none" />
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
                    <h3 class="font-semibold text-sm">Cancel Credit Note</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="cancelTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <div class="p-3 rounded-lg badge-soft-warning text-xs flex items-start gap-2">
                        <i class="ti ti-alert-triangle mt-0.5" />
                        <div>
                            <p class="font-semibold">Posts a reversing journal entry</p>
                            <p class="text-xxs mt-0.5">Credit note <span class="font-mono">{{ cancelTarget.creditNoteNumber }}</span> will be marked cancelled. The original posting stays in the audit log; if linked, the invoice's paid balance rolls back by <span class="font-mono">{{ cancelTarget.amount.toFixed(2) }}</span> and demotes <em>paid</em> → <em>confirmed</em> if needed.</p>
                        </div>
                    </div>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="cancelTarget = null">Keep</button>
                    <button type="button" class="btn btn-danger text-xs" :disabled="cancelling" @click="onConfirmCancel">
                        <i v-if="cancelling" class="ti ti-loader-2 animate-spin" />
                        Cancel Credit Note
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
    CreditNote,
    CreditNoteStatus,
    CreateCreditNotePayload,
    ReceiptOpenInvoice,
} from '~/types/finance'

definePageMeta({ breadcrumb: 'Credit Notes' })

interface CustomerLite { id: string; name: string }

const finance = useFinance()
const sales = useSales()
const toast = useToast()
const authStore = useAuthStore()

const canRead  = computed(() => authStore.hasPermission('fms.credit_notes.read'))
const canWrite = computed(() => authStore.hasPermission('fms.credit_notes.write'))

const loading    = ref(false)
const posting    = ref(false)
const cancelling = ref(false)

const notes = ref<CreditNote[]>([])
const pagination = reactive({ page: 1, limit: 25, total: 0, totalPages: 1 })
const statusFilter = ref<'' | CreditNoteStatus>('')
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
const statusBadge = (s: CreditNoteStatus) => ({
    issued:    'badge-soft-success',
    cancelled: 'badge-soft-secondary',
}[s] || 'badge-soft-secondary')
const invoiceStatusBadge = (s: string) => ({
    new:       'badge-soft-info',
    confirmed: 'badge-soft-warning',
    paid:      'badge-soft-success',
    cancelled: 'badge-soft-secondary',
}[s] || 'badge-soft-secondary')

// ---- KPIs --------------------------------------------------------------------

const issuedCount    = computed(() => notes.value.filter(n => n.status === 'issued').length)
const cancelledCount = computed(() => notes.value.filter(n => n.status === 'cancelled').length)
const linkedCount    = computed(() => notes.value.filter(n => n.invoiceId).length)
const unlinkedCount  = computed(() => notes.value.length - linkedCount.value)

const monthStart = (() => { const d = new Date(); d.setDate(1); return d.toISOString().slice(0, 10) })()
const issuedThisMonth = computed(() => notes.value
    .filter(n => n.status === 'issued' && n.issueDate >= monthStart)
    .reduce((s, n) => s + n.amount, 0))
const todayCount = computed(() => notes.value
    .filter(n => n.status === 'issued' && n.issueDate === today).length)

const kpiCountAnim  = useCountUp(() => notes.value.length)
const kpiMonthAnim  = useCountUp(() => issuedThisMonth.value)
const kpiTodayAnim  = useCountUp(() => todayCount.value)
const kpiLinkedAnim = useCountUp(() => linkedCount.value)

// ---- Load --------------------------------------------------------------------

const load = async () => {
    if (!canRead.value) return
    loading.value = true
    try {
        const res = await finance.creditNotes.list({
            page: pagination.page,
            limit: pagination.limit,
            status: statusFilter.value || undefined,
        })
        notes.value = res.data
        const p = (res as any).pagination
        if (p) {
            pagination.total = p.total ?? 0
            pagination.totalPages = p.totalPages ?? 1
            pagination.page = p.page ?? 1
            pagination.limit = p.limit ?? pagination.limit
        }
    } catch (err: any) {
        toast.error('Failed to load credit notes', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const goPage = (p: number) => {
    if (p < 1 || p > pagination.totalPages) return
    pagination.page = p
    load()
}

const setStatusFilter = (s: '' | CreditNoteStatus) => {
    if (statusFilter.value === s) return
    statusFilter.value = s
    pagination.page = 1
    load()
}

// ---- Pickers (lazy-loaded on modal open) -------------------------------------

const customers = ref<CustomerLite[]>([])
const customersLoading = ref(false)
const accountsList = ref<Account[]>([])
const accountsLoading = ref(false)
const assetAccounts = computed(() => accountsList.value.filter(a => a.type === 'asset'))
const salesReturnsAccounts = computed(() =>
    accountsList.value.filter(a => a.type === 'revenue' || a.type === 'expense')
)

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
        // Default AR to code 1200 (matches InvoiceService DEFAULT_AR_CODE).
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

// ---- Open invoices for selected customer -------------------------------------

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

const blankForm = (): CreateCreditNotePayload => ({
    credit_note_number: '',
    customer_id: '',
    invoice_id: null,
    sales_returns_account_id: '',
    ar_account_id: '',
    issue_date: today,
    amount: 0,
    currency: 'USD',
    reason: '',
    notes: null,
})

const form = reactive<CreateCreditNotePayload>(blankForm())

const resetForm = () => Object.assign(form, blankForm())

const openCreateModal = () => {
    resetForm()
    openInvoices.value = []
    showFormModal.value = true
    ensureCustomersLoaded()
    ensureAccountsLoaded()
}

const onCustomerChange = () => {
    form.invoice_id = null
    loadOpenInvoices(form.customer_id)
}

const selectedInvoice = computed(() =>
    openInvoices.value.find(i => i.id === form.invoice_id) || null
)

const exceedsOutstanding = computed(() => {
    if (!selectedInvoice.value) return false
    return (Number(form.amount) || 0) > selectedInvoice.value.outstandingAmount + 0.001
})

const canSubmit = computed(() => {
    if (!form.credit_note_number.trim()) return false
    if (!form.customer_id) return false
    if (!form.sales_returns_account_id || !form.ar_account_id) return false
    if (!form.issue_date) return false
    if (!form.amount || form.amount <= 0) return false
    if (!form.reason.trim()) return false
    if (exceedsOutstanding.value) return false
    return true
})

const post = async () => {
    if (!canSubmit.value) return
    posting.value = true
    try {
        const payload: CreateCreditNotePayload = {
            ...form,
            credit_note_number: form.credit_note_number.trim(),
            reason: form.reason.trim(),
            notes: form.notes?.trim() || null,
            invoice_id: form.invoice_id || null,
        }
        const res = await finance.creditNotes.create(payload)
        toast.success('Credit note issued', res.data.creditNoteNumber)
        showFormModal.value = false
        await load()
    } catch (err: any) {
        toast.error('Issue failed', err?.data?.message)
    } finally {
        posting.value = false
    }
}

// ---- Cancel ------------------------------------------------------------------

const cancelTarget = ref<CreditNote | null>(null)
const confirmCancel = (n: CreditNote) => { cancelTarget.value = n }
const onConfirmCancel = async () => {
    if (!cancelTarget.value) return
    cancelling.value = true
    try {
        const res = await finance.creditNotes.cancel(cancelTarget.value.id)
        toast.success('Credit note cancelled', res.data.creditNoteNumber)
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
