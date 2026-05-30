<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Disposal</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        Retire assets — sale, scrap, or write-off. Posts the balancing journal and updates NBV.
                    </p>
                </div>
                <button v-if="canWrite" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-archive" />Dispose asset
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
                    <div class="relative md:col-span-6">
                        <i class="ti ti-cube absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
                        <select v-model="filters.assetId" class="form-control pl-9 appearance-none">
                            <option value="">All assets</option>
                            <option v-for="a in assetsAll" :key="a.id" :value="a.id">
                                {{ a.assetCode }} — {{ a.name }}
                            </option>
                        </select>
                    </div>
                    <div class="md:col-span-6 flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1">
                        <button v-for="t in (['', 'sale', 'scrap', 'writeoff'] as const)" :key="t || 'all'"
                            class="flex-1 px-3 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
                            :class="filters.type === t ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                            @click="filters.type = t">
                            {{ t || 'all' }}
                        </button>
                    </div>
                </div>
            </section>

            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading disposals...</span>
            </div>
            <div v-else-if="filtered.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-archive-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No disposals on file</h4>
                <p class="text-xs text-(--text-muted) mt-1">Retire an asset to record its final disposition.</p>
            </div>

            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                <th class="px-4 py-3 font-semibold">Asset</th>
                                <th class="px-4 py-3 font-semibold font-mono">Date</th>
                                <th class="px-4 py-3 font-semibold">Type</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Sale price</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Final NBV</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Gain/Loss</th>
                                <th class="px-4 py-3 font-semibold">Journal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-color)">
                            <tr v-for="log in paged" :key="log.id" class="hover:bg-(--bg-muted) transition-colors">
                                <td class="px-4 py-3">
                                    <div class="text-xs font-semibold text-(--text-heading)">{{ assetFor(log)?.assetCode || '—' }}</div>
                                    <div class="text-xxs text-(--text-muted) truncate max-w-[180px]">{{ assetFor(log)?.name || 'Asset archived' }}</div>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">{{ formatDate(log.disposalDate) }}</td>
                                <td class="px-4 py-3"><Badge :variant="typeVariant(log.disposalType)" :dot="true">{{ log.disposalType }}</Badge></td>
                                <td class="px-4 py-3 font-mono text-xs text-right">{{ formatMoney(log.salePrice) }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-right">{{ formatMoney(log.finalNbv) }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-right"
                                    :class="log.gainLossType === 'gain' ? 'text-(--color-success)' : log.gainLossType === 'loss' ? 'text-(--color-danger)' : 'text-(--text-muted)'">
                                    {{ log.gainLossType === 'none' ? '—' : (log.gainLoss >= 0 ? '+' : '') + formatMoney(log.gainLoss) }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge v-if="log.journalEntryId" variant="success" :dot="true">Posted</Badge>
                                    <Badge v-else variant="warning" :dot="true">Pending</Badge>
                                </td>
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

        <!-- Dispose modal -->
        <div v-if="showFormModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-lg p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                <header class="flex items-center justify-between mb-5">
                    <h3 class="text-base font-semibold text-(--text-heading)">Dispose asset</h3>
                    <button class="topbar-btn" @click="closeFormModal"><i class="ti ti-x" /></button>
                </header>
                <form class="form-grid" @submit.prevent="saveDisposal">
                    <div class="form-grid-full">
                        <label class="form-label">Asset <span class="text-(--color-danger)">*</span></label>
                        <select v-model="form.assetId" required class="form-control">
                            <option value="">Pick an active asset...</option>
                            <option v-for="a in activeAssets" :key="a.id" :value="a.id">
                                {{ a.assetCode }} — {{ a.name }} (NBV: {{ formatMoney(a.netBookValue) }})
                            </option>
                        </select>
                    </div>
                    <div class="form-grid-half">
                        <label class="form-label">Type <span class="text-(--color-danger)">*</span></label>
                        <select v-model="form.disposalType" required class="form-control">
                            <option value="sale">Sale</option>
                            <option value="scrap">Scrap</option>
                            <option value="writeoff">Write-off</option>
                        </select>
                    </div>
                    <div class="form-grid-half">
                        <label class="form-label">Disposal date</label>
                        <input v-model="form.disposalDate" type="date" class="form-control" />
                    </div>
                    <div v-if="form.disposalType === 'sale'" class="form-grid-full">
                        <label class="form-label">Sale price</label>
                        <input v-model.number="form.salePrice" type="number" step="0.01" min="0" class="form-control" />
                    </div>
                    <div class="form-grid-full">
                        <label class="form-label">Notes</label>
                        <textarea v-model="form.notes" rows="2" class="form-control" />
                    </div>

                    <div v-if="form.assetId && selectedAsset" class="form-grid-full glass-card rounded-xl p-3 space-y-2 bg-(--bg-muted)">
                        <div class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Gain/Loss preview</div>
                        <div class="grid grid-cols-2 gap-2 text-xs">
                            <div class="flex justify-between"><span class="text-(--text-muted)">Final NBV</span><span class="font-mono">{{ formatMoney(selectedAsset.netBookValue) }}</span></div>
                            <div class="flex justify-between"><span class="text-(--text-muted)">Sale price</span><span class="font-mono">{{ formatMoney(form.disposalType === 'sale' ? form.salePrice : 0) }}</span></div>
                            <div class="flex justify-between col-span-2 border-t border-(--border-color) pt-2">
                                <span class="text-(--text-muted)">Gain / Loss</span>
                                <span class="font-mono font-semibold"
                                    :class="gainLoss > 0 ? 'text-(--color-success)' : gainLoss < 0 ? 'text-(--color-danger)' : 'text-(--text-muted)'">
                                    {{ gainLoss > 0 ? '+' : '' }}{{ formatMoney(gainLoss) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="formError" class="form-grid-full text-xs text-(--color-danger)">{{ formError }}</div>

                    <div class="form-grid-full flex justify-end gap-2 mt-2">
                        <button type="button" class="btn btn-ghost text-xs" :disabled="saving" @click="closeFormModal">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="saving">
                            <i :class="['ti', saving ? 'ti-loader animate-spin' : 'ti-archive']" />
                            {{ saving ? 'Posting...' : 'Dispose + retire' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useAssets, type Asset, type DisposalLog, type DisposalType } from '~/composables/useAssets'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import { useCountUp } from '~/composables/useCountUp'
import { formatDate } from '~/composables/useDateFormat'
import Badge from '~/components/Badge.vue'
import Pagination from '~/components/Pagination.vue'

definePageMeta({ breadcrumb: 'Disposal' })

const assetsApi = useAssets()
const authStore = useAuthStore()
const toast = useToast()
const canWrite = computed(() => authStore.hasPermission('assets.disposal.write'))

const assetsAll = ref<Asset[]>([])
const logs = ref<DisposalLog[]>([])
const loading = ref(false)

const filters = reactive({ assetId: '', type: '' as '' | DisposalType })
const pagination = reactive({ page: 1, limit: 15 })

const showFormModal = ref(false)
const saving = ref(false)
const formError = ref<string | null>(null)
const form = reactive({
    assetId: '',
    disposalType: 'sale' as DisposalType,
    salePrice: 0,
    disposalDate: new Date().toISOString().slice(0, 10),
    notes: '',
})

const formatMoney = (n: number) => new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(n || 0)

const assetFor = (log: DisposalLog) => assetsAll.value.find(a => a.id === log.assetId)
const activeAssets = computed(() => assetsAll.value.filter(a => a.status === 'active'))
const selectedAsset = computed(() => assetsAll.value.find(a => a.id === form.assetId) || null)
const gainLoss = computed(() => {
    if (!selectedAsset.value) return 0
    if (form.disposalType !== 'sale') return -selectedAsset.value.netBookValue
    return Math.round(((form.salePrice || 0) - selectedAsset.value.netBookValue) * 100) / 100
})

const typeVariant = (t: DisposalType): 'success' | 'warning' | 'danger' => {
    if (t === 'sale') return 'success'
    if (t === 'scrap') return 'warning'
    return 'danger'
}

const filtered = computed(() => logs.value.filter(l => {
    if (filters.assetId && l.assetId !== filters.assetId) return false
    if (filters.type && l.disposalType !== filters.type) return false
    return true
}))
const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / pagination.limit)))
const paged = computed(() => {
    const start = (pagination.page - 1) * pagination.limit
    return filtered.value.slice(start, start + pagination.limit)
})
watch([() => filters.assetId, () => filters.type], () => { pagination.page = 1 })

const kpis = computed(() => {
    const k = { total: logs.value.length, sale: 0, scrap: 0, totalProceeds: 0 }
    for (const l of logs.value) {
        if (l.disposalType === 'sale') { k.sale++; k.totalProceeds += l.salePrice }
        else k.scrap++
    }
    return k
})
const totalCount = useCountUp(() => kpis.value.total)
const saleCount = useCountUp(() => kpis.value.sale)
const scrapCount = useCountUp(() => kpis.value.scrap)
const proceeds = useCountUp(() => Math.round(kpis.value.totalProceeds))
const kpiCards = computed(() => [
    { key: 'total',    label: 'Disposals',    value: totalCount.value.toLocaleString(), icon: 'ti-archive',     tone: 'primary', subtext: 'All-time records' },
    { key: 'sales',    label: 'Sales',        value: saleCount.value.toLocaleString(),  icon: 'ti-cash',        tone: 'success', subtext: 'Sold assets' },
    { key: 'scrap',    label: 'Scrap/write-off', value: scrapCount.value.toLocaleString(), icon: 'ti-trash',    tone: 'danger',  subtext: 'Decommissioned' },
    { key: 'proceeds', label: 'Sale proceeds',value: formatMoney(proceeds.value),       icon: 'ti-coin',        tone: 'info',    subtext: 'Cumulative' },
])

const loadAssets = async () => {
    try {
        const res = await assetsApi.getAssets({ limit: 500 })
        assetsAll.value = res.data
    } catch { assetsAll.value = [] }
}
const loadLogs = async () => {
    loading.value = true
    try {
        const res = await assetsApi.getDisposals({ limit: 500 })
        logs.value = res.data
    } catch { logs.value = [] } finally { loading.value = false }
}

const openCreateModal = () => {
    Object.assign(form, {
        assetId: '', disposalType: 'sale', salePrice: 0,
        disposalDate: new Date().toISOString().slice(0, 10), notes: '',
    })
    formError.value = null
    showFormModal.value = true
}
const closeFormModal = () => { if (!saving.value) showFormModal.value = false }

const saveDisposal = async () => {
    if (!form.assetId) return
    saving.value = true
    formError.value = null
    try {
        await assetsApi.createDisposal(form.assetId, {
            disposalType: form.disposalType,
            salePrice: form.disposalType === 'sale' ? form.salePrice : 0,
            disposalDate: form.disposalDate,
            notes: form.notes.trim() || null,
        })
        showFormModal.value = false
        await Promise.all([loadLogs(), loadAssets()])
        toast.success('Asset disposed.', 'Disposal journal posted and asset retired.')
    } catch (err: any) {
        formError.value = err?.data?.message || 'Failed to record disposal.'
    } finally {
        saving.value = false
    }
}

onMounted(() => { loadAssets(); loadLogs() })
</script>
