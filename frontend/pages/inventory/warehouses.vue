<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Warehouses</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Stocking locations with capacity tracking. Active warehouses receive stock movements and PO receipts.</p>
                </div>
                <button v-if="canWrite" type="button" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />New Warehouse
                </button>
            </header>

            <!-- Metrics -->
            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="glass-card rounded-2xl p-4 space-y-2 col-span-1">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Total</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-primary flex items-center justify-center">
                            <i class="ti ti-building-warehouse text-sm" />
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ totalCountAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">All warehouses</p>
                </div>

                <div class="glass-card rounded-2xl p-4 space-y-2 col-span-1">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Active</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-success flex items-center justify-center">
                            <i class="ti ti-circle-check text-sm" />
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ activeCountAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">In service</p>
                </div>

                <div class="glass-card rounded-2xl p-4 space-y-2 col-span-1">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Capacity Total</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-info flex items-center justify-center">
                            <i class="ti ti-stack-2 text-sm" />
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ capacityNumericAnim.toLocaleString() }}</p>
                    <p class="text-xxs text-(--text-muted)">Combined storage units</p>
                </div>

                <div class="glass-card rounded-2xl p-4 space-y-2 col-span-1">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Countries</span>
                        <span class="w-7 h-7 rounded-lg badge-soft-warning flex items-center justify-center">
                            <i class="ti ti-flag text-sm" />
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ countryCountAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">Geographies covered</p>
                </div>
            </section>

            <!-- Filters -->
            <section class="glass-card rounded-xl p-4 flex flex-col md:flex-row gap-3 items-center justify-between">
                <div class="relative w-full md:w-96">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                    <input v-model="search" type="search" placeholder="Search by name, code, or city..."
                        class="form-control pl-9" />
                </div>
                <div class="flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1 shrink-0">
                    <button v-for="s in (['all', 'active', 'inactive'] as const)" :key="s" type="button"
                        class="px-2.5 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
                        :class="filterActive === s ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                        @click="filterActive = s">{{ s }}</button>
                </div>
            </section>

            <!-- Loading -->
            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading warehouses...</span>
            </div>

            <!-- Empty -->
            <div v-else-if="filteredList.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-building-warehouse text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No warehouses found</h4>
                <p class="text-xs text-(--text-muted) mt-1">Create a warehouse to start tracking stock.</p>
            </div>

            <!-- Table -->
            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                <th class="px-4 py-3 font-semibold">Warehouse</th>
                                <th class="px-4 py-3 font-semibold hidden md:table-cell">Location</th>
                                <th class="px-4 py-3 font-semibold hidden lg:table-cell">Manager</th>
                                <th class="px-4 py-3 font-semibold text-right hidden md:table-cell">Capacity</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold text-right w-32">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-color)">
                            <tr v-for="w in filteredList" :key="w.id"
                                class="hover:bg-(--bg-muted) transition-colors">
                                <td class="px-4 py-3">
                                    <p class="text-sm font-semibold text-(--text-heading) leading-tight">{{ w.name }}</p>
                                    <p class="text-xxs text-(--text-muted) font-mono mt-0.5">{{ w.code }}</p>
                                </td>
                                <td class="px-4 py-3 hidden md:table-cell">
                                    <p v-if="w.city || w.country">
                                        {{ [w.city, w.country].filter(Boolean).join(', ') }}
                                    </p>
                                    <p v-else-if="w.location">{{ w.location }}</p>
                                    <p v-else class="text-(--text-muted)">—</p>
                                </td>
                                <td class="px-4 py-3 hidden lg:table-cell">
                                    <span v-if="w.manager">{{ w.manager.name }}</span>
                                    <span v-else class="text-(--text-muted)">Unassigned</span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono hidden md:table-cell">
                                    <span v-if="w.capacity !== null" class="font-semibold">{{ w.capacity.toLocaleString() }}</span>
                                    <span v-else class="text-(--text-muted)">—</span>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge :variant="w.isActive ? 'success' : 'secondary'">
                                        {{ w.isActive ? 'Active' : 'Inactive' }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <button v-if="canWrite" type="button" class="action-btn" title="Edit"
                                            @click="openEditModal(w)">
                                            <i class="ti ti-pencil" />
                                        </button>
                                        <button v-if="canDelete" type="button"
                                            class="action-btn action-btn-danger" title="Archive"
                                            @click="confirmDelete(w)">
                                            <i class="ti ti-archive" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Form Modal -->
        <div v-if="showFormModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-lg bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">{{ isEdit ? 'Edit Warehouse' : 'New Warehouse' }}</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="saveWarehouse">
                    <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
                        <div class="grid grid-cols-3 gap-3">
                            <div class="space-y-1 col-span-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Code *</label>
                                <input v-model="form.code" type="text" required maxlength="40" placeholder="WH-01"
                                    class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1 col-span-2">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Name *</label>
                                <input v-model="form.name" type="text" required maxlength="255" placeholder="Main Distribution Center"
                                    class="form-control text-xs" />
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Address Line</label>
                            <input v-model="form.address_line" type="text" maxlength="255" placeholder="Street, building, unit..."
                                class="form-control text-xs" />
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div class="space-y-1 col-span-2">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">City</label>
                                <input v-model="form.city" type="text" maxlength="120" placeholder="Phnom Penh"
                                    class="form-control text-xs" />
                            </div>
                            <div class="space-y-1 col-span-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Country (ISO-2)</label>
                                <input v-model="form.country" type="text" maxlength="2" placeholder="KH"
                                    class="form-control text-xs uppercase" />
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Capacity (units)</label>
                            <input v-model.number="form.capacity" type="number" min="0" placeholder="e.g. 50000"
                                class="form-control text-xs" />
                            <p class="text-xxs text-(--text-muted)">Soft logical limit — not enforced by stock posts.</p>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Notes</label>
                            <textarea v-model="form.notes" rows="3" maxlength="2000" placeholder="Internal notes..."
                                class="form-control text-xs resize-none" />
                        </div>

                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-(--border-color)" />
                            <span class="text-xs">Active</span>
                        </label>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showFormModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="submitting">
                            <i v-if="submitting" class="ti ti-loader-2 animate-spin" />
                            {{ isEdit ? 'Save Changes' : 'Create Warehouse' }}
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Delete Modal -->
        <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Archive Warehouse</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="deleteTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5">
                    <p class="text-xs text-(--text-body)">
                        Archive warehouse <span class="font-semibold text-(--text-heading)">{{ deleteTarget.name }}</span>?
                    </p>
                    <p class="text-xxs text-(--text-muted) mt-2">
                        Archiving will fail if the warehouse currently holds any on-hand stock.
                    </p>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="deleteTarget = null">Cancel</button>
                    <button type="button" class="btn btn-danger text-xs" :disabled="deleting" @click="onConfirmDelete">
                        <i v-if="deleting" class="ti ti-loader-2 animate-spin" />
                        Archive
                    </button>
                </footer>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useInventory } from '~/composables/useInventory'
import { useToast } from '~/composables/useToast'
import { useCountUp } from '~/composables/useCountUp'
import { useAuthStore } from '~/stores/auth'
import type { Warehouse, CreateWarehousePayload } from '~/types/inventory'

definePageMeta({ breadcrumb: 'Warehouses' })

const inventory = useInventory()
const toast = useToast()
const authStore = useAuthStore()

const canWrite = computed(() => authStore.hasPermission('inventory.warehouse.write'))
const canDelete = computed(() => authStore.hasPermission('inventory.warehouse.delete'))

const loading = ref(false)
const submitting = ref(false)
const deleting = ref(false)

const warehousesList = ref<Warehouse[]>([])

const search = ref('')
const filterActive = ref<'all' | 'active' | 'inactive'>('all')

const activeCount = computed(() => warehousesList.value.filter(w => w.isActive).length)
const capacityNumeric = computed(() =>
    warehousesList.value.reduce((sum, w) => sum + (w.capacity ?? 0), 0)
)
const capacitySum = computed(() => capacityNumeric.value.toLocaleString())
const countryCount = computed(
    () => new Set(warehousesList.value.map(w => w.country).filter(Boolean)).size
)

// Animated KPI counters (RAF-driven, ease-out cubic).
const totalCountAnim = useCountUp(() => warehousesList.value.length)
const activeCountAnim = useCountUp(() => activeCount.value)
const capacityNumericAnim = useCountUp(() => capacityNumeric.value)
const countryCountAnim = useCountUp(() => countryCount.value)

const filteredList = computed(() => warehousesList.value.filter(w => {
    const q = search.value.trim().toLowerCase()
    const haystack = [w.name, w.code, w.city, w.location].filter(Boolean).join(' ').toLowerCase()
    const matchSearch = !q || haystack.includes(q)
    const matchActive =
        filterActive.value === 'all' ||
        (filterActive.value === 'active' && w.isActive) ||
        (filterActive.value === 'inactive' && !w.isActive)
    return matchSearch && matchActive
}))

const showFormModal = ref(false)
const isEdit = ref(false)
const editId = ref<string | null>(null)
const form = reactive<CreateWarehousePayload>({
    code: '',
    name: '',
    location: null,
    manager_id: null,
    address_line: null,
    city: null,
    country: null,
    capacity: null,
    is_active: true,
    notes: null,
})

const resetForm = () => {
    form.code = ''
    form.name = ''
    form.location = null
    form.manager_id = null
    form.address_line = null
    form.city = null
    form.country = null
    form.capacity = null
    form.is_active = true
    form.notes = null
}

const openCreateModal = () => {
    isEdit.value = false
    editId.value = null
    resetForm()
    showFormModal.value = true
}

const openEditModal = (w: Warehouse) => {
    isEdit.value = true
    editId.value = w.id
    form.code = w.code
    form.name = w.name
    form.location = w.location
    form.manager_id = w.managerId
    form.address_line = w.addressLine
    form.city = w.city
    form.country = w.country
    form.capacity = w.capacity
    form.is_active = w.isActive
    form.notes = w.notes
    showFormModal.value = true
}

const saveWarehouse = async () => {
    submitting.value = true
    try {
        const payload: CreateWarehousePayload = {
            ...form,
            country: form.country ? form.country.toUpperCase() : null,
        }
        if (isEdit.value && editId.value) {
            const res = await inventory.warehouses.update(editId.value, payload)
            const idx = warehousesList.value.findIndex(w => w.id === editId.value)
            if (idx !== -1) warehousesList.value[idx] = res.data
            toast.success('Warehouse updated', payload.name)
        } else {
            const res = await inventory.warehouses.create(payload)
            warehousesList.value.unshift(res.data)
            toast.success('Warehouse created', payload.name)
        }
        showFormModal.value = false
    } catch (err: any) {
        toast.error('Save failed', err?.data?.message)
    } finally {
        submitting.value = false
    }
}

const deleteTarget = ref<Warehouse | null>(null)
const confirmDelete = (w: Warehouse) => { deleteTarget.value = w }
const onConfirmDelete = async () => {
    if (!deleteTarget.value) return
    deleting.value = true
    try {
        await inventory.warehouses.destroy(deleteTarget.value.id)
        warehousesList.value = warehousesList.value.filter(w => w.id !== deleteTarget.value!.id)
        toast.success('Warehouse archived', deleteTarget.value.name)
        deleteTarget.value = null
    } catch (err: any) {
        toast.error('Archive failed', err?.data?.message)
    } finally {
        deleting.value = false
    }
}

const load = async () => {
    loading.value = true
    try {
        const res = await inventory.warehouses.list({ limit: 100 })
        warehousesList.value = res.data
    } catch (err: any) {
        toast.error('Failed to load warehouses', err?.data?.message)
    } finally {
        loading.value = false
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
</style>
