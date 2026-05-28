<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Suppliers</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Vendor directory with payment terms, lead times, and performance ratings.</p>
                </div>
                <button v-if="canWrite" type="button" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />New Supplier
                </button>
            </header>

            <!-- Metrics -->
            <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Total</p>
                    <p class="text-xl font-semibold text-(--text-heading) mt-1">{{ suppliersList.length }}</p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Active</p>
                    <p class="text-xl font-semibold text-(--color-success) mt-1">{{ activeCount }}</p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Avg Rating</p>
                    <p class="text-xl font-semibold text-(--color-warning) mt-1">{{ avgRating }}</p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Avg Lead Time</p>
                    <p class="text-xl font-semibold text-(--color-info) mt-1">{{ avgLeadTime }}d</p>
                </div>
            </section>

            <!-- Filters -->
            <section class="glass-card rounded-xl p-4 flex flex-col md:flex-row gap-3 items-center justify-between">
                <div class="relative w-full md:w-96">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                    <input v-model="search" type="search" placeholder="Search by name, code, email, contact..."
                        class="form-control pl-9" />
                </div>
                <div class="flex gap-2 items-center w-full md:w-auto">
                    <div class="relative flex-1 md:flex-initial">
                        <i class="ti ti-star absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
                        <select v-model="filterRating" class="form-control pl-9 text-xs appearance-none md:w-auto">
                            <option :value="0">Any rating</option>
                            <option :value="3">3+ stars</option>
                            <option :value="4">4+ stars</option>
                            <option :value="5">5 stars</option>
                        </select>
                    </div>
                    <div class="flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1 shrink-0">
                        <button v-for="s in (['all', 'active', 'inactive'] as const)" :key="s" type="button"
                            class="px-2.5 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
                            :class="filterActive === s ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                            @click="filterActive = s">{{ s }}</button>
                    </div>
                </div>
            </section>

            <!-- Loading -->
            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading suppliers...</span>
            </div>

            <!-- Empty -->
            <div v-else-if="filteredList.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-truck-delivery text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No suppliers found</h4>
                <p class="text-xs text-(--text-muted) mt-1">Add a vendor to source purchase orders from.</p>
            </div>

            <!-- Grid view -->
            <section v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <article v-for="s in filteredList" :key="s.id"
                    class="glass-card rounded-2xl p-5 flex flex-col gap-3 group relative overflow-hidden transition-all duration-150 border border-(--border-color) hover:border-(--color-primary)/40">
                    
                    <!-- Glowing shape behind card -->
                    <div class="absolute -right-8 -top-8 w-20 h-20 rounded-full bg-(--color-primary)/10 blur-xl pointer-events-none group-hover:scale-150 transition-transform duration-500" />

                    <div class="space-y-3 relative z-10 flex-1 flex flex-col">
                        <header class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p v-if="s.code" class="text-xxs text-(--text-muted) font-mono uppercase tracking-wider">{{ s.code }}</p>
                                <h3 class="text-sm font-semibold text-(--text-heading) mt-0.5 truncate group-hover:text-(--color-primary) transition-colors">{{ s.name }}</h3>
                                <p v-if="s.contactName" class="text-xxs text-(--text-muted) mt-0.5 truncate font-sans">{{ s.contactName }}</p>
                            </div>
                            <Badge :variant="s.isActive ? 'success' : 'secondary'" class="shrink-0">
                                {{ s.isActive ? 'Active' : 'Inactive' }}
                            </Badge>
                        </header>

                        <div class="text-xs space-y-1.5 border-t border-b border-(--border-color)/50 py-3 my-1">
                            <div v-if="s.email" class="flex items-center gap-2 text-(--text-body)">
                                <i class="ti ti-mail text-(--text-muted)" />
                                <span class="truncate font-mono text-xxs">{{ s.email }}</span>
                            </div>
                            <div v-if="s.phone" class="flex items-center gap-2 text-(--text-body)">
                                <i class="ti ti-phone text-(--text-muted)" />
                                <span class="font-mono text-xxs">{{ s.phone }}</span>
                            </div>
                            <div v-if="s.website" class="flex items-center gap-2 text-(--text-body)">
                                <i class="ti ti-world text-(--text-muted)" />
                                <a :href="s.website" target="_blank" rel="noopener"
                                    class="text-(--color-primary) hover:underline truncate text-xxs">
                                    {{ s.website }}
                                </a>
                            </div>
                        </div>

                        <div class="grid grid-cols-3 gap-2 text-center mt-auto">
                            <div>
                                <p class="text-xxs text-(--text-muted) uppercase font-bold tracking-wider">Rating</p>
                                <div class="text-sm mt-1">
                                    <template v-if="s.rating">
                                        <span v-for="i in 5" :key="i"
                                            :class="i <= s.rating! ? 'text-(--color-warning)' : 'text-(--text-muted)/30'">★</span>
                                    </template>
                                    <span v-else class="text-(--text-muted) text-xs">—</span>
                                </div>
                            </div>
                            <div>
                                <p class="text-xxs text-(--text-muted) uppercase font-bold tracking-wider">Lead Time</p>
                                <p class="text-sm font-semibold text-(--text-heading) mt-1">
                                    {{ s.leadTimeDays !== null ? `${s.leadTimeDays}d` : '—' }}
                                </p>
                            </div>
                            <div>
                                <p class="text-xxs text-(--text-muted) uppercase font-bold tracking-wider">Terms</p>
                                <p class="text-xs font-semibold text-(--text-heading) mt-1 truncate">
                                    {{ s.paymentTerms || '—' }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <footer class="mt-2 pt-2 border-t border-(--border-color)/50 flex items-center justify-end gap-1.5 relative z-10">
                        <button v-if="canWrite" type="button" class="action-btn" title="Edit"
                            @click="openEditModal(s)">
                            <i class="ti ti-pencil" />
                        </button>
                        <button v-if="canDelete" type="button" class="action-btn action-btn-danger" title="Archive"
                            @click="confirmDelete(s)">
                            <i class="ti ti-archive" />
                        </button>
                    </footer>
                </article>
            </section>
        </div>

        <!-- Form Modal -->
        <div v-if="showFormModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-xl bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">{{ isEdit ? 'Edit Supplier' : 'New Supplier' }}</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="saveSupplier">
                    <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
                        <div class="grid grid-cols-3 gap-3">
                            <div class="space-y-1 col-span-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Code</label>
                                <input v-model="form.code" type="text" maxlength="40" placeholder="VEN-01"
                                    class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1 col-span-2">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Name *</label>
                                <input v-model="form.name" type="text" required maxlength="255" placeholder="Acme Components Ltd."
                                    class="form-control text-xs" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Contact Name</label>
                                <input v-model="form.contact_name" type="text" placeholder="Jane Doe"
                                    class="form-control text-xs" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Email</label>
                                <input v-model="form.email" type="email" placeholder="orders@acme.test"
                                    class="form-control text-xs" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Phone</label>
                                <input v-model="form.phone" type="tel" maxlength="50" placeholder="+855 12 345 678"
                                    class="form-control text-xs" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Website</label>
                                <input v-model="form.website" type="url" placeholder="https://acme.test"
                                    class="form-control text-xs" />
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Address</label>
                            <textarea v-model="form.address" rows="2" maxlength="1000" placeholder="Mailing address"
                                class="form-control text-xs resize-none" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Tax ID</label>
                                <input v-model="form.tax_id" type="text" maxlength="60" placeholder="K001-123456"
                                    class="form-control text-xs" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Payment Terms</label>
                                <input v-model="form.payment_terms" type="text" maxlength="60" placeholder="Net 30"
                                    class="form-control text-xs" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Lead Time (days)</label>
                                <input v-model.number="form.lead_time_days" type="number" min="0" max="365" placeholder="14"
                                    class="form-control text-xs" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Rating (1-5)</label>
                                <select v-model.number="form.rating" class="form-control text-xs">
                                    <option :value="null">Unrated</option>
                                    <option v-for="n in 5" :key="n" :value="n">{{ n }} ★</option>
                                </select>
                            </div>
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
                            {{ isEdit ? 'Save Changes' : 'Create Supplier' }}
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Delete Modal -->
        <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Archive Supplier</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="deleteTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5">
                    <p class="text-xs text-(--text-body)">
                        Archive supplier <span class="font-semibold text-(--text-heading)">{{ deleteTarget.name }}</span>?
                    </p>
                    <p class="text-xxs text-(--text-muted) mt-2">
                        Archiving will fail if the supplier currently has any open purchase orders.
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
import { useAuthStore } from '~/stores/auth'
import type { Supplier, CreateSupplierPayload } from '~/types/inventory'

definePageMeta({ breadcrumb: 'Suppliers' })

const inventory = useInventory()
const toast = useToast()
const authStore = useAuthStore()

const canWrite = computed(() => authStore.hasPermission('inventory.suppliers.write'))
const canDelete = computed(() => authStore.hasPermission('inventory.suppliers.delete'))

const loading = ref(false)
const submitting = ref(false)
const deleting = ref(false)

const suppliersList = ref<Supplier[]>([])

const search = ref('')
const filterActive = ref<'all' | 'active' | 'inactive'>('all')
const filterRating = ref<number>(0)

const activeCount = computed(() => suppliersList.value.filter(s => s.isActive).length)
const avgRating = computed(() => {
    const rated = suppliersList.value.filter(s => s.rating !== null) as (Supplier & { rating: number })[]
    if (!rated.length) return '—'
    return (rated.reduce((a, b) => a + b.rating, 0) / rated.length).toFixed(1)
})
const avgLeadTime = computed(() => {
    const withLead = suppliersList.value.filter(s => s.leadTimeDays !== null) as (Supplier & { leadTimeDays: number })[]
    if (!withLead.length) return 0
    return Math.round(withLead.reduce((a, b) => a + b.leadTimeDays, 0) / withLead.length)
})

const filteredList = computed(() => suppliersList.value.filter(s => {
    const q = search.value.trim().toLowerCase()
    const haystack = [s.name, s.code, s.email, s.contactName].filter(Boolean).join(' ').toLowerCase()
    const matchSearch = !q || haystack.includes(q)
    const matchActive =
        filterActive.value === 'all' ||
        (filterActive.value === 'active' && s.isActive) ||
        (filterActive.value === 'inactive' && !s.isActive)
    const matchRating = filterRating.value === 0 || (s.rating !== null && s.rating >= filterRating.value)
    return matchSearch && matchActive && matchRating
}))

const showFormModal = ref(false)
const isEdit = ref(false)
const editId = ref<string | null>(null)
const form = reactive<CreateSupplierPayload>({
    code: null,
    name: '',
    contact_name: null,
    email: null,
    phone: null,
    address: null,
    website: null,
    tax_id: null,
    payment_terms: null,
    lead_time_days: null,
    rating: null,
    is_active: true,
    notes: null,
})

const resetForm = () => {
    form.code = null
    form.name = ''
    form.contact_name = null
    form.email = null
    form.phone = null
    form.address = null
    form.website = null
    form.tax_id = null
    form.payment_terms = null
    form.lead_time_days = null
    form.rating = null
    form.is_active = true
    form.notes = null
}

const openCreateModal = () => {
    isEdit.value = false
    editId.value = null
    resetForm()
    showFormModal.value = true
}

const openEditModal = (s: Supplier) => {
    isEdit.value = true
    editId.value = s.id
    form.code = s.code
    form.name = s.name
    form.contact_name = s.contactName
    form.email = s.email
    form.phone = s.phone
    form.address = s.address
    form.website = s.website
    form.tax_id = s.taxId
    form.payment_terms = s.paymentTerms
    form.lead_time_days = s.leadTimeDays
    form.rating = s.rating
    form.is_active = s.isActive
    form.notes = s.notes
    showFormModal.value = true
}

const saveSupplier = async () => {
    submitting.value = true
    try {
        if (isEdit.value && editId.value) {
            const res = await inventory.suppliers.update(editId.value, form)
            const idx = suppliersList.value.findIndex(s => s.id === editId.value)
            if (idx !== -1) suppliersList.value[idx] = res.data
            toast.success('Supplier updated', form.name)
        } else {
            const res = await inventory.suppliers.create(form)
            suppliersList.value.unshift(res.data)
            toast.success('Supplier created', form.name)
        }
        showFormModal.value = false
    } catch (err: any) {
        toast.error('Save failed', err?.data?.message)
    } finally {
        submitting.value = false
    }
}

const deleteTarget = ref<Supplier | null>(null)
const confirmDelete = (s: Supplier) => { deleteTarget.value = s }
const onConfirmDelete = async () => {
    if (!deleteTarget.value) return
    deleting.value = true
    try {
        await inventory.suppliers.destroy(deleteTarget.value.id)
        suppliersList.value = suppliersList.value.filter(s => s.id !== deleteTarget.value!.id)
        toast.success('Supplier archived', deleteTarget.value.name)
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
        const res = await inventory.suppliers.list({ limit: 100 })
        suppliersList.value = res.data
    } catch (err: any) {
        toast.error('Failed to load suppliers', err?.data?.message)
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
