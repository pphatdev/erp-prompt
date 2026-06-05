<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Journals</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Double-entry postings to the general ledger. Once posted, journals are immutable — reverse via an offsetting entry.</p>
                </div>
                <button v-if="canWrite" type="button" class="btn btn-primary text-xs" @click="openPostModal">
                    <i class="ti ti-plus" />New Journal
                </button>
            </header>

            <!-- KPIs -->
            <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Entries</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-primary flex items-center justify-center">
                            <i class="ti ti-book-2 text-sm" />
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiCountAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">On this page</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Posted Volume</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-success flex items-center justify-center">
                            <i class="ti ti-arrow-up-right text-sm" />
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ formatMoney(kpiVolumeAnim) }}</p>
                    <p class="text-xxs text-(--text-muted)">Sum of debits across page</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Today</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-info flex items-center justify-center">
                            <i class="ti ti-calendar text-sm" />
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiTodayAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">Posted today</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Reversed</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-warning flex items-center justify-center">
                            <i class="ti ti-rotate-2 text-sm" />
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiReversedAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">Marked reversed</p>
                </div>
            </section>

            <!-- Status filter chips -->
            <section class="flex items-center gap-2 flex-wrap max-sm:justify-center">
                <button type="button" class="chip" :class="{ active: statusFilter === '' }" @click="setStatusFilter('')">All</button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'posted' }" @click="setStatusFilter('posted')">
                    <i class="ti ti-check" /> Posted
                </button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'reversed' }" @click="setStatusFilter('reversed')">
                    <i class="ti ti-rotate-2" /> Reversed
                </button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'draft' }" @click="setStatusFilter('draft')">
                    <i class="ti ti-pencil" /> Draft
                </button>
            </section>

            <!-- Loading -->
            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading journals...</span>
            </div>

            <!-- Empty -->
            <div v-else-if="journals.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-book-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No journals posted yet</h4>
                <p class="text-xs text-(--text-muted) mt-1">Post your first balanced entry to the ledger.</p>
            </div>

            <!-- Table -->
            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-(--bg-muted)/40 text-(--text-muted)">
                            <tr>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-4 py-3 w-8"></th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-4 py-3">Date</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-4 py-3">Reference</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-4 py-3">Description</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-4 py-3">Status</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-4 py-3">Total</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-4 py-3 w-24">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template v-for="j in journals" :key="j.id">
                                <tr class="border-t border-(--border-color) hover:bg-(--bg-muted)/40"
                                    :class="{ 'opacity-70': j.status === 'reversed' }">
                                    <td class="px-4 py-3 cursor-pointer" @click="toggle(j.id)">
                                        <i class="ti" :class="expanded.has(j.id) ? 'ti-chevron-down' : 'ti-chevron-right'" />
                                    </td>
                                    <td class="px-4 py-3 font-mono text-(--text-body) cursor-pointer" @click="toggle(j.id)">{{ formatDate(j.entry_date) }}</td>
                                    <td class="px-4 py-3 cursor-pointer" @click="toggle(j.id)">
                                        <div class="font-mono font-semibold"
                                            :class="j.status === 'reversed' ? 'text-(--text-muted) line-through' : 'text-(--text-heading)'">
                                            {{ j.reference_number }}
                                        </div>
                                        <div v-if="j.reverses_journal_id" class="text-xxs text-(--text-muted) font-mono mt-0.5">
                                            <i class="ti ti-corner-down-left" />
                                            reverses
                                            <span class="text-(--text-body)">{{ refLookup.get(j.reverses_journal_id) || `#${shortId(j.reverses_journal_id)}` }}</span>
                                        </div>
                                        <div v-if="j.reversed_by_journal_id" class="text-xxs text-(--text-muted) font-mono mt-0.5">
                                            <i class="ti ti-corner-down-right" />
                                            reversed by
                                            <span class="text-(--text-body)">{{ refLookup.get(j.reversed_by_journal_id) || `#${shortId(j.reversed_by_journal_id)}` }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-(--text-body) truncate max-w-md cursor-pointer" @click="toggle(j.id)">{{ j.description || '—' }}</td>
                                    <td class="px-4 py-3 cursor-pointer" @click="toggle(j.id)">
                                        <span class="text-xxs px-1.5 py-0.5 rounded font-mono" :class="statusBadge(j.status)">{{ j.status }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-mono font-semibold text-(--text-heading) cursor-pointer" @click="toggle(j.id)">{{ lineTotal(j).toFixed(2) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <button v-if="canWrite && j.status === 'posted' && !j.reversed_by_journal_id"
                                            type="button"
                                            class="action-btn action-btn-warning"
                                            title="Reverse this journal"
                                            @click.stop="openReverseModal(j)">
                                            <i class="ti ti-rotate-2" />
                                        </button>
                                    </td>
                                </tr>
                                <tr v-if="expanded.has(j.id)" class="border-t border-(--border-color) bg-(--bg-muted)/20">
                                    <td colspan="7" class="px-4 py-3">
                                        <table class="w-full text-xxs font-mono">
                                            <thead class="text-(--text-muted)">
                                                <tr>
                                                    <th class="text-left font-bold uppercase tracking-widest py-1">Account</th>
                                                    <th class="text-right font-bold uppercase tracking-widest py-1 w-32">Debit</th>
                                                    <th class="text-right font-bold uppercase tracking-widest py-1 w-32">Credit</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr v-for="line in j.lines" :key="line.id" class="border-t border-(--border-color)/40">
                                                    <td class="py-1.5">
                                                        <span v-if="line.account">
                                                            <span class="text-(--text-heading)">{{ line.account.code }}</span>
                                                            <span class="text-(--text-muted) ml-2">{{ line.account.name }}</span>
                                                        </span>
                                                        <span v-else class="text-(--text-muted)">—</span>
                                                    </td>
                                                    <td class="text-right py-1.5">{{ line.debit > 0 ? line.debit.toFixed(2) : '' }}</td>
                                                    <td class="text-right py-1.5">{{ line.credit > 0 ? line.credit.toFixed(2) : '' }}</td>
                                                </tr>
                                                <tr class="border-t border-(--border-color) font-bold text-(--text-heading)">
                                                    <td class="py-1.5 uppercase tracking-widest text-xxs">Total</td>
                                                    <td class="text-right py-1.5">{{ debitTotal(j).toFixed(2) }}</td>
                                                    <td class="text-right py-1.5">{{ creditTotal(j).toFixed(2) }}</td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <Pagination :page="pagination.page" :limit="pagination.limit"
                    :total="pagination.total" :total-pages="pagination.totalPages"
                    @update:page="(p) => { pagination.page = p; load() }"
                    @update:limit="(l) => { pagination.limit = l; pagination.page = 1; load() }" />
            </section>
        </div>

        <!-- Reverse Modal -->
        <div v-if="reverseTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-lg bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Reverse Journal Entry</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="reverseTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-4">
                    <div class="p-3 rounded-lg badge-soft-warning text-xs flex items-start gap-2">
                        <i class="ti ti-alert-triangle mt-0.5" />
                        <div>
                            <p class="font-semibold">Posts an offsetting entry</p>
                            <p class="text-xxs mt-0.5">Original journal <span class="font-mono">{{ reverseTarget.reference_number }}</span> stays in the ledger and is marked <span class="font-mono">reversed</span>. A new balanced entry with DR/CR swapped is posted today. This action cannot be undone.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Reversal Reference</label>
                            <input v-model="reverseForm.reference_number" type="text" maxlength="64"
                                class="form-control text-xs font-mono" />
                            <p class="text-xxs text-(--text-muted)">Leave default to use <span class="font-mono">{ORIGINAL}-REV</span>.</p>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Memo</label>
                            <input v-model="reverseForm.description" type="text" maxlength="255"
                                class="form-control text-xs" />
                        </div>
                    </div>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="reverseTarget = null">Cancel</button>
                    <button type="button" class="btn btn-warning text-xs" :disabled="reversing" @click="confirmReverse">
                        <i v-if="reversing" class="ti ti-loader-2 animate-spin" />
                        <i v-else class="ti ti-rotate-2" />
                        Reverse Journal
                    </button>
                </footer>
            </div>
        </div>

        <!-- Post Modal -->
        <div v-if="showPostModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-3xl bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Post Journal Entry</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showPostModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="post">
                    <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
                        <!-- Header fields -->
                        <div class="grid grid-cols-3 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Reference *</label>
                                <input v-model="postForm.reference_number" type="text" required maxlength="64"
                                    placeholder="e.g. JV-2026-001"
                                    class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Entry Date *</label>
                                <input v-model="postForm.entry_date" type="date" required
                                    class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Description</label>
                                <input v-model="postForm.description" type="text" maxlength="255"
                                    placeholder="Memo line"
                                    class="form-control text-xs" />
                            </div>
                        </div>

                        <!-- Lines -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Lines (min 2)</label>
                                <button type="button" class="btn btn-ghost text-xxs" @click="addLine">
                                    <i class="ti ti-plus" />Add Line
                                </button>
                            </div>

                            <table class="w-full text-xs">
                                <thead class="text-(--text-muted)">
                                    <tr>
                                        <th class="text-left font-bold uppercase tracking-widest text-xxs py-1">Account</th>
                                        <th class="text-right font-bold uppercase tracking-widest text-xxs py-1 w-32">Debit</th>
                                        <th class="text-right font-bold uppercase tracking-widest text-xxs py-1 w-32">Credit</th>
                                        <th class="w-8"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(line, idx) in postForm.lines" :key="idx" class="border-t border-(--border-color)/40">
                                        <td class="py-1.5 pr-2">
                                            <select v-model="line.account_id" required class="form-control text-xs">
                                                <option value="">— pick account —</option>
                                                <option v-for="a in flatAccounts" :key="a.id" :value="a.id">
                                                    {{ a.code }} · {{ a.name }} ({{ a.type }})
                                                </option>
                                            </select>
                                        </td>
                                        <td class="py-1.5 pr-2">
                                            <input v-model.number="line.debit" type="number" step="0.01" min="0"
                                                @input="onAmountChange(idx, 'debit')"
                                                class="form-control text-xs font-mono text-right" />
                                        </td>
                                        <td class="py-1.5 pr-2">
                                            <input v-model.number="line.credit" type="number" step="0.01" min="0"
                                                @input="onAmountChange(idx, 'credit')"
                                                class="form-control text-xs font-mono text-right" />
                                        </td>
                                        <td class="py-1.5">
                                            <button type="button" class="action-btn action-btn-danger"
                                                :disabled="postForm.lines.length <= 2"
                                                @click="removeLine(idx)">
                                                <i class="ti ti-trash" />
                                            </button>
                                        </td>
                                    </tr>
                                    <tr class="border-t border-(--border-color) font-bold text-(--text-heading) font-mono">
                                        <td class="py-2 text-xxs uppercase tracking-widest text-(--text-muted)">Totals</td>
                                        <td class="py-2 text-right">{{ formDebitTotal.toFixed(2) }}</td>
                                        <td class="py-2 text-right">{{ formCreditTotal.toFixed(2) }}</td>
                                        <td></td>
                                    </tr>
                                </tbody>
                            </table>

                            <!-- Balance indicator -->
                            <div class="flex items-center gap-2 text-xs px-3 py-2 rounded-lg"
                                :class="isBalanced ? 'badge-soft-success' : 'badge-soft-warning'">
                                <i class="ti" :class="isBalanced ? 'ti-check' : 'ti-alert-triangle'" />
                                <span v-if="isBalanced">Balanced · DR {{ formDebitTotal.toFixed(2) }} = CR {{ formCreditTotal.toFixed(2) }}</span>
                                <span v-else>
                                    Out of balance by {{ Math.abs(formDebitTotal - formCreditTotal).toFixed(2) }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showPostModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="!canSubmit || submitting">
                            <i v-if="submitting" class="ti ti-loader-2 animate-spin" />
                            Post Journal
                        </button>
                    </footer>
                </form>
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
import type {
    Account,
    JournalEntry,
    JournalStatus,
    CreateJournalEntryPayload,
    CreateJournalLinePayload,
    ReverseJournalPayload,
} from '~/types/finance'

definePageMeta({ breadcrumb: 'Journals' })

const finance = useFinance()
const toast = useToast()
const authStore = useAuthStore()

const canRead = computed(() => authStore.hasPermission('fms.ledger.read'))
const canWrite = computed(() => authStore.hasPermission('fms.ledger.write'))

const loading = ref(false)
const submitting = ref(false)
const reversing = ref(false)
const journals = ref<JournalEntry[]>([])
const flatAccounts = ref<Account[]>([])
const pagination = reactive({ page: 1, limit: 25, total: 0, totalPages: 1 })
const expanded = ref<Set<string>>(new Set())
const statusFilter = ref<'' | JournalStatus>('')

// Lookup table for resolving reversal-link IDs to their reference numbers on
// the current page. External pairs (off-page) fall back to a short id badge.
const refLookup = computed(() => {
    const map = new Map<string, string>()
    journals.value.forEach(j => map.set(j.id, j.reference_number))
    return map
})

const shortId = (id: string) => id.slice(0, 8)

const toggle = (id: string) => {
    if (expanded.value.has(id)) expanded.value.delete(id)
    else expanded.value.add(id)
    expanded.value = new Set(expanded.value)
}

const debitTotal  = (j: JournalEntry) => j.lines.reduce((s, l) => s + (Number(l.debit)  || 0), 0)
const creditTotal = (j: JournalEntry) => j.lines.reduce((s, l) => s + (Number(l.credit) || 0), 0)
const lineTotal   = (j: JournalEntry) => debitTotal(j)

const formatDate = (s: string) => {
    if (!s) return ''
    const d = new Date(s)
    if (isNaN(d.getTime())) return s
    return d.toISOString().slice(0, 10)
}

const formatMoney = (n: number) => {
    const abs = Math.abs(n)
    if (abs >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`
    if (abs >= 1_000)     return `${(n / 1_000).toFixed(1)}K`
    return n.toFixed(2)
}

const statusBadge = (s: JournalStatus) => ({
    posted:   'badge-soft-success',
    draft:    'badge-soft-info',
    reversed: 'badge-soft-warning',
}[s] || 'badge-soft-primary')

// KPI source numbers
const kpiCount    = computed(() => journals.value.length)
const kpiVolume   = computed(() => journals.value.reduce((s, j) => s + debitTotal(j), 0))
const today       = new Date().toISOString().slice(0, 10)
const kpiToday    = computed(() => journals.value.filter(j => formatDate(j.entry_date) === today).length)
const kpiReversed = computed(() => journals.value.filter(j => j.status === 'reversed').length)

const kpiCountAnim    = useCountUp(() => kpiCount.value)
const kpiVolumeAnim   = useCountUp(() => kpiVolume.value)
const kpiTodayAnim    = useCountUp(() => kpiToday.value)
const kpiReversedAnim = useCountUp(() => kpiReversed.value)

// ---- Posting form ------------------------------------------------------------

const showPostModal = ref(false)

const makeLine = (): CreateJournalLinePayload => ({ account_id: '', debit: 0, credit: 0 })

const postForm = reactive<CreateJournalEntryPayload>({
    reference_number: '',
    description: '',
    entry_date: today,
    lines: [makeLine(), makeLine()],
})

const resetPostForm = () => {
    postForm.reference_number = ''
    postForm.description = ''
    postForm.entry_date = new Date().toISOString().slice(0, 10)
    postForm.lines = [makeLine(), makeLine()]
}

// Per skills/accounting/rules.md § 3.B: a single line is either a debit OR a credit,
// never both. Editing one zeroes the other.
const onAmountChange = (idx: number, field: 'debit' | 'credit') => {
    const line = postForm.lines[idx]
    if ((line[field] ?? 0) > 0) {
        if (field === 'debit') line.credit = 0
        else line.debit = 0
    }
}

const addLine = () => postForm.lines.push(makeLine())
const removeLine = (idx: number) => {
    if (postForm.lines.length <= 2) return
    postForm.lines.splice(idx, 1)
}

const formDebitTotal  = computed(() => postForm.lines.reduce((s, l) => s + (Number(l.debit)  || 0), 0))
const formCreditTotal = computed(() => postForm.lines.reduce((s, l) => s + (Number(l.credit) || 0), 0))
const isBalanced      = computed(() => Math.abs(formDebitTotal.value - formCreditTotal.value) < 0.001 && formDebitTotal.value > 0)

const canSubmit = computed(() => {
    if (!postForm.reference_number.trim()) return false
    if (!postForm.entry_date) return false
    if (postForm.lines.length < 2) return false
    // Every line: must have an account and exactly one of debit/credit > 0.
    for (const l of postForm.lines) {
        if (!l.account_id) return false
        const d = Number(l.debit)  || 0
        const c = Number(l.credit) || 0
        if (d > 0 && c > 0) return false
        if (d <= 0 && c <= 0) return false
    }
    return isBalanced.value
})

const flattenAccounts = (nodes: Account[]): Account[] => {
    const out: Account[] = []
    const walk = (list: Account[]) => list.forEach(n => { out.push(n); if (n.children?.length) walk(n.children) })
    walk(nodes)
    return out
}

const openPostModal = async () => {
    resetPostForm()
    showPostModal.value = true
    if (flatAccounts.value.length === 0) {
        try {
            const res = await finance.accounts.tree()
            flatAccounts.value = flattenAccounts(res.data).sort((a, b) => a.code.localeCompare(b.code))
        } catch (err: any) {
            toast.error('Failed to load accounts', err?.data?.message)
        }
    }
}

const post = async () => {
    if (!canSubmit.value) return
    submitting.value = true
    try {
        const payload: CreateJournalEntryPayload = {
            reference_number: postForm.reference_number.trim(),
            description: postForm.description?.trim() || null,
            entry_date: postForm.entry_date,
            lines: postForm.lines.map(l => ({
                account_id: l.account_id,
                debit:  (Number(l.debit)  || 0) > 0 ? Number(l.debit)  : 0,
                credit: (Number(l.credit) || 0) > 0 ? Number(l.credit) : 0,
            })),
        }
        await finance.journals.create(payload)
        toast.success('Journal posted', payload.reference_number)
        showPostModal.value = false
        await load()
    } catch (err: any) {
        toast.error('Post failed', err?.data?.message)
    } finally {
        submitting.value = false
    }
}

// ---- List load ---------------------------------------------------------------

const load = async () => {
    if (!canRead.value) return
    loading.value = true
    try {
        const res = await finance.journals.list({
            page: pagination.page,
            limit: pagination.limit,
            status: statusFilter.value || undefined,
        })
        journals.value = res.data
        const p = (res as any).pagination
        if (p) {
            pagination.total = p.total ?? 0
            pagination.totalPages = p.totalPages ?? 1
            pagination.page = p.page ?? 1
            pagination.limit = p.limit ?? pagination.limit
        }
    } catch (err: any) {
        toast.error('Failed to load journals', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const setStatusFilter = (s: '' | JournalStatus) => {
    if (statusFilter.value === s) return
    statusFilter.value = s
    pagination.page = 1
    load()
}

// ---- Reversal flow -----------------------------------------------------------

const reverseTarget = ref<JournalEntry | null>(null)
const reverseForm = reactive<ReverseJournalPayload>({
    reference_number: '',
    description: '',
})

const openReverseModal = (j: JournalEntry) => {
    reverseTarget.value = j
    reverseForm.reference_number = `${j.reference_number}-REV`
    reverseForm.description = `Reversal of ${j.reference_number}`
}

const confirmReverse = async () => {
    if (!reverseTarget.value) return
    reversing.value = true
    try {
        const payload: ReverseJournalPayload = {
            reference_number: reverseForm.reference_number?.trim() || undefined,
            description:      reverseForm.description?.trim() || undefined,
        }
        const res = await finance.journals.reverse(reverseTarget.value.id, payload)
        toast.success('Journal reversed', res.data.reference_number)
        reverseTarget.value = null
        await load()
    } catch (err: any) {
        toast.error('Reverse failed', err?.data?.message)
    } finally {
        reversing.value = false
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

.action-btn-warning:hover {
    color: var(--color-warning);
    border-color: rgb(var(--color-warning-rgb) / 0.4);
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

.chip:hover {
    background: var(--bg-muted);
}

.chip.active {
    background: rgb(var(--color-primary-rgb) / 0.12);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}

.action-btn[disabled] {
    opacity: 0.3;
    cursor: not-allowed;
}
</style>
