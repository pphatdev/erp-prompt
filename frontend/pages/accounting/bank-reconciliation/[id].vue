<template>
    <NuxtLayout name="default">
        <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
            <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
            <span class="text-xs text-(--text-muted)">Loading session...</span>
        </div>
        <div v-else-if="!session" class="glass-card rounded-2xl py-20 text-center">
            <i class="ti ti-alert-circle text-4xl text-(--text-muted)" />
            <h4 class="text-sm font-semibold text-(--text-heading) mt-3">Session not found</h4>
            <NuxtLink to="/accounting/bank-reconciliation" class="text-xs underline text-(--color-primary) mt-2 inline-block">Back to sessions</NuxtLink>
        </div>
        <div v-else class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-semibold font-mono">{{ session.sessionNumber }}</h1>
                        <span class="text-xxs px-1.5 py-0.5 rounded font-mono" :class="statusBadge(session.status)">{{ session.status }}</span>
                    </div>
                    <p class="text-xs text-(--text-muted) mt-1">
                        {{ session.bankAccount?.name }}
                        <span v-if="session.bankAccount?.bankName">/ {{ session.bankAccount.bankName }}</span>
                        / {{ formatDate(session.startDate) }} to {{ formatDate(session.endDate) }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button v-if="canWrite && session.status === 'open'" type="button" class="btn btn-ghost text-xs" @click="openAddLineModal">
                        <i class="ti ti-plus" />Statement Line
                    </button>
                    <button v-if="canWrite && session.status === 'open'" type="button" class="btn btn-primary text-xs"
                        :disabled="!session.isClosable || closing" @click="onClose">
                        <i v-if="closing" class="ti ti-loader-2 animate-spin" />
                        <i v-else class="ti ti-lock" />
                        Close Session
                    </button>
                    <button v-if="canReopen && session.status === 'closed'" type="button" class="btn btn-ghost text-xs"
                        :disabled="reopening" @click="onReopen">
                        <i v-if="reopening" class="ti ti-loader-2 animate-spin" />
                        <i v-else class="ti ti-lock-open" />
                        Reopen
                    </button>
                </div>
            </header>

            <section class="grid grid-cols-2 sm:grid-cols-5 gap-4">
                <div class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Opening</span>
                    <p class="text-lg font-bold text-(--text-heading) font-mono">{{ session.openingBalance.toFixed(2) }}</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Stmt Lines Total</span>
                    <p class="text-lg font-bold text-(--text-heading) font-mono"
                        :class="session.statementLinesTotal > 0 ? 'text-(--color-success)' : (session.statementLinesTotal < 0 ? 'text-(--color-danger)' : '')">
                        {{ session.statementLinesTotal.toFixed(2) }}
                    </p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Expected Ending</span>
                    <p class="text-lg font-bold text-(--text-heading) font-mono">{{ session.expectedEndingBalance.toFixed(2) }}</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Statement Ending</span>
                    <p class="text-lg font-bold text-(--text-heading) font-mono">{{ session.statementEndingBalance.toFixed(2) }}</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Balance Check</span>
                    <p class="text-sm font-bold font-mono flex items-center gap-1"
                        :class="session.balanceMatches ? 'text-(--color-success)' : 'text-(--color-warning)'">
                        <i class="ti" :class="session.balanceMatches ? 'ti-check' : 'ti-alert-triangle'" />
                        <span v-if="session.balanceMatches">Balanced</span>
                        <span v-else>Diff {{ (session.expectedEndingBalance - session.statementEndingBalance).toFixed(2) }}</span>
                    </p>
                    <p class="text-xxs text-(--text-muted)">{{ session.unmatchedLinesCount }} unmatched</p>
                </div>
            </section>

            <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                <!-- Statement Lines -->
                <div class="glass-card rounded-2xl overflow-hidden">
                    <header class="flex items-center justify-between p-3 border-b border-(--border-color) bg-(--bg-muted)/30">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-(--text-muted)">Statement Lines</h3>
                        <span class="text-xxs text-(--text-muted)">{{ session.statementLines.length }} total</span>
                    </header>
                    <div class="overflow-x-auto max-h-[600px]">
                        <table class="min-w-full text-xs">
                            <thead class="bg-(--bg-muted)/40 text-(--text-muted) sticky top-0">
                                <tr>
                                    <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-2">Date</th>
                                    <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-2">Description</th>
                                    <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-2 w-24">Amount</th>
                                    <th class="text-center font-bold uppercase tracking-widest text-xxs px-3 py-2 w-24">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="line in session.statementLines" :key="line.id"
                                    class="border-t border-(--border-color) hover:bg-(--bg-muted)/40 cursor-pointer"
                                    :class="{ 'bg-(--color-primary)/10': selectedLineId === line.id }"
                                    @click="selectLine(line.id)">
                                    <td class="px-3 py-2 font-mono">{{ formatDate(line.statementDate) }}</td>
                                    <td class="px-3 py-2">
                                        <p class="text-(--text-heading) truncate max-w-xs">{{ line.description }}</p>
                                        <p v-if="line.referenceNumber" class="text-xxs text-(--text-muted) font-mono">{{ line.referenceNumber }}</p>
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono"
                                        :class="line.amount > 0 ? 'text-(--color-success)' : 'text-(--color-danger)'">
                                        {{ line.amount.toFixed(2) }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span v-if="line.isMatched" class="text-xxs px-1.5 py-0.5 rounded font-mono badge-soft-success">matched</span>
                                        <span v-else class="text-xxs px-1.5 py-0.5 rounded font-mono badge-soft-warning">unmatched</span>
                                        <div class="flex items-center justify-center gap-1 mt-1">
                                            <button v-if="canWrite && session.status === 'open' && line.isMatched" type="button"
                                                class="action-btn" title="Unmatch" @click.stop="onUnmatch(line.id)">
                                                <i class="ti ti-unlink text-xs" />
                                            </button>
                                            <button v-if="canWrite && session.status === 'open' && !line.isMatched" type="button"
                                                class="action-btn action-btn-danger" title="Delete unmatched line" @click.stop="onRemoveLine(line.id)">
                                                <i class="ti ti-trash text-xs" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-if="session.statementLines.length === 0">
                                    <td colspan="4" class="p-8 text-center text-xxs text-(--text-muted)">No statement lines yet. Add lines from the bank statement.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Period Ledger Entries -->
                <div class="glass-card rounded-2xl overflow-hidden">
                    <header class="flex items-center justify-between p-3 border-b border-(--border-color) bg-(--bg-muted)/30">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-(--text-muted)">Bank GL Entries (period)</h3>
                        <span class="text-xxs text-(--text-muted)">{{ ledgerEntries.length }} total</span>
                    </header>
                    <div class="overflow-x-auto max-h-[600px]">
                        <table class="min-w-full text-xs">
                            <thead class="bg-(--bg-muted)/40 text-(--text-muted) sticky top-0">
                                <tr>
                                    <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-2">Date</th>
                                    <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-2">Description</th>
                                    <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-2 w-24">Amount</th>
                                    <th class="text-center font-bold uppercase tracking-widest text-xxs px-3 py-2 w-24">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="e in ledgerEntries" :key="e.id"
                                    class="border-t border-(--border-color) hover:bg-(--bg-muted)/40">
                                    <td class="px-3 py-2 font-mono">{{ formatDate(e.entryDate) }}</td>
                                    <td class="px-3 py-2">
                                        <p class="text-(--text-heading) truncate max-w-xs">{{ e.description || e.referenceNumber }}</p>
                                        <p v-if="e.referenceNumber && e.description" class="text-xxs text-(--text-muted) font-mono">{{ e.referenceNumber }}</p>
                                    </td>
                                    <td class="px-3 py-2 text-right font-mono"
                                        :class="e.direction === 'deposit' ? 'text-(--color-success)' : 'text-(--color-danger)'">
                                        {{ e.direction === 'deposit' ? '+' : '-' }}{{ e.amountAbs.toFixed(2) }}
                                    </td>
                                    <td class="px-3 py-2 text-center">
                                        <span v-if="e.matchedInSession === session.id" class="text-xxs px-1.5 py-0.5 rounded font-mono badge-soft-success">in this</span>
                                        <span v-else-if="e.matchedInSession" class="text-xxs px-1.5 py-0.5 rounded font-mono badge-soft-secondary">other</span>
                                        <button v-else-if="canWrite && session.status === 'open' && selectedLineId" type="button"
                                            class="action-btn action-btn-primary" title="Match to selected statement line"
                                            @click="onMatch(e.id)">
                                            <i class="ti ti-link text-xs" />
                                        </button>
                                        <span v-else class="text-xxs text-(--text-muted)">-</span>
                                    </td>
                                </tr>
                                <tr v-if="ledgerEntries.length === 0">
                                    <td colspan="4" class="p-8 text-center text-xxs text-(--text-muted)">No posted ledger entries on this bank's GL within the session period.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <p v-if="selectedLineId" class="text-xxs text-(--text-muted) text-center">
                Pick a ledger entry on the right with <i class="ti ti-link" /> to match it to the selected statement line.
            </p>
            <p v-else-if="canWrite && session.status === 'open'" class="text-xxs text-(--text-muted) text-center">
                Click an unmatched statement line on the left to select it, then pick its counterpart on the right.
            </p>
        </div>

        <div v-if="showAddLineModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-xl bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Add Statement Line</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showAddLineModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="postLine">
                    <div class="p-5 space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Statement Date *</label>
                                <input v-model="lineForm.statement_date" type="date" required class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Amount *</label>
                                <input v-model.number="lineForm.amount" type="number" step="0.01" required
                                    class="form-control text-xs font-mono text-right"
                                    placeholder="positive = deposit, negative = withdrawal" />
                                <p class="text-xxs text-(--text-muted)">Positive: money INTO bank (deposit). Negative: OUT (withdrawal).</p>
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Description *</label>
                            <input v-model="lineForm.description" type="text" required maxlength="500"
                                class="form-control text-xs" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Reference #</label>
                            <input v-model="lineForm.reference_number" type="text" maxlength="64"
                                class="form-control text-xs font-mono" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Notes</label>
                            <textarea v-model="lineForm.notes" rows="2" maxlength="500" class="form-control text-xs resize-none" />
                        </div>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showAddLineModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="!canSubmitLine || postingLine">
                            <i v-if="postingLine" class="ti ti-loader-2 animate-spin" />
                            Add Line
                        </button>
                    </footer>
                </form>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useFinance } from '~/composables/useFinance'
import { useToast } from '~/composables/useToast'
import { useAuthStore } from '~/stores/auth'
import type {
    BankReconSession,
    BankReconStatus,
    BankReconPeriodLedgerEntry,
    CreateBankReconStatementLinePayload,
} from '~/types/finance'

definePageMeta({ breadcrumb: 'Session' })

const route = useRoute()
const finance = useFinance()
const toast = useToast()
const authStore = useAuthStore()

const sessionId = computed(() => route.params.id as string)

const canRead   = computed(() => authStore.hasPermission('fms.bank_recon.read'))
const canWrite  = computed(() => authStore.hasPermission('fms.bank_recon.write'))
const canReopen = computed(() => authStore.hasPermission('fms.bank_recon.reopen'))

const loading = ref(false)
const closing = ref(false)
const reopening = ref(false)
const postingLine = ref(false)

const session = ref<BankReconSession | null>(null)
const ledgerEntries = ref<BankReconPeriodLedgerEntry[]>([])
const selectedLineId = ref<string | null>(null)

const formatDate = (s: string | null) => {
    if (!s) return '-'
    const d = new Date(s)
    return isNaN(d.getTime()) ? s : d.toISOString().slice(0, 10)
}
const statusBadge = (s: BankReconStatus) => ({
    open:   'badge-soft-warning',
    closed: 'badge-soft-success',
}[s] || 'badge-soft-secondary')

const loadSession = async () => {
    if (!canRead.value) return
    loading.value = true
    try {
        const [s, le] = await Promise.all([
            finance.bankReconciliations.show(sessionId.value),
            finance.bankReconciliations.periodLedgerEntries(sessionId.value),
        ])
        session.value = s.data
        ledgerEntries.value = le.data
    } catch (err: any) {
        toast.error('Failed to load session', err?.data?.message)
        session.value = null
    } finally {
        loading.value = false
    }
}

const selectLine = (id: string) => {
    const line = session.value?.statementLines.find(l => l.id === id)
    if (!line || line.isMatched) {
        selectedLineId.value = null
        return
    }
    selectedLineId.value = selectedLineId.value === id ? null : id
}

const onMatch = async (ledgerEntryId: string) => {
    if (!selectedLineId.value) return
    try {
        await finance.bankReconciliations.matchLine(selectedLineId.value, ledgerEntryId)
        toast.success('Matched')
        selectedLineId.value = null
        await loadSession()
    } catch (err: any) {
        toast.error('Match failed', err?.data?.message)
    }
}

const onUnmatch = async (lineId: string) => {
    try {
        await finance.bankReconciliations.unmatchLine(lineId)
        toast.success('Unmatched')
        await loadSession()
    } catch (err: any) {
        toast.error('Unmatch failed', err?.data?.message)
    }
}

const onRemoveLine = async (lineId: string) => {
    if (!confirm('Delete this unmatched statement line?')) return
    try {
        await finance.bankReconciliations.removeLine(lineId)
        toast.success('Line deleted')
        await loadSession()
    } catch (err: any) {
        toast.error('Delete failed', err?.data?.message)
    }
}

const onClose = async () => {
    if (!session.value) return
    closing.value = true
    try {
        await finance.bankReconciliations.close(session.value.id)
        toast.success('Session closed')
        await loadSession()
    } catch (err: any) {
        toast.error('Close failed', err?.data?.message)
    } finally {
        closing.value = false
    }
}

const onReopen = async () => {
    if (!session.value) return
    reopening.value = true
    try {
        await finance.bankReconciliations.reopen(session.value.id)
        toast.success('Session reopened')
        await loadSession()
    } catch (err: any) {
        toast.error('Reopen failed', err?.data?.message)
    } finally {
        reopening.value = false
    }
}

const showAddLineModal = ref(false)
const today = new Date().toISOString().slice(0, 10)
const lineForm = reactive<CreateBankReconStatementLinePayload>({
    statement_date: today,
    description: '',
    reference_number: null,
    amount: 0,
    notes: null,
})

const openAddLineModal = () => {
    Object.assign(lineForm, {
        statement_date: today,
        description: '',
        reference_number: null,
        amount: 0,
        notes: null,
    })
    showAddLineModal.value = true
}

const canSubmitLine = computed(() => {
    if (!lineForm.statement_date) return false
    if (!lineForm.description.trim()) return false
    if (!lineForm.amount || Math.abs(lineForm.amount) < 0.005) return false
    return true
})

const postLine = async () => {
    if (!canSubmitLine.value || !session.value) return
    postingLine.value = true
    try {
        const payload: CreateBankReconStatementLinePayload = {
            ...lineForm,
            description: lineForm.description.trim(),
            reference_number: lineForm.reference_number?.trim() || null,
            notes: lineForm.notes?.trim() || null,
        }
        await finance.bankReconciliations.addLine(session.value.id, payload)
        toast.success('Statement line added')
        showAddLineModal.value = false
        await loadSession()
    } catch (err: any) {
        toast.error('Add line failed', err?.data?.message)
    } finally {
        postingLine.value = false
    }
}

onMounted(loadSession)
</script>

<style scoped>
.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
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

.action-btn-primary {
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}

.action-btn-danger:hover {
    color: var(--color-danger);
    border-color: rgb(var(--color-danger-rgb) / 0.4);
}
</style>
