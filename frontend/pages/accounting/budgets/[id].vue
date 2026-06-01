<template>
    <NuxtLayout name="default">
        <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
            <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
            <span class="text-xs text-(--text-muted)">Loading budget...</span>
        </div>
        <div v-else-if="!budget" class="glass-card rounded-2xl py-20 text-center">
            <i class="ti ti-alert-circle text-4xl text-(--text-muted)" />
            <h4 class="text-sm font-semibold text-(--text-heading) mt-3">Budget not found</h4>
            <NuxtLink to="/accounting/budgets" class="text-xs underline text-(--color-primary) mt-2 inline-block">Back to budgets</NuxtLink>
        </div>
        <div v-else class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-semibold font-mono">{{ budget.budgetNumber }}</h1>
                        <span class="text-xxs px-1.5 py-0.5 rounded font-mono" :class="statusBadge(budget.status)">{{ budget.status }}</span>
                    </div>
                    <p class="text-xs text-(--text-muted) mt-1">
                        {{ budget.name }} / {{ formatDate(budget.startDate) }} to {{ formatDate(budget.endDate) }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button v-if="canWrite && budget.isEditable" type="button" class="btn btn-ghost text-xs"
                        @click="openAddLineModal">
                        <i class="ti ti-plus" />Line
                    </button>
                    <button v-if="canWrite && budget.isActivatable" type="button" class="btn btn-primary text-xs"
                        :disabled="activating" @click="onActivate">
                        <i v-if="activating" class="ti ti-loader-2 animate-spin" />
                        <i v-else class="ti ti-check" />
                        Activate
                    </button>
                    <button v-if="canWrite && budget.isArchivable" type="button" class="btn btn-ghost text-xs"
                        :disabled="archiving" @click="onArchive">
                        <i v-if="archiving" class="ti ti-loader-2 animate-spin" />
                        <i v-else class="ti ti-archive" />
                        Archive
                    </button>
                    <button v-if="canDelete && budget.isEditable" type="button" class="btn btn-ghost text-xs text-(--color-danger)"
                        :disabled="deleting" @click="onDelete">
                        <i v-if="deleting" class="ti ti-loader-2 animate-spin" />
                        <i v-else class="ti ti-trash" />
                        Delete
                    </button>
                </div>
            </header>

            <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Expected Total</span>
                    <p class="text-lg font-bold text-(--text-heading) font-mono">{{ budget.expectedTotal.toFixed(2) }}</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Actual Total</span>
                    <p class="text-lg font-bold text-(--text-heading) font-mono">{{ actualTotal.toFixed(2) }}</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Total Variance</span>
                    <p class="text-lg font-bold font-mono"
                        :class="varianceTotal >= 0 ? 'text-(--color-success)' : 'text-(--color-danger)'">
                        {{ varianceTotal >= 0 ? '+' : '' }}{{ varianceTotal.toFixed(2) }}
                    </p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Health</span>
                    <p class="text-sm font-bold font-mono flex items-center gap-1">
                        <span class="inline-block w-2 h-2 rounded-full" :class="healthDot" />
                        {{ greenCount }}g / {{ yellowCount }}y / {{ redCount }}r
                    </p>
                    <p class="text-xxs text-(--text-muted)">of {{ budget.lines?.length ?? 0 }} lines</p>
                </div>
            </section>

            <section class="glass-card rounded-2xl overflow-hidden">
                <header class="flex items-center justify-between p-3 border-b border-(--border-color) bg-(--bg-muted)/30">
                    <h3 class="text-xs font-bold uppercase tracking-widest text-(--text-muted)">Variance by Account</h3>
                    <span class="text-xxs text-(--text-muted)">Computed against posted ledger entries in period</span>
                </header>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-(--bg-muted)/40 text-(--text-muted)">
                            <tr>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-2 w-8"></th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-2">Account</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-2 w-32">Expected</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-2 w-32">Actual</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-2 w-32">Variance</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-2 w-24">%</th>
                                <th class="text-center font-bold uppercase tracking-widest text-xxs px-3 py-2 w-24">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in budget.lines || []" :key="line.id"
                                class="border-t border-(--border-color) hover:bg-(--bg-muted)/40">
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-block w-2 h-2 rounded-full" :class="bucketDot(line.variance?.bucket)" />
                                </td>
                                <td class="px-3 py-2">
                                    <p class="text-(--text-heading) font-mono">
                                        <span class="font-semibold">{{ line.account?.code }}</span>
                                        <span class="ml-2 text-(--text-muted)">{{ line.account?.name }}</span>
                                    </p>
                                    <p v-if="line.account?.type" class="text-xxs text-(--text-muted)">{{ line.account.type }}</p>
                                </td>
                                <td class="px-3 py-2 text-right font-mono">
                                    <span v-if="!budget.isEditable">{{ line.expectedAmount.toFixed(2) }}</span>
                                    <input v-else v-model.number="inlineDraft[line.id]" type="number" step="0.01" min="0"
                                        class="form-control text-xs font-mono text-right w-24"
                                        @blur="saveInline(line.id)" @keyup.enter="saveInline(line.id)" />
                                </td>
                                <td class="px-3 py-2 text-right font-mono">{{ line.variance?.actual.toFixed(2) ?? '-' }}</td>
                                <td class="px-3 py-2 text-right font-mono"
                                    :class="varianceClass(line.variance)">
                                    <span v-if="line.variance">
                                        {{ line.variance.variance >= 0 ? '+' : '' }}{{ line.variance.variance.toFixed(2) }}
                                    </span>
                                    <span v-else class="text-(--text-muted)">-</span>
                                </td>
                                <td class="px-3 py-2 text-right font-mono"
                                    :class="varianceClass(line.variance)">
                                    <span v-if="line.variance?.variancePct != null">
                                        {{ line.variance.variancePct >= 0 ? '+' : '' }}{{ line.variance.variancePct.toFixed(1) }}%
                                    </span>
                                    <span v-else class="text-(--text-muted)">-</span>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <button v-if="canWrite && budget.isEditable" type="button" class="action-btn action-btn-danger"
                                        title="Remove line" @click="onRemoveLine(line.id)">
                                        <i class="ti ti-trash text-xs" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="!budget.lines || budget.lines.length === 0">
                                <td colspan="7" class="p-8 text-center text-xxs text-(--text-muted)">
                                    No lines yet. Add lines to start tracking variance.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot v-if="budget.lines && budget.lines.length > 0" class="bg-(--bg-muted)/30 text-(--text-heading) font-bold">
                            <tr>
                                <td></td>
                                <td class="px-3 py-2 uppercase tracking-widest text-xxs">Total</td>
                                <td class="px-3 py-2 text-right font-mono">{{ budget.expectedTotal.toFixed(2) }}</td>
                                <td class="px-3 py-2 text-right font-mono">{{ actualTotal.toFixed(2) }}</td>
                                <td class="px-3 py-2 text-right font-mono"
                                    :class="varianceTotal >= 0 ? 'text-(--color-success)' : 'text-(--color-danger)'">
                                    {{ varianceTotal >= 0 ? '+' : '' }}{{ varianceTotal.toFixed(2) }}
                                </td>
                                <td></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>
        </div>

        <div v-if="showAddLineModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-xl bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Add Budget Line</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showAddLineModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="postLine">
                    <div class="p-5 space-y-4">
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Account *</label>
                            <select v-model="lineForm.account_id" required class="form-control text-xs" :disabled="accountsLoading">
                                <option value="">Pick account</option>
                                <option v-for="a in availableAccounts" :key="a.id" :value="a.id">
                                    {{ a.code }} / {{ a.name }} ({{ a.type }})
                                </option>
                            </select>
                            <p class="text-xxs text-(--text-muted)">Accounts already on this budget are filtered out.</p>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Expected Amount *</label>
                            <input v-model.number="lineForm.expected_amount" type="number" step="0.01" min="0" required
                                class="form-control text-xs font-mono text-right" />
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
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useFinance } from '~/composables/useFinance'
import { useToast } from '~/composables/useToast'
import { useAuthStore } from '~/stores/auth'
import type {
    Account,
    Budget,
    BudgetLineVariance,
    BudgetStatus,
    CreateBudgetLinePayload,
    VarianceBucket,
} from '~/types/finance'

definePageMeta({ breadcrumb: 'Budget' })

const route = useRoute()
const router = useRouter()
const finance = useFinance()
const toast = useToast()
const authStore = useAuthStore()

const budgetId = computed(() => route.params.id as string)

const canRead   = computed(() => authStore.hasPermission('fms.budgets.read'))
const canWrite  = computed(() => authStore.hasPermission('fms.budgets.write'))
const canDelete = computed(() => authStore.hasPermission('fms.budgets.delete'))

const loading = ref(false)
const activating = ref(false)
const archiving = ref(false)
const deleting = ref(false)
const postingLine = ref(false)

const budget = ref<Budget | null>(null)

const formatDate = (s: string | null) => {
    if (!s) return '-'
    const d = new Date(s)
    return isNaN(d.getTime()) ? s : d.toISOString().slice(0, 10)
}
const statusBadge = (s: BudgetStatus) => ({
    draft:    'badge-soft-warning',
    active:   'badge-soft-success',
    archived: 'badge-soft-secondary',
}[s] || 'badge-soft-secondary')

const bucketDot = (b?: VarianceBucket | null) => ({
    green:  'bg-(--color-success)',
    yellow: 'bg-(--color-warning)',
    red:    'bg-(--color-danger)',
}[b ?? 'green'] || 'bg-(--text-muted)')

const varianceClass = (v?: BudgetLineVariance | null) => {
    if (!v) return 'text-(--text-muted)'
    if (v.bucket === 'green')  return 'text-(--color-success)'
    if (v.bucket === 'yellow') return 'text-(--color-warning)'
    return 'text-(--color-danger)'
}

const inlineDraft = reactive<Record<string, number>>({})

const seedInlineDraft = () => {
    for (const line of budget.value?.lines ?? []) {
        inlineDraft[line.id] = line.expectedAmount
    }
}

const loadBudget = async () => {
    if (!canRead.value) return
    loading.value = true
    try {
        const res = await finance.budgets.variance(budgetId.value)
        budget.value = res.data
        seedInlineDraft()
    } catch (err: any) {
        toast.error('Failed to load budget', err?.data?.message)
        budget.value = null
    } finally {
        loading.value = false
    }
}

const greenCount  = computed(() => (budget.value?.lines ?? []).filter(l => l.variance?.bucket === 'green').length)
const yellowCount = computed(() => (budget.value?.lines ?? []).filter(l => l.variance?.bucket === 'yellow').length)
const redCount    = computed(() => (budget.value?.lines ?? []).filter(l => l.variance?.bucket === 'red').length)
const healthDot   = computed(() => {
    if (redCount.value > 0) return 'bg-(--color-danger)'
    if (yellowCount.value > 0) return 'bg-(--color-warning)'
    return 'bg-(--color-success)'
})
const actualTotal   = computed(() => (budget.value?.lines ?? []).reduce((s, l) => s + (l.variance?.actual ?? 0), 0))
const varianceTotal = computed(() => (budget.value?.lines ?? []).reduce((s, l) => s + (l.variance?.variance ?? 0), 0))

const saveInline = async (lineId: string) => {
    if (!budget.value?.isEditable) return
    const line = budget.value.lines?.find(l => l.id === lineId)
    if (!line) return
    const v = inlineDraft[lineId]
    if (v == null || v < 0) { inlineDraft[lineId] = line.expectedAmount; return }
    if (Math.abs(v - line.expectedAmount) < 0.005) return
    try {
        await finance.budgets.updateLine(lineId, { expected_amount: v })
        toast.success('Line updated')
        await loadBudget()
    } catch (err: any) {
        toast.error('Update failed', err?.data?.message)
        inlineDraft[lineId] = line.expectedAmount
    }
}

const onRemoveLine = async (lineId: string) => {
    if (!confirm('Remove this budget line?')) return
    try {
        await finance.budgets.removeLine(lineId)
        toast.success('Line removed')
        await loadBudget()
    } catch (err: any) {
        toast.error('Remove failed', err?.data?.message)
    }
}

const onActivate = async () => {
    if (!budget.value) return
    activating.value = true
    try {
        await finance.budgets.activate(budget.value.id)
        toast.success('Budget activated')
        await loadBudget()
    } catch (err: any) {
        toast.error('Activate failed', err?.data?.message)
    } finally {
        activating.value = false
    }
}

const onArchive = async () => {
    if (!budget.value) return
    archiving.value = true
    try {
        await finance.budgets.archive(budget.value.id)
        toast.success('Budget archived')
        await loadBudget()
    } catch (err: any) {
        toast.error('Archive failed', err?.data?.message)
    } finally {
        archiving.value = false
    }
}

const onDelete = async () => {
    if (!budget.value) return
    if (!confirm('Delete this draft budget? This cannot be undone.')) return
    deleting.value = true
    try {
        await finance.budgets.destroy(budget.value.id)
        toast.success('Budget deleted')
        router.push('/accounting/budgets')
    } catch (err: any) {
        toast.error('Delete failed', err?.data?.message)
    } finally {
        deleting.value = false
    }
}

const showAddLineModal = ref(false)
const accountsList = ref<Account[]>([])
const accountsLoading = ref(false)
const availableAccounts = computed(() => {
    const used = new Set((budget.value?.lines ?? []).map(l => l.accountId))
    return accountsList.value.filter(a => !used.has(a.id))
})

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

const lineForm = reactive<CreateBudgetLinePayload>({
    account_id: '',
    expected_amount: 0,
    notes: null,
})

const openAddLineModal = () => {
    Object.assign(lineForm, { account_id: '', expected_amount: 0, notes: null })
    showAddLineModal.value = true
    ensureAccountsLoaded()
}

const canSubmitLine = computed(() =>
    !!lineForm.account_id && lineForm.expected_amount >= 0
)

const postLine = async () => {
    if (!canSubmitLine.value || !budget.value) return
    postingLine.value = true
    try {
        const payload: CreateBudgetLinePayload = {
            ...lineForm,
            notes: lineForm.notes?.trim() || null,
        }
        await finance.budgets.addLine(budget.value.id, payload)
        toast.success('Line added')
        showAddLineModal.value = false
        await loadBudget()
    } catch (err: any) {
        toast.error('Add failed', err?.data?.message)
    } finally {
        postingLine.value = false
    }
}

watch(budget, seedInlineDraft)
onMounted(loadBudget)
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

.action-btn-danger:hover {
    color: var(--color-danger);
    border-color: rgb(var(--color-danger-rgb) / 0.4);
}
</style>
