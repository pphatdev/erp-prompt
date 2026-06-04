<template>
    <div class="space-y-5">
        <!-- Page header (mirrors WorkSchedulesSection / LeaveTypesSection) -->
        <header>
            <h2 class="text-xl font-semibold text-(--text-heading) leading-tight">Workflow Statuses</h2>
            <p class="text-xs text-(--text-muted) mt-1">
                Per-tenant FSM configuration for every HRM lifecycle (application, leave, appraisal, vacancy,
                employee, payroll period, ...). Domain services consult this table at runtime via
                <code class="font-mono">WorkflowStatusService::validateTransition</code>.
            </p>
        </header>

        <!-- Sticky toolbar -->
        <section class="sticky top-16 z-20 py-2 bg-(--bg-layout)/90 backdrop-blur">
            <div class="flex items-center gap-3 flex-wrap">
                <div class="filter-select" :class="{ active: !!activeModule }">
                    <i :class="['ti', activeModuleMeta.icon, 'text-(--text-muted) text-sm']" />
                    <select v-model="activeModule" @change="onModuleChange" aria-label="Select module">
                        <option value="">Select module...</option>
                        <option v-for="m in modules" :key="m" :value="m">{{ moduleTitle(m) }}</option>
                    </select>
                    <i class="ti ti-chevron-down text-(--text-muted) text-[10px] pointer-events-none" />
                </div>

                <div v-if="activeModule" class="text-xxs font-mono text-(--text-muted)">
                    {{ filteredRows.length }} row{{ filteredRows.length === 1 ? '' : 's' }}
                </div>

                
                <div class="ml-auto flex items-center gap-2">
                    <!-- View switcher — Grid (status cards) vs Graph (FSM diagram). -->
                    <div v-if="activeModule" class="view-switcher" role="group" aria-label="Switch view">
                        <button type="button" class="view-btn"
                            :class="{ 'view-btn-active': viewMode === 'grid' }"
                            :aria-pressed="viewMode === 'grid'"
                            @click="viewMode = 'grid'">
                            <i class="ti ti-layout-grid" /> <span>Grid</span>
                        </button>
                        <button type="button" class="view-btn"
                            :class="{ 'view-btn-active': viewMode === 'graph' }"
                            :aria-pressed="viewMode === 'graph'"
                            @click="viewMode = 'graph'">
                            <i class="ti ti-chart-arrows" /> <span>Graph</span>
                        </button>
                    </div>
                    <button v-if="activeModule" class="btn btn-primary text-xs" @click="openCreateModal">
                        <i class="ti ti-plus" /> New status
                    </button>
                </div>
            </div>
        </section>

        <!-- Active module banner — shows the friendly title + description
             so a tenant admin understands what they're editing without
             having to decode the raw module key. Always visible when a
             module is picked, regardless of view mode. -->
        <section v-if="activeModule" class="glass-card rounded-2xl p-4 flex items-start gap-3">
            <span class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0 badge-soft-primary">
                <i :class="['ti', activeModuleMeta.icon, 'text-base']" />
            </span>
            <div class="flex-1 min-w-0">
                <div class="flex items-baseline gap-2 flex-wrap">
                    <h3 class="text-sm font-semibold text-(--text-heading)">{{ activeModuleMeta.title }}</h3>
                    <code class="text-xxs font-mono text-(--text-muted)">{{ activeModule }}</code>
                </div>
                <p class="text-xs text-(--text-body) mt-1">{{ activeModuleMeta.description }}</p>
            </div>
            <!-- Fullscreen only makes sense for the Graph view. -->
            <button v-if="viewMode === 'graph' && flowLayout.nodes.length > 0" type="button"
                class="flow-toggle shrink-0" @click="fullscreen = true"
                title="Expand diagram to fullscreen">
                <i class="ti ti-arrows-maximize" />
                <span class="hidden sm:inline">Fullscreen</span>
            </button>
        </section>

        <!-- Graph view: FSM flow diagram. -->
        <section v-if="activeModule && viewMode === 'graph' && flowLayout.nodes.length > 0"
            class="glass-card rounded-2xl p-4">
            <div class="flex items-center justify-between text-xxs text-(--text-muted) mb-2">
                <span class="font-semibold uppercase tracking-widest">Flow diagram</span>
                <span class="flex items-center gap-3 font-mono">
                    <span class="inline-flex items-center gap-1">
                        <span class="legend-dot legend-dot-initial" /> initial
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <span class="legend-dot legend-dot-terminal" /> terminal
                    </span>
                    <span class="inline-flex items-center gap-1">
                        <span class="legend-dot legend-dot-transition" /> transition
                    </span>
                </span>
            </div>
            <div class="flow-hint">
                <i class="ti ti-hand-grab" />
                <span>Drag a card to reposition it on the canvas. Edges follow the cards automatically. Layout changes are visual only — they reset on reload. Use the Grid view to add or edit transitions.</span>
            </div>
            <div class="flow-canvas w-full" @mouseleave="hoverKey = null">
                <WorkflowFlowDiagram :layout="flowLayout" :hover-key="hoverKey"
                    @hover="hoverKey = $event" />
            </div>
        </section>

        <!-- Fullscreen overlay — same diagram rendered at viewport size. -->
        <Teleport to="body">
            <transition name="fs">
                <div v-if="fullscreen" class="fs-overlay" role="dialog" aria-modal="true"
                    @keydown.esc="fullscreen = false" tabindex="0" ref="fsRoot">
                    <header class="fs-header">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 badge-soft-primary">
                                <i :class="['ti', activeModuleMeta.icon, 'text-base']" />
                            </span>
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-(--text-heading) truncate">
                                    {{ activeModuleMeta.title }}
                                </h3>
                                <code class="text-xxs font-mono text-(--text-muted)">{{ activeModule }}</code>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="flex items-center gap-3 text-xxs font-mono text-(--text-muted)">
                                <span class="inline-flex items-center gap-1">
                                    <span class="legend-dot legend-dot-initial" /> initial
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <span class="legend-dot legend-dot-terminal" /> terminal
                                </span>
                                <span class="inline-flex items-center gap-1">
                                    <span class="legend-dot legend-dot-transition" /> transition
                                </span>
                            </span>
                            <button type="button" class="flow-toggle" @click="fullscreen = false"
                                title="Close fullscreen (Esc)">
                                <i class="ti ti-x" /> <span>Close</span>
                            </button>
                        </div>
                    </header>
                    <div class="fs-body flow-canvas-fs" @mouseleave="hoverKey = null">
                        <WorkflowFlowDiagram :layout="flowLayout" :hover-key="hoverKey"
                            @hover="hoverKey = $event" />
                    </div>
                </div>
            </transition>
        </Teleport>

        <!-- Alert -->
        <div v-if="alert.msg" class="px-4 py-3 rounded-lg flex items-center justify-between text-xs font-semibold"
            :class="alert.type === 'success' ? 'badge-soft-success' : 'badge-soft-danger'">
            <span class="flex items-center gap-2">
                <i :class="['ti', alert.type === 'success' ? 'ti-check' : 'ti-alert-triangle']" />
                {{ alert.msg }}
            </span>
            <button class="text-current" @click="alert.msg = ''"><i class="ti ti-x" /></button>
        </div>

        <!-- Loading -->
        <div v-if="loading" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4">
            <div v-for="i in 6" :key="i" class="glass-card rounded-2xl p-4 space-y-3">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-lg bg-(--bg-muted) animate-pulse" />
                    <div class="flex-1 space-y-2">
                        <div class="h-3 w-2/3 bg-(--bg-muted) rounded animate-pulse" />
                        <div class="h-2 w-1/3 bg-(--bg-muted) rounded animate-pulse" />
                    </div>
                </div>
                <div class="h-2 w-full bg-(--bg-muted) rounded animate-pulse" />
            </div>
        </div>

        <!-- No module selected -->
        <div v-else-if="!activeModule" class="glass-card rounded-2xl py-20 text-center">
            <i class="ti ti-list-tree text-4xl text-(--text-muted)" />
            <h4 class="text-sm font-semibold text-(--text-heading) mt-3">Pick an HRM module</h4>
            <p class="text-xs text-(--text-muted) mt-1">
                Status flows are scoped to a module key
                (<code class="font-mono">hrm.application</code>, <code class="font-mono">hrm.leave</code>, ...).
                Choose one above to view + edit its states.
            </p>
        </div>

        <!-- Empty -->
        <div v-else-if="filteredRows.length === 0" class="glass-card rounded-2xl py-20 text-center">
            <i class="ti ti-mood-empty text-4xl text-(--text-muted)" />
            <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No statuses yet</h4>
            <p class="text-xs text-(--text-muted) mt-1">
                <code class="font-mono">{{ activeModule }}</code> has no statuses configured.
                Add an initial state to start the FSM.
            </p>
            <button class="btn btn-soft-primary text-xs mt-4" @click="openCreateModal">
                <i class="ti ti-plus" /> Create first status
            </button>
        </div>

        <!-- Status cards — Grid view only. -->
        <section v-else-if="viewMode === 'grid'"
            class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4">
            <article v-for="row in filteredRows" :key="row.id" class="status-card">
                <header class="flex items-start gap-3">
                    <span class="w-10 h-10 rounded-lg flex items-center justify-center shrink-0"
                        :class="`badge-soft-${row.color || 'secondary'}`">
                        <i :class="['ti', row.icon || 'ti-circle-dot', 'text-base']" />
                    </span>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-semibold text-(--text-heading) truncate">{{ row.label }}</h3>
                        <p class="text-xxs font-mono text-(--text-muted) mt-0.5">
                            #{{ row.sequence }} . {{ row.key }}
                        </p>
                    </div>
                    <button type="button" class="action-trigger"
                        :class="{ 'action-trigger-open': actionMenu.open && actionMenu.row?.id === row.id }"
                        title="Actions" @click.stop="openActionMenu(row, $event)">
                        <i class="ti ti-dots-vertical" />
                    </button>
                </header>

                <div class="mt-3 flex flex-wrap gap-1.5">
                    <span v-if="row.isInitial" class="state-chip badge-soft-info">
                        <i class="ti ti-flag" /> initial
                    </span>
                    <span v-if="row.isTerminal" class="state-chip badge-soft-warning">
                        <i class="ti ti-lock" /> terminal
                    </span>
                    <span v-if="!row.isInitial && !row.isTerminal" class="state-chip badge-soft-secondary">
                        transition
                    </span>
                </div>

                <footer class="mt-3 pt-3 border-t border-(--border-color)/60">
                    <p class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Allowed next</p>
                    <div v-if="row.allowedNext.length > 0" class="flex flex-wrap gap-1.5 mt-2">
                        <span v-for="next in row.allowedNext" :key="next" class="state-chip badge-soft-secondary font-mono">
                            <i class="ti ti-arrow-right text-[10px]" /> {{ next }}
                        </span>
                    </div>
                    <p v-else class="text-xxs text-(--text-muted) italic mt-1">No outgoing transitions.</p>
                </footer>
            </article>
        </section>

        <!-- Edit / create modal -->
        <div v-if="showModal"
            class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-lg p-6 shadow-(--shadow-lg) bg-(--bg-card)">
                <header class="flex items-center justify-between mb-5">
                    <h3 class="text-base font-semibold text-(--text-heading)">
                        {{ editing ? 'Edit status' : 'New status' }}
                    </h3>
                    <button class="topbar-btn" @click="closeModal"><i class="ti ti-x" /></button>
                </header>

                <form class="space-y-4" @submit.prevent="saveRow">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Module</label>
                            <input v-model="form.module" type="text" required class="form-control font-mono"
                                placeholder="hrm.application" :disabled="!!editing" />
                        </div>
                        <div>
                            <label class="form-label">Key</label>
                            <input v-model="form.key" type="text" required class="form-control font-mono"
                                pattern="[a-z0-9_]+" placeholder="approved" :disabled="!!editing" />
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Label</label>
                        <input v-model="form.label" type="text" required class="form-control" placeholder="Approved" />
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Color</label>
                            <select v-model="form.color" class="form-control">
                                <option value="">(none)</option>
                                <option v-for="c in colorOptions" :key="c" :value="c">{{ c }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Icon (ti-*)</label>
                            <input v-model="form.icon" type="text" class="form-control font-mono"
                                placeholder="ti-circle-check" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="form-label">Sequence</label>
                            <input v-model.number="form.sequence" type="number" min="0" class="form-control font-mono" />
                        </div>
                        <div class="flex items-center gap-4 self-end pb-2">
                            <label class="inline-flex items-center gap-1.5 text-xs">
                                <input v-model="form.isInitial" type="checkbox" /> Initial
                            </label>
                            <label class="inline-flex items-center gap-1.5 text-xs">
                                <input v-model="form.isTerminal" type="checkbox" /> Terminal
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="form-label">Allowed next ({{ form.allowedNext.length }} selected)</label>
                        <div class="flex flex-wrap gap-1.5">
                            <button v-for="cand in candidateNextKeys" :key="cand" type="button"
                                class="next-toggle font-mono"
                                :class="{ 'next-toggle-active': form.allowedNext.includes(cand) }"
                                @click="toggleAllowedNext(cand)">
                                {{ cand }}
                            </button>
                            <p v-if="candidateNextKeys.length === 0" class="text-xxs text-(--text-muted) italic">
                                No other statuses in this module yet.
                            </p>
                        </div>
                    </div>

                    <div v-if="formError"
                        class="text-xs text-(--color-danger) bg-(--color-danger-subtle) px-3 py-2 rounded">
                        {{ formError }}
                    </div>

                    <footer class="pt-4 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="closeModal">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="saving">
                            <i :class="['ti', saving ? 'ti-loader-2 animate-spin' : 'ti-device-floppy']" />
                            {{ saving ? 'Saving...' : 'Save' }}
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Action dropdown -->
        <div v-if="actionMenu.open && actionMenu.row"
            class="fixed z-50 glass-card rounded-lg shadow-(--shadow-lg) bg-(--bg-card) border border-(--border-color) py-1 min-w-[180px]"
            :style="{ top: actionMenu.y + 'px', left: actionMenu.x + 'px' }" @click.stop>
            <button class="action-item" @click="actionEdit">
                <i class="ti ti-pencil" /> Edit
            </button>
            <hr class="my-1 border-(--border-color)" />
            <button class="action-item action-item-danger" @click="actionRemove">
                <i class="ti ti-trash" /> Archive
            </button>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, reactive, ref, watch } from 'vue'
import { useWorkflowStatuses, type WorkflowStatus, type WorkflowColor } from '~/composables/useWorkflowStatuses'
import { useToast } from '~/composables/useToast'
import WorkflowFlowDiagram from '~/components/hrm-settings/WorkflowFlowDiagram.vue'

const api = useWorkflowStatuses()
const toast = useToast()

const colorOptions: WorkflowColor[] = ['primary', 'success', 'warning', 'danger', 'info', 'secondary']

// Component lives under HRM Settings — the picker is filtered to `hrm.*`
// module keys only. Non-HRM workflow modules (sales.*, crm.*, ...) are
// edited via their own module settings surface or directly through the
// API; this component intentionally narrows scope to keep the UI focused.
const HRM_MODULE_PREFIX = 'hrm.'

/**
 * Friendly metadata per module key. Tenant admins see the title +
 * description so they understand what each FSM controls without having
 * to decode the dotted key. Keys here mirror the seeded `hrm.*` modules
 * in TenantDatabaseSeeder::seedWorkflowStatuses(); unknown keys fall
 * back to a Title-Cased rendering of the suffix.
 */
const MODULE_META: Record<string, { title: string; description: string; icon: string }> = {
    'hrm.application': {
        title: 'Candidate Flow Status',
        description: 'Recruitment pipeline applied to job applications - drives the Candidate Stages Kanban (applied -> screening -> interview -> hired / rejected / withdrawn).',
        icon: 'ti-user-search',
    },
    'hrm.leave': {
        title: 'Leave Request Status',
        description: 'Status flow for time-off requests. Drives approve / reject buttons and feeds the leave balance calculator.',
        icon: 'ti-calendar-event',
    },
    'hrm.appraisal': {
        title: 'Performance Appraisal Status',
        description: 'Lifecycle of an appraisal cycle: draft -> submitted -> reviewed -> closed. Locks the rating once it reaches a terminal state.',
        icon: 'ti-stars',
    },
    'hrm.vacancy': {
        title: 'Job Vacancy Status',
        description: 'Publication state of a job vacancy. Public careers portal only renders vacancies in the `open` state.',
        icon: 'ti-briefcase',
    },
    'hrm.employee': {
        title: 'Employee Workforce Status',
        description: 'Active / on-leave / terminated state. Influences whether an employee appears in payroll runs and the directory.',
        icon: 'ti-user-circle',
    },
    'hrm.payroll_period': {
        title: 'Payroll Period Status',
        description: 'Lifecycle of a payroll period: draft -> processing -> processed -> closed. Closed periods are immutable and post journals to the GL.',
        icon: 'ti-cash',
    },
    'hrm.quiz_attempt': {
        title: 'Candidate Quiz Attempt Status',
        description: 'Lifecycle of a single magic-link quiz attempt: invited -> in_progress -> completed | expired | abandoned.',
        icon: 'ti-clipboard-check',
    },
    'hrm.interview': {
        title: 'Interview Session Status',
        description: 'Schedule state for panel interviews: scheduled -> completed | cancelled | no_show.',
        icon: 'ti-message-circle',
    },
    'hrm.offer': {
        title: 'Job Offer Status',
        description: 'Lifecycle of a binding offer letter: draft -> sent -> accepted | declined | expired. Acceptance triggers Employee provisioning and the onboarding checklist.',
        icon: 'ti-file-certificate',
    },
    'hrm.onboarding_task': {
        title: 'Onboarding Task Status',
        description: 'Per-task state inside an onboarding checklist: pending -> in_progress -> completed | skipped.',
        icon: 'ti-checklist',
    },
}

const DEFAULT_MODULE_ICON = 'ti-stack'

/**
 * Title-Case the trailing segment after `hrm.` as a graceful fallback
 * when a tenant adds a fully custom module key that isn't in the
 * static metadata map.
 */
const titleCaseSuffix = (mod: string): string => {
    const suffix = mod.startsWith(HRM_MODULE_PREFIX) ? mod.slice(HRM_MODULE_PREFIX.length) : mod
    return suffix
        .split(/[._-]/)
        .filter(Boolean)
        .map(w => w.charAt(0).toUpperCase() + w.slice(1))
        .join(' ')
}

const moduleTitle = (mod: string): string =>
    MODULE_META[mod]?.title ?? titleCaseSuffix(mod)

const activeModuleMeta = computed(() => {
    if (!activeModule.value) {
        return { title: '', description: '', icon: DEFAULT_MODULE_ICON }
    }
    const meta = MODULE_META[activeModule.value]
    if (meta) return meta
    return {
        title: titleCaseSuffix(activeModule.value),
        description: 'Custom workflow module configured for this tenant. Edit transitions, colors, and icons to match your business rules.',
        icon: DEFAULT_MODULE_ICON,
    }
})

// ----------------------------------------------------------------------
// FSM flow diagram
// ----------------------------------------------------------------------

// Grid view shows the editable status cards; Graph view shows the
// FSM diagram. Defaults to Grid because that's where mutations happen
// (Add / Edit / Archive) — the diagram is best for understanding,
// the grid for editing detail.
const viewMode = ref<'grid' | 'graph'>('grid')
const hoverKey = ref<string | null>(null)
const fullscreen = ref(false)

// Esc closes the fullscreen overlay even if the user hasn't clicked the
// inner dialog (which gets focus by default but can lose it).
const onGlobalKey = (e: KeyboardEvent) => {
    if (e.key === 'Escape' && fullscreen.value) {
        fullscreen.value = false
    }
}

// Lock the underlying page scroll while the fullscreen overlay is up so
// trackpad scroll doesn't leak through to the document. Cleared on
// unmount as a safety net.
watch(fullscreen, (open) => {
    if (typeof document === 'undefined') return
    document.body.style.overflow = open ? 'hidden' : ''
})

// Layout constants — FlowMapp-style top-down hierarchy. Pipeline
// statuses stack vertically (one card per row), off-ramp terminals
// live in a sibling column on the right.
const NODE_WIDTH = 220
const NODE_HEIGHT = 84
const ROW_GAP = 48          // vertical space between stacked cards
const COL_GAP = 80          // horizontal space between pipeline + terminal lanes
const PADDING_X = 24
const PADDING_TOP = 56      // extra room for the start trigger above the initial card
const PADDING_BOTTOM = 24
const OFFRAMP_SEQUENCE_THRESHOLD = 50

interface FlowNode {
    key: string
    label: string
    color: string | null
    icon: string | null
    isInitial: boolean
    isTerminal: boolean
    x: number
    y: number
    lane: 'pipeline' | 'offramp'
    allowedNext: string[]
}

interface FlowEdge {
    from: string
    to: string
    path: string
    dashed: boolean
}

/**
 * Left-to-right layered layout. Pipeline statuses (sequence < 50) sit on
 * the top lane in sequence order; off-ramp terminals (sequence >= 50)
 * sit on the bottom lane spread evenly across the same horizontal span.
 * Edges between lanes render dashed so the eye reads them as exits.
 */
const flowLayout = computed<{ nodes: FlowNode[]; edges: FlowEdge[]; width: number; height: number }>(() => {
    if (filteredRows.value.length === 0) {
        return { nodes: [], edges: [], width: 0, height: 0 }
    }

    const pipeline = filteredRows.value
        .filter(r => r.sequence < OFFRAMP_SEQUENCE_THRESHOLD)
    const offramp = filteredRows.value
        .filter(r => r.sequence >= OFFRAMP_SEQUENCE_THRESHOLD)

    const nodes: FlowNode[] = []

    // Pipeline column — stack vertically by sequence. Centered at
    // PIPELINE_X; terminal column sits to its right at TERMINAL_X.
    const PIPELINE_X = PADDING_X
    const TERMINAL_X = PADDING_X + NODE_WIDTH + COL_GAP

    pipeline.forEach((r, idx) => {
        nodes.push({
            key: r.key,
            label: r.label,
            color: r.color,
            icon: r.icon,
            isInitial: r.isInitial,
            isTerminal: r.isTerminal,
            x: PIPELINE_X,
            y: PADDING_TOP + idx * (NODE_HEIGHT + ROW_GAP),
            lane: 'pipeline',
            allowedNext: r.allowedNext ?? [],
        })
    })

    // Terminal column — spread evenly along the pipeline's vertical
    // span so each off-ramp lines up roughly with the pipeline row it
    // tends to be entered from. When there's only one off-ramp it
    // centres against the pipeline midpoint.
    const pipelineSpan = pipeline.length > 0
        ? pipeline.length * NODE_HEIGHT + (pipeline.length - 1) * ROW_GAP
        : NODE_HEIGHT
    offramp.forEach((r, idx) => {
        const slotCount = Math.max(1, offramp.length)
        const slotHeight = pipelineSpan / slotCount
        const slotCenter = slotHeight * idx + slotHeight / 2
        nodes.push({
            key: r.key,
            label: r.label,
            color: r.color,
            icon: r.icon,
            isInitial: r.isInitial,
            isTerminal: r.isTerminal,
            x: TERMINAL_X,
            y: Math.max(PADDING_TOP, PADDING_TOP + slotCenter - NODE_HEIGHT / 2),
            lane: 'offramp',
            allowedNext: r.allowedNext ?? [],
        })
    })

    const nodeByKey = new Map(nodes.map(n => [n.key, n]))

    // First pass: collect cross-lane edges per target. We'll stagger
    // their arrival points along the target's left edge so 8 incoming
    // arrows to `rejected` don't merge into a single visual blob.
    interface PendingEdge { src: FlowNode; dst: FlowNode }
    const crossLaneByTarget = new Map<string, PendingEdge[]>()
    const sameLane: PendingEdge[] = []
    for (const src of nodes) {
        for (const targetKey of src.allowedNext) {
            const dst = nodeByKey.get(targetKey)
            if (!dst) continue
            if (src.lane !== dst.lane) {
                const list = crossLaneByTarget.get(targetKey) ?? []
                list.push({ src, dst })
                crossLaneByTarget.set(targetKey, list)
            } else {
                sameLane.push({ src, dst })
            }
        }
    }

    // Second pass: assign each cross-lane edge a unique arrival y on
    // the destination's LEFT edge. Sorted by source y so the visual
    // order reads top-to-bottom — earliest pipeline stage arrives at
    // the highest slot, latest at the lowest. Avoids line crossings.
    const arrivalY = new Map<string, number>() // key = `${fromKey}->${toKey}`
    for (const [, list] of crossLaneByTarget) {
        const sorted = [...list].sort((a, b) => a.src.y - b.src.y)
        const dst = sorted[0].dst
        const yTop = dst.y + 12
        const yBottom = dst.y + NODE_HEIGHT - 12
        sorted.forEach((edge, i) => {
            const slot = sorted.length === 1
                ? dst.y + NODE_HEIGHT / 2
                : yTop + (i / (sorted.length - 1)) * (yBottom - yTop)
            arrivalY.set(`${edge.src.key}->${edge.dst.key}`, slot)
        })
    }

    const edges: FlowEdge[] = []
    for (const { src, dst } of [...sameLane, ...Array.from(crossLaneByTarget.values()).flat()]) {
        const isCrossLane = src.lane !== dst.lane
        const arrival = isCrossLane
            ? arrivalY.get(`${src.key}->${dst.key}`) ?? (dst.y + NODE_HEIGHT / 2)
            : null
        edges.push({
            from: src.key,
            to: dst.key,
            path: buildEdgePath(src, dst, arrival),
            // Cross-lane (off-ramp) arrows render dashed so the eye
            // reads them as exits rather than the happy path.
            dashed: isCrossLane,
        })
    }

    // Account for skip-edge bulge — the wide cubic Beziers for
    // same-lane skips bow out beyond the right edge of the pipeline
    // column. Reserve up to 180px of breathing room on the right when
    // there are no terminals; terminals take precedence when present
    // because they sit even further to the right.
    const skipBulgePad = 180
    const rightEdge = offramp.length > 0
        ? TERMINAL_X + NODE_WIDTH + PADDING_X
        : PIPELINE_X + NODE_WIDTH + skipBulgePad + PADDING_X
    const totalWidth = rightEdge
    const totalHeight = PADDING_TOP + pipelineSpan + PADDING_BOTTOM

    return { nodes, edges, width: totalWidth, height: totalHeight }
})

const buildEdgePath = (src: FlowNode, dst: FlowNode, arrivalY: number | null = null): string => {
    const r = 8 // corner radius for soft elbow joints

    // Helper: how many pipeline rows lie between source and destination
    // in the same column. 0 = adjacent (clean V drop). > 0 = skip
    // edge that must route AROUND intermediate cards.
    const rowSpan = (NODE_HEIGHT + ROW_GAP) // one row's worth of vertical space
    const sameColumn = src.lane === dst.lane && Math.abs(src.x - dst.x) < 1
    const verticalDistance = dst.y - src.y
    const skipsBetween = sameColumn && verticalDistance > 0
        ? Math.max(0, Math.round(verticalDistance / rowSpan) - 1)
        : 0

    // ----- Cross-lane (pipeline -> terminal) -----
    // Exit the RIGHT edge of the source (so the line never crosses the
    // pipeline column below), arrive at the LEFT edge of the terminal
    // at the staggered y assigned by the layout pass.
    if (src.lane !== dst.lane && arrivalY !== null) {
        const sx = src.x + NODE_WIDTH
        const sy = src.y + NODE_HEIGHT / 2
        const ex = dst.x
        const ey = arrivalY
        // Same y — straight horizontal line.
        if (Math.abs(sy - ey) < 1) {
            return `M ${sx} ${sy} H ${ex}`
        }
        // H-V-H elbow centred between the two columns.
        const midX = sx + (ex - sx) / 2
        const dy = ey > sy ? 1 : -1
        return [
            `M ${sx} ${sy}`,
            `H ${midX - r}`,
            `Q ${midX} ${sy}, ${midX} ${sy + r * dy}`,
            `V ${ey - r * dy}`,
            `Q ${midX} ${ey}, ${midX + r} ${ey}`,
            `H ${ex}`,
        ].join(' ')
    }

    // ----- Same-lane, adjacent rows -----
    // Straight vertical drop bottom -> top.
    if (sameColumn && skipsBetween === 0 && verticalDistance > 0) {
        const sx = src.x + NODE_WIDTH / 2
        const sy = src.y + NODE_HEIGHT
        const ey = dst.y
        return `M ${sx} ${sy} V ${ey}`
    }

    // ----- Same-lane skip edges -----
    // Bow out to the right of the pipeline column via a cubic Bezier
    // so the curve clears the intermediate cards. Curve magnitude
    // scales with the skip distance — a skip-1 barely bulges, a
    // skip-4 swings further out.
    if (sameColumn && skipsBetween > 0 && verticalDistance > 0) {
        const sx = src.x + NODE_WIDTH / 2
        const sy = src.y + NODE_HEIGHT
        const ex = dst.x + NODE_WIDTH / 2
        const ey = dst.y
        // Right-bulge magnitude. Tuned so a 4-row skip bows out by ~140px
        // past the source's right edge — clear of cards on either side.
        const bulge = Math.min(180, 60 + 26 * skipsBetween)
        const cp1x = sx + bulge
        const cp1y = sy + (ey - sy) * 0.15
        const cp2x = ex + bulge
        const cp2y = ey - (ey - sy) * 0.15
        return `M ${sx} ${sy} C ${cp1x} ${cp1y}, ${cp2x} ${cp2y}, ${ex} ${ey}`
    }

    // ----- Backward edge -----
    // Destination is above the source in the same column. Route
    // around to the right side and climb. Same shape as the skip
    // edges, just inverted.
    if (sameColumn && verticalDistance <= 0) {
        const sx = src.x + NODE_WIDTH / 2
        const sy = src.y + NODE_HEIGHT
        const ex = dst.x + NODE_WIDTH / 2
        const ey = dst.y
        const bulge = 140
        return `M ${sx} ${sy} C ${sx + bulge} ${sy + 30}, ${ex + bulge} ${ey - 30}, ${ex} ${ey}`
    }

    // ----- Fallback: forward edge across columns within the same lane (rare). -----
    const sx = src.x + NODE_WIDTH / 2
    const sy = src.y + NODE_HEIGHT
    const ex = dst.x + NODE_WIDTH / 2
    const ey = dst.y
    const midY = sy + (ey - sy) / 2
    const dx = ex > sx ? 1 : -1
    return [
        `M ${sx} ${sy}`,
        `V ${midY - r}`,
        `Q ${sx} ${midY}, ${sx + r * dx} ${midY}`,
        `H ${ex - r * dx}`,
        `Q ${ex} ${midY}, ${ex} ${midY + r}`,
        `V ${ey}`,
    ].join(' ')
}

const isEdgeActive = (e: FlowEdge): boolean => hoverKey.value === e.from

const isOutgoingTarget = (key: string): boolean => {
    if (!hoverKey.value) return false
    const src = filteredRows.value.find(r => r.key === hoverKey.value)
    return !!src && (src.allowedNext ?? []).includes(key)
}

const modules = ref<string[]>([])
const activeModule = ref<string>('')
const rows = ref<WorkflowStatus[]>([])
const loading = ref(false)
const saving = ref(false)
const showModal = ref(false)
const editing = ref<WorkflowStatus | null>(null)
const formError = ref<string | null>(null)
const alert = reactive({ msg: '', type: 'success' as 'success' | 'danger' })

const form = reactive({
    module: '',
    key: '',
    label: '',
    color: '' as WorkflowColor | '',
    icon: '',
    sequence: 1,
    isInitial: false,
    isTerminal: false,
    allowedNext: [] as string[],
})

const actionMenu = reactive({ open: false, x: 0, y: 0, row: null as WorkflowStatus | null })

const filteredRows = computed(() =>
    [...rows.value].sort((a, b) => a.sequence - b.sequence)
)

const candidateNextKeys = computed(() =>
    rows.value
        .filter(r => r.key !== form.key)
        .map(r => r.key)
        .sort()
)

const loadModules = async () => {
    try {
        const res = await api.modules()
        // Narrow to HRM modules only — this component is mounted inside
        // the HRM Settings page.
        modules.value = res.data.filter(m => m.startsWith(HRM_MODULE_PREFIX))
        if (modules.value.length > 0 && !activeModule.value) {
            activeModule.value = modules.value[0]
            await loadRows()
        }
    } catch (err: any) {
        alert.msg = err?.data?.message || 'Failed to load modules.'
        alert.type = 'danger'
    }
}

const loadRows = async () => {
    if (!activeModule.value) {
        rows.value = []
        return
    }
    loading.value = true
    try {
        const res = await api.list(activeModule.value)
        rows.value = res.data
    } catch (err: any) {
        alert.msg = err?.data?.message || 'Failed to load statuses.'
        alert.type = 'danger'
        rows.value = []
    } finally {
        loading.value = false
    }
}

const onModuleChange = () => { loadRows() }

const resetForm = () => {
    form.module = activeModule.value
    form.key = ''
    form.label = ''
    form.color = ''
    form.icon = ''
    form.sequence = filteredRows.value.length + 1
    form.isInitial = false
    form.isTerminal = false
    form.allowedNext = []
    formError.value = null
}

const openCreateModal = () => {
    editing.value = null
    resetForm()
    showModal.value = true
}

const openEditModal = (row: WorkflowStatus) => {
    editing.value = row
    form.module = row.module
    form.key = row.key
    form.label = row.label
    form.color = (row.color ?? '') as WorkflowColor | ''
    form.icon = row.icon ?? ''
    form.sequence = row.sequence
    form.isInitial = row.isInitial
    form.isTerminal = row.isTerminal
    form.allowedNext = [...row.allowedNext]
    formError.value = null
    showModal.value = true
}

const closeModal = () => { showModal.value = false; editing.value = null }

const toggleAllowedNext = (key: string) => {
    const idx = form.allowedNext.indexOf(key)
    if (idx === -1) form.allowedNext.push(key)
    else form.allowedNext.splice(idx, 1)
}

const saveRow = async () => {
    if (form.isInitial && form.isTerminal) {
        formError.value = 'A status cannot be both initial and terminal.'
        return
    }
    if (form.isTerminal && form.allowedNext.length > 0) {
        formError.value = 'Terminal statuses cannot have outgoing transitions.'
        return
    }
    // New rows must keep the HRM scope when created from this tab.
    if (!editing.value && !form.module.startsWith(HRM_MODULE_PREFIX)) {
        formError.value = `Module must start with "${HRM_MODULE_PREFIX}" (this tab manages HRM workflows only).`
        return
    }

    saving.value = true
    formError.value = null
    try {
        const payload = {
            module: form.module,
            key: form.key,
            label: form.label,
            color: form.color || null,
            icon: form.icon || null,
            sequence: form.sequence,
            isInitial: form.isInitial,
            isTerminal: form.isTerminal,
            allowedNext: form.allowedNext,
        }
        if (editing.value) {
            await api.update(editing.value.id, payload)
        } else {
            await api.create(payload)
        }
        showModal.value = false
        alert.msg = editing.value ? 'Status updated.' : 'Status created.'
        alert.type = 'success'
        // Refresh modules list in case we just introduced a new module key.
        await loadModules()
        await loadRows()
    } catch (err: any) {
        const errors = err?.data?.errors
        if (errors && typeof errors === 'object') {
            const first = Object.values(errors)[0]
            formError.value = Array.isArray(first) ? String(first[0]) : 'Validation failed.'
        } else {
            formError.value = err?.data?.message || 'Failed to save.'
        }
    } finally {
        saving.value = false
    }
}

const openActionMenu = (row: WorkflowStatus, ev: MouseEvent) => {
    const rect = (ev.currentTarget as HTMLElement).getBoundingClientRect()
    const menuWidth = 180
    const menuMaxHeight = 120
    const left = Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8)
    const wouldOverflow = rect.bottom + menuMaxHeight + 8 > window.innerHeight
    actionMenu.row = row
    actionMenu.x = Math.max(8, left)
    actionMenu.y = wouldOverflow ? rect.top - menuMaxHeight - 6 : rect.bottom + 6
    actionMenu.open = true
}

const closeActionMenu = () => { actionMenu.open = false; actionMenu.row = null }

const actionEdit = () => {
    const r = actionMenu.row
    closeActionMenu()
    if (r) openEditModal(r)
}

const actionRemove = async () => {
    const r = actionMenu.row
    closeActionMenu()
    if (!r) return

    const ok = await toast.confirm({
        title: `Archive "${r.label}"?`,
        description: `This soft-deletes status "${r.key}" from module "${r.module}". Any live records currently sitting in this status will fail transition validation until the row is restored or those records are moved.`,
        confirmLabel: 'Archive status',
        cancelLabel: 'Keep status',
        color: 'danger',
        icon: 'ti-trash',
    })
    if (!ok) return

    try {
        await api.remove(r.id)
        alert.msg = 'Status archived.'
        alert.type = 'success'
        await loadRows()
    } catch (err: any) {
        alert.msg = err?.data?.message || 'Failed to archive.'
        alert.type = 'danger'
    }
}

onMounted(() => {
    if (import.meta.client) {
        document.addEventListener('click', closeActionMenu)
        document.addEventListener('keydown', onGlobalKey)
    }
    loadModules()
})

onBeforeUnmount(() => {
    if (import.meta.client) {
        document.removeEventListener('click', closeActionMenu)
        document.removeEventListener('keydown', onGlobalKey)
        // Safety net: if a user leaves the tab while fullscreen was open
        // (e.g. SPA navigation), restore the scroll lock so the next
        // page isn't stuck unscrollable.
        if (fullscreen.value) {
            document.body.style.overflow = ''
        }
    }
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
    display: inline-flex; align-items: center; justify-content: center;
    width: 32px; height: 32px; border-radius: 8px;
    color: var(--text-muted); cursor: pointer;
}
.topbar-btn:hover { background: var(--bg-muted); color: var(--text-heading); }

.filter-select {
    position: relative; display: inline-flex; align-items: center; gap: 6px;
    height: 32px; padding: 0 10px; border-radius: 999px;
    border: 1px solid var(--border-color); background: var(--bg-card);
    color: var(--text-body); font-size: 11px;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
.filter-select:hover { background: var(--bg-muted); color: var(--text-heading); }
.filter-select.active {
    background: rgb(var(--color-primary-rgb) / 0.08);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}
.filter-select.active i.ti { color: var(--color-primary); }
.filter-select select {
    appearance: none; -webkit-appearance: none; -moz-appearance: none;
    background: transparent; border: 0; outline: none; font: inherit;
    color: inherit; padding-right: 4px; max-width: 220px; cursor: pointer;
    font-family: var(--font-mono, monospace);
}

.status-card {
    background: var(--bg-card); border: 1px solid var(--border-color);
    border-radius: 1rem; padding: 1rem;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}
.status-card:hover {
    border-color: rgb(var(--color-primary-rgb) / 0.35);
    box-shadow: 0 2px 8px rgb(var(--color-primary-rgb) / 0.05);
}

.state-chip {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 3px 8px; border-radius: 999px;
    font-size: 10px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.04em;
}

.next-toggle {
    display: inline-flex; align-items: center; padding: 4px 10px;
    border-radius: 999px; border: 1px solid var(--border-color);
    background: var(--bg-card); color: var(--text-muted);
    font-size: 11px; cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
.next-toggle:hover { border-color: rgb(var(--color-primary-rgb) / 0.4); color: var(--text-heading); }
.next-toggle-active {
    background: rgb(var(--color-primary-rgb) / 0.12);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}

.action-trigger {
    display: inline-flex; align-items: center; justify-content: center;
    width: 30px; height: 30px; border-radius: 8px;
    color: var(--text-muted); cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}
.action-trigger:hover { background: var(--bg-muted); color: var(--text-heading); }
.action-trigger-open { background: var(--bg-muted); color: var(--color-primary); }

.action-item {
    width: 100%; display: flex; align-items: center; gap: 0.5rem;
    padding: 0.5rem 0.75rem; font-size: 0.75rem;
    color: var(--text-heading); text-align: left; cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}
.action-item:hover { background: var(--bg-muted); }
.action-item-danger { color: var(--color-danger); }
.action-item-danger:hover { background: var(--color-danger-subtle); }

/* ----- FSM flow diagram ------------------------------------------- */

.flow-toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    border-radius: 8px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-body);
    font-size: 11px;
    font-weight: 600;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
.flow-toggle:hover {
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}
.flow-toggle-open {
    background: rgb(var(--color-primary-rgb) / 0.08);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}

/* Grid/Graph segmented switcher — sits in the toolbar between the
   module picker and the "New status" button. Mirrors the segmented
   pattern used elsewhere (status filter on Receipts, etc.). */
.view-switcher {
    display: inline-flex;
    align-items: center;
    padding: 3px;
    border-radius: 999px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
}
.view-btn {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 10px;
    border-radius: 999px;
    border: 0;
    background: transparent;
    font-size: 11px;
    font-weight: 600;
    color: var(--text-body);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}
.view-btn:hover { color: var(--text-heading); }
.view-btn-active {
    background: rgb(var(--color-primary-rgb) / 0.12);
    color: var(--color-primary);
    box-shadow: inset 0 0 0 1px rgb(var(--color-primary-rgb) / 0.25);
}

/* Inline diagram scroll container — full width so the SVG breathes
   across the full panel; overflow-x keeps wider diagrams pannable. */
.flow-canvas {
    width: 100%;
    overflow-x: auto;
    padding: 4px 0 8px 0;
}
/* Stretch the SVG inside the canvas to the container width. When the
   layout's computed width is narrower than the container, the SVG
   scales up via viewBox; when it's wider, overflow-x kicks in. */
.flow-canvas :deep(svg) {
    width: 100%;
    height: auto;
    display: block;
    min-width: 100%;
}

/* Brief hint surfaced above the diagram for editors. Tells admins what
   the interaction model is without forcing a heavy onboarding flow. */
.flow-hint {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    margin-bottom: 8px;
    border-radius: 8px;
    background: rgb(var(--color-info-rgb) / 0.08);
    border: 1px solid rgb(var(--color-info-rgb) / 0.25);
    color: var(--text-body);
    font-size: 11px;
    line-height: 1.4;
}
.flow-hint .ti {
    color: rgb(var(--color-info-rgb));
    font-size: 14px;
    flex-shrink: 0;
}

/* Fullscreen diagram fills the modal body with its own scroll. */
.flow-canvas-fs {
    flex: 1;
    overflow: auto;
    padding: 1.5rem;
}

/* Legend chips at the top of the diagram. Mermaid uses solid dots for
   start markers + ring outlines for terminal states; we mirror that. */
.legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 999px;
    display: inline-block;
    box-sizing: border-box;
}
.legend-dot-initial    { background: var(--text-heading); }
.legend-dot-terminal   { background: var(--bg-card); border: 2px solid color-mix(in srgb, var(--text-heading) 60%, transparent); }
.legend-dot-transition { background: var(--bg-card); border: 1px solid color-mix(in srgb, var(--text-heading) 35%, transparent); }

/* ----- Fullscreen overlay ----- */
.fs-overlay {
    position: fixed;
    inset: 0;
    z-index: 90;
    display: flex;
    flex-direction: column;
    background: var(--bg-layout, var(--bg-card));
    outline: none;
}
.fs-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 1rem;
    padding: 0.75rem 1.25rem;
    border-bottom: 1px solid var(--border-color);
    background: var(--bg-card);
    flex-shrink: 0;
}

.fs-enter-active,
.fs-leave-active {
    transition: opacity 0.18s ease;
}
.fs-enter-from,
.fs-leave-to {
    opacity: 0;
}

/* ----- Transition ----- */
.flow-enter-active,
.flow-leave-active {
    transition: opacity 0.18s ease, max-height 0.25s ease;
    overflow: hidden;
}
.flow-enter-from,
.flow-leave-to {
    opacity: 0;
    max-height: 0;
}
.flow-enter-to,
.flow-leave-from {
    opacity: 1;
    max-height: 400px;
}
</style>
