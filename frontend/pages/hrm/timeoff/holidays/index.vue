<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Holidays</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Public, company, and optional days off. Recurring entries fire on the same month/day every year so you only have to enter them once.</p>
                </div>
                <button v-if="canWrite" type="button" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />New Holiday
                </button>
            </header>

            <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Total</span>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ kpiCountAnim }}</p>
                    <p class="text-xxs text-(--text-muted)">{{ recurringCount }} recurring</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Public</span>
                    <p class="text-2xl font-bold font-mono text-(--color-success)">{{ kpiPublicAnim }}</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Company</span>
                    <p class="text-2xl font-bold font-mono text-(--color-primary)">{{ kpiCompanyAnim }}</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Optional</span>
                    <p class="text-2xl font-bold font-mono text-(--color-info)">{{ kpiOptionalAnim }}</p>
                </div>
            </section>

            <section class="flex items-center gap-2 flex-wrap max-sm:justify-center">
                <button type="button" class="chip" :class="{ active: typeFilter === '' }" @click="setTypeFilter('')">All</button>
                <button v-for="t in HOLIDAY_TYPES" :key="t.value" type="button"
                    class="chip" :class="{ active: typeFilter === t.value }" @click="setTypeFilter(t.value)">
                    {{ t.label }}
                </button>
                <div class="ml-auto flex items-center gap-2">
                    <input v-model.lazy="search" type="search" placeholder="search by name..."
                        class="form-control text-xs w-56" @keyup.enter="load" @change="load" />
                </div>
            </section>

            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading holidays...</span>
            </div>
            <div v-else-if="holidays.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-confetti-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No holidays defined</h4>
                <p class="text-xs text-(--text-muted) mt-1">Add the first one to start populating the calendar.</p>
            </div>

            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full text-xs">
                        <thead class="bg-(--bg-muted)/40 text-(--text-muted)">
                            <tr>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3 w-32">Date</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Name</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3 w-24">Type</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3 w-24">Recurring</th>
                                <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Notes</th>
                                <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3 w-20">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="h in holidays" :key="h.id" class="border-t border-(--border-color) hover:bg-(--bg-muted)/40">
                                <td class="px-3 py-2 font-mono">
                                    <p class="text-(--text-heading)">{{ formatDate(h.date) }}</p>
                                    <p class="text-xxs text-(--text-muted)">{{ weekdayOf(h.date) }}</p>
                                </td>
                                <td class="px-3 py-2 text-(--text-heading) font-semibold">{{ h.name }}</td>
                                <td class="px-3 py-2">
                                    <span class="text-xxs px-1.5 py-0.5 rounded font-mono" :class="typeBadge(h.type)">{{ h.type }}</span>
                                </td>
                                <td class="px-3 py-2">
                                    <span v-if="h.isRecurring" class="text-xxs px-1.5 py-0.5 rounded font-mono badge-soft-warning">
                                        <i class="ti ti-refresh text-[10px]" /> yearly
                                    </span>
                                    <span v-else class="text-xxs text-(--text-muted)">once</span>
                                </td>
                                <td class="px-3 py-2 text-xxs text-(--text-muted) truncate max-w-sm">{{ h.notes || '-' }}</td>
                                <td class="px-3 py-2 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <button v-if="canWrite" type="button" class="action-btn" title="Edit" @click="openEditModal(h)">
                                            <i class="ti ti-pencil text-xs" />
                                        </button>
                                        <button v-if="canDelete" type="button" class="action-btn action-btn-danger" title="Delete"
                                            @click="confirmDelete(h)">
                                            <i class="ti ti-trash text-xs" />
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

        <div v-if="showFormModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-xl bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">{{ editingId ? 'Edit Holiday' : 'New Holiday' }}</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="post">
                    <div class="p-5 space-y-4">
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Name *</label>
                            <input v-model="form.name" type="text" required maxlength="200"
                                placeholder="e.g. New Year's Day" class="form-control text-xs" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Date *</label>
                                <input v-model="form.date" type="date" required class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Type</label>
                                <select v-model="form.type" class="form-control text-xs">
                                    <option v-for="t in HOLIDAY_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                                </select>
                            </div>
                        </div>
                        <label class="flex items-center gap-2 text-xs cursor-pointer">
                            <input v-model="form.is_recurring" type="checkbox" />
                            <span>Recurring (fires on the same month/day every year)</span>
                        </label>
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Notes</label>
                            <textarea v-model="form.notes" rows="2" maxlength="2000"
                                class="form-control text-xs resize-none" />
                        </div>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showFormModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="!canSubmit || posting">
                            <i v-if="posting" class="ti ti-loader-2 animate-spin" />
                            {{ editingId ? 'Save' : 'Create' }}
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Delete Holiday</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="deleteTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <p class="text-xs text-(--text-muted)">
                        Delete <span class="font-semibold text-(--text-heading)">{{ deleteTarget.name }}</span>?
                        <span v-if="deleteTarget.isRecurring">All future yearly occurrences will stop.</span>
                    </p>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="deleteTarget = null">Cancel</button>
                    <button type="button" class="btn btn-danger text-xs" :disabled="deleting" @click="onConfirmDelete">
                        <i v-if="deleting" class="ti ti-loader-2 animate-spin" />
                        Delete
                    </button>
                </footer>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useHrmCalendar, HOLIDAY_TYPES } from '~/composables/useHrmCalendar'
import { useToast } from '~/composables/useToast'
import { useCountUp } from '~/composables/useCountUp'
import { useAuthStore } from '~/stores/auth'
import type { Holiday, HolidayType, CreateHolidayPayload } from '~/types/hrm-calendar'

definePageMeta({ breadcrumb: 'Holidays' })

const hrm = useHrmCalendar()
const toast = useToast()
const authStore = useAuthStore()

const canRead   = computed(() => authStore.hasPermission('hrm.holiday.read'))
const canWrite  = computed(() => authStore.hasPermission('hrm.holiday.write'))
const canDelete = computed(() => authStore.hasPermission('hrm.holiday.delete'))

const loading = ref(false)
const posting = ref(false)
const deleting = ref(false)

const holidays = ref<Holiday[]>([])
const pagination = reactive({ page: 1, limit: 25, total: 0, totalPages: 1 })
const typeFilter = ref<'' | HolidayType>('')
const search = ref('')

const WEEKDAYS = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']
const formatDate = (s: string | null) => {
    if (!s) return '-'
    const d = new Date(s)
    return isNaN(d.getTime()) ? s : d.toISOString().slice(0, 10)
}
const weekdayOf = (s: string | null) => {
    if (!s) return ''
    const d = new Date(s)
    return isNaN(d.getTime()) ? '' : WEEKDAYS[d.getDay()]
}
const typeBadge = (t: HolidayType) =>
    HOLIDAY_TYPES.find(x => x.value === t)?.badge ?? 'badge-soft-secondary'

const recurringCount = computed(() => holidays.value.filter(h => h.isRecurring).length)
const publicCount    = computed(() => holidays.value.filter(h => h.type === 'public').length)
const companyCount   = computed(() => holidays.value.filter(h => h.type === 'company').length)
const optionalCount  = computed(() => holidays.value.filter(h => h.type === 'optional').length)

const kpiCountAnim    = useCountUp(() => holidays.value.length)
const kpiPublicAnim   = useCountUp(() => publicCount.value)
const kpiCompanyAnim  = useCountUp(() => companyCount.value)
const kpiOptionalAnim = useCountUp(() => optionalCount.value)

const load = async () => {
    if (!canRead.value) return
    loading.value = true
    try {
        const res = await hrm.holidays.list({
            page: pagination.page,
            limit: pagination.limit,
            type: typeFilter.value || undefined,
            search: search.value.trim() || undefined,
        })
        holidays.value = res.data
        const p = (res as any).pagination
        if (p) {
            pagination.total = p.total ?? 0
            pagination.totalPages = p.totalPages ?? 1
            pagination.page = p.page ?? 1
            pagination.limit = p.limit ?? pagination.limit
        }
    } catch (err: any) {
        toast.error('Failed to load holidays', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const setTypeFilter = (t: '' | HolidayType) => {
    if (typeFilter.value === t) return
    typeFilter.value = t
    pagination.page = 1
    load()
}

const showFormModal = ref(false)
const editingId = ref<string | null>(null)

const blankForm = (): CreateHolidayPayload => ({
    name: '',
    date: new Date().toISOString().slice(0, 10),
    type: 'public',
    is_recurring: false,
    notes: null,
})

const form = reactive<CreateHolidayPayload>(blankForm())

const openCreateModal = () => {
    editingId.value = null
    Object.assign(form, blankForm())
    showFormModal.value = true
}

const openEditModal = (h: Holiday) => {
    editingId.value = h.id
    Object.assign(form, {
        name: h.name,
        date: h.date,
        type: h.type,
        is_recurring: h.isRecurring,
        notes: h.notes,
    })
    showFormModal.value = true
}

const canSubmit = computed(() => !!form.name.trim() && !!form.date)

const post = async () => {
    if (!canSubmit.value) return
    posting.value = true
    try {
        const payload: CreateHolidayPayload = {
            ...form,
            name: form.name.trim(),
            notes: form.notes?.toString().trim() || null,
        }
        if (editingId.value) {
            await hrm.holidays.update(editingId.value, payload)
            toast.success('Holiday updated')
        } else {
            await hrm.holidays.create(payload)
            toast.success('Holiday created')
        }
        showFormModal.value = false
        await load()
    } catch (err: any) {
        toast.error('Save failed', err?.data?.message)
    } finally {
        posting.value = false
    }
}

const deleteTarget = ref<Holiday | null>(null)
const confirmDelete = (h: Holiday) => { deleteTarget.value = h }
const onConfirmDelete = async () => {
    if (!deleteTarget.value) return
    deleting.value = true
    try {
        await hrm.holidays.destroy(deleteTarget.value.id)
        toast.success('Holiday deleted')
        deleteTarget.value = null
        await load()
    } catch (err: any) {
        toast.error('Delete failed', err?.data?.message)
    } finally {
        deleting.value = false
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
