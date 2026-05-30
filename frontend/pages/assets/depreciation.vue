<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Depreciation</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        Schedule monthly runs, preview next-period calculations, and view the audit trail of GL postings.
                    </p>
                </div>
                <button v-if="canWrite" class="btn btn-primary text-xs" @click="openRunModal">
                    <i class="ti ti-calculator" />Run depreciation
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
                            <i :class="['ti', loading ? 'ti-loader animate-spin' : 'ti-refresh']" />
                            Refresh
                        </button>
                    </div>
                </div>
            </section>

            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading depreciation history...</span>
            </div>
            <div v-else-if="filteredLogs.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-receipt-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No depreciation runs yet</h4>
                <p class="text-xs text-(--text-muted) mt-1">Run a monthly calculation to start the schedule.</p>
            </div>

            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                <th class="px-4 py-3 font-semibold">Asset</th>
                                <th class="px-4 py-3 font-semibold font-mono">Period</th>
                                <th class="px-4 py-3 font-semibold">Method</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Amount</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Accumulated</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">NBV</th>
                                <th class="px-4 py-3 font-semibold">Journal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-color)">
                            <tr v-for="log in paged" :key="log.id" class="hover:bg-(--bg-muted) transition-colors">
                                <td class="px-4 py-3">
                                    <div class="text-xs font-semibold text-(--text-heading)">{{ assetFor(log)?.assetCode || '—' }}</div>
                                    <div class="text-xxs text-(--text-muted) truncate max-w-[180px]">{{ assetFor(log)?.name || '' }}</div>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">{{ formatDate(log.periodDate) }}</td>
                                <td class="px-4 py-3 text-xs capitalize">{{ (log.method || '').replace(/_/g, ' ') }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-right">{{ formatMoney(log.depreciationAmount) }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-right">{{ formatMoney(log.accumulatedDepreciation) }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-right">{{ formatMoney(log.bookValue) }}</td>
                                <td class="px-4 py-3">
                                    <Badge v-if="log.journalEntryId" variant="success" :dot="true">Posted</Badge>
                                    <Badge v-else variant="warning" :dot="true">Pending</Badge>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Pagination :page="pagination.page" :limit="pagination.limit" :total="filteredLogs.length"
                    :total-pages="totalPages"
                    @update:page="(p) => { pagination.page = p }"
                    @update:limit="(l) => { pagination.limit = l; pagination.page = 1 }" />
            </section>
        </div>

        <!-- Run modal -->
        <div v-if="showRunModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-lg p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                <header class="flex items-center justify-between mb-5">
                    <h3 class="text-base font-semibold text-(--text-heading)">Run monthly depreciation</h3>
                    <button class="topbar-btn" @click="closeRunModal"><i class="ti ti-x" /></button>
                </header>
                <form class="form-grid" @submit.prevent="runDepreciation">
                    <div class="form-grid-full">
                        <label class="form-label">Asset <span class="text-(--color-danger)">*</span></label>
                        <select v-model="runForm.assetId" required class="form-control">
                            <option value="">Pick an asset...</option>
                            <option v-for="a in activeAssets" :key="a.id" :value="a.id">
                                {{ a.assetCode }} — {{ a.name }}
                            </option>
                        </select>
                    </div>
                    <div class="form-grid-full">
                        <label class="form-label">Posting date</label>
                        <input v-model="runForm.periodDate" type="date" class="form-control" />
                    </div>

                    <div v-if="preview" class="form-grid-full glass-card rounded-xl p-3 space-y-2 bg-(--bg-muted)">
                        <div class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Next-period preview</div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="flex justify-between"><span class="text-(--text-muted)">Method</span><span class="font-mono capitalize">{{ preview.method.replace(/_/g, ' ') }}</span></div>
                            <div class="flex justify-between"><span class="text-(--text-muted)">Amount</span><span class="font-mono font-semibold">{{ formatMoney(preview.amount) }}</span></div>
                            <div class="flex justify-between"><span class="text-(--text-muted)">Accumulated after</span><span class="font-mono">{{ formatMoney(preview.accumulatedAfter) }}</span></div>
                            <div class="flex justify-between"><span class="text-(--text-muted)">NBV after</span><span class="font-mono">{{ formatMoney(preview.netBookValueAfter) }}</span></div>
                        </div>
                    </div>

                    <div v-if="runError" class="form-grid-full text-xs text-(--color-danger)">{{ runError }}</div>

                    <div class="form-grid-full flex justify-end gap-2 mt-2">
                        <button type="button" class="btn btn-ghost text-xs" :disabled="running" @click="closeRunModal">Cancel</button>
                        <button type="button" class="btn btn-ghost text-xs" :disabled="running || !runForm.assetId" @click="loadPreview">
                            <i class="ti ti-eye" /> Preview
                        </button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="running || !runForm.assetId">
                            <i :class="['ti', running ? 'ti-loader animate-spin' : 'ti-calculator']" />
                            {{ running ? 'Posting...' : 'Run + post' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useAssets, type Asset, type DepreciationLog, type DepreciationPreview } from '~/composables/useAssets'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import { useCountUp } from '~/composables/useCountUp'
import { formatDate } from '~/composables/useDateFormat'
import Badge from '~/components/Badge.vue'
import Pagination from '~/components/Pagination.vue'

definePageMeta({ breadcrumb: 'Depreciation' })

const assetsApi = useAssets()
const authStore = useAuthStore()
const toast = useToast()
const canWrite = computed(() => authStore.hasPermission('assets.depreciation.write'))

const assets = ref<Asset[]>([])
const logs = ref<DepreciationLog[]>([])
const loading = ref(false)

const filters = reactive({ assetId: '' })
const pagination = reactive({ page: 1, limit: 15 })

const showRunModal = ref(false)
const running = ref(false)
const runError = ref<string | null>(null)
const preview = ref<DepreciationPreview | null>(null)
const runForm = reactive({ assetId: '', periodDate: '' })

const formatMoney = (n: number) => new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(n || 0)

const assetFor = (log: DepreciationLog) => assets.value.find(a => a.id === log.assetId)
const activeAssets = computed(() => assets.value.filter(a => a.status === 'active'))

const filteredLogs = computed(() => {
    if (!filters.assetId) return logs.value
    return logs.value.filter(l => l.assetId === filters.assetId)
})
const totalPages = computed(() => Math.max(1, Math.ceil(filteredLogs.value.length / pagination.limit)))
const paged = computed(() => {
    const start = (pagination.page - 1) * pagination.limit
    return filteredLogs.value.slice(start, start + pagination.limit)
})

watch(() => filters.assetId, () => { pagination.page = 1 })

const kpis = computed(() => {
    const k = { logs: logs.value.length, monthAmount: 0, posted: 0, pending: 0 }
    const monthKey = new Date().toISOString().slice(0, 7)
    for (const l of logs.value) {
        if (l.journalEntryId) k.posted++; else k.pending++
        if ((l.periodDate || '').startsWith(monthKey)) k.monthAmount += l.depreciationAmount
    }
    return k
})
const logsCount = useCountUp(() => kpis.value.logs)
const postedCount = useCountUp(() => kpis.value.posted)
const pendingCount = useCountUp(() => kpis.value.pending)
const monthAmount = useCountUp(() => Math.round(kpis.value.monthAmount))

const kpiCards = computed(() => [
    { key: 'logs',    label: 'Total runs',     value: logsCount.value.toLocaleString(), icon: 'ti-history',     tone: 'primary', subtext: 'All-time entries' },
    { key: 'posted',  label: 'Posted to GL',   value: postedCount.value.toLocaleString(),icon: 'ti-circle-check',tone: 'success', subtext: 'Synced journals' },
    { key: 'pending', label: 'Pending sync',   value: pendingCount.value.toLocaleString(),icon: 'ti-clock-pause',tone: 'warning', subtext: 'Awaiting journal' },
    { key: 'month',   label: 'This month',     value: formatMoney(monthAmount.value),    icon: 'ti-coin',        tone: 'info',    subtext: 'Depreciation cost' },
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
        const res = await assetsApi.getDepreciationLogs({ limit: 500 })
        logs.value = res.data
    } catch { logs.value = [] } finally { loading.value = false }
}

const openRunModal = () => {
    runForm.assetId = ''
    runForm.periodDate = ''
    preview.value = null
    runError.value = null
    showRunModal.value = true
}
const closeRunModal = () => {
    if (running.value) return
    showRunModal.value = false
}

const loadPreview = async () => {
    if (!runForm.assetId) return
    runError.value = null
    try {
        const res = await assetsApi.previewDepreciation(runForm.assetId)
        preview.value = res.data
    } catch (err: any) {
        runError.value = err?.data?.message || 'Preview failed.'
    }
}

const runDepreciation = async () => {
    if (!runForm.assetId) return
    running.value = true
    runError.value = null
    try {
        await assetsApi.runDepreciation(runForm.assetId, runForm.periodDate || undefined)
        showRunModal.value = false
        await Promise.all([loadLogs(), loadAssets()])
        toast.success('Depreciation posted.', 'GL journal entry created and asset NBV updated.')
    } catch (err: any) {
        runError.value = err?.data?.message || 'Failed to post depreciation. Check the GL period is open.'
    } finally {
        running.value = false
    }
}

watch(() => runForm.assetId, () => { preview.value = null })

onMounted(() => {
    loadAssets()
    loadLogs()
})
</script>
