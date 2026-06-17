<template>
    <Teleport to="body">
        <transition name="backdrop">
            <div v-if="modelValue" class="fixed inset-0 z-100 flex items-start justify-center pt-[10vh] px-4 sm:px-6">
                <!-- Backdrop -->
                <div class="fixed inset-0 bg-black/40 backdrop-blur-sm" @click="$emit('update:modelValue', false)"></div>

                <!-- Palette -->
                <div class="relative w-full max-w-2xl bg-(--bg-card) rounded-2xl shadow-(--shadow-lg) border border-(--border-color) overflow-hidden flex flex-col max-h-[80vh]"
                    @click.stop>

                    <!-- Search Input -->
                    <div class="flex items-center px-4 py-4 border-b border-(--border-color)">
                        <i class="ti ti-search text-(--text-muted) text-xl mr-3"></i>
                        <input ref="searchInput" v-model="searchQuery" type="text"
                            class="flex-1 bg-transparent border-none outline-none text-(--text-heading) text-base placeholder:text-(--text-muted)"
                            placeholder="Jump to anything — pages, actions, settings..."
                            @keydown.esc="$emit('update:modelValue', false)"
                            @keydown.down.prevent="navigateOptions(1)"
                            @keydown.up.prevent="navigateOptions(-1)"
                            @keydown.enter.prevent="selectActive"
                            @keydown="onInputKeydown" />
                        <span class="text-xxs font-mono font-semibold px-1.5 py-0.5 rounded border border-(--border-color) bg-(--bg-muted) text-(--text-muted)">ESC</span>
                    </div>

                    <!-- Results -->
                    <div class="flex-1 overflow-y-auto p-2 custom-scrollbar">
                        <template v-if="visibleSections.length">
                            <div v-for="section in visibleSections" :key="section.id" class="px-3 py-2">
                                <h4 class="text-xxs font-bold uppercase tracking-widest text-(--text-muted) mb-2 flex items-center gap-2">
                                    <i v-if="section.icon" :class="['ti', section.icon]" class="text-sm" />
                                    {{ section.label }}
                                </h4>
                                <ul class="space-y-1">
                                    <li v-for="item in section.items" :key="item.id">
                                        <button
                                            :data-palette-id="item.id"
                                            class="w-full flex items-center gap-3 px-3 py-2 rounded-xl text-left transition-colors text-sm"
                                            :class="activeId === item.id
                                                ? 'bg-(--color-primary)/10 text-(--color-primary)'
                                                : 'hover:bg-(--bg-muted) text-(--text-body)'"
                                            @click="selectItem(item)"
                                            @mouseenter="activeId = item.id">
                                            <div class="w-8 h-8 rounded-lg flex items-center justify-center transition-colors shrink-0"
                                                :class="activeId === item.id
                                                    ? 'bg-(--color-primary)/20 text-(--color-primary)'
                                                    : 'bg-(--bg-muted) text-(--text-muted)'">
                                                <i :class="['ti', item.icon, 'text-lg']" />
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="font-medium truncate flex items-center gap-2">
                                                    {{ item.label }}
                                                    <span v-if="item.kind === 'action'"
                                                        class="text-xxs uppercase tracking-widest px-1.5 py-0.5 rounded bg-(--color-primary)/10 text-(--color-primary) font-bold shrink-0">
                                                        Action
                                                    </span>
                                                </div>
                                                <div class="text-xxs mt-0.5 truncate"
                                                    :class="activeId === item.id
                                                        ? 'text-(--color-primary)/70'
                                                        : 'text-(--text-muted)'">
                                                    {{ item.category }}
                                                </div>
                                            </div>
                                            <span v-if="item.quickIndex !== undefined"
                                                class="hidden sm:inline-flex text-xxs font-mono font-semibold px-1.5 py-0.5 rounded border border-(--border-color) bg-(--bg-muted)/50 text-(--text-muted) shrink-0"
                                                :title="`Press ${shortcutLabel} ${item.quickIndex} to open`">
                                                {{ shortcutLabel }}{{ item.quickIndex }}
                                            </span>
                                            <i class="ti ti-chevron-right text-xs shrink-0"
                                                :class="activeId === item.id ? 'opacity-100' : 'opacity-0 text-(--text-muted)'"></i>
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </template>

                        <div v-else class="px-3 py-12 flex flex-col items-center justify-center text-center">
                            <i class="ti ti-mood-empty text-4xl text-(--text-muted)/50 mb-3"></i>
                            <p class="text-sm text-(--text-muted)">
                                Nothing matches "<span class="text-(--text-heading)">{{ searchQuery }}</span>"
                            </p>
                            <p class="text-xxs text-(--text-muted)/70 mt-2">
                                Try shorter words, or check spelling.
                            </p>
                        </div>
                    </div>

                    <div class="bg-(--bg-muted)/50 border-t border-(--border-color) px-4 py-3 flex items-center justify-between text-xxs text-(--text-muted)">
                        <div class="flex items-center gap-4 flex-wrap">
                            <span class="flex items-center gap-1">
                                <span class="px-1 py-0.5 rounded border border-(--border-color) bg-(--bg-card)">↑↓</span>
                                navigate
                            </span>
                            <span class="flex items-center gap-1">
                                <span class="px-1 py-0.5 rounded border border-(--border-color) bg-(--bg-card)">↵</span>
                                open
                            </span>
                            <span class="flex items-center gap-1">
                                <span class="px-1 py-0.5 rounded border border-(--border-color) bg-(--bg-card)">{{ shortcutLabel }} 1–9</span>
                                quick pick
                            </span>
                        </div>
                        <span class="hidden md:inline">{{ totalCount }} result{{ totalCount === 1 ? '' : 's' }}</span>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<script setup lang="ts">
import { ref, watch, computed, nextTick, onMounted } from 'vue'
import { useRouter } from 'vue-router'

type ItemKind = 'page' | 'action'

interface SearchItem {
    kind: ItemKind
    id: string
    label: string
    icon: string
    category: string
    route: string
    keywords?: string[]
    quickIndex?: number
}

interface Section {
    id: string
    label: string
    icon?: string
    items: SearchItem[]
}

const props = defineProps<{
    modelValue: boolean
    navGroups?: any[]
}>()

const emit = defineEmits(['update:modelValue'])
const router = useRouter()

const searchQuery = ref('')
const searchInput = ref<HTMLInputElement | null>(null)
const activeId = ref<string | null>(null)

// macOS shows ⌥ (Option), others Alt. Alt is used for quick-pick to avoid
// the browser's Ctrl/⌘+1–9 tab-switch shortcut, which can't be reliably
// preventDefault'd from JS.
const shortcutLabel = ref('Alt')

// Frecency: { count, lastAt } per item id. Persists across sessions so the
// palette learns each user's habits and surfaces the items they use most.
type FrecencyEntry = { count: number; lastAt: number }
const FRECENCY_KEY = 'palette.frecency.v1'
const FRECENCY_CAP = 80
const frecency = ref<Record<string, FrecencyEntry>>({})

const loadFrecency = () => {
    try {
        const raw = localStorage.getItem(FRECENCY_KEY)
        if (!raw) return
        const parsed = JSON.parse(raw)
        if (parsed && typeof parsed === 'object') frecency.value = parsed
    } catch { /* corrupt blob — ignore, will be overwritten on next save */ }
}

const saveFrecency = () => {
    try {
        // Cap entries so localStorage doesn't grow unbounded. Drop oldest by lastAt.
        const entries = Object.entries(frecency.value)
        if (entries.length > FRECENCY_CAP) {
            entries.sort((a, b) => b[1].lastAt - a[1].lastAt)
            const trimmed = Object.fromEntries(entries.slice(0, FRECENCY_CAP))
            frecency.value = trimmed
        }
        localStorage.setItem(FRECENCY_KEY, JSON.stringify(frecency.value))
    } catch { /* quota or disabled — silently drop */ }
}

const trackVisit = (id: string) => {
    const entry = frecency.value[id] ?? { count: 0, lastAt: 0 }
    frecency.value[id] = { count: entry.count + 1, lastAt: Date.now() }
    saveFrecency()
}

// Quick actions — curated create-flow shortcuts. Click navigates to the host
// page (the destination is expected to read `?cmd=new` later for auto-open;
// for now it just lands the user one click away from the create button).
const QUICK_ACTIONS: SearchItem[] = [
    { kind: 'action', id: 'action:hire-employee',     label: 'Hire employee',        icon: 'ti-user-plus',        category: 'HRM',        route: '/hrm/employees?cmd=new',                  keywords: ['new', 'create', 'add', 'staff', 'onboard'] },
    { kind: 'action', id: 'action:request-leave',     label: 'Request leave',        icon: 'ti-calendar-plus',    category: 'HRM',        route: '/hrm/timeoff/leaves?cmd=new',             keywords: ['vacation', 'time off', 'absence', 'pto'] },
    { kind: 'action', id: 'action:new-customer',      label: 'New customer',         icon: 'ti-user-plus',        category: 'Sales',      route: '/sales/customers/new',                    keywords: ['create', 'add', 'client', 'account'] },
    { kind: 'action', id: 'action:new-quotation',     label: 'New quotation',        icon: 'ti-file-invoice',     category: 'Sales',      route: '/sales/quotations/new',                   keywords: ['quote', 'estimate', 'create', 'proposal'] },
    { kind: 'action', id: 'action:new-invoice',       label: 'New invoice',          icon: 'ti-receipt-2',        category: 'Sales',      route: '/sales/invoices?cmd=new',                 keywords: ['bill', 'create', 'charge'] },
    { kind: 'action', id: 'action:new-lead',          label: 'New lead',             icon: 'ti-target',           category: 'CRM',        route: '/crm/leads?cmd=new',                      keywords: ['prospect', 'create', 'inquiry'] },
    { kind: 'action', id: 'action:new-opportunity',   label: 'New opportunity',      icon: 'ti-trending-up',      category: 'CRM',        route: '/crm/opportunities?cmd=new',              keywords: ['deal', 'pipeline', 'create'] },
    { kind: 'action', id: 'action:new-product',       label: 'New product',          icon: 'ti-box',              category: 'Inventory',  route: '/inventory/products?cmd=new',             keywords: ['item', 'sku', 'create', 'add'] },
    { kind: 'action', id: 'action:new-po',            label: 'New purchase order',   icon: 'ti-truck-delivery',   category: 'Inventory',  route: '/inventory/purchase-orders/create',       keywords: ['po', 'supplier', 'create', 'procure'] },
    { kind: 'action', id: 'action:upload-document',   label: 'Upload document',      icon: 'ti-upload',           category: 'Documents',  route: '/edocuments?cmd=upload',                  keywords: ['file', 'new', 'pdf'] },
    { kind: 'action', id: 'action:new-project',       label: 'New project',          icon: 'ti-folders',          category: 'Projects',   route: '/projects?cmd=new',                       keywords: ['create'] },
    { kind: 'action', id: 'action:new-task',          label: 'New task',             icon: 'ti-checkbox',         category: 'Projects',   route: '/projects/tasks?cmd=new',                 keywords: ['todo', 'create'] },
    { kind: 'action', id: 'action:new-receipt',       label: 'Record receipt',       icon: 'ti-cash',             category: 'Finance',    route: '/finance/receipts?cmd=new',               keywords: ['payment', 'collect', 'cash'] },
    { kind: 'action', id: 'action:pos-register',      label: 'Open POS register',    icon: 'ti-cash-register',    category: 'POS',        route: '/pos/register',                           keywords: ['cashier', 'sell', 'checkout'] },
]

// Flatten navigation groups into page-kind search items.
const pageItems = computed<SearchItem[]>(() => {
    if (!props.navGroups) return []
    const flat: SearchItem[] = []
    const traverse = (items: any[], category: string) => {
        for (const item of items) {
            if (item.children) {
                traverse(item.children, `${category} / ${item.label}`)
            } else if (item.route && item.route !== '#') {
                flat.push({
                    kind: 'page',
                    id: `page:${item.route}`,
                    label: item.label,
                    icon: item.icon,
                    route: item.route,
                    category,
                })
            }
        }
    }
    for (const group of props.navGroups) {
        traverse(group.items, group.label)
    }
    return flat
})

// All searchable items = quick actions + page items. Action items appear
// first when scored identically — they're typically what someone wants
// when they type a verb like "new".
const allItems = computed<SearchItem[]>(() => [...QUICK_ACTIONS, ...pageItems.value])

// ------------ Scoring engine -------------------------------------------
const escapeRegex = (s: string) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')

const fuzzyScore = (q: string, target: string): number => {
    if (!q) return 0
    const t = target.toLowerCase()
    const query = q.toLowerCase()

    if (t === query) return 1000
    if (t.startsWith(query)) return 800 + Math.floor((query.length / t.length) * 100)

    // Word-boundary match — "emp" matches "Employee" even mid-string.
    if (new RegExp(`\\b${escapeRegex(query)}`, 'i').test(t)) return 600

    // Plain substring — earlier indices score higher.
    const idx = t.indexOf(query)
    if (idx >= 0) return Math.max(300, 550 - idx * 5)

    // Subsequence (typo-tolerant) — all query chars appear in order. Score
    // decreases with the number of skipped characters between matches.
    let ti = 0
    let qi = 0
    let gaps = 0
    let firstHit = -1
    while (ti < t.length && qi < query.length) {
        if (t[ti] === query[qi]) {
            if (firstHit === -1) firstHit = ti
            qi++
        } else if (qi > 0) {
            gaps++
        }
        ti++
    }
    if (qi === query.length) {
        // Cap subsequence score below substring; reward shorter spans.
        const span = ti - firstHit
        return Math.max(80, 250 - gaps * 8 - Math.max(0, span - query.length) * 2)
    }
    return 0
}

const scoreItem = (item: SearchItem, query: string): number => {
    const tokens = query.toLowerCase().trim().split(/\s+/).filter(Boolean)
    if (tokens.length === 0) return 0

    let total = 0
    for (const tok of tokens) {
        const labelS = fuzzyScore(tok, item.label)
        const catS = fuzzyScore(tok, item.category) * 0.4
        const kwS = item.keywords?.length
            ? Math.max(0, ...item.keywords.map(k => fuzzyScore(tok, k))) * 0.6
            : 0
        const tokScore = Math.max(labelS, catS, kwS)
        if (tokScore === 0) return 0
        total += tokScore
    }

    // Frecency boost — visit count (capped) + recency decay.
    const f = frecency.value[item.id]
    if (f) {
        const ageDays = (Date.now() - f.lastAt) / 86400000
        const recency = Math.max(0, 60 - ageDays * 1.5)
        const frequency = Math.min(80, f.count * 12)
        total += recency + frequency
    }

    // Tiny tiebreaker so actions beat pages on identical text scores.
    if (item.kind === 'action') total += 5

    return total
}

// ------------ Sections (visible) ---------------------------------------
const MAX_RESULTS = 18
const RECENT_LIMIT = 5
const SUGGEST_LIMIT = 6

const recentItems = computed<SearchItem[]>(() => {
    const entries = Object.entries(frecency.value)
        .sort((a, b) => b[1].lastAt - a[1].lastAt)
        .slice(0, RECENT_LIMIT)
    const out: SearchItem[] = []
    for (const [id] of entries) {
        const item = allItems.value.find(i => i.id === id)
        if (item) out.push(item)
    }
    return out
})

const visibleSections = computed<Section[]>(() => {
    const q = searchQuery.value.trim()
    const sections: Section[] = []

    if (!q) {
        // Empty state: Recent + Quick Actions + Suggested Pages.
        if (recentItems.value.length) {
            sections.push({ id: 'recent', label: 'Recent', icon: 'ti-history', items: recentItems.value })
        }
        const recentIds = new Set(recentItems.value.map(i => i.id))
        sections.push({
            id: 'actions',
            label: 'Quick actions',
            icon: 'ti-bolt',
            items: QUICK_ACTIONS.filter(a => !recentIds.has(a.id)).slice(0, SUGGEST_LIMIT),
        })
        sections.push({
            id: 'pages',
            label: 'Suggested pages',
            icon: 'ti-layout-grid',
            items: pageItems.value.filter(p => !recentIds.has(p.id)).slice(0, SUGGEST_LIMIT),
        })
    } else {
        // Search state: score everything, split by kind for clarity.
        const scored = allItems.value
            .map(item => ({ item, score: scoreItem(item, q) }))
            .filter(x => x.score > 0)
            .sort((a, b) => b.score - a.score)
            .slice(0, MAX_RESULTS)
            .map(x => x.item)

        const actions = scored.filter(i => i.kind === 'action')
        const pages = scored.filter(i => i.kind === 'page')
        if (actions.length) sections.push({ id: 'actions', label: 'Actions', icon: 'ti-bolt', items: actions })
        if (pages.length) sections.push({ id: 'pages', label: 'Pages', icon: 'ti-file', items: pages })
    }

    // Assign Ctrl/Cmd+digit shortcuts to the first 9 visible items across
    // all sections so the user can jump straight to a result.
    let n = 0
    for (const section of sections) {
        for (const item of section.items) {
            n += 1
            item.quickIndex = n <= 9 ? n : undefined
        }
    }
    return sections
})

const flatVisible = computed<SearchItem[]>(() =>
    visibleSections.value.flatMap(s => s.items),
)

const totalCount = computed(() => flatVisible.value.length)

// ------------ Keyboard navigation --------------------------------------
const navigateOptions = (dir: number) => {
    const items = flatVisible.value
    if (!items.length) return
    const cur = items.findIndex(i => i.id === activeId.value)
    const next = (cur + dir + items.length) % items.length
    activeId.value = items[next].id
    nextTick(() => {
        const el = document.querySelector<HTMLElement>(`[data-palette-id="${CSS.escape(activeId.value!)}"]`)
        el?.scrollIntoView({ block: 'nearest' })
    })
}

const selectActive = () => {
    const item = flatVisible.value.find(i => i.id === activeId.value)
    if (item) selectItem(item)
}

const selectItem = (item: SearchItem) => {
    trackVisit(item.id)
    emit('update:modelValue', false)
    router.push(item.route)
}

// Alt/Option+1..9 quick-pick. Listened on the search input so it doesn't
// fire outside the palette. Plain 1..9 still types into the search box.
// Chosen over Ctrl/⌘ because the browser owns those for tab switching.
const onInputKeydown = (e: KeyboardEvent) => {
    if (!e.altKey) return
    if (e.key < '1' || e.key > '9') return
    const idx = Number(e.key) - 1
    const item = flatVisible.value[idx]
    if (!item) return
    e.preventDefault()
    selectItem(item)
}

// ------------ Lifecycle / watchers -------------------------------------
watch(searchQuery, () => {
    activeId.value = flatVisible.value[0]?.id ?? null
})

watch(() => props.modelValue, async (val) => {
    if (val) {
        searchQuery.value = ''
        activeId.value = flatVisible.value[0]?.id ?? null
        await nextTick()
        searchInput.value?.focus()
    }
})

onMounted(() => {
    if (typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform)) {
        shortcutLabel.value = '⌥'
    }
    loadFrecency()
    activeId.value = flatVisible.value[0]?.id ?? null
})
</script>

<style scoped>
.backdrop-enter-active,
.backdrop-leave-active {
    transition: opacity 0.2s ease;
}

.backdrop-enter-from,
.backdrop-leave-to {
    opacity: 0;
}

.backdrop-enter-active .max-w-2xl,
.backdrop-leave-active .max-w-2xl {
    transition: transform 0.2s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s ease;
}

.backdrop-enter-from .max-w-2xl,
.backdrop-leave-to .max-w-2xl {
    transform: scale(0.95);
    opacity: 0;
}
</style>
