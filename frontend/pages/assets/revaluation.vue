<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Revaluation</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        Log professional appraisals — surplus posts to the revaluation reserve, loss hits P&amp;L.
                    </p>
                </div>
                <button v-if="canWrite" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-stars" />Log appraisal
                </button>
            </header>

            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <article v-for="card in kpiCards" :key="card.key" class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">{{ card.label }}</span>
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center" :class="`badge-soft-${card.tone}`">
                            <i :class="['ti', card.icon, 'text-sm']" />
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ card.value }}</p>
                    <p class="text-xxs text-(--text-muted)">{{ card.subtext }}</p>
                </article>
            </section>

            <section class="glass-card rounded-xl p-4">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="relative md:col-span-8">
                        <i class="ti ti-cube absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
                        <select v-model="filters.assetId" class="form-control pl-9 appearance-none">
                            <option value="">All assets</option>
                            <option v-for="a in assets" :key="a.id" :value="a.id">
                                {{ a.assetCode }} — {{ a.name }}
                            </option>
                        </select>
                    </div>
                    <div class="md:col-span-4 flex justify-end">
                        <button class="btn btn-ghost text-xs" :disabled="loading" @click="loadLogs">
                            <i :class="['ti', loading ? 'ti-loader animate-spin' : 'ti-refresh']" /> Refresh
                        </button>
                    </div>
                </div>
            </section>

            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading revaluation history...</span>
            </div>
            <div v-else-if="filtered.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-stars text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No appraisals on file</h4>
                <p class="text-xs text-(--text-muted) mt-1">Log the first professional appraisal to start the revaluation history.</p>
            </div>

            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                <th class="px-4 py-3 font-semibold">Asset</th>
                                <th class="px-4 py-3 font-semibold font-mono">Date</th>
                                <th class="px-4 py-3 font-semibold">Appraiser</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Prev value</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Appraisal</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Δ Adjustment</th>
                                <th class="px-4 py-3 font-semibold">Type</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-color)">
                            <tr v-for="log in paged" :key="log.id" class="hover:bg-(--bg-muted) transition-colors">
                                <td class="px-4 py-3">
                                    <div class="text-xs font-semibold text-(--text-heading)">{{ assetFor(log)?.assetCode || '—' }}</div>
                                    <div class="text-xxs text-(--text-muted) truncate max-w-[180px]">{{ assetFor(log)?.name || '' }}</div>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">{{ formatDate(log.appraisalDate) }}</td>
                                <td class="px-4 py-3 text-xs">{{ log.appraiser || '—' }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-right">{{ formatMoney(log.previousValue) }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-right">{{ formatMoney(log.appraisalValue) }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-right"
                                    :class="log.adjustmentAmount >= 0 ? 'text-(--color-success)' : 'text-(--color-danger)'">
                                    {{ log.adjustmentAmount >= 0 ? '+' : '' }}{{ formatMoney(log.adjustmentAmount) }}
                                </td>
                                <td class="px-4 py-3"><Badge :variant="log.adjustmentType === 'surplus' ? 'success' : 'danger'" :dot="true">{{ log.adjustmentType }}</Badge></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Pagination :page="pagination.page" :limit="pagination.limit" :total="filtered.length"
                    :total-pages="totalPages"
                    @update:page="(p) => { pagination.page = p }"
                    @update:limit="(l) => { pagination.limit = l; pagination.page = 1 }" />
            </section>
        </div>

        <!-- Create modal -->
        <div v-if="showFormModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-lg p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                <header class="flex items-center justify-between mb-5">
                    <h3 class="text-base font-semibold text-(--text-heading)">Log appraisal</h3>
                    <button class="topbar-btn" @click="closeFormModal"><i class="ti ti-x" /></button>
                </header>
                <form class="form-grid" @submit.prevent="saveAppraisal">
                    <div class="form-grid-full">
                        <label class="form-label">Asset <span class="text-(--color-danger)">*</span></label>
                        <select v-model="form.assetId" required class="form-control">
                            <option value="">Pick an asset...</option>
                            <option v-for="a in activeAssets" :key="a.id" :value="a.id">
                                {{ a.assetCode }} — {{ a.name }} (NBV: {{ formatMoney(a.netBookValue) }})
                            </option>
                        </select>
                    </div>
                    <div class="form-grid-half">
                        <label class="form-label">Appraisal date</label>
                        <input v-model="form.appraisalDate" type="date" class="form-control" />
                    </div>
                    <div class="form-grid-half">
                        <label class="form-label">New value <span class="text-(--color-danger)">*</span></label>
                        <input v-model.number="form.appraisalValue" type="number" step="0.01" min="0" required class="form-control" />
                    </div>
                    <div class="form-grid-full">
                        <label class="form-label">Appraiser</label>
                        <input v-model="form.appraiser" type="text" class="form-control" placeholder="Acme Valuation Co." />
                    </div>
                    <div class="form-grid-full">
                        <label class="form-label">Notes</label>
                        <textarea v-model="form.notes" rows="2" class="form-control" />
                    </div>

                    <div v-if="form.assetId && selectedAsset" class="form-grid-full glass-card rounded-xl p-3 space-y-2 bg-(--bg-muted)">
                        <div class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Adjustment preview</div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="flex justify-between"><span class="text-(--text-muted)">Current NBV</span><span class="font-mono">{{ formatMoney(selectedAsset.netBookValue) }}</span></div>
                            <div class="flex justify-between"><span class="text-(--text-muted)">New value</span><span class="font-mono">{{ formatMoney(form.appraisalValue || 0) }}</span></div>
                            <div class="flex justify-between col-span-2 border-t border-(--border-color) pt-2">
                                <span class="text-(--text-muted)">Δ Adjustment</span>
                                <span class="font-mono font-semibold"
                                    :class="adjustment >= 0 ? 'text-(--color-success)' : 'text-(--color-danger)'">
                                    {{ adjustment >= 0 ? 'Surplus +' : 'Loss ' }}{{ formatMoney(adjustment) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="formError" class="form-grid-full text-xs text-(--color-danger)">{{ formError }}</div>

                    <div class="form-grid-full flex justify-end gap-2 mt-2">
                        <button type="button" class="btn btn-ghost text-xs" :disabled="saving" @click="closeFormModal">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="saving">
                            <i :class="['ti', saving ? 'ti-loader animate-spin' : 'ti-stars']" />
                            {{ saving ? 'Posting...' : 'Post appraisal' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useAssets, type Asset, type RevaluationLog } from '~/composables/useAssets'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import { useCountUp } from '~/composables/useCountUp'
import { formatDate } from '~/composables/useDateFormat'
import Badge from '~/components/Badge.vue'
import Pagination from '~/components/Pagination.vue'

definePageMeta({ breadcrumb: 'Revaluation' })

const assetsApi = useAssets()
const authStore = useAuthStore()
const toast = useToast()
const canWrite = computed(() => authStore.hasPermission('assets.revaluation.write'))

const assets = ref<Asset[]>([])
const logs = ref<RevaluationLog[]>([])
const loading = ref(false)

const filters = reactive({ assetId: '' })
const pagination = reactive({ page: 1, limit: 15 })

const showFormModal = ref(false)
const saving = ref(false)
const formError = ref<string | null>(null)
const form = reactive({
    assetId: '',
    appraisalDate: new Date().toISOString().slice(0, 10),
    appraisalValue: 0,
    appraiser: '',
    notes: '',
})

const formatMoney = (n: number) => new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(n || 0)

const assetFor = (log: RevaluationLog) => assets.value.find(a => a.id === log.assetId)
const activeAssets = computed(() => assets.value.filter(a => a.status === 'active'))
const selectedAsset = computed(() => assets.value.find(a => a.id === form.assetId) || null)
const adjustment = computed(() => Math.round(((form.appraisalValue || 0) - (selectedAsset.value?.netBookValue || 0)) * 100) / 100)

const filtered = computed(() => {
    if (!filters.assetId) return logs.value
    return logs.value.filter(l => l.assetId === filters.assetId)
})
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / pagination.limit)))
const paged = computed(() => {
    const start = (pagination.page - 1) * pagination.limit
    return filtered.value.slice(start, start + pagination.limit)
})
watch(() => filters.assetId, () => { pagination.page = 1 })

const kpis = computed(() => {
    const k = { logs: logs.value.length, surplus: 0, loss: 0, netDelta: 0 }
    for (const l of logs.value) {
        if (l.adjustmentType === 'surplus') k.surplus++
        else k.loss++
        k.netDelta += l.adjustmentAmount
    }
    return k
})
const logsCount = useCountUp(() => kpis.value.logs)
const surplusCount = useCountUp(() => kpis.value.surplus)
const lossCount = useCountUp(() => kpis.value.loss)
const netDelta = useCountUp(() => Math.round(kpis.value.netDelta))

const kpiCards = computed(() => [
    { key: 'logs',    label: 'Appraisals',   value: logsCount.value.toLocaleString(),    icon: 'ti-history',     tone: 'primary', subtext: 'All-time entries' },
    { key: 'surplus', label: 'Surplus runs', value: surplusCount.value.toLocaleString(), icon: 'ti-arrow-up',    tone: 'success', subtext: 'Reserve credits' },
    { key: 'loss',    label: 'Loss runs',    value: lossCount.value.toLocaleString(),    icon: 'ti-arrow-down',  tone: 'danger',  subtext: 'P&L hits' },
    { key: 'net',     label: 'Net Δ',        value: formatMoney(netDelta.value),         icon: 'ti-coin',        tone: 'info',    subtext: 'Surplus minus loss' },
])

const loadAssets = async () => {
    try {
        const res = await assetsApi.getAssets({ limit: 500 })
        assets.value = res.data
    } catch { assets.value = [] }
}
const loadLogs = async () => {
    loading.value = true
    try {
        const res = await assetsApi.getRevaluations({ limit: 500 })
        logs.value = res.data
    } catch { logs.value = [] } finally { loading.value = false }
}

const openCreateModal = () => {
    Object.assign(form, {
        assetId: '', appraisalDate: new Date().toISOString().slice(0, 10),
        appraisalValue: 0, appraiser: '', notes: '',
    })
    formError.value = null
    showFormModal.value = true
}
const closeFormModal = () => { if (!saving.value) showFormModal.value = false }

const saveAppraisal = async () => {
    if (!form.assetId) return
    saving.value = true
    formError.value = null
    try {
        await assetsApi.createRevaluation(form.assetId, {
            appraisalValue: form.appraisalValue,
            appraisalDate: form.appraisalDate,
            appraiser: form.appraiser.trim() || null,
            notes: form.notes.trim() || null,
        })
        showFormModal.value = false
        await Promise.all([loadLogs(), loadAssets()])
        toast.success('Appraisal logged.', 'Revaluation journal posted to GL.')
    } catch (err: any) {
        formError.value = err?.data?.message || 'Failed to log appraisal.'
    } finally {
        saving.value = false
    }
}

onMounted(() => { loadAssets(); loadLogs() })
</script>
