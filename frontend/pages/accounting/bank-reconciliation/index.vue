<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Bank Reconciliation</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Pair the bank statement with posted ledger entries on the bank's GL. Sessions are locked once closed; reopening requires a separate permission.</p>
                </div>
                <button v-if="canWrite" type="button" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />New Session
                </button>
            </header>

            <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Total Sessions</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-primary flex items-center justify-center"><i class="ti ti-checks text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiCountAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">On page</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Open</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-warning flex items-center justify-center"><i class="ti ti-circle text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiOpenAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">Awaiting match</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Closed</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-success flex items-center justify-center"><i class="ti ti-lock text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiClosedAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">Locked, reopen-gated</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Latest Closed</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-info flex items-center justify-center"><i class="ti ti-calendar-check text-sm" /></span>
                    </div>
                    <p class="text-sm font-bold text-(--text-heading) font-mono">{{ latestClosed ? formatDate(latestClosed) : 'None' }}</p>
                    <p class="text-xxs text-(--text-muted)">Most recent close date</p>
                </div>
            </section>

            <section class="flex items-center gap-2 flex-wrap">
                <button type="button" class="chip" :class="{ active: statusFilter === '' }" @click="setStatusFilter('')">All</button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'open' }" @click="setStatusFilter('open')">
                    <i class="ti ti-circle" /> Open
                </button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'closed' }" @click="setStatusFilter('closed')">
                    <i class="ti ti-lock" /> Closed
                </button>
            </section>

            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading sessions...</span>
            </div>
            <div v-else-if="sessions.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-checks text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No reconciliation sessions yet</h4>
                <p class="text-xs text-(--text-muted) mt-1">Start a session for a bank account once you have its statement.</p>
            </div>

            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-(--bg-muted)/40 text-(--text-muted)">
                            <tr>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Session #</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Bank</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Period</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Status</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3">Opening</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3">Stmt Ending</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3">Unmatched</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3 w-24">Open</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="s in sessions" :key="s.id"
                                class="border-t border-(--border-color) hover:bg-(--bg-muted)/40 cursor-pointer"
                                @click="goToSession(s.id)">
                                <td class="px-3 py-3 font-mono font-semibold text-(--text-heading)">{{ s.sessionNumber }}</td>
                                <td class="px-3 py-3">
                                    <p class="text-(--text-heading) font-semibold truncate max-w-xs">{{ s.bankAccount?.name || '-' }}</p>
                                    <p v-if="s.bankAccount?.bankName" class="text-xxs text-(--text-muted) truncate">{{ s.bankAccount.bankName }}</p>
                                </td>
                                <td class="px-3 py-3 font-mono">
                                    <span>{{ formatDate(s.startDate) }}</span>
                                    <span class="text-(--text-muted) mx-1">to</span>
                                    <span>{{ formatDate(s.endDate) }}</span>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="text-xxs px-1.5 py-0.5 rounded font-mono" :class="statusBadge(s.status)">{{ s.status }}</span>
                                </td>
                                <td class="px-3 py-3 text-right font-mono">{{ s.openingBalance.toFixed(2) }}</td>
                                <td class="px-3 py-3 text-right font-mono font-semibold text-(--text-heading)">{{ s.statementEndingBalance.toFixed(2) }}</td>
                                <td class="px-3 py-3 text-right font-mono"
                                    :class="s.unmatchedLinesCount > 0 ? 'text-(--color-warning)' : 'text-(--color-success)'">
                                    {{ s.unmatchedLinesCount }}
                                </td>
                                <td class="px-3 py-3 text-right">
                                    <i class="ti ti-chevron-right text-(--text-muted)" />
                                </td>
                            </tr>
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

        <div v-if="showFormModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-xl bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Start Reconciliation Session</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="post">
                    <div class="p-5 space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Session # *</label>
                                <input v-model="form.session_number" type="text" required maxlength="64"
                                    placeholder="e.g. BR-2026-01" class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Bank *</label>
                                <select v-model="form.bank_account_id" required class="form-control text-xs" :disabled="banksLoading"
                                    @change="onBankChange">
                                    <option value="">Pick bank</option>
                                    <option v-for="b in banks" :key="b.id" :value="b.id">
                                        {{ b.name }} ({{ b.currency }}){{ b.isDefault ? ' default' : '' }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Period Start *</label>
                                <input v-model="form.start_date" type="date" required class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Period End *</label>
                                <input v-model="form.end_date" type="date" required class="form-control text-xs font-mono" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Opening Balance</label>
                                <input v-model.number="form.opening_balance" type="number" step="0.01"
                                    class="form-control text-xs font-mono text-right" />
                                <p class="text-xxs text-(--text-muted)">Defaults to last reconciled balance of the picked bank.</p>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Statement Ending *</label>
                                <input v-model.number="form.statement_ending_balance" type="number" step="0.01" required
                                    class="form-control text-xs font-mono text-right" />
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Notes</label>
                            <textarea v-model="form.notes" rows="2" maxlength="2000" class="form-control text-xs resize-none" />
                        </div>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showFormModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="!canSubmit || posting">
                            <i v-if="posting" class="ti ti-loader-2 animate-spin" />
                            Open Session
                        </button>
                    </footer>
                </form>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useFinance } from '~/composables/useFinance'
import { useToast } from '~/composables/useToast'
import { useCountUp } from '~/composables/useCountUp'
import { useAuthStore } from '~/stores/auth'
import type {
    BankAccount,
    BankReconSession,
    BankReconStatus,
    CreateBankReconSessionPayload,
} from '~/types/finance'

definePageMeta({ breadcrumb: 'Bank Reconciliation' })

const finance = useFinance()
const toast = useToast()
const router = useRouter()
const authStore = useAuthStore()

const canRead  = computed(() => authStore.hasPermission('fms.bank_recon.read'))
const canWrite = computed(() => authStore.hasPermission('fms.bank_recon.write'))

const loading = ref(false)
const posting = ref(false)

const sessions = ref<BankReconSession[]>([])
const pagination = reactive({ page: 1, limit: 25, total: 0, totalPages: 1 })
const statusFilter = ref<'' | BankReconStatus>('')

const today = new Date().toISOString().slice(0, 10)
const formatDate = (s: string | null) => {
    if (!s) return '-'
    const d = new Date(s)
    return isNaN(d.getTime()) ? s : d.toISOString().slice(0, 10)
}
const statusBadge = (s: BankReconStatus) => ({
    open:   'badge-soft-warning',
    closed: 'badge-soft-success',
}[s] || 'badge-soft-secondary')

const openCount    = computed(() => sessions.value.filter(s => s.status === 'open').length)
const closedCount  = computed(() => sessions.value.filter(s => s.status === 'closed').length)
const latestClosed = computed(() => {
    const closed = sessions.value.filter(s => s.status === 'closed' && s.endDate)
    if (!closed.length) return null
    return closed.reduce((latest, s) => (s.endDate! > (latest || '') ? s.endDate! : latest), '')
})

const kpiCountAnim  = useCountUp(() => sessions.value.length)
const kpiOpenAnim   = useCountUp(() => openCount.value)
const kpiClosedAnim = useCountUp(() => closedCount.value)

const load = async () => {
    if (!canRead.value) return
    loading.value = true
    try {
        const res = await finance.bankReconciliations.list({
            page: pagination.page,
            limit: pagination.limit,
            status: statusFilter.value || undefined,
        })
        sessions.value = res.data
        const p = (res as any).pagination
        if (p) {
            pagination.total = p.total ?? 0
            pagination.totalPages = p.totalPages ?? 1
            pagination.page = p.page ?? 1
            pagination.limit = p.limit ?? pagination.limit
        }
    } catch (err: any) {
        toast.error('Failed to load sessions', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const goPage = (p: number) => {
    if (p < 1 || p > pagination.totalPages) return
    pagination.page = p
    load()
}

const setStatusFilter = (s: '' | BankReconStatus) => {
    if (statusFilter.value === s) return
    statusFilter.value = s
    pagination.page = 1
    load()
}

const goToSession = (id: string) => {
    router.push(`/accounting/bank-reconciliation/${id}`)
}

const banks = ref<BankAccount[]>([])
const banksLoading = ref(false)
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

const showFormModal = ref(false)

const blankForm = (): CreateBankReconSessionPayload => ({
    session_number: '',
    bank_account_id: '',
    start_date: today,
    end_date: today,
    opening_balance: null,
    statement_ending_balance: 0,
    notes: null,
})

const form = reactive<CreateBankReconSessionPayload>(blankForm())

const openCreateModal = () => {
    Object.assign(form, blankForm())
    showFormModal.value = true
    ensureBanksLoaded()
}

const onBankChange = () => {
    const b = banks.value.find(x => x.id === form.bank_account_id)
    if (b && (form.opening_balance == null || form.opening_balance === 0)) {
        form.opening_balance = Number(b.lastReconciledBalance ?? 0)
    }
}

const canSubmit = computed(() => {
    if (!form.session_number.trim()) return false
    if (!form.bank_account_id) return false
    if (!form.start_date || !form.end_date) return false
    if (form.start_date > form.end_date) return false
    if (form.statement_ending_balance == null) return false
    return true
})

const post = async () => {
    if (!canSubmit.value) return
    posting.value = true
    try {
        const payload: CreateBankReconSessionPayload = {
            ...form,
            session_number: form.session_number.trim(),
            notes: form.notes?.trim() || null,
        }
        const res = await finance.bankReconciliations.create(payload)
        toast.success('Session opened', res.data.sessionNumber)
        showFormModal.value = false
        await load()
        router.push(`/accounting/bank-reconciliation/${res.data.id}`)
    } catch (err: any) {
        toast.error('Open failed', err?.data?.message)
    } finally {
        posting.value = false
    }
}

onMounted(load)
</script>

<style scoped>
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
