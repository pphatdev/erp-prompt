<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Budgets</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Set expected amounts per account for a date range. Variance against posted ledger entries is computed at read time. Drafts are editable; activating locks the budget.</p>
                </div>
                <button v-if="canWrite" type="button" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />New Budget
                </button>
            </header>

            <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Total</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-primary flex items-center justify-center"><i class="ti ti-target text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiCountAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">On page</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Active</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-success flex items-center justify-center"><i class="ti ti-check text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiActiveAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">Locked, tracking</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Drafts</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-warning flex items-center justify-center"><i class="ti ti-pencil text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiDraftAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">In progress</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Archived</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-secondary flex items-center justify-center"><i class="ti ti-archive text-sm" /></span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiArchivedAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">Past periods</p>
                </div>
            </section>

            <section class="flex items-center gap-2 flex-wrap max-sm:justify-center">
                <button type="button" class="chip" :class="{ active: statusFilter === '' }" @click="setStatusFilter('')">All</button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'draft' }" @click="setStatusFilter('draft')">
                    <i class="ti ti-pencil" /> Draft
                </button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'active' }" @click="setStatusFilter('active')">
                    <i class="ti ti-check" /> Active
                </button>
                <button type="button" class="chip" :class="{ active: statusFilter === 'archived' }" @click="setStatusFilter('archived')">
                    <i class="ti ti-archive" /> Archived
                </button>
            </section>

            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading budgets...</span>
            </div>
            <div v-else-if="budgets.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-target text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No budgets yet</h4>
                <p class="text-xs text-(--text-muted) mt-1">Create a draft, add line items, then activate.</p>
            </div>

            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-(--bg-muted)/40 text-(--text-muted)">
                            <tr>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Budget #</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Name</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Period</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Status</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3">Lines</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3">Expected Total</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3 w-12">Open</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="b in budgets" :key="b.id"
                                class="border-t border-(--border-color) hover:bg-(--bg-muted)/40 cursor-pointer"
                                @click="goToBudget(b.id)">
                                <td class="px-3 py-3 font-mono font-semibold text-(--text-heading)">{{ b.budgetNumber }}</td>
                                <td class="px-3 py-3">
                                    <p class="text-(--text-heading) font-semibold truncate max-w-xs">{{ b.name }}</p>
                                </td>
                                <td class="px-3 py-3 font-mono">
                                    <span>{{ formatDate(b.startDate) }}</span>
                                    <span class="text-(--text-muted) mx-1">to</span>
                                    <span>{{ formatDate(b.endDate) }}</span>
                                </td>
                                <td class="px-3 py-3">
                                    <span class="text-xxs px-1.5 py-0.5 rounded font-mono" :class="statusBadge(b.status)">{{ b.status }}</span>
                                </td>
                                <td class="px-3 py-3 text-right font-mono">{{ b.linesCount ?? 0 }}</td>
                                <td class="px-3 py-3 text-right font-mono font-semibold text-(--text-heading)">{{ b.expectedTotal.toFixed(2) }}</td>
                                <td class="px-3 py-3 text-right">
                                    <i class="ti ti-chevron-right text-(--text-muted)" />
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :page="pagination.page" :limit="pagination.limit"
                    :total="pagination.total" :total-pages="pagination.totalPages"
                    @update:page="(p) => { pagination.page = p; load() }"
                    @update:limit="(l) => { pagination.limit = l; pagination.page = 1; load() }" />
            </section>
        </div>

        <div v-if="showFormModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-xl bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">New Budget</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="post">
                    <div class="p-5 space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Budget # *</label>
                                <input v-model="form.budget_number" type="text" required maxlength="64"
                                    placeholder="e.g. BUD-2026-01" class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Name *</label>
                                <input v-model="form.name" type="text" required maxlength="200"
                                    placeholder="Q1 Operating Budget" class="form-control text-xs" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Start *</label>
                                <input v-model="form.start_date" type="date" required class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">End *</label>
                                <input v-model="form.end_date" type="date" required class="form-control text-xs font-mono" />
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Notes</label>
                            <textarea v-model="form.notes" rows="2" maxlength="2000" class="form-control text-xs resize-none" />
                        </div>
                        <p class="text-xxs text-(--text-muted)">Add line items after creating. Variance is computed at read time against posted ledger entries.</p>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showFormModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="!canSubmit || posting">
                            <i v-if="posting" class="ti ti-loader-2 animate-spin" />
                            Create Draft
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
import type { Budget, BudgetStatus, CreateBudgetPayload } from '~/types/finance'

definePageMeta({ breadcrumb: 'Budgets' })

const finance = useFinance()
const toast = useToast()
const router = useRouter()
const authStore = useAuthStore()

const canRead  = computed(() => authStore.hasPermission('fms.budgets.read'))
const canWrite = computed(() => authStore.hasPermission('fms.budgets.write'))

const loading = ref(false)
const posting = ref(false)

const budgets = ref<Budget[]>([])
const pagination = reactive({ page: 1, limit: 25, total: 0, totalPages: 1 })
const statusFilter = ref<'' | BudgetStatus>('')

const today = new Date().toISOString().slice(0, 10)
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

const activeCount   = computed(() => budgets.value.filter(b => b.status === 'active').length)
const draftCount    = computed(() => budgets.value.filter(b => b.status === 'draft').length)
const archivedCount = computed(() => budgets.value.filter(b => b.status === 'archived').length)

const kpiCountAnim    = useCountUp(() => budgets.value.length)
const kpiActiveAnim   = useCountUp(() => activeCount.value)
const kpiDraftAnim    = useCountUp(() => draftCount.value)
const kpiArchivedAnim = useCountUp(() => archivedCount.value)

const load = async () => {
    if (!canRead.value) return
    loading.value = true
    try {
        const res = await finance.budgets.list({
            page: pagination.page,
            limit: pagination.limit,
            status: statusFilter.value || undefined,
        })
        budgets.value = res.data
        const p = (res as any).pagination
        if (p) {
            pagination.total = p.total ?? 0
            pagination.totalPages = p.totalPages ?? 1
            pagination.page = p.page ?? 1
            pagination.limit = p.limit ?? pagination.limit
        }
    } catch (err: any) {
        toast.error('Failed to load budgets', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const setStatusFilter = (s: '' | BudgetStatus) => {
    if (statusFilter.value === s) return
    statusFilter.value = s
    pagination.page = 1
    load()
}

const goToBudget = (id: string) => {
    router.push(`/accounting/budgets/${id}`)
}

const showFormModal = ref(false)

const blankForm = (): CreateBudgetPayload => ({
    budget_number: '',
    name: '',
    start_date: today,
    end_date: today,
    notes: null,
})

const form = reactive<CreateBudgetPayload>(blankForm())

const openCreateModal = () => {
    Object.assign(form, blankForm())
    showFormModal.value = true
}

const canSubmit = computed(() => {
    if (!form.budget_number.trim()) return false
    if (!form.name.trim()) return false
    if (!form.start_date || !form.end_date) return false
    if (form.start_date > form.end_date) return false
    return true
})

const post = async () => {
    if (!canSubmit.value) return
    posting.value = true
    try {
        const payload: CreateBudgetPayload = {
            ...form,
            budget_number: form.budget_number.trim(),
            name: form.name.trim(),
            notes: form.notes?.trim() || null,
        }
        const res = await finance.budgets.create(payload)
        toast.success('Draft created', res.data.budgetNumber)
        showFormModal.value = false
        await load()
        router.push(`/accounting/budgets/${res.data.id}`)
    } catch (err: any) {
        toast.error('Create failed', err?.data?.message)
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
