<template>
  <NuxtLayout name="default">
    <div class="space-y-6">
      <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
          <h1 class="text-xl font-semibold">Leave requests</h1>
          <p class="text-xs text-(--text-muted) mt-1">Submit, review, and approve time-off requests across the workforce.</p>
        </div>
        <div class="flex items-center gap-2">
          <NuxtLink to="/leave-types" class="btn btn-ghost text-xs">
            <i class="ti ti-list" />Leave types
          </NuxtLink>
          <button class="btn btn-primary text-xs" @click="openSubmitModal">
            <i class="ti ti-plus" />Submit request
          </button>
        </div>
      </header>

      <!-- Filters -->
      <section class="glass-card rounded-xl p-4">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
          <div class="relative md:col-span-5">
            <i class="ti ti-user absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
            <select v-model="filters.employeeId" class="form-control pl-9 appearance-none">
              <option :value="''">All employees</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.fullName }} ({{ e.employeeId }})</option>
            </select>
          </div>

          <div class="md:col-span-5 flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1">
            <button
              v-for="s in (['', 'pending', 'approved', 'rejected'] as const)"
              :key="s || 'all'"
              class="flex-1 px-3 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
              :class="filters.status === s ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
              @click="filters.status = s"
            >
              {{ s || 'all' }}
            </button>
          </div>

          <div class="md:col-span-2">
            <button
              class="btn btn-ghost text-xs w-full"
              :disabled="!filters.employeeId"
              @click="openBalanceModal"
            >
              <i class="ti ti-calendar-stats" />Balance
            </button>
          </div>
        </div>
      </section>

      <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
        <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
        <span class="text-xs text-(--text-muted) font-medium">Loading leave requests...</span>
      </div>

      <div v-else-if="leaves.length === 0" class="glass-card rounded-2xl py-20 text-center">
        <i class="ti ti-calendar-off text-4xl text-(--text-muted)" />
        <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No leave requests</h4>
        <p class="text-xs text-(--text-muted) mt-1">Adjust filters, or submit the first request.</p>
      </div>

      <section v-else class="glass-card rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead>
              <tr class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                <th class="px-4 py-3 font-semibold">Employee</th>
                <th class="px-4 py-3 font-semibold">Leave type</th>
                <th class="px-4 py-3 font-semibold">Dates</th>
                <th class="px-4 py-3 font-semibold font-mono text-right">Days</th>
                <th class="px-4 py-3 font-semibold">Reason</th>
                <th class="px-4 py-3 font-semibold">Status</th>
                <th class="px-4 py-3 font-semibold text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-(--border-color)">
              <tr v-for="lv in leaves" :key="lv.id" class="hover:bg-(--bg-muted) transition-colors">
                <td class="px-4 py-3">
                  <div class="text-xs font-semibold text-(--text-heading)">{{ lv.employee?.fullName || '—' }}</div>
                  <div class="text-xxs text-(--text-muted) font-mono">{{ lv.employee?.employeeId || '' }}</div>
                </td>
                <td class="px-4 py-3 text-xs">{{ lv.leaveType?.name || '—' }}</td>
                <td class="px-4 py-3 text-xs font-mono">
                  <div>{{ lv.startDate }}</div>
                  <div class="text-(--text-muted) text-xxs">→ {{ lv.endDate }}</div>
                </td>
                <td class="px-4 py-3 font-mono text-xs text-right">{{ lv.days }}</td>
                <td class="px-4 py-3 text-xs text-(--text-body) max-w-[240px] truncate" :title="lv.reason || ''">
                  {{ lv.reason || '—' }}
                </td>
                <td class="px-4 py-3">
                  <Badge :variant="statusVariant(lv.status)" :dot="true">{{ lv.status }}</Badge>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="inline-flex items-center gap-1">
                    <template v-if="lv.status === 'pending' && canApprove">
                      <button
                        class="btn text-xs px-2 py-1 text-(--color-success) hover:bg-(--color-success-subtle, var(--color-success)/10)"
                        title="Approve"
                        @click="approve(lv)"
                      >
                        <i class="ti ti-check" />
                      </button>
                      <button
                        class="btn text-xs px-2 py-1 text-(--color-warning) hover:bg-(--color-warning-subtle, var(--color-warning)/10)"
                        title="Reject"
                        @click="reject(lv)"
                      >
                        <i class="ti ti-x" />
                      </button>
                    </template>
                    <button
                      v-if="lv.status === 'pending'"
                      class="btn text-xs px-2 py-1 text-(--color-danger) hover:bg-(--color-danger-subtle)"
                      title="Withdraw"
                      @click="withdraw(lv)"
                    >
                      <i class="ti ti-trash" />
                    </button>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <Pagination
          :page="pagination.page"
          :limit="pagination.limit"
          :total="pagination.total"
          :total-pages="pagination.totalPages"
          @update:page="(p) => { pagination.page = p; loadLeaves() }"
          @update:limit="(l) => { pagination.limit = l; pagination.page = 1; loadLeaves() }"
        />
      </section>

      <!-- Submit modal -->
      <div v-if="showSubmitModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="glass-card rounded-2xl w-full max-w-lg p-6 shadow-(--shadow-lg) bg-(--bg-card)">
          <header class="flex items-center justify-between mb-5">
            <h3 class="text-base font-semibold text-(--text-heading)">Submit leave request</h3>
            <button class="topbar-btn" @click="showSubmitModal = false"><i class="ti ti-x" /></button>
          </header>

          <form class="grid grid-cols-1 sm:grid-cols-2 gap-4" @submit.prevent="submitLeave">
            <div class="sm:col-span-2">
              <label class="form-label">Employee</label>
              <select v-model="form.employee_id" required class="form-control">
                <option value="" disabled>Select employee...</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.fullName }} ({{ e.employeeId }})</option>
              </select>
            </div>

            <div class="sm:col-span-2">
              <label class="form-label">Leave type</label>
              <select v-model="form.leave_type_id" required class="form-control">
                <option value="" disabled>Select leave type...</option>
                <option v-for="t in leaveTypes" :key="t.id" :value="t.id">
                  {{ t.name }} · {{ t.annualAllowance }} days/yr
                </option>
              </select>
            </div>

            <div>
              <label class="form-label">Start date</label>
              <input v-model="form.start_date" type="date" required class="form-control" />
            </div>
            <div>
              <label class="form-label">End date</label>
              <input v-model="form.end_date" type="date" required class="form-control" />
            </div>

            <div class="sm:col-span-2">
              <label class="form-label">Reason (optional)</label>
              <textarea v-model="form.reason" rows="3" class="form-control" placeholder="Family event, medical, etc." />
            </div>

            <div v-if="formError" class="sm:col-span-2 text-xs text-(--color-danger) bg-(--color-danger-subtle) px-3 py-2 rounded">
              {{ formError }}
            </div>

            <footer class="sm:col-span-2 pt-4 border-t border-(--border-color) flex justify-end gap-2">
              <button type="button" class="btn btn-ghost text-xs" @click="showSubmitModal = false">Cancel</button>
              <button type="submit" class="btn btn-primary text-xs" :disabled="saving">
                <i class="ti ti-send" />{{ saving ? 'Submitting...' : 'Submit' }}
              </button>
            </footer>
          </form>
        </div>
      </div>

      <!-- Balance modal -->
      <div v-if="showBalanceModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="glass-card rounded-2xl w-full max-w-lg p-6 shadow-(--shadow-lg) bg-(--bg-card)">
          <header class="flex items-center justify-between mb-5">
            <div>
              <h3 class="text-base font-semibold text-(--text-heading)">Leave balance</h3>
              <p class="text-xxs text-(--text-muted) mt-1">{{ balanceEmployee?.fullName }} · current year</p>
            </div>
            <button class="topbar-btn" @click="showBalanceModal = false"><i class="ti ti-x" /></button>
          </header>

          <div v-if="balanceLoading" class="py-10 text-center">
            <span class="w-6 h-6 inline-block rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
          </div>
          <div v-else-if="balance.length === 0" class="py-10 text-center text-xs text-(--text-muted)">
            No leave types configured yet.
          </div>
          <ul v-else class="space-y-2">
            <li v-for="b in balance" :key="b.leaveTypeId" class="flex items-center justify-between rounded-lg border border-(--border-color) px-3 py-2.5">
              <div>
                <div class="text-xs font-semibold text-(--text-heading)">{{ b.name }}</div>
                <div class="text-xxs text-(--text-muted)">{{ b.used }} of {{ b.annualAllowance }} days used</div>
              </div>
              <div class="text-right">
                <div class="text-sm font-mono font-semibold text-(--color-primary)">{{ b.remaining }}</div>
                <div class="text-xxs text-(--text-muted) uppercase tracking-widest">remaining</div>
              </div>
            </li>
          </ul>
        </div>
      </div>
    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'

interface EmployeeLite { id: string; employeeId: string; fullName: string }
interface LeaveType { id: string; name: string; annualAllowance: number }
interface Leave {
  id: string
  employeeId: string
  leaveTypeId: string
  startDate: string
  endDate: string
  days: number
  reason: string | null
  status: 'pending' | 'approved' | 'rejected'
  employee?: EmployeeLite
  leaveType?: LeaveType
}
interface BalanceRow { leaveTypeId: string; name: string; annualAllowance: number; used: number; remaining: number }
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

const api = useApi()
const authStore = useAuthStore()
const toast = useToast()
const canApprove = computed(() => authStore.hasPermission('hrm.leave.write'))

const leaves = ref<Leave[]>([])
const employees = ref<EmployeeLite[]>([])
const leaveTypes = ref<LeaveType[]>([])
const loading = ref(false)

const pagination = reactive({ page: 1, limit: 15, total: 0, totalPages: 1 })
const filters = reactive({ employeeId: '', status: '' as '' | 'pending' | 'approved' | 'rejected' })

const showSubmitModal = ref(false)
const saving = ref(false)
const formError = ref<string | null>(null)
const form = reactive({
  employee_id: '',
  leave_type_id: '',
  start_date: '',
  end_date: '',
  reason: ''
})

const showBalanceModal = ref(false)
const balanceLoading = ref(false)
const balance = ref<BalanceRow[]>([])
const balanceEmployee = computed(() => employees.value.find(e => e.id === filters.employeeId) || null)

const statusVariant = (s: string): 'success' | 'warning' | 'danger' =>
  s === 'approved' ? 'success' : s === 'pending' ? 'warning' : 'danger'

const loadLookups = async () => {
  try {
    const [e, t] = await Promise.all([
      api.get<Paginated<EmployeeLite>>('/employees?limit=100'),
      api.get<Paginated<LeaveType>>('/leave-types?limit=100')
    ])
    employees.value = e.data
    leaveTypes.value = t.data
  } catch (err) {
    console.error('Failed to load lookups', err)
  }
}

const loadLeaves = async () => {
  loading.value = true
  try {
    const query = new URLSearchParams({ page: String(pagination.page), limit: String(pagination.limit) })
    if (filters.employeeId) query.set('employeeId', filters.employeeId)
    if (filters.status) query.set('status', filters.status)

    const res = await api.get<Paginated<Leave>>(`/leaves?${query.toString()}`)
    leaves.value = res.data
    pagination.total = res.pagination.total
    pagination.totalPages = res.pagination.totalPages
  } catch (err) {
    console.error('Failed to load leaves', err)
    leaves.value = []
  } finally {
    loading.value = false
  }
}

watch(() => [filters.employeeId, filters.status], () => {
  pagination.page = 1
  loadLeaves()
})

const openSubmitModal = () => {
  Object.assign(form, { employee_id: '', leave_type_id: '', start_date: '', end_date: '', reason: '' })
  formError.value = null
  showSubmitModal.value = true
}

const submitLeave = async () => {
  saving.value = true
  formError.value = null
  try {
    await api.post('/leaves', {
      employee_id: form.employee_id,
      leave_type_id: form.leave_type_id,
      start_date: form.start_date,
      end_date: form.end_date,
      reason: form.reason || null
    })
    showSubmitModal.value = false
    await loadLeaves()
  } catch (err: any) {
    formError.value = err.data?.message || 'Failed to submit leave request.'
  } finally {
    saving.value = false
  }
}

const approve = async (lv: Leave) => {
  try {
    await api.post(`/leaves/${lv.id}/approve`)
    await loadLeaves()
  } catch (err: any) {
    toast.error('Failed to approve leave.', err?.data?.message)
  }
}

const reject = async (lv: Leave) => {
  if (!confirm('Reject this leave request?')) return
  try {
    await api.post(`/leaves/${lv.id}/reject`)
    await loadLeaves()
  } catch (err: any) {
    toast.error('Failed to reject leave.', err?.data?.message)
  }
}

const withdraw = async (lv: Leave) => {
  if (!confirm('Withdraw this leave request?')) return
  try {
    await api.delete(`/leaves/${lv.id}`)
    await loadLeaves()
  } catch (err: any) {
    toast.error('Failed to withdraw leave.', err?.data?.message)
  }
}

const openBalanceModal = async () => {
  if (!filters.employeeId) return
  showBalanceModal.value = true
  balanceLoading.value = true
  balance.value = []
  try {
    const res = await api.get<{ data: BalanceRow[] }>(`/employees/${filters.employeeId}/leave-balance`)
    balance.value = res.data || []
  } catch (err) {
    console.error('Failed to load leave balance', err)
  } finally {
    balanceLoading.value = false
  }
}

onMounted(async () => {
  await Promise.all([loadLookups(), loadLeaves()])
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
.topbar-btn:hover { background: var(--bg-muted); color: var(--text-heading); }
</style>
