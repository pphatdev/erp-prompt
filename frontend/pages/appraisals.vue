<template>
  <NuxtLayout name="default">
    <div class="space-y-6">
      <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
          <h1 class="text-xl font-semibold">Performance appraisals</h1>
          <p class="text-xs text-(--text-muted) mt-1">Cycle reviews with ratings, strengths, growth areas, and OKR goals.</p>
        </div>
        <button v-if="canWrite" class="btn btn-primary text-xs" @click="openCreateModal">
          <i class="ti ti-plus" />New appraisal
        </button>
      </header>

      <!-- Filters -->
      <section class="glass-card rounded-xl p-4">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
          <div class="md:col-span-4">
            <select v-model="filters.employeeId" class="form-control">
              <option :value="''">All employees</option>
              <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.fullName }} ({{ e.employeeId }})</option>
            </select>
          </div>
          <div class="md:col-span-3">
            <input v-model="filters.cycle" type="text" placeholder="Cycle (e.g. 2026-Q2)" class="form-control" />
          </div>
          <div class="md:col-span-5 flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1">
            <button
              v-for="s in (['', 'draft', 'submitted', 'reviewed', 'closed'] as const)"
              :key="s || 'all'"
              class="flex-1 px-3 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
              :class="filters.status === s ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
              @click="filters.status = s"
            >
              {{ s || 'all' }}
            </button>
          </div>
        </div>
      </section>

      <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
        <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
        <span class="text-xs text-(--text-muted) font-medium">Loading appraisals...</span>
      </div>

      <div v-else-if="appraisals.length === 0" class="glass-card rounded-2xl py-20 text-center">
        <i class="ti ti-clipboard-list text-4xl text-(--text-muted)" />
        <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No appraisals</h4>
        <p class="text-xs text-(--text-muted) mt-1">Open the first review cycle for an employee.</p>
      </div>

      <section v-else class="glass-card rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead>
              <tr class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                <th class="px-4 py-3 font-semibold">Employee</th>
                <th class="px-4 py-3 font-semibold">Reviewer</th>
                <th class="px-4 py-3 font-semibold font-mono">Cycle</th>
                <th class="px-4 py-3 font-semibold">Period</th>
                <th class="px-4 py-3 font-semibold font-mono text-right">Rating</th>
                <th class="px-4 py-3 font-semibold">Status</th>
                <th class="px-4 py-3 font-semibold text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-(--border-color)">
              <tr v-for="a in appraisals" :key="a.id" class="hover:bg-(--bg-muted) transition-colors">
                <td class="px-4 py-3 text-xs">
                  <div class="font-semibold text-(--text-heading)">{{ a.employee?.fullName || '—' }}</div>
                  <div class="text-xxs text-(--text-muted) font-mono">{{ a.employee?.employeeId || '' }}</div>
                </td>
                <td class="px-4 py-3 text-xs">{{ a.reviewer?.fullName || '—' }}</td>
                <td class="px-4 py-3 font-mono text-xs">{{ a.cycle }}</td>
                <td class="px-4 py-3 text-xxs font-mono">
                  <div>{{ a.periodStart }}</div>
                  <div class="text-(--text-muted)">→ {{ a.periodEnd }}</div>
                </td>
                <td class="px-4 py-3 font-mono text-xs text-right">
                  <span v-if="a.overallRating != null" class="font-semibold" :class="ratingColor(a.overallRating)">
                    {{ a.overallRating.toFixed(2) }}
                  </span>
                  <span v-else class="text-(--text-muted)">{{ a.status === 'reviewed' || a.status === 'closed' ? '••••' : '—' }}</span>
                </td>
                <td class="px-4 py-3">
                  <Badge :variant="statusVariant(a.status)" :dot="true">{{ a.status }}</Badge>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="inline-flex items-center gap-1">
                    <button class="btn btn-ghost text-xs px-2 py-1" @click="openEditModal(a)" title="Edit / view">
                      <i class="ti ti-pencil" />
                    </button>
                    <button
                      v-if="a.status === 'draft' && canWrite"
                      class="btn btn-primary text-xs px-2 py-1"
                      @click="submit(a)"
                      title="Submit"
                    >
                      <i class="ti ti-send" />Submit
                    </button>
                    <button
                      v-if="a.status === 'submitted' && canWrite"
                      class="btn text-xs px-2 py-1 text-(--color-info) border border-(--color-info)/20 hover:bg-(--color-info-subtle, var(--color-info)/10)"
                      @click="openReviewModal(a)"
                      title="Review"
                    >
                      <i class="ti ti-stars" />Review
                    </button>
                    <button
                      v-if="a.status === 'reviewed' && canWrite"
                      class="btn text-xs px-2 py-1 text-(--color-warning) border border-(--color-warning)/20 hover:bg-(--color-warning-subtle, var(--color-warning)/10)"
                      @click="closeAppraisal(a)"
                      title="Close"
                    >
                      <i class="ti ti-lock" />Close
                    </button>
                    <button
                      v-if="a.status !== 'closed' && canWrite"
                      class="btn text-xs px-2 py-1 text-(--color-danger) hover:bg-(--color-danger-subtle)"
                      @click="archive(a)"
                      title="Archive"
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
          @update:page="(p) => { pagination.page = p; loadAppraisals() }"
          @update:limit="(l) => { pagination.limit = l; pagination.page = 1; loadAppraisals() }"
        />
      </section>

      <!-- Create / edit modal -->
      <div v-if="showModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="glass-card rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6 shadow-(--shadow-lg) bg-(--bg-card)">
          <header class="flex items-center justify-between mb-5">
            <h3 class="text-base font-semibold text-(--text-heading)">
              {{ editing ? 'Edit appraisal' : 'New appraisal' }}
            </h3>
            <button class="topbar-btn" @click="closeModal"><i class="ti ti-x" /></button>
          </header>

          <form class="form-grid" @submit.prevent="saveAppraisal">
            <div>
              <label class="form-label form-label-required">Employee</label>
              <select v-model="form.employee_id" required class="form-control" :disabled="!!editing">
                <option value="" disabled>Select employee...</option>
                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.fullName }} ({{ e.employeeId }})</option>
              </select>
            </div>
            <div>
              <label class="form-label">Reviewer</label>
              <select v-model="form.reviewer_id" class="form-control">
                <option :value="''">— Self review</option>
                <option v-for="e in employees" :key="e.id" :value="e.id" :disabled="e.id === form.employee_id">
                  {{ e.fullName }} ({{ e.employeeId }})
                </option>
              </select>
            </div>

            <div>
              <label class="form-label form-label-required">Cycle</label>
              <input v-model="form.cycle" type="text" required class="form-control font-mono" placeholder="2026-Q2" />
            </div>
            <div></div>

            <div>
              <label class="form-label form-label-required">Period start</label>
              <input v-model="form.period_start" type="date" required class="form-control" />
            </div>
            <div>
              <label class="form-label form-label-required">Period end</label>
              <input v-model="form.period_end" type="date" required class="form-control" />
            </div>

            <div class="form-grid-full">
              <label class="form-label">Strengths</label>
              <textarea v-model="form.strengths" rows="3" class="form-control" placeholder="Consistent shipping, mentors juniors..." />
            </div>

            <div class="form-grid-full">
              <label class="form-label">Areas for improvement</label>
              <textarea v-model="form.improvements" rows="3" class="form-control" placeholder="Cross-team alignment, deeper specs..." />
            </div>

            <div class="form-grid-full">
              <div class="flex items-center justify-between mb-2">
                <label class="form-label mb-0">Goals (OKR-style)</label>
                <button type="button" class="btn btn-ghost text-xs px-2 py-1" @click="addGoal">
                  <i class="ti ti-plus" />Add goal
                </button>
              </div>
              <div v-if="form.goals.length === 0" class="rounded-lg bg-(--bg-muted) border border-dashed border-(--border-color) p-4 text-xxs text-(--text-muted) text-center">
                No goals yet. Add one to track OKRs.
              </div>
              <div v-else class="space-y-2">
                <div
                  v-for="(goal, idx) in form.goals"
                  :key="idx"
                  class="rounded-lg border border-(--border-color) p-3 grid grid-cols-12 gap-2 items-center"
                >
                  <input v-model="goal.title" type="text" required placeholder="Goal title" class="form-control col-span-6" />
                  <select v-model="goal.status" class="form-control col-span-3">
                    <option value="pending">Pending</option>
                    <option value="in_progress">In progress</option>
                    <option value="achieved">Achieved</option>
                    <option value="missed">Missed</option>
                  </select>
                  <input v-model="goal.due" type="date" class="form-control col-span-2" />
                  <button type="button" class="col-span-1 text-(--color-danger) hover:text-(--color-danger) p-2" @click="removeGoal(idx)" title="Remove">
                    <i class="ti ti-trash" />
                  </button>
                </div>
              </div>
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

      <!-- Review modal -->
      <div v-if="showReviewModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="glass-card rounded-2xl w-full max-w-lg p-6 shadow-(--shadow-lg) bg-(--bg-card)">
          <header class="flex items-center justify-between mb-5">
            <div>
              <h3 class="text-base font-semibold text-(--text-heading)">Reviewer summary</h3>
              <p class="text-xxs text-(--text-muted) mt-1">{{ reviewing?.employee?.fullName }} · {{ reviewing?.cycle }}</p>
            </div>
            <button class="topbar-btn" @click="showReviewModal = false"><i class="ti ti-x" /></button>
          </header>

          <form class="form-stack" @submit.prevent="submitReview">
            <div>
              <label class="form-label form-label-required">Overall rating (0.00 – 5.00)</label>
              <input v-model.number="reviewForm.overall_rating" type="number" min="0" max="5" step="0.01" required class="form-control font-mono" />
            </div>
            <div>
              <label class="form-label">Strengths (reviewer-confirmed)</label>
              <textarea v-model="reviewForm.strengths" rows="3" class="form-control" />
            </div>
            <div>
              <label class="form-label">Growth areas</label>
              <textarea v-model="reviewForm.improvements" rows="3" class="form-control" />
            </div>

            <div v-if="reviewError" class="form-error">{{ reviewError }}</div>

            <footer class="pt-4 border-t border-(--border-color) flex justify-end gap-2">
              <button type="button" class="btn btn-ghost text-xs" @click="showReviewModal = false">Cancel</button>
              <button type="submit" class="btn btn-primary text-xs" :disabled="reviewing === null || reviewSaving">
                <i class="ti ti-stars" />{{ reviewSaving ? 'Saving...' : 'Submit review' }}
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
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'

interface EmployeeLite { id: string; employeeId: string; fullName: string }

interface Goal { title: string; status?: 'pending' | 'in_progress' | 'achieved' | 'missed'; due?: string | null }

type AppraisalStatus = 'draft' | 'submitted' | 'reviewed' | 'closed'

interface Appraisal {
  id: string
  employeeId: string
  reviewerId: string | null
  cycle: string
  periodStart: string
  periodEnd: string
  overallRating: number | null
  strengths: string | null
  improvements: string | null
  goals: Goal[] | null
  status: AppraisalStatus
  submittedAt: string | null
  reviewedAt: string | null
  employee?: EmployeeLite
  reviewer?: EmployeeLite
}
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

const api = useApi()
const authStore = useAuthStore()
const toast = useToast()
const canWrite = computed(() => authStore.hasPermission('hrm.performance.write'))

const appraisals = ref<Appraisal[]>([])
const employees = ref<EmployeeLite[]>([])
const loading = ref(false)

const pagination = reactive({ page: 1, limit: 15, total: 0, totalPages: 1 })
const filters = reactive({
  employeeId: '',
  cycle: '',
  status: '' as '' | AppraisalStatus
})

const showModal = ref(false)
const editing = ref<Appraisal | null>(null)
const saving = ref(false)
const formError = ref<string | null>(null)
const form = reactive({
  employee_id: '',
  reviewer_id: '',
  cycle: '',
  period_start: '',
  period_end: '',
  strengths: '',
  improvements: '',
  goals: [] as Goal[]
})

const showReviewModal = ref(false)
const reviewing = ref<Appraisal | null>(null)
const reviewSaving = ref(false)
const reviewError = ref<string | null>(null)
const reviewForm = reactive({
  overall_rating: 0,
  strengths: '',
  improvements: ''
})

const statusVariant = (s: AppraisalStatus): 'secondary' | 'info' | 'warning' | 'success' => {
  if (s === 'submitted') return 'info'
  if (s === 'reviewed') return 'warning'
  if (s === 'closed') return 'success'
  return 'secondary'
}

const ratingColor = (n: number) => {
  if (n >= 4) return 'text-(--color-success)'
  if (n >= 3) return 'text-(--color-primary)'
  if (n >= 2) return 'text-(--color-warning)'
  return 'text-(--color-danger)'
}

const loadLookups = async () => {
  try {
    const e = await api.get<Paginated<EmployeeLite>>('/employees?limit=100')
    employees.value = e.data
  } catch (err) {
    console.error('Failed to load employees', err)
  }
}

const loadAppraisals = async () => {
  loading.value = true
  try {
    const q = new URLSearchParams({ page: String(pagination.page), limit: String(pagination.limit) })
    if (filters.employeeId) q.set('employeeId', filters.employeeId)
    if (filters.cycle) q.set('cycle', filters.cycle)
    if (filters.status) q.set('status', filters.status)

    const res = await api.get<Paginated<Appraisal>>(`/appraisals?${q.toString()}`)
    appraisals.value = res.data
    pagination.total = res.pagination.total
    pagination.totalPages = res.pagination.totalPages
  } catch (err) {
    console.error('Failed to load appraisals', err)
    appraisals.value = []
  } finally {
    loading.value = false
  }
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(() => [filters.employeeId, filters.cycle, filters.status], () => {
  if (searchTimer) clearTimeout(searchTimer)
  searchTimer = setTimeout(() => {
    pagination.page = 1
    loadAppraisals()
  }, 300)
})

const resetForm = () => {
  Object.assign(form, {
    employee_id: '', reviewer_id: '', cycle: '',
    period_start: '', period_end: '',
    strengths: '', improvements: '', goals: []
  })
  formError.value = null
}

const openCreateModal = () => { editing.value = null; resetForm(); showModal.value = true }

const openEditModal = (a: Appraisal) => {
  editing.value = a
  Object.assign(form, {
    employee_id: a.employeeId,
    reviewer_id: a.reviewerId ?? '',
    cycle: a.cycle,
    period_start: a.periodStart,
    period_end: a.periodEnd,
    strengths: a.strengths ?? '',
    improvements: a.improvements ?? '',
    goals: Array.isArray(a.goals) ? a.goals.map(g => ({ ...g })) : []
  })
  formError.value = null
  showModal.value = true
}

const closeModal = () => { showModal.value = false; editing.value = null }

const addGoal = () => { form.goals.push({ title: '', status: 'pending', due: '' }) }
const removeGoal = (idx: number) => { form.goals.splice(idx, 1) }

const saveAppraisal = async () => {
  saving.value = true
  formError.value = null
  try {
    const payload: Record<string, any> = {
      cycle: form.cycle,
      period_start: form.period_start,
      period_end: form.period_end,
      strengths: form.strengths || null,
      improvements: form.improvements || null,
      reviewer_id: form.reviewer_id || null,
      goals: form.goals.filter(g => g.title).map(g => ({
        title: g.title,
        status: g.status || 'pending',
        due: g.due || null
      }))
    }

    if (editing.value) {
      await api.put(`/appraisals/${editing.value.id}`, payload)
    } else {
      payload.employee_id = form.employee_id
      await api.post('/appraisals', payload)
    }
    showModal.value = false
    await loadAppraisals()
  } catch (err: any) {
    formError.value = err.data?.message || 'Failed to save appraisal.'
  } finally {
    saving.value = false
  }
}

const submit = async (a: Appraisal) => {
  if (!confirm(`Submit ${a.employee?.fullName}'s appraisal for review?`)) return
  try {
    await api.post(`/appraisals/${a.id}/submit`)
    await loadAppraisals()
  } catch (err: any) {
    toast.error('Failed to submit appraisal.', err?.data?.message)
  }
}

const openReviewModal = (a: Appraisal) => {
  reviewing.value = a
  reviewForm.overall_rating = a.overallRating ?? 0
  reviewForm.strengths = a.strengths ?? ''
  reviewForm.improvements = a.improvements ?? ''
  reviewError.value = null
  showReviewModal.value = true
}

const submitReview = async () => {
  if (!reviewing.value) return
  reviewSaving.value = true
  reviewError.value = null
  try {
    await api.post(`/appraisals/${reviewing.value.id}/review`, {
      overall_rating: reviewForm.overall_rating,
      strengths: reviewForm.strengths || null,
      improvements: reviewForm.improvements || null
    })
    showReviewModal.value = false
    reviewing.value = null
    await loadAppraisals()
  } catch (err: any) {
    reviewError.value = err.data?.message || 'Failed to submit review.'
  } finally {
    reviewSaving.value = false
  }
}

const closeAppraisal = async (a: Appraisal) => {
  if (!confirm(`Close ${a.employee?.fullName}'s appraisal? Closed records are immutable.`)) return
  try {
    await api.post(`/appraisals/${a.id}/close`)
    await loadAppraisals()
  } catch (err: any) {
    toast.error('Failed to close appraisal.', err?.data?.message)
  }
}

const archive = async (a: Appraisal) => {
  if (!confirm(`Archive ${a.employee?.fullName}'s appraisal?`)) return
  try {
    await api.delete(`/appraisals/${a.id}`)
    await loadAppraisals()
  } catch (err: any) {
    toast.error('Failed to archive appraisal.', err?.data?.message)
  }
}

onMounted(async () => {
  await Promise.all([loadLookups(), loadAppraisals()])
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
.topbar-btn:hover { background: var(--bg-muted); color: var(--text-heading); }
</style>
