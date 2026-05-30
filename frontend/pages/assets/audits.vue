<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Audit Campaigns</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        Periodic stock-take cycles — track scans, reconciliation, and missing assets.
                    </p>
                </div>
                <button v-if="canWrite" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />New campaign
                </button>
            </header>

            <!-- Active campaign reconciliation -->
            <section v-if="activeCampaign" class="glass-card rounded-2xl p-5 space-y-4">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <Badge variant="success" :dot="true">Active</Badge>
                        <h3 class="text-base font-semibold text-(--text-heading) mt-2">{{ activeCampaign.name }}</h3>
                        <p class="text-xxs text-(--text-muted)">
                            {{ formatDate(activeCampaign.startsAt) }} → {{ formatDate(activeCampaign.endsAt) }} · {{ activeCampaign.frequency }}
                        </p>
                        <p v-if="activeCampaign.startedAt" class="text-xxs text-(--text-muted) font-mono">
                            Started {{ formatDateTime(activeCampaign.startedAt) }}
                        </p>
                    </div>
                    <button v-if="canWrite" class="btn btn-primary text-xs" @click="completeCampaign(activeCampaign)">
                        <i class="ti ti-flag-check" />Complete cycle
                    </button>
                </div>

                <div v-if="activeReconciliation" class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3">
                    <article v-for="tile in reconciliationTiles" :key="tile.key"
                        class="glass-card rounded-xl p-3 space-y-1 bg-(--bg-muted)">
                        <div class="flex items-center justify-between">
                            <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">{{ tile.label }}</span>
                            <span class="w-6 h-6 rounded-md flex items-center justify-center" :class="`badge-soft-${tile.tone}`">
                                <i :class="['ti', tile.icon, 'text-xs']" />
                            </span>
                        </div>
                        <p class="text-lg font-bold text-(--text-heading) font-mono">{{ tile.value }}</p>
                    </article>
                </div>

                <div v-if="activeReconciliation" class="space-y-1">
                    <div class="flex justify-between text-xxs text-(--text-muted) font-bold uppercase tracking-widest">
                        <span>Progress</span>
                        <span>{{ activeReconciliation.progress.toFixed(1) }}%</span>
                    </div>
                    <div class="h-2 rounded-full bg-(--bg-muted) overflow-hidden">
                        <div class="h-full bg-(--color-primary) transition-all"
                            :style="{ width: `${Math.min(100, activeReconciliation.progress)}%` }" />
                    </div>
                </div>
            </section>

            <!-- All campaigns -->
            <section class="glass-card rounded-xl p-4">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="relative md:col-span-6">
                        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                        <input v-model="filters.search" type="search" placeholder="Search campaign name..." class="form-control pl-9" />
                    </div>
                    <div class="md:col-span-6 flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1">
                        <button v-for="s in (['', 'draft', 'active', 'completed', 'cancelled'] as const)" :key="s || 'all'"
                            class="flex-1 px-3 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
                            :class="filters.status === s ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                            @click="filters.status = s">
                            {{ s || 'all' }}
                        </button>
                    </div>
                </div>
            </section>

            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading campaigns...</span>
            </div>
            <div v-else-if="filteredCampaigns.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-calendar-stats text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No campaigns yet</h4>
                <p class="text-xs text-(--text-muted) mt-1">Start a bi-annual cycle to begin reconciling physical assets.</p>
            </div>

            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                <th class="px-4 py-3 font-semibold">Campaign</th>
                                <th class="px-4 py-3 font-semibold">Frequency</th>
                                <th class="px-4 py-3 font-semibold font-mono">Window</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Expected</th>
                                <th class="px-4 py-3 font-semibold text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-color)">
                            <tr v-for="c in paged" :key="c.id" class="hover:bg-(--bg-muted) transition-colors">
                                <td class="px-4 py-3">
                                    <div class="text-xs font-semibold text-(--text-heading)">{{ c.name }}</div>
                                    <div class="text-xxs text-(--text-muted) truncate max-w-[240px]">{{ c.description || '—' }}</div>
                                </td>
                                <td class="px-4 py-3 text-xs capitalize">{{ c.frequency }}</td>
                                <td class="px-4 py-3 font-mono text-xxs text-(--text-muted)">
                                    {{ formatDate(c.startsAt) }} → {{ formatDate(c.endsAt) }}
                                </td>
                                <td class="px-4 py-3"><Badge :variant="statusVariant(c.status)" :dot="true">{{ c.status }}</Badge></td>
                                <td class="px-4 py-3 font-mono text-xs text-right">{{ c.expectedAssetCount?.toLocaleString() ?? '—' }}</td>
                                <td class="px-4 py-3 text-center">
                                    <button class="action-trigger"
                                        :class="{ 'action-trigger-open': actionMenu.open && actionMenu.campaign?.id === c.id }"
                                        title="Actions" @click.stop="openActionMenu(c, $event)">
                                        <i class="ti ti-dots-vertical" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <Pagination :page="pagination.page" :limit="pagination.limit" :total="filteredCampaigns.length"
                    :total-pages="totalPages"
                    @update:page="(p) => { pagination.page = p }"
                    @update:limit="(l) => { pagination.limit = l; pagination.page = 1 }" />
            </section>
        </div>

        <!-- Create modal -->
        <div v-if="showFormModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-lg p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                <header class="flex items-center justify-between mb-5">
                    <h3 class="text-base font-semibold text-(--text-heading)">New audit campaign</h3>
                    <button class="topbar-btn" @click="closeFormModal"><i class="ti ti-x" /></button>
                </header>
                <form class="form-grid" @submit.prevent="saveCampaign">
                    <div class="form-grid-full">
                        <label class="form-label">Name <span class="text-(--color-danger)">*</span></label>
                        <input v-model="form.name" type="text" required class="form-control" placeholder="2026 H1 Stock-take" />
                    </div>
                    <div class="form-grid-full">
                        <label class="form-label">Description</label>
                        <textarea v-model="form.description" rows="2" class="form-control" />
                    </div>
                    <div class="form-grid-half">
                        <label class="form-label">Frequency</label>
                        <select v-model="form.frequency" class="form-control">
                            <option value="annual">Annual</option>
                            <option value="biannual">Bi-annual</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="adhoc">Ad-hoc</option>
                        </select>
                    </div>
                    <div class="form-grid-half" />
                    <div class="form-grid-half">
                        <label class="form-label">Starts</label>
                        <input v-model="form.startsAt" type="date" class="form-control" />
                    </div>
                    <div class="form-grid-half">
                        <label class="form-label">Ends</label>
                        <input v-model="form.endsAt" type="date" class="form-control" />
                    </div>

                    <div v-if="formError" class="form-grid-full text-xs text-(--color-danger)">{{ formError }}</div>

                    <div class="form-grid-full flex justify-end gap-2 mt-2">
                        <button type="button" class="btn btn-ghost text-xs" :disabled="saving" @click="closeFormModal">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="saving">
                            <i :class="['ti', saving ? 'ti-loader animate-spin' : 'ti-device-floppy']" />
                            {{ saving ? 'Saving...' : 'Save campaign' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Action dropdown -->
        <div v-if="actionMenu.open && actionMenu.campaign"
            class="fixed z-50 glass-card rounded-lg shadow-(--shadow-lg) bg-(--bg-card) border border-(--border-color) py-1 min-w-[200px]"
            :style="{ top: actionMenu.y + 'px', left: actionMenu.x + 'px' }" @click.stop>
            <button v-if="actionMenu.campaign.status === 'draft' && canWrite" class="action-item" @click="actionStart">
                <i class="ti ti-player-play" /> Start campaign
            </button>
            <button v-if="actionMenu.campaign.status === 'active' && canWrite" class="action-item" @click="actionComplete">
                <i class="ti ti-flag-check" /> Complete cycle
            </button>
            <hr v-if="canDelete" class="my-1 border-(--border-color)" />
            <button v-if="canDelete" class="action-item action-item-danger" @click="actionDelete">
                <i class="ti ti-trash" /> Cancel + delete
            </button>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useAssets, type AuditCampaign, type CampaignFrequency, type CampaignStatus, type Reconciliation } from '~/composables/useAssets'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import { formatDate, formatDateTime } from '~/composables/useDateFormat'
import Badge from '~/components/Badge.vue'
import Pagination from '~/components/Pagination.vue'

definePageMeta({ breadcrumb: 'Audits' })

const assetsApi = useAssets()
const authStore = useAuthStore()
const toast = useToast()
const canWrite = computed(() => authStore.hasPermission('assets.audit.write'))
const canDelete = computed(() => authStore.hasPermission('assets.audit.delete'))

const campaigns = ref<AuditCampaign[]>([])
const activeReconciliation = ref<Reconciliation | null>(null)
const loading = ref(false)

const filters = reactive({ search: '', status: '' as '' | CampaignStatus })
const pagination = reactive({ page: 1, limit: 15 })

const showFormModal = ref(false)
const saving = ref(false)
const formError = ref<string | null>(null)
const form = reactive({
    name: '',
    description: '',
    frequency: 'biannual' as CampaignFrequency,
    startsAt: new Date().toISOString().slice(0, 10),
    endsAt: '',
})

const actionMenu = reactive({ open: false, x: 0, y: 0, campaign: null as AuditCampaign | null })

const activeCampaign = computed(() => campaigns.value.find(c => c.status === 'active') || null)

const filteredCampaigns = computed(() => {
    const q = filters.search.trim().toLowerCase()
    return campaigns.value.filter(c => {
        if (filters.status && c.status !== filters.status) return false
        if (!q) return true
        return c.name.toLowerCase().includes(q) || (c.description ?? '').toLowerCase().includes(q)
    })
})
const totalPages = computed(() => Math.max(1, Math.ceil(filteredCampaigns.value.length / pagination.limit)))
const paged = computed(() => {
    const start = (pagination.page - 1) * pagination.limit
    return filteredCampaigns.value.slice(start, start + pagination.limit)
})
watch([() => filters.search, () => filters.status], () => { pagination.page = 1 })

const statusVariant = (s: CampaignStatus): 'success' | 'warning' | 'info' | 'secondary' => {
    if (s === 'active') return 'success'
    if (s === 'draft') return 'warning'
    if (s === 'completed') return 'info'
    return 'secondary'
}

const reconciliationTiles = computed(() => {
    const r = activeReconciliation.value
    if (!r) return []
    return [
        { key: 'expected', label: 'Expected',   value: r.expected.toLocaleString(),  icon: 'ti-target',         tone: 'primary' },
        { key: 'scanned',  label: 'Scanned',    value: r.scanned.toLocaleString(),   icon: 'ti-qrcode',         tone: 'info' },
        { key: 'matched',  label: 'Matched',    value: r.matched.toLocaleString(),   icon: 'ti-circle-check',   tone: 'success' },
        { key: 'moved',    label: 'Moved',      value: r.moved.toLocaleString(),     icon: 'ti-route',          tone: 'warning' },
        { key: 'damaged',  label: 'Damaged',    value: r.damaged.toLocaleString(),   icon: 'ti-alert-triangle', tone: 'danger' },
        { key: 'missing',  label: 'Missing',    value: r.missing.toLocaleString(),   icon: 'ti-help',           tone: 'danger' },
    ]
})

const loadCampaigns = async () => {
    loading.value = true
    try {
        const res = await assetsApi.getCampaigns({ limit: 500 })
        campaigns.value = res.data
        // Refresh the live reconciliation for the active campaign if one exists.
        if (activeCampaign.value) {
            const r = await assetsApi.getReconciliation(activeCampaign.value.id)
            activeReconciliation.value = r.data
        } else {
            activeReconciliation.value = null
        }
    } catch {
        campaigns.value = []
        activeReconciliation.value = null
    } finally {
        loading.value = false
    }
}

const openCreateModal = () => {
    Object.assign(form, {
        name: '', description: '', frequency: 'biannual',
        startsAt: new Date().toISOString().slice(0, 10), endsAt: '',
    })
    formError.value = null
    showFormModal.value = true
}
const closeFormModal = () => { if (!saving.value) showFormModal.value = false }

const saveCampaign = async () => {
    saving.value = true
    formError.value = null
    try {
        await assetsApi.createCampaign({
            name: form.name.trim(),
            description: form.description.trim() || null,
            frequency: form.frequency,
            startsAt: form.startsAt || null,
            endsAt: form.endsAt || null,
        })
        showFormModal.value = false
        await loadCampaigns()
        toast.success('Campaign created.', 'Move it to active when you are ready to start scanning.')
    } catch (err: any) {
        formError.value = err?.data?.message || 'Failed to create campaign.'
    } finally {
        saving.value = false
    }
}

const startCampaign = async (c: AuditCampaign) => {
    try {
        await assetsApi.startCampaign(c.id)
        await loadCampaigns()
        toast.success('Campaign started.', 'Custodians can now scan assets in the field.')
    } catch (err: any) {
        toast.error('Failed to start campaign.', err?.data?.message)
    }
}

const completeCampaign = async (c: AuditCampaign) => {
    const ok = await toast.confirm({
        title: `Complete ${c.name}?`,
        description: 'Auto-flags any unscanned assets as missing. The campaign moves to completed and can no longer accept scans.',
        confirmLabel: 'Complete cycle',
        color: 'primary',
        icon: 'ti-flag-check',
    })
    if (!ok) return
    try {
        await assetsApi.completeCampaign(c.id)
        await loadCampaigns()
        toast.success('Campaign completed.', 'Reconciliation report is now final.')
    } catch (err: any) {
        toast.error('Failed to complete.', err?.data?.message)
    }
}

const deleteCampaign = async (c: AuditCampaign) => {
    const ok = await toast.confirm({
        title: `Cancel ${c.name}?`,
        description: 'Soft-deletes the campaign. Scan logs already recorded against it remain attached.',
        confirmLabel: 'Cancel + delete',
        color: 'danger',
        icon: 'ti-trash',
    })
    if (!ok) return
    try {
        await assetsApi.deleteCampaign(c.id)
        await loadCampaigns()
        toast.success('Campaign cancelled.', `${c.name} is no longer active.`)
    } catch (err: any) {
        toast.error('Failed to cancel campaign.', err?.data?.message)
    }
}

// Position math mirrors fleet/vehicles: clamp on both axes so the dropdown
// never spills off the viewport, and flip above the trigger when there isn't
// room below. Without this, the last-row kebab pushes the dropdown under the
// page footer or off the right edge on narrow viewports.
const openActionMenu = (c: AuditCampaign, ev: MouseEvent) => {
    const rect = (ev.currentTarget as HTMLElement).getBoundingClientRect()
    const menuWidth = 200
    const menuMaxHeight = 160
    const left = Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8)
    const wouldOverflow = rect.bottom + menuMaxHeight + 8 > window.innerHeight
    actionMenu.campaign = c
    actionMenu.x = Math.max(8, left)
    actionMenu.y = wouldOverflow ? rect.top - menuMaxHeight - 6 : rect.bottom + 6
    actionMenu.open = true
}
const closeActionMenu = () => { actionMenu.open = false; actionMenu.campaign = null }
const actionStart = () => { if (actionMenu.campaign) startCampaign(actionMenu.campaign); closeActionMenu() }
const actionComplete = () => { if (actionMenu.campaign) completeCampaign(actionMenu.campaign); closeActionMenu() }
const actionDelete = () => { if (actionMenu.campaign) deleteCampaign(actionMenu.campaign); closeActionMenu() }

const onClickAway = () => closeActionMenu()
onMounted(() => {
    loadCampaigns()
    document.addEventListener('click', onClickAway)
})
onBeforeUnmount(() => document.removeEventListener('click', onClickAway))
</script>

<style scoped>
.topbar-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    color: var(--text-muted);
    cursor: pointer;
}

.topbar-btn:hover {
    background: var(--bg-muted);
    color: var(--text-heading);
}

.action-trigger {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 30px;
    height: 30px;
    border-radius: 8px;
    color: var(--text-muted);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}

.action-trigger:hover {
    background: var(--bg-muted);
    color: var(--text-heading);
}

.action-trigger-open {
    background: var(--bg-muted);
    color: var(--color-primary);
}

.action-item {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.75rem;
    color: var(--text-heading);
    text-align: left;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}

.action-item:hover {
    background: var(--bg-muted);
}

.action-item-danger {
    color: var(--color-danger);
}

.action-item-danger:hover {
    background: var(--color-danger-subtle);
}
</style>
