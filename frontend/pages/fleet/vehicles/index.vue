<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Page header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Vehicles</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        <span v-if="kpis.total">
                            Manage {{ kpis.total.toLocaleString() }} vehicle{{ kpis.total === 1 ? '' : 's' }} — register
                            new assets, monitor service, track fuel.
                        </span>
                        <span v-else>Vehicle registry — register assets, monitor service, track fuel.</span>
                    </p>
                </div>
                <button v-if="canWrite" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />Add vehicle
                </button>
            </header>

            <!-- KPI strip -->
            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-4 gap-4">
                <article v-for="card in kpiCards" :key="card.key"
                    class="glass-card rounded-2xl p-4 space-y-2 col-span-1">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">{{ card.label }}</span>
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center"
                            :class="`badge-soft-${card.tone}`">
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
                    <div class="relative md:col-span-6">
                        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                        <input v-model="filters.search" type="search"
                            placeholder="Search registration, make, model, or VIN..." class="form-control pl-9" />
                    </div>
                    <div class="md:col-span-6 flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1">
                        <button v-for="s in (['', 'active', 'maintenance', 'retired'] as const)" :key="s || 'all'"
                            class="flex-1 px-3 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
                            :class="filters.status === s ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                            @click="filters.status = s">
                            {{ s || 'all' }}
                        </button>
                    </div>
                </div>
            </section>

            <!-- Loading -->
            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span
                    class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading vehicles...</span>
            </div>

            <!-- Empty -->
            <div v-else-if="filtered.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-truck-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">
                    {{ vehicles.length === 0 ? 'No vehicles registered' : 'No vehicles match' }}
                </h4>
                <p class="text-xs text-(--text-muted) mt-1">
                    {{ vehicles.length === 0 ? 'Register your first vehicle to start tracking the fleet.' : 'Adjust your filters or clear the search.' }}
                </p>
            </div>

            <!-- Table -->
            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <!-- Bulk action toolbar (slides in while rows are checked) -->
                <transition name="bulkbar">
                    <div v-if="canWrite && selectedCount > 0" class="bulk-toolbar">
                        <div class="flex items-center gap-2 text-xs">
                            <span class="font-semibold text-(--color-primary)">{{ selectedCount }} selected</span>
                            <span class="text-(--text-muted)">·</span>
                            <button type="button"
                                class="text-(--text-muted) hover:text-(--text-heading) underline-offset-2 hover:underline"
                                @click="clearSelection">
                                Clear
                            </button>
                        </div>
                        <div class="flex items-center gap-2">
                            <button type="button"
                                class="btn btn-ghost text-xs px-3 py-1.5 text-(--color-danger) hover:bg-(--color-danger-subtle) hover:text-(--color-danger)"
                                :disabled="bulkArchiving || selectedArchivable.length === 0"
                                :title="selectedArchivable.length === 0 ? 'Nothing to archive' : 'Archive the selected vehicles'"
                                @click="bulkArchive">
                                <i :class="['ti', bulkArchiving ? 'ti-loader animate-spin' : 'ti-trash']" />
                                {{ bulkArchiving ? 'Archiving...' : `Archive ${selectedArchivable.length}` }}
                            </button>
                        </div>
                    </div>
                </transition>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                <th v-if="canWrite" class="pl-4 pr-1 py-3 w-8">
                                    <input type="checkbox" class="row-checkbox" :checked="allSelectableSelected"
                                        :indeterminate.prop="someSelectableSelected && !allSelectableSelected"
                                        :disabled="selectableRows.length === 0"
                                        :title="selectableRows.length === 0 ? 'No rows on this page' : 'Select all visible rows'"
                                        @change="toggleSelectAll">
                                </th>
                                <th class="px-4 py-3 font-semibold font-mono">Reg #</th>
                                <th class="px-4 py-3 font-semibold">Vehicle</th>
                                <th class="px-4 py-3 font-semibold hidden md:table-cell">Year</th>
                                <th class="px-4 py-3 font-semibold font-mono hidden lg:table-cell">VIN</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Mileage</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-color)">
                            <tr v-for="v in paged" :key="v.id" class="transition-colors"
                                :class="selectedIds.has(v.id) ? 'bg-(--color-primary-subtle)/30' : 'hover:bg-(--bg-muted)'">
                                <td v-if="canWrite" class="pl-4 pr-1 py-3 w-8">
                                    <input type="checkbox" class="row-checkbox" :checked="selectedIds.has(v.id)"
                                        @change="toggleRow(v)">
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-(--text-body)">{{ v.registrationNumber }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="w-9 h-9 rounded-lg bg-(--color-primary-subtle) text-(--color-primary) flex items-center justify-center shrink-0 overflow-hidden">
                                            <img v-if="v.imageUrl" :src="v.imageUrl" :alt="`${v.make} ${v.model}`"
                                                class="w-full h-full object-cover" />
                                            <i v-else class="ti ti-truck text-sm" />
                                        </div>
                                        <div class="min-w-0">
                                            <div class="text-xs font-semibold text-(--text-heading) truncate">
                                                {{ v.make }} {{ v.model }}
                                            </div>
                                            <div class="text-xxs text-(--text-muted) truncate">
                                                Last updated {{ formatDateTime(v.updatedAt) }}
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-xs font-mono hidden md:table-cell">{{ v.year }}</td>
                                <td class="px-4 py-3 font-mono text-xxs text-(--text-muted) hidden lg:table-cell">
                                    {{ v.vin || '—' }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-right">
                                    {{ v.currentMileage.toLocaleString() }}<span class="text-xxs text-(--text-muted) ml-1">km</span>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge :variant="statusVariant(v.status)" :dot="true">{{ v.status }}</Badge>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button class="action-trigger"
                                        :class="{ 'action-trigger-open': actionMenu.open && actionMenu.vehicle?.id === v.id }"
                                        title="Actions" @click.stop="openActionMenu(v, $event)">
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

            <!-- Form modal — shared between Create + Edit. `editing` ref branches save behavior. -->
            <div v-if="showFormModal"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="glass-card rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                    <header class="flex items-center justify-between mb-5">
                        <h3 class="text-base font-semibold text-(--text-heading)">
                            {{ editing ? 'Edit vehicle' : 'Register vehicle' }}
                        </h3>
                        <button class="topbar-btn" @click="closeFormModal"><i class="ti ti-x" /></button>
                    </header>

                    <form class="form-grid" @submit.prevent="saveVehicle">
                        <!-- Vehicle photo — public asset, multipart endpoint fires AFTER the JSON save. -->
                        <div class="form-grid-full flex items-start gap-4">
                            <div class="w-20 h-20 rounded-xl border border-(--border-color) bg-(--bg-muted) overflow-hidden flex items-center justify-center shrink-0">
                                <img v-if="imagePreview" :src="imagePreview" alt="preview" class="w-full h-full object-cover" />
                                <i v-else class="ti ti-truck text-2xl text-(--text-muted)" />
                            </div>
                            <div class="flex-1 space-y-2">
                                <label class="form-label">Photo</label>
                                <div class="flex flex-wrap items-center gap-2">
                                    <label class="btn btn-ghost text-xs border border-(--border-color) rounded-lg px-3 py-1.5 cursor-pointer inline-flex items-center gap-2">
                                        <i class="ti ti-upload" />
                                        {{ imagePreview ? 'Change photo' : 'Upload photo' }}
                                        <input ref="imageInput" type="file" accept="image/*" class="hidden"
                                            @change="onImageChange" />
                                    </label>
                                    <button v-if="imagePreview" type="button"
                                        class="text-xxs text-(--color-danger) hover:underline inline-flex items-center gap-1"
                                        @click="clearImage">
                                        <i class="ti ti-trash text-xs" />Remove
                                    </button>
                                </div>
                                <p class="text-xxs text-(--text-muted)">PNG, JPG or WebP · max 2 MB</p>
                            </div>
                        </div>

                        <div>
                            <label class="form-label form-label-required">Registration number</label>
                            <input v-model="form.registration_number" type="text" required
                                class="form-control font-mono uppercase" placeholder="ABC-1234" />
                        </div>
                        <div>
                            <label class="form-label">VIN</label>
                            <input v-model="form.vin" type="text" class="form-control font-mono"
                                placeholder="17-char chassis number" />
                        </div>

                        <!-- Explicit catalog picker — populates Make/Model on selection.
                             Free-text + datalist autocomplete below still work for entries
                             that aren't in the catalog yet (or for refining a pick). -->
                        <div v-if="vehicleModels.length" class="form-grid-full">
                            <label class="form-label">Pick from catalog</label>
                            <select :value="catalogPickValue" class="form-control" @change="onCatalogPick">
                                <option value="">— Type freely or pick a model below</option>
                                <option v-for="m in sortedVehicleModels" :key="m.id" :value="m.id">
                                    {{ m.make }} {{ m.model }}{{ catalogOptionSuffix(m) }}
                                </option>
                            </select>
                            <span class="form-hint">Picking populates Make + Model below. You can still edit either.</span>
                        </div>

                        <div>
                            <label class="form-label form-label-required">Make</label>
                            <input v-model="form.make" type="text" required class="form-control" placeholder="Toyota"
                                list="vehicle-make-list" />
                        </div>
                        <div>
                            <label class="form-label form-label-required">Model</label>
                            <input v-model="form.model" type="text" required class="form-control" placeholder="Hilux"
                                list="vehicle-model-list" />
                            <span v-if="currentCatalogEntry" class="form-hint flex items-center gap-1.5 mt-1">
                                <i class="ti ti-check text-(--color-success)" />
                                <span>
                                    Catalog match
                                    <template v-if="currentCatalogEntry.bodyType || currentCatalogEntry.fuelType">·</template>
                                    <span v-if="currentCatalogEntry.bodyType" class="font-mono">{{ currentCatalogEntry.bodyType }}</span>
                                    <template v-if="currentCatalogEntry.bodyType && currentCatalogEntry.fuelType"> · </template>
                                    <span v-if="currentCatalogEntry.fuelType" class="font-mono">{{ currentCatalogEntry.fuelType }}</span>
                                </span>
                            </span>
                        </div>

                        <!-- Catalog-driven autocomplete. Empty when no entries / no access. -->
                        <datalist id="vehicle-make-list">
                            <option v-for="make in distinctMakes" :key="make" :value="make" />
                        </datalist>
                        <datalist id="vehicle-model-list">
                            <option v-for="model in modelsForMake" :key="model" :value="model" />
                        </datalist>

                        <div>
                            <label class="form-label form-label-required">Year</label>
                            <input v-model.number="form.year" type="number" min="1900" :max="currentYear + 1" required
                                class="form-control font-mono" />
                        </div>
                        <div>
                            <label class="form-label">Status</label>
                            <select v-model="form.status" class="form-control">
                                <option value="active">Active</option>
                                <option value="maintenance">In maintenance</option>
                                <option value="retired">Retired</option>
                            </select>
                        </div>

                        <div class="form-grid-full">
                            <label class="form-label">
                                Current mileage (km)
                                <span v-if="editing" class="text-xxs text-(--text-muted) ml-1 normal-case">(immutable)</span>
                            </label>
                            <input v-model.number="form.current_mileage" type="number" min="0"
                                class="form-control font-mono" :disabled="!!editing" />
                            <span class="form-hint">
                                {{ editing
                                    ? 'Mileage only advances through a maintenance or fuel log — the backend enforces the monotonic check.'
                                    : 'Used as the floor for the monotonic mileage check on every future log.' }}
                            </span>
                        </div>

                        <div v-if="formError" class="form-grid-full form-error">{{ formError }}</div>

                        <footer class="form-grid-full pt-4 border-t border-(--border-color) flex justify-end gap-2">
                            <button type="button" class="btn btn-ghost text-xs" @click="closeFormModal">Cancel</button>
                            <button type="submit" class="btn btn-primary text-xs" :disabled="saving">
                                <i class="ti ti-device-floppy" />
                                {{ saving
                                    ? 'Saving...'
                                    : (editing ? 'Save changes' : 'Register vehicle') }}
                            </button>
                        </footer>
                    </form>
                </div>
            </div>

            <!-- Details modal -->
            <div v-if="detailsOpen && detailsVehicle"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="glass-card rounded-2xl w-full max-w-lg max-h-[80vh] overflow-y-auto p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                    <header class="flex items-center justify-between mb-5">
                        <div>
                            <h3 class="text-base font-semibold text-(--text-heading)">
                                {{ detailsVehicle.make }} {{ detailsVehicle.model }}
                            </h3>
                            <p class="text-xxs text-(--text-muted) mt-1 font-mono">
                                {{ detailsVehicle.registrationNumber }}
                            </p>
                        </div>
                        <button class="topbar-btn" @click="detailsOpen = false"><i class="ti ti-x" /></button>
                    </header>

                    <div v-if="detailsVehicle.imageUrl"
                        class="rounded-xl overflow-hidden border border-(--border-color) bg-(--bg-muted) mb-5 aspect-video">
                        <img :src="detailsVehicle.imageUrl" :alt="`${detailsVehicle.make} ${detailsVehicle.model}`"
                            class="w-full h-full object-cover" />
                    </div>

                    <dl class="text-xs space-y-3">
                        <div class="flex justify-between gap-3">
                            <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Year</dt>
                            <dd class="text-(--text-body) font-mono">{{ detailsVehicle.year }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">VIN</dt>
                            <dd class="text-(--text-body) font-mono">{{ detailsVehicle.vin || '—' }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Current mileage</dt>
                            <dd class="text-(--text-body) font-mono">
                                {{ detailsVehicle.currentMileage.toLocaleString() }} km
                            </dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Status</dt>
                            <dd><Badge :variant="statusVariant(detailsVehicle.status)" :dot="true">{{ detailsVehicle.status }}</Badge></dd>
                        </div>
                        <div class="flex justify-between gap-3 pt-2 border-t border-(--border-color)">
                            <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Registered</dt>
                            <dd class="text-(--text-muted) font-mono text-xxs">{{ formatDateTime(detailsVehicle.createdAt) }}</dd>
                        </div>
                        <div class="flex justify-between gap-3">
                            <dt class="text-(--text-muted) uppercase tracking-widest text-xxs font-bold">Last updated</dt>
                            <dd class="text-(--text-muted) font-mono text-xxs">{{ formatDateTime(detailsVehicle.updatedAt) }}</dd>
                        </div>
                    </dl>

                    <p class="text-xxs text-(--text-muted) mt-5 pt-4 border-t border-(--border-color)">
                        Maintenance and fuel logs surface in a dedicated detail page in a later phase.
                    </p>
                </div>
            </div>

            <!-- Action dropdown -->
            <div v-if="actionMenu.open && actionMenu.vehicle"
                class="fixed z-50 glass-card rounded-lg shadow-(--shadow-lg) bg-(--bg-card) border border-(--border-color) py-1 min-w-[180px]"
                :style="{ top: actionMenu.y + 'px', left: actionMenu.x + 'px' }" @click.stop>
                <button class="action-item" @click="actionView">
                    <i class="ti ti-eye" /> View details
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
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useFleet, type BulkResult, type Vehicle, type VehicleModel, type VehicleStatus } from '~/composables/useFleet'
import { formatDateTime } from '~/composables/useDateFormat'
import { useCountUp } from '~/composables/useCountUp'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import Badge from '~/components/Badge.vue'
import Pagination from '~/components/Pagination.vue'

const fleet = useFleet()
const authStore = useAuthStore()
const toast = useToast()
const canWrite = computed(() => authStore.hasPermission('fleet.vehicles.write'))
const canDelete = computed(() => authStore.hasPermission('fleet.vehicles.delete'))

const vehicles = ref<Vehicle[]>([])
// Catalog rows feed the Make/Model datalist hints. Loaded best-effort: a
// 403 (user lacks fleet.vehicle_models.read) just leaves the datalists empty
// — the free-text inputs still work, the form just loses autocomplete.
const vehicleModels = ref<VehicleModel[]>([])
const loading = ref(false)

// We fetch up to 500 rows in one shot so the KPI strip can derive accurate
// per-status counts without an extra endpoint. Pagination below is client-side
// against the `filtered` view — fleets bigger than 500 will need a server
// aggregation endpoint, tracked in .task/fleet/task.md.
const PAGE_BATCH = 500

const filters = reactive({ search: '', status: '' as '' | VehicleStatus })
const pagination = reactive({ page: 1, limit: 15 })

const showFormModal = ref(false)
const editing = ref<Vehicle | null>(null)
const saving = ref(false)
const formError = ref<string | null>(null)
const currentYear = new Date().getFullYear()
const form = reactive({
    registration_number: '',
    make: '',
    model: '',
    year: currentYear,
    vin: '',
    status: 'active' as VehicleStatus,
    current_mileage: 0,
})

const detailsOpen = ref(false)
const detailsVehicle = ref<Vehicle | null>(null)

// --- Photo picker --------------------------------------------------------
// The JSON Create/Update endpoint stays clean — the image rides a separate
// POST /vehicles/{id}/image multipart call once we know the id (mirrors how
// the employees page handles avatars). `removeImageFlag` lets the user clear
// a previously-uploaded photo as part of saving an edit.
const imageInput = ref<HTMLInputElement | null>(null)
const imageFile = ref<File | null>(null)
const imagePreview = ref<string | null>(null)
const removeImageFlag = ref(false)

const onImageChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null
    imageFile.value = file
    removeImageFlag.value = false
    if (file) {
        const reader = new FileReader()
        reader.onload = ev => { imagePreview.value = ev.target?.result as string }
        reader.readAsDataURL(file)
    }
}

const clearImage = () => {
    imageFile.value = null
    imagePreview.value = null
    removeImageFlag.value = true
    if (imageInput.value) imageInput.value.value = ''
}

const actionMenu = reactive({
    open: false,
    x: 0,
    y: 0,
    vehicle: null as Vehicle | null,
})

const statusVariant = (s: VehicleStatus): 'success' | 'warning' | 'secondary' => {
    if (s === 'active') return 'success'
    if (s === 'maintenance') return 'warning'
    return 'secondary'
}

const loadVehicles = async () => {
    loading.value = true
    try {
        const [vehiclesRes, modelsRes] = await Promise.all([
            fleet.getVehicles({ limit: PAGE_BATCH }),
            // Catalog is best-effort — a failure (403 / network) silently
            // collapses to an empty list so the form's free-text fallback
            // remains usable.
            fleet.getVehicleModels({ limit: PAGE_BATCH }).catch(() => ({ data: [] as VehicleModel[] })),
        ])
        vehicles.value = vehiclesRes.data
        vehicleModels.value = (modelsRes as { data: VehicleModel[] }).data ?? []
    } catch (err) {
        console.error('Failed to load vehicles', err)
        vehicles.value = []
    } finally {
        loading.value = false
    }
}

// Distinct makes from the catalog, alpha-sorted for the Make datalist.
const distinctMakes = computed(() => {
    const set = new Set<string>()
    for (const m of vehicleModels.value) set.add(m.make)
    return Array.from(set).sort((a, b) => a.localeCompare(b))
})

// Models filtered by whatever the user has currently typed/picked in Make.
// Case-insensitive match so "toyota" still finds "Toyota Hilux".
const modelsForMake = computed(() => {
    const make = form.make.trim().toLowerCase()
    if (!make) return []
    return vehicleModels.value
        .filter(m => m.make.toLowerCase() === make)
        .map(m => m.model)
        .sort((a, b) => a.localeCompare(b))
})

// Current (make, model) catalog hit — drives the body/fuel hint under Model
// AND the bound value of the explicit catalog picker above.
const currentCatalogEntry = computed(() => {
    const make = form.make.trim().toLowerCase()
    const model = form.model.trim().toLowerCase()
    if (!make || !model) return null
    return vehicleModels.value.find(m =>
        m.make.toLowerCase() === make && m.model.toLowerCase() === model
    ) ?? null
})

// Alphabetized by Make then Model so the dropdown stays scannable as the
// catalog grows. Sort runs once per catalog change.
const sortedVehicleModels = computed(() => {
    return [...vehicleModels.value].sort((a, b) => {
        const byMake = a.make.localeCompare(b.make)
        return byMake !== 0 ? byMake : a.model.localeCompare(b.model)
    })
})

// Bind the explicit picker to the current catalog match (or '' when free-text).
const catalogPickValue = computed(() =>
    currentCatalogEntry.value?.id ?? ''
)

const catalogOptionSuffix = (m: VehicleModel) => {
    const tail = [m.bodyType, m.fuelType].filter(Boolean).join(' · ')
    return tail ? ` · ${tail}` : ''
}

const onCatalogPick = (ev: Event) => {
    const id = (ev.target as HTMLSelectElement).value
    if (!id) return
    const picked = vehicleModels.value.find(m => m.id === id)
    if (picked) {
        form.make = picked.make
        form.model = picked.model
    }
}

const filtered = computed(() => {
    const q = filters.search.trim().toLowerCase()
    return vehicles.value.filter(v => {
        if (filters.status && v.status !== filters.status) return false
        if (!q) return true
        return [v.registrationNumber, v.make, v.model, v.vin ?? '']
            .some(s => s.toLowerCase().includes(q))
    })
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / pagination.limit)))

const paged = computed(() => {
    const start = (pagination.page - 1) * pagination.limit
    return filtered.value.slice(start, start + pagination.limit)
})

// Reset to page 1 when the filter set changes; otherwise a deeply-paged user
// can find themselves stranded past the new last page.
watch([() => filters.search, () => filters.status], () => {
    pagination.page = 1
})

const kpis = computed(() => {
    const counts = { total: vehicles.value.length, active: 0, maintenance: 0, retired: 0 }
    for (const v of vehicles.value) {
        if (v.status === 'active') counts.active++
        else if (v.status === 'maintenance') counts.maintenance++
        else if (v.status === 'retired') counts.retired++
    }
    return counts
})

// Per-card animated counters — read raw numbers from `kpis` and ride the
// useCountUp RAF loop so the table-card values tick from 0 → target on
// initial load and smoothly retarget after each loadVehicles() refresh.
const totalCount = useCountUp(() => kpis.value.total)
const activeCount = useCountUp(() => kpis.value.active)
const maintenanceCount = useCountUp(() => kpis.value.maintenance)
const retiredCount = useCountUp(() => kpis.value.retired)

const kpiCards = computed(() => [
    { key: 'total',       label: 'Total',          value: totalCount.value,       icon: 'ti-truck',        tone: 'primary', subtext: 'All vehicles' },
    { key: 'active',      label: 'Active',         value: activeCount.value,      icon: 'ti-circle-check', tone: 'success', subtext: 'On the road' },
    { key: 'maintenance', label: 'In maintenance', value: maintenanceCount.value, icon: 'ti-tool',         tone: 'warning', subtext: 'Off the road' },
    { key: 'retired',     label: 'Retired',        value: retiredCount.value,     icon: 'ti-archive',      tone: 'info',    subtext: 'Decommissioned' },
])

const resetForm = () => {
    Object.assign(form, {
        registration_number: '',
        make: '',
        model: '',
        year: currentYear,
        vin: '',
        status: 'active',
        current_mileage: 0,
    })
    formError.value = null
    imageFile.value = null
    imagePreview.value = null
    removeImageFlag.value = false
    if (imageInput.value) imageInput.value.value = ''
}

const openCreateModal = () => {
    editing.value = null
    resetForm()
    showFormModal.value = true
}

const openEditModal = (v: Vehicle) => {
    editing.value = v
    Object.assign(form, {
        registration_number: v.registrationNumber,
        make: v.make,
        model: v.model,
        year: v.year,
        vin: v.vin ?? '',
        status: v.status,
        current_mileage: v.currentMileage,
    })
    formError.value = null
    imageFile.value = null
    imagePreview.value = v.imageUrl ?? null
    removeImageFlag.value = false
    if (imageInput.value) imageInput.value.value = ''
    showFormModal.value = true
}

const closeFormModal = () => {
    if (saving.value) return
    showFormModal.value = false
    editing.value = null
}

const saveVehicle = async () => {
    saving.value = true
    formError.value = null
    try {
        const payload: Record<string, unknown> = {
            registration_number: form.registration_number.trim().toUpperCase(),
            make: form.make.trim(),
            model: form.model.trim(),
            year: form.year,
            status: form.status,
        }
        if (form.vin) payload.vin = form.vin.trim().toUpperCase()
        else if (editing.value) payload.vin = null
        // current_mileage only goes on create — on edit the backend rejects it
        // to keep the monotonic invariant honest.
        if (!editing.value) payload.current_mileage = form.current_mileage

        // Resolve the target vehicle id so the image multipart can fire after
        // either branch. On create we read it from the resource the API returns.
        let vehicleId: string
        if (editing.value) {
            await fleet.updateVehicle(editing.value.id, payload)
            vehicleId = editing.value.id
        } else {
            const res = await fleet.createVehicle(payload)
            vehicleId = res.data.id
        }

        // Image rides a separate multipart call so the JSON PUT/POST stays
        // pure JSON. Order: upload new file first; otherwise delete if the
        // user cleared an existing photo while editing.
        if (imageFile.value) {
            await fleet.uploadVehicleImage(vehicleId, imageFile.value)
        } else if (editing.value && removeImageFlag.value) {
            await fleet.deleteVehicleImage(vehicleId)
        }

        showFormModal.value = false
        const wasEditing = !!editing.value
        editing.value = null
        await loadVehicles()
        toast.success(
            wasEditing ? 'Vehicle updated.' : 'Vehicle registered.',
            `${payload.registration_number} ${wasEditing ? 'saved.' : 'is now in the fleet.'}`
        )
    } catch (err: any) {
        const errors = err?.data?.errors
        if (errors && typeof errors === 'object') {
            formError.value = (Object.values(errors).flat()[0] as string) || 'Validation failed.'
        } else {
            formError.value = err?.data?.message || 'Failed to save vehicle.'
        }
    } finally {
        saving.value = false
    }
}

// --- Single archive ------------------------------------------------------
const archiveVehicle = async (v: Vehicle) => {
    const ok = await toast.confirm({
        title: `Archive ${v.registrationNumber}?`,
        description: 'The vehicle is removed from the active fleet. Maintenance and fuel logs stay attached for audit and reappear if the vehicle is restored.',
        confirmLabel: 'Archive',
        color: 'danger',
        icon: 'ti-trash',
    })
    if (!ok) return
    try {
        await fleet.archiveVehicle(v.id)
        selectedIds.value.delete(v.id)
        await loadVehicles()
        toast.success('Vehicle archived.', `${v.registrationNumber} is no longer active.`)
    } catch (err: any) {
        toast.error('Failed to archive vehicle.', err?.data?.message)
    }
}

// --- Bulk selection (design.md §14.3 / §14.4) ----------------------------
// Every vehicle is selectable because Archive applies to any status. The
// envelope returned by /vehicles/bulk-archive (deleted / skipped / missing)
// drives the partial-success toast.
const selectedIds = ref<Set<string>>(new Set())
const bulkArchiving = ref(false)

const isSelectable = (_v: Vehicle) => true
const selectableRows = computed(() => vehicles.value)
const selectedVehicles = computed(() => vehicles.value.filter(v => selectedIds.value.has(v.id)))
const selectedArchivable = computed(() => selectedVehicles.value)
const selectedCount = computed(() => selectedIds.value.size)

const allSelectableSelected = computed(() =>
    selectableRows.value.length > 0 &&
    selectableRows.value.every(v => selectedIds.value.has(v.id))
)
const someSelectableSelected = computed(() =>
    selectableRows.value.some(v => selectedIds.value.has(v.id))
)

const toggleRow = (v: Vehicle) => {
    const next = new Set(selectedIds.value)
    if (next.has(v.id)) next.delete(v.id)
    else next.add(v.id)
    selectedIds.value = next
}

const toggleSelectAll = () => {
    const next = new Set(selectedIds.value)
    if (allSelectableSelected.value) {
        selectableRows.value.forEach(v => next.delete(v.id))
    } else {
        selectableRows.value.forEach(v => next.add(v.id))
    }
    selectedIds.value = next
}

const clearSelection = () => { selectedIds.value = new Set() }

const bulkArchive = async () => {
    if (bulkArchiving.value) return
    const ids = selectedArchivable.value.map(v => v.id)
    if (ids.length === 0) return
    const ok = await toast.confirm({
        title: `Archive ${ids.length} vehicle${ids.length === 1 ? '' : 's'}?`,
        description: 'Each is removed from the active fleet. Maintenance and fuel logs stay attached for audit.',
        confirmLabel: 'Archive all',
        color: 'danger',
        icon: 'ti-trash',
    })
    if (!ok) return

    bulkArchiving.value = true
    try {
        const res: BulkResult = await fleet.bulkArchiveVehicles(ids)
        ids.forEach(id => selectedIds.value.delete(id))
        await loadVehicles()
        if (res.skipped?.length || res.missing?.length) {
            const parts: string[] = [`${res.deleted} archived`]
            if (res.skipped?.length) parts.push(`${res.skipped.length} skipped`)
            if (res.missing?.length) parts.push(`${res.missing.length} not found`)
            toast.info('Bulk archive completed', parts.join(' · '))
        } else {
            toast.success(
                'Bulk archive complete',
                `${res.deleted} vehicle${res.deleted === 1 ? '' : 's'} archived.`
            )
        }
    } catch (err: any) {
        toast.error('Bulk archive failed.', err?.data?.message)
    } finally {
        bulkArchiving.value = false
    }
}

const openActionMenu = (v: Vehicle, ev: MouseEvent) => {
    const rect = (ev.currentTarget as HTMLElement).getBoundingClientRect()
    const menuWidth = 180
    const menuMaxHeight = 160
    const left = Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8)
    const wouldOverflow = rect.bottom + menuMaxHeight + 8 > window.innerHeight
    actionMenu.vehicle = v
    actionMenu.x = Math.max(8, left)
    actionMenu.y = wouldOverflow ? rect.top - menuMaxHeight - 6 : rect.bottom + 6
    actionMenu.open = true
}

const closeActionMenu = () => {
    actionMenu.open = false
    actionMenu.vehicle = null
}

const actionView = () => {
    const v = actionMenu.vehicle
    closeActionMenu()
    if (v) {
        detailsVehicle.value = v
        detailsOpen.value = true
    }
}

const actionEdit = () => {
    const v = actionMenu.vehicle
    closeActionMenu()
    if (v) openEditModal(v)
}

const actionArchive = () => {
    const v = actionMenu.vehicle
    closeActionMenu()
    if (v) archiveVehicle(v)
}

onMounted(() => {
    if (import.meta.client) {
        document.addEventListener('click', closeActionMenu)
    }
    loadVehicles()
})

onBeforeUnmount(() => {
    if (import.meta.client) {
        document.removeEventListener('click', closeActionMenu)
    }
})
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

.row-checkbox {
    width: 1rem;
    height: 1rem;
    border-radius: 4px;
    border: 1px solid var(--border-strong);
    background: var(--bg-card);
    accent-color: var(--color-primary);
    cursor: pointer;
    transition: border-color 0.15s ease;
}

.row-checkbox:hover:not(:disabled) {
    border-color: var(--color-primary);
}

.row-checkbox:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.row-checkbox:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px rgb(var(--color-primary-rgb) / 0.2);
}

.bulk-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.625rem 1rem;
    background: var(--color-primary-subtle);
    border-bottom: 1px solid rgb(var(--color-primary-rgb) / 0.2);
}

.bulkbar-enter-active,
.bulkbar-leave-active {
    transition: opacity 0.15s ease, max-height 0.2s ease;
    overflow: hidden;
}

.bulkbar-enter-from,
.bulkbar-leave-to {
    opacity: 0;
    max-height: 0;
}

.bulkbar-enter-to,
.bulkbar-leave-from {
    opacity: 1;
    max-height: 60px;
}
</style>
