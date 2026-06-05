<template>
    <NuxtLayout name="default">
        <div class="space-y-8">
            <!-- Hero banner card (Dashboard-style layout) -->
            <section class="relative overflow-hidden rounded-2xl border border-(--border-color) bg-(--bg-card) p-6 sm:p-8 shadow-(--shadow-sm)">
                <!-- Soft glowing shapes -->
                <div class="absolute -top-20 -right-16 w-72 h-72 rounded-full blur-3xl bg-(--color-primary)/10 pointer-events-none" />
                <div class="absolute -bottom-20 -left-20 w-72 h-72 rounded-full blur-3xl bg-(--color-info)/10 pointer-events-none" />

                <div class="relative z-10 flex flex-col lg:flex-row justify-between items-start lg:items-center gap-6">
                    <div class="space-y-2 max-w-2xl">
                        <Badge variant="primary" :dot="true">eApprovals Portal</Badge>
                        <h1 class="text-2xl font-bold tracking-tight text-(--text-heading)">
                            Workflow Submission Center
                        </h1>
                        <p class="text-xs text-(--text-body) leading-relaxed">
                            Initiate leave requests, expense claims, overtime logs, or purchase requisitions. 
                            Every submission is automatically routed through your tenant's active approval policies.
                        </p>
                    </div>

                    <!-- Search Box -->
                    <div class="w-full lg:w-80 relative shrink-0">
                        <i class="ti ti-search absolute left-3.5 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                        <input v-model="searchQuery" type="text" placeholder="Search workflow forms..." 
                            class="form-control pl-10 pr-10 py-2.5 rounded-xl bg-(--bg-muted)/40 border-(--border-color) focus:bg-(--bg-card) focus:outline-2 focus:outline-(--color-primary) focus:outline-offset-0 text-xs" />
                        <button v-if="searchQuery" @click="searchQuery = ''" class="absolute right-3 top-1/2 -translate-y-1/2 text-(--text-muted) hover:text-(--text-heading)">
                            <i class="ti ti-x text-xs"></i>
                        </button>
                    </div>
                </div>
            </section>

            <!-- Categories Toolbar & Main Grid -->
            <div class="space-y-6">
                <!-- Filters Tab list -->
                <div class="flex items-center justify-between  pb-4 flex-wrap gap-4">
                    <section class="flex items-center gap-2 flex-wrap max-sm:justify-center">
                        <button v-for="cat in categories" :key="cat.id" type="button" 
                            class="chip"
                            :class="{ active: activeCategory === cat.id }"
                            @click="activeCategory = cat.id">
                            <i v-if="cat.icon" :class="['ti', cat.icon]"></i>
                            {{ cat.name }}
                        </button>
                    </section>

                    <span class="text-xxs font-mono text-(--text-muted)">
                        Showing {{ filteredFormTypes.length }} of {{ formTypes.length }} Forms
                    </span>
                </div>

                <!-- Forms Grid -->
                <TransitionGroup 
                    name="list" 
                    tag="div" 
                    v-if="filteredFormTypes.length > 0" 
                    class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5"
                >
                    <div v-for="form in filteredFormTypes" :key="form.id" 
                        class="glass-card rounded-2xl p-5 pb-3 transition-all duration-150 cursor-pointer group border border-(--border-color) relative overflow-hidden flex flex-col justify-between min-h-[160px]"
                        :class="themeColorMap[form.color]?.border || 'hover:border-(--border-color)/50'"
                        @click="openForm(form.route)">
                        
                        <!-- Glowing shape behind card -->
                        <div class="absolute -right-8 -top-8 w-20 h-20 rounded-full bg-(--color-primary)/10 blur-xl pointer-events-none group-hover:scale-150 transition-transform duration-500" />
                        
                        <div class="space-y-3">
                            <div class="w-12 h-12 bg-(--color-primary)/10 text-(--color-primary) rounded-xl flex items-center justify-center transition-all duration-300"
                                :class="[themeColorMap[form.color]?.bg || 'bg-(--color-primary)/10', themeColorMap[form.color]?.text || 'text-(--color-primary)', 'group-hover:scale-110']">
                                <i :class="['ti', form.icon, 'text-2xl']"></i>
                            </div>
                            
                            <div>
                                <h3 class="font-bold text-base text-(--text-heading) group-hover:text-primary transition-colors">{{ form.title }}</h3>
                                <p class="text-xs text-(--text-muted) mt-1.5 leading-relaxed font-sans">{{ form.description }}</p>
                            </div>
                        </div>

                        <!-- Card Action Affordance (Arrow slides in) -->
                        <div class="flex justify-between items-center mt-4 pt-3 border-t border-(--border-color)/50">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-primary opacity-0 group-hover:opacity-100 transition-opacity duration-300">Launch Workflow</span>
                            <div class="w-6 h-6 rounded-full bg-primary/10 text-primary flex items-center justify-center transform translate-x-2 opacity-0 group-hover:translate-x-0 group-hover:opacity-100 transition-all duration-300">
                                <i class="ti ti-arrow-right text-xs"></i>
                            </div>
                        </div>
                    </div>
                </TransitionGroup>

                <!-- Empty State for Search -->
                <div v-else class="glass-card rounded-2xl py-16 text-center max-w-lg mx-auto">
                    <i class="ti ti-search-off text-4xl text-(--text-muted) opacity-60" />
                    <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No matching templates</h4>
                    <p class="text-xs text-(--text-muted) mt-1">Try refining your search keyword or check other categories.</p>
                </div>
            </div>

            <!-- Recent Submissions Widget -->
            <div class="glass-card rounded-3xl border border-(--border-color) p-6 space-y-5">
                <div class="flex items-center justify-between border-b border-(--border-color)/80 pb-4">
                    <div class="space-y-1">
                        <h2 class="text-base font-bold text-(--text-heading) flex items-center gap-2">
                            <i class="ti ti-history text-primary" />
                            Recent Submissions
                        </h2>
                        <p class="text-xxs text-(--text-muted)">Your latest workflow requests and their real-time state.</p>
                    </div>
                    <NuxtLink to="/approvals/requests" class="btn btn-ghost text-xxs font-bold uppercase tracking-wider text-primary hover:bg-primary/5 px-3 py-1.5 rounded-lg flex items-center gap-1">
                        View All Requests
                        <i class="ti ti-arrow-right text-xs" />
                    </NuxtLink>
                </div>

                <!-- Loading state -->
                <div v-if="recentLoading" class="py-12 flex flex-col items-center justify-center gap-3">
                    <span class="w-6 h-6 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                    <span class="text-xxs text-(--text-muted)">Fetching latest history...</span>
                </div>

                <!-- Data list -->
                <div v-else-if="recentRequests.length > 0" class="divide-y divide-(--border-color)/50">
                    <div v-for="req in recentRequests" :key="req.id" 
                        class="py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-(--bg-muted)/30 px-3 -mx-3 rounded-xl transition-colors cursor-pointer"
                        @click="viewRequestDetails(req.id)"
                    >
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="w-9 h-9 rounded-xl bg-primary/10 text-primary flex items-center justify-center shrink-0">
                                <i class="ti ti-file-text text-sm"></i>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-semibold text-xs text-(--text-heading)">
                                        {{ getRequestLabel(req.requestable_type) }}
                                    </span>
                                    <span class="font-mono text-xxs text-(--text-muted) bg-(--bg-muted) px-1.5 py-0.5 rounded">
                                        #{{ req.id.split('-')[0].toUpperCase() }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-2 mt-1 text-xxs text-(--text-muted)">
                                    <span>Workflow: {{ req.workflow?.name || 'Standard Approval' }}</span>
                                    <span>·</span>
                                    <span class="font-mono">{{ formatDateTime(req.created_at) }}</span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 justify-between sm:justify-end shrink-0">
                            <Badge :variant="statusVariant(req.status)" :dot="true">{{ req.status.replace('_', ' ') }}</Badge>
                            <i class="ti ti-chevron-right text-(--text-muted) text-xs" />
                        </div>
                    </div>
                </div>

                <!-- Empty State -->
                <div v-else class="text-center py-10">
                    <i class="ti ti-inbox text-3xl text-(--text-muted) opacity-50" />
                    <p class="text-xs text-(--text-muted) mt-2">You haven't submitted any workflow requests yet.</p>
                </div>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useApprovals, type ApprovalRequest } from '~/composables/useApprovals'
import { useDateFormat } from '~/composables/useDateFormat'
import Badge from '~/components/Badge.vue'

const router = useRouter()
const { getRequests } = useApprovals()
const { formatDateTime } = useDateFormat()

const searchQuery = ref('')
const activeCategory = ref('all')

const themeColorMap: Record<string, { bg: string, text: string, border: string }> = {
    primary: {
        bg: 'bg-primary/10',
        text: 'text-primary',
        border: 'hover:border-primary/40'
    },
    warning: {
        bg: 'bg-warning/10',
        text: 'text-warning',
        border: 'hover:border-warning/40'
    },
    success: {
        bg: 'bg-success/10',
        text: 'text-success',
        border: 'hover:border-success/40'
    },
    info: {
        bg: 'bg-info/10',
        text: 'text-info',
        border: 'hover:border-info/40'
    },
    danger: {
        bg: 'bg-danger/10',
        text: 'text-danger',
        border: 'hover:border-danger/40'
    },
    secondary: {
        bg: 'bg-slate-500/10',
        text: 'text-slate-500',
        border: 'hover:border-slate-500/40'
    }
}

const categories = [
    { id: 'all', name: 'All Workflows', icon: 'ti-apps' },
    { id: 'hrm', name: 'Human Resources', icon: 'ti-users' },
    { id: 'finance', name: 'Finance', icon: 'ti-coin' },
    { id: 'procurement', name: 'Procurement & SCM', icon: 'ti-shopping-cart' }
]

const formTypes = [
    { id: 'leave', title: 'Leave Request', description: 'Request annual, sick, or unpaid leave.', icon: 'ti-calendar-event', color: 'primary', route: '/approvals/forms/leave', category: 'hrm' },
    { id: 'overtime', title: 'Overtime', description: 'Log extra hours for manager approval.', icon: 'ti-clock', color: 'warning', route: '/approvals/forms/overtime', category: 'hrm' },
    { id: 'expense', title: 'Expense Claim', description: 'Submit receipts for reimbursement.', icon: 'ti-receipt', color: 'success', route: '/finance/payments', category: 'finance' },
    { id: 'purchase', title: 'Purchase Requisition', description: 'Request to buy items or services.', icon: 'ti-shopping-cart', color: 'info', route: '/inventory/purchase-orders', category: 'procurement' },
    { id: 'petty_cash', title: 'Petty Cash', description: 'Small cash advances for operations.', icon: 'ti-cash', color: 'danger', route: '/finance/petty-cash/new', category: 'finance' },
    { id: 'appraisal', title: 'Self Appraisal', description: 'Submit performance review goals.', icon: 'ti-clipboard-list', color: 'secondary', route: '/approvals/forms/appraisal', category: 'hrm' },
    { id: 'employee_appointment', title: 'Employee Appointment', description: 'Request to convert a hired candidate into an employee.', icon: 'ti-user-plus', color: 'info', route: '/approvals/forms/employee-appointment', category: 'hrm' },
]

const filteredFormTypes = computed(() => {
    return formTypes.filter(form => {
        const matchesCategory = activeCategory.value === 'all' || form.category === activeCategory.value
        const matchesSearch = form.title.toLowerCase().includes(searchQuery.value.toLowerCase()) ||
                              form.description.toLowerCase().includes(searchQuery.value.toLowerCase())
        return matchesCategory && matchesSearch
    })
})

const recentRequests = ref<ApprovalRequest[]>([])
const recentLoading = ref(true)

const fetchRecentSubmissions = async () => {
    recentLoading.value = true
    try {
        const res = await getRequests(1, 5) // Fetch latest 5 requests
        recentRequests.value = res.data || []
    } catch (e) {
        console.error(e)
    } finally {
        recentLoading.value = false
    }
}

const getRequestLabel = (type: string) => {
    if (!type) return 'Approval Request'
    const parts = type.split('\\')
    return parts[parts.length - 1].replace(/([A-Z])/g, ' $1').trim()
}

const statusVariant = (s: string): 'success' | 'warning' | 'danger' | 'info' => {
    if (s === 'approved') return 'success'
    if (s === 'pending') return 'warning'
    if (s === 'rejected') return 'danger'
    return 'info'
}

const openForm = (routePath: string) => {
    router.push(routePath)
}

const viewRequestDetails = (id: string) => {
    router.push(`/approvals/requests/${id}`)
}

onMounted(() => {
    fetchRecentSubmissions()
})
</script>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.list-enter-active,
.list-leave-active {
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
}
.list-enter-from,
.list-leave-to {
    opacity: 0;
    transform: translateY(10px) scale(0.95);
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

.chip:hover {
    background: var(--bg-muted);
}

.chip.active {
    background: rgb(var(--color-primary-rgb) / 0.12);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}
</style>
