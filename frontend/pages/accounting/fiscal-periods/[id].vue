<template>
    <NuxtLayout name="default">
        <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
            <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
            <span class="text-xs text-(--text-muted)">Loading period...</span>
        </div>
        <div v-else-if="!period" class="glass-card rounded-2xl py-20 text-center">
            <i class="ti ti-alert-circle text-4xl text-(--text-muted)" />
            <h4 class="text-sm font-semibold text-(--text-heading) mt-3">Period not found</h4>
            <NuxtLink to="/accounting/fiscal-periods" class="text-xs underline text-(--color-primary) mt-2 inline-block">Back to periods</NuxtLink>
        </div>
        <div v-else class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-semibold font-mono">{{ period.periodNumber }}</h1>
                        <span class="text-xxs px-1.5 py-0.5 rounded font-mono" :class="statusBadge(period.status)">{{ period.status }}</span>
                    </div>
                    <p class="text-xs text-(--text-muted) mt-1">
                        {{ period.name }} / {{ formatDate(period.startDate) }} to {{ formatDate(period.endDate) }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button v-if="canReopen && period.isReopenable" type="button" class="btn btn-ghost text-xs"
                        :disabled="reopening" @click="onReopen">
                        <i v-if="reopening" class="ti ti-loader-2 animate-spin" />
                        <i v-else class="ti ti-lock-open" />
                        Reopen
                    </button>
                    <button v-if="canDelete && period.status === 'open'" type="button" class="btn btn-ghost text-xs text-(--color-danger)"
                        :disabled="deleting" @click="onDelete">
                        <i v-if="deleting" class="ti ti-loader-2 animate-spin" />
                        <i v-else class="ti ti-trash" />
                        Delete
                    </button>
                </div>
            </header>

            <section v-if="period.status === 'locked'" class="glass-card rounded-2xl p-4 space-y-2 bg-(--color-success)/5">
                <div class="flex items-center gap-2">
                    <i class="ti ti-calendar-check text-(--color-success)" />
                    <h3 class="text-sm font-semibold">Period locked</h3>
                </div>
                <p class="text-xs text-(--text-muted)">
                    Locked at <span class="font-mono">{{ formatDate(period.lockedAt) }}</span>.
                    New JE postings within {{ formatDate(period.startDate) }} to {{ formatDate(period.endDate) }} are refused at AccountingService::postEntry.
                </p>
                <p v-if="period.retainedEarningsAccount" class="text-xs">
                    Retained Earnings:
                    <span class="font-mono font-semibold">{{ period.retainedEarningsAccount.code }}</span>
                    {{ period.retainedEarningsAccount.name }}
                </p>
                <p v-if="period.closingJournalEntry" class="text-xs">
                    Closing JE:
                    <span class="font-mono font-semibold">{{ period.closingJournalEntry.referenceNumber }}</span>
                    <span class="text-(--text-muted)"> / dated {{ formatDate(period.closingJournalEntry.entryDate) }}</span>
                </p>
            </section>

            <section v-if="period.status === 'open'" class="space-y-4">
                <header class="flex items-center justify-between">
                    <div>
                        <h3 class="text-sm font-semibold">Closing Preview</h3>
                        <p class="text-xxs text-(--text-muted)">Pick a Retained Earnings account to compute the rollover entry.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <select v-model="selectedRetainedEarningsId" class="form-control text-xs" :disabled="accountsLoading"
                            @change="loadPreview">
                            <option value="">Pick equity account</option>
                            <option v-for="a in equityAccounts" :key="a.id" :value="a.id">
                                {{ a.code }} / {{ a.name }}
                            </option>
                        </select>
                        <button v-if="canClose" type="button" class="btn btn-primary text-xs"
                            :disabled="!selectedRetainedEarningsId || closing" @click="onClose">
                            <i v-if="closing" class="ti ti-loader-2 animate-spin" />
                            <i v-else class="ti ti-lock" />
                            Close Period
                        </button>
                    </div>
                </header>

                <div v-if="!selectedRetainedEarningsId" class="glass-card rounded-2xl py-12 text-center">
                    <i class="ti ti-arrow-up text-4xl text-(--text-muted)" />
                    <p class="text-xs text-(--text-muted) mt-3">Select a Retained Earnings account above to see the closing plan.</p>
                </div>
                <div v-else-if="previewLoading" class="glass-card rounded-2xl py-12 text-center">
                    <span class="w-8 h-8 mx-auto rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin block" />
                    <p class="text-xs text-(--text-muted) mt-3">Computing closing entry...</p>
                </div>
                <div v-else-if="preview" class="space-y-4">
                    <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div class="glass-card rounded-2xl p-4 space-y-1">
                            <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Revenue Total</span>
                            <p class="text-lg font-bold text-(--color-success) font-mono">{{ revenueTotal.toFixed(2) }}</p>
                            <p class="text-xxs text-(--text-muted)">{{ preview.revenue.length }} accounts</p>
                        </div>
                        <div class="glass-card rounded-2xl p-4 space-y-1">
                            <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Expense Total</span>
                            <p class="text-lg font-bold text-(--color-danger) font-mono">{{ expenseTotal.toFixed(2) }}</p>
                            <p class="text-xxs text-(--text-muted)">{{ preview.expense.length }} accounts</p>
                        </div>
                        <div class="glass-card rounded-2xl p-4 space-y-1">
                            <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Net</span>
                            <p class="text-lg font-bold font-mono"
                                :class="preview.net >= 0 ? 'text-(--color-success)' : 'text-(--color-danger)'">
                                {{ preview.net >= 0 ? '+' : '' }}{{ preview.net.toFixed(2) }}
                            </p>
                            <p class="text-xxs text-(--text-muted)">{{ preview.net >= 0 ? 'Profit' : 'Loss' }}</p>
                        </div>
                        <div class="glass-card rounded-2xl p-4 space-y-1">
                            <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Retained Earnings</span>
                            <p class="text-lg font-bold font-mono">
                                <span v-if="preview.retainedCr > 0" class="text-(--color-success)">CR {{ preview.retainedCr.toFixed(2) }}</span>
                                <span v-else-if="preview.retainedDr > 0" class="text-(--color-danger)">DR {{ preview.retainedDr.toFixed(2) }}</span>
                                <span v-else class="text-(--text-muted)">-</span>
                            </p>
                            <p class="text-xxs text-(--text-muted)">Net to RE</p>
                        </div>
                    </section>

                    <section class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                        <div class="glass-card rounded-2xl overflow-hidden">
                            <header class="p-3 border-b border-(--border-color) bg-(--bg-muted)/30">
                                <h3 class="text-xs font-bold uppercase tracking-widest text-(--text-muted)">DR Revenue Accounts</h3>
                            </header>
                            <table class="min-w-full text-xs">
                                <tbody>
                                    <tr v-for="row in preview.revenue" :key="row.account.id" class="border-t border-(--border-color)">
                                        <td class="px-3 py-2 font-mono text-(--text-heading)">{{ row.account.code }}</td>
                                        <td class="px-3 py-2 text-(--text-body)">{{ row.account.name }}</td>
                                        <td class="px-3 py-2 text-right font-mono font-semibold text-(--color-success)">{{ row.amount.toFixed(2) }}</td>
                                    </tr>
                                    <tr v-if="preview.revenue.length === 0">
                                        <td colspan="3" class="p-6 text-center text-xxs text-(--text-muted)">No revenue movement in this period.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="glass-card rounded-2xl overflow-hidden">
                            <header class="p-3 border-b border-(--border-color) bg-(--bg-muted)/30">
                                <h3 class="text-xs font-bold uppercase tracking-widest text-(--text-muted)">CR Expense Accounts</h3>
                            </header>
                            <table class="min-w-full text-xs">
                                <tbody>
                                    <tr v-for="row in preview.expense" :key="row.account.id" class="border-t border-(--border-color)">
                                        <td class="px-3 py-2 font-mono text-(--text-heading)">{{ row.account.code }}</td>
                                        <td class="px-3 py-2 text-(--text-body)">{{ row.account.name }}</td>
                                        <td class="px-3 py-2 text-right font-mono font-semibold text-(--color-danger)">{{ row.amount.toFixed(2) }}</td>
                                    </tr>
                                    <tr v-if="preview.expense.length === 0">
                                        <td colspan="3" class="p-6 text-center text-xxs text-(--text-muted)">No expense movement in this period.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </section>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useFinance } from '~/composables/useFinance'
import { useToast } from '~/composables/useToast'
import { useAuthStore } from '~/stores/auth'
import type {
    Account,
    FiscalPeriod,
    FiscalPeriodStatus,
    FiscalPeriodClosingPreview,
} from '~/types/finance'

definePageMeta({ breadcrumb: 'Period' })

const route = useRoute()
const router = useRouter()
const finance = useFinance()
const toast = useToast()
const authStore = useAuthStore()

const periodId = computed(() => route.params.id as string)

const canRead   = computed(() => authStore.hasPermission('fms.fiscal_periods.read'))
const canClose  = computed(() => authStore.hasPermission('fms.fiscal_periods.close'))
const canReopen = computed(() => authStore.hasPermission('fms.fiscal_periods.reopen'))
const canDelete = computed(() => authStore.hasPermission('fms.fiscal_periods.write'))

const loading = ref(false)
const previewLoading = ref(false)
const closing = ref(false)
const reopening = ref(false)
const deleting = ref(false)

const period = ref<FiscalPeriod | null>(null)
const preview = ref<FiscalPeriodClosingPreview | null>(null)

const formatDate = (s: string | null) => {
    if (!s) return '-'
    const d = new Date(s)
    return isNaN(d.getTime()) ? s : d.toISOString().slice(0, 10)
}
const statusBadge = (s: FiscalPeriodStatus) => ({
    open:   'badge-soft-warning',
    locked: 'badge-soft-success',
}[s] || 'badge-soft-secondary')

const accountsList = ref<Account[]>([])
const accountsLoading = ref(false)
const equityAccounts = computed(() => accountsList.value.filter(a => a.type === 'equity'))

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

const selectedRetainedEarningsId = ref('')

const revenueTotal = computed(() => preview.value?.revenue.reduce((s, r) => s + r.amount, 0) ?? 0)
const expenseTotal = computed(() => preview.value?.expense.reduce((s, r) => s + r.amount, 0) ?? 0)

const loadPeriod = async () => {
    if (!canRead.value) return
    loading.value = true
    try {
        const res = await finance.fiscalPeriods.show(periodId.value)
        period.value = res.data
        if (period.value?.retainedEarningsAccountId) {
            selectedRetainedEarningsId.value = period.value.retainedEarningsAccountId
        }
        if (period.value?.status === 'open') {
            await ensureAccountsLoaded()
        }
    } catch (err: any) {
        toast.error('Failed to load period', err?.data?.message)
        period.value = null
    } finally {
        loading.value = false
    }
}

const loadPreview = async () => {
    if (!period.value || period.value.status !== 'open' || !selectedRetainedEarningsId.value) {
        preview.value = null
        return
    }
    previewLoading.value = true
    try {
        const res = await finance.fiscalPeriods.closingPreview(period.value.id, selectedRetainedEarningsId.value)
        preview.value = res.data
    } catch (err: any) {
        toast.error('Preview failed', err?.data?.message)
        preview.value = null
    } finally {
        previewLoading.value = false
    }
}

const onClose = async () => {
    if (!period.value || !selectedRetainedEarningsId.value) return
    if (!confirm(`Lock period ${period.value.periodNumber}? This posts the closing JE and refuses future postings in the range.`)) return
    closing.value = true
    try {
        await finance.fiscalPeriods.close(period.value.id, {
            retained_earnings_account_id: selectedRetainedEarningsId.value,
        })
        toast.success('Period closed and locked')
        preview.value = null
        await loadPeriod()
    } catch (err: any) {
        toast.error('Close failed', err?.data?.message)
    } finally {
        closing.value = false
    }
}

const onReopen = async () => {
    if (!period.value) return
    if (!confirm(`Reopen period ${period.value.periodNumber}? Closing JE stays in place. Reverse it manually if you want to undo the rollover.`)) return
    reopening.value = true
    try {
        await finance.fiscalPeriods.reopen(period.value.id)
        toast.success('Period reopened')
        await loadPeriod()
    } catch (err: any) {
        toast.error('Reopen failed', err?.data?.message)
    } finally {
        reopening.value = false
    }
}

const onDelete = async () => {
    if (!period.value) return
    if (!confirm('Delete this period? Only open periods can be deleted.')) return
    deleting.value = true
    try {
        await finance.fiscalPeriods.destroy(period.value.id)
        toast.success('Period deleted')
        router.push('/accounting/fiscal-periods')
    } catch (err: any) {
        toast.error('Delete failed', err?.data?.message)
    } finally {
        deleting.value = false
    }
}

onMounted(loadPeriod)
</script>
