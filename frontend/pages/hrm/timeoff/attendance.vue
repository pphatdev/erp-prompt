<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Attendance</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Clock-in / clock-out and daily attendance ledger.</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <button v-if="canClock" class="btn btn-primary text-xs" :disabled="clocking" @click="clockIn">
                        <i :class="['ti', clocking ? 'ti-loader animate-spin' : 'ti-login-2']" />
                        {{ clocking ? 'Stamping...' : 'Clock in' }}
                    </button>
                    <button v-if="canClock" class="btn btn-ghost text-xs" :disabled="clocking" @click="clockOut">
                        <i :class="['ti', clocking ? 'ti-loader animate-spin' : 'ti-logout-2']" />Clock out
                    </button>
                    <button v-if="canReconcile" class="btn btn-ghost text-xs" :disabled="reconciling"
                        @click="reconcile">
                        <i :class="['ti', reconciling ? 'ti-loader animate-spin' : 'ti-refresh']" />Reconcile yesterday
                    </button>
                </div>
            </header>

            <!-- Filters -->
            <section class="glass-card rounded-xl p-4">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div v-if="isAdmin" class="md:col-span-4">
                        <select v-model="filters.employeeId" class="form-control">
                            <option :value="''">All employees</option>
                            <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.fullName }} ({{ e.employeeId
                                }})</option>
                        </select>
                    </div>
                    <div class="md:col-span-3">
                        <select v-model="filters.status" class="form-control">
                            <option :value="''">All statuses</option>
                            <option v-for="s in statusOptions" :key="s" :value="s">{{ statusLabel(s) }}</option>
                        </select>
                    </div>
                    <div class="md:col-span-2">
                        <input v-model="filters.from" type="date" class="form-control" />
                    </div>
                    <div class="md:col-span-2">
                        <input v-model="filters.to" type="date" class="form-control" />
                    </div>
                    <div class="md:col-span-1">
                        <button class="btn btn-ghost text-xs w-full" @click="clearFilters">
                            <i class="ti ti-eraser" />Clear
                        </button>
                    </div>
                </div>
            </section>

            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span
                    class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading attendance logs...</span>
            </div>

            <div v-else-if="logs.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-fingerprint text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No attendance records</h4>
                <p class="text-xs text-(--text-muted) mt-1">Clock in to create your first log entry, or adjust the
                    filters.</p>
            </div>

            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr
                                class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                <th class="px-4 py-3 font-semibold font-mono">Date</th>
                                <th v-if="isAdmin" class="px-4 py-3 font-semibold">Employee</th>
                                <th class="px-4 py-3 font-semibold font-mono">In</th>
                                <th class="px-4 py-3 font-semibold font-mono">Out</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th v-if="isAdmin" class="px-4 py-3 font-semibold font-mono">Source IP</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-color)">
                            <tr v-for="l in logs" :key="l.id" class="hover:bg-(--bg-muted) transition-colors">
                                <td class="px-4 py-3 font-mono text-xs">{{ l.date }}</td>
                                <td v-if="isAdmin" class="px-4 py-3 text-xs">
                                    <div class="font-semibold text-(--text-heading)">{{ l.employee?.fullName || '—' }}
                                    </div>
                                    <div class="text-xxs text-(--text-muted) font-mono">{{ l.employee?.employeeId || ''
                                        }}</div>
                                </td>
                                <td class="px-4 py-3 font-mono text-xxs text-(--text-muted)">{{ formatTime(l.checkIn) }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xxs text-(--text-muted)">{{ formatTime(l.checkOut)
                                    }}</td>
                                <td class="px-4 py-3">
                                    <Badge :variant="statusVariant(l.status)" :dot="true">{{ statusLabel(l.status) }}
                                    </Badge>
                                </td>
                                <td v-if="isAdmin" class="px-4 py-3 font-mono text-xxs text-(--text-muted)">{{
                                    l.checkInIp || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :page="pagination.page" :limit="pagination.limit" :total="pagination.total"
                    :total-pages="pagination.totalPages" @update:page="(p) => { pagination.page = p; loadLogs() }"
                    @update:limit="(l) => { pagination.limit = l; pagination.page = 1; loadLogs() }" />
            </section>
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
interface AttendanceLog {
    id: string
    employeeId: string
    date: string
    checkIn: string | null
    checkOut: string | null
    status: string
    checkInIp: string | null
    checkOutIp: string | null
    employee?: EmployeeLite
}
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

const api = useApi()
const authStore = useAuthStore()
const toast = useToast()
const isAdmin = computed(() => authStore.hasPermission('hrm.attendance.read'))
const canClock = computed(() =>
    authStore.hasPermission('hrm.attendance.clock.self') || authStore.hasPermission('hrm.attendance.write')
)
const canReconcile = computed(() => authStore.hasPermission('hrm.attendance.write'))

const statusOptions = ['present', 'late', 'early_out', 'half_day', 'absent', 'paid_leave', 'unpaid_leave', 'weekend', 'holiday']

const logs = ref<AttendanceLog[]>([])
const employees = ref<EmployeeLite[]>([])
const loading = ref(false)
const clocking = ref(false)
const reconciling = ref(false)

const pagination = reactive({ page: 1, limit: 15, total: 0, totalPages: 1 })
const filters = reactive({
    employeeId: '',
    status: '',
    from: '',
    to: ''
})

const statusVariant = (s: string): 'success' | 'warning' | 'danger' | 'info' | 'secondary' | 'primary' => {
    switch (s) {
        case 'present': return 'success'
        case 'late': return 'warning'
        case 'half_day':
        case 'early_out': return 'warning'
        case 'absent': return 'danger'
        case 'paid_leave': return 'info'
        case 'unpaid_leave': return 'danger'
        case 'weekend':
        case 'holiday': return 'secondary'
        default: return 'secondary'
    }
}

const statusLabel = (s: string): string => s.replace('_', ' ')

const formatTime = (iso: string | null): string => {
    if (!iso) return '—'
    const d = new Date(iso)
    return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: false })
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

const loadLogs = async () => {
    loading.value = true
    try {
        const q = new URLSearchParams({ page: String(pagination.page), limit: String(pagination.limit) })
        if (filters.employeeId) q.set('employeeId', filters.employeeId)
        if (filters.status) q.set('status', filters.status)
        if (filters.from) q.set('from', filters.from)
        if (filters.to) q.set('to', filters.to)

        const res = await api.get<Paginated<AttendanceLog>>(`/attendance/logs?${q.toString()}`)
        logs.value = res.data
        pagination.total = res.pagination.total
        pagination.totalPages = res.pagination.totalPages
    } catch (err) {
        console.error('Failed to load attendance', err)
        logs.value = []
    } finally {
        loading.value = false
    }
}

watch(() => [filters.employeeId, filters.status, filters.from, filters.to], () => {
    pagination.page = 1
    loadLogs()
})

const clearFilters = () => {
    filters.employeeId = ''
    filters.status = ''
    filters.from = ''
    filters.to = ''
}

// Resolve browser geolocation. Resolves to null if denied/unsupported — the
// server still accepts the request when the department has no geofence
// configured. Times out after 8s so a flaky locator doesn't strand the user.
const getLocation = (): Promise<{ latitude: number; longitude: number } | null> => {
    if (typeof window === 'undefined' || !('geolocation' in navigator)) {
        return Promise.resolve(null)
    }
    return new Promise((resolve) => {
        navigator.geolocation.getCurrentPosition(
            (pos) => resolve({ latitude: pos.coords.latitude, longitude: pos.coords.longitude }),
            () => resolve(null),
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 30000 }
        )
    })
}

const clockIn = async () => {
    if (clocking.value) return
    clocking.value = true
    try {
        const coords = await getLocation()
        const log = await api.post<{ data: AttendanceLog }>('/attendance/clock-in', coords ?? {})
        const data = (log as any).data ?? log
        toast.success('Clocked in', `Status: ${statusLabel(data.status)} at ${formatTime(data.checkIn)}.`)
        pagination.page = 1
        await loadLogs()
    } catch (err: any) {
        toast.error('Could not clock in.', err?.data?.message)
    } finally {
        clocking.value = false
    }
}

const clockOut = async () => {
    if (clocking.value) return
    clocking.value = true
    try {
        const coords = await getLocation()
        const log = await api.post<{ data: AttendanceLog }>('/attendance/clock-out', coords ?? {})
        const data = (log as any).data ?? log
        toast.success('Clocked out', `Out at ${formatTime(data.checkOut)} (status: ${statusLabel(data.status)}).`)
        await loadLogs()
    } catch (err: any) {
        toast.error('Could not clock out.', err?.data?.message)
    } finally {
        clocking.value = false
    }
}

const reconcile = async () => {
    if (reconciling.value) return
    const ok = await toast.confirm({
        title: 'Reconcile yesterday\'s attendance?',
        description: 'Fills in absent / weekend / paid-leave rows for every active employee. Idempotent — rows that already exist are skipped.',
        confirmLabel: 'Reconcile',
        color: 'primary',
        icon: 'ti-refresh'
    })
    if (!ok) return

    reconciling.value = true
    try {
        const res = await api.post<{ processed: number; created: number; skipped: number; date: string }>('/attendance/reconcile', {})
        toast.info(
            'Reconciliation complete',
            `${res.processed} employees · ${res.created} created · ${res.skipped} already had rows (date ${res.date}).`
        )
        await loadLogs()
    } catch (err: any) {
        toast.error('Reconciliation failed.', err?.data?.message)
    } finally {
        reconciling.value = false
    }
}

onMounted(async () => {
    await Promise.all([loadEmployees(), loadLogs()])
})
</script>
