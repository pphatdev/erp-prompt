<template>
    <NuxtLayout name="default">
        <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
            <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
            <span class="text-xs text-(--text-muted)">Loading project...</span>
        </div>
        <div v-else-if="!project" class="glass-card rounded-2xl py-20 text-center">
            <i class="ti ti-folder-off text-4xl text-(--text-muted)" />
            <h4 class="text-sm font-semibold text-(--text-heading) mt-3">Project not found</h4>
            <NuxtLink to="/projects" class="text-xs underline text-(--color-primary) mt-2 inline-block">Back to projects</NuxtLink>
        </div>
        <div v-else class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-semibold">{{ project.name }}</h1>
                        <span class="text-xxs px-1.5 py-0.5 rounded font-mono" :class="statusBadge(project.status)">{{ project.status }}</span>
                    </div>
                    <p class="text-xs text-(--text-muted) mt-1">
                        {{ formatDate(project.startDate) }}
                        <span class="mx-1">to</span>
                        {{ formatDate(project.endDate) }}
                        <span v-if="project.manager?.fullName" class="ml-2">/ {{ project.manager.fullName }}</span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <NuxtLink :to="`/projects/${project.id}/kanban`" class="btn btn-ghost text-xs">
                        <i class="ti ti-layout-board" />Kanban
                    </NuxtLink>
                    <button v-if="canWrite" type="button" class="btn btn-ghost text-xs" @click="openTaskModal()">
                        <i class="ti ti-plus" />Task
                    </button>
                    <button v-if="canWrite" type="button" class="btn btn-ghost text-xs" @click="openEditProjectModal">
                        <i class="ti ti-pencil" />Edit
                    </button>
                    <button v-if="canDelete" type="button" class="btn btn-ghost text-xs text-(--color-danger)" @click="confirmDeleteProject">
                        <i class="ti ti-trash" />Delete
                    </button>
                </div>
            </header>

            <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                <div class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Tasks</span>
                    <p class="text-lg font-bold text-(--text-heading) font-mono">{{ tasks.length }}</p>
                    <p class="text-xxs text-(--text-muted)">{{ doneCount }} done / {{ totalOpen }} open</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">In Progress</span>
                    <p class="text-lg font-bold font-mono text-(--color-info)">{{ statusCounts.in_progress }}</p>
                    <p class="text-xxs text-(--text-muted)">{{ statusCounts.todo }} todo / {{ statusCounts.review }} review</p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Budget</span>
                    <p class="text-lg font-bold text-(--text-heading) font-mono">{{ project.budget > 0 ? project.budget.toFixed(2) : '-' }}</p>
                    <p class="text-xxs text-(--text-muted)">
                        <span v-if="budgetStatus">
                            {{ Math.round(budgetStatus.percentage_used) }}% used
                        </span>
                        <span v-else>Click Budget tab</span>
                    </p>
                </div>
                <div class="glass-card rounded-2xl p-4 space-y-1">
                    <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Variance</span>
                    <p v-if="budgetStatus" class="text-lg font-bold font-mono"
                        :class="budgetStatus.variance >= 0 ? 'text-(--color-success)' : 'text-(--color-danger)'">
                        {{ budgetStatus.variance >= 0 ? '+' : '' }}{{ budgetStatus.variance.toFixed(2) }}
                    </p>
                    <p v-else class="text-lg font-bold font-mono text-(--text-muted)">-</p>
                    <p class="text-xxs text-(--text-muted)">budget - actual</p>
                </div>
            </section>

            <section class="flex items-center gap-2 flex-wrap">
                <button type="button" class="tab-btn" :class="{ active: activeTab === 'tasks' }" @click="setTab('tasks')">
                    <i class="ti ti-checkbox" /> Tasks
                </button>
                <button type="button" class="tab-btn" :class="{ active: activeTab === 'budget' }" @click="setTab('budget')">
                    <i class="ti ti-coin" /> Budget
                </button>
            </section>

            <!-- Tasks tab -->
            <section v-if="activeTab === 'tasks'" class="space-y-4">
                <div class="flex items-center gap-2 flex-wrap">
                    <button type="button" class="chip" :class="{ active: taskStatusFilter === '' }" @click="setTaskStatusFilter('')">All</button>
                    <button v-for="s in TASK_STATUSES" :key="s.value" type="button"
                        class="chip" :class="{ active: taskStatusFilter === s.value }" @click="setTaskStatusFilter(s.value)">
                        {{ s.label }}
                    </button>
                    <div class="ml-auto">
                        <input v-model.lazy="taskSearch" type="search" placeholder="Search tasks..."
                            class="form-control text-xs w-64" @keyup.enter="loadTasks" @change="loadTasks" />
                    </div>
                </div>

                <div v-if="tasksLoading" class="py-12 flex justify-center">
                    <span class="w-6 h-6 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                </div>
                <div v-else-if="tasks.length === 0" class="glass-card rounded-2xl py-12 text-center">
                    <i class="ti ti-checklist text-3xl text-(--text-muted)" />
                    <p class="text-xs text-(--text-muted) mt-2">No tasks yet. Add one to get started.</p>
                </div>
                <div v-else class="glass-card rounded-2xl overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-xs">
                            <thead class="bg-(--bg-muted)/40 text-(--text-muted)">
                                <tr>
                                    <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3">Title</th>
                                    <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3 w-32">Assignee</th>
                                    <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3 w-24">Priority</th>
                                    <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3 w-32">Status</th>
                                    <th class="text-left font-bold uppercase tracking-widest text-xxs px-3 py-3 w-28">Due</th>
                                    <th class="text-right font-bold uppercase tracking-widest text-xxs px-3 py-3 w-20">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="t in tasks" :key="t.id" class="border-t border-(--border-color) hover:bg-(--bg-muted)/40">
                                    <td class="px-3 py-2">
                                        <p class="text-(--text-heading) font-semibold truncate max-w-md">{{ t.title }}</p>
                                        <p v-if="t.description" class="text-xxs text-(--text-muted) line-clamp-1 max-w-md">{{ t.description }}</p>
                                    </td>
                                    <td class="px-3 py-2 truncate max-w-xs">{{ t.assignee?.fullName || '-' }}</td>
                                    <td class="px-3 py-2">
                                        <span class="text-xxs px-1.5 py-0.5 rounded font-mono" :class="priorityBadge(t.priority)">{{ t.priority }}</span>
                                    </td>
                                    <td class="px-3 py-2">
                                        <select :value="t.status" :disabled="!canWriteTasks"
                                            class="form-control form-control-sm text-xxs font-mono w-full"
                                            @change="(ev) => onStatusChangeFromEvent(t, ev)">
                                            <option v-for="s in TASK_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
                                        </select>
                                    </td>
                                    <td class="px-3 py-2 font-mono" :class="dueColor(t.dueDate)">{{ formatDate(t.dueDate) }}</td>
                                    <td class="px-3 py-2 text-right">
                                        <div class="flex items-center justify-end gap-1">
                                            <button v-if="canWriteTasks" type="button" class="action-btn" title="Edit" @click="openTaskModal(t)">
                                                <i class="ti ti-pencil text-xs" />
                                            </button>
                                            <button v-if="canDeleteTasks" type="button" class="action-btn action-btn-danger" title="Delete"
                                                @click="confirmDeleteTask(t)">
                                                <i class="ti ti-trash text-xs" />
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <Pagination v-if="taskPagination.total > 0" :page="taskPagination.page" :limit="taskPagination.limit"
                        :total="taskPagination.total" :total-pages="taskPagination.totalPages"
                        @update:page="(p) => { taskPagination.page = p; loadTasks() }"
                        @update:limit="(l) => { taskPagination.limit = l; taskPagination.page = 1; loadTasks() }" />
                </div>
            </section>

            <!-- Budget tab -->
            <section v-else-if="activeTab === 'budget'" class="space-y-4">
                <div v-if="budgetLoading" class="py-12 flex justify-center">
                    <span class="w-6 h-6 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                </div>
                <div v-else-if="!budgetStatus" class="glass-card rounded-2xl py-12 text-center">
                    <i class="ti ti-coin-off text-3xl text-(--text-muted)" />
                    <p class="text-xs text-(--text-muted) mt-2">No budget data yet.</p>
                </div>
                <div v-else class="space-y-4">
                    <section class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div class="glass-card rounded-2xl p-4 space-y-1">
                            <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Budget</span>
                            <p class="text-lg font-bold text-(--text-heading) font-mono">{{ budgetStatus.budget.toFixed(2) }}</p>
                        </div>
                        <div class="glass-card rounded-2xl p-4 space-y-1">
                            <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Actual Cost</span>
                            <p class="text-lg font-bold font-mono"
                                :class="budgetStatus.actual_cost > budgetStatus.budget ? 'text-(--color-danger)' : 'text-(--text-heading)'">
                                {{ budgetStatus.actual_cost.toFixed(2) }}
                            </p>
                            <p class="text-xxs text-(--text-muted)">flat 50/hr labour</p>
                        </div>
                        <div class="glass-card rounded-2xl p-4 space-y-1">
                            <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Variance</span>
                            <p class="text-lg font-bold font-mono"
                                :class="budgetStatus.variance >= 0 ? 'text-(--color-success)' : 'text-(--color-danger)'">
                                {{ budgetStatus.variance >= 0 ? '+' : '' }}{{ budgetStatus.variance.toFixed(2) }}
                            </p>
                        </div>
                        <div class="glass-card rounded-2xl p-4 space-y-1">
                            <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Used</span>
                            <p class="text-lg font-bold font-mono"
                                :class="usageColor(budgetStatus.percentage_used)">
                                {{ budgetStatus.percentage_used.toFixed(1) }}%
                            </p>
                        </div>
                    </section>

                    <div class="glass-card rounded-2xl p-4 space-y-2">
                        <div class="flex items-center justify-between text-xxs">
                            <span class="font-bold uppercase tracking-widest text-(--text-muted)">Burn</span>
                            <span class="font-mono">{{ budgetStatus.actual_cost.toFixed(2) }} / {{ budgetStatus.budget.toFixed(2) }}</span>
                        </div>
                        <div class="w-full bg-(--bg-muted) rounded-full h-2 overflow-hidden">
                            <div class="h-full rounded-full transition-all"
                                :style="{ width: Math.min(100, budgetStatus.percentage_used) + '%' }"
                                :class="usageBar(budgetStatus.percentage_used)" />
                        </div>
                        <p class="text-xxs text-(--text-muted)">
                            Actual cost is computed as total logged hours times a flat $50/hr placeholder. Phase 2 will replace with per-assignee rates.
                        </p>
                    </div>
                </div>
            </section>
        </div>

        <!-- Task modal -->
        <div v-if="showTaskModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-2xl bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">{{ editingTaskId ? 'Edit Task' : 'New Task' }}</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showTaskModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="postTask">
                    <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Title *</label>
                            <input v-model="taskForm.title" type="text" required maxlength="255" class="form-control text-xs" />
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Description</label>
                            <textarea v-model="taskForm.description" rows="3" class="form-control text-xs resize-none" />
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Status</label>
                                <select v-model="taskForm.status" class="form-control text-xs">
                                    <option v-for="s in TASK_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Priority</label>
                                <select v-model="taskForm.priority" class="form-control text-xs">
                                    <option v-for="p in TASK_PRIORITIES" :key="p.value" :value="p.value">{{ p.label }}</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Due Date</label>
                                <input v-model="taskForm.due_date" type="date" class="form-control text-xs font-mono" />
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Assignee</label>
                            <select v-model="taskForm.assignee_id" class="form-control text-xs" :disabled="employeesLoading">
                                <option :value="null">Unassigned</option>
                                <option v-for="e in employees" :key="e.id" :value="e.id">
                                    {{ e.fullName }}{{ e.employeeId ? ` (${e.employeeId})` : '' }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showTaskModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="!canSubmitTask || taskPosting">
                            <i v-if="taskPosting" class="ti ti-loader-2 animate-spin" />
                            {{ editingTaskId ? 'Save' : 'Add Task' }}
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Edit project modal -->
        <div v-if="showProjectModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-2xl bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Edit Project</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showProjectModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="postProject">
                    <div class="p-5 space-y-4 max-h-[75vh] overflow-y-auto">
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Name *</label>
                            <input v-model="projectForm.name" type="text" required maxlength="255" class="form-control text-xs" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Description</label>
                            <textarea v-model="projectForm.description" rows="3" class="form-control text-xs resize-none" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Start</label>
                                <input v-model="projectForm.start_date" type="date" class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">End</label>
                                <input v-model="projectForm.end_date" type="date" class="form-control text-xs font-mono" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Budget</label>
                                <input v-model.number="projectForm.budget" type="number" step="0.01" min="0"
                                    class="form-control text-xs font-mono text-right" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Status</label>
                                <select v-model="projectForm.status" class="form-control text-xs">
                                    <option v-for="s in PROJECT_STATUSES" :key="s.value" :value="s.value">{{ s.label }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Manager</label>
                            <select v-model="projectForm.manager_id" class="form-control text-xs" :disabled="employeesLoading">
                                <option :value="null">Unassigned</option>
                                <option v-for="e in employees" :key="e.id" :value="e.id">
                                    {{ e.fullName }}{{ e.employeeId ? ` (${e.employeeId})` : '' }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showProjectModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="!canSubmitProject || projectPosting">
                            <i v-if="projectPosting" class="ti ti-loader-2 animate-spin" />
                            Save
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Delete confirms -->
        <div v-if="deleteTaskTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Delete Task</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="deleteTaskTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <p class="text-xs text-(--text-muted)">
                        Delete task <span class="font-semibold text-(--text-heading)">{{ deleteTaskTarget.title }}</span>?
                        Its timesheets will be cascade-deleted.
                    </p>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="deleteTaskTarget = null">Cancel</button>
                    <button type="button" class="btn btn-danger text-xs" :disabled="taskDeleting" @click="onConfirmDeleteTask">
                        <i v-if="taskDeleting" class="ti ti-loader-2 animate-spin" />
                        Delete
                    </button>
                </footer>
            </div>
        </div>

        <div v-if="showDeleteProject" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Delete Project</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showDeleteProject = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5 space-y-3">
                    <div class="p-3 rounded-lg badge-soft-danger text-xs flex items-start gap-2">
                        <i class="ti ti-alert-triangle mt-0.5" />
                        <div>
                            <p class="font-semibold">Soft delete this project</p>
                            <p class="text-xxs mt-0.5">All tasks and timesheets cascade. Data stays on disk and can be restored.</p>
                        </div>
                    </div>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="showDeleteProject = false">Cancel</button>
                    <button type="button" class="btn btn-danger text-xs" :disabled="projectDeleting" @click="onConfirmDeleteProject">
                        <i v-if="projectDeleting" class="ti ti-loader-2 animate-spin" />
                        Delete
                    </button>
                </footer>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '~/composables/useApi'
import { useProjects, PROJECT_STATUSES, TASK_STATUSES, TASK_PRIORITIES } from '~/composables/useProjects'
import { useToast } from '~/composables/useToast'
import { useAuthStore } from '~/stores/auth'
import type {
    Project,
    ProjectStatus,
    ProjectBudgetStatus,
    Task,
    TaskStatus,
    TaskPriority,
    CreateTaskPayload,
    UpdateTaskPayload,
    CreateProjectPayload,
} from '~/types/projects'

definePageMeta({ breadcrumb: 'Project' })

interface EmployeeLite { id: string; employeeId: string | null; fullName: string }
interface Paginated<T> { data: T[]; pagination?: { page: number; limit: number; total: number; totalPages: number } }

const route = useRoute()
const router = useRouter()
const api = useApi()
const pm = useProjects()
const toast = useToast()
const authStore = useAuthStore()

const projectId = computed(() => route.params.id as string)

const canWrite        = computed(() => authStore.hasPermission('projects.project.write'))
const canDelete       = computed(() => authStore.hasPermission('projects.project.delete'))
const canWriteTasks   = computed(() => authStore.hasPermission('projects.task.write'))
const canDeleteTasks  = computed(() => authStore.hasPermission('projects.task.delete'))

const loading = ref(false)
const tasksLoading = ref(false)
const budgetLoading = ref(false)
const taskPosting = ref(false)
const taskDeleting = ref(false)
const projectPosting = ref(false)
const projectDeleting = ref(false)

const project = ref<Project | null>(null)
const tasks = ref<Task[]>([])
const taskPagination = reactive({ page: 1, limit: 15, total: 0, totalPages: 1 })
const taskStatusFilter = ref<'' | TaskStatus>('')
const taskSearch = ref('')

const budgetStatus = ref<ProjectBudgetStatus | null>(null)

type TabKey = 'tasks' | 'budget'
const VALID_TABS: TabKey[] = ['tasks', 'budget']

const initialTab: TabKey = (typeof route.query.tab === 'string' && (VALID_TABS as string[]).includes(route.query.tab))
    ? route.query.tab as TabKey
    : 'tasks'

const activeTab = ref<TabKey>(initialTab)

const setTab = (tab: TabKey) => {
    if (activeTab.value === tab) return
    activeTab.value = tab
    router.replace({ query: { ...route.query, tab } })
    if (tab === 'budget' && !budgetStatus.value) loadBudget()
}

const formatDate = (s: string | null) => {
    if (!s) return '-'
    const d = new Date(s)
    return isNaN(d.getTime()) ? s : d.toISOString().slice(0, 10)
}
const statusBadge = (s: ProjectStatus) =>
    PROJECT_STATUSES.find(x => x.value === s)?.badge ?? 'badge-soft-secondary'
const priorityBadge = (p: TaskPriority) =>
    TASK_PRIORITIES.find(x => x.value === p)?.badge ?? 'badge-soft-secondary'
const today = new Date().toISOString().slice(0, 10)
const dueColor = (due: string | null) => {
    if (!due) return 'text-(--text-muted)'
    const d = new Date(due).getTime()
    const t = new Date(today).getTime()
    const days = (d - t) / 86_400_000
    if (days < 0) return 'text-(--color-danger)'
    if (days < 3) return 'text-(--color-warning)'
    return 'text-(--text-body)'
}
const usageColor = (pct: number) => {
    if (pct > 100) return 'text-(--color-danger)'
    if (pct > 80) return 'text-(--color-warning)'
    return 'text-(--color-success)'
}
const usageBar = (pct: number) => {
    if (pct > 100) return 'bg-(--color-danger)'
    if (pct > 80) return 'bg-(--color-warning)'
    return 'bg-(--color-success)'
}

const statusCounts = computed(() => {
    const counts = { todo: 0, in_progress: 0, review: 0, done: 0 } as Record<TaskStatus, number>
    for (const t of tasks.value) counts[t.status] = (counts[t.status] ?? 0) + 1
    return counts
})
const doneCount = computed(() => statusCounts.value.done)
const totalOpen = computed(() => tasks.value.length - doneCount.value)

const loadProject = async () => {
    loading.value = true
    try {
        const res = await pm.projects.show(projectId.value)
        project.value = res.data
    } catch (err: any) {
        toast.error('Failed to load project', err?.data?.message)
        project.value = null
    } finally {
        loading.value = false
    }
}

const loadTasks = async () => {
    tasksLoading.value = true
    try {
        const res = await pm.tasks.list({
            project_id: projectId.value,
            page: taskPagination.page,
            limit: taskPagination.limit,
            status: taskStatusFilter.value || undefined,
            search: taskSearch.value.trim() || undefined,
        })
        tasks.value = res.data
        const p = (res as any).pagination
        if (p) {
            taskPagination.total = p.total ?? 0
            taskPagination.totalPages = p.totalPages ?? 1
            taskPagination.page = p.page ?? 1
            taskPagination.limit = p.limit ?? taskPagination.limit
        }
    } catch (err: any) {
        toast.error('Failed to load tasks', err?.data?.message)
    } finally {
        tasksLoading.value = false
    }
}

const loadBudget = async () => {
    budgetLoading.value = true
    try {
        const res = await pm.projects.budgetStatus(projectId.value)
        budgetStatus.value = res.data
    } catch (err: any) {
        toast.error('Failed to load budget status', err?.data?.message)
    } finally {
        budgetLoading.value = false
    }
}

const setTaskStatusFilter = (s: '' | TaskStatus) => {
    if (taskStatusFilter.value === s) return
    taskStatusFilter.value = s
    taskPagination.page = 1
    loadTasks()
}

const onStatusChange = async (t: Task, status: TaskStatus) => {
    const prev = t.status
    t.status = status
    try {
        await pm.tasks.updateStatus(t.id, status)
        toast.success('Status updated')
    } catch (err: any) {
        t.status = prev
        toast.error('Update failed', err?.data?.message)
    }
}

const onStatusChangeFromEvent = (t: Task, ev: Event) => {
    const select = ev.target as HTMLSelectElement
    onStatusChange(t, select.value as TaskStatus)
}

// ---- Employees picker (shared between task + edit-project modals) ----------

const employees = ref<EmployeeLite[]>([])
const employeesLoading = ref(false)
const ensureEmployeesLoaded = async () => {
    if (employees.value.length || employeesLoading.value) return
    employeesLoading.value = true
    try {
        const res = await api.get<Paginated<EmployeeLite>>('/employees?limit=200')
        employees.value = res.data
    } catch (err: any) {
        toast.error('Failed to load employees', err?.data?.message)
    } finally {
        employeesLoading.value = false
    }
}

// ---- Task modal ------------------------------------------------------------

const showTaskModal = ref(false)
const editingTaskId = ref<string | null>(null)

const blankTaskForm = (): CreateTaskPayload => ({
    project_id: projectId.value,
    title: '',
    description: null,
    due_date: null,
    status: 'todo',
    priority: 'medium',
    assignee_id: null,
})

const taskForm = reactive<CreateTaskPayload>(blankTaskForm())

const openTaskModal = (t?: Task) => {
    if (t) {
        editingTaskId.value = t.id
        Object.assign(taskForm, {
            project_id: projectId.value,
            title: t.title,
            description: t.description,
            due_date: t.dueDate,
            status: t.status,
            priority: t.priority,
            assignee_id: t.assigneeId,
        })
    } else {
        editingTaskId.value = null
        Object.assign(taskForm, blankTaskForm())
    }
    showTaskModal.value = true
    ensureEmployeesLoaded()
}

const canSubmitTask = computed(() => !!taskForm.title.trim())

const postTask = async () => {
    if (!canSubmitTask.value) return
    taskPosting.value = true
    try {
        const payload = {
            ...taskForm,
            title: taskForm.title.trim(),
            description: taskForm.description?.toString().trim() || null,
        }
        if (editingTaskId.value) {
            const updates: UpdateTaskPayload = { ...payload }
            await pm.tasks.update(editingTaskId.value, updates)
            toast.success('Task updated')
        } else {
            await pm.tasks.create(payload)
            toast.success('Task created')
        }
        showTaskModal.value = false
        await loadTasks()
        budgetStatus.value = null
    } catch (err: any) {
        toast.error('Save failed', err?.data?.message)
    } finally {
        taskPosting.value = false
    }
}

const deleteTaskTarget = ref<Task | null>(null)
const confirmDeleteTask = (t: Task) => { deleteTaskTarget.value = t }
const onConfirmDeleteTask = async () => {
    if (!deleteTaskTarget.value) return
    taskDeleting.value = true
    try {
        await pm.tasks.destroy(deleteTaskTarget.value.id)
        toast.success('Task deleted')
        deleteTaskTarget.value = null
        await loadTasks()
        budgetStatus.value = null
    } catch (err: any) {
        toast.error('Delete failed', err?.data?.message)
    } finally {
        taskDeleting.value = false
    }
}

// ---- Edit project ----------------------------------------------------------

const showProjectModal = ref(false)
const projectForm = reactive<CreateProjectPayload>({
    name: '',
    description: null,
    start_date: null,
    end_date: null,
    budget: 0,
    status: 'planning',
    manager_id: null,
})

const openEditProjectModal = () => {
    if (!project.value) return
    Object.assign(projectForm, {
        name: project.value.name,
        description: project.value.description,
        start_date: project.value.startDate,
        end_date: project.value.endDate,
        budget: project.value.budget,
        status: project.value.status,
        manager_id: project.value.managerId,
    })
    showProjectModal.value = true
    ensureEmployeesLoaded()
}

const canSubmitProject = computed(() => {
    if (!projectForm.name.trim()) return false
    if (projectForm.start_date && projectForm.end_date && projectForm.start_date > projectForm.end_date) return false
    return true
})

const postProject = async () => {
    if (!canSubmitProject.value || !project.value) return
    projectPosting.value = true
    try {
        const payload: CreateProjectPayload = {
            ...projectForm,
            name: projectForm.name.trim(),
            description: projectForm.description?.toString().trim() || null,
            budget: projectForm.budget ?? 0,
        }
        await pm.projects.update(project.value.id, payload)
        toast.success('Project updated')
        showProjectModal.value = false
        await loadProject()
    } catch (err: any) {
        toast.error('Save failed', err?.data?.message)
    } finally {
        projectPosting.value = false
    }
}

const showDeleteProject = ref(false)
const confirmDeleteProject = () => { showDeleteProject.value = true }
const onConfirmDeleteProject = async () => {
    if (!project.value) return
    projectDeleting.value = true
    try {
        await pm.projects.destroy(project.value.id)
        toast.success('Project deleted')
        router.push('/projects')
    } catch (err: any) {
        toast.error('Delete failed', err?.data?.message)
    } finally {
        projectDeleting.value = false
    }
}

onMounted(async () => {
    await loadProject()
    if (!project.value) return
    await loadTasks()
    if (activeTab.value === 'budget') await loadBudget()
})
</script>

<style scoped>
.action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    color: var(--text-body);
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.action-btn:hover {
    background: var(--bg-muted);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}

.action-btn-danger:hover {
    color: var(--color-danger);
    border-color: rgb(var(--color-danger-rgb) / 0.4);
}

.chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    font-size: 11px;
    color: var(--text-body);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.chip:hover { background: var(--bg-muted); }
.chip.active {
    background: rgb(var(--color-primary-rgb) / 0.12);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}

.tab-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 8px 16px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    font-size: 12px;
    font-weight: 600;
    color: var(--text-body);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
.tab-btn:hover { background: var(--bg-muted); }
.tab-btn.active {
    background: rgb(var(--color-primary-rgb) / 0.12);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}
</style>
