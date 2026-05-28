<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Page header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">My Requests</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Track the status of your submitted forms.</p>
                </div>
                <div class="flex items-center gap-2">
                    <!-- View toggle -->
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
                </div>
            </header>

            <!-- Filters -->
            <section class="glass-card rounded-xl p-4">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="relative md:col-span-6">
                        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                        <input v-model="filters.search" type="search" placeholder="Search requests..."
                            class="form-control pl-9" />
                    </div>

                    <div
                        class="md:col-span-6 flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1">
                        <button v-for="s in (['', 'pending', 'approved', 'rejected', 'sent_back'] as const)" :key="s || 'all'"
                            class="flex-1 px-3 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
                            :class="filters.status === s ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                            @click="filters.status = s">
                            {{ s ? s.replace('_', ' ') : 'all' }}
                        </button>
                    </div>
                </div>
            </section>

            <!-- Loading -->
            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading requests...</span>
            </div>

            <!-- Empty -->
            <div v-else-if="requests.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-inbox text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No requests found</h4>
                <p class="text-xs text-(--text-muted) mt-1">You haven't submitted any forms matching these filters.</p>
            </div>

            <!-- Data table -->
            <section v-else-if="view === 'table'" class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                <th class="px-4 py-3 font-semibold font-mono">ID / Type</th>
                                <th class="px-4 py-3 font-semibold">Workflow</th>
                                <th class="px-4 py-3 font-semibold">Submitted</th>
                                <th class="px-4 py-3 font-semibold text-center">Status</th>
                                <th class="px-4 py-3 font-semibold text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-color)">
                            <tr v-for="req in requests" :key="req.id" class="hover:bg-(--bg-muted) transition-colors cursor-pointer" @click="viewDetails(req)">
                                <td class="px-4 py-3">
                                    <div class="font-mono font-medium text-primary text-xs">{{ req.id.split('-')[0].toUpperCase() }}</div>
                                    <div class="text-xxs text-(--text-muted) mt-1 truncate">{{ req.requestable_type.split('\\').pop() }}</div>
                                </td>
                                <td class="px-4 py-3 text-xs text-(--text-body)">
                                    {{ req.workflow?.name || 'Standard Approval' }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xxs text-(--text-muted)">
                                    {{ formatDate(req.created_at) }}
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <Badge :variant="statusVariant(req.status)" :dot="true">{{ req.status.replace('_', ' ') }}</Badge>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button class="btn btn-ghost btn-sm text-xs border border-(--border-color)" @click.stop="viewDetails(req)">
                                        View Details
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <Pagination :page="pagination.page" :limit="pagination.limit" :total="pagination.total"
                    :total-pages="pagination.totalPages" @update:page="(p) => { pagination.page = p; fetchRequests() }"
                    @update:limit="(l) => { pagination.limit = l; pagination.page = 1; fetchRequests() }" />
            </section>

            <!-- Grid view -->
            <section v-else>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
                    <article v-for="req in requests" :key="req.id"
                        class="glass-card rounded-2xl p-5 pb-4 flex flex-col gap-3 group hover:border-(--color-primary)/40 transition-all duration-150 relative overflow-hidden min-h-[160px] cursor-pointer"
                        @click="viewDetails(req)">
                        
                        <!-- Glowing shape behind card -->
                        <div class="absolute -right-8 -top-8 w-20 h-20 rounded-full bg-(--color-primary)/10 blur-xl pointer-events-none group-hover:scale-150 transition-transform duration-500" />

                        <div class="space-y-3 relative z-10 flex-1">
                            <header class="flex items-start justify-between gap-3 mb-2">
                                <div class="flex flex-col min-w-0">
                                    <span class="font-mono font-semibold text-(--color-primary) text-sm group-hover:text-primary transition-colors">{{ req.id.split('-')[0].toUpperCase() }}</span>
                                    <span class="text-xs text-(--text-muted) truncate mt-0.5">{{ req.requestable_type.split('\\').pop() }}</span>
                                </div>
                                <Badge :variant="statusVariant(req.status)" :dot="true" class="shrink-0">{{ req.status.replace('_', ' ') }}</Badge>
                            </header>
                        </div>

                        <div class="flex items-end justify-between mt-auto pt-3 border-t border-(--border-color)/50 relative z-10">
                            <!-- Left info: Workflow name -->
                            <div class="min-w-0 flex-1 mr-4">
                                <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Workflow</p>
                                <p class="text-xs font-semibold text-(--text-heading) truncate">{{ req.workflow?.name || 'Standard Approval' }}</p>
                            </div>
                            
                            <!-- Hover action replaces submitted date info -->
                            <div class="relative h-9 flex items-center justify-end shrink-0">
                                <div class="absolute right-0 flex items-center gap-1.5 transition-all duration-300 opacity-0 group-hover:opacity-100 translate-x-2 group-hover:translate-x-0">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-(--color-primary)">Open details</span>
                                    <div class="w-6 h-6 rounded-full bg-(--color-primary)/10 text-(--color-primary) flex items-center justify-center">
                                        <i class="ti ti-arrow-right text-xs"></i>
                                    </div>
                                </div>
                                <div class="text-right transition-all duration-300 opacity-100 group-hover:opacity-0 group-hover:translate-x-[-8px]">
                                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Submitted</p>
                                    <p class="text-xs text-(--text-body) font-mono">{{ formatDate(req.created_at) }}</p>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>

                <Pagination class="mt-6" :page="pagination.page" :limit="pagination.limit" :total="pagination.total"
                    :total-pages="pagination.totalPages" @update:page="(p) => { pagination.page = p; fetchRequests() }"
                    @update:limit="(l) => { pagination.limit = l; pagination.page = 1; fetchRequests() }" />
            </section>
        </div>

    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, onMounted, reactive, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useApprovals, type ApprovalRequest } from '~/composables/useApprovals'
import { useDateFormat } from '~/composables/useDateFormat'
import Badge from '~/components/Badge.vue'
import Pagination from '~/components/Pagination.vue'

const router = useRouter()
const { getRequests } = useApprovals()
const { formatDate } = useDateFormat()

type View = 'table' | 'grid'
const VIEW_KEY = 'approvals.my_requests.view'
const view = ref<View>('table')

const setView = (v: View) => {
    view.value = v
    if (import.meta.client) localStorage.setItem(VIEW_KEY, v)
}

const loading = ref(true)
const requests = ref<ApprovalRequest[]>([])

const pagination = reactive({ page: 1, limit: 15, total: 0, totalPages: 1 })
const filters = reactive({ search: '', status: '' as '' | 'pending' | 'approved' | 'rejected' | 'sent_back' })

const statusVariant = (s: string): 'success' | 'warning' | 'danger' | 'info' => {
    if (s === 'approved') return 'success'
    if (s === 'pending') return 'warning'
    if (s === 'rejected') return 'danger'
    return 'info'
}

const fetchRequests = async () => {
    loading.value = true
    try {
        const res = await getRequests(pagination.page, pagination.limit)
        requests.value = res.data || []
        
        // Use pagination if the backend implements it, else mock total pages
        if (res.pagination) {
            pagination.total = res.pagination.total
            pagination.totalPages = res.pagination.totalPages
        } else {
            pagination.total = requests.value.length
            pagination.totalPages = 1
        }
    } catch (e) {
        console.error(e)
    } finally {
        loading.value = false
    }
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
watch(() => [filters.search, filters.status], () => {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(() => {
        pagination.page = 1
        fetchRequests()
    }, 300)
})

const viewDetails = (req: ApprovalRequest) => {
    router.push(`/approvals/requests/${req.id}`)
}

onMounted(() => {
    if (import.meta.client) {
        const saved = localStorage.getItem(VIEW_KEY)
        if (saved === 'grid' || saved === 'table') view.value = saved
    }
    fetchRequests()
})
</script>
