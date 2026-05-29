<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Toolbar -->
            <header class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-(--text-heading)">Sales Pipeline</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        <span>Drag a card across columns to advance deal progression.</span>
                    </p>
                </div>

                <div class="flex items-center gap-3">
                    <div class="inline-flex items-center bg-(--bg-card) border border-(--border-color) rounded-lg p-1">
                        <button type="button"
                            class="px-3 py-1.5 rounded-md text-xs font-semibold inline-flex items-center gap-1.5 bg-(--color-primary-subtle) text-(--color-primary)">
                            <i class="ti ti-layout-kanban" /> Board
                        </button>
                        <NuxtLink to="/crm/leads"
                            class="px-3 py-1.5 rounded-md text-xs font-semibold inline-flex items-center gap-1.5 text-(--text-muted) hover:text-(--text-heading) transition-colors">
                            <i class="ti ti-list" /> Leads
                        </NuxtLink>
                    </div>

                    <button type="button" class="btn btn-primary text-xs" @click="openCreateModal">
                        <i class="ti ti-plus" /> New Opportunity
                    </button>
                </div>
            </header>

            <!-- Metrics row -->
            <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Total Pipeline Value</p>
                    <p class="text-xl font-semibold text-(--text-heading) mt-1">
                        <CountUp :value="totalValue" currency="USD" />
                    </p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Weighted Forecast</p>
                    <p class="text-xl font-semibold text-(--color-primary) mt-1">
                        <CountUp :value="weightedForecast" currency="USD" />
                    </p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Active Deals</p>
                    <p class="text-xl font-semibold text-(--text-heading) mt-1">
                        <CountUp :value="activeDealsCount" />
                    </p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Deals Won</p>
                    <p class="text-xl font-semibold text-(--color-success) mt-1">
                        <CountUp :value="wonCount" />
                    </p>
                </div>
            </section>

            <!-- Filters -->
            <section class="glass-card rounded-xl p-3">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="relative md:col-span-8">
                        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                        <input v-model="search" type="search" placeholder="Search opportunities by title..."
                            class="form-control pl-9">
                    </div>
                    <div class="md:col-span-4">
                        <select v-model="filterCustomer" class="form-control">
                            <option value="">All customer accounts</option>
                            <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                        </select>
                    </div>
                </div>
            </section>

            <!-- Loading -->
            <div v-if="loading" class="py-24 flex flex-col items-center justify-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted) font-medium">Loading pipeline...</span>
            </div>

            <!-- Board -->
            <section v-else class="kanban-scroller -mx-2 px-2 pb-4 overflow-x-auto">
                <div class="flex gap-4 min-w-max">
                    <div v-for="col in columns" :key="col.stage" class="kanban-column min-h-[calc(100vh-28.5rem)]! flex flex-col gap-3"
                        :class="{ 'kanban-column--dragover': draggedOverStage === col.stage }"
                        @dragover.prevent="onColumnDragOver(col.stage, $event)"
                        @dragleave="onColumnDragLeave(col.stage)" @drop="onColumnDrop(col.stage)">
                        
                        <header class="flex items-center justify-between px-1">
                            <div class="flex items-center gap-2">
                                <span class="text-xxs font-bold uppercase tracking-wider px-2.5 py-1 rounded-full border"
                                    :class="columnHeaderClass(col.stage)">
                                    {{ col.label }}
                                    <span class="opacity-70">({{ col.deals.length }})</span>
                                </span>
                            </div>
                            <span v-if="movingId && col.stage === pendingDropStage"
                                class="text-xxs text-(--text-muted) inline-flex items-center gap-1">
                                <span class="w-3 h-3 rounded-full border-2 border-(--color-primary)/30 border-t-(--color-primary) animate-spin" />
                                Moving...
                            </span>
                            <span v-else class="font-mono text-xxs text-(--text-muted)">{{ formatCurrency(col.sum) }}</span>
                        </header>

                        <div class="kanban-list flex flex-col gap-3 pr-1">
                            <article v-for="d in col.deals" :key="d.id"
                                class="kanban-card glass-card rounded-xl p-3 shadow-sm transition-all cursor-grab"
                                :class="{
                                    'kanban-card--dragging': draggingId === d.id,
                                    'ring-1 ring-(--color-success)/40': col.stage === 'won',
                                    'ring-1 ring-(--color-danger)/40': col.stage === 'lost'
                                }" draggable="true" @dragstart="onCardDragStart(d, $event)" @dragend="onCardDragEnd">
                                
                                <header class="flex items-start justify-between gap-2 mb-2">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <div class="w-8 h-8 rounded-full flex items-center justify-center text-xxs font-bold text-(--color-primary) bg-(--color-primary-subtle) border border-(--color-primary)/20 shrink-0"
                                            :title="d.customer?.name || 'Customer'">
                                            {{ initials(d.customer?.name || d.title) }}
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-xs font-semibold text-(--text-heading) truncate">{{ d.title }}</p>
                                            <p class="text-xxs text-(--text-muted) truncate">
                                                <i class="ti ti-building" /> {{ d.customer?.name || 'No account' }}
                                            </p>
                                        </div>
                                    </div>
                                    <Badge :variant="col.stage === 'won' ? 'success' : col.stage === 'lost' ? 'danger' : 'secondary'">
                                        {{ d.probability }}%
                                    </Badge>
                                </header>

                                <!-- Star probability rating -->
                                <div class="flex items-center gap-0.5 mb-2">
                                    <i v-for="n in 5" :key="n" class="ti text-[12px]"
                                        :class="n <= probabilityRating(d.probability) ? 'ti-star-filled text-(--color-warning)' : 'ti-star text-(--border-strong)'" />
                                </div>

                                <div class="flex justify-between items-center text-xxs text-(--text-muted) mt-1 border-t border-(--border-color) pt-2 mb-2">
                                    <div>
                                        <p class="text-[9px] uppercase tracking-wider font-bold">Est. Value</p>
                                        <p class="font-semibold text-(--text-heading) mt-0.5">{{ formatCurrency(d.estimatedValue) }}</p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-[9px] uppercase tracking-wider font-bold">Weighted</p>
                                        <p class="font-semibold text-(--color-primary) mt-0.5">{{ formatCurrency(weightedValue(d)) }}</p>
                                    </div>
                                </div>

                                <footer class="flex items-center justify-between text-xxs text-(--text-muted)">
                                    <span class="inline-flex items-center gap-1">
                                        <i class="ti ti-calendar" /> {{ d.projectedCloseDate ? formatDate(d.projectedCloseDate) : 'No date' }}
                                    </span>
                                    <div class="flex gap-1" @click.stop @mousedown.stop @dragstart.stop.prevent>
                                        <button type="button" class="mini-action-btn" title="B2B Product Schedule" @click="openSchedule(d)">
                                            <i class="ti ti-package text-[10px]" />
                                        </button>
                                        <button type="button" class="mini-action-btn" title="Edit" @click="openEditModal(d)">
                                            <i class="ti ti-pencil text-[10px]" />
                                        </button>
                                        <button type="button" class="mini-action-btn hover:text-(--color-danger)" title="Archive" @click="confirmDelete(d)">
                                            <i class="ti ti-trash text-[10px]" />
                                        </button>
                                    </div>
                                </footer>
                            </article>

                            <!-- Column Empty state -->
                            <template v-if="!col.deals.length">
                                <button v-if="col.stage === 'new'" type="button"
                                    class="kanban-cta glass-card rounded-xl p-4 w-full text-left transition-all"
                                    @click="openCreateModal">
                                    <span class="kanban-cta-icon">
                                        <i class="ti ti-plus text-base" />
                                    </span>
                                    <span class="block text-xs font-semibold text-(--text-heading) mt-3">
                                        New Opportunity
                                    </span>
                                    <span class="block text-xxs text-(--text-muted) mt-1">
                                        Submit a deal to start the pipeline.
                                    </span>
                                </button>
                                <div v-else
                                    class="kanban-empty rounded-xl border border-dashed border-(--border-color) text-(--text-muted) text-xxs py-6 text-center">
                                    Drop here to move to <span class="font-semibold">{{ col.label }}</span>
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Opportunity Form Modal (Create / Edit) -->
        <div v-if="showFormModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">{{ isEdit ? 'Edit Opportunity' : 'New Sales Opportunity' }}</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="saveOpportunity">
                    <div class="p-5 space-y-4">
                        <!-- Lead picker (create only) — "Opportunities get value from a New Lead".
                             Selecting one pre-fills title and estimated value from the lead. -->
                        <div v-if="!isEdit" class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">
                                From Lead <span class="text-(--text-muted) normal-case font-normal">— optional, pre-fills value</span>
                            </label>
                            <select v-model="form.leadId" class="form-control text-xs" @change="hydrateFromLead">
                                <option :value="null">— Standalone opportunity (no lead) —</option>
                                <option v-for="l in selectableLeads" :key="l.id" :value="l.id">
                                    {{ l.title }}<template v-if="l.estimatedValue"> · {{ formatCurrency(l.estimatedValue) }}</template>
                                </option>
                            </select>
                        </div>
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Opportunity Title</label>
                            <input v-model="form.title" type="text" placeholder="e.g. Acme Corp - Enterprise Licensing" required class="form-control text-xs" />
                        </div>
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">
                                Customer / Corporate Account <span class="text-(--text-muted) normal-case font-normal">— optional, created on Quotation Won</span>
                            </label>
                            <select v-model="form.customerId" class="form-control text-xs" :disabled="isEdit">
                                <option value="">— No account yet (prospect) —</option>
                                <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Est. Value (USD)</label>
                                <input v-model.number="form.estimatedValue" type="number" required placeholder="e.g. 15000" class="form-control text-xs" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Projected Close Date</label>
                                <input v-model="form.projectedCloseDate" type="date" class="form-control text-xs" />
                            </div>
                        </div>
                        <div :class="isEdit ? 'grid grid-cols-2 gap-3' : ''">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Probability (%)</label>
                                <input v-model.number="form.probability" type="number" min="0" max="100" class="form-control text-xs" />
                            </div>
                            <!-- Stage is omitted on create: new opportunities always land in the
                                 "Opportunities" column (STAGE_NEW). Reps move them by dragging. -->
                            <div v-if="isEdit" class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Stage</label>
                                <select v-model="form.stage" class="form-control text-xs">
                                    <option value="new">Opportunities</option>
                                    <option value="schedules">Schedules (B2B/B2C)</option>
                                    <option value="contacted">Contacted</option>
                                    <option value="won">Won (Qualified)</option>
                                    <option value="lost">Lost (Not Qualified)</option>
                                </select>
                            </div>
                        </div>
                        <p v-if="!isEdit" class="text-xxs text-(--text-muted) -mt-1">
                            <i class="ti ti-info-circle" />
                            New opportunities land in the <strong>Opportunities</strong> column. Drag the card across
                            the board to move it through the pipeline.
                        </p>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showFormModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="submitting">
                            <i v-if="submitting" class="ti ti-loader-2 animate-spin" />
                            {{ isEdit ? 'Save Changes' : 'Create Deal' }}
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Loss Reason Enforcement Modal -->
        <div v-if="showLossModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Opportunity Lost Feedback</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="cancelLossTransition">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="commitLossTransition">
                    <div class="p-5 space-y-3">
                        <p class="text-xs text-(--text-body)">Please capture the primary reason why <span class="font-semibold text-(--text-heading)">{{ lossTarget?.title }}</span> was lost:</p>
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase">Reason for Loss</label>
                            <textarea v-model="lossReason" required rows="3" placeholder="e.g. Budget constraints, Competitor pricing, Feature gap..." class="form-control text-xs resize-none"></textarea>
                        </div>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="cancelLossTransition">Cancel Drop</button>
                        <button type="submit" class="btn btn-danger text-xs text-white" :disabled="transitioning">
                            <i v-if="transitioning" class="ti ti-loader-2 animate-spin" />
                            Log Lost Deal
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- B2B Product Schedule editor -->
        <ProductScheduleEditor v-if="scheduleTarget" :opportunity="scheduleTarget" @close="scheduleTarget = null" />

        <!-- Delete Modal -->
        <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3>Archive Opportunity</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="deleteTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5">
                    <p class="text-xs text-(--text-body)">Archive opportunity <span class="font-semibold text-(--text-heading)">{{ deleteTarget.title }}</span>?</p>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="deleteTarget = null">Cancel</button>
                    <button type="button" class="btn btn-danger text-xs" :disabled="deleting" @click="onConfirmDelete">
                        <i v-if="deleting" class="ti ti-loader-2 animate-spin" />
                        Archive
                    </button>
                </footer>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, reactive, watch } from 'vue'
import { useCrm } from '~/composables/useCrm'
import { useSales } from '~/composables/useSales'
import { useToast } from '~/composables/useToast'
import type { Lead, Opportunity, CreateOpportunityPayload, OpportunityStage } from '~/types/crm'
import type { CustomerLite } from '~/types/sales'
import ProductScheduleEditor from '~/components/crm/ProductScheduleEditor.vue'
import CountUp from '~/components/CountUp.vue'

const crm = useCrm()
const sales = useSales()
const toast = useToast()

const loading = ref(false)
const submitting = ref(false)
const transitioning = ref(false)
const deleting = ref(false)

const opportunitiesList = ref<Opportunity[]>([])
const customers = ref<CustomerLite[]>([])
const leads = ref<Lead[]>([])

// Leads still in the funnel (new / contacted / qualified) — those already
// converted (lead has an Opportunity) are filtered out so the picker stays
// focused on leads that genuinely need to be promoted.
const convertedLeadIds = computed(() => new Set(
    opportunitiesList.value.map(o => o.leadId).filter((id): id is string => !!id)
))
const selectableLeads = computed(() =>
    leads.value
        .filter(l => l.status !== 'unqualified' && !convertedLeadIds.value.has(l.id))
        .slice(0, 100)
)

const search = ref('')
const filterCustomer = ref('')

const draggingId = ref<string | null>(null)
const draggingFrom = ref<OpportunityStage | null>(null)

const scheduleTarget = ref<Opportunity | null>(null)

const openSchedule = (opp: Opportunity) => {
    scheduleTarget.value = opp
}
const draggedOverStage = ref<OpportunityStage | null>(null)
const movingId = ref<string | null>(null)
const pendingDropStage = ref<OpportunityStage | null>(null)

// Kanban Column Configuration — matches the CRM module spec:
//   Opportunities · Schedules (B2B/B2C) · Contacted · Won (Qualified) · Lost (Not Qualified)
// The backend Opportunity::STAGES also accepts legacy values (qualified /
// proposal / negotiation); visualStage() collapses those into "Contacted" so
// nothing disappears off the board.
const stagesList: { stage: OpportunityStage; label: string }[] = [
    { stage: 'new',       label: 'Opportunities' },
    { stage: 'schedules', label: 'Schedules (B2B/B2C)' },
    { stage: 'contacted', label: 'Contacted' },
    { stage: 'won',       label: 'Won (Qualified)' },
    { stage: 'lost',      label: 'Lost (Not Qualified)' },
]

const visualStage = (raw: OpportunityStage): OpportunityStage => {
    // Roll intermediate/legacy stages forward into "contacted" so they still
    // surface on the trimmed board.
    if (raw === 'qualified' || raw === 'proposal' || raw === 'negotiation') return 'contacted'
    return raw
}

const num = (v: unknown): number => {
    const n = typeof v === 'number' ? v : Number(v)
    return Number.isFinite(n) ? n : 0
}

const columns = computed(() => {
    return stagesList.map(st => {
        const deals = filteredDeals.value.filter(o => visualStage(o.stage) === st.stage)
        const sum = deals.reduce((acc, curr) => acc + num(curr.estimatedValue), 0)
        return {
            ...st,
            deals,
            sum
        }
    })
})

const totalValue = computed(() => opportunitiesList.value.reduce((acc, curr) => acc + num(curr.estimatedValue), 0))
const weightedForecast = computed(() => opportunitiesList.value.reduce((acc, curr) => acc + num(curr.estimatedValue) * num(curr.probability) / 100, 0))
const activeDealsCount = computed(() => opportunitiesList.value.filter(o => o.stage !== 'won' && o.stage !== 'lost').length)
const wonCount = computed(() => opportunitiesList.value.filter(o => o.stage === 'won').length)

// Filters
const filteredDeals = computed(() => opportunitiesList.value.filter(d => {
    const q = search.value.toLowerCase()
    const matchSearch = !q || d.title.toLowerCase().includes(q)
    const matchCust = !filterCustomer.value || d.customerId === filterCustomer.value
    return matchSearch && matchCust
}))

// Drag & Drop Actions
/**
 * @description Event handler triggered when starting to drag an opportunity card.
 * @param { Opportunity } opp Opportunity model instance being dragged
 * @param { DragEvent } ev Native drag event
 * @returns { void }
 */
const onCardDragStart = (opp: Opportunity, ev: DragEvent) => {
    if (opp.stage === 'won' || opp.stage === 'lost') {
        ev.preventDefault()
        return
    }
    draggingId.value = opp.id
    draggingFrom.value = opp.stage
    if (ev.dataTransfer) {
        ev.dataTransfer.effectAllowed = 'move'
        ev.dataTransfer.setData('text/plain', opp.id)
    }
}

/**
 * @description Event handler triggered when card drag completes.
 * @returns { void }
 */
const onCardDragEnd = () => {
    draggingId.value = null
    draggingFrom.value = null
    draggedOverStage.value = null
}

/**
 * @description Event handler triggered when dragging a card over a Kanban column.
 * @param { OpportunityStage } stage Target pipeline stage being dragged over
 * @param { DragEvent } ev Native drag event
 * @returns { void }
 */
const onColumnDragOver = (stage: OpportunityStage, ev: DragEvent) => {
    if (draggingFrom.value === stage) {
        if (ev.dataTransfer) ev.dataTransfer.dropEffect = 'none'
        return
    }
    if (ev.dataTransfer) ev.dataTransfer.dropEffect = 'move'
    draggedOverStage.value = stage
}

/**
 * @description Event handler triggered when card leaves a Kanban column while dragging.
 * @param { OpportunityStage } stage Stage identifier
 * @returns { void }
 */
const onColumnDragLeave = (stage: OpportunityStage) => {
    if (draggedOverStage.value === stage) draggedOverStage.value = null
}

const showLossModal = ref(false)
const lossTarget = ref<Opportunity | null>(null)
const lossReason = ref('')

/**
 * @description Event handler triggered when dropping a card into a Kanban column.
 * @param { OpportunityStage } stage Target pipeline stage
 * @returns { Promise<void> }
 */
const onColumnDrop = async (stage: OpportunityStage) => {
    const id = draggingId.value
    const from = draggingFrom.value
    draggedOverStage.value = null
    draggingId.value = null
    draggingFrom.value = null

    if (!id || !from || from === stage) return

    const idx = opportunitiesList.value.findIndex(o => o.id === id)
    if (idx === -1) return
    const opp = opportunitiesList.value[idx]

    // If lost, trigger validation modal
    if (stage === 'lost') {
        lossTarget.value = opp
        lossReason.value = ''
        pendingDropStage.value = stage
        showLossModal.value = true
    } else {
        await commitStageChange(opp.id, stage)
    }
}

/**
 * @description Persist the Opportunity stage transition event to the backend API
 * @method PATCH
 * @param { String } id Opportunity UUID
 * @param { OpportunityStage } stage Target pipeline stage to transition into
 * @param { String } [reason] Optional feedback statement if marked as Lost
 * @returns { Promise<void> } Resolves on successful transaction
 */
const commitStageChange = async (id: string, stage: OpportunityStage, reason?: string) => {
    const originalDeals = [...opportunitiesList.value]

    // Optimistic UI update
    const idx = opportunitiesList.value.findIndex(o => o.id === id)
    if (idx !== -1) {
        opportunitiesList.value[idx].stage = stage
        if (stage === 'lost' && reason) opportunitiesList.value[idx].lossReason = reason
    }
    movingId.value = id
    pendingDropStage.value = stage

    try {
        const res = await crm.opportunities.updateStage(id, stage, reason)
        if (idx !== -1) opportunitiesList.value[idx] = res.data
        toast.success(stageDropMessage(stage), opportunitiesList.value[idx].title)
        // Dropping into Schedules opens the B2B/B2C product schedule editor
        // so the rep can capture products-of-interest in one motion.
        if (stage === 'schedules' && idx !== -1) {
            scheduleTarget.value = opportunitiesList.value[idx]
        }
    } catch (err: any) {
        opportunitiesList.value = originalDeals // Revert UI
        toast.error('Transition failed', err?.data?.message)
    } finally {
        movingId.value = null
        pendingDropStage.value = null
    }
}

const stageDropMessage = (stage: OpportunityStage): string => {
    switch (stage) {
        case 'new':       return 'Deal moved to Opportunities'
        case 'schedules': return 'Deal moved to Schedules — capture products-of-interest'
        case 'contacted': return 'Opportunity marked as Contacted'
        case 'won':       return 'Opportunity marked as Won (Qualified)'
        case 'lost':      return 'Opportunity marked as Lost (Not Qualified)'
        default:          return `Deal moved to ${stage}`
    }
}

/**
 * @description Submit stage change to Lost along with mandatory loss reason feedback
 * @method PATCH
 * @returns { Promise<void> } Resolves on successful transition
 */
const commitLossTransition = async () => {
    if (!lossTarget.value || !pendingDropStage.value) return
    transitioning.value = true
    try {
        await commitStageChange(lossTarget.value.id, pendingDropStage.value, lossReason.value)
        showLossModal.value = false
        lossTarget.value = null
        pendingDropStage.value = null
    } finally {
        transitioning.value = false
    }
}

/**
 * @description Abort stage transition to Lost, resetting loss-reason states and closing modal.
 * @returns { void }
 */
const cancelLossTransition = () => {
    showLossModal.value = false
    lossTarget.value = null
    pendingDropStage.value = null
}

// Opportunity Form Actions
const showFormModal = ref(false)
const isEdit = ref(false)
const editId = ref<string | null>(null)
const form = reactive<CreateOpportunityPayload>({
    title: '',
    customerId: null,
    estimatedValue: 0,
    probability: 20,
    stage: 'new',
    leadId: null,
    projectedCloseDate: null
})

/**
 * @description Open opportunity creation form modal and reset field inputs.
 * @returns { void }
 */
const openCreateModal = () => {
    isEdit.value = false
    editId.value = null
    form.title = ''
    form.customerId = null
    form.estimatedValue = 0
    form.probability = 20
    form.stage = 'new'
    form.leadId = null
    form.projectedCloseDate = null
    showFormModal.value = true
}

/**
 * @description Pre-fill the form when a Lead is picked in the "From Lead"
 * dropdown so the rep doesn't retype the title / value the lead already has.
 * Existing user edits (non-empty title or non-zero value) are preserved.
 */
const hydrateFromLead = () => {
    if (!form.leadId) return
    const lead = leads.value.find(l => l.id === form.leadId)
    if (!lead) return
    if (!form.title) form.title = lead.title || ''
    if (!form.estimatedValue && lead.estimatedValue) form.estimatedValue = lead.estimatedValue
    if (!form.customerId && lead.customerId) form.customerId = lead.customerId
}

/**
 * @description Open opportunity editing form modal, populating details from model.
 * @param { Opportunity } opp Opportunity model instance to edit
 * @returns { void }
 */
const openEditModal = (opp: Opportunity) => {
    isEdit.value = true
    editId.value = opp.id
    form.title = opp.title
    form.customerId = opp.customerId
    form.estimatedValue = opp.estimatedValue
    form.probability = opp.probability
    form.stage = opp.stage
    form.leadId = opp.leadId
    form.projectedCloseDate = opp.projectedCloseDate
    showFormModal.value = true
}

/**
 * @description Save opportunity details to the database (Create new or Update existing)
 * @method POST/PUT
 * @returns { Promise<void> } Resolves on success, shows toast and reloads Kanban columns
 */
const saveOpportunity = async () => {
    submitting.value = true
    try {
        // Empty string FKs would fail Laravel's uuid rule — null them out so
        // they pass `sometimes|nullable|uuid` cleanly. Customer is optional in
        // the target flow; it gets created at Quotation::win in Sales.
        const payload: CreateOpportunityPayload = {
            ...form,
            customerId: form.customerId || null,
            leadId: form.leadId || null,
        }

        if (isEdit.value && editId.value) {
            const res = await crm.opportunities.update(editId.value, payload)
            const idx = opportunitiesList.value.findIndex(o => o.id === editId.value)
            if (idx !== -1) opportunitiesList.value[idx] = res.data
            toast.success('Opportunity updated', form.title)
        } else {
            const res = await crm.opportunities.create(payload)
            opportunitiesList.value.unshift(res.data)
            toast.success('Opportunity created', form.title)
        }
        showFormModal.value = false
    } catch (err: any) {
        toast.error('Saving failed', err?.data?.message)
    } finally {
        submitting.value = false
    }
}

// Delete Actions
const deleteTarget = ref<Opportunity | null>(null)
/**
 * @description Prompts confirmation overlay before archiving a sales opportunity.
 * @param { Opportunity } d Opportunity model instance to archive
 * @returns { void }
 */
const confirmDelete = (d: Opportunity) => { deleteTarget.value = d }

/**
 * @description Submits archiving request for designated target opportunity.
 * @method DELETE
 * @returns { Promise<void> } Resolves on success, shows toast and updates list
 */
const onConfirmDelete = async () => {
    if (!deleteTarget.value) return
    deleting.value = true
    try {
        await crm.opportunities.destroy(deleteTarget.value.id)
        opportunitiesList.value = opportunitiesList.value.filter(o => o.id !== deleteTarget.value!.id)
        toast.success('Opportunity archived', deleteTarget.value.title)
        deleteTarget.value = null
    } catch (err: any) {
        toast.error('Archive failed', err?.data?.message)
    } finally {
        deleting.value = false
    }
}

// Styling helpers
/**
 * @description Extracts first initials of words in name string.
 * @param { String } name Target name string
 * @returns { String } Mapped initials in upper-case
 */
const initials = (name: string) =>
    name.split(/\s+/).filter(Boolean).slice(0, 2).map(p => p[0]!.toUpperCase()).join('')

/**
 * @description Computes integer rating from 1 to 5 stars base on deal probability percentage.
 * @param { Number } prob Probability percentage value
 * @returns { Number } Calculated star count (1 to 5)
 */
const probabilityRating = (prob: number) => {
    return Math.max(1, Math.min(5, Math.ceil(prob / 20)))
}

/**
 * @description Map pipeline stage status to corresponding UI class variant.
 * @param { OpportunityStage } s Pipeline stage status
 * @returns { String | undefined } Styling class name
 */
const columnHeaderClass = (s: OpportunityStage) => {
    switch (s) {
        case 'new':       return 'badge-soft-info'
        case 'schedules': return 'badge-soft-primary'
        case 'contacted': return 'badge-soft-warning'
        case 'won':       return 'badge-soft-success'
        case 'lost':      return 'badge-soft-danger'
        // Legacy stages collapse into "Contacted" — kept here so the cast
        // stays exhaustive even though they no longer surface as columns.
        case 'qualified':
        case 'proposal':
        case 'negotiation': return 'badge-soft-warning'
    }
}

const stageColorMap: Record<OpportunityStage, string> = {
    new:         'bg-slate-400',
    schedules:   'bg-indigo-500',
    contacted:   'bg-amber-500',
    qualified:   'bg-amber-500',
    proposal:    'bg-amber-500',
    negotiation: 'bg-amber-500',
    won:         'bg-emerald-500',
    lost:        'bg-rose-500',
}

// Boot Data
/**
 * @description Hydrates pipeline data, fetching opportunities and customer catalogs.
 * @method GET
 * @returns { Promise<void> } Resolves on success
 */
const load = async () => {
    loading.value = true
    try {
        const [oRes, cRes, lRes] = await Promise.all([
            crm.opportunities.list({ limit: 150 }),
            sales.catalogue.listCustomers({ limit: 200 }),
            crm.leads.list({ limit: 150 })
        ])
        opportunitiesList.value = oRes.data
        customers.value = cRes.data
        leads.value = lRes.data
    } catch (err: any) {
        toast.error('Failed to load sales pipeline', err?.data?.message)
    } finally {
        loading.value = false
    }
}

/**
 * @description Calculates weighted deal value (Estimated Value * Probability).
 * @param { Opportunity } d Opportunity model instance
 * @returns { Number } Calculated dollar amount
 */
const weightedValue = (d: Opportunity) => (num(d.estimatedValue) * num(d.probability)) / 100
/**
 * @description Formats a date string into custom localized format.
 * @param { String } d ISO Date string
 * @returns { String } Formatted string representation
 */
const formatDate = (d: string) => new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: '2-digit' })
/**
 * @description Format numeric amount to USD currency display pattern.
 * @param { Number } v Value
 * @returns { String } Mapped currency format string
 */
const formatCurrency = (v: number) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v)

onMounted(load)
</script>

<style scoped>
.kanban-column {
    width: 300px;
    min-width: 300px;
    max-width: 300px;
    background: var(--bg-muted);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 0.75rem;
    transition: border-color 0.15s ease, background 0.15s ease;
}

.kanban-column--dragover {
    border-color: var(--color-primary);
    background: color-mix(in srgb, var(--color-primary-subtle) 70%, var(--bg-muted));
}

.kanban-list {
    min-height: 1px;
}

.kanban-card {
    user-select: none;
}

.kanban-card:hover {
    transform: translateY(-2px);
}

.kanban-card:active {
    cursor: grabbing;
}

.kanban-card--dragging {
    opacity: 0.45;
    transform: scale(0.98);
}

.kanban-empty {
    background: color-mix(in srgb, var(--bg-card) 60%, transparent);
}

.kanban-scroller {
    scrollbar-width: thin;
    scrollbar-color: var(--border-strong) transparent;
}

.kanban-scroller::-webkit-scrollbar {
    height: 8px;
}

.kanban-scroller::-webkit-scrollbar-thumb {
    background: var(--border-strong);
    border-radius: 4px;
}

.mini-action-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 20px;
    height: 20px;
    border-radius: 4px;
    color: var(--text-muted);
    background: transparent;
    border: none;
    cursor: pointer;
    transition: color 0.15s ease, background 0.15s ease;
}

.mini-action-btn:hover {
    color: var(--color-primary);
    background: var(--bg-muted);
}

.kanban-cta {
    display: block;
    cursor: pointer;
    background: linear-gradient(180deg,
            color-mix(in srgb, var(--color-primary-subtle) 80%, var(--bg-card)) 0%,
            var(--bg-card) 100%);
    border: 1px dashed rgb(var(--color-primary-rgb) / 0.4);
    box-shadow: 0 1px 2px 0 rgb(var(--color-primary-rgb) / 0.08);
    transition: transform 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease, background 0.15s ease;
}

.kanban-cta:hover {
    transform: translateY(-2px);
    border-color: var(--color-primary);
    background: linear-gradient(180deg,
            color-mix(in srgb, var(--color-primary-subtle) 95%, var(--bg-card)) 0%,
            color-mix(in srgb, var(--color-primary-subtle) 30%, var(--bg-card)) 100%);
    box-shadow: 0 6px 16px -4px rgb(var(--color-primary-rgb) / 0.25);
}

.kanban-cta:focus-visible {
    outline: none;
    border-color: var(--color-primary);
    box-shadow: 0 0 0 3px rgb(var(--color-primary-rgb) / 0.2);
}

.kanban-cta-icon {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 9999px;
    background: var(--color-primary);
    color: #fff;
    box-shadow: 0 4px 12px rgb(var(--color-primary-rgb) / 0.35);
}
</style>
