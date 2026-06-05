<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">B2B Contacts</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Directory of human decision makers, buyers, and technical champions linked to corporate accounts.</p>
                </div>
                <button type="button" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-user-plus" />New Contact
                </button>
            </header>

            <!-- Filters -->
            <section class="glass-card rounded-xl p-4 flex flex-col md:flex-row gap-3 items-center justify-between">
                <div class="relative w-full md:w-96">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                    <input v-model="search" type="search" placeholder="Search contacts by name, email, job title..."
                        class="form-control pl-9" />
                </div>
            </section>

            <!-- Loading -->
            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading contacts...</span>
            </div>

            <!-- Empty -->
            <div v-else-if="filteredContacts.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-user-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No contacts found</h4>
                <p class="text-xs text-(--text-muted) mt-1">Capture your first human contact for corporate accounts.</p>
            </div>

            <!-- Contacts Cards Grid -->
            <section v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <article v-for="c in filteredContacts" :key="c.id" 
                    class="glass-card rounded-2xl p-5 flex flex-col gap-3 group relative overflow-hidden transition-all duration-150 border border-(--border-color) hover:border-(--color-primary)/40"
                    :class="{ 'ring-1 ring-(--color-primary)/40': c.isPrimary }">
                    
                    <!-- Glowing shape behind card -->
                    <div class="absolute -right-8 -top-8 w-20 h-20 rounded-full bg-(--color-primary)/10 blur-xl pointer-events-none group-hover:scale-150 transition-transform duration-500" />

                    <div class="space-y-3 relative z-10 flex-1 flex flex-col">
                        <header class="flex items-start gap-3">
                            <div class="w-10 h-10 rounded-xl bg-(--color-primary-subtle) text-(--color-primary) flex items-center justify-center font-bold text-xs shrink-0 transition-transform duration-300 group-hover:scale-105">
                                {{ initials(c) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2 flex-wrap max-sm:justify-center">
                                    <h3 class="text-sm font-semibold text-(--text-heading) truncate group-hover:text-(--color-primary) transition-colors">
                                        {{ displayName(c) }}
                                    </h3>
                                    <span v-if="c.isPrimary"
                                        class="text-xxs px-1.5 py-0.5 rounded font-bold uppercase tracking-wide badge-soft-primary inline-flex items-center gap-1">
                                        <i class="ti ti-star-filled text-[10px]" />Primary
                                    </span>
                                </div>
                                <p class="text-xxs text-(--text-muted) truncate font-medium">{{ c.jobTitle || 'Representative' }}</p>
                            </div>
                        </header>

                        <div class="text-xxs space-y-1.5 border-t border-b border-(--border-color)/50 py-3 my-1">
                            <div v-if="c.customer" class="flex items-center gap-2 truncate">
                                <i class="ti ti-building text-(--text-muted) shrink-0" />
                                <span class="font-semibold text-(--text-heading) truncate">{{ c.customer.name }}</span>
                            </div>
                            <div class="flex items-center gap-2 truncate">
                                <i class="ti ti-mail text-(--text-muted) shrink-0" />
                                <a v-if="c.email" :href="`mailto:${c.email}`" class="truncate hover:text-(--color-primary) transition-colors">{{ c.email }}</a>
                                <span v-else class="text-(--text-muted) italic">No email</span>
                            </div>
                            <div class="flex items-center gap-2 truncate">
                                <i class="ti ti-phone text-(--text-muted) shrink-0" />
                                <a v-if="c.phone" :href="`tel:${c.phone}`" class="hover:text-(--color-primary) transition-colors">{{ c.phone }}</a>
                                <span v-else class="text-(--text-muted) italic">No phone</span>
                            </div>
                        </div>
                    </div>

                    <footer class="mt-auto pt-2 border-t border-(--border-color)/50 flex items-center justify-end gap-1.5 relative z-10">
                        <button type="button" class="action-btn" title="Edit" @click="openEditModal(c)">
                            <i class="ti ti-pencil" />
                        </button>
                        <button type="button" class="action-btn action-btn-danger" title="Delete" @click="confirmDelete(c)">
                            <i class="ti ti-trash" />
                        </button>
                    </footer>
                </article>
            </section>
        </div>

        <!-- Form Modal -->
        <div v-if="showFormModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">{{ isEdit ? 'Edit Contact' : 'Create Contact person' }}</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="saveContact">
                    <div class="p-5 space-y-4">
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Corporate Customer Account</label>
                            <select v-model="form.customer_id" class="form-control text-xs" required :disabled="isEdit">
                                <option :value="''" disabled>-- Choose Customer Account --</option>
                                <option v-for="cust in customers" :key="cust.id" :value="cust.id">{{ cust.name }}</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">First Name *</label>
                                <input v-model="form.first_name" type="text" required class="form-control text-xs" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Last Name</label>
                                <input v-model="form.last_name" type="text" class="form-control text-xs" />
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Email Address</label>
                            <input v-model="form.email" type="email" placeholder="name@company.com" class="form-control text-xs" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Phone Number</label>
                                <input v-model="form.phone" type="text" placeholder="+1 555-0100" class="form-control text-xs" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Job Title</label>
                                <input v-model="form.job_title" type="text" placeholder="e.g. Procurement Lead" class="form-control text-xs" />
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-xs text-(--text-body) cursor-pointer select-none">
                            <input v-model="form.is_primary" type="checkbox" class="rounded border-(--border-color)" />
                            <span class="inline-flex items-center gap-1">
                                <i class="ti ti-star-filled text-(--color-warning)" />
                                Mark as primary contact for this account
                            </span>
                        </label>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showFormModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="submitting">
                            <i v-if="submitting" class="ti ti-loader-2 animate-spin" />
                            {{ isEdit ? 'Save Changes' : 'Create Contact' }}
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Delete Modal -->
        <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3>Archive Contact</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="deleteTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5">
                    <p class="text-xs text-(--text-body)">Archive contact <span class="font-semibold text-(--text-heading)">{{ displayName(deleteTarget) }}</span>?</p>
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
import { useCrm } from '~/composables/useCrm'
import { useSales } from '~/composables/useSales'
import { useToast } from '~/composables/useToast'
import { useValidation } from '~/composables/useValidation'
import type { CrmContact, CreateContactPayload } from '~/types/crm'
import type { CustomerLite } from '~/types/sales'

const crm = useCrm()
const sales = useSales()
const toast = useToast()
const { normalizePhoneNumber, normalizeEmail } = useValidation()

const loading = ref(false)
const submitting = ref(false)
const deleting = ref(false)

const contactsList = ref<CrmContact[]>([])
const customers = ref<CustomerLite[]>([])
const search = ref('')

const filteredContacts = computed(() => contactsList.value.filter(c => {
    const q = search.value.toLowerCase()
    if (!q) return true
    const haystack = [
        c.firstName, c.lastName, c.fullName, c.email, c.jobTitle, c.customer?.name,
    ].filter(Boolean).join(' ').toLowerCase()
    return haystack.includes(q)
}))

/**
 * Server-composed full name, with a safe fallback for legacy rows where
 * only firstName is present.
 */
const displayName = (c: CrmContact): string => {
    if (c.fullName && c.fullName.trim()) return c.fullName
    return [c.firstName, c.lastName].filter(Boolean).join(' ') || 'Unnamed contact'
}

/**
 * @description Extracts first letter initials from contact's first and last name.
 * Null-safe so contacts created from a Lead with no last name (via
 * QuotationService::win) still get a visible avatar.
 */
const initials = (c: CrmContact): string => {
    const first = (c.firstName?.charAt(0) ?? '').toUpperCase()
    const last  = (c.lastName?.charAt(0) ?? '').toUpperCase()
    return (first + last) || '?'
}

// Form Actions
const showFormModal = ref(false)
const isEdit = ref(false)
const editId = ref<string | null>(null)
const form = reactive<CreateContactPayload>({
    customer_id: '',
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    job_title: '',
    is_primary: false,
})

/**
 * @description Opens the contact form modal in creation mode.
 * @returns { void }
 */
const openCreateModal = () => {
    isEdit.value = false
    editId.value = null
    form.customer_id = ''
    form.first_name = ''
    form.last_name = ''
    form.email = ''
    form.phone = ''
    form.job_title = ''
    form.is_primary = false
    showFormModal.value = true
}

/**
 * @description Opens the contact form modal in edit mode and populates existing fields.
 * @param { CrmContact } c Contact model instance to edit
 * @returns { void }
 */
const openEditModal = (c: CrmContact) => {
    isEdit.value = true
    editId.value = c.id
    form.customer_id = c.customerId
    form.first_name = c.firstName
    form.last_name = c.lastName ?? ''
    form.email = c.email ?? ''
    form.phone = c.phone ?? ''
    form.job_title = c.jobTitle ?? ''
    form.is_primary = !!c.isPrimary
    showFormModal.value = true
}

/**
 * @description Save contact form details to the database (Create or Update)
 * @method POST/PUT
 * @returns { Promise<void> } Resolves on success, shows toast and updates local contacts list
 */
const saveContact = async () => {
    submitting.value = true

    // Send null instead of empty strings so backend nullable validation +
    // unique-on-email rules behave correctly.
    const payload: CreateContactPayload = {
        customer_id: form.customer_id,
        first_name: form.first_name.trim(),
        last_name: form.last_name?.trim() || null,
        email: form.email ? normalizeEmail(form.email) : null,
        phone: form.phone ? normalizePhoneNumber(form.phone) : null,
        job_title: form.job_title?.trim() || null,
        is_primary: !!form.is_primary,
    }
    const displayLabel = [payload.first_name, payload.last_name].filter(Boolean).join(' ')

    try {
        if (isEdit.value && editId.value) {
            const res = await crm.contacts.update(editId.value, payload)
            const idx = contactsList.value.findIndex(c => c.id === editId.value)
            if (idx !== -1) contactsList.value[idx] = res.data
            toast.success('Contact updated', displayLabel)
        } else {
            const res = await crm.contacts.create(payload)
            contactsList.value.unshift(res.data)
            toast.success('Contact added', displayLabel)
        }
        showFormModal.value = false
    } catch (err: any) {
        toast.error('Saving failed', err?.data?.message)
    } finally {
        submitting.value = false
    }
}

// Delete Actions
const deleteTarget = ref<CrmContact | null>(null)
/**
 * @description Prompts confirmation overlay before archiving a contact.
 * @param { CrmContact } c Contact model instance to delete
 * @returns { void }
 */
const confirmDelete = (c: CrmContact) => { deleteTarget.value = c }

/**
 * @description Submits archiving request for the designated target contact.
 * @method DELETE
 * @returns { Promise<void> } Resolves on success, shows toast and updates list
 */
const onConfirmDelete = async () => {
    if (!deleteTarget.value) return
    deleting.value = true
    try {
        await crm.contacts.destroy(deleteTarget.value.id)
        contactsList.value = contactsList.value.filter(c => c.id !== deleteTarget.value!.id)
        toast.success('Contact archived', displayName(deleteTarget.value))
        deleteTarget.value = null
    } catch (err: any) {
        toast.error('Archiving failed', err?.data?.message)
    } finally {
        deleting.value = false
    }
}

// Boot Data
/**
 * @description Loads B2B contacts and B2B corporate customer accounts lists.
 * @method GET
 * @returns { Promise<void> } Resolves on success
 */
const load = async () => {
    loading.value = true
    try {
        const [cRes, custRes] = await Promise.all([
            crm.contacts.list({ limit: 150 }),
            sales.catalogue.listCustomers({ limit: 200 })
        ])
        contactsList.value = cRes.data
        customers.value = custRes.data
    } catch (err: any) {
        toast.error('Failed to load contacts ledger', err?.data?.message)
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
