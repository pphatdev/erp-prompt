<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Shifts</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Define working windows. Grace and half-day boundaries
                        drive attendance status resolution.</p>
                </div>
                <button v-if="canWrite" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />New shift
                </button>
            </header>

            <section class="glass-card rounded-xl p-4">
                <div class="relative w-full md:w-96">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                    <input v-model="search" type="search" placeholder="Search shift name..."
                        class="form-control pl-9" />
                </div>
            </section>

            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span
                    class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading shifts...</span>
            </div>

            <div v-else-if="shifts.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-clock-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No shifts yet</h4>
                <p class="text-xs text-(--text-muted) mt-1">Create your first shift to start tracking attendance.</p>
            </div>

            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr
                                class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                <th class="px-4 py-3 font-semibold">Name</th>
                                <th class="px-4 py-3 font-semibold font-mono">Hours</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Grace (min)</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Half-day (min)</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Assigned</th>
                                <th class="px-4 py-3 font-semibold text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-color)">
                            <tr v-for="s in shifts" :key="s.id" class="hover:bg-(--bg-muted) transition-colors">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="w-8 h-8 rounded-lg bg-(--color-primary-subtle) text-(--color-primary) flex items-center justify-center">
                                            <i class="ti ti-clock-hour-8 text-sm" />
                                        </span>
                                        <span class="text-xs font-semibold text-(--text-heading)">{{ s.name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs">{{ shortTime(s.startTime) }} – {{
                                    shortTime(s.endTime) }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-right">{{ s.gracePeriodMinutes }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-right">
                                    {{ s.halfDayThresholdMinutes ?? '—' }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-right">{{ s.assignmentCount ?? 0 }}</td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button" class="action-trigger"
                                        :class="{ 'action-trigger-open': actionMenu.open && actionMenu.shift?.id === s.id }"
                                        title="Actions" @click.stop="openActionMenu(s, $event)">
                                        <i class="ti ti-dots-vertical" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :page="pagination.page" :limit="pagination.limit" :total="pagination.total"
                    :total-pages="pagination.totalPages" @update:page="(p) => { pagination.page = p; loadShifts() }"
                    @update:limit="(l) => { pagination.limit = l; pagination.page = 1; loadShifts() }" />
            </section>

            <!-- Create / edit modal -->
            <div v-if="showModal"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="glass-card rounded-2xl w-full max-w-lg p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                    <header class="flex items-center justify-between mb-5">
                        <h3 class="text-base font-semibold text-(--text-heading)">{{ editing ? 'Edit shift' : 'New shift' }}</h3>
                        <button class="topbar-btn" @click="closeModal"><i class="ti ti-x" /></button>
                    </header>

                    <form class="space-y-4" @submit.prevent="saveShift">
                        <div>
                            <label class="form-label form-label-required">Name</label>
                            <input v-model="form.name" type="text" required class="form-control"
                                placeholder="Day Shift" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label form-label-required">Start time</label>
                                <input v-model="form.start_time" type="time" required class="form-control font-mono"
                                    step="60" />
                            </div>
                            <div>
                                <label class="form-label form-label-required">End time</label>
                                <input v-model="form.end_time" type="time" required class="form-control font-mono"
                                    step="60" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label">Grace period (min)</label>
                                <input v-model.number="form.grace_period_minutes" type="number" min="0" max="240"
                                    class="form-control font-mono" />
                                <p class="text-xxs text-(--text-muted) mt-1">Up to this many minutes late is still
                                    "present".</p>
                            </div>
                            <div>
                                <label class="form-label">Half-day threshold (min)</label>
                                <input v-model.number="form.half_day_threshold_minutes" type="number" min="0" max="480"
                                    class="form-control font-mono" />
                                <p class="text-xxs text-(--text-muted) mt-1">Beyond this, the day is recorded as
                                    half-day absent.</p>
                            </div>
                        </div>

                        <div v-if="formError" class="form-error">{{ formError }}</div>

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
            <div v-if="actionMenu.open && actionMenu.shift"
                class="fixed z-50 glass-card rounded-lg shadow-(--shadow-lg) bg-(--bg-card) border border-(--border-color) py-1 min-w-[180px]"
                :style="{ top: actionMenu.y + 'px', left: actionMenu.x + 'px' }" @click.stop>
                <button class="action-item" @click="actionEdit">
                    <i class="ti ti-pencil" /> Edit
                </button>
                <template v-if="canDelete">
                    <hr class="my-1 border-(--border-color)" />
                    <button class="action-item action-item-danger" @click="actionDelete">
                        <i class="ti ti-trash" /> Delete
                    </button>
                </template>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'

interface Shift {
    id: string
    name: string
    startTime: string
    endTime: string
    gracePeriodMinutes: number
    halfDayThresholdMinutes: number | null
    assignmentCount?: number
}
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

const api = useApi()
const authStore = useAuthStore()
const toast = useToast()
const canWrite = computed(() => authStore.hasPermission('hrm.shift.write'))
const canDelete = computed(() => authStore.hasPermission('hrm.shift.delete'))

const shifts = ref<Shift[]>([])
const loading = ref(false)
const search = ref('')
const pagination = reactive({ page: 1, limit: 15, total: 0, totalPages: 1 })

const showModal = ref(false)
const editing = ref<Shift | null>(null)
const saving = ref(false)
const formError = ref<string | null>(null)
const form = reactive({
    name: '',
    start_time: '08:00',
    end_time: '17:00',
    grace_period_minutes: 15 as number | null,
    half_day_threshold_minutes: 120 as number | null
})

const actionMenu = reactive({
    open: false,
    x: 0,
    y: 0,
    shift: null as Shift | null
})

const shortTime = (t: string | null | undefined): string => {
    if (!t) return '—'
    // Backend returns "HH:MM:SS"; surface "HH:MM" for the table.
    return t.slice(0, 5)
}

const toSeconds = (hhmm: string): string => {
    if (!hhmm) return ''
    return hhmm.length === 5 ? `${hhmm}:00` : hhmm
}

const loadShifts = async () => {
    loading.value = true
    try {
        const q = new URLSearchParams({ page: String(pagination.page), limit: String(pagination.limit) })
        if (search.value) q.set('search', search.value)
        const res = await api.get<Paginated<Shift>>(`/hrm/shifts?${q.toString()}`)
        shifts.value = res.data
        pagination.total = res.pagination.total
        pagination.totalPages = res.pagination.totalPages
    } catch (err) {
        console.error('Failed to load shifts', err)
        shifts.value = []
    } finally {
        loading.value = false
    }
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(search, () => {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        pagination.page = 1
        loadShifts()
    }, 300)
})

const resetForm = () => {
    Object.assign(form, {
        name: '',
        start_time: '08:00',
        end_time: '17:00',
        grace_period_minutes: 15,
        half_day_threshold_minutes: 120
    })
    formError.value = null
}

const openCreateModal = () => { editing.value = null; resetForm(); showModal.value = true }
const openEditModal = (s: Shift) => {
    editing.value = s
    Object.assign(form, {
        name: s.name,
        start_time: shortTime(s.startTime),
        end_time: shortTime(s.endTime),
        grace_period_minutes: s.gracePeriodMinutes,
        half_day_threshold_minutes: s.halfDayThresholdMinutes
    })
    formError.value = null
    showModal.value = true
}
const closeModal = () => { showModal.value = false; editing.value = null }

const saveShift = async () => {
    saving.value = true
    formError.value = null
    try {
        const payload = {
            name: form.name,
            start_time: toSeconds(form.start_time),
            end_time: toSeconds(form.end_time),
            grace_period_minutes: form.grace_period_minutes ?? 0,
            half_day_threshold_minutes: form.half_day_threshold_minutes
        }
        if (editing.value) {
            await api.put(`/hrm/shifts/${editing.value.id}`, payload)
        } else {
            await api.post('/hrm/shifts', payload)
        }
        showModal.value = false
        await loadShifts()
        toast.success(editing.value ? 'Shift updated.' : 'Shift created.')
    } catch (err: any) {
        formError.value = err?.data?.message || 'Failed to save shift.'
    } finally {
        saving.value = false
    }
}

const removeShift = async (s: Shift) => {
    const ok = await toast.confirm({
        title: `Delete "${s.name}"?`,
        description: 'Existing employee assignments stay traversable for audit but no new employees can reference this shift.',
        confirmLabel: 'Delete',
        color: 'danger'
    })
    if (!ok) return
    try {
        await api.delete(`/hrm/shifts/${s.id}`)
        await loadShifts()
        toast.info('Shift removed.')
    } catch (err: any) {
        toast.error('Failed to remove shift.', err?.data?.message)
    }
}

// ----- Action menu -----
const openActionMenu = (s: Shift, ev: MouseEvent) => {
    const rect = (ev.currentTarget as HTMLElement).getBoundingClientRect()
    const menuWidth = 180
    const menuMaxHeight = 120
    const left = Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8)
    const wouldOverflow = rect.bottom + menuMaxHeight + 8 > window.innerHeight
    actionMenu.shift = s
    actionMenu.x = Math.max(8, left)
    actionMenu.y = wouldOverflow ? rect.top - menuMaxHeight - 6 : rect.bottom + 6
    actionMenu.open = true
}

const closeActionMenu = () => { actionMenu.open = false; actionMenu.shift = null }

const actionEdit = () => { const s = actionMenu.shift; closeActionMenu(); if (s) openEditModal(s) }
const actionDelete = async () => { const s = actionMenu.shift; closeActionMenu(); if (s) await removeShift(s) }

onMounted(() => {
    if (import.meta.client) document.addEventListener('click', closeActionMenu)
    loadShifts()
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
