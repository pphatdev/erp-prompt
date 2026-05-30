<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Fixed Assets</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        <span v-if="kpis.total">
                            Registry of {{ kpis.total.toLocaleString() }} asset{{ kpis.total === 1 ? '' : 's' }} —
                            track custody, condition, and lifecycle.
                        </span>
                        <span v-else>Asset registry — capitalize, tag, and track every physical asset.</span>
                    </p>
                </div>
                <button v-if="canWrite" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />Register asset
                </button>
            </header>

            <!-- KPI strip — dashboard pattern: glass-card rounded-2xl + badge-soft icon + font-mono value + useCountUp -->
            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <article v-for="card in kpiCards" :key="card.key"
                    class="glass-card rounded-2xl p-4 space-y-2 col-span-1">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">{{ card.label }}</span>
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center" :class="`badge-soft-${card.tone}`">
                            <i :class="['ti', card.icon, 'text-sm']" />
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ card.value.toLocaleString() }}</p>
                    <p class="text-xxs text-(--text-muted)">{{ card.subtext }}</p>
                </article>
            </section>

            <!-- Filters -->
            <section class="glass-card rounded-xl p-4">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="relative md:col-span-5">
                        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                        <input v-model="filters.search" type="search"
                            placeholder="Search code, name, or serial..." class="form-control pl-9" />
                    </div>
                    <div class="md:col-span-4 flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1">
                        <button v-for="s in statusOptions" :key="s || 'all'"
                            class="flex-1 px-3 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
                            :class="filters.status === s ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                            @click="filters.status = s">
                            {{ s || 'all' }}
                        </button>
                    </div>
                    <div class="relative md:col-span-3">
                        <i class="ti ti-shield-check absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
                        <select v-model="filters.condition" class="form-control pl-9 appearance-none">
                            <option value="">Any condition</option>
                            <option v-for="c in conditions" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </div>
                </div>
            </section>

            <!-- Loading / empty -->
            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading assets...</span>
            </div>
            <div v-else-if="filtered.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-box-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">
                    {{ assets.length === 0 ? 'No assets registered' : 'No assets match' }}
                </h4>
                <p class="text-xs text-(--text-muted) mt-1">
                    {{ assets.length === 0
                        ? 'Register your first physical asset to start tracking the lifecycle.'
                        : 'Adjust your filters or clear the search.' }}
                </p>
            </div>

            <!-- Table -->
            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                <th class="px-4 py-3 font-semibold font-mono">Code</th>
                                <th class="px-4 py-3 font-semibold">Asset</th>
                                <th class="px-4 py-3 font-semibold hidden md:table-cell">Category</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Cost</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right hidden lg:table-cell">NBV</th>
                                <th class="px-4 py-3 font-semibold">Condition</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-color)">
                            <tr v-for="a in paged" :key="a.id" class="hover:bg-(--bg-muted) transition-colors">
                                <td class="px-4 py-3 font-mono text-xs text-(--text-body)">{{ a.assetCode }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-9 h-9 rounded-lg bg-(--color-primary-subtle) text-(--color-primary) flex items-center justify-center shrink-0">
                                            <i class="ti ti-cube text-sm" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs font-semibold text-(--text-heading) truncate">{{ a.name }}</div>
                                            <div class="text-xxs text-(--text-muted) truncate">
                                                {{ a.serialNumber ? `SN ${a.serialNumber}` : 'No serial' }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-xs hidden md:table-cell">{{ a.category || '—' }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-right">{{ formatMoney(a.purchasePrice) }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-right hidden lg:table-cell">
                                    {{ formatMoney(a.netBookValue) }}
                                </td>
                                <td class="px-4 py-3"><Badge :variant="conditionVariant(a.condition)" :dot="true">{{ a.condition }}</Badge></td>
                                <td class="px-4 py-3"><Badge :variant="statusVariant(a.status)" :dot="true">{{ a.status }}</Badge></td>
                                <td class="px-4 py-3 text-center">
                                    <button class="action-trigger"
                                        :class="{ 'action-trigger-open': actionMenu.open && actionMenu.asset?.id === a.id }"
                                        title="Actions" @click.stop="openActionMenu(a, $event)">
                                        <i class="ti ti-dots-vertical" />
                                    </button>
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

        <!-- Create / Edit modal -->
        <div v-if="showFormModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                <header class="flex items-center justify-between mb-5">
                    <h3 class="text-base font-semibold text-(--text-heading)">
                        {{ editing ? 'Edit asset' : 'Register asset' }}
                    </h3>
                    <button class="topbar-btn" @click="closeFormModal"><i class="ti ti-x" /></button>
                </header>
                <form class="form-grid" @submit.prevent="saveAsset">
                    <div class="form-grid-half">
                        <label class="form-label">Name <span class="text-(--color-danger)">*</span></label>
                        <input v-model="form.name" type="text" required class="form-control"
                            placeholder="MacBook Pro 16" />
                    </div>
                    <div class="form-grid-half">
                        <label class="form-label">Category <span class="text-(--color-danger)">*</span></label>
                        <input v-model="form.category" type="text" required class="form-control"
                            placeholder="IT Equipment" />
                    </div>
                    <div class="form-grid-half">
                        <label class="form-label">Serial number</label>
                        <input v-model="form.serialNumber" type="text" class="form-control" placeholder="C02XXXX" />
                    </div>
                    <div class="form-grid-half">
                        <label class="form-label">Vendor</label>
                        <input v-model="form.vendorName" type="text" class="form-control" placeholder="Apple" />
                    </div>
                    <div class="form-grid-full">
                        <label class="form-label">Description</label>
                        <textarea v-model="form.description" rows="2" class="form-control" />
                    </div>
                    <div class="form-grid-half">
                        <label class="form-label">Purchase date <span class="text-(--color-danger)">*</span></label>
                        <input v-model="form.purchaseDate" type="date" required class="form-control" />
                    </div>
                    <div class="form-grid-half">
                        <label class="form-label">Purchase price <span class="text-(--color-danger)">*</span></label>
                        <input v-model.number="form.purchasePrice" type="number" step="0.01" min="0" required class="form-control" />
                    </div>
                    <div class="form-grid-half">
                        <label class="form-label">Salvage value</label>
                        <input v-model.number="form.salvageValue" type="number" step="0.01" min="0" class="form-control" />
                    </div>
                    <div class="form-grid-half">
                        <label class="form-label">Useful life (months) <span class="text-(--color-danger)">*</span></label>
                        <input v-model.number="form.usefulLifeMonths" type="number" min="1" required class="form-control" />
                    </div>
                    <div class="form-grid-half">
                        <label class="form-label">Depreciation method</label>
                        <select v-model="form.depreciationMethod" class="form-control">
                            <option value="straight_line">Straight-Line</option>
                            <option value="declining_balance">Declining Balance</option>
                            <option value="sum_of_years_digits">Sum-of-the-Years' Digits</option>
                        </select>
                    </div>
                    <div class="form-grid-half">
                        <label class="form-label">Condition</label>
                        <select v-model="form.condition" class="form-control">
                            <option v-for="c in conditions" :key="c" :value="c">{{ c }}</option>
                        </select>
                    </div>
                    <div class="form-grid-half">
                        <label class="form-label">Custodian</label>
                        <select v-model="form.custodianEmployeeId" class="form-control">
                            <option value="">Unassigned</option>
                            <option v-for="e in employees" :key="e.id" :value="e.id">
                                {{ e.firstName }} {{ e.lastName }} ({{ e.employeeId }})
                            </option>
                        </select>
                    </div>
                    <div class="form-grid-half">
                        <label class="form-label">Location</label>
                        <input v-model="form.locationId" type="text" class="form-control" placeholder="HQ-Floor3-Room12" />
                    </div>
                    <div class="form-grid-full">
                        <label class="form-label">Notes</label>
                        <textarea v-model="form.notes" rows="2" class="form-control" />
                    </div>

                    <div v-if="formError" class="form-grid-full text-xs text-(--color-danger)">{{ formError }}</div>

                    <div class="form-grid-full flex justify-end gap-2 mt-2">
                        <button type="button" class="btn btn-ghost text-xs" :disabled="saving" @click="closeFormModal">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="saving">
                            <i :class="['ti', saving ? 'ti-loader animate-spin' : 'ti-device-floppy']" />
                            {{ saving ? 'Saving...' : (editing ? 'Save changes' : 'Register') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- QR preview modal -->
        <div v-if="qrAsset" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md p-6 shadow-(--shadow-lg) bg-(--bg-card) text-center">
                <header class="flex items-center justify-between mb-5">
                    <h3 class="text-base font-semibold text-(--text-heading)">QR tracking tag</h3>
                    <button class="topbar-btn" @click="qrAsset = null"><i class="ti ti-x" /></button>
                </header>
                <div class="font-mono text-xs text-(--text-muted) mb-3">{{ qrAsset.assetCode }} — {{ qrAsset.name }}</div>
                <div class="bg-white p-4 rounded-xl inline-block">
                    <img v-if="qrAsset.qrCodeUrl" :src="qrImgSrc(qrAsset.qrCodeUrl)" alt="QR" class="w-48 h-48" />
                    <div v-else class="w-48 h-48 flex items-center justify-center text-xs text-gray-500">
                        No QR URL on file.
                    </div>
                </div>
                <p class="text-xxs text-(--text-muted) mt-3 break-all">{{ qrAsset.qrCodeUrl || '—' }}</p>
                <button class="btn btn-ghost text-xs mt-3" @click="copyQrUrl(qrAsset)">
                    <i class="ti ti-copy" /> Copy URL
                </button>
            </div>
        </div>

        <!-- Action dropdown -->
        <div v-if="actionMenu.open && actionMenu.asset"
            class="fixed z-50 glass-card rounded-lg shadow-(--shadow-lg) bg-(--bg-card) border border-(--border-color) py-1 min-w-[200px]"
            :style="{ top: actionMenu.y + 'px', left: actionMenu.x + 'px' }" @click.stop>
            <button class="action-item" @click="actionViewQr">
                <i class="ti ti-qrcode" /> View QR tag
            </button>
            <button v-if="canWrite" class="action-item" @click="actionEdit">
                <i class="ti ti-pencil" /> Edit
            </button>
            <template v-if="canDelete">
                <hr class="my-1 border-(--border-color)" />
                <button class="action-item action-item-danger" @click="actionArchive">
                    <i class="ti ti-trash" /> Archive
                </button>
            </template>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useAssets, type Asset, type AssetCondition, type AssetStatus, type DepreciationMethod } from '~/composables/useAssets'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import { useCountUp } from '~/composables/useCountUp'
import { useApi } from '~/composables/useApi'
import Badge from '~/components/Badge.vue'
import Pagination from '~/components/Pagination.vue'

definePageMeta({ breadcrumb: 'Assets' })

interface EmployeeOption {
    id: string
    firstName: string
    lastName: string
    employeeId: string
}

const assetsApi = useAssets()
const authStore = useAuthStore()
const toast = useToast()
const api = useApi()
const canWrite = computed(() => authStore.hasPermission('assets.tracking.write'))
const canDelete = computed(() => authStore.hasPermission('assets.tracking.delete'))

const PAGE_BATCH = 500
const conditions: AssetCondition[] = ['Excellent', 'Good', 'Fair', 'Poor', 'Damaged']
const statusOptions = ['', 'draft', 'active', 'retired'] as const

const assets = ref<Asset[]>([])
const employees = ref<EmployeeOption[]>([])
const loading = ref(false)

const filters = reactive({ search: '', status: '' as '' | AssetStatus, condition: '' as '' | AssetCondition })
const pagination = reactive({ page: 1, limit: 15 })

const showFormModal = ref(false)
const editing = ref<Asset | null>(null)
const saving = ref(false)
const formError = ref<string | null>(null)
const qrAsset = ref<Asset | null>(null)

const form = reactive({
    name: '',
    serialNumber: '',
    description: '',
    category: '',
    vendorName: '',
    purchaseDate: new Date().toISOString().slice(0, 10),
    purchasePrice: 0,
    salvageValue: 0,
    usefulLifeMonths: 60,
    depreciationMethod: 'straight_line' as DepreciationMethod,
    condition: 'Good' as AssetCondition,
    custodianEmployeeId: '',
    locationId: '',
    notes: '',
})

const actionMenu = reactive({ open: false, x: 0, y: 0, asset: null as Asset | null })

const statusVariant = (s: AssetStatus): 'success' | 'warning' | 'secondary' => {
    if (s === 'active') return 'success'
    if (s === 'draft') return 'warning'
    return 'secondary'
}
const conditionVariant = (c: AssetCondition): 'success' | 'info' | 'warning' | 'danger' | 'secondary' => {
    if (c === 'Excellent') return 'success'
    if (c === 'Good') return 'info'
    if (c === 'Fair') return 'warning'
    if (c === 'Poor') return 'warning'
    return 'danger'
}

const formatMoney = (n: number) => new Intl.NumberFormat(undefined, { style: 'currency', currency: 'USD' }).format(n || 0)

const loadAssets = async () => {
    loading.value = true
    try {
        const res = await assetsApi.getAssets({ limit: PAGE_BATCH })
        assets.value = res.data
    } catch (err) {
        console.error('Failed to load assets', err)
        assets.value = []
    } finally {
        loading.value = false
    }
}

const loadEmployees = async () => {
    try {
        const res = await api.get<{ data: EmployeeOption[] }>('/employees?limit=500')
        employees.value = res.data || []
    } catch {
        // Lacking hrm.employee.read: free-text custodian selection just collapses to Unassigned.
        employees.value = []
    }
}

const filtered = computed(() => {
    const q = filters.search.trim().toLowerCase()
    return assets.value.filter(a => {
        if (filters.status && a.status !== filters.status) return false
        if (filters.condition && a.condition !== filters.condition) return false
        if (!q) return true
        return [a.assetCode, a.name, a.serialNumber ?? '', a.category ?? '']
            .some(s => s.toLowerCase().includes(q))
    })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / pagination.limit)))
const paged = computed(() => {
    const start = (pagination.page - 1) * pagination.limit
    return filtered.value.slice(start, start + pagination.limit)
})

watch([() => filters.search, () => filters.status, () => filters.condition], () => { pagination.page = 1 })

const kpis = computed(() => {
    const k = { total: assets.value.length, active: 0, retired: 0, totalValue: 0 }
    for (const a of assets.value) {
        if (a.status === 'active') k.active++
        else if (a.status === 'retired') k.retired++
        k.totalValue += a.netBookValue
    }
    return k
})

const totalCount = useCountUp(() => kpis.value.total)
const activeCount = useCountUp(() => kpis.value.active)
const retiredCount = useCountUp(() => kpis.value.retired)
const valueCount = useCountUp(() => Math.round(kpis.value.totalValue))

const kpiCards = computed(() => [
    { key: 'total',   label: 'Total assets', value: totalCount.value,   icon: 'ti-cube',         tone: 'primary', subtext: 'All registered' },
    { key: 'active',  label: 'Active',       value: activeCount.value,  icon: 'ti-circle-check', tone: 'success', subtext: 'In service' },
    { key: 'retired', label: 'Retired',      value: retiredCount.value, icon: 'ti-archive',      tone: 'info',    subtext: 'Decommissioned' },
    { key: 'nbv',     label: 'Total NBV',    value: valueCount.value,   icon: 'ti-coin',         tone: 'warning', subtext: 'Net book value' },
])

const resetForm = () => {
    Object.assign(form, {
        name: '', serialNumber: '', description: '', category: '', vendorName: '',
        purchaseDate: new Date().toISOString().slice(0, 10),
        purchasePrice: 0, salvageValue: 0, usefulLifeMonths: 60,
        depreciationMethod: 'straight_line', condition: 'Good',
        custodianEmployeeId: '', locationId: '', notes: '',
    })
    formError.value = null
}

const openCreateModal = () => {
    editing.value = null
    resetForm()
    showFormModal.value = true
}

const openEditModal = (a: Asset) => {
    editing.value = a
    Object.assign(form, {
        name: a.name,
        serialNumber: a.serialNumber ?? '',
        description: a.description ?? '',
        category: a.category ?? '',
        vendorName: a.vendorName ?? '',
        purchaseDate: a.purchaseDate ?? new Date().toISOString().slice(0, 10),
        purchasePrice: a.purchasePrice,
        salvageValue: a.salvageValue,
        usefulLifeMonths: a.usefulLifeMonths,
        depreciationMethod: a.depreciationMethod,
        condition: a.condition,
        custodianEmployeeId: a.custodianEmployeeId ?? '',
        locationId: a.locationId ?? '',
        notes: a.notes ?? '',
    })
    formError.value = null
    showFormModal.value = true
}

const closeFormModal = () => {
    if (saving.value) return
    showFormModal.value = false
    editing.value = null
}

const saveAsset = async () => {
    saving.value = true
    formError.value = null
    try {
        const payload: Record<string, unknown> = {
            name: form.name.trim(),
            category: form.category.trim(),
            purchaseDate: form.purchaseDate,
            purchasePrice: form.purchasePrice,
            salvageValue: form.salvageValue,
            usefulLifeMonths: form.usefulLifeMonths,
            depreciationMethod: form.depreciationMethod,
            condition: form.condition,
        }
        if (form.serialNumber.trim())        payload.serialNumber       = form.serialNumber.trim()
        if (form.description.trim())         payload.description        = form.description.trim()
        if (form.vendorName.trim())          payload.vendorName         = form.vendorName.trim()
        if (form.custodianEmployeeId)        payload.custodianEmployeeId= form.custodianEmployeeId
        if (form.locationId.trim())          payload.locationId         = form.locationId.trim()
        if (form.notes.trim())               payload.notes              = form.notes.trim()

        if (editing.value) {
            await assetsApi.updateAsset(editing.value.id, payload)
        } else {
            await assetsApi.createAsset(payload)
        }
        const wasEditing = !!editing.value
        showFormModal.value = false
        editing.value = null
        await loadAssets()
        toast.success(
            wasEditing ? 'Asset updated.' : 'Asset registered.',
            `${payload.name} ${wasEditing ? 'saved.' : 'is now in the register.'}`
        )
    } catch (err: any) {
        const errors = err?.data?.errors
        if (errors && typeof errors === 'object') {
            formError.value = (Object.values(errors).flat()[0] as string) || 'Validation failed.'
        } else {
            formError.value = err?.data?.message || 'Failed to save asset.'
        }
    } finally {
        saving.value = false
    }
}

const archiveAsset = async (a: Asset) => {
    const ok = await toast.confirm({
        title: `Archive ${a.assetCode}?`,
        description: 'Removes the asset from the active register. Depreciation logs and audit history stay attached for traceability.',
        confirmLabel: 'Archive',
        color: 'danger',
        icon: 'ti-trash',
    })
    if (!ok) return
    try {
        await assetsApi.archiveAsset(a.id)
        await loadAssets()
        toast.success('Asset archived.', `${a.assetCode} is no longer active.`)
    } catch (err: any) {
        toast.error('Failed to archive asset.', err?.data?.message)
    }
}

// Action menu --------------------------------------------------------------
// Position math mirrors fleet/vehicles: clamp on both axes so the dropdown
// never spills off the viewport, and flip above the trigger when there isn't
// room below. Without this, kebabs in the last visible row push the dropdown
// under the page footer (or off the right edge on narrow viewports).
const openActionMenu = (asset: Asset, ev: MouseEvent) => {
    const rect = (ev.currentTarget as HTMLElement).getBoundingClientRect()
    const menuWidth = 200
    const menuMaxHeight = 160
    const left = Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8)
    const wouldOverflow = rect.bottom + menuMaxHeight + 8 > window.innerHeight
    actionMenu.asset = asset
    actionMenu.x = Math.max(8, left)
    actionMenu.y = wouldOverflow ? rect.top - menuMaxHeight - 6 : rect.bottom + 6
    actionMenu.open = true
}
const closeActionMenu = () => { actionMenu.open = false; actionMenu.asset = null }
const actionEdit = () => { if (actionMenu.asset) openEditModal(actionMenu.asset); closeActionMenu() }
const actionArchive = () => { if (actionMenu.asset) archiveAsset(actionMenu.asset); closeActionMenu() }
const actionViewQr = () => { qrAsset.value = actionMenu.asset; closeActionMenu() }

const onClickAway = () => closeActionMenu()
onMounted(() => {
    loadAssets()
    loadEmployees()
    document.addEventListener('click', onClickAway)
})
onBeforeUnmount(() => document.removeEventListener('click', onClickAway))

// QR helpers ---------------------------------------------------------------
const qrImgSrc = (url: string) =>
    `https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=${encodeURIComponent(url)}`

const copyQrUrl = async (a: Asset) => {
    if (!a.qrCodeUrl) return
    try {
        await navigator.clipboard.writeText(a.qrCodeUrl)
        toast.success('Copied.', 'QR verification URL copied to clipboard.')
    } catch {
        toast.error('Copy failed.', 'Clipboard access was blocked.')
    }
}
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
