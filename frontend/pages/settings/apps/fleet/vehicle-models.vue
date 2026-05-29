<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Vehicle Models</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        Catalog of Make / Model pairs that drives the picker on the Vehicle Create form.
                    </p>
                </div>
                <button v-if="canWrite" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />New model
                </button>
            </header>

            <!-- Search -->
            <section class="glass-card rounded-xl p-4">
                <div class="relative w-full md:w-96">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                    <input v-model="search" type="search" placeholder="Search make, model, body or fuel..."
                        class="form-control pl-9" />
                </div>
            </section>

            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span
                    class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading vehicle models...</span>
            </div>

            <div v-else-if="filtered.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-car text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">
                    {{ models.length === 0 ? 'No models defined yet' : 'No models match' }}
                </h4>
                <p class="text-xs text-(--text-muted) mt-1">
                    {{ models.length === 0
                        ? 'Add a Make + Model entry so it shows up in the Vehicle Create picker.'
                        : 'Adjust your search or clear it.' }}
                </p>
            </div>

            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                <th class="px-4 py-3 font-semibold">Make</th>
                                <th class="px-4 py-3 font-semibold">Model</th>
                                <th class="px-4 py-3 font-semibold hidden md:table-cell">Body type</th>
                                <th class="px-4 py-3 font-semibold hidden md:table-cell">Fuel type</th>
                                <th class="px-4 py-3 font-semibold hidden lg:table-cell">Notes</th>
                                <th class="px-4 py-3 font-semibold text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-color)">
                            <tr v-for="m in paged" :key="m.id" class="hover:bg-(--bg-muted) transition-colors">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="w-8 h-8 rounded-lg bg-(--color-primary-subtle) text-(--color-primary) flex items-center justify-center shrink-0">
                                            <i class="ti ti-car text-sm" />
                                        </span>
                                        <span class="text-xs font-semibold text-(--text-heading)">{{ m.make }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-xs">{{ m.model }}</td>
                                <td class="px-4 py-3 text-xs text-(--text-body) hidden md:table-cell">
                                    {{ m.bodyType || '—' }}
                                </td>
                                <td class="px-4 py-3 text-xs text-(--text-body) hidden md:table-cell">
                                    {{ m.fuelType || '—' }}
                                </td>
                                <td class="px-4 py-3 text-xs text-(--text-muted) hidden lg:table-cell truncate max-w-[240px]">
                                    {{ m.notes || '—' }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button" class="action-trigger"
                                        :class="{ 'action-trigger-open': actionMenu.open && actionMenu.model?.id === m.id }"
                                        title="Actions" @click.stop="openActionMenu(m, $event)">
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

            <!-- Modal -->
            <div v-if="showFormModal"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="glass-card rounded-2xl w-full max-w-md p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                    <header class="flex items-center justify-between mb-5">
                        <h3 class="text-base font-semibold text-(--text-heading)">
                            {{ editing ? 'Edit vehicle model' : 'New vehicle model' }}
                        </h3>
                        <button class="topbar-btn" @click="closeFormModal"><i class="ti ti-x" /></button>
                    </header>

                    <form class="space-y-4" @submit.prevent="saveModel">
                        <div>
                            <label class="form-label form-label-required">Make</label>
                            <input v-model="form.make" type="text" required class="form-control"
                                placeholder="Toyota" />
                        </div>
                        <div>
                            <label class="form-label form-label-required">Model</label>
                            <input v-model="form.model" type="text" required class="form-control"
                                placeholder="Hilux" />
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Body type</label>
                                <input v-model="form.body_type" type="text" class="form-control"
                                    list="vm-body-options" placeholder="Pickup" />
                                <datalist id="vm-body-options">
                                    <option value="Pickup" />
                                    <option value="Sedan" />
                                    <option value="SUV" />
                                    <option value="Van" />
                                    <option value="Hatchback" />
                                    <option value="Truck" />
                                    <option value="Coupe" />
                                    <option value="Wagon" />
                                </datalist>
                            </div>
                            <div>
                                <label class="form-label">Fuel type</label>
                                <input v-model="form.fuel_type" type="text" class="form-control"
                                    list="vm-fuel-options" placeholder="Diesel" />
                                <datalist id="vm-fuel-options">
                                    <option value="Diesel" />
                                    <option value="Gasoline" />
                                    <option value="Hybrid" />
                                    <option value="Electric" />
                                    <option value="LPG" />
                                    <option value="CNG" />
                                </datalist>
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Notes</label>
                            <textarea v-model="form.notes" rows="3" class="form-control"
                                placeholder="Default service intervals, trim levels, anything worth surfacing in the picker." />
                        </div>

                        <div v-if="formError" class="form-error">{{ formError }}</div>

                        <footer class="pt-4 border-t border-(--border-color) flex justify-end gap-2">
                            <button type="button" class="btn btn-ghost text-xs" @click="closeFormModal">Cancel</button>
                            <button type="submit" class="btn btn-primary text-xs" :disabled="saving">
                                <i class="ti ti-device-floppy" />
                                {{ saving ? 'Saving...' : (editing ? 'Save changes' : 'Add model') }}
                            </button>
                        </footer>
                    </form>
                </div>
            </div>

            <!-- Action dropdown -->
            <div v-if="actionMenu.open && actionMenu.model"
                class="fixed z-50 glass-card rounded-lg shadow-(--shadow-lg) bg-(--bg-card) border border-(--border-color) py-1 min-w-[180px]"
                :style="{ top: actionMenu.y + 'px', left: actionMenu.x + 'px' }" @click.stop>
                <button v-if="canWrite" class="action-item" @click="actionEdit">
                    <i class="ti ti-pencil" /> Edit
                </button>
                <template v-if="canDelete">
                    <hr v-if="canWrite" class="my-1 border-(--border-color)" />
                    <button class="action-item action-item-danger" @click="actionDelete">
                        <i class="ti ti-trash" /> Remove
                    </button>
                </template>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import { useFleet, type VehicleModel } from '~/composables/useFleet'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import Pagination from '~/components/Pagination.vue'

const fleet = useFleet()
const authStore = useAuthStore()
const toast = useToast()
const canWrite = computed(() => authStore.hasPermission('fleet.vehicle_models.write'))
const canDelete = computed(() => authStore.hasPermission('fleet.vehicle_models.delete'))

const models = ref<VehicleModel[]>([])
const loading = ref(false)
const search = ref('')

// Same client-side batched load as the operational pages so the catalog
// search/sort feels instant. Catalog is unlikely to exceed 200 entries per
// tenant; 500 is a safe ceiling.
const PAGE_BATCH = 500
const pagination = reactive({ page: 1, limit: 15 })

const showFormModal = ref(false)
const editing = ref<VehicleModel | null>(null)
const saving = ref(false)
const formError = ref<string | null>(null)
const form = reactive({
    make: '',
    model: '',
    body_type: '',
    fuel_type: '',
    notes: '',
})

const actionMenu = reactive({
    open: false,
    x: 0,
    y: 0,
    model: null as VehicleModel | null,
})

const filtered = computed(() => {
    const q = search.value.trim().toLowerCase()
    if (!q) return models.value
    return models.value.filter(m =>
        [m.make, m.model, m.bodyType ?? '', m.fuelType ?? '']
            .some(s => s.toLowerCase().includes(q))
    )
})

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / pagination.limit)))

const paged = computed(() => {
    const start = (pagination.page - 1) * pagination.limit
    return filtered.value.slice(start, start + pagination.limit)
})

watch(search, () => { pagination.page = 1 })

const loadModels = async () => {
    loading.value = true
    try {
        const res = await fleet.getVehicleModels({ limit: PAGE_BATCH })
        models.value = res.data
    } catch (err) {
        console.error('Failed to load vehicle models', err)
        models.value = []
    } finally {
        loading.value = false
    }
}

const resetForm = () => {
    Object.assign(form, { make: '', model: '', body_type: '', fuel_type: '', notes: '' })
    formError.value = null
}

const openCreateModal = () => {
    editing.value = null
    resetForm()
    showFormModal.value = true
}

const openEditModal = (m: VehicleModel) => {
    editing.value = m
    Object.assign(form, {
        make: m.make,
        model: m.model,
        body_type: m.bodyType ?? '',
        fuel_type: m.fuelType ?? '',
        notes: m.notes ?? '',
    })
    formError.value = null
    showFormModal.value = true
}

const closeFormModal = () => {
    if (saving.value) return
    showFormModal.value = false
    editing.value = null
}

const saveModel = async () => {
    saving.value = true
    formError.value = null
    try {
        const payload: Record<string, unknown> = {
            make: form.make.trim(),
            model: form.model.trim(),
            body_type: form.body_type.trim() || null,
            fuel_type: form.fuel_type.trim() || null,
            notes: form.notes.trim() || null,
        }
        if (editing.value) {
            await fleet.updateVehicleModel(editing.value.id, payload)
        } else {
            await fleet.createVehicleModel(payload)
        }
        const wasEditing = !!editing.value
        showFormModal.value = false
        editing.value = null
        await loadModels()
        toast.success(
            wasEditing ? 'Vehicle model updated.' : 'Vehicle model added.',
            `${payload.make} ${payload.model}`
        )
    } catch (err: any) {
        const errors = err?.data?.errors
        if (errors && typeof errors === 'object') {
            formError.value = (Object.values(errors).flat()[0] as string) || 'Validation failed.'
        } else {
            formError.value = err?.data?.message || 'Failed to save vehicle model.'
        }
    } finally {
        saving.value = false
    }
}

const removeModel = async (m: VehicleModel) => {
    const ok = await toast.confirm({
        title: `Remove ${m.make} ${m.model}?`,
        description: 'The entry disappears from the Vehicle Create picker. Existing vehicles keep their free-text make/model values.',
        confirmLabel: 'Remove',
        color: 'danger',
        icon: 'ti-trash',
    })
    if (!ok) return
    try {
        await fleet.deleteVehicleModel(m.id)
        await loadModels()
        toast.success('Vehicle model removed.', `${m.make} ${m.model} is no longer in the picker.`)
    } catch (err: any) {
        toast.error('Failed to remove model.', err?.data?.message)
    }
}

const openActionMenu = (m: VehicleModel, ev: MouseEvent) => {
    const rect = (ev.currentTarget as HTMLElement).getBoundingClientRect()
    const menuWidth = 180
    const menuMaxHeight = 120
    const left = Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8)
    const wouldOverflow = rect.bottom + menuMaxHeight + 8 > window.innerHeight
    actionMenu.model = m
    actionMenu.x = Math.max(8, left)
    actionMenu.y = wouldOverflow ? rect.top - menuMaxHeight - 6 : rect.bottom + 6
    actionMenu.open = true
}

const closeActionMenu = () => {
    actionMenu.open = false
    actionMenu.model = null
}

const actionEdit = () => {
    const m = actionMenu.model
    closeActionMenu()
    if (m) openEditModal(m)
}

const actionDelete = () => {
    const m = actionMenu.model
    closeActionMenu()
    if (m) removeModel(m)
}

onMounted(() => {
    if (import.meta.client) {
        document.addEventListener('click', closeActionMenu)
    }
    loadModels()
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
</style>
