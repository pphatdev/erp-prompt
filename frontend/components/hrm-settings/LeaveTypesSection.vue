<template>
    <div class="space-y-5">
        <!-- Page header (mirrors Shop > All Products) -->
        <header>
            <h2 class="text-xl font-semibold text-(--text-heading) leading-tight">{{ pageTitle }}</h2>
            <p class="text-xs text-(--text-muted) mt-1">{{ pageHint }}</p>
        </header>

        <!-- Sticky toolbar -->
        <section class="sticky top-16 z-20 py-2 bg-(--bg-layout)/90 backdrop-blur">
            <div class="flex items-center gap-3 flex-wrap">
                <!-- Search -->
                <div class="relative flex-1 min-w-[220px] max-w-md">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
                    <input v-model="searchDraft" type="search" placeholder="Search leave types..."
                        class="search-input w-full pl-9 pr-9 py-2 text-xs rounded-lg bg-(--bg-card) border border-(--border-color) text-(--text-heading) placeholder:text-(--text-muted) focus:outline-none focus:border-(--color-primary) focus:ring-2 focus:ring-(--color-primary)/20"
                        @keyup.escape="clearSearch" />
                    <button v-if="searchDraft" type="button"
                        class="absolute right-2 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full inline-flex items-center justify-center text-(--text-muted) hover:bg-(--bg-muted) hover:text-(--text-heading)"
                        aria-label="Clear search" @click="clearSearch">
                        <i class="ti ti-x text-[12px]" />
                    </button>
                </div>

                <div class="ml-auto flex items-center gap-2 flex-wrap max-sm:justify-center">
                    <!-- Sort segmented -->
                    <div class="segmented" role="group" aria-label="Sort">
                        <button type="button" class="seg-btn" :class="{ active: sort === 'newest' }"
                            :aria-pressed="sort === 'newest'" @click="sort = 'newest'">
                            <i class="ti ti-sparkles" /> Newest
                        </button>
                        <button type="button" class="seg-btn" :class="{ active: sort === 'name' }"
                            :aria-pressed="sort === 'name'" @click="sort = 'name'">
                            <i class="ti ti-sort-ascending-letters" /> Name
                        </button>
                        <button type="button" class="seg-btn" :class="{ active: sort === 'allowance' }"
                            :aria-pressed="sort === 'allowance'" @click="sort = 'allowance'">
                            <i class="ti ti-sort-descending-numbers" /> Allowance
                        </button>
                    </div>

                    <button class="btn btn-primary text-xs" @click="openCreateModal">
                        <i class="ti ti-plus" /> New leave type
                    </button>
                </div>
            </div>

            <!-- Active filter chips -->
            <div v-if="activeChips.length > 0" class="flex items-center gap-2 flex-wrap max-sm:justify-center pt-3">
                <span class="text-xxs uppercase tracking-wider text-(--text-muted) font-semibold">Filtered by</span>
                <button v-for="c in activeChips" :key="c.key" type="button" class="active-filter-chip"
                    @click="c.remove">
                    <span class="text-(--text-muted)">{{ c.label }}</span>
                    <span class="text-(--text-heading) font-semibold">{{ c.value }}</span>
                    <i class="ti ti-x text-[10px] text-(--text-muted)" />
                </button>
            </div>
        </section>

        <!-- Results summary -->
        <div v-if="!loading" class="flex items-center justify-between text-xxs text-(--text-muted)">
            <span>{{ resultsSummary }}</span>
            <span v-if="pagination.totalPages > 1" class="font-mono">
                Page {{ pagination.page }} / {{ pagination.totalPages }}
            </span>
        </div>

        <!-- Loading skeleton -->
        <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4">
            <div v-for="i in 8" :key="i" class="glass-card rounded-2xl p-4 space-y-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-(--bg-muted) animate-pulse" />
                    <div class="flex-1 space-y-2">
                        <div class="h-3 w-2/3 bg-(--bg-muted) rounded animate-pulse" />
                        <div class="h-2 w-1/3 bg-(--bg-muted) rounded animate-pulse" />
                    </div>
                </div>
                <div class="h-2 w-full bg-(--bg-muted) rounded animate-pulse" />
            </div>
        </div>

        <!-- Empty state -->
        <div v-else-if="filteredTypes.length === 0" class="glass-card rounded-2xl py-20 text-center">
            <i class="ti ti-mood-empty text-4xl text-(--text-muted)" />
            <h4 class="text-sm font-semibold text-(--text-heading) mt-3">
                {{ types.length === 0 ? 'No leave types yet' : 'No matches' }}
            </h4>
            <p class="text-xs text-(--text-muted) mt-1">
                {{ types.length === 0
                    ? 'Define categories such as Annual, Sick, Maternity before employees can request leave.'
                    : 'Try clearing the search.' }}
            </p>
            <button v-if="types.length === 0" class="btn btn-soft-primary text-xs mt-4 inline-flex items-center gap-2"
                @click="openCreateModal">
                <i class="ti ti-plus" /> Create first leave type
            </button>
            <button v-else-if="searchDraft"
                class="btn btn-soft-primary text-xs mt-4 inline-flex items-center gap-2" @click="clearSearch">
                <i class="ti ti-restore" /> Clear search
            </button>
        </div>

        <!-- Cards grid -->
        <section v-else class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4">
                <article v-for="t in filteredTypes" :key="t.id" class="type-card group">
                    <header class="flex items-start gap-3">
                        <span class="w-10 h-10 rounded-lg bg-(--color-primary-subtle) text-(--color-primary) flex items-center justify-center shrink-0">
                            <i class="ti ti-calendar-event text-base" />
                        </span>
                        <div class="flex-1 min-w-0">
                            <h3 class="text-sm font-semibold text-(--text-heading) truncate">{{ t.name }}</h3>
                            <p class="text-xxs font-mono text-(--text-muted) mt-0.5">
                                Created {{ formatDate(t.createdAt) }}
                            </p>
                            <span v-if="t.applicableGender && t.applicableGender !== 'any'"
                                class="gender-badge mt-1.5"
                                :class="t.applicableGender === 'female' ? 'gender-badge-female' : 'gender-badge-male'">
                                <i :class="['ti', genderMeta(t.applicableGender).icon]" />
                                {{ genderMeta(t.applicableGender).label }}
                            </span>
                        </div>
                        <button type="button" class="action-trigger"
                            :class="{ 'action-trigger-open': actionMenu.open && actionMenu.type?.id === t.id }"
                            title="Actions" @click.stop="openActionMenu(t, $event)">
                            <i class="ti ti-dots-vertical" />
                        </button>
                    </header>
                    <footer class="mt-4 pt-3 border-t border-(--border-color)/60 flex items-center justify-between">
                        <span class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Annual allowance</span>
                        <span class="text-base font-mono font-semibold text-(--text-heading)">
                            {{ t.annualAllowance }}<span class="text-xxs text-(--text-muted) ml-0.5">d</span>
                        </span>
                    </footer>
                </article>
            </div>

            <!-- Pagination -->
            <div v-if="pagination.totalPages > 1" class="flex items-center justify-center gap-2 pt-2">
                <button class="btn btn-ghost text-xs" :disabled="pagination.page <= 1"
                    @click="setPage(pagination.page - 1)">
                    <i class="ti ti-chevron-left" /> Prev
                </button>
                <div class="flex gap-1">
                    <button v-for="n in pageNumbers" :key="n"
                        :class="['btn text-xs w-9', n === pagination.page ? 'btn-primary' : 'btn-ghost']"
                        @click="setPage(n)">
                        {{ n }}
                    </button>
                </div>
                <button class="btn btn-ghost text-xs" :disabled="pagination.page >= pagination.totalPages"
                    @click="setPage(pagination.page + 1)">
                    Next <i class="ti ti-chevron-right" />
                </button>
            </div>
        </section>

        <!-- Modal -->
        <div v-if="showModal"
            class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                <header class="flex items-center justify-between mb-5">
                    <h3 class="text-base font-semibold text-(--text-heading)">
                        {{ editing ? 'Edit leave type' : 'New leave type' }}
                    </h3>
                    <button class="topbar-btn" @click="closeModal"><i class="ti ti-x" /></button>
                </header>

                <form class="space-y-4" @submit.prevent="saveType">
                    <div>
                        <label class="form-label">Name</label>
                        <input v-model="form.name" type="text" required class="form-control" placeholder="Annual Leave" />
                    </div>
                    <div>
                        <label class="form-label">Annual allowance (days)</label>
                        <input v-model.number="form.annual_allowance" type="number" min="0" max="365" required
                            class="form-control font-mono" />
                    </div>
                    <div>
                        <label class="form-label">Applies to</label>
                        <div class="gender-segmented" role="radiogroup" aria-label="Applicable gender">
                            <button v-for="opt in GENDER_OPTIONS" :key="opt.value" type="button"
                                class="gender-seg-btn" :class="{ active: form.applicable_gender === opt.value }"
                                :aria-pressed="form.applicable_gender === opt.value"
                                @click="form.applicable_gender = opt.value">
                                <i :class="['ti', opt.icon]" />
                                <span>{{ opt.label }}</span>
                            </button>
                        </div>
                        <p class="text-xxs text-(--text-muted) mt-1.5">
                            Leaves of this type may only be requested by matching employees.
                        </p>
                    </div>

                    <div v-if="formError"
                        class="text-xs text-(--color-danger) bg-(--color-danger-subtle) px-3 py-2 rounded">
                        {{ formError }}
                    </div>

                    <footer class="pt-4 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="closeModal">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="saving">
                            <i class="ti ti-device-floppy" />{{ saving ? 'Saving...' : 'Save' }}
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Action dropdown -->
        <div v-if="actionMenu.open && actionMenu.type"
            class="fixed z-50 glass-card rounded-lg shadow-(--shadow-lg) bg-(--bg-card) border border-(--border-color) py-1 min-w-[180px]"
            :style="{ top: actionMenu.y + 'px', left: actionMenu.x + 'px' }" @click.stop>
            <button class="action-item" @click="actionEdit">
                <i class="ti ti-pencil" /> Edit
            </button>
            <hr class="my-1 border-(--border-color)" />
            <button class="action-item action-item-danger" @click="actionRemove">
                <i class="ti ti-trash" /> Remove
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, reactive, ref } from 'vue'
import { useApi } from '~/composables/useApi'
import { formatDate } from '~/composables/useDateFormat'
import { useToast } from '~/composables/useToast'

type ApplicableGender = 'any' | 'male' | 'female'
interface LeaveType {
    id: string;
    name: string;
    annualAllowance: number;
    applicableGender: ApplicableGender;
    createdAt: string | null;
}
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

type SortKey = 'newest' | 'name' | 'allowance'

const api = useApi()
const toast = useToast()
const types = ref<LeaveType[]>([])
const loading = ref(false)

const pagination = reactive({ page: 1, limit: 16, total: 0, totalPages: 1 })

const showModal = ref(false)
const editing = ref<LeaveType | null>(null)
const saving = ref(false)
const formError = ref<string | null>(null)
const form = reactive({ name: '', annual_allowance: 0, applicable_gender: 'any' as ApplicableGender })

const GENDER_OPTIONS: { value: ApplicableGender; label: string; icon: string }[] = [
    { value: 'any',    label: 'All employees', icon: 'ti-users' },
    { value: 'female', label: 'Female only',   icon: 'ti-gender-female' },
    { value: 'male',   label: 'Male only',     icon: 'ti-gender-male' },
]

const genderMeta = (g: ApplicableGender) => GENDER_OPTIONS.find(o => o.value === g) ?? GENDER_OPTIONS[0]

const searchDraft = ref('')
const sort = ref<SortKey>('newest')

const actionMenu = reactive({
    open: false,
    x: 0,
    y: 0,
    type: null as LeaveType | null
})

// Header / hint mirror Shop > All Products narrative tone.
const pageTitle = computed(() => 'Leave types')
const pageHint = computed(() => {
    const total = pagination.total
    if (total === 0) return 'Define the leave categories your employees can request.'
    const noun = total === 1 ? 'type' : 'types'
    return `${total.toLocaleString()} ${noun} configured for this tenant.`
})

const resultsSummary = computed(() => {
    const total = pagination.total
    if (total === 0) return ''
    const limit = pagination.limit
    const start = (pagination.page - 1) * limit + 1
    const end = Math.min(start + types.value.length - 1, total)
    return `Showing ${start.toLocaleString()}-${end.toLocaleString()} of ${total.toLocaleString()}`
})

interface Chip { key: string; label: string; value: string; remove: () => void }
const activeChips = computed<Chip[]>(() => {
    const chips: Chip[] = []
    if (searchDraft.value) {
        chips.push({ key: 'search', label: 'Search', value: `"${searchDraft.value}"`, remove: clearSearch })
    }
    return chips
})

const filteredTypes = computed(() => {
    const term = searchDraft.value.trim().toLowerCase()
    let list = !term ? [...types.value] : types.value.filter(t => t.name.toLowerCase().includes(term))
    if (sort.value === 'name') {
        list.sort((a, b) => a.name.localeCompare(b.name))
    } else if (sort.value === 'allowance') {
        list.sort((a, b) => b.annualAllowance - a.annualAllowance)
    }
    return list
})

const pageNumbers = computed(() => {
    const total = pagination.totalPages
    const cur = pagination.page
    if (total <= 5) return Array.from({ length: total }, (_, i) => i + 1)
    const win = new Set<number>([1, total, cur, cur - 1, cur + 1])
    return Array.from(win).filter(n => n >= 1 && n <= total).sort((a, b) => a - b)
})

const loadTypes = async () => {
    loading.value = true
    try {
        const res = await api.get<Paginated<LeaveType>>(`/leave-types?page=${pagination.page}&limit=${pagination.limit}`)
        types.value = res.data
        pagination.total = res.pagination.total
        pagination.totalPages = res.pagination.totalPages
    } catch (err) {
        console.error('Failed to load leave types', err)
        types.value = []
    } finally {
        loading.value = false
    }
}

const setPage = (n: number) => {
    pagination.page = Math.max(1, Math.min(pagination.totalPages, n))
    loadTypes()
}

const clearSearch = () => {
    searchDraft.value = ''
}

const resetForm = () => {
    form.name = ''
    form.annual_allowance = 0
    form.applicable_gender = 'any'
    formError.value = null
}
const openCreateModal = () => { editing.value = null; resetForm(); showModal.value = true }
const openEditModal = (t: LeaveType) => {
    editing.value = t
    form.name = t.name
    form.annual_allowance = t.annualAllowance
    form.applicable_gender = t.applicableGender || 'any'
    formError.value = null
    showModal.value = true
}
const closeModal = () => { showModal.value = false; editing.value = null }

const saveType = async () => {
    saving.value = true
    formError.value = null
    try {
        if (editing.value) {
            await api.put(`/leave-types/${editing.value.id}`, form)
        } else {
            await api.post('/leave-types', form)
        }
        showModal.value = false
        await loadTypes()
    } catch (err: any) {
        formError.value = err.data?.message || 'Failed to save leave type.'
    } finally {
        saving.value = false
    }
}

const removeType = async (t: LeaveType) => {
    const ok = await toast.confirm({
        title: `Remove "${t.name}"?`,
        description: `Existing leave requests of this type are kept, but new requests will be unable to choose it. Annual allowance: ${t.annualAllowance} day${t.annualAllowance === 1 ? '' : 's'}.`,
        confirmLabel: 'Remove leave type',
        cancelLabel: 'Cancel',
        color: 'danger',
        icon: 'ti-trash',
    })
    if (!ok) return

    try {
        await api.delete(`/leave-types/${t.id}`)
        await loadTypes()
    } catch (err: any) {
        toast.error('Failed to remove leave type.', err?.data?.message)
    }
}

const openActionMenu = (t: LeaveType, ev: MouseEvent) => {
    const rect = (ev.currentTarget as HTMLElement).getBoundingClientRect()
    const menuWidth = 180
    const menuMaxHeight = 120
    const left = Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8)
    const wouldOverflow = rect.bottom + menuMaxHeight + 8 > window.innerHeight
    actionMenu.type = t
    actionMenu.x = Math.max(8, left)
    actionMenu.y = wouldOverflow ? rect.top - menuMaxHeight - 6 : rect.bottom + 6
    actionMenu.open = true
}

const closeActionMenu = () => { actionMenu.open = false; actionMenu.type = null }

const actionEdit = () => {
    const t = actionMenu.type
    closeActionMenu()
    if (t) openEditModal(t)
}

const actionRemove = async () => {
    const t = actionMenu.type
    closeActionMenu()
    if (t) await removeType(t)
}

onMounted(() => {
    if (import.meta.client) {
        document.addEventListener('click', closeActionMenu)
    }
    loadTypes()
})

onBeforeUnmount(() => {
    if (import.meta.client) {
        document.removeEventListener('click', closeActionMenu)
    }
})
</script>

<style scoped>
.form-label {
    display: block;
    font-size: 0.625rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: var(--text-muted);
    margin-bottom: 0.375rem;
}

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

/* Toolbar primitives (mirrors Shop > All Products) */
.segmented {
    display: inline-flex;
    align-items: center;
    padding: 3px;
    border-radius: 999px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
}

.seg-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 999px;
    border: 0;
    background: transparent;
    font-size: 11px;
    color: var(--text-body);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}

.seg-btn:hover {
    color: var(--text-heading);
}

.seg-btn.active {
    background: rgb(var(--color-primary-rgb) / 0.12);
    color: var(--color-primary);
    box-shadow: inset 0 0 0 1px rgb(var(--color-primary-rgb) / 0.25);
}

.search-input {
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.active-filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    font-size: 11px;
    color: var(--text-body);
    cursor: pointer;
    transition: background 0.12s ease, border-color 0.12s ease;
}

.active-filter-chip:hover {
    background: rgb(var(--color-danger-rgb) / 0.08);
    border-color: rgb(var(--color-danger-rgb) / 0.35);
}

.active-filter-chip:hover .ti-x {
    color: var(--color-danger);
}

/* Type card — mirrors ProductCard / day-card patterns */
.type-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 1rem;
    padding: 1rem;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.type-card:hover {
    border-color: rgb(var(--color-primary-rgb) / 0.35);
    box-shadow: 0 2px 8px rgb(var(--color-primary-rgb) / 0.05);
}

/* Action trigger + dropdown items (preserved from the previous version) */
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

/* Applicable-gender segmented control inside the modal. */
.gender-segmented {
    display: inline-flex;
    align-items: stretch;
    width: 100%;
    padding: 3px;
    border-radius: 0.625rem;
    background: var(--bg-muted);
    border: 1px solid var(--border-color);
    gap: 2px;
}

.gender-seg-btn {
    flex: 1 1 0;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 0.5rem 0.5rem;
    border: 0;
    background: transparent;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}

.gender-seg-btn:hover {
    color: var(--text-heading);
}

.gender-seg-btn.active {
    background: var(--bg-card);
    color: var(--color-primary);
    box-shadow: inset 0 0 0 1px rgb(var(--color-primary-rgb) / 0.25);
}

/* Card badge — surfaces gender restriction at a glance. */
.gender-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    line-height: 1;
}

.gender-badge-female {
    background: rgb(236 72 153 / 0.12);
    color: rgb(190 24 93);
    border: 1px solid rgb(236 72 153 / 0.3);
}

.gender-badge-male {
    background: rgb(59 130 246 / 0.12);
    color: rgb(29 78 216);
    border: 1px solid rgb(59 130 246 / 0.3);
}
</style>
