<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Job Offers</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        Track every offer from draft through signature. Salary figures are hidden from
                        recruiters without payroll access.
                    </p>
                </div>
            </header>

            <section class="grid grid-cols-2 sm:grid-cols-5 gap-3">
                <div v-for="kpi in kpis" :key="kpi.key" class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">{{ kpi.label }}</span>
                    <p class="text-2xl font-bold font-mono" :class="kpi.text">{{ kpi.value }}</p>
                </div>
            </section>

            <section class="flex items-center gap-2 flex-wrap max-sm:justify-center">
                <button type="button" class="chip" :class="{ active: statusFilter === '' }" @click="setStatusFilter('')">
                    All
                </button>
                <button v-for="s in (Object.keys(OFFER_STATUS_META) as OfferStatus[])" :key="s" type="button"
                    class="chip" :class="{ active: statusFilter === s }" @click="setStatusFilter(s)">
                    <i :class="['ti', OFFER_STATUS_META[s].icon]" />
                    {{ OFFER_STATUS_META[s].label }}
                </button>
                <div class="ml-auto flex items-center gap-2">
                    <input v-model.lazy="search" type="search" placeholder="search candidate name..."
                        class="form-control text-xs w-64" @keyup.enter="load" @change="load" />
                </div>
            </section>

            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading offers...</span>
            </div>

            <div v-else-if="filteredOffers.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-file-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No offers found</h4>
                <p class="text-xs text-(--text-muted) mt-1">
                    Offers are drafted from the candidate profile after a hire is marked.
                </p>
            </div>

            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-(--bg-muted)/40 text-(--text-muted)">
                            <tr>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Candidate</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3 w-40">Reference</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Position</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3 w-32">Effective</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3 w-32">Expires</th>
                                <th v-if="canSeeSalary" class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3 w-32">Base Salary</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3 w-28">Status</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3 w-20">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="o in filteredOffers" :key="o.id"
                                class="border-t border-(--border-color) hover:bg-(--bg-muted)/40">
                                <td class="px-3 py-2">
                                    <p class="font-semibold text-(--text-heading)">
                                        {{ o.application?.applicantName || '—' }}
                                    </p>
                                    <p class="text-xxs text-(--text-muted) font-mono">
                                        {{ o.application?.candidateCode || o.application?.applicantEmail || '' }}
                                    </p>
                                </td>
                                <td class="px-3 py-2 font-mono text-(--text-heading)">{{ o.referenceNumber }}</td>
                                <td class="px-3 py-2 text-(--text-body)">{{ o.title }}</td>
                                <td class="px-3 py-2 font-mono text-(--text-muted)">{{ formatDate(o.effectiveDate) }}</td>
                                <td class="px-3 py-2 font-mono"
                                    :class="isExpiringSoon(o) ? 'text-(--color-warning)' : 'text-(--text-muted)'">
                                    {{ formatDate(o.expiresAt) }}
                                </td>
                                <td v-if="canSeeSalary" class="px-3 py-2 text-right font-mono">
                                    {{ o.baseSalary != null ? formatMoney(o.baseSalary, o.currency) : '—' }}
                                </td>
                                <td class="px-3 py-2">
                                    <Badge :variant="OFFER_STATUS_META[o.status].variant" :dot="true">
                                        {{ OFFER_STATUS_META[o.status].label }}
                                    </Badge>
                                </td>
                                <td class="px-3 py-2 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <NuxtLink v-if="o.applicationId"
                                            :to="`/hrm/recruitments/candidates/${o.applicationId}#offer`"
                                            class="action-btn" title="Open candidate offer tab">
                                            <i class="ti ti-eye text-xs" />
                                        </NuxtLink>
                                        <button v-if="canWrite && o.status === 'draft'" type="button"
                                            class="action-btn action-btn-danger" title="Delete draft"
                                            @click="confirmDelete(o)">
                                            <i class="ti ti-trash text-xs" />
                                        </button>
                                        <button v-if="canWrite && o.status === 'sent'" type="button"
                                            class="action-btn" title="Mark accepted"
                                            @click="openAccept(o)">
                                            <i class="ti ti-check text-xs text-(--color-success)" />
                                        </button>
                                        <button v-if="canWrite && o.status === 'sent'" type="button"
                                            class="action-btn" title="Decline"
                                            @click="openDecline(o)">
                                            <i class="ti ti-x text-xs text-(--color-danger)" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination v-if="pagination.total > 0" :page="pagination.page" :limit="pagination.limit"
                    :total="pagination.total" :total-pages="pagination.totalPages"
                    @update:page="(p) => { pagination.page = p; load() }"
                    @update:limit="(l) => { pagination.limit = l; pagination.page = 1; load() }" />
            </section>
        </div>

        <!-- Delete draft modal -->
        <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Delete Draft Offer</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center"
                        @click="deleteTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 text-xs text-(--text-muted) space-y-2">
                    <p>
                        Permanently delete draft <span class="font-mono text-(--text-heading)">{{ deleteTarget.referenceNumber }}</span>
                        for <span class="text-(--text-heading)">{{ deleteTarget.application?.applicantName }}</span>?
                    </p>
                    <p>Only drafts can be deleted. Sent offers must be voided through the e-signature provider.</p>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="deleteTarget = null">Cancel</button>
                    <button type="button" class="btn btn-danger text-xs" :disabled="busy" @click="onConfirmDelete">
                        <i v-if="busy" class="ti ti-loader-2 animate-spin" />
                        Delete
                    </button>
                </footer>
            </div>
        </div>

        <!-- Accept modal -->
        <div v-if="acceptTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Mark Offer Accepted</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center"
                        @click="acceptTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 text-xs text-(--text-muted) space-y-2">
                    <p>
                        Confirm wet-ink signature for offer
                        <span class="font-mono text-(--text-heading)">{{ acceptTarget.referenceNumber }}</span>?
                    </p>
                    <p>
                        Accepting will convert
                        <span class="text-(--text-heading)">{{ acceptTarget.application?.applicantName }}</span>
                        into an employee record and seed the default onboarding checklist.
                    </p>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="acceptTarget = null">Cancel</button>
                    <button type="button" class="btn btn-primary text-xs" :disabled="busy" @click="onConfirmAccept">
                        <i v-if="busy" class="ti ti-loader-2 animate-spin" />
                        Accept &amp; Convert
                    </button>
                </footer>
            </div>
        </div>

        <!-- Decline modal -->
        <div v-if="declineTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Decline Offer</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center"
                        @click="declineTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <p class="text-xs text-(--text-muted)">
                        Decline offer <span class="font-mono text-(--text-heading)">{{ declineTarget.referenceNumber }}</span>
                        for <span class="text-(--text-heading)">{{ declineTarget.application?.applicantName }}</span>?
                    </p>
                    <div class="space-y-1">
                        <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Reason (optional)</label>
                        <textarea v-model="declineReason" rows="3" maxlength="500"
                            class="form-control text-xs resize-none" placeholder="e.g. Candidate counter-signed elsewhere." />
                    </div>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="declineTarget = null">Cancel</button>
                    <button type="button" class="btn btn-danger text-xs" :disabled="busy" @click="onConfirmDecline">
                        <i v-if="busy" class="ti ti-loader-2 animate-spin" />
                        Decline
                    </button>
                </footer>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import Badge from '~/components/Badge.vue'
import { useOffers, OFFER_STATUS_META, type Offer, type OfferStatus } from '~/composables/useOffers'
import { useToast } from '~/composables/useToast'
import { useAuthStore } from '~/stores/auth'

definePageMeta({ breadcrumb: 'Job Offers' })

const offers = useOffers()
const toast = useToast()
const authStore = useAuthStore()

const canRead = computed(() => authStore.hasPermission('hrm.recruitment.read'))
const canWrite = computed(() => authStore.hasPermission('hrm.recruitment.write'))
const canSeeSalary = computed(() => authStore.hasPermission('hrm.payroll.read'))

const loading = ref(false)
const busy = ref(false)
const rows = ref<Offer[]>([])
const pagination = reactive({ page: 1, limit: 25, total: 0, totalPages: 1 })
const statusFilter = ref<'' | OfferStatus>('')
const search = ref('')

const filteredOffers = computed(() => {
    const term = search.value.trim().toLowerCase()
    if (!term) return rows.value
    return rows.value.filter(o => {
        const name = o.application?.applicantName?.toLowerCase() ?? ''
        const email = o.application?.applicantEmail?.toLowerCase() ?? ''
        const ref = o.referenceNumber.toLowerCase()
        return name.includes(term) || email.includes(term) || ref.includes(term)
    })
})

const statusCount = (s: OfferStatus) => rows.value.filter(r => r.status === s).length

const kpis = computed(() => [
    { key: 'total',    label: 'Total',    value: rows.value.length, text: 'text-(--text-heading)' },
    { key: 'draft',    label: 'Drafts',   value: statusCount('draft'),    text: 'text-(--text-muted)' },
    { key: 'sent',     label: 'Sent',     value: statusCount('sent'),     text: 'text-(--color-info)' },
    { key: 'accepted', label: 'Accepted', value: statusCount('accepted'), text: 'text-(--color-success)' },
    { key: 'declined', label: 'Declined', value: statusCount('declined') + statusCount('expired'), text: 'text-(--color-danger)' },
])

const load = async () => {
    if (!canRead.value) return
    loading.value = true
    try {
        const res = await offers.list({
            page: pagination.page,
            limit: pagination.limit,
            status: statusFilter.value || undefined,
        })
        rows.value = res.data
        const p = res.pagination
        if (p) {
            pagination.total = p.total ?? 0
            pagination.totalPages = p.totalPages ?? 1
            pagination.page = p.page ?? 1
            pagination.limit = p.limit ?? pagination.limit
        }
    } catch (err: any) {
        toast.error('Failed to load offers', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const setStatusFilter = (s: '' | OfferStatus) => {
    if (statusFilter.value === s) return
    statusFilter.value = s
    pagination.page = 1
    load()
}

const formatDate = (iso: string | null) => {
    if (!iso) return '—'
    const d = new Date(iso)
    return isNaN(d.getTime()) ? iso : d.toISOString().slice(0, 10)
}

const formatMoney = (n: number, currency: string | null) => {
    try {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency || 'USD',
            maximumFractionDigits: 0,
        }).format(n)
    } catch {
        return `${n}`
    }
}

const isExpiringSoon = (o: Offer): boolean => {
    if (!o.expiresAt || o.status !== 'sent') return false
    const days = Math.floor((new Date(o.expiresAt).getTime() - Date.now()) / 86_400_000)
    return days >= 0 && days <= 3
}

// ----- Delete draft -----
const deleteTarget = ref<Offer | null>(null)
const confirmDelete = (o: Offer) => { deleteTarget.value = o }
const onConfirmDelete = async () => {
    if (!deleteTarget.value) return
    busy.value = true
    try {
        await offers.destroy(deleteTarget.value.id)
        toast.success('Draft deleted')
        deleteTarget.value = null
        await load()
    } catch (err: any) {
        toast.error('Delete failed', err?.data?.message)
    } finally {
        busy.value = false
    }
}

// ----- Manual accept -----
const acceptTarget = ref<Offer | null>(null)
const openAccept = (o: Offer) => { acceptTarget.value = o }
const onConfirmAccept = async () => {
    if (!acceptTarget.value) return
    busy.value = true
    try {
        await offers.accept(acceptTarget.value.id)
        toast.success('Offer accepted', 'Employee record provisioned and onboarding seeded.')
        acceptTarget.value = null
        await load()
    } catch (err: any) {
        toast.error('Accept failed', err?.data?.message)
    } finally {
        busy.value = false
    }
}

// ----- Decline -----
const declineTarget = ref<Offer | null>(null)
const declineReason = ref('')
const openDecline = (o: Offer) => {
    declineTarget.value = o
    declineReason.value = ''
}
const onConfirmDecline = async () => {
    if (!declineTarget.value) return
    busy.value = true
    try {
        await offers.decline(declineTarget.value.id, declineReason.value.trim() || undefined)
        toast.success('Offer declined')
        declineTarget.value = null
        await load()
    } catch (err: any) {
        toast.error('Decline failed', err?.data?.message)
    } finally {
        busy.value = false
    }
}

onMounted(load)
</script>

<style scoped>
.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
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
.chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    font-size: 11px;
    color: var(--text-body);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
.chip:hover { background: var(--bg-muted); }
.chip.active {
    background: rgb(var(--color-primary-rgb) / 0.12);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}
</style>
