<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Job vacancies</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Open requisitions and applicant pipelines.</p>
                </div>
                <button v-if="canWrite" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />New vacancy
                </button>
            </header>

            <!-- Filters -->
            <section class="glass-card rounded-xl p-4">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="relative md:col-span-4">
                        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                        <input v-model="filters.search" type="search" placeholder="Search title or location..."
                            class="form-control pl-9" />
                    </div>

                    <div class="md:col-span-3">
                        <select v-model="filters.departmentId" class="form-control">
                            <option :value="''">All departments</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>

                    <div class="md:col-span-2">
                        <select v-model="filters.employmentType" class="form-control">
                            <option :value="''">All types</option>
                            <option value="full_time">Full-time</option>
                            <option value="part_time">Part-time</option>
                            <option value="contract">Contract</option>
                            <option value="intern">Intern</option>
                        </select>
                    </div>

                    <div class="md:col-span-3">
                        <select v-model="filters.status" class="form-control">
                            <option :value="''">All status</option>
                            <option value="draft">Draft</option>
                            <option value="open">Open</option>
                            <option value="paused">Paused</option>
                            <option value="closed">Closed</option>
                            <option value="filled">Filled</option>
                        </select>
                    </div>
                </div>
            </section>

            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span
                    class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading vacancies...</span>
            </div>

            <div v-else-if="vacancies.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-briefcase-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No vacancies match</h4>
                <p class="text-xs text-(--text-muted) mt-1">Open a requisition to start hiring.</p>
            </div>

            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <!-- Bulk action toolbar (visible while drafts are checked) -->
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
                            <button type="button" class="btn btn-soft-primary text-xs px-3 py-1.5"
                                :disabled="bulkPublishing || bulkClosing || bulkArchiving || selectedPublishable.length === 0"
                                :title="selectedPublishable.length === 0 ? 'No draft rows in the selection' : 'Open (publish) the draft rows in your selection'"
                                @click="bulkPublish">
                                <i :class="['ti', bulkPublishing ? 'ti-loader animate-spin' : 'ti-send']" />
                                {{ bulkPublishing
                                    ? 'Opening...'
                                    : `Open ${selectedPublishable.length}` }}
                            </button>
                            <button type="button"
                                class="btn btn-ghost text-xs px-3 py-1.5 text-(--color-warning) hover:bg-(--color-warning-subtle) hover:text-(--color-warning)"
                                :disabled="bulkPublishing || bulkClosing || bulkArchiving || selectedCloseable.length === 0"
                                :title="selectedCloseable.length === 0 ? 'No open/paused rows in the selection' : 'Close the open/paused rows in your selection'"
                                @click="bulkClose">
                                <i :class="['ti', bulkClosing ? 'ti-loader animate-spin' : 'ti-lock']" />
                                {{ bulkClosing
                                    ? 'Closing...'
                                    : `Close ${selectedCloseable.length}` }}
                            </button>
                            <button type="button"
                                class="btn btn-ghost text-xs px-3 py-1.5 text-(--color-danger) hover:bg-(--color-danger-subtle) hover:text-(--color-danger)"
                                :disabled="bulkPublishing || bulkClosing || bulkArchiving || selectedArchivable.length === 0"
                                :title="selectedArchivable.length === 0 ? 'Nothing to archive' : 'Archive the selected rows'"
                                @click="bulkArchive">
                                <i :class="['ti', bulkArchiving ? 'ti-loader animate-spin' : 'ti-trash']" />
                                {{ bulkArchiving
                                    ? 'Archiving...'
                                    : `Archive ${selectedArchivable.length}` }}
                            </button>
                        </div>
                    </div>
                </transition>

                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr
                                class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                <th v-if="canWrite" class="pl-4 pr-1 py-3 w-8">
                                    <input type="checkbox" class="row-checkbox" :checked="allSelectableSelected"
                                        :indeterminate.prop="someSelectableSelected && !allSelectableSelected"
                                        :disabled="selectableRows.length === 0"
                                        :title="selectableRows.length === 0 ? 'No rows on this page' : 'Select all rows'"
                                        @change="toggleSelectAll">
                                </th>
                                <th class="px-4 py-3 font-semibold">Title</th>
                                <th class="px-4 py-3 font-semibold">Department</th>
                                <th class="px-4 py-3 font-semibold">Location</th>
                                <th class="px-4 py-3 font-semibold">Type</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Salary</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Apps</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-color)">
                            <tr v-for="v in vacancies" :key="v.id" class="transition-colors"
                                :class="selectedIds.has(v.id) ? 'bg-(--color-primary-subtle)/30' : 'hover:bg-(--bg-muted)'">
                                <td v-if="canWrite" class="pl-4 pr-1 py-3 w-8">
                                    <input type="checkbox" class="row-checkbox" :checked="selectedIds.has(v.id)"
                                        @change="toggleRow(v)">
                                </td>
                                <td class="px-4 py-3">
                                    <div class="text-xs font-semibold text-(--text-heading)">{{ v.title }}</div>
                                    <div class="text-xxs text-(--text-muted) font-mono">{{ formatDate(v.postedAt) }}
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-xs">{{ v.department?.name || '—' }}</td>
                                <td class="px-4 py-3 text-xs">{{ v.location || '—' }}</td>
                                <td class="px-4 py-3 text-xs capitalize">{{ (v.employmentType || '').replace('_', ' ')
                                    }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-right">
                                    <span v-if="v.salaryMin != null && v.salaryMax != null">
                                        {{ formatMoney(v.salaryMin) }} – {{ formatMoney(v.salaryMax) }}
                                    </span>
                                    <span v-else class="text-(--text-muted)">—</span>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-right">
                                    <NuxtLink :to="`/hrm/recruitments/applications?vacancyId=${v.id}`"
                                        class="text-(--color-primary) hover:underline">
                                        {{ v.applicationCount ?? 0 }}
                                    </NuxtLink>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge :variant="statusVariant(v.status)" :dot="true">{{ v.status }}</Badge>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button class="action-trigger"
                                        :class="{ 'action-trigger-open': actionMenu.open && actionMenu.vacancy?.id === v.id }"
                                        title="Actions" @click.stop="openActionMenu(v, $event)">
                                        <i class="ti ti-dots-vertical" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :page="pagination.page" :limit="pagination.limit" :total="pagination.total"
                    :total-pages="pagination.totalPages" @update:page="(p) => { pagination.page = p; loadVacancies() }"
                    @update:limit="(l) => { pagination.limit = l; pagination.page = 1; loadVacancies() }" />
            </section>

            <!-- Create / edit modal -->
            <div v-if="showModal"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div
                    class="glass-card rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                    <header class="flex items-center justify-between mb-5">
                        <h3 class="text-base font-semibold text-(--text-heading)">
                            {{ editing ? 'Edit vacancy' : 'New vacancy' }}
                        </h3>
                        <button class="topbar-btn" @click="closeModal"><i class="ti ti-x" /></button>
                    </header>

                    <form class="form-grid" @submit.prevent="saveVacancy">
                        <div class="form-grid-full">
                            <label class="form-label form-label-required">Title</label>
                            <input v-model="form.title" type="text" required class="form-control"
                                placeholder="Senior Backend Engineer" />
                        </div>

                        <div class="form-grid-full">
                            <label class="form-label">Description</label>
                            <textarea v-model="form.description" rows="3" class="form-control"
                                placeholder="Role summary, key responsibilities..." />
                        </div>

                        <div>
                            <label class="form-label">Location</label>
                            <input v-model="form.location" type="text" class="form-control"
                                placeholder="Phnom Penh / Remote" />
                        </div>
                        <div>
                            <label class="form-label">Employment type</label>
                            <select v-model="form.employment_type" class="form-control">
                                <option value="full_time">Full-time</option>
                                <option value="part_time">Part-time</option>
                                <option value="contract">Contract</option>
                                <option value="intern">Intern</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Department</label>
                            <select v-model="form.department_id" class="form-control">
                                <option :value="''">—</option>
                                <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Position</label>
                            <select v-model="form.position_id" class="form-control">
                                <option :value="''">—</option>
                                <option v-for="p in positions" :key="p.id" :value="p.id">{{ p.title }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Experience min (years)</label>
                            <input v-model.number="form.experience_min_years" type="number" min="0" max="60"
                                class="form-control font-mono" />
                        </div>
                        <div>
                            <label class="form-label">Experience max (years)</label>
                            <input v-model.number="form.experience_max_years" type="number" min="0" max="60"
                                class="form-control font-mono" />
                        </div>

                        <div>
                            <label class="form-label">Salary min</label>
                            <input v-model.number="form.salary_min" type="number" step="0.01" min="0"
                                class="form-control font-mono" />
                        </div>
                        <div>
                            <label class="form-label">Salary max</label>
                            <input v-model.number="form.salary_max" type="number" step="0.01" min="0"
                                class="form-control font-mono" />
                        </div>

                        <div>
                            <label class="form-label">Vacancies count</label>
                            <input v-model.number="form.vacancies_count" type="number" min="1"
                                class="form-control font-mono" />
                        </div>
                        <div>
                            <label class="form-label">Closes at</label>
                            <input v-model="form.closes_at" type="date" class="form-control" />
                        </div>

                        <div v-if="formError" class="form-grid-full form-error">{{ formError }}</div>

                        <footer class="form-grid-full pt-4 border-t border-(--border-color) flex justify-end gap-2">
                            <button type="button" class="btn btn-ghost text-xs" @click="closeModal">Cancel</button>
                            <button type="submit" class="btn btn-primary text-xs" :disabled="saving">
                                <i class="ti ti-device-floppy" />{{ saving ? 'Saving...' : 'Save' }}
                            </button>
                        </footer>
                    </form>
                </div>
            </div>

            <!-- Action dropdown -->
            <div v-if="actionMenu.open && actionMenu.vacancy"
                class="fixed z-50 glass-card rounded-lg shadow-(--shadow-lg) bg-(--bg-card) border border-(--border-color) py-1 min-w-[180px]"
                :style="{ top: actionMenu.y + 'px', left: actionMenu.x + 'px' }" @click.stop>
                <button class="action-item" @click="actionEdit">
                    <i class="ti ti-pencil" /> Edit
                </button>
                <NuxtLink :to="`/hrm/recruitments/applications?vacancyId=${actionMenu.vacancy.id}`" class="action-item"
                    @click="closeActionMenu">
                    <i class="ti ti-users" /> View applications
                </NuxtLink>
                <template v-if="canWrite && actionMenu.vacancy.status === 'draft'">
                    <hr class="my-1 border-(--border-color)" />
                    <button class="action-item action-item-primary" @click="actionPublish">
                        <i class="ti ti-send" /> Publish
                    </button>
                </template>
                <template v-if="canWrite && ['open', 'paused'].includes(actionMenu.vacancy.status)">
                    <hr class="my-1 border-(--border-color)" />
                    <button class="action-item action-item-warning" @click="actionClose">
                        <i class="ti ti-lock" /> Close vacancy
                    </button>
                </template>
                <template v-if="canWrite">
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
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useApi } from '~/composables/useApi'
import { formatDate } from '~/composables/useDateFormat'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'

interface Lookup { id: string; name?: string; title?: string }
interface Vacancy {
    id: string
    title: string
    description: string | null
    location: string | null
    employmentType: string
    experienceMinYears: number | null
    experienceMaxYears: number | null
    salaryMin: number | null
    salaryMax: number | null
    vacanciesCount: number
    status: 'draft' | 'open' | 'paused' | 'closed' | 'filled'
    postedAt: string | null
    closesAt: string | null
    department: { id: string; name: string } | null
    position: { id: string; title: string } | null
    applicationCount?: number
}
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

const api = useApi()
const authStore = useAuthStore()
const toast = useToast()
const canWrite = computed(() => authStore.hasPermission('hrm.recruitment.write'))

const vacancies = ref<Vacancy[]>([])
const departments = ref<Array<{ id: string; name: string }>>([])
const positions = ref<Array<{ id: string; title: string }>>([])
const loading = ref(false)

const pagination = reactive({ page: 1, limit: 15, total: 0, totalPages: 1 })
const filters = reactive({ search: '', status: '', departmentId: '', employmentType: '' })

const showModal = ref(false)
const editing = ref<Vacancy | null>(null)
const saving = ref(false)
const formError = ref<string | null>(null)

const actionMenu = reactive({
    open: false,
    x: 0,
    y: 0,
    vacancy: null as Vacancy | null
})
const form = reactive({
    title: '',
    description: '',
    location: '',
    employment_type: 'full_time',
    experience_min_years: null as number | null,
    experience_max_years: null as number | null,
    salary_min: null as number | null,
    salary_max: null as number | null,
    vacancies_count: 1,
    status: 'draft',
    closes_at: '',
    department_id: '',
    position_id: ''
})

// --- Bulk selection -------------------------------------------------------
// Every vacancy is selectable because Archive applies to any status. The
// three bulk buttons each compute their own eligible subset and disable when
// the selection has no matching rows. Selection persists across pagination/
// filter changes so users can build a batch.
const selectedIds = ref<Set<string>>(new Set())
const bulkPublishing = ref(false)
const bulkClosing = ref(false)
const bulkArchiving = ref(false)

const isSelectable = (_v: Vacancy) => true

const selectableRows = computed(() => vacancies.value)

const selectedVacancies = computed(() =>
    vacancies.value.filter(v => selectedIds.value.has(v.id))
)
const selectedPublishable = computed(() =>
    selectedVacancies.value.filter(v => v.status === 'draft')
)
const selectedCloseable = computed(() =>
    selectedVacancies.value.filter(v => v.status === 'open' || v.status === 'paused')
)
const selectedArchivable = computed(() => selectedVacancies.value)

const selectedCount = computed(() => selectedIds.value.size)

const allSelectableSelected = computed(() =>
    selectableRows.value.length > 0 &&
    selectableRows.value.every(v => selectedIds.value.has(v.id))
)

const someSelectableSelected = computed(() =>
    selectableRows.value.some(v => selectedIds.value.has(v.id))
)

const toggleRow = (v: Vacancy) => {
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

// Shared executor: fan out per-id calls via Promise.allSettled, surface
// success/partial-failure via toast, drop processed ids from the selection.
type BulkOp = {
    ids: string[]
    perform: (id: string) => Promise<unknown>
    pastTense: string                       // e.g. 'opened', 'closed', 'archived'
    failureTitle: string                    // toast title on hard error
}

const runBulk = async ({ ids, perform, pastTense, failureTitle }: BulkOp) => {
    try {
        const results = await Promise.allSettled(ids.map(perform))
        const successes = results.filter(r => r.status === 'fulfilled').length
        const failures = results.length - successes
        ids.forEach(id => selectedIds.value.delete(id))
        await loadVacancies()
        if (failures > 0) {
            toast.info(`Bulk ${pastTense} completed`, `${successes} ${pastTense} · ${failures} failed`)
        } else {
            toast.success(
                `Bulk ${pastTense} complete`,
                `${successes} vacanc${successes === 1 ? 'y' : 'ies'} ${pastTense}.`
            )
        }
    } catch (err: any) {
        toast.error(failureTitle, err?.data?.message)
    }
}

const bulkPublish = async () => {
    if (bulkPublishing.value) return
    const ids = selectedPublishable.value.map(v => v.id)
    if (ids.length === 0) return
    const ok = await toast.confirm({
        title: `Open ${ids.length} draft vacanc${ids.length === 1 ? 'y' : 'ies'}?`,
        description: 'Each selected draft will become open and immediately visible to candidates. You can pause or close them later.',
        confirmLabel: 'Open all',
        color: 'primary',
        icon: 'ti-send'
    })
    if (!ok) return

    bulkPublishing.value = true
    try {
        await runBulk({
            ids,
            perform: id => api.post(`/job-vacancies/${id}/publish`),
            pastTense: 'opened',
            failureTitle: 'Bulk open failed.'
        })
    } finally {
        bulkPublishing.value = false
    }
}

const bulkClose = async () => {
    if (bulkClosing.value) return
    const ids = selectedCloseable.value.map(v => v.id)
    if (ids.length === 0) return
    const ok = await toast.confirm({
        title: `Close ${ids.length} vacanc${ids.length === 1 ? 'y' : 'ies'}?`,
        description: 'Each will stop accepting new applications. To mark a single vacancy as "filled" instead, use the per-row action.',
        confirmLabel: 'Close all',
        color: 'warning',
        icon: 'ti-lock'
    })
    if (!ok) return

    bulkClosing.value = true
    try {
        await runBulk({
            ids,
            perform: id => api.post(`/job-vacancies/${id}/close`, { reason: 'closed' }),
            pastTense: 'closed',
            failureTitle: 'Bulk close failed.'
        })
    } finally {
        bulkClosing.value = false
    }
}

const bulkArchive = async () => {
    if (bulkArchiving.value) return
    const ids = selectedArchivable.value.map(v => v.id)
    if (ids.length === 0) return
    const ok = await toast.confirm({
        title: `Archive ${ids.length} vacanc${ids.length === 1 ? 'y' : 'ies'}?`,
        description: 'Archived vacancies are removed from the active list. Existing applications remain linked and searchable.',
        confirmLabel: 'Archive all',
        color: 'danger',
        icon: 'ti-trash'
    })
    if (!ok) return

    bulkArchiving.value = true
    try {
        await runBulk({
            ids,
            perform: id => api.delete(`/job-vacancies/${id}`),
            pastTense: 'archived',
            failureTitle: 'Bulk archive failed.'
        })
    } finally {
        bulkArchiving.value = false
    }
}

const statusVariant = (s: string): 'primary' | 'success' | 'warning' | 'danger' | 'secondary' => {
    if (s === 'open') return 'success'
    if (s === 'paused') return 'warning'
    if (s === 'closed') return 'danger'
    if (s === 'filled') return 'primary'
    return 'secondary'
}

const formatMoney = (n: number) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(n)

const loadLookups = async () => {
    try {
        const [d, p] = await Promise.all([
            api.get<Paginated<{ id: string; name: string }>>('/departments?limit=100'),
            api.get<Paginated<{ id: string; title: string }>>('/positions?limit=100')
        ])
        departments.value = d.data
        positions.value = p.data
    } catch (err) {
        console.error('Failed to load lookups', err)
    }
}

const loadVacancies = async () => {
    loading.value = true
    try {
        const q = new URLSearchParams({ page: String(pagination.page), limit: String(pagination.limit) })
        if (filters.search) q.set('search', filters.search)
        if (filters.status) q.set('status', filters.status)
        if (filters.departmentId) q.set('departmentId', filters.departmentId)
        if (filters.employmentType) q.set('employmentType', filters.employmentType)

        const res = await api.get<Paginated<Vacancy>>(`/job-vacancies?${q.toString()}`)
        vacancies.value = res.data
        pagination.total = res.pagination.total
        pagination.totalPages = res.pagination.totalPages
    } catch (err) {
        console.error('Failed to load vacancies', err)
        vacancies.value = []
    } finally {
        loading.value = false
    }
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(() => [filters.search, filters.status, filters.departmentId, filters.employmentType], () => {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        pagination.page = 1
        loadVacancies()
    }, 300)
})

const resetForm = () => {
    Object.assign(form, {
        title: '', description: '', location: '', employment_type: 'full_time',
        experience_min_years: null, experience_max_years: null,
        salary_min: null, salary_max: null,
        vacancies_count: 1, status: 'draft', closes_at: '',
        department_id: '', position_id: ''
    })
    formError.value = null
}

const openCreateModal = () => { editing.value = null; resetForm(); showModal.value = true }

const openEditModal = (v: Vacancy) => {
    editing.value = v
    Object.assign(form, {
        title: v.title,
        description: v.description ?? '',
        location: v.location ?? '',
        employment_type: v.employmentType,
        experience_min_years: v.experienceMinYears,
        experience_max_years: v.experienceMaxYears,
        salary_min: v.salaryMin,
        salary_max: v.salaryMax,
        vacancies_count: v.vacanciesCount,
        status: v.status,
        closes_at: v.closesAt ?? '',
        department_id: v.department?.id ?? '',
        position_id: v.position?.id ?? ''
    })
    formError.value = null
    showModal.value = true
}

const closeModal = () => { showModal.value = false; editing.value = null }

const saveVacancy = async () => {
    saving.value = true
    formError.value = null
    const isEdit = !!editing.value
    try {
        const payload: Record<string, any> = { ...form }
        if (!payload.department_id) payload.department_id = null
        if (!payload.position_id) payload.position_id = null
        if (!payload.closes_at) payload.closes_at = null
        if (!payload.location) payload.location = null
        if (!payload.description) payload.description = null

        if (editing.value) {
            await api.put(`/job-vacancies/${editing.value.id}`, payload)
        } else {
            await api.post('/job-vacancies', payload)
        }
        showModal.value = false
        await loadVacancies()
        toast.success(
            isEdit ? 'Vacancy updated.' : 'Vacancy created.',
            `"${payload.title}" is now ${payload.status === 'open' ? 'open for applications' : 'saved as draft'}.`
        )
    } catch (err: any) {
        const detail = err.data?.message || 'Failed to save vacancy.'
        formError.value = detail
        toast.error(isEdit ? 'Could not update vacancy.' : 'Could not create vacancy.', detail)
    } finally {
        saving.value = false
    }
}

const publish = async (v: Vacancy) => {
    const ok = await toast.confirm({
        title: `Publish "${v.title}"?`,
        description: 'The vacancy will become visible and open for applications. You can pause or close it again later.',
        confirmLabel: 'Publish vacancy',
        color: 'primary',
        icon: 'ti-send'
    })
    if (!ok) return
    try {
        await api.post(`/job-vacancies/${v.id}/publish`)
        await loadVacancies()
        toast.success('Vacancy published.', `"${v.title}" is now open for applications.`)
    } catch (err: any) {
        toast.error('Could not publish vacancy.', err.data?.message)
    }
}

const closeVacancy = async (v: Vacancy) => {
    const reason = prompt('Close reason? Type "filled" if the role has been filled, or "closed" to close without filling.', 'closed')
    if (!reason || !['closed', 'filled'].includes(reason)) return
    try {
        await api.post(`/job-vacancies/${v.id}/close`, { reason })
        await loadVacancies()
        toast.warning(
            reason === 'filled' ? 'Vacancy marked as filled.' : 'Vacancy closed.',
            `"${v.title}" no longer accepts new applications.`
        )
    } catch (err: any) {
        toast.error('Could not close vacancy.', err.data?.message)
    }
}

const archive = async (v: Vacancy) => {
    if (!confirm(`Archive "${v.title}"? It will be removed from the list.`)) return
    try {
        await api.delete(`/job-vacancies/${v.id}`)
        await loadVacancies()
        toast.info('Vacancy archived.', `"${v.title}" was removed from the active list.`)
    } catch (err: any) {
        toast.error('Could not archive vacancy.', err.data?.message)
    }
}

const openActionMenu = (v: Vacancy, ev: MouseEvent) => {
    const target = ev.currentTarget as HTMLElement
    const rect = target.getBoundingClientRect()
    const menuWidth = 200
    const menuMaxHeight = 240
    const left = Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8)
    const wouldOverflow = rect.bottom + menuMaxHeight + 8 > window.innerHeight
    actionMenu.vacancy = v
    actionMenu.x = Math.max(8, left)
    actionMenu.y = wouldOverflow ? rect.top - menuMaxHeight - 6 : rect.bottom + 6
    actionMenu.open = true
}

const closeActionMenu = () => {
    actionMenu.open = false
    actionMenu.vacancy = null
}

const actionEdit = () => {
    const v = actionMenu.vacancy
    closeActionMenu()
    if (v) openEditModal(v)
}

const actionPublish = () => {
    const v = actionMenu.vacancy
    closeActionMenu()
    if (v) publish(v)
}

const actionClose = () => {
    const v = actionMenu.vacancy
    closeActionMenu()
    if (v) closeVacancy(v)
}

const actionArchive = () => {
    const v = actionMenu.vacancy
    closeActionMenu()
    if (v) archive(v)
}

onMounted(async () => {
    if (import.meta.client) {
        document.addEventListener('click', closeActionMenu)
    }
    await Promise.all([loadLookups(), loadVacancies()])
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

.action-item-primary {
    color: var(--color-primary);
    font-weight: 600;
}

.action-item-primary:hover {
    background: var(--color-primary-subtle);
}

.action-item-warning {
    color: var(--color-warning);
}

.action-item-warning:hover {
    background: var(--color-warning-subtle, rgb(var(--color-warning-rgb, 250 173 20) / 0.1));
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
