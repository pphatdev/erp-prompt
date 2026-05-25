<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Departments</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Org units used to group employees and route approvals.
                    </p>
                </div>
                <button class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />New department
                </button>
            </header>

            <!-- Search -->
            <section class="glass-card rounded-xl p-4">
                <div class="relative w-full md:w-96">
                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                    <input v-model="search" type="search" placeholder="Search by name or code..."
                        class="form-control pl-9" />
                </div>
            </section>

            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span
                    class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading departments...</span>
            </div>

            <div v-else-if="filtered.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-building text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No departments</h4>
                <p class="text-xs text-(--text-muted) mt-1">Create your first department to organise the workforce.</p>
            </div>

            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr
                                class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                <th class="px-4 py-3 font-semibold">Name</th>
                                <th class="px-4 py-3 font-semibold font-mono">Code</th>
                                <th class="px-4 py-3 font-semibold">Created</th>
                                <th class="px-4 py-3 font-semibold text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-color)">
                            <tr v-for="d in filtered" :key="d.id" class="hover:bg-(--bg-muted) transition-colors">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3">
                                        <span
                                            class="w-8 h-8 rounded-lg bg-(--color-primary-subtle) text-(--color-primary) flex items-center justify-center">
                                            <i class="ti ti-building text-sm" />
                                        </span>
                                        <span class="text-xs font-semibold text-(--text-heading)">{{ d.name }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-mono text-xs text-(--text-body)">{{ d.code }}</td>
                                <td class="px-4 py-3 font-mono text-xxs text-(--text-muted)">{{ formatDate(d.createdAt)
                                    }}</td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button" class="action-trigger"
                                        :class="{ 'action-trigger-open': actionMenu.open && actionMenu.dept?.id === d.id }"
                                        title="Actions" @click.stop="openActionMenu(d, $event)">
                                        <i class="ti ti-dots-vertical" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :page="pagination.page" :limit="pagination.limit" :total="pagination.total"
                    :total-pages="pagination.totalPages"
                    @update:page="(p) => { pagination.page = p; loadDepartments() }"
                    @update:limit="(l) => { pagination.limit = l; pagination.page = 1; loadDepartments() }" />
            </section>

            <!-- Modal -->
            <div v-if="showModal"
                class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="glass-card rounded-2xl w-full max-w-md p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                    <header class="flex items-center justify-between mb-5">
                        <h3 class="text-base font-semibold text-(--text-heading)">
                            {{ editing ? 'Edit department' : 'New department' }}
                        </h3>
                        <button class="topbar-btn" @click="closeModal"><i class="ti ti-x" /></button>
                    </header>

                    <form class="space-y-4" @submit.prevent="saveDepartment">
                        <div>
                            <label class="form-label">Name</label>
                            <input v-model="form.name" type="text" required class="form-control"
                                placeholder="Engineering" />
                        </div>
                        <div>
                            <label class="form-label">Code</label>
                            <input v-model="form.code" type="text" required class="form-control font-mono uppercase"
                                placeholder="ENG" />
                        </div>

                        <div v-if="formError"
                            class="text-xs text-(--color-danger) bg-(--color-danger-subtle) px-3 py-2 rounded">
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

            <!-- Action dropdown -->
            <div v-if="actionMenu.open && actionMenu.dept"
                class="fixed z-50 glass-card rounded-lg shadow-(--shadow-lg) bg-(--bg-card) border border-(--border-color) py-1 min-w-[180px]"
                :style="{ top: actionMenu.y + 'px', left: actionMenu.x + 'px' }" @click.stop>
                <button class="action-item" @click="actionEdit">
                    <i class="ti ti-pencil" /> Edit
                </button>
                <hr class="my-1 border-(--border-color)" />
                <button class="action-item action-item-danger" @click="actionArchive">
                    <i class="ti ti-trash" /> Archive
                </button>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useApi } from '~/composables/useApi'
import { formatDate } from '~/composables/useDateFormat'
import { useToast } from '~/composables/useToast'

interface Department { id: string; name: string; code: string; createdAt: string | null }
interface Paginated<T> { data: T[]; pagination: { page: number; limit: number; total: number; totalPages: number } }

const api = useApi()
const toast = useToast()
const departments = ref<Department[]>([])
const loading = ref(false)
const search = ref('')

const pagination = reactive({ page: 1, limit: 15, total: 0, totalPages: 1 })

const showModal = ref(false)
const editing = ref<Department | null>(null)
const saving = ref(false)
const formError = ref<string | null>(null)
const form = reactive({ name: '', code: '' })

const actionMenu = reactive({
    open: false,
    x: 0,
    y: 0,
    dept: null as Department | null
})

const filtered = computed(() => {
    if (!search.value) return departments.value
    const q = search.value.toLowerCase()
    return departments.value.filter(d => d.name.toLowerCase().includes(q) || d.code.toLowerCase().includes(q))
})

const loadDepartments = async () => {
    loading.value = true
    try {
        const res = await api.get<Paginated<Department>>(`/departments?page=${pagination.page}&limit=${pagination.limit}`)
        departments.value = res.data
        pagination.total = res.pagination.total
        pagination.totalPages = res.pagination.totalPages
    } catch (err) {
        console.error('Failed to load departments', err)
        departments.value = []
    } finally {
        loading.value = false
    }
}

const resetForm = () => { form.name = ''; form.code = ''; formError.value = null }
const openCreateModal = () => { editing.value = null; resetForm(); showModal.value = true }
const openEditModal = (d: Department) => {
    editing.value = d
    form.name = d.name
    form.code = d.code
    formError.value = null
    showModal.value = true
}
const closeModal = () => { showModal.value = false; editing.value = null }

const saveDepartment = async () => {
    saving.value = true
    formError.value = null
    try {
        if (editing.value) {
            await api.put(`/departments/${editing.value.id}`, form)
        } else {
            await api.post('/departments', form)
        }
        showModal.value = false
        await loadDepartments()
    } catch (err: any) {
        formError.value = err.data?.message || 'Failed to save department.'
    } finally {
        saving.value = false
    }
}

const removeDepartment = async (d: Department) => {
    if (!confirm(`Archive department "${d.name}"? Employees assigned to it will become unassigned.`)) return
    try {
        await api.delete(`/departments/${d.id}`)
        await loadDepartments()
    } catch (err: any) {
        console.error('Failed to archive department', err)
        toast.error('Failed to archive department.', err?.data?.message)
    }
}

watch(() => pagination.limit, () => { pagination.page = 1 })

const openActionMenu = (d: Department, ev: MouseEvent) => {
    const rect = (ev.currentTarget as HTMLElement).getBoundingClientRect()
    const menuWidth = 180
    const menuMaxHeight = 120
    const left = Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8)
    const wouldOverflow = rect.bottom + menuMaxHeight + 8 > window.innerHeight
    actionMenu.dept = d
    actionMenu.x = Math.max(8, left)
    actionMenu.y = wouldOverflow ? rect.top - menuMaxHeight - 6 : rect.bottom + 6
    actionMenu.open = true
}

const closeActionMenu = () => { actionMenu.open = false; actionMenu.dept = null }

const actionEdit = () => {
    const d = actionMenu.dept
    closeActionMenu()
    if (d) openEditModal(d)
}

const actionArchive = async () => {
    const d = actionMenu.dept
    closeActionMenu()
    if (d) await removeDepartment(d)
}

onMounted(() => {
    if (import.meta.client) {
        document.addEventListener('click', closeActionMenu)
    }
    loadDepartments()
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
