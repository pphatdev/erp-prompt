<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Leads & Prospects</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Raw customer inquiries and intake contacts. Qualify them to convert into Opportunities.</p>
                </div>
                <button type="button" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />New Lead
                </button>
            </header>

            <!-- Metrics -->
            <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Total Active</p>
                    <p class="text-xl font-semibold text-(--text-heading) mt-1">{{ leadsList.length }}</p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">New Inquiries</p>
                    <p class="text-xl font-semibold text-(--color-info) mt-1">{{ newCount }}</p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">In Discussion</p>
                    <p class="text-xl font-semibold text-(--color-warning) mt-1">{{ contactedCount }}</p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Qualified Rate</p>
                    <p class="text-xl font-semibold text-(--color-success) mt-1">{{ qualifiedRate }}%</p>
                </div>
            </section>

            <!-- Filters -->
            <section class="glass-card rounded-xl p-4 flex flex-col md:flex-row gap-3 items-center justify-between">
                <div class="relative w-full md:w-96">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                    <input v-model="search" type="search" placeholder="Search leads by title or source..."
                        class="form-control pl-9" />
                </div>
                <div class="flex gap-2 items-center w-full md:w-auto">
                    <div class="flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1 shrink-0">
                        <button v-for="s in (['all', 'new', 'contacted', 'qualified', 'unqualified'] as const)" :key="s" type="button"
                            class="px-2.5 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
                            :class="filterStatus === s ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                            @click="filterStatus = s">{{ s }}</button>
                    </div>
                    <!-- View toggle (grid / list) — persisted in localStorage. -->
                    <div class="flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1 shrink-0">
                        <button v-for="v in (['grid', 'list'] as const)" :key="v" type="button"
                            class="px-2 py-1 rounded text-xxs inline-flex items-center gap-1 transition-colors"
                            :class="viewMode === v ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                            :title="v === 'grid' ? 'Grid view' : 'List view'"
                            @click="setViewMode(v)">
                            <i :class="['ti text-base', v === 'grid' ? 'ti-layout-grid' : 'ti-list-details']" />
                        </button>
                    </div>
                </div>
            </section>

            <!-- Loading -->
            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading leads...</span>
            </div>

            <!-- Empty -->
            <div v-else-if="filteredLeads.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-address-book-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No leads found</h4>
                <p class="text-xs text-(--text-muted) mt-1">Start by capturing a new prospect or lead inquiry.</p>
            </div>

            <!-- Grid view -->
            <section v-else-if="viewMode === 'grid'" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <article v-for="l in filteredLeads" :key="l.id" class="glass-card rounded-2xl p-5 flex flex-col gap-3 group">
                    <header class="flex items-start justify-between gap-3">
                        <div>
                            <span :class="sourceBadgeClass(l.source)" class="text-xxs px-1.5 py-0.5 rounded font-bold uppercase tracking-wide">
                                {{ l.source || 'Direct' }}
                            </span>
                            <h3 class="text-sm font-semibold text-(--text-heading) mt-2 group-hover:text-(--color-primary) transition-colors">
                                {{ l.fullName || l.title || 'Unnamed lead' }}
                            </h3>
                            <p v-if="l.email" class="text-xxs text-(--text-muted) mt-0.5 truncate">{{ l.email }}</p>
                            <p class="text-xxs text-(--text-muted) mt-0.5 flex items-center gap-1">
                                <i class="ti ti-calendar" /> Captured {{ formatDate(l.createdAt) }}
                            </p>
                        </div>
                        <Badge :variant="crmBadgeVariant(l.status)">{{ l.status }}</Badge>
                    </header>

                    <div class="text-xs space-y-1.5 border-t border-b border-(--border-color) py-3 my-1">
                        <div class="flex justify-between items-center">
                            <span class="text-xxs text-(--text-muted) uppercase font-bold tracking-wider">Est. Value</span>
                            <span class="font-semibold text-(--text-heading)">{{ l.estimatedValue ? formatCurrency(l.estimatedValue) : 'TBD' }}</span>
                        </div>
                        <div v-if="l.customer" class="flex justify-between items-center">
                            <span class="text-xxs text-(--text-muted) uppercase font-bold tracking-wider">Linked Account</span>
                            <span class="text-(--color-primary) font-medium">{{ l.customer.name }}</span>
                        </div>
                    </div>

                    <footer class="mt-auto pt-1 flex items-center justify-between gap-2">
                        <div class="flex gap-1.5">
                            <button v-if="l.status !== 'qualified' && l.status !== 'unqualified'" type="button"
                                class="btn btn-ghost text-xxs text-(--color-success) py-1 px-2 border border-(--color-success)/30 hover:bg-(--color-success-subtle)"
                                @click="openQualifyModal(l)">
                                <i class="ti ti-circle-check" /> Qualify Lead
                            </button>
                            <span v-else class="text-xxs text-(--text-muted) flex items-center gap-1 font-mono uppercase">
                                <i :class="l.status === 'qualified' ? 'ti ti-circle-check-filled text-(--color-success)' : 'ti ti-circle-x-filled text-(--color-danger)'" />
                                {{ l.status }}
                            </span>
                        </div>

                        <div class="flex gap-1">
                            <button type="button" class="action-btn" title="Edit Lead" @click="openEditModal(l)">
                                <i class="ti ti-pencil" />
                            </button>
                            <button type="button" class="action-btn action-btn-danger" title="Delete" @click="confirmDelete(l)">
                                <i class="ti ti-trash" />
                            </button>
                        </div>
                    </footer>
                </article>
            </section>

            <!-- List view -->
            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="bg-(--bg-muted) text-xxs uppercase tracking-widest font-bold text-(--text-muted)">
                            <tr>
                                <th class="text-left px-4 py-3">Lead</th>
                                <th class="text-left px-2 py-3 hidden md:table-cell">Contact</th>
                                <th class="text-left px-2 py-3 hidden lg:table-cell">Type</th>
                                <th class="text-left px-2 py-3 hidden lg:table-cell">Source</th>
                                <th class="text-right px-2 py-3 hidden md:table-cell">Est. Value</th>
                                <th class="text-left px-2 py-3">Status</th>
                                <th class="text-right px-4 py-3 w-32">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="l in filteredLeads" :key="l.id"
                                class="border-t border-(--border-color)/60 hover:bg-(--bg-muted)/40 transition-colors">
                                <td class="px-4 py-3">
                                    <p class="text-sm font-semibold text-(--text-heading) leading-tight">
                                        {{ l.fullName || l.title || 'Unnamed lead' }}
                                    </p>
                                    <p class="text-xxs text-(--text-muted) mt-0.5 flex items-center gap-1">
                                        <i class="ti ti-calendar" />{{ formatDate(l.createdAt) }}
                                    </p>
                                </td>
                                <td class="px-2 py-3 hidden md:table-cell">
                                    <p v-if="l.email" class="font-mono">{{ l.email }}</p>
                                    <p v-if="l.phone" class="text-xxs text-(--text-muted) font-mono mt-0.5">{{ l.phone }}</p>
                                    <p v-if="!l.email && !l.phone" class="text-(--text-muted)">—</p>
                                </td>
                                <td class="px-2 py-3 hidden lg:table-cell">
                                    <span class="text-xxs px-1.5 py-0.5 rounded font-bold uppercase tracking-wide badge-soft-secondary">
                                        {{ l.customerType || 'business' }}
                                    </span>
                                </td>
                                <td class="px-2 py-3 hidden lg:table-cell">
                                    <span :class="sourceBadgeClass(l.source)" class="text-xxs px-1.5 py-0.5 rounded font-bold uppercase tracking-wide">
                                        {{ l.source || 'Direct' }}
                                    </span>
                                </td>
                                <td class="px-2 py-3 text-right font-mono hidden md:table-cell">
                                    <span v-if="l.estimatedValue" class="font-semibold text-(--text-heading)">
                                        {{ formatCurrency(l.estimatedValue) }}
                                    </span>
                                    <span v-else class="text-(--text-muted)">TBD</span>
                                </td>
                                <td class="px-2 py-3">
                                    <Badge :variant="crmBadgeVariant(l.status)">{{ l.status }}</Badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <button v-if="l.status !== 'qualified' && l.status !== 'unqualified'" type="button"
                                            class="btn btn-ghost text-xxs text-(--color-success) py-0.5 px-2 border border-(--color-success)/30 hover:bg-(--color-success-subtle)"
                                            title="Qualify lead" @click="openQualifyModal(l)">
                                            <i class="ti ti-circle-check" />
                                        </button>
                                        <button type="button" class="action-btn" title="Edit lead" @click="openEditModal(l)">
                                            <i class="ti ti-pencil" />
                                        </button>
                                        <button type="button" class="action-btn action-btn-danger" title="Delete" @click="confirmDelete(l)">
                                            <i class="ti ti-trash" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Lead Form Modal (Create / Edit) -->
        <div v-if="showFormModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">{{ isEdit ? 'Edit Lead' : 'Capture New Lead' }}</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="saveLead">
                    <div class="p-5 space-y-4">
                        <!-- Person identity -->
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">First Name *</label>
                                <input v-model="form.first_name" type="text" placeholder="e.g. Anna" required class="form-control text-xs" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Last Name *</label>
                                <input v-model="form.last_name" type="text" placeholder="e.g. Park" required class="form-control text-xs" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Email *</label>
                                <input v-model="form.email" type="email" placeholder="anna@acme.test" required class="form-control text-xs" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Phone *</label>
                                <input v-model="form.phone" type="tel" placeholder="+855 12 345 678" required class="form-control text-xs" />
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Customer Type *</label>
                            <select v-model="form.customer_type" class="form-control text-xs" required>
                                <option value="individual">Individual</option>
                                <option value="business">Business</option>
                                <option value="tenant">Tenant (SaaS customer)</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Address *</label>
                            <textarea v-model="form.address" rows="2" placeholder="Mailing / site address" required class="form-control text-xs resize-none" />
                        </div>

                        <!-- Deal-level optional fields -->
                        <div class="grid grid-cols-2 gap-3 pt-3 border-t border-(--border-color)">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Est. Value (USD)</label>
                                <input v-model.number="form.estimated_value" type="number" step="0.01" placeholder="e.g. 5000" class="form-control text-xs" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Inquiry Source</label>
                                <select v-model="form.source" class="form-control text-xs">
                                    <option value="Website">Website</option>
                                    <option value="Referral">Referral</option>
                                    <option value="Cold Outreach">Cold Outreach</option>
                                    <option value="Partner">Partner</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Link Existing Customer (Optional)</label>
                            <select v-model="form.customer_id" class="form-control text-xs">
                                <option :value="null">-- No associated customer --</option>
                                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div v-if="isEdit" class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Status</label>
                            <select v-model="form.status" class="form-control text-xs">
                                <option value="new">New</option>
                                <option value="contacted">Contacted</option>
                                <option value="unqualified">Unqualified</option>
                            </select>
                        </div>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showFormModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="submitting">
                            <i v-if="submitting" class="ti ti-loader-2 animate-spin" />
                            {{ isEdit ? 'Save Changes' : 'Capture Lead' }}
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Lead Qualification Modal (Atomic Conversion) -->
        <div v-if="showQualifyModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-lg bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <div>
                        <h3 class="font-semibold text-sm">Qualify & Convert Lead</h3>
                        <p class="text-xxs text-(--text-muted) mt-0.5">Converts this inquiry into an active B2B Opportunity.</p>
                    </div>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showQualifyModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="qualifyLead">
                    <div class="p-5 space-y-4">
                        <section class="p-3 bg-(--bg-muted) border border-(--border-color) rounded-xl space-y-2">
                            <p class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Qualified Prospect:</p>
                            <h4 class="text-xs font-semibold text-(--text-heading)">{{ qualifyTarget?.title }}</h4>
                            <p class="text-xxs text-(--text-muted)">Intake value: {{ qualifyTarget?.estimatedValue ? formatCurrency(qualifyTarget.estimatedValue) : 'TBD' }}</p>
                        </section>

                        <!-- Customer reference linking -->
                        <div class="space-y-2">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider block">Customer B2B Account (Optional)</label>
                            <select v-model="qualifyForm.customer_id" class="form-control text-xs">
                                <option :value="null">-- No associated customer (defer to Quotation Won) --</option>
                                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <p class="text-xxs text-(--text-muted) mt-1">
                                If no customer is linked, creation will be deferred until the Quotation is marked as Won.
                            </p>
                        </div>

                        <!-- Opportunity details -->
                        <div class="border-t border-(--border-color) pt-4 space-y-3">
                            <h4 class="text-xxs font-bold text-(--text-heading) uppercase tracking-wider">Opportunity Pipeline Details</h4>
                            
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase">Opportunity / Deal Name</label>
                                <input v-model="qualifyForm.title" type="text" required placeholder="e.g. Enterprise ERP Setup - Acme Corp" class="form-control text-xs" />
                            </div>

                            <div class="grid grid-cols-2 gap-3">
                                <div class="space-y-1">
                                    <label class="text-xxs font-bold text-(--text-muted) uppercase">Est. Value (USD)</label>
                                    <input v-model.number="qualifyForm.estimated_value" type="number" required placeholder="e.g. 7500" class="form-control text-xs" />
                                </div>
                                <div class="space-y-1">
                                    <label class="text-xxs font-bold text-(--text-muted) uppercase">Projected Close Date</label>
                                    <input v-model="qualifyForm.projected_close_date" type="date" class="form-control text-xs" />
                                </div>
                            </div>
                        </div>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showQualifyModal = false">Cancel</button>
                        <button type="submit" class="btn btn-success text-xs text-white" :disabled="qualifying">
                            <i v-if="qualifying" class="ti ti-loader-2 animate-spin" />
                            Qualify & Convert
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Delete Modal -->
        <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3>Archive Lead</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="deleteTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5">
                    <p class="text-xs text-(--text-body)">Archive lead <span class="font-semibold text-(--text-heading)">{{ deleteTarget.title }}</span>?</p>
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
import { computed, onMounted, ref, reactive } from 'vue'
import { useCrm, crmBadgeVariant } from '~/composables/useCrm'
import { useSales } from '~/composables/useSales'
import { useToast } from '~/composables/useToast'
import { useValidation } from '~/composables/useValidation'
import type { Lead, CreateLeadPayload, QualifyLeadPayload } from '~/types/crm'
import type { CustomerLite } from '~/types/sales'

const crm = useCrm()
const sales = useSales()
const toast = useToast()
const { normalizePhoneNumber, normalizeEmail } = useValidation()

const loading = ref(false)
const submitting = ref(false)
const qualifying = ref(false)
const deleting = ref(false)

const leadsList = ref<Lead[]>([])
const customers = ref<CustomerLite[]>([])

const search = ref('')
const filterStatus = ref<'all' | 'new' | 'contacted' | 'qualified' | 'unqualified'>('all')

// View toggle — persisted in localStorage so the user's preference survives
// reloads and tenant switches.
type LeadsViewMode = 'grid' | 'list'
const VIEW_STORAGE_KEY = 'crm.leads.view'
const viewMode = ref<LeadsViewMode>(
    (typeof window !== 'undefined' && (localStorage.getItem(VIEW_STORAGE_KEY) as LeadsViewMode)) || 'grid'
)
const setViewMode = (mode: LeadsViewMode) => {
    viewMode.value = mode
    if (typeof window !== 'undefined') localStorage.setItem(VIEW_STORAGE_KEY, mode)
}

// Metrics
const newCount = computed(() => leadsList.value.filter(l => l.status === 'new').length)
const contactedCount = computed(() => leadsList.value.filter(l => l.status === 'contacted').length)
const qualifiedRate = computed(() => {
    if (leadsList.value.length === 0) return 0
    const qual = leadsList.value.filter(l => l.status === 'qualified').length
    return Math.round((qual / leadsList.value.length) * 100)
})

// Filters
const filteredLeads = computed(() => leadsList.value.filter(l => {
    const q = search.value.toLowerCase()
    const haystack = [
        l.title, l.firstName, l.lastName, l.fullName, l.email, l.phone, l.source,
    ].filter(Boolean).join(' ').toLowerCase()
    const matchSearch = !q || haystack.includes(q)
    const matchStatus = filterStatus.value === 'all' || l.status === filterStatus.value
    return matchSearch && matchStatus
}))

// Form Actions
const showFormModal = ref(false)
const isEdit = ref(false)
const editId = ref<string | null>(null)
const form = reactive<CreateLeadPayload & { status?: string }>({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    customer_type: 'business',
    address: '',
    title: null,
    customer_id: null,
    estimated_value: null,
    source: 'Website',
    status: 'new',
})

/**
 * @description Opens the lead creation form modal and resets fields.
 * @returns { void }
 */
const openCreateModal = () => {
    isEdit.value = false
    editId.value = null
    form.first_name = ''
    form.last_name = ''
    form.email = ''
    form.phone = ''
    form.customer_type = 'business'
    form.address = ''
    form.title = null
    form.customer_id = null
    form.estimated_value = null
    form.source = 'Website'
    form.status = 'new'
    showFormModal.value = true
}

/**
 * @description Opens the lead form modal in edit mode and populates existing fields.
 * @param { Lead } l Lead model instance to edit
 * @returns { void }
 */
const openEditModal = (l: Lead) => {
    isEdit.value = true
    editId.value = l.id
    form.first_name = l.firstName ?? ''
    form.last_name = l.lastName ?? ''
    form.email = l.email ?? ''
    form.phone = l.phone ?? ''
    form.customer_type = l.customerType ?? 'business'
    form.address = l.address ?? ''
    form.title = l.title
    form.customer_id = l.customerId
    form.estimated_value = l.estimatedValue
    form.source = l.source || 'Website'
    form.status = l.status
    showFormModal.value = true
}

/**
 * @description Save the lead details (Create new raw Lead or Update existing one)
 * @method POST/PUT
 * @returns { Promise<void> } Resolves when lead is successfully persisted
 */
const saveLead = async () => {
    submitting.value = true
    try {
        // Backend auto-derives title from name when omitted — send null to use it.
        const payload = { ...form, title: form.title || null }

        const displayName = `${form.first_name} ${form.last_name}`.trim()
        if (isEdit.value && editId.value) {
            const res = await crm.leads.update(editId.value, payload)
            const idx = leadsList.value.findIndex(l => l.id === editId.value)
            if (idx !== -1) leadsList.value[idx] = res.data
            toast.success('Lead updated', displayName)
        } else {
            const res = await crm.leads.create(payload)
            leadsList.value.unshift(res.data)
            toast.success('Lead captured', displayName)
        }
        showFormModal.value = false
    } catch (err: any) {
        toast.error('Operation failed', err?.data?.message)
    } finally {
        submitting.value = false
    }
}

// Lead Qualification
const showQualifyModal = ref(false)
const qualifyTarget = ref<Lead | null>(null)
const qualifyForm = reactive({
    customer_id: null as string | null,
    title: '',
    estimated_value: 0 as number,
    projected_close_date: null as string | null
})

/**
 * @description Opens the lead qualification and conversion modal, resetting/prefilling fields.
 * @param { Lead } l Lead model instance to qualify
 * @returns { void }
 */
const openQualifyModal = (l: Lead) => {
    qualifyTarget.value = l
    qualifyForm.customer_id = l.customerId
    qualifyForm.title = `Opportunity - ${l.fullName || l.title || 'Unnamed lead'}`
    qualifyForm.estimated_value = l.estimatedValue || 0
    qualifyForm.projected_close_date = null
    showQualifyModal.value = true
}

/**
 * @description Submit qualify and convert lead request payload to API
 * @method POST
 * @returns { Promise<void> } Resolves when qualified, showing a success toast and reloading state
 */
const qualifyLead = async () => {
    if (!qualifyTarget.value) return
    qualifying.value = true
    try {
        const payload: QualifyLeadPayload = {
            opportunity_title: qualifyForm.title,
            estimated_value: qualifyForm.estimated_value,
            close_date: qualifyForm.projected_close_date,
            customer_id: qualifyForm.customer_id,
        }

        const res = await crm.leads.qualify(qualifyTarget.value.id, payload)
        
        // Update local list
        const idx = leadsList.value.findIndex(l => l.id === qualifyTarget.value!.id)
        if (idx !== -1) leadsList.value[idx] = res.data

        toast.success('Lead Qualified', qualifyForm.title)
        showQualifyModal.value = false
    } catch (err: any) {
        toast.error('Qualification failed', err?.data?.message)
    } finally {
        qualifying.value = false
    }
}

// Delete Lead
const deleteTarget = ref<Lead | null>(null)
/**
 * @description Prompts the confirmation overlay before archiving a lead.
 * @param { Lead } l Lead model instance to delete
 * @returns { void }
 */
const confirmDelete = (l: Lead) => { deleteTarget.value = l }

/**
 * @description Submits archiving request for the designated target lead.
 * @method DELETE
 * @returns { Promise<void> } Resolves on success, shows toast and updates list
 */
const onConfirmDelete = async () => {
    if (!deleteTarget.value) return
    deleting.value = true
    try {
        await crm.leads.destroy(deleteTarget.value.id)
        leadsList.value = leadsList.value.filter(l => l.id !== deleteTarget.value!.id)
        toast.success('Lead archived', deleteTarget.value.title ?? undefined)
        deleteTarget.value = null
    } catch (err: any) {
        toast.error('Archiving failed', err?.data?.message)
    } finally {
        deleting.value = false
    }
}

// Data Boot
/**
 * @description Loads CRM leads and customer accounts catalogs.
 * @method GET
 * @returns { Promise<void> } Resolves on success
 */
const load = async () => {
    loading.value = true
    try {
        const [lRes, cRes] = await Promise.all([
            crm.leads.list({ limit: 100 }),
            sales.catalogue.listCustomers({ limit: 200 })
        ])
        leadsList.value = lRes.data
        customers.value = cRes.data
    } catch (err: any) {
        toast.error('Failed to load CRM leads data', err?.data?.message)
    } finally {
        loading.value = false
    }
}

/**
 * @description Map lead source strings to corresponding HSL/Tailwind CSS styling classes.
 * @param { String | null } source Lead source string
 * @returns { String } Tailwind/CSS class string
 */
const sourceBadgeClass = (source: string | null) => {
    if (source === 'Website') return 'bg-(--color-info)/15 text-(--color-info)'
    if (source === 'Referral') return 'bg-purple-500/15 text-purple-500'
    if (source === 'Cold Outreach') return 'bg-amber-500/15 text-amber-500'
    return 'bg-(--bg-muted) text-(--text-muted)'
}

/**
 * @description Formats a date string into human-readable format.
 * @param { String } d ISO Date string
 * @returns { String } The formatted date string (e.g. "May 25")
 */
const formatDate = (d: string) => new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
/**
 * @description Format a numeric dollar amount into formatted USD currency representation.
 * @param { Number } v Number value
 * @returns { String } Formatted currency string
 */
const formatCurrency = (v: number) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v)

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
