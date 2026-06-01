<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Bank Accounts</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Physical cash and bank deposits. Book balance flows live from the linked GL asset account.</p>
                </div>
                <button v-if="canWrite" type="button" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />New Bank Account
                </button>
            </header>

            <!-- KPIs -->
            <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Total Accounts</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-primary flex items-center justify-center">
                            <i class="ti ti-building-bank text-sm" />
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiTotalAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">{{ activeCount }} active</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Book Balance</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-success flex items-center justify-center">
                            <i class="ti ti-cash-banknote text-sm" />
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ formatMoney(kpiBalanceAnim) }}</p>
                    <p class="text-xxs text-(--text-muted)">Across all currencies</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Currencies</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-info flex items-center justify-center">
                            <i class="ti ti-currency-dollar text-sm" />
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiCurrenciesAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">{{ uniqueCurrencies.join(' · ') || '—' }}</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Needs Reconciliation</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-warning flex items-center justify-center">
                            <i class="ti ti-alert-circle text-sm" />
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiNeedsReconAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">Stale &gt; 30 days</p>
                </div>
            </section>

            <!-- Filters -->
            <section class="glass-card rounded-xl p-4 flex flex-col md:flex-row gap-3 items-center justify-between">
                <div class="relative w-full md:w-96">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                    <input v-model="search" type="search" placeholder="Search by name, bank, holder, account #..."
                        class="form-control pl-9" />
                </div>
                <div class="flex gap-2 items-center w-full md:w-auto">
                    <select v-model="filterCurrency" class="form-control text-xs">
                        <option value="">All currencies</option>
                        <option v-for="c in uniqueCurrencies" :key="c" :value="c">{{ c }}</option>
                    </select>
                    <div class="flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1 shrink-0">
                        <button v-for="s in (['all', 'active', 'inactive'] as const)" :key="s" type="button"
                            class="px-2.5 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
                            :class="filterActive === s ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                            @click="filterActive = s">{{ s }}</button>
                    </div>
                </div>
            </section>

            <!-- Loading -->
            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading bank accounts...</span>
            </div>

            <!-- Empty -->
            <div v-else-if="filteredList.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-building-bank text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No bank accounts found</h4>
                <p class="text-xs text-(--text-muted) mt-1">Add a bank account to start posting receipts and disbursements.</p>
            </div>

            <!-- Grid -->
            <section v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <article v-for="b in filteredList" :key="b.id"
                    class="glass-card rounded-2xl p-5 flex flex-col gap-3 group relative overflow-hidden transition-all duration-150 border border-(--border-color) hover:border-(--color-primary)/40">
                    <div class="absolute -right-8 -top-8 w-20 h-20 rounded-full bg-(--color-primary)/10 blur-xl pointer-events-none group-hover:scale-150 transition-transform duration-500" />

                    <div class="relative z-10 flex-1 space-y-3">
                        <header class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-sm font-semibold text-(--text-heading) truncate group-hover:text-(--color-primary) transition-colors">
                                        {{ b.name }}
                                    </h3>
                                    <span v-if="b.isDefault" class="badge-soft-primary text-xxs px-1.5 py-0.5 rounded font-bold uppercase tracking-widest">Default</span>
                                </div>
                                <p class="text-xxs text-(--text-muted) mt-0.5 truncate">{{ b.bankName }}<span v-if="b.branch"> · {{ b.branch }}</span></p>
                            </div>
                            <span class="text-xxs px-1.5 py-0.5 rounded font-mono font-bold badge-soft-info shrink-0">{{ b.currency }}</span>
                        </header>

                        <div class="text-xs space-y-1.5 border-t border-b border-(--border-color)/50 py-3">
                            <div v-if="b.accountNumber" class="flex items-center gap-2 text-(--text-body)">
                                <i class="ti ti-hash text-(--text-muted)" />
                                <span class="font-mono text-xxs">{{ maskAccount(b.accountNumber) }}</span>
                            </div>
                            <div v-if="b.accountHolder" class="flex items-center gap-2 text-(--text-body)">
                                <i class="ti ti-user text-(--text-muted)" />
                                <span class="text-xxs truncate">{{ b.accountHolder }}</span>
                            </div>
                            <div v-if="b.swift" class="flex items-center gap-2 text-(--text-body)">
                                <i class="ti ti-world text-(--text-muted)" />
                                <span class="font-mono text-xxs">{{ b.swift }}</span>
                            </div>
                            <div v-if="b.glAccount" class="flex items-center gap-2 text-(--text-body)">
                                <i class="ti ti-tree text-(--text-muted)" />
                                <span class="font-mono text-xxs truncate">{{ b.glAccount.code }} · {{ b.glAccount.name }}</span>
                            </div>
                            <div v-else class="flex items-center gap-2 text-(--color-warning)">
                                <i class="ti ti-alert-triangle text-xxs" />
                                <span class="text-xxs">No GL account linked</span>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-2 text-center">
                            <div>
                                <p class="text-xxs text-(--text-muted) uppercase font-bold tracking-wider">Book Balance</p>
                                <p class="text-sm font-bold text-(--text-heading) font-mono mt-1">{{ b.currency }} {{ b.bookBalance.toFixed(2) }}</p>
                            </div>
                            <div>
                                <p class="text-xxs text-(--text-muted) uppercase font-bold tracking-wider">Last Reconciled</p>
                                <p class="text-xxs font-mono mt-1" :class="reconcileClass(b)">{{ formatReconciled(b.lastReconciledAt) }}</p>
                            </div>
                        </div>
                    </div>

                    <footer class="mt-2 pt-2 border-t border-(--border-color)/50 flex items-center justify-between relative z-10">
                        <Badge :variant="b.isActive ? 'success' : 'secondary'" class="shrink-0">{{ b.isActive ? 'Active' : 'Inactive' }}</Badge>
                        <div class="flex items-center gap-1.5">
                            <button v-if="canWrite" type="button" class="action-btn" title="Edit" @click="openEditModal(b)">
                                <i class="ti ti-pencil" />
                            </button>
                            <button v-if="canDelete" type="button" class="action-btn action-btn-danger" title="Archive" @click="confirmDelete(b)">
                                <i class="ti ti-archive" />
                            </button>
                        </div>
                    </footer>
                </article>
            </section>
        </div>

        <!-- Form Modal -->
        <div v-if="showFormModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-2xl bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">{{ isEdit ? 'Edit Bank Account' : 'New Bank Account' }}</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="save">
                    <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
                        <div class="grid grid-cols-3 gap-3">
                            <div class="space-y-1 col-span-2">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Display Name *</label>
                                <input v-model="form.name" type="text" required maxlength="160" placeholder="e.g. ABA Operating USD"
                                    class="form-control text-xs" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Currency *</label>
                                <input v-model="form.currency" type="text" required maxlength="3"
                                    class="form-control text-xs font-mono uppercase"
                                    @input="form.currency = (form.currency || '').toUpperCase()" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Bank Name *</label>
                                <input v-model="form.bank_name" type="text" required maxlength="160" placeholder="e.g. ABA Bank"
                                    class="form-control text-xs" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Branch</label>
                                <input v-model="form.branch" type="text" maxlength="160"
                                    class="form-control text-xs" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Account Holder</label>
                                <input v-model="form.account_holder" type="text" maxlength="160"
                                    class="form-control text-xs" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Account Number</label>
                                <input v-model="form.account_number" type="text" maxlength="60"
                                    class="form-control text-xs font-mono" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">SWIFT / BIC</label>
                                <input v-model="form.swift" type="text" maxlength="20"
                                    class="form-control text-xs font-mono uppercase" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">IBAN</label>
                                <input v-model="form.iban" type="text" maxlength="40"
                                    class="form-control text-xs font-mono" />
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Linked GL Account (must be an asset account)</label>
                            <select v-model="form.account_id" class="form-control text-xs" :disabled="accountsLoading">
                                <option :value="null">— None (link later) —</option>
                                <option v-for="a in assetAccounts" :key="a.id" :value="a.id">
                                    {{ a.code }} · {{ a.name }}
                                </option>
                            </select>
                            <p v-if="assetAccounts.length === 0 && !accountsLoading" class="text-xxs text-(--color-warning) flex items-center gap-1.5">
                                <i class="ti ti-info-circle" />
                                No asset accounts in the Chart yet. Add one in
                                <NuxtLink to="/accounting/accounts" class="underline">Chart of Accounts</NuxtLink>.
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Opening Balance</label>
                                <input v-model.number="form.opening_balance" type="number" step="0.01"
                                    class="form-control text-xs font-mono text-right" />
                                <p class="text-xxs text-(--text-muted)">Reference only. Live book balance comes from the linked GL account.</p>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Last Reconciled Balance</label>
                                <input v-model.number="form.last_reconciled_balance" type="number" step="0.01"
                                    class="form-control text-xs font-mono text-right" />
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Notes</label>
                            <textarea v-model="form.notes" rows="2" maxlength="2000"
                                class="form-control text-xs resize-none" />
                        </div>

                        <div class="flex items-center gap-4">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input v-model="form.is_active" type="checkbox" class="rounded border-(--border-color)" />
                                <span class="text-xs">Active</span>
                            </label>
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input v-model="form.is_default" type="checkbox" class="rounded border-(--border-color)" />
                                <span class="text-xs">Set as Default</span>
                            </label>
                            <p class="text-xxs text-(--text-muted)">Default bank is suggested first on disbursements and receipts.</p>
                        </div>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showFormModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="submitting">
                            <i v-if="submitting" class="ti ti-loader-2 animate-spin" />
                            {{ isEdit ? 'Save Changes' : 'Create Bank Account' }}
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Delete Modal -->
        <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Archive Bank Account</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="deleteTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5">
                    <p class="text-xs text-(--text-body)">
                        Archive <span class="font-semibold text-(--text-heading)">{{ deleteTarget.name }}</span>?
                    </p>
                    <p class="text-xxs text-(--text-muted) mt-2">
                        The linked GL account stays untouched. Cannot archive while this bank is marked as default.
                    </p>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="deleteTarget = null">Cancel</button>
                    <button type="button" class="btn btn-danger text-xs" :disabled="deleting" @click="onConfirmDelete">
                        <i v-if="deleting" class="ti ti-loader-2 animate-spin" />
                        Archive
                    </button>
                </footer>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useFinance } from '~/composables/useFinance'
import { useToast } from '~/composables/useToast'
import { useCountUp } from '~/composables/useCountUp'
import { useAuthStore } from '~/stores/auth'
import type { Account, BankAccount, CreateBankAccountPayload } from '~/types/finance'

definePageMeta({ breadcrumb: 'Bank' })

const finance = useFinance()
const toast = useToast()
const authStore = useAuthStore()

const canWrite  = computed(() => authStore.hasPermission('fms.bank_accounts.write'))
const canDelete = computed(() => authStore.hasPermission('fms.bank_accounts.delete'))

const loading    = ref(false)
const submitting = ref(false)
const deleting   = ref(false)
const banks      = ref<BankAccount[]>([])

const search         = ref('')
const filterActive   = ref<'all' | 'active' | 'inactive'>('all')
const filterCurrency = ref<string>('')

const filteredList = computed(() => banks.value.filter(b => {
    const q = search.value.trim().toLowerCase()
    const hay = [b.name, b.bankName, b.accountHolder, b.accountNumber].filter(Boolean).join(' ').toLowerCase()
    const matchSearch = !q || hay.includes(q)
    const matchActive =
        filterActive.value === 'all' ||
        (filterActive.value === 'active' && b.isActive) ||
        (filterActive.value === 'inactive' && !b.isActive)
    const matchCurrency = !filterCurrency.value || b.currency === filterCurrency.value
    return matchSearch && matchActive && matchCurrency
}))

const activeCount       = computed(() => banks.value.filter(b => b.isActive).length)
const uniqueCurrencies  = computed(() => Array.from(new Set(banks.value.map(b => b.currency))).sort())
const totalBookBalance  = computed(() => banks.value.reduce((s, b) => s + b.bookBalance, 0))
const needsReconCount   = computed(() => {
    const cutoff = Date.now() - 30 * 24 * 60 * 60 * 1000
    return banks.value.filter(b => {
        if (!b.lastReconciledAt) return true
        return new Date(b.lastReconciledAt).getTime() < cutoff
    }).length
})

const kpiTotalAnim       = useCountUp(() => banks.value.length)
const kpiBalanceAnim     = useCountUp(() => totalBookBalance.value)
const kpiCurrenciesAnim  = useCountUp(() => uniqueCurrencies.value.length)
const kpiNeedsReconAnim  = useCountUp(() => needsReconCount.value)

const formatMoney = (n: number) => {
    const abs = Math.abs(n)
    if (abs >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`
    if (abs >= 1_000)     return `${(n / 1_000).toFixed(1)}K`
    return n.toFixed(2)
}

const maskAccount = (n: string) => n.length > 4 ? `•••• ${n.slice(-4)}` : n

const formatReconciled = (iso: string | null) => {
    if (!iso) return 'Never'
    const d = new Date(iso)
    if (isNaN(d.getTime())) return iso
    const days = Math.floor((Date.now() - d.getTime()) / (24 * 60 * 60 * 1000))
    if (days === 0) return 'Today'
    if (days === 1) return 'Yesterday'
    if (days < 30) return `${days}d ago`
    return d.toISOString().slice(0, 10)
}

const reconcileClass = (b: BankAccount) => {
    if (!b.lastReconciledAt) return 'text-(--color-warning)'
    const days = Math.floor((Date.now() - new Date(b.lastReconciledAt).getTime()) / (24 * 60 * 60 * 1000))
    if (days > 30) return 'text-(--color-warning)'
    return 'text-(--text-body)'
}

// ---- GL accounts (lazy-loaded for the modal picker) --------------------------

const accountsList = ref<Account[]>([])
const accountsLoading = ref(false)
const assetAccounts = computed(() => accountsList.value.filter(a => a.type === 'asset'))

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
const isEdit        = ref(false)
const editId        = ref<string | null>(null)

const blankForm = (): CreateBankAccountPayload => ({
    account_id: null,
    name: '',
    bank_name: '',
    branch: null,
    account_number: null,
    account_holder: null,
    swift: null,
    iban: null,
    currency: 'USD',
    opening_balance: 0,
    last_reconciled_at: null,
    last_reconciled_balance: null,
    notes: null,
    is_active: true,
    is_default: false,
})

const form = reactive<CreateBankAccountPayload>(blankForm())

const resetForm = () => Object.assign(form, blankForm())

const openCreateModal = () => {
    isEdit.value = false
    editId.value = null
    resetForm()
    showFormModal.value = true
    ensureAccountsLoaded()
}

const openEditModal = (b: BankAccount) => {
    isEdit.value = true
    editId.value = b.id
    form.account_id              = b.accountId
    form.name                    = b.name
    form.bank_name               = b.bankName
    form.branch                  = b.branch
    form.account_number          = b.accountNumber
    form.account_holder          = b.accountHolder
    form.swift                   = b.swift
    form.iban                    = b.iban
    form.currency                = b.currency
    form.opening_balance         = b.openingBalance
    form.last_reconciled_at      = b.lastReconciledAt
    form.last_reconciled_balance = b.lastReconciledBalance
    form.notes                   = b.notes
    form.is_active               = b.isActive
    form.is_default              = b.isDefault
    showFormModal.value = true
    ensureAccountsLoaded()
}

const save = async () => {
    submitting.value = true
    try {
        if (isEdit.value && editId.value) {
            const res = await finance.bankAccounts.update(editId.value, form)
            const idx = banks.value.findIndex(b => b.id === editId.value)
            if (idx !== -1) banks.value[idx] = res.data
            toast.success('Bank account updated', form.name)
        } else {
            const res = await finance.bankAccounts.create(form)
            banks.value.unshift(res.data)
            toast.success('Bank account created', form.name)
        }
        showFormModal.value = false
        // Re-pull so default-demotion side-effects are reflected for other rows.
        await load()
    } catch (err: any) {
        toast.error('Save failed', err?.data?.message)
    } finally {
        submitting.value = false
    }
}

// ---- Archive -----------------------------------------------------------------

const deleteTarget = ref<BankAccount | null>(null)
const confirmDelete = (b: BankAccount) => { deleteTarget.value = b }
const onConfirmDelete = async () => {
    if (!deleteTarget.value) return
    deleting.value = true
    try {
        await finance.bankAccounts.destroy(deleteTarget.value.id)
        banks.value = banks.value.filter(b => b.id !== deleteTarget.value!.id)
        toast.success('Bank account archived', deleteTarget.value.name)
        deleteTarget.value = null
    } catch (err: any) {
        toast.error('Archive failed', err?.data?.message)
    } finally {
        deleting.value = false
    }
}

// ---- Load --------------------------------------------------------------------

const load = async () => {
    loading.value = true
    try {
        const res = await finance.bankAccounts.list({ limit: 100 })
        banks.value = res.data
    } catch (err: any) {
        toast.error('Failed to load bank accounts', err?.data?.message)
    } finally {
        loading.value = false
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
</style>
