<template>
  <NuxtLayout name="default">
    <div class="space-y-6">
      <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
          <h1 class="text-xl font-semibold">Leave types</h1>
          <p class="text-xs text-(--text-muted) mt-1">Catalogue of leave categories with annual allowance per employee.</p>
        </div>
        <button class="btn btn-primary text-xs" @click="openCreateModal">
          <i class="ti ti-plus" />New leave type
        </button>
      </header>

      <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
        <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
        <span class="text-xs text-(--text-muted) font-medium">Loading leave types...</span>
      </div>

      <div v-else-if="types.length === 0" class="glass-card rounded-2xl py-20 text-center">
        <i class="ti ti-list text-4xl text-(--text-muted)" />
        <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No leave types yet</h4>
        <p class="text-xs text-(--text-muted) mt-1">Define categories such as Annual, Sick, Maternity before employees can request leave.</p>
      </div>

      <section v-else class="glass-card rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead>
              <tr class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                <th class="px-4 py-3 font-semibold">Name</th>
                <th class="px-4 py-3 font-semibold font-mono text-right">Annual allowance</th>
                <th class="px-4 py-3 font-semibold">Created</th>
                <th class="px-4 py-3 font-semibold text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-(--border-color)">
              <tr v-for="t in types" :key="t.id" class="hover:bg-(--bg-muted) transition-colors">
                <td class="px-4 py-3">
                  <div class="flex items-center gap-3">
                    <span class="w-8 h-8 rounded-lg bg-(--color-primary-subtle) text-(--color-primary) flex items-center justify-center">
                      <i class="ti ti-calendar-event text-sm" />
                    </span>
                    <span class="text-xs font-semibold text-(--text-heading)">{{ t.name }}</span>
                  </div>
                </td>
                <td class="px-4 py-3 font-mono text-xs text-right">{{ t.annualAllowance }} days</td>
                <td class="px-4 py-3 font-mono text-xxs text-(--text-muted)">{{ formatDate(t.createdAt) }}</td>
                <td class="px-4 py-3 text-center">
                  <div class="inline-flex items-center gap-1">
                    <button class="btn btn-ghost text-xs px-2 py-1" @click="openEditModal(t)" title="Edit">
                      <i class="ti ti-pencil" />
                    </button>
                    <button
                      class="btn text-xs px-2 py-1 text-(--color-danger) hover:bg-(--color-danger-subtle)"
                      @click="removeType(t)"
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
          @update:page="(p) => { pagination.page = p; loadTypes() }"
          @update:limit="(l) => { pagination.limit = l; pagination.page = 1; loadTypes() }"
        />
      </section>

      <!-- Modal -->
      <div v-if="showModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
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
              <input v-model.number="form.annual_allowance" type="number" min="0" max="365" required class="form-control font-mono" />
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
import { onMounted, reactive, ref } from 'vue'
import { useApi } from '~/composables/useApi'
import { useToast } from '~/composables/useToast'

interface LeaveType { id: string; name: string; annualAllowance: number; createdAt: string | null }
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

const api = useApi()
const toast = useToast()
const types = ref<LeaveType[]>([])
const loading = ref(false)

const pagination = reactive({ page: 1, limit: 15, total: 0, totalPages: 1 })

const showModal = ref(false)
const editing = ref<LeaveType | null>(null)
const saving = ref(false)
const formError = ref<string | null>(null)
const form = reactive({ name: '', annual_allowance: 0 })

const formatDate = (iso: string | null) => iso ? new Date(iso).toLocaleDateString() : '—'

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

const resetForm = () => { form.name = ''; form.annual_allowance = 0; formError.value = null }
const openCreateModal = () => { editing.value = null; resetForm(); showModal.value = true }
const openEditModal = (t: LeaveType) => {
  editing.value = t
  form.name = t.name
  form.annual_allowance = t.annualAllowance
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
  if (!confirm(`Remove leave type "${t.name}"?`)) return
  try {
    await api.delete(`/leave-types/${t.id}`)
    await loadTypes()
  } catch (err: any) {
    toast.error('Failed to remove leave type.', err?.data?.message)
  }
}

onMounted(loadTypes)
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
