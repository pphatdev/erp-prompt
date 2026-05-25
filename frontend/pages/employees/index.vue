<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Page header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Employees</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        <span v-if="pagination.total">
                            Manage {{ pagination.total.toLocaleString() }} workforce records — hire, edit, and offboard
                            personnel.
                        </span>
                        <span v-else>Workforce records — hire, edit, and offboard personnel.</span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <!-- View toggle: persisted in localStorage so refresh keeps the user's choice. -->
                    <div class="inline-flex items-center bg-(--bg-card) border border-(--border-color) rounded-lg p-1">
                        <button v-for="opt in (['table', 'grid'] as const)" :key="opt" type="button"
                            class="px-3 py-1.5 rounded-md text-xs font-semibold inline-flex items-center gap-1.5 transition-colors"
                            :class="view === opt
                                ? 'bg-(--color-primary-subtle) text-(--color-primary)'
                                : 'text-(--text-muted) hover:text-(--text-heading)'" @click="setView(opt)">
                            <i :class="['ti', opt === 'table' ? 'ti-list' : 'ti-layout-grid']" />
                            {{ opt === 'table' ? 'List' : 'Grid' }}
                        </button>
                    </div>

                    <button class="btn btn-ghost text-xs" disabled>
                        <i class="ti ti-download" />Export
                    </button>
                    <button class="btn btn-primary text-xs" @click="openCreateModal">
                        <i class="ti ti-user-plus" />Hire employee
                    </button>
                </div>
            </header>

            <!-- Filters -->
            <section class="glass-card rounded-xl p-4">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="relative md:col-span-5">
                        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                        <input v-model="filters.search" type="search" placeholder="Search name, email, or ID..."
                            class="form-control pl-9" />
                    </div>

                    <div class="relative md:col-span-3">
                        <i
                            class="ti ti-building absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
                        <select v-model="filters.departmentId" class="form-control pl-9 appearance-none">
                            <option :value="''">All departments</option>
                            <option v-for="d in departments" :key="d.id" :value="d.id">{{ d.name }}</option>
                        </select>
                    </div>

                    <div
                        class="md:col-span-4 flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1">
                        <button v-for="s in (['', 'active', 'on_leave', 'terminated'] as const)" :key="s || 'all'"
                            class="flex-1 px-3 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
                            :class="filters.status === s ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                            @click="filters.status = s">
                            {{ s || 'all' }}
                        </button>
                    </div>
                </div>
            </section>

            <!-- Loading -->
            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span
                    class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading employees...</span>
            </div>

            <!-- Empty -->
            <div v-else-if="employees.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-users text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No employees found</h4>
                <p class="text-xs text-(--text-muted) mt-1">Adjust your filters, or hire your first employee.</p>
            </div>

            <!-- Data table -->
            <section v-else-if="view === 'table'" class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr
                                class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                <th class="px-4 py-3 font-semibold font-mono">ID</th>
                                <th class="px-4 py-3 font-semibold">Employee</th>
                                <th class="px-4 py-3 font-semibold">Department</th>
                                <th class="px-4 py-3 font-semibold">Position</th>
                                <th class="px-4 py-3 font-semibold">Hired</th>
                                <th v-if="canSeeSalary" class="px-4 py-3 font-semibold text-right font-mono">Salary</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-color)">
                            <tr v-for="emp in employees" :key="emp.id" class="hover:bg-(--bg-muted) transition-colors">
                                <td class="px-4 py-3 font-mono text-xs text-(--text-body)">{{ emp.employeeId }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div
                                            class="w-9 h-9 rounded-lg bg-(--color-primary-subtle) text-(--color-primary) flex items-center justify-center font-semibold text-xs shrink-0">
                                            {{ initials(emp) }}
                                        </div>
                                        <div class="min-w-0">
                                            <NuxtLink :to="`/employees/${emp.id}`"
                                                class="text-xs font-semibold text-(--text-heading) truncate hover:text-(--color-primary) hover:underline underline-offset-2 block">
                                                {{ emp.fullName }}
                                            </NuxtLink>
                                            <div class="text-xxs text-(--text-muted) truncate">{{ emp.email }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-xs">{{ emp.department?.name || '—' }}</td>
                                <td class="px-4 py-3 text-xs">{{ emp.position?.title || '—' }}</td>
                                <td class="px-4 py-3 font-mono text-xxs text-(--text-muted)">{{ formatDate(emp.hiredAt)
                                    }}</td>
                                <td v-if="canSeeSalary" class="px-4 py-3 text-right font-mono text-xs">
                                    {{ emp.baseSalary != null ? formatMoney(emp.baseSalary) : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge :variant="statusVariant(emp.status)" :dot="true">{{ emp.status }}</Badge>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button" class="emp-card-kebab"
                                        :class="{ 'emp-card-kebab--open': cardMenu.open && cardMenu.emp?.id === emp.id }"
                                        title="Actions" @click.stop="openCardMenu(emp, $event)">
                                        <i class="ti ti-dots-vertical" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :page="pagination.page" :limit="pagination.limit" :total="pagination.total"
                    :total-pages="pagination.totalPages" @update:page="(p) => { pagination.page = p; loadEmployees() }"
                    @update:limit="(l) => { pagination.limit = l; pagination.page = 1; loadEmployees() }" />
            </section>

            <!-- Grid view -->
            <section v-else>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <article v-for="emp in employees" :key="emp.id"
                        class="emp-card glass-card rounded-2xl p-5 transition-all"
                        :class="emp.status === 'terminated' ? 'emp-card--terminated' : ''">
                        <!-- Header: avatar + name + kebab -->
                        <header class="flex items-start justify-between gap-3 mb-4">
                            <div class="flex items-start gap-3 min-w-0">
                                <div class="relative shrink-0">
                                    <div class="w-12 h-12 rounded-xl bg-(--color-primary-subtle) text-(--color-primary) flex items-center justify-center font-semibold text-sm"
                                        :title="emp.fullName">
                                        {{ initials(emp) }}
                                    </div>
                                    <span
                                        class="absolute -bottom-1 -right-1 w-3.5 h-3.5 rounded-full border-2 border-(--bg-card)"
                                        :class="statusDotClass(emp.status)" :title="statusLabel(emp.status)" />
                                </div>
                                <div class="min-w-0">
                                    <NuxtLink :to="`/employees/${emp.id}`"
                                        class="text-sm font-semibold text-(--text-heading) truncate block hover:text-(--color-primary) hover:underline underline-offset-2"
                                        :class="emp.status === 'terminated' ? 'opacity-70' : ''">
                                        {{ emp.fullName }}
                                    </NuxtLink>
                                    <p
                                        class="text-xxs uppercase tracking-wider font-bold text-(--color-primary) truncate">
                                        {{ emp.position?.title || '—' }}
                                    </p>
                                </div>
                            </div>

                            <button v-if="canWrite || emp.status !== 'terminated'" type="button" class="emp-card-kebab"
                                :class="{ 'emp-card-kebab--open': cardMenu.open && cardMenu.emp?.id === emp.id }"
                                title="Actions" @click.stop="openCardMenu(emp, $event)">
                                <i class="ti ti-dots-vertical" />
                            </button>
                        </header>

                        <!-- Body: department / hired / id -->
                        <dl class="space-y-2 mb-5 text-xs">
                            <div class="flex items-center gap-2 text-(--text-body)">
                                <i class="ti ti-building text-(--text-muted) text-[15px]" />
                                <span class="truncate">{{ emp.department?.name || 'Unassigned' }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-(--text-body)">
                                <i class="ti ti-calendar text-(--text-muted) text-[15px]" />
                                <span class="font-mono text-xxs">{{ emp.hiredAt ? `Hired
                                    ${formatRelativeDate(emp.hiredAt)}` : 'Hire date —' }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-(--text-body)">
                                <i class="ti ti-id text-(--text-muted) text-[15px]" />
                                <span class="font-mono text-xxs truncate">{{ emp.employeeId }}</span>
                            </div>
                            <div v-if="canSeeSalary" class="flex items-center gap-2 text-(--text-body)">
                                <i class="ti ti-cash text-(--text-muted) text-[15px]" />
                                <span class="font-mono text-xxs">{{ emp.baseSalary != null ? formatMoney(emp.baseSalary)
                                    : '••••' }}</span>
                            </div>
                        </dl>

                        <!-- Actions -->
                        <div class="flex items-center gap-2">
                            <a :href="`mailto:${emp.email}`" class="emp-card-btn flex-1"
                                :class="emp.status === 'terminated' ? 'emp-card-btn--disabled' : ''"
                                :aria-disabled="emp.status === 'terminated' ? 'true' : 'false'"
                                @click="emp.status === 'terminated' && $event.preventDefault()">
                                <i class="ti ti-mail text-[14px]" />
                                <span>Email</span>
                            </a>
                            <button type="button" class="emp-card-btn flex-1" :disabled="!canWrite"
                                @click="openEditModal(emp)">
                                <i class="ti ti-pencil text-[14px]" />
                                <span>Edit</span>
                            </button>
                            <Badge :variant="statusVariant(emp.status)" :dot="true">{{ emp.status }}</Badge>
                        </div>
                    </article>
                </div>

                <Pagination class="mt-6" :page="pagination.page" :limit="pagination.limit" :total="pagination.total"
                    :total-pages="pagination.totalPages" @update:page="(p) => { pagination.page = p; loadEmployees() }"
                    @update:limit="(l) => { pagination.limit = l; pagination.page = 1; loadEmployees() }" />
            </section>

            <!-- Card kebab dropdown (used by grid view) -->
            <div v-if="cardMenu.open && cardMenu.emp"
                class="fixed z-50 glass-card rounded-lg shadow-(--shadow-lg) bg-(--bg-card) border border-(--border-color) py-1 min-w-[180px]"
                :style="{ top: cardMenu.y + 'px', left: cardMenu.x + 'px' }" @click.stop>
                <NuxtLink :to="`/employees/${cardMenu.emp.id}`" class="action-item" @click="closeCardMenu">
                    <i class="ti ti-user-circle" /> View profile
                </NuxtLink>
                <button class="action-item" @click="cardActionEdit">
                    <i class="ti ti-pencil" /> Edit
                </button>
                <a :href="`mailto:${cardMenu.emp.email}`" class="action-item" @click="closeCardMenu">
                    <i class="ti ti-mail" /> Email
                </a>
                <template v-if="cardMenu.emp.status !== 'terminated' && canWrite">
                    <hr class="my-1 border-(--border-color)" />
                    <button class="action-item action-item-danger" @click="cardActionTerminate">
                        <i class="ti ti-user-off" /> Terminate
                    </button>
                </template>
            </div>

            <!-- Modal -->
            <div v-if="showModal"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="glass-card rounded-2xl w-full max-w-lg p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                    <header class="flex items-center justify-between mb-5">
                        <h3 class="text-base font-semibold text-(--text-heading)">
                            {{ editing ? 'Edit employee' : 'Hire new employee' }}
                        </h3>
                        <button class="topbar-btn" @click="closeModal"><i class="ti ti-x" /></button>
                    </header>

                    <form class="grid grid-cols-1 sm:grid-cols-2 gap-4" @submit.prevent="saveEmployee">
                        <div class="sm:col-span-2">
                            <label class="form-label">Employee ID</label>
                            <input v-model="form.employee_id" type="text" required class="form-control font-mono"
                                placeholder="EMP-0001" />
                        </div>

                        <div>
                            <label class="form-label">First name</label>
                            <input v-model="form.first_name" type="text" required class="form-control" />
                        </div>
                        <div>
                            <label class="form-label">Last name</label>
                            <input v-model="form.last_name" type="text" required class="form-control" />
                        </div>

                        <div class="sm:col-span-2">
                            <label class="form-label">Email</label>
                            <input v-model="form.email" type="email" required class="form-control" />
                        </div>

                        <div>
                            <label class="form-label">Phone</label>
                            <input v-model="form.phone" type="tel" class="form-control" />
                        </div>
                        <div>
                            <label class="form-label">Hired at</label>
                            <input v-model="form.hired_at" type="date" class="form-control" />
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

                        <div v-if="canSeeSalary">
                            <label class="form-label">Base salary</label>
                            <input v-model.number="form.base_salary" type="number" step="0.01" min="0"
                                class="form-control font-mono" />
                        </div>
                        <div>
                            <label class="form-label">Status</label>
                            <select v-model="form.status" class="form-control">
                                <option value="active">Active</option>
                                <option value="on_leave">On leave</option>
                                <option value="terminated">Terminated</option>
                            </select>
                        </div>

                        <div v-if="formError" class="sm:col-span-2 form-error">{{ formError }}</div>

                        <footer class="sm:col-span-2 pt-4 border-t border-(--border-color) flex justify-end gap-2">
                            <button type="button" class="btn btn-ghost text-xs" @click="closeModal">Cancel</button>
                            <button type="submit" class="btn btn-primary text-xs" :disabled="saving">
                                <i class="ti ti-device-floppy" />{{ saving ? 'Saving...' : 'Save' }}
                            </button>
                        </footer>
                    </form>
                </div>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useApi } from '~/composables/useApi'
import { formatDate } from '~/composables/useDateFormat'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import Badge from '~/components/Badge.vue'

interface Lookup { id: string; name?: string; title?: string }
interface Employee {
    id: string
    employeeId: string
    firstName: string
    lastName: string
    fullName: string
    email: string
    phone: string | null
    status: 'active' | 'on_leave' | 'terminated'
    hiredAt: string | null
    baseSalary: number | null
    department: { id: string; name: string } | null
    position: { id: string; title: string } | null
}
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

const api = useApi()
const route = useRoute()
const authStore = useAuthStore()
const toast = useToast()
const canSeeSalary = computed(() => authStore.hasPermission('hrm.payroll.read'))
const canWrite = computed(() => authStore.hasPermission('hrm.employee.write'))

// View toggle: 'table' (default — denser) or 'grid' (card layout). Persisted
// in localStorage so a refresh keeps the user's last choice.
type View = 'table' | 'grid'
const VIEW_KEY = 'employees.view'
const view = ref<View>('table')

const setView = (v: View) => {
    view.value = v
    if (import.meta.client) localStorage.setItem(VIEW_KEY, v)
}

const employees = ref<Employee[]>([])
const departments = ref<Array<{ id: string; name: string }>>([])
const positions = ref<Array<{ id: string; title: string }>>([])
const loading = ref(false)

const pagination = reactive({ page: 1, limit: 15, total: 0, totalPages: 1 })
const filters = reactive({ search: '', status: '' as '' | 'active' | 'on_leave' | 'terminated', departmentId: '' })

const showModal = ref(false)
const editing = ref<Employee | null>(null)
const saving = ref(false)
const formError = ref<string | null>(null)
const form = reactive({
    employee_id: '',
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    hired_at: '',
    department_id: '',
    position_id: '',
    base_salary: null as number | null,
    status: 'active' as 'active' | 'on_leave' | 'terminated'
})

const initials = (e: Employee) =>
    `${(e.firstName?.[0] || '').toUpperCase()}${(e.lastName?.[0] || '').toUpperCase()}` || 'EM'

const statusVariant = (s: string): 'success' | 'warning' | 'danger' =>
    s === 'active' ? 'success' : s === 'on_leave' ? 'warning' : 'danger'

const statusLabel = (s: string): string =>
    s === 'active' ? 'Active' : s === 'on_leave' ? 'On leave' : 'Terminated'

const statusDotClass = (s: string): string =>
    s === 'active' ? 'bg-(--color-success)'
        : s === 'on_leave' ? 'bg-(--color-warning)'
            : 'bg-(--color-danger)'

const formatMoney = (n: number) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(n)

// "Hired 3 mo ago" style for the grid card, falls back to ISO date for old hires.
const formatRelativeDate = (iso: string): string => {
    const then = new Date(iso).getTime()
    if (!Number.isFinite(then)) return iso
    const diffMs = Date.now() - then
    const days = Math.floor(diffMs / 86_400_000)
    if (days < 0) return formatDate(iso)
    if (days < 30) return `${days || 1} day${days === 1 ? '' : 's'} ago`
    const months = Math.floor(days / 30)
    if (months < 12) return `${months} mo ago`
    const years = Math.floor(months / 12)
    return `${years} yr${years === 1 ? '' : 's'} ago`
}

// Card kebab — fixed-positioned dropdown anchored to the trigger button.
const cardMenu = reactive({
    open: false,
    x: 0,
    y: 0,
    emp: null as Employee | null
})

const openCardMenu = (emp: Employee, ev: MouseEvent) => {
    const rect = (ev.currentTarget as HTMLElement).getBoundingClientRect()
    const menuWidth = 180
    const menuMaxHeight = 160
    const left = Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8)
    const wouldOverflow = rect.bottom + menuMaxHeight + 8 > window.innerHeight
    cardMenu.emp = emp
    cardMenu.x = Math.max(8, left)
    cardMenu.y = wouldOverflow ? rect.top - menuMaxHeight - 6 : rect.bottom + 6
    cardMenu.open = true
}

const closeCardMenu = () => { cardMenu.open = false; cardMenu.emp = null }

const cardActionEdit = () => {
    const emp = cardMenu.emp
    closeCardMenu()
    if (emp) openEditModal(emp)
}

const cardActionTerminate = async () => {
    const emp = cardMenu.emp
    closeCardMenu()
    if (emp) await terminate(emp)
}

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

const loadEmployees = async () => {
    loading.value = true
    try {
        const query = new URLSearchParams({
            page: String(pagination.page),
            limit: String(pagination.limit)
        })
        if (filters.search) query.set('search', filters.search)
        if (filters.status) query.set('status', filters.status)
        if (filters.departmentId) query.set('departmentId', filters.departmentId)

        const res = await api.get<Paginated<Employee>>(`/employees?${query.toString()}`)
        employees.value = res.data
        pagination.total = res.pagination.total
        pagination.totalPages = res.pagination.totalPages
    } catch (err) {
        console.error('Failed to load employees', err)
        employees.value = []
    } finally {
        loading.value = false
    }
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(() => [filters.search, filters.status, filters.departmentId], () => {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        pagination.page = 1
        loadEmployees()
    }, 300)
})

const resetForm = () => {
    Object.assign(form, {
        employee_id: '', first_name: '', last_name: '', email: '', phone: '',
        hired_at: '', department_id: '', position_id: '', base_salary: null, status: 'active'
    })
    formError.value = null
}

const openCreateModal = () => {
    editing.value = null
    resetForm()
    showModal.value = true
}

const openEditModal = (emp: Employee) => {
    editing.value = emp
    Object.assign(form, {
        employee_id: emp.employeeId,
        first_name: emp.firstName,
        last_name: emp.lastName,
        email: emp.email,
        phone: emp.phone ?? '',
        hired_at: emp.hiredAt ?? '',
        department_id: emp.department?.id ?? '',
        position_id: emp.position?.id ?? '',
        base_salary: emp.baseSalary,
        status: emp.status
    })
    formError.value = null
    showModal.value = true
}

const closeModal = () => { showModal.value = false; editing.value = null }

const saveEmployee = async () => {
    saving.value = true
    formError.value = null
    try {
        const payload: Record<string, any> = {
            employee_id: form.employee_id,
            first_name: form.first_name,
            last_name: form.last_name,
            email: form.email,
            phone: form.phone || null,
            hired_at: form.hired_at || null,
            department_id: form.department_id || null,
            position_id: form.position_id || null,
            status: form.status
        }
        if (canSeeSalary.value) payload.base_salary = form.base_salary

        if (editing.value) {
            await api.put(`/employees/${editing.value.id}`, payload)
        } else {
            await api.post('/employees', payload)
        }
        showModal.value = false
        await loadEmployees()
    } catch (err: any) {
        formError.value = err.data?.message || 'Failed to save employee.'
    } finally {
        saving.value = false
    }
}

const terminate = async (emp: Employee) => {
    const ok = await toast.confirm({
        title: `Terminate ${emp.fullName}?`,
        description: 'Their status flips to terminated and they drop off active rosters. Payroll, leave, and audit history are preserved.',
        confirmLabel: 'Terminate',
        color: 'danger',
        icon: 'ti-user-off'
    })
    if (!ok) return
    try {
        await api.delete(`/employees/${emp.id}`)
        toast.success('Employee terminated', `${emp.fullName} is no longer active.`)
        await loadEmployees()
    } catch (err: any) {
        console.error('Failed to terminate employee', err)
        toast.error('Failed to terminate employee.', err?.data?.message)
    }
}

onMounted(async () => {
    if (import.meta.client) {
        const saved = localStorage.getItem(VIEW_KEY)
        if (saved === 'grid' || saved === 'table') view.value = saved
        document.addEventListener('click', closeCardMenu)
    }

    // Pick up ?search=... from the URL so the convert-success toast's "View"
    // action can land directly on the new employee (matches by name/email/id
    // per buildIndexQuery). Also clears any active status filter so the row
    // isn't accidentally hidden by a stale segmented-control selection.
    const initialSearch = (route.query.search as string | undefined) || ''
    if (initialSearch) {
        filters.search = initialSearch
        filters.status = ''
    }

    await Promise.all([loadLookups(), loadEmployees()])
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

/* ---------- Grid view card ---------- */
.emp-card {
    position: relative;
    transition: transform 0.18s ease, box-shadow 0.18s ease, border-color 0.18s ease;
}

.emp-card:hover {
    transform: translateY(-2px);
    border-color: rgb(var(--color-primary-rgb) / 0.25);
    box-shadow: var(--shadow-md), 0 0 0 1px rgb(var(--color-primary-rgb) / 0.06) inset;
}

.emp-card--terminated {
    border-style: dashed;
}

.emp-card--terminated:hover {
    /* Don't suggest interactivity on a terminated record. */
    transform: none;
    border-color: var(--border-color);
    box-shadow: var(--shadow-sm);
}

.emp-card-kebab {
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

.emp-card-kebab:hover {
    background: var(--bg-muted);
    color: var(--text-heading);
}

.emp-card-kebab--open {
    background: var(--bg-muted);
    color: var(--color-primary);
}

/* In-card secondary buttons — share styling with mailto link & edit button. */
.emp-card-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 0.375rem;
    padding: 0.5rem 0.75rem;
    border-radius: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    background: var(--bg-muted);
    color: var(--text-body);
    border: 1px solid transparent;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
    text-decoration: none;
}

.emp-card-btn:hover:not(:disabled):not(.emp-card-btn--disabled) {
    background: var(--color-primary-subtle);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.2);
}

.emp-card-btn:disabled,
.emp-card-btn--disabled {
    opacity: 0.5;
    cursor: not-allowed;
    pointer-events: none;
}

/* ---------- Card dropdown menu (mirrors applications.vue action-item) ---------- */
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
    text-decoration: none;
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
