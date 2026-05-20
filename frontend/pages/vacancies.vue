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
            <input v-model="filters.search" type="search" placeholder="Search title or location..." class="form-control pl-9" />
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
        <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
        <span class="text-xs text-(--text-muted) font-medium">Loading vacancies...</span>
      </div>

      <div v-else-if="vacancies.length === 0" class="glass-card rounded-2xl py-20 text-center">
        <i class="ti ti-briefcase-off text-4xl text-(--text-muted)" />
        <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No vacancies match</h4>
        <p class="text-xs text-(--text-muted) mt-1">Open a requisition to start hiring.</p>
      </div>

      <section v-else class="glass-card rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead>
              <tr class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
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
              <tr v-for="v in vacancies" :key="v.id" class="hover:bg-(--bg-muted) transition-colors">
                <td class="px-4 py-3">
                  <div class="text-xs font-semibold text-(--text-heading)">{{ v.title }}</div>
                  <div class="text-xxs text-(--text-muted) font-mono">{{ v.postedAt || '—' }}</div>
                </td>
                <td class="px-4 py-3 text-xs">{{ v.department?.name || '—' }}</td>
                <td class="px-4 py-3 text-xs">{{ v.location || '—' }}</td>
                <td class="px-4 py-3 text-xs capitalize">{{ (v.employmentType || '').replace('_', ' ') }}</td>
                <td class="px-4 py-3 font-mono text-xs text-right">
                  <span v-if="v.salaryMin != null && v.salaryMax != null">
                    {{ formatMoney(v.salaryMin) }} – {{ formatMoney(v.salaryMax) }}
                  </span>
                  <span v-else class="text-(--text-muted)">—</span>
                </td>
                <td class="px-4 py-3 font-mono text-xs text-right">
                  <NuxtLink :to="`/applications?vacancyId=${v.id}`" class="text-(--color-primary) hover:underline">
                    {{ v.applicationCount ?? 0 }}
                  </NuxtLink>
                </td>
                <td class="px-4 py-3">
                  <Badge :variant="statusVariant(v.status)" :dot="true">{{ v.status }}</Badge>
                </td>
                <td class="px-4 py-3 text-center">
                  <div class="inline-flex items-center gap-1">
                    <button class="btn btn-ghost text-xs px-2 py-1" @click="openEditModal(v)" title="Edit">
                      <i class="ti ti-pencil" />
                    </button>
                    <button
                      v-if="v.status === 'draft' && canWrite"
                      class="btn btn-primary text-xs px-2 py-1"
                      @click="publish(v)"
                      title="Publish"
                    >
                      <i class="ti ti-send" />Publish
                    </button>
                    <button
                      v-if="['open','paused'].includes(v.status) && canWrite"
                      class="btn text-xs px-2 py-1 text-(--color-warning) border border-(--color-warning)/20 hover:bg-(--color-warning-subtle, var(--color-warning)/10)"
                      @click="closeVacancy(v)"
                      title="Close"
                    >
                      <i class="ti ti-lock" />Close
                    </button>
                    <button
                      v-if="canWrite"
                      class="btn text-xs px-2 py-1 text-(--color-danger) hover:bg-(--color-danger-subtle)"
                      @click="archive(v)"
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
          @update:page="(p) => { pagination.page = p; loadVacancies() }"
          @update:limit="(l) => { pagination.limit = l; pagination.page = 1; loadVacancies() }"
        />
      </section>

      <!-- Create / edit modal -->
      <div v-if="showModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="glass-card rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto p-6 shadow-(--shadow-lg) bg-(--bg-card)">
          <header class="flex items-center justify-between mb-5">
            <h3 class="text-base font-semibold text-(--text-heading)">
              {{ editing ? 'Edit vacancy' : 'New vacancy' }}
            </h3>
            <button class="topbar-btn" @click="closeModal"><i class="ti ti-x" /></button>
          </header>

          <form class="form-grid" @submit.prevent="saveVacancy">
            <div class="form-grid-full">
              <label class="form-label form-label-required">Title</label>
              <input v-model="form.title" type="text" required class="form-control" placeholder="Senior Backend Engineer" />
            </div>

            <div class="form-grid-full">
              <label class="form-label">Description</label>
              <textarea v-model="form.description" rows="3" class="form-control" placeholder="Role summary, key responsibilities..." />
            </div>

            <div>
              <label class="form-label">Location</label>
              <input v-model="form.location" type="text" class="form-control" placeholder="Phnom Penh / Remote" />
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
              <input v-model.number="form.experience_min_years" type="number" min="0" max="60" class="form-control font-mono" />
            </div>
            <div>
              <label class="form-label">Experience max (years)</label>
              <input v-model.number="form.experience_max_years" type="number" min="0" max="60" class="form-control font-mono" />
            </div>

            <div>
              <label class="form-label">Salary min</label>
              <input v-model.number="form.salary_min" type="number" step="0.01" min="0" class="form-control font-mono" />
            </div>
            <div>
              <label class="form-label">Salary max</label>
              <input v-model.number="form.salary_max" type="number" step="0.01" min="0" class="form-control font-mono" />
            </div>

            <div>
              <label class="form-label">Vacancies count</label>
              <input v-model.number="form.vacancies_count" type="number" min="1" class="form-control font-mono" />
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
    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useApi } from '~/composables/useApi'
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
  if (!confirm(`Publish "${v.title}" and open it for applications?`)) return
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

onMounted(async () => {
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
.topbar-btn:hover { background: var(--bg-muted); color: var(--text-heading); }
</style>
