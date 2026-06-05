<template>
    <NuxtLayout name="shop">
        <div class="space-y-5">
            <header>
                <h1 class="text-xl font-semibold text-(--text-heading) leading-tight">{{ activeCategoryLabel }}</h1>
                <p class="text-xs text-(--text-muted) mt-1">{{ pageHint }}</p>
            </header>

            <section
                class="catalog-toolbar sticky top-16 z-20 -mx-4 sm:-mx-6 px-4 sm:px-6 py-3 bg-(--bg-layout)/90 backdrop-blur">
                <div class="flex items-center gap-3 flex-wrap">
                    <div class="relative flex-1 min-w-[220px] max-w-md">
                        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
                        <input v-model="searchDraft" type="search" placeholder="Search products, SKU..."
                            class="search-input w-full pl-9 pr-9 py-2 text-xs rounded-lg bg-(--bg-card) border border-(--border-color) text-(--text-heading) placeholder:text-(--text-muted) focus:outline-none focus:border-(--color-primary) focus:ring-2 focus:ring-(--color-primary)/20"
                            @input="onSearch" @keyup.enter="commitSearch" @keyup.escape="clearSearch" />
                        <button v-if="searchDraft" type="button"
                            class="absolute right-2 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full inline-flex items-center justify-center text-(--text-muted) hover:bg-(--bg-muted) hover:text-(--text-heading)"
                            aria-label="Clear search" @click="clearSearch">
                            <i class="ti ti-x text-[12px]" />
                        </button>
                    </div>

                    <div class="ml-auto flex items-center gap-2 flex-wrap max-sm:justify-center">
                        <div class="segmented" role="group" aria-label="Stock filter">
                            <button type="button" class="seg-btn"
                                :class="{ active: stockFilter === 'all' }"
                                :aria-pressed="stockFilter === 'all'"
                                @click="setStockFilter('all')">
                                <i class="ti ti-list" /> All
                            </button>
                            <button type="button" class="seg-btn"
                                :class="{ active: stockFilter === 'in' }"
                                :aria-pressed="stockFilter === 'in'"
                                @click="setStockFilter('in')">
                                <i class="ti ti-circle-check" /> In stock
                            </button>
                            <button type="button" class="seg-btn"
                                :class="{ active: stockFilter === 'out' }"
                                :aria-pressed="stockFilter === 'out'"
                                @click="setStockFilter('out')">
                                <i class="ti ti-circle-x" /> Out
                            </button>
                        </div>

                        <div class="filter-select" :class="{ active: !!categorySelect }">
                            <i class="ti ti-tag text-(--text-muted) text-sm" />
                            <select v-model="categorySelect" @change="onCategorySelect"
                                aria-label="Filter by category">
                                <option value="">All categories</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">
                                    {{ c.name }} ({{ c.productCount }})
                                </option>
                            </select>
                            <i class="ti ti-chevron-down text-(--text-muted) text-[10px] pointer-events-none" />
                        </div>

                        <div class="filter-select" :class="{ active: sort !== 'featured' }">
                            <i class="ti ti-arrows-sort text-(--text-muted) text-sm" />
                            <select v-model="sort" @change="onFilterChange"
                                aria-label="Sort products">
                                <option value="featured">Featured</option>
                                <option value="newest">Newest</option>
                                <option value="price_asc">Price: Low to High</option>
                                <option value="price_desc">Price: High to Low</option>
                            </select>
                            <i class="ti ti-chevron-down text-(--text-muted) text-[10px] pointer-events-none" />
                        </div>
                    </div>
                </div>

                <div v-if="activeFilterChips.length > 0" class="flex items-center gap-2 flex-wrap max-sm:justify-center pt-3">
                    <span class="text-xxs uppercase tracking-wider text-(--text-muted) font-semibold">
                        Filtered by
                    </span>
                    <button v-for="f in activeFilterChips" :key="f.key" type="button"
                        class="active-filter-chip"
                        @click="f.remove">
                        <span class="text-(--text-muted)">{{ f.label }}</span>
                        <span class="text-(--text-heading) font-semibold">{{ f.value }}</span>
                        <i class="ti ti-x text-[10px] text-(--text-muted)" />
                    </button>
                    <button v-if="activeFilterChips.length > 1" type="button"
                        class="text-xxs text-(--color-primary) hover:underline ml-1"
                        @click="clearAllFilters">
                        Clear all
                    </button>
                </div>
            </section>

            <div v-if="!loadingProducts" class="flex items-center justify-between text-xxs text-(--text-muted)">
                <span>{{ resultsSummary }}</span>
                <span v-if="pagination && pagination.totalPages > 1" class="font-mono">
                    Page {{ page }} / {{ pagination.totalPages }}
                </span>
            </div>

            <div v-if="loadingProducts" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                <div v-for="i in 12" :key="i"
                    class="glass-card rounded-2xl overflow-hidden flex flex-col">
                    <div class="aspect-video bg-(--bg-muted) animate-pulse" />
                    <div class="p-3 space-y-2">
                        <div class="h-2 w-1/3 bg-(--bg-muted) rounded animate-pulse" />
                        <div class="h-3 w-4/5 bg-(--bg-muted) rounded animate-pulse" />
                        <div class="h-3 w-1/3 bg-(--bg-muted) rounded animate-pulse" />
                    </div>
                </div>
            </div>

            <div v-else-if="products.length === 0"
                class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-mood-empty text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No matches</h4>
                <p class="text-xs text-(--text-muted) mt-1">
                    {{ stockFilter === 'out'
                        ? 'No out-of-stock items in this filter.'
                        : 'Try clearing a filter or widening the search.' }}
                </p>
                <button v-if="hasActiveFilters" class="btn btn-soft-primary text-xs mt-4 inline-flex items-center gap-2"
                    @click="clearAllFilters">
                    <i class="ti ti-restore" /> Reset filters
                </button>
            </div>

            <section v-else class="space-y-6">
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                    <ProductCard v-for="p in products" :key="p.id" :product="p" />
                </div>

                <div v-if="pagination && pagination.totalPages > 1"
                    class="flex items-center justify-center gap-2 pt-2">
                    <button class="btn btn-ghost text-xs" :disabled="page <= 1" @click="setPage(page - 1)">
                        <i class="ti ti-chevron-left" /> Prev
                    </button>
                    <div class="flex gap-1">
                        <button v-for="n in pageNumbers" :key="n"
                            :class="['btn text-xs w-9', n === page ? 'btn-primary' : 'btn-ghost']"
                            @click="setPage(n)">
                            {{ n }}
                        </button>
                    </div>
                    <button class="btn btn-ghost text-xs" :disabled="page >= pagination.totalPages"
                        @click="setPage(page + 1)">
                        Next <i class="ti ti-chevron-right" />
                    </button>
                </div>
            </section>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useShop, type StorefrontProduct, type StorefrontCategory, type CatalogSort } from '~/composables/useShop'

definePageMeta({ layout: false })

useHead({ title: 'Catalog | Storefront' })

const PER_PAGE = 12

const route = useRoute()
const router = useRouter()
const shop = useShop()

const loadingProducts = ref(true)
const products = ref<StorefrontProduct[]>([])
const categories = ref<StorefrontCategory[]>([])
const pagination = ref<{ page: number; limit: number; total: number; totalPages: number } | null>(null)

const searchDraft = ref('')
const search = ref('')
const sort = ref<CatalogSort>('featured')
const stockFilter = ref<'all' | 'in' | 'out'>('all')
const categorySelect = ref<string>('')
const page = ref(1)

const hasActiveFilters = computed(() =>
    !!categorySelect.value ||
    stockFilter.value !== 'all' ||
    !!search.value ||
    sort.value !== 'featured'
)

const activeCategoryLabel = computed(() => {
    if (!categorySelect.value) return 'All products'
    return categories.value.find(c => c.id === categorySelect.value)?.name ?? 'Category'
})

const pageHint = computed(() => {
    const total = pagination.value?.total ?? products.value.length
    const noun = total === 1 ? 'product' : 'products'
    if (hasActiveFilters.value) return `${total.toLocaleString()} ${noun} match your filters.`
    return `Browse the full catalog. ${total.toLocaleString()} ${noun} available.`
})

const resultsSummary = computed(() => {
    const total = pagination.value?.total ?? 0
    if (total === 0) return ''
    const limit = pagination.value?.limit ?? PER_PAGE
    const start = (page.value - 1) * limit + 1
    const end = Math.min(start + products.value.length - 1, total)
    return `Showing ${start.toLocaleString()}-${end.toLocaleString()} of ${total.toLocaleString()}`
})

interface ActiveChip {
    key: string
    label: string
    value: string
    remove: () => void
}

const STOCK_LABELS: Record<string, string> = { in: 'In stock', out: 'Out of stock' }
const SORT_LABELS: Record<string, string> = {
    newest: 'Newest',
    price_asc: 'Price: Low to High',
    price_desc: 'Price: High to Low',
}

const activeFilterChips = computed<ActiveChip[]>(() => {
    const chips: ActiveChip[] = []
    if (categorySelect.value) {
        const c = categories.value.find(x => x.id === categorySelect.value)
        chips.push({
            key: 'category',
            label: 'Category',
            value: c?.name ?? 'Selected',
            remove: () => {
                categorySelect.value = ''
                onCategorySelect()
            },
        })
    }
    if (stockFilter.value !== 'all') {
        chips.push({
            key: 'stock',
            label: 'Stock',
            value: STOCK_LABELS[stockFilter.value] ?? stockFilter.value,
            remove: () => setStockFilter('all'),
        })
    }
    if (search.value) {
        chips.push({
            key: 'search',
            label: 'Search',
            value: `"${search.value}"`,
            remove: clearSearch,
        })
    }
    if (sort.value !== 'featured') {
        chips.push({
            key: 'sort',
            label: 'Sort',
            value: SORT_LABELS[sort.value] ?? sort.value,
            remove: () => {
                sort.value = 'featured'
                onFilterChange()
            },
        })
    }
    return chips
})

const pageNumbers = computed(() => {
    const total = pagination.value?.totalPages ?? 1
    const cur = page.value
    if (total <= 5) return Array.from({ length: total }, (_, i) => i + 1)
    const win = new Set<number>([1, total, cur, cur - 1, cur + 1])
    return Array.from(win).filter(n => n >= 1 && n <= total).sort((a, b) => a - b)
})

function setStockFilter(v: 'all' | 'in' | 'out') {
    stockFilter.value = v
    page.value = 1
    syncToUrl()
    loadProducts()
}

function setPage(n: number) {
    const total = pagination.value?.totalPages ?? 1
    page.value = Math.max(1, Math.min(total, n))
    syncToUrl()
    loadProducts()
    if (typeof window !== 'undefined') window.scrollTo({ top: 0, behavior: 'smooth' })
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
function onSearch() {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(commitSearch, 250)
}

function commitSearch() {
    search.value = searchDraft.value
    page.value = 1
    syncToUrl()
    loadProducts()
}

function clearSearch() {
    if (!searchDraft.value && !search.value) return
    searchDraft.value = ''
    search.value = ''
    page.value = 1
    syncToUrl()
    loadProducts()
}

function onFilterChange() {
    page.value = 1
    syncToUrl()
    loadProducts()
}

function onCategorySelect() {
    page.value = 1
    syncToUrl()
    loadProducts()
}

function clearAllFilters() {
    stockFilter.value = 'all'
    categorySelect.value = ''
    search.value = ''
    searchDraft.value = ''
    sort.value = 'featured'
    page.value = 1
    syncToUrl()
    loadProducts()
}

function readFromUrl() {
    const q = route.query
    const stock = String(q.in_stock ?? 'all')
    stockFilter.value = (['all', 'in', 'out'].includes(stock) ? stock : 'all') as 'all' | 'in' | 'out'

    const ids = q.category_ids
    if (Array.isArray(ids) && ids[0]) categorySelect.value = String(ids[0])
    else if (typeof ids === 'string') categorySelect.value = ids.split(',')[0] || ''
    else if (typeof q.category_id === 'string') categorySelect.value = q.category_id
    else categorySelect.value = ''

    const s = String(q.sort ?? 'featured')
    sort.value = (['featured', 'price_asc', 'price_desc', 'newest'].includes(s) ? s : 'featured') as CatalogSort

    page.value = Math.max(1, Number(q.page) || 1)
    search.value = typeof q.search === 'string' ? q.search : ''
    searchDraft.value = search.value
}

function syncToUrl() {
    const q: Record<string, any> = {}
    if (categorySelect.value) q.category_ids = categorySelect.value
    if (stockFilter.value !== 'all') q.in_stock = stockFilter.value
    if (sort.value !== 'featured') q.sort = sort.value
    if (page.value > 1) q.page = page.value
    if (search.value) q.search = search.value
    router.replace({ query: q })
}

async function loadCategories() {
    try {
        const res = await shop.catalog.categories()
        categories.value = res.data ?? []
    } catch {
        categories.value = []
    }
}

async function loadProducts() {
    loadingProducts.value = true
    try {
        const res = await shop.catalog.list({
            search: search.value || undefined,
            category_ids: categorySelect.value ? [categorySelect.value] : undefined,
            in_stock: stockFilter.value,
            sort: sort.value,
            page: page.value,
            limit: PER_PAGE,
        })
        products.value = res.data ?? []
        pagination.value = res.pagination ?? null
    } catch {
        products.value = []
        pagination.value = null
    } finally {
        loadingProducts.value = false
    }
}

watch(() => route.query, readFromUrl)

onMounted(async () => {
    readFromUrl()
    await Promise.all([loadCategories(), loadProducts()])
})
</script>

<style scoped>
/* Segmented control for the stock filter (mutually exclusive states) */
.segmented {
    display: inline-flex;
    align-items: center;
    padding: 3px;
    border-radius: 999px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
}

.seg-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 12px;
    border-radius: 999px;
    border: 0;
    background: transparent;
    font-size: 11px;
    color: var(--text-body);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}

.seg-btn:hover {
    color: var(--text-heading);
}

.seg-btn.active {
    background: rgb(var(--color-primary-rgb) / 0.12);
    color: var(--color-primary);
    box-shadow: inset 0 0 0 1px rgb(var(--color-primary-rgb) / 0.25);
}

/* Compact native-select restyled as a filter button */
.filter-select {
    position: relative;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    height: 32px;
    padding: 0 10px 0 10px;
    border-radius: 999px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-body);
    font-size: 11px;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.filter-select:hover {
    background: var(--bg-muted);
    color: var(--text-heading);
}

.filter-select.active {
    background: rgb(var(--color-primary-rgb) / 0.08);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}

.filter-select.active i.ti {
    color: var(--color-primary);
}

.filter-select select {
    appearance: none;
    -webkit-appearance: none;
    -moz-appearance: none;
    background: transparent;
    border: 0;
    outline: none;
    font: inherit;
    color: inherit;
    padding-right: 4px;
    max-width: 140px;
    cursor: pointer;
}

.filter-select select:focus {
    outline: none;
}

/* Search input — visible focus ring */
.search-input {
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

/* Removable active-filter pill (sits below the toolbar when filters apply) */
.active-filter-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    border-radius: 999px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    font-size: 11px;
    color: var(--text-body);
    cursor: pointer;
    transition: background 0.12s ease, border-color 0.12s ease;
}

.active-filter-chip:hover {
    background: rgb(var(--color-danger-rgb) / 0.08);
    border-color: rgb(var(--color-danger-rgb) / 0.35);
}

.active-filter-chip:hover .ti-x {
    color: var(--color-danger);
}

/* Sticky toolbar — sits below shop layout's sticky h-16 header */
.catalog-toolbar {
    /* `top-16` from utilities; extra ring keeps the divider crisp over scrolling cards */
    box-shadow: inset 0 -1px 0 0 var(--border-color);
}
</style>
