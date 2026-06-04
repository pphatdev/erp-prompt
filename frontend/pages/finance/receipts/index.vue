<template>
    <NuxtLayout name="default">
        <div class="space-y-5">
            <header>
                <h1 class="text-xl font-semibold text-(--text-heading) leading-tight">Customer Receipts</h1>
                <p class="text-xs text-(--text-muted) mt-1">
                    Record cash, bank, card or wallet receipts and apply them against open invoices.
                    Each receipt posts a balanced
                    <code class="font-mono">DR Bank / CR AR</code> journal; cancelling reverses it cleanly.
                </p>
            </header>

            <!-- Sticky toolbar -->
            <section class="sticky top-16 z-20 py-2 bg-(--bg-layout)/90 backdrop-blur">
                <div class="flex items-center gap-3 flex-wrap">
                    <div class="relative flex-1 min-w-[220px] max-w-md">
                        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
                        <input v-model="filters.search" type="search" placeholder="Search number, reference, customer..."
                            class="w-full pl-9 pr-9 py-2 text-xs rounded-lg bg-(--bg-card) border border-(--border-color) text-(--text-heading) placeholder:text-(--text-muted) focus:outline-none focus:border-(--color-primary) focus:ring-2 focus:ring-(--color-primary)/20"
                            @input="onFilterChange" />
                        <button v-if="filters.search" type="button"
                            class="absolute right-2 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full inline-flex items-center justify-center text-(--text-muted) hover:bg-(--bg-muted) hover:text-(--text-heading)"
                            aria-label="Clear search" @click="filters.search = ''; onFilterChange()">
                            <i class="ti ti-x text-[12px]" />
                        </button>
                    </div>

                    <div class="ml-auto flex items-center gap-2 flex-wrap">
                        <div class="segmented" role="group" aria-label="Status filter">
                            <button type="button" class="seg-btn" :class="{ active: filters.status === '' }"
                                @click="setStatus('')"><i class="ti ti-list" /> All</button>
                            <button type="button" class="seg-btn" :class="{ active: filters.status === 'posted' }"
                                @click="setStatus('posted')"><i class="ti ti-circle-check" /> Posted</button>
                            <button type="button" class="seg-btn" :class="{ active: filters.status === 'cancelled' }"
                                @click="setStatus('cancelled')"><i class="ti ti-circle-x" /> Cancelled</button>
                        </div>

                        <button class="btn btn-primary text-xs" @click="openCreateModal">
                            <i class="ti ti-plus" /> Record receipt
                        </button>
                    </div>
                </div>
            </section>

            <!-- Alert -->
            <div v-if="alert.msg"
                class="px-4 py-3 rounded-lg flex items-center justify-between text-xs font-semibold"
                :class="alert.type === 'success' ? 'badge-soft-success' : 'badge-soft-danger'">
                <span class="flex items-center gap-2">
                    <i :class="['ti', alert.type === 'success' ? 'ti-check' : 'ti-alert-triangle']" />
                    {{ alert.msg }}
                </span>
                <button class="text-current" @click="alert.msg = ''"><i class="ti ti-x" /></button>
            </div>

            <!-- Results -->
            <div v-if="!loading && pagination.total > 0" class="flex items-center justify-between text-xxs text-(--text-muted)">
                <span>{{ resultsSummary }}</span>
                <span v-if="pagination.totalPages > 1" class="font-mono">
                    Page {{ pagination.page }} / {{ pagination.totalPages }}
                </span>
            </div>

            <!-- Loading -->
            <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                <div v-for="i in 6" :key="i" class="glass-card rounded-2xl p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="h-3 w-32 bg-(--bg-muted) rounded animate-pulse" />
                        <div class="h-5 w-16 bg-(--bg-muted) rounded animate-pulse" />
                    </div>
                    <div class="h-3 w-full bg-(--bg-muted) rounded animate-pulse" />
                    <div class="h-2 w-2/3 bg-(--bg-muted) rounded animate-pulse" />
                </div>
            </div>

            <!-- Empty -->
            <div v-else-if="receipts.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-mood-empty text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">
                    {{ hasFilters ? 'No matches' : 'No receipts yet' }}
                </h4>
                <p class="text-xs text-(--text-muted) mt-1">
                    {{ hasFilters
                        ? 'Try clearing the filters.'
                        : 'Record your first customer payment to settle outstanding invoices.' }}
                </p>
                <button v-if="!hasFilters" class="btn btn-soft-primary text-xs mt-4" @click="openCreateModal">
                    <i class="ti ti-plus" /> Record first receipt
                </button>
            </div>

            <!-- Cards grid -->
            <section v-else class="space-y-4">
                <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-4">
                    <article v-for="r in receipts" :key="r.id" class="receipt-card"
                        :class="{ 'opacity-70': r.status === 'cancelled' }">
                        <header class="flex items-start gap-3">
                            <span class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0"
                                :class="r.status === 'posted' ? 'badge-soft-success' : 'badge-soft-secondary'">
                                <i :class="['ti', r.status === 'posted' ? 'ti-receipt' : 'ti-receipt-off', 'text-base']" />
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-(--text-heading) font-mono">{{ r.receiptNumber }}</p>
                                <p class="text-xxs text-(--text-muted) mt-0.5 truncate">
                                    {{ r.customer?.name || '—' }}
                                </p>
                            </div>
                            <button type="button" class="action-trigger"
                                :class="{ 'action-trigger-open': actionMenu.open && actionMenu.receipt?.id === r.id }"
                                title="Actions" @click.stop="openActionMenu(r, $event)">
                                <i class="ti ti-dots-vertical" />
                            </button>
                        </header>

                        <div class="mt-3 flex items-center justify-between">
                            <div>
                                <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Amount</p>
                                <p class="text-lg font-mono font-semibold text-(--text-heading) mt-0.5">
                                    {{ r.currency || 'USD' }} {{ r.amount.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Received</p>
                                <p class="text-xs font-mono text-(--text-heading) mt-0.5">{{ r.receivedOn || '—' }}</p>
                            </div>
                        </div>

                        <footer class="mt-3 pt-3 border-t border-(--border-color)/60 space-y-1.5">
                            <div class="flex items-center justify-between text-xxs">
                                <span class="text-(--text-muted)">{{ r.bankAccount?.name || 'Bank' }}</span>
                                <span class="text-(--text-muted) font-mono">{{ r.paymentMethod || '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between text-xxs">
                                <span class="text-(--text-muted)">
                                    {{ r.applications.length }} invoice{{ r.applications.length === 1 ? '' : 's' }}
                                </span>
                                <span v-if="r.status === 'cancelled'" class="state-chip badge-soft-danger">
                                    <i class="ti ti-arrow-back" /> reversed
                                </span>
                                <span v-else class="state-chip badge-soft-success">
                                    <i class="ti ti-circle-check" /> posted
                                </span>
                            </div>
                        </footer>
                    </article>
                </div>

                <!-- Pagination -->
                <div v-if="pagination.totalPages > 1" class="flex items-center justify-center gap-2 pt-2">
                    <button class="btn btn-ghost text-xs" :disabled="pagination.page <= 1" @click="setPage(pagination.page - 1)">
                        <i class="ti ti-chevron-left" /> Prev
                    </button>
                    <button class="btn btn-ghost text-xs" :disabled="pagination.page >= pagination.totalPages"
                        @click="setPage(pagination.page + 1)">
                        Next <i class="ti ti-chevron-right" />
                    </button>
                </div>
            </section>
        </div>

        <!-- Record-receipt modal -->
        <div v-if="showModal"
            class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-2xl p-6 shadow-(--shadow-lg) bg-(--bg-card) max-h-[90vh] overflow-y-auto">
                <header class="flex items-center justify-between mb-5">
                    <h3 class="text-base font-semibold text-(--text-heading)">Record customer receipt</h3>
                    <button class="topbar-btn" @click="closeModal"><i class="ti ti-x" /></button>
                </header>

                <form class="space-y-4" @submit.prevent="saveReceipt">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Receipt number</label>
                            <input v-model="form.receipt_number" type="text" required class="form-control font-mono"
                                placeholder="RCT-2026-001" />
                        </div>
                        <div>
                            <label class="form-label">Received on</label>
                            <input v-model="form.received_on" type="date" required class="form-control font-mono" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Customer</label>
                            <select v-model="form.customer_id" required class="form-control" @change="onCustomerChange">
                                <option value="">Select customer...</option>
                                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Bank account</label>
                            <select v-model="form.bank_account_id" required class="form-control">
                                <option value="">Select bank...</option>
                                <option v-for="b in bankAccounts" :key="b.id" :value="b.id">
                                    {{ b.name }}{{ b.bankName ? ' (' + b.bankName + ')' : '' }}
                                </option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="form-label">Amount</label>
                            <input v-model.number="form.amount" type="number" step="0.01" min="0.01" required
                                class="form-control font-mono" />
                        </div>
                        <div>
                            <label class="form-label">Currency</label>
                            <input v-model="form.currency" type="text" maxlength="3" class="form-control font-mono"
                                placeholder="USD" />
                        </div>
                        <div>
                            <label class="form-label">Method</label>
                            <select v-model="form.payment_method" class="form-control">
                                <option value="">(unspecified)</option>
                                <option value="cash">Cash</option>
                                <option value="bank_transfer">Bank transfer</option>
                                <option value="card">Card</option>
                                <option value="mobile_money">Mobile money</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">AR account</label>
                        <select v-model="form.ar_account_id" required class="form-control">
                            <option value="">Select AR account...</option>
                            <option v-for="a in arAccounts" :key="a.id" :value="a.id">
                                {{ a.code }} . {{ a.name }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="form-label">Reference</label>
                        <input v-model="form.reference_number" type="text" class="form-control font-mono"
                            placeholder="Wire reference, cheque number, ..." />
                    </div>

                    <!-- Open invoices for the chosen customer -->
                    <section class="border border-(--border-color) rounded-xl p-3 space-y-2">
                        <header class="flex items-center justify-between">
                            <h4 class="text-xs font-semibold text-(--text-heading) uppercase tracking-widest">
                                Apply to invoices
                            </h4>
                            <span class="text-xxs font-mono text-(--text-muted)">
                                Applied {{ totalApplied.toFixed(2) }} / {{ Number(form.amount || 0).toFixed(2) }}
                            </span>
                        </header>

                        <div v-if="loadingInvoices" class="py-6 text-center">
                            <span class="w-5 h-5 inline-block rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                        </div>
                        <div v-else-if="!form.customer_id" class="text-xxs text-(--text-muted) py-4 text-center">
                            Pick a customer to see their open invoices.
                        </div>
                        <div v-else-if="openInvoices.length === 0" class="text-xxs text-(--text-muted) py-4 text-center">
                            This customer has no open (confirmed) invoices.
                        </div>
                        <table v-else class="w-full text-left">
                            <thead>
                                <tr class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                    <th class="px-2 py-2 font-semibold font-mono">#</th>
                                    <th class="px-2 py-2 font-semibold">Due</th>
                                    <th class="px-2 py-2 font-semibold font-mono text-right">Outstanding</th>
                                    <th class="px-2 py-2 font-semibold font-mono text-right">Apply</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-(--border-color)">
                                <tr v-for="inv in openInvoices" :key="inv.id">
                                    <td class="px-2 py-2 font-mono text-xs">{{ inv.invoiceNumber }}</td>
                                    <td class="px-2 py-2 text-xxs font-mono text-(--text-muted)">{{ inv.dueDate || '—' }}</td>
                                    <td class="px-2 py-2 font-mono text-xs text-right">
                                        {{ inv.outstandingAmount.toFixed(2) }}
                                    </td>
                                    <td class="px-2 py-2 text-right">
                                        <input v-model.number="applications[inv.id]" type="number" step="0.01" min="0"
                                            :max="inv.outstandingAmount" class="form-control font-mono text-right w-28 ml-auto" />
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </section>

                    <div>
                        <label class="form-label">Notes</label>
                        <textarea v-model="form.notes" rows="2" class="form-control text-xs"
                            placeholder="Optional notes for the receipt..." />
                    </div>

                    <div v-if="formError"
                        class="text-xs text-(--color-danger) bg-(--color-danger-subtle) px-3 py-2 rounded">
                        {{ formError }}
                    </div>

                    <footer class="pt-4 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="closeModal">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="saving || !canSave">
                            <i :class="['ti', saving ? 'ti-loader-2 animate-spin' : 'ti-device-floppy']" />
                            {{ saving ? 'Posting...' : 'Post receipt' }}
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Action dropdown -->
        <div v-if="actionMenu.open && actionMenu.receipt"
            class="fixed z-50 glass-card rounded-lg shadow-(--shadow-lg) bg-(--bg-card) border border-(--border-color) py-1 min-w-[200px]"
            :style="{ top: actionMenu.y + 'px', left: actionMenu.x + 'px' }" @click.stop>
            <button class="action-item" @click="actionView">
                <i class="ti ti-eye" /> View detail
            </button>
            <template v-if="actionMenu.receipt.isCancellable">
                <hr class="my-1 border-(--border-color)" />
                <button class="action-item action-item-danger" @click="actionCancel">
                    <i class="ti ti-arrow-back" /> Cancel + reverse JE
                </button>
            </template>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useApi } from '~/composables/useApi'
import { useReceipts, type Receipt, type OpenInvoice, type ReceiptStatus } from '~/composables/useReceipts'

definePageMeta({
    breadcrumb: 'Customer Receipts',
})

const api = useApi()
const receiptsApi = useReceipts()

interface CustomerLite { id: string; name: string }
interface BankLite { id: string; name: string; bankName: string | null }
interface AccountLite { id: string; code: string; name: string; type: string }

const receipts = ref<Receipt[]>([])
const pagination = reactive({ page: 1, limit: 18, total: 0, totalPages: 1 })
const loading = ref(false)
const alert = reactive({ msg: '', type: 'success' as 'success' | 'danger' })

const filters = reactive({
    search: '',
    status: '' as ReceiptStatus | '',
})

const hasFilters = computed(() => !!filters.search || !!filters.status)

const resultsSummary = computed(() => {
    if (pagination.total === 0) return ''
    const start = (pagination.page - 1) * pagination.limit + 1
    const end = Math.min(start + receipts.value.length - 1, pagination.total)
    return `Showing ${start}-${end} of ${pagination.total} receipts`
})

// Modal state
const showModal = ref(false)
const saving = ref(false)
const formError = ref<string | null>(null)
const form = reactive({
    receipt_number: '',
    customer_id: '',
    bank_account_id: '',
    ar_account_id: '',
    received_on: new Date().toISOString().substring(0, 10),
    amount: 0,
    currency: 'USD',
    payment_method: '',
    reference_number: '',
    notes: '',
})

// Modal pickers
const customers = ref<CustomerLite[]>([])
const bankAccounts = ref<BankLite[]>([])
const arAccounts = ref<AccountLite[]>([])
const openInvoices = ref<OpenInvoice[]>([])
const loadingInvoices = ref(false)
const applications = reactive<Record<string, number>>({})

const totalApplied = computed(() =>
    Object.values(applications).reduce((sum, v) => sum + (Number(v) || 0), 0)
)

const canSave = computed(() => {
    if (!form.customer_id || !form.bank_account_id || !form.ar_account_id) return false
    if (!form.amount || form.amount <= 0) return false
    if (totalApplied.value <= 0) return false
    return Math.abs(totalApplied.value - form.amount) < 0.01
})

// Action menu
const actionMenu = reactive({ open: false, x: 0, y: 0, receipt: null as Receipt | null })

const onFilterChange = () => {
    pagination.page = 1
    loadReceipts()
}

const setStatus = (s: ReceiptStatus | '') => {
    filters.status = s
    onFilterChange()
}

const setPage = (n: number) => {
    pagination.page = Math.max(1, Math.min(pagination.totalPages, n))
    loadReceipts()
}

const loadReceipts = async () => {
    loading.value = true
    try {
        const res = await receiptsApi.list({
            search: filters.search || undefined,
            status: filters.status || undefined,
            page: pagination.page,
            limit: pagination.limit,
        })
        receipts.value = res.data
        pagination.total = res.pagination.total
        pagination.totalPages = res.pagination.totalPages
    } catch (err: any) {
        alert.msg = err?.data?.message || 'Failed to load receipts.'
        alert.type = 'danger'
        receipts.value = []
    } finally {
        loading.value = false
    }
}

const loadPickers = async () => {
    try {
        const [cust, banks, accounts] = await Promise.all([
            api.get<{ data: CustomerLite[] }>('customers?limit=500'),
            api.get<{ data: BankLite[] }>('bank-accounts?limit=200'),
            api.get<{ data: AccountLite[] }>("accounts?type=asset&limit=200"),
        ])
        customers.value = cust.data
        bankAccounts.value = banks.data
        // Filter to likely AR accounts client-side: name contains "receivable"
        // or code starts with 12 (per CoA convention). Backend returns every
        // asset account; this narrows the picker.
        arAccounts.value = (accounts.data || []).filter(a =>
            /receiv/i.test(a.name) || /^12/.test(a.code || '')
        )
        if (arAccounts.value.length === 0) {
            // Fallback: show all asset accounts so the user can still pick.
            arAccounts.value = accounts.data || []
        }
    } catch { /* swallow — pickers stay empty */ }
}

const onCustomerChange = async () => {
    Object.keys(applications).forEach(k => delete applications[k])
    if (!form.customer_id) {
        openInvoices.value = []
        return
    }
    loadingInvoices.value = true
    try {
        const res = await receiptsApi.openInvoices(form.customer_id)
        openInvoices.value = res.data
    } catch (err: any) {
        alert.msg = err?.data?.message || 'Failed to load invoices.'
        alert.type = 'danger'
        openInvoices.value = []
    } finally {
        loadingInvoices.value = false
    }
}

// Auto-allocate the receipt amount across invoices oldest-first when the
// user edits `amount` AFTER picking a customer. Saves clicks in the common
// case of "apply this $500 to whichever invoices it covers."
watch(() => form.amount, (newAmt) => {
    if (!form.customer_id || openInvoices.value.length === 0) return
    let remaining = Number(newAmt) || 0
    Object.keys(applications).forEach(k => delete applications[k])
    for (const inv of openInvoices.value) {
        if (remaining <= 0) break
        const apply = Math.min(inv.outstandingAmount, remaining)
        if (apply > 0) {
            applications[inv.id] = Number(apply.toFixed(2))
            remaining = Number((remaining - apply).toFixed(2))
        }
    }
})

const openCreateModal = () => {
    form.receipt_number = ''
    form.customer_id = ''
    form.bank_account_id = ''
    form.ar_account_id = ''
    form.received_on = new Date().toISOString().substring(0, 10)
    form.amount = 0
    form.currency = 'USD'
    form.payment_method = ''
    form.reference_number = ''
    form.notes = ''
    formError.value = null
    Object.keys(applications).forEach(k => delete applications[k])
    openInvoices.value = []
    showModal.value = true
}

const closeModal = () => { showModal.value = false; formError.value = null }

const saveReceipt = async () => {
    if (!canSave.value) {
        formError.value = 'Applications must sum exactly to the receipt amount.'
        return
    }
    saving.value = true
    formError.value = null
    try {
        const apps = Object.entries(applications)
            .filter(([_, v]) => Number(v) > 0)
            .map(([invoice_id, applied_amount]) => ({ invoice_id, applied_amount: Number(applied_amount) }))
        await receiptsApi.record({
            receipt_number: form.receipt_number,
            customer_id: form.customer_id,
            bank_account_id: form.bank_account_id,
            ar_account_id: form.ar_account_id,
            received_on: form.received_on,
            amount: form.amount,
            currency: form.currency || null,
            payment_method: form.payment_method || null,
            reference_number: form.reference_number || null,
            notes: form.notes || null,
            applications: apps,
        })
        showModal.value = false
        alert.msg = 'Receipt posted.'
        alert.type = 'success'
        await loadReceipts()
    } catch (err: any) {
        const errs = err?.data?.errors
        if (errs && typeof errs === 'object') {
            const first = Object.values(errs)[0]
            formError.value = Array.isArray(first) ? String(first[0]) : 'Validation failed.'
        } else {
            formError.value = err?.data?.message || 'Failed to post receipt.'
        }
    } finally {
        saving.value = false
    }
}

const openActionMenu = (r: Receipt, ev: MouseEvent) => {
    const rect = (ev.currentTarget as HTMLElement).getBoundingClientRect()
    const menuWidth = 200
    const menuMaxHeight = 120
    const left = Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8)
    const wouldOverflow = rect.bottom + menuMaxHeight + 8 > window.innerHeight
    actionMenu.receipt = r
    actionMenu.x = Math.max(8, left)
    actionMenu.y = wouldOverflow ? rect.top - menuMaxHeight - 6 : rect.bottom + 6
    actionMenu.open = true
}
const closeActionMenu = () => { actionMenu.open = false; actionMenu.receipt = null }

const actionView = () => {
    const r = actionMenu.receipt
    closeActionMenu()
    if (r) navigateTo(`/finance/receipts/${r.id}`)
}

const actionCancel = async () => {
    const r = actionMenu.receipt
    closeActionMenu()
    if (!r) return
    if (!confirm(`Cancel receipt ${r.receiptNumber}? This reverses the journal entry and rolls back invoice paid_amounts.`)) return
    try {
        await receiptsApi.cancel(r.id)
        alert.msg = 'Receipt cancelled and JE reversed.'
        alert.type = 'success'
        await loadReceipts()
    } catch (err: any) {
        alert.msg = err?.data?.message || 'Failed to cancel receipt.'
        alert.type = 'danger'
    }
}

onMounted(() => {
    if (import.meta.client) {
        document.addEventListener('click', closeActionMenu)
    }
    loadReceipts()
    loadPickers()
})

onBeforeUnmount(() => {
    if (import.meta.client) {
        document.removeEventListener('click', closeActionMenu)
    }
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
.topbar-btn:hover { background: var(--bg-muted); color: var(--text-heading); }

.segmented {
    display: inline-flex; align-items: center; padding: 3px;
    border-radius: 999px; background: var(--bg-card); border: 1px solid var(--border-color);
}
.seg-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 5px 12px; border-radius: 999px; border: 0; background: transparent;
    font-size: 11px; color: var(--text-body); cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}
.seg-btn:hover { color: var(--text-heading); }
.seg-btn.active {
    background: rgb(var(--color-primary-rgb) / 0.12);
    color: var(--color-primary);
    box-shadow: inset 0 0 0 1px rgb(var(--color-primary-rgb) / 0.25);
}

.receipt-card {
    background: var(--bg-card); border: 1px solid var(--border-color);
    border-radius: 1rem; padding: 1rem;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.receipt-card:hover {
    border-color: rgb(var(--color-primary-rgb) / 0.35);
    box-shadow: 0 2px 8px rgb(var(--color-primary-rgb) / 0.05);
}

.state-chip {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; border-radius: 999px;
    font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em;
}

.action-trigger {
    display: inline-flex; align-items: center; justify-content: center;
    width: 30px; height: 30px; border-radius: 8px;
    color: var(--text-muted); cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}
.action-trigger:hover { background: var(--bg-muted); color: var(--text-heading); }
.action-trigger-open { background: var(--bg-muted); color: var(--color-primary); }

.action-item {
    width: 100%; display: flex; align-items: center; gap: 0.5rem;
    padding: 0.5rem 0.75rem; font-size: 0.75rem;
    color: var(--text-heading); text-align: left; cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}
.action-item:hover { background: var(--bg-muted); }
.action-item-danger { color: var(--color-danger); }
.action-item-danger:hover { background: var(--color-danger-subtle); }
</style>
