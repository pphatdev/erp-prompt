<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Overtime requests</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Submit overtime hours and track approval. Approved hours
                        feed into the next payroll period.</p>
                </div>
                <button v-if="canSubmit" class="btn btn-primary text-xs" @click="openSubmitModal">
                    <i class="ti ti-plus" />New request
                </button>
            </header>

            <section class="glass-card rounded-xl p-4">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div v-if="isAdmin" class="md:col-span-4">
                        <select v-model="filters.employeeId" class="form-control">
                            <option :value="''">All employees</option>
                            <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.fullName }} ({{ e.employeeId
                                }})</option>
                        </select>
                    </div>
                    <div
                        class="md:col-span-4 flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1">
                        <button v-for="s in (['', 'pending', 'approved', 'rejected', 'cancelled'] as const)"
                            :key="s || 'all'"
                            class="flex-1 px-3 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
                            :class="filters.status === s ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                            @click="filters.status = s">
                            {{ s || 'all' }}
                        </button>
                    </div>
                    <div class="md:col-span-2">
                        <input v-model="filters.from" type="date" class="form-control" />
                    </div>
                    <div class="md:col-span-2">
                        <input v-model="filters.to" type="date" class="form-control" />
                    </div>
                </div>
            </section>

            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span
                    class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading overtime requests...</span>
            </div>

            <div v-else-if="requests.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-clock-up text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No overtime requests</h4>
                <p class="text-xs text-(--text-muted) mt-1">Submit your first overtime entry to track approval.</p>
            </div>

            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr
                                class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                <th class="px-4 py-3 font-semibold font-mono">Date</th>
                                <th v-if="isAdmin" class="px-4 py-3 font-semibold">Employee</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Hours</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Multiplier</th>
                                <th class="px-4 py-3 font-semibold">Reason</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-color)">
                            <tr v-for="r in requests" :key="r.id" class="hover:bg-(--bg-muted) transition-colors">
                                <td class="px-4 py-3 font-mono text-xs">{{ r.date }}</td>
                                <td v-if="isAdmin" class="px-4 py-3 text-xs">
                                    <div class="font-semibold text-(--text-heading)">{{ r.employee?.fullName || '—' }}
                                    </div>
                                    <div class="text-xxs text-(--text-muted) font-mono">{{ r.employee?.employeeId || ''
                                        }}</div>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-right">{{ r.hours.toFixed(2) }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-right">{{ r.rateMultiplier.toFixed(1) }}x
                                </td>
                                <td class="px-4 py-3 text-xs text-(--text-body) max-w-[260px] truncate"
                                    :title="r.reason || ''">
                                    {{ r.reason || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge :variant="statusVariant(r.status)" :dot="true">{{ r.status }}</Badge>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button
                                        v-if="canProcess && r.status === 'pending' || (canSubmit && r.status === 'pending')"
                                        type="button" class="action-trigger"
                                        :class="{ 'action-trigger-open': actionMenu.open && actionMenu.request?.id === r.id }"
                                        title="Actions" @click.stop="openActionMenu(r, $event)">
                                        <i class="ti ti-dots-vertical" />
                                    </button>
                                    <span v-else class="text-xxs text-(--text-muted)">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :page="pagination.page" :limit="pagination.limit" :total="pagination.total"
                    :total-pages="pagination.totalPages" @update:page="(p) => { pagination.page = p; loadRequests() }"
                    @update:limit="(l) => { pagination.limit = l; pagination.page = 1; loadRequests() }" />
            </section>

            <!-- Submit modal -->
            <div v-if="showSubmitModal"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="glass-card rounded-2xl w-full max-w-lg p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                    <header class="flex items-center justify-between mb-5">
                        <h3 class="text-base font-semibold text-(--text-heading)">New overtime request</h3>
                        <button class="topbar-btn" @click="showSubmitModal = false"><i class="ti ti-x" /></button>
                    </header>

                    <form class="space-y-4" @submit.prevent="submit">
                        <div v-if="isAdmin">
                            <label class="form-label form-label-required">Employee</label>
                            <select v-model="form.employee_id" required class="form-control">
                                <option value="" disabled>Select employee...</option>
                                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.fullName }} ({{
                                    e.employeeId }})</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="form-label form-label-required">Date</label>
                                <input v-model="form.date" type="date" required class="form-control" />
                            </div>
                            <div>
                                <label class="form-label form-label-required">Hours</label>
                                <input v-model.number="form.hours" type="number" step="0.25" min="0.25" max="16"
                                    required class="form-control font-mono" />
                            </div>
                        </div>
                        <div>
                            <label class="form-label">Rate multiplier</label>
                            <select v-model.number="form.rate_multiplier" class="form-control">
                                <option :value="1.5">1.5x (weekday)</option>
                                <option :value="2.0">2.0x (weekend)</option>
                                <option :value="3.0">3.0x (holiday)</option>
                            </select>
                            <p class="text-xxs text-(--text-muted) mt-1">Weekend dates are auto-promoted to 2.0x
                                server-side.</p>
                        </div>
                        <div>
                            <label class="form-label">Reason</label>
                            <textarea v-model="form.reason" rows="3" class="form-control"
                                placeholder="Production support, release window, etc." />
                        </div>

                        <div v-if="formError" class="form-error">{{ formError }}</div>

                        <footer class="pt-4 border-t border-(--border-color) flex justify-end gap-2">
                            <button type="button" class="btn btn-ghost text-xs"
                                @click="showSubmitModal = false">Cancel</button>
                            <button type="submit" class="btn btn-primary text-xs" :disabled="saving">
                                <i class="ti ti-send" />{{ saving ? 'Submitting...' : 'Submit' }}
                            </button>
                        </footer>
                    </form>
                </div>
            </div>

            <!-- Action dropdown -->
            <div v-if="actionMenu.open && actionMenu.request"
                class="fixed z-50 glass-card rounded-lg shadow-(--shadow-lg) bg-(--bg-card) border border-(--border-color) py-1 min-w-[180px]"
                :style="{ top: actionMenu.y + 'px', left: actionMenu.x + 'px' }" @click.stop>
                <template v-if="canProcess && actionMenu.request.status === 'pending'">
                    <button class="action-item action-item-success" @click="actionApprove">
                        <i class="ti ti-check" /> Approve
                    </button>
                    <button class="action-item action-item-warning" @click="actionReject">
                        <i class="ti ti-x" /> Reject
                    </button>
                    <hr v-if="canSubmit" class="my-1 border-(--border-color)" />
                </template>
                <button v-if="canSubmit && actionMenu.request.status === 'pending'"
                    class="action-item action-item-danger" @click="actionCancel">
                    <i class="ti ti-trash" /> Cancel
                </button>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import Badge from '~/components/Badge.vue'

interface EmployeeLite { id: string; employeeId: string; fullName: string }
interface OvertimeRequest {
    id: string
    employeeId: string
    date: string
    hours: number
    rateMultiplier: number
    reason: string | null
    status: 'pending' | 'approved' | 'rejected' | 'cancelled'
    employee?: EmployeeLite
}
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

const api = useApi()
const authStore = useAuthStore()
const toast = useToast()

const isAdmin = computed(() => authStore.hasPermission('hrm.overtime.read'))
const canProcess = computed(() => authStore.hasPermission('hrm.overtime.write'))
const canSubmit = computed(() =>
    authStore.hasPermission('hrm.overtime.write') || authStore.hasPermission('hrm.overtime.write.self')
)

const requests = ref<OvertimeRequest[]>([])
const employees = ref<EmployeeLite[]>([])
const loading = ref(false)

const pagination = reactive({ page: 1, limit: 15, total: 0, totalPages: 1 })
const filters = reactive({
    employeeId: '',
    status: '' as '' | 'pending' | 'approved' | 'rejected' | 'cancelled',
    from: '',
    to: ''
})

const showSubmitModal = ref(false)
const saving = ref(false)
const formError = ref<string | null>(null)
const form = reactive({
    employee_id: '',
    date: '',
    hours: 1.0 as number | null,
    rate_multiplier: 1.5 as number | null,
    reason: ''
})

const actionMenu = reactive({
    open: false,
    x: 0,
    y: 0,
    request: null as OvertimeRequest | null
})

const statusVariant = (s: string): 'success' | 'warning' | 'danger' | 'secondary' => {
    if (s === 'approved') return 'success'
    if (s === 'pending') return 'warning'
    if (s === 'rejected') return 'danger'
    return 'secondary'
}

const loadEmployees = async () => {
    if (!isAdmin.value) return
    try {
        const res = await api.get<Paginated<EmployeeLite>>('/employees?limit=200')
        employees.value = res.data
    } catch (err) {
        console.error('Failed to load employees', err)
    }
}

const loadRequests = async () => {
    loading.value = true
    try {
        const q = new URLSearchParams({ page: String(pagination.page), limit: String(pagination.limit) })
        if (filters.employeeId) q.set('employeeId', filters.employeeId)
        if (filters.status) q.set('status', filters.status)
        if (filters.from) q.set('from', filters.from)
        if (filters.to) q.set('to', filters.to)

        const res = await api.get<Paginated<OvertimeRequest>>(`/hrm/overtime-requests?${q.toString()}`)
        requests.value = res.data
        pagination.total = res.pagination.total
        pagination.totalPages = res.pagination.totalPages
    } catch (err) {
        console.error('Failed to load overtime requests', err)
        requests.value = []
    } finally {
        loading.value = false
    }
}

watch(() => [filters.employeeId, filters.status, filters.from, filters.to], () => {
    pagination.page = 1
    loadRequests()
})

const openSubmitModal = () => {
    Object.assign(form, {
        employee_id: isAdmin.value ? '' : (authStore.user as any)?.employee?.id || '',
        date: '',
        hours: 1.0,
        rate_multiplier: 1.5,
        reason: ''
    })
    formError.value = null
    showSubmitModal.value = true
}

const submit = async () => {
    saving.value = true
    formError.value = null
    try {
        const payload: Record<string, any> = { ...form }
        if (!payload.employee_id) delete payload.employee_id   // server force-fills for self-service callers
        if (!payload.reason) payload.reason = null
        await api.post('/hrm/overtime-requests', payload)
        showSubmitModal.value = false
        toast.success('Overtime request submitted', 'Awaiting approval.')
        await loadRequests()
    } catch (err: any) {
        formError.value = err?.data?.message || 'Failed to submit overtime request.'
    } finally {
        saving.value = false
    }
}

const processRequest = async (r: OvertimeRequest, decision: 'approve' | 'reject') => {
    const ok = await toast.confirm({
        title: `${decision === 'approve' ? 'Approve' : 'Reject'} ${r.hours}h overtime on ${r.date}?`,
        description: decision === 'approve'
            ? `Adds ${(r.hours * r.rateMultiplier).toFixed(2)} weighted hours to the next payroll period.`
            : 'The request will be marked rejected. The requester sees the reason if you supply one.',
        confirmLabel: decision === 'approve' ? 'Approve' : 'Reject',
        color: decision === 'approve' ? 'primary' : 'warning'
    })
    if (!ok) return
    try {
        await api.patch(`/hrm/overtime-requests/${r.id}/process`, { decision })
        toast.success(`Overtime ${decision === 'approve' ? 'approved' : 'rejected'}.`)
        await loadRequests()
    } catch (err: any) {
        toast.error(`Could not ${decision} overtime.`, err?.data?.message)
    }
}

const cancelRequest = async (r: OvertimeRequest) => {
    const ok = await toast.confirm({
        title: 'Cancel this overtime request?',
        description: 'Only pending requests can be cancelled.',
        confirmLabel: 'Cancel request',
        color: 'danger'
    })
    if (!ok) return
    try {
        await api.delete(`/hrm/overtime-requests/${r.id}`)
        toast.info('Overtime request cancelled.')
        await loadRequests()
    } catch (err: any) {
        toast.error('Could not cancel.', err?.data?.message)
    }
}

const openActionMenu = (r: OvertimeRequest, ev: MouseEvent) => {
    const rect = (ev.currentTarget as HTMLElement).getBoundingClientRect()
    const menuWidth = 180
    const menuMaxHeight = 200
    const left = Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8)
    const wouldOverflow = rect.bottom + menuMaxHeight + 8 > window.innerHeight
    actionMenu.request = r
    actionMenu.x = Math.max(8, left)
    actionMenu.y = wouldOverflow ? rect.top - menuMaxHeight - 6 : rect.bottom + 6
    actionMenu.open = true
}

const closeActionMenu = () => { actionMenu.open = false; actionMenu.request = null }

const actionApprove = () => { const r = actionMenu.request; closeActionMenu(); if (r) processRequest(r, 'approve') }
const actionReject = () => { const r = actionMenu.request; closeActionMenu(); if (r) processRequest(r, 'reject') }
const actionCancel = () => { const r = actionMenu.request; closeActionMenu(); if (r) cancelRequest(r) }

onMounted(async () => {
    if (import.meta.client) document.addEventListener('click', closeActionMenu)
    await Promise.all([loadEmployees(), loadRequests()])
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

.action-item-success {
    color: var(--color-success);
}

.action-item-success:hover {
    background: var(--color-success-subtle, rgb(var(--color-success-rgb, 16 185 129) / 0.1));
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
</style>
