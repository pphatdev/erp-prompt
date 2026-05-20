<template>
  <NuxtLayout name="default">
    <div class="space-y-6">
      <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
          <h1 class="text-xl font-semibold">Positions</h1>
          <p class="text-xs text-(--text-muted) mt-1">Job titles and grade levels assigned to employees.</p>
        </div>
        <button class="btn btn-primary text-xs" @click="openCreateModal">
          <i class="ti ti-plus" />New position
        </button>
      </header>

      <!-- Search -->
      <section class="glass-card rounded-xl p-4">
        <div class="relative w-full md:w-96">
          <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
          <input v-model="search" type="search" placeholder="Search title or level..." class="form-control pl-9" />
        </div>
      </section>

      <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
        <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
        <span class="text-xs text-(--text-muted) font-medium">Loading positions...</span>
      </div>

      <div v-else-if="filtered.length === 0" class="glass-card rounded-2xl py-20 text-center">
        <i class="ti ti-briefcase text-4xl text-(--text-muted)" />
        <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No positions</h4>
        <p class="text-xs text-(--text-muted) mt-1">Define titles before hiring employees.</p>
      </div>

      <section v-else class="glass-card rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead>
              <tr class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                <th class="px-4 py-3 font-semibold">Title</th>
                <th class="px-4 py-3 font-semibold font-mono">Level</th>
                <th class="px-4 py-3 font-semibold">Created</th>
                <th class="px-4 py-3 font-semibold text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-(--border-color)">
              <tr v-for="p in filtered" :key="p.id" class="hover:bg-(--bg-muted) transition-colors">
                <td class="px-4 py-3">
                  <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-(--color-info-subtle, var(--color-primary-subtle)) text-(--color-info, var(--color-primary)) flex items-center justify-center">
                      <i class="ti ti-briefcase text-sm" />
                    </span>
                    <span class="text-xs font-semibold text-(--text-heading)">{{ p.title }}</span>
                  </div>
                </td>
                <td class="px-4 py-3">
                  <Badge v-if="p.level" variant="secondary">{{ p.level }}</Badge>
                  <span v-else class="text-xxs text-(--text-muted)">—</span>
                </td>
                <td class="px-4 py-3 font-mono text-xxs text-(--text-muted)">{{ formatDate(p.createdAt) }}</td>
                <td class="px-4 py-3 text-center">
                  <div class="inline-flex items-center gap-1">
                    <button class="btn btn-ghost text-xs px-2 py-1" @click="openEditModal(p)" title="Edit">
                      <i class="ti ti-pencil" />
                    </button>
                    <button
                      class="btn text-xs px-2 py-1 text-(--color-danger) hover:bg-(--color-danger-subtle)"
                      @click="removePosition(p)"
                      title="Remove"
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
          @update:page="(p) => { pagination.page = p; loadPositions() }"
          @update:limit="(l) => { pagination.limit = l; pagination.page = 1; loadPositions() }"
        />
      </section>

      <!-- Modal -->
      <div v-if="showModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="glass-card rounded-2xl w-full max-w-md p-6 shadow-(--shadow-lg) bg-(--bg-card)">
          <header class="flex items-center justify-between mb-5">
            <h3 class="text-base font-semibold text-(--text-heading)">
              {{ editing ? 'Edit position' : 'New position' }}
            </h3>
            <button class="topbar-btn" @click="closeModal"><i class="ti ti-x" /></button>
          </header>

          <form class="space-y-4" @submit.prevent="savePosition">
            <div>
              <label class="form-label">Title</label>
              <input v-model="form.title" type="text" required class="form-control" placeholder="Senior Backend Engineer" />
            </div>
            <div>
              <label class="form-label">Level</label>
              <input v-model="form.level" type="text" class="form-control font-mono" placeholder="L5" />
            </div>

            <div v-if="formError" class="text-xs text-(--color-danger) bg-(--color-danger-subtle) px-3 py-2 rounded">
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
    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useApi } from '~/composables/useApi'
import { useToast } from '~/composables/useToast'

interface Position { id: string; title: string; level: string | null; createdAt: string | null }
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

const api = useApi()
const toast = useToast()
const positions = ref<Position[]>([])
const loading = ref(false)
const search = ref('')

const pagination = reactive({ page: 1, limit: 15, total: 0, totalPages: 1 })

const showModal = ref(false)
const editing = ref<Position | null>(null)
const saving = ref(false)
const formError = ref<string | null>(null)
const form = reactive({ title: '', level: '' })

const filtered = computed(() => {
  if (!search.value) return positions.value
  const q = search.value.toLowerCase()
  return positions.value.filter(p =>
    p.title.toLowerCase().includes(q) || (p.level || '').toLowerCase().includes(q)
  )
})

const formatDate = (iso: string | null) => iso ? new Date(iso).toLocaleDateString() : '—'

const loadPositions = async () => {
  loading.value = true
  try {
    const res = await api.get<Paginated<Position>>(`/positions?page=${pagination.page}&limit=${pagination.limit}`)
    positions.value = res.data
    pagination.total = res.pagination.total
    pagination.totalPages = res.pagination.totalPages
  } catch (err) {
    console.error('Failed to load positions', err)
    positions.value = []
  } finally {
    loading.value = false
  }
}

const resetForm = () => { form.title = ''; form.level = ''; formError.value = null }
const openCreateModal = () => { editing.value = null; resetForm(); showModal.value = true }
const openEditModal = (p: Position) => {
  editing.value = p
  form.title = p.title
  form.level = p.level ?? ''
  formError.value = null
  showModal.value = true
}
const closeModal = () => { showModal.value = false; editing.value = null }

const savePosition = async () => {
  saving.value = true
  formError.value = null
  try {
    const payload = { title: form.title, level: form.level || null }
    if (editing.value) {
      await api.put(`/positions/${editing.value.id}`, payload)
    } else {
      await api.post('/positions', payload)
    }
    showModal.value = false
    await loadPositions()
  } catch (err: any) {
    formError.value = err.data?.message || 'Failed to save position.'
  } finally {
    saving.value = false
  }
}

const removePosition = async (p: Position) => {
  if (!confirm(`Remove position "${p.title}"? Employees currently assigned will become unassigned.`)) return
  try {
    await api.delete(`/positions/${p.id}`)
    await loadPositions()
  } catch (err: any) {
    console.error('Failed to remove position', err)
    toast.error('Failed to remove position.', err?.data?.message)
  }
}

onMounted(loadPositions)
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
