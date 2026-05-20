<template>
  <NuxtLayout name="default">
    <div class="space-y-6">
      <!-- Page header -->
      <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
          <h1 class="text-xl font-semibold">Product catalog</h1>
          <p class="text-xs text-(--text-muted) mt-1">High-density inventory canvas — design.md §5 Product Management.</p>
        </div>
        <div class="flex items-center gap-2">
          <button class="btn btn-ghost text-xs"><i class="ti ti-download" />Export</button>
          <button class="btn btn-danger text-xs" @click="onAdd">
            <i class="ti ti-plus" />Add product
          </button>
        </div>
      </header>

      <!-- Metric row §5.1 -->
      <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
        <MetricCard variant="primary"   icon="ti-package"         label="Products"   value="2,240"     sub="Active listings"  sub-value="980"     delta="+24 New" />
        <MetricCard variant="secondary" icon="ti-shopping-cart"   label="Orders"     value="8,014"     sub="Total orders"     sub-value="105K"    delta="+120 New" />
        <MetricCard variant="success"   icon="ti-currency-dollar" label="Sales"      value="$17,854"   sub="Today's sales"    sub-value="$156K"   delta="+8.2%" />
        <MetricCard variant="info"      icon="ti-users"           label="Customers"  value="3,209"     sub="Total customers"  sub-value="58,320"  delta="+36 New" />
        <MetricCard variant="warning"   icon="ti-chart-bar"       label="Revenue"    value="$3.50M"    sub="Total revenue"    sub-value="$12.8M"  delta="-4.5%" delta-direction="down" />
      </section>

      <!-- Filter toolbar §5.2 -->
      <section class="glass-card rounded-xl p-4">
        <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
          <!-- Search -->
          <div class="relative md:col-span-4">
            <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
            <input v-model="search" type="search" placeholder="Search product name..." class="form-control pl-9" />
          </div>

          <!-- Category -->
          <div class="relative md:col-span-2">
            <i class="ti ti-tag absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
            <select v-model="category" class="form-control pl-9 appearance-none">
              <option value="All">All categories</option>
              <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>

          <!-- Status -->
          <div class="relative md:col-span-2">
            <i class="ti ti-activity absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
            <select v-model="status" class="form-control pl-9 appearance-none">
              <option value="All">All status</option>
              <option v-for="s in statuses" :key="s" :value="s">{{ s }}</option>
            </select>
          </div>

          <!-- Price range -->
          <div class="relative md:col-span-2">
            <i class="ti ti-currency-dollar absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
            <select v-model="priceRange" class="form-control pl-9 appearance-none">
              <option value="All">All prices</option>
              <option value="0-50">$0 – $50</option>
              <option value="51-150">$51 – $150</option>
              <option value="151-500">$151 – $500</option>
              <option value="500+">$500+</option>
            </select>
          </div>

          <!-- View toggle -->
          <div class="md:col-span-2 flex items-center justify-end gap-1 border border-(--border-color) rounded-lg p-1 bg-(--bg-muted)">
            <button
              type="button"
              class="flex-1 inline-flex items-center justify-center gap-1.5 px-2 py-1 rounded text-xs font-semibold transition-colors"
              :class="view === 'list' ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted)'"
              @click="view = 'list'"
            >
              <i class="ti ti-list-check" />List
            </button>
            <button
              type="button"
              class="flex-1 inline-flex items-center justify-center gap-1.5 px-2 py-1 rounded text-xs font-semibold transition-colors"
              :class="view === 'grid' ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted)'"
              @click="view = 'grid'"
            >
              <i class="ti ti-layout-grid" />Grid
            </button>
          </div>
        </div>
      </section>

      <!-- Data table §5.3 -->
      <section v-if="view === 'list'" class="glass-card rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
          <table class="w-full text-left">
            <thead>
              <tr class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                <th class="px-4 py-3 w-10">
                  <input type="checkbox" v-model="allSelected" @change="toggleSelectAll" class="rounded border-(--border-color)" />
                </th>
                <th class="px-4 py-3 font-semibold">Product info</th>
                <th class="px-4 py-3 font-semibold font-mono">SKU</th>
                <th class="px-4 py-3 font-semibold">Category</th>
                <th class="px-4 py-3 font-semibold text-right font-mono">Stock</th>
                <th class="px-4 py-3 font-semibold text-right font-mono">Price</th>
                <th class="px-4 py-3 font-semibold">Rating</th>
                <th class="px-4 py-3 font-semibold">Status</th>
                <th class="px-4 py-3 font-semibold text-center">Actions</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-(--border-color)">
              <tr
                v-for="p in paginated"
                :key="p.id"
                class="hover:bg-(--bg-muted)/60 transition-colors group/row"
              >
                <td class="px-4 py-3">
                  <input type="checkbox" :value="p.id" v-model="selected" class="rounded border-(--border-color)" />
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-3 min-w-[220px]">
                    <div class="w-12 h-12 rounded-lg bg-(--bg-muted) border border-(--border-color) flex items-center justify-center text-(--color-primary)">
                      <i class="ti ti-photo text-lg" />
                    </div>
                    <div class="min-w-0">
                      <p class="text-xs font-semibold text-(--text-heading) truncate group-hover/row:text-(--color-primary) transition-colors">{{ p.title }}</p>
                      <p class="text-[10px] text-(--text-muted) truncate">by: {{ p.brand }}</p>
                    </div>
                  </div>
                </td>
                <td class="px-4 py-3 font-mono text-xxs text-(--text-body)">{{ p.sku }}</td>
                <td class="px-4 py-3">
                  <Badge variant="secondary">{{ p.category }}</Badge>
                </td>
                <td class="px-4 py-3 text-right font-mono text-xs" :class="p.stock === 0 ? 'text-(--color-danger) font-bold' : 'text-(--text-body)'">{{ p.stock }}</td>
                <td class="px-4 py-3 text-right font-mono text-xs font-semibold text-(--text-heading)">${{ p.price.toFixed(2) }}</td>
                <td class="px-4 py-3">
                  <div class="flex items-center gap-0.5 text-(--color-warning) text-sm">
                    <i v-for="n in 5" :key="n" class="ti" :class="n <= Math.round(p.rating) ? 'ti-star-filled' : 'ti-star'" />
                    <span class="text-xxs text-(--text-muted) font-mono ml-1">{{ p.rating.toFixed(1) }}</span>
                  </div>
                </td>
                <td class="px-4 py-3">
                  <Badge :variant="statusVariant(p.status)">{{ p.status }}</Badge>
                </td>
                <td class="px-4 py-3">
                  <div class="flex items-center justify-center gap-1">
                    <button class="action-btn" title="View" @click="action('View', p.title)"><i class="ti ti-eye" /></button>
                    <button class="action-btn" title="Edit" @click="action('Edit', p.title)"><i class="ti ti-pencil" /></button>
                    <button class="action-btn action-btn-danger" title="Delete" @click="action('Delete', p.title)"><i class="ti ti-trash" /></button>
                  </div>
                </td>
              </tr>
              <tr v-if="filtered.length === 0">
                <td colspan="9" class="py-12 text-center text-xs text-(--text-muted)">No products match the current filters.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 border-t border-(--border-color) text-xxs text-(--text-muted)">
          <span>
            Showing <span class="font-mono font-bold text-(--text-heading)">{{ startIndex + 1 }}</span>
            to <span class="font-mono font-bold text-(--text-heading)">{{ Math.min(endIndex, filtered.length) }}</span>
            of <span class="font-mono font-bold text-(--text-heading)">{{ filtered.length }}</span>
          </span>
          <div class="flex items-center gap-1">
            <button class="action-btn" :disabled="page === 1" @click="page = Math.max(1, page - 1)"><i class="ti ti-chevron-left" /></button>
            <button
              v-for="pg in totalPages"
              :key="pg"
              class="w-7 h-7 rounded text-xxs font-mono font-bold flex items-center justify-center border transition-colors"
              :class="pg === page ? 'bg-(--color-primary) text-white border-(--color-primary)' : 'border-(--border-color) text-(--text-body) hover:bg-(--bg-muted)'"
              @click="page = pg"
            >
              {{ pg }}
            </button>
            <button class="action-btn" :disabled="page === totalPages || totalPages === 0" @click="page = Math.min(totalPages, page + 1)"><i class="ti ti-chevron-right" /></button>
          </div>
        </div>
      </section>

      <!-- Grid view -->
      <section v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
        <div v-for="p in filtered" :key="p.id" class="glass-card rounded-xl overflow-hidden group flex flex-col">
          <div class="aspect-[4/3] bg-(--bg-muted) flex items-center justify-center text-(--color-primary)">
            <i class="ti ti-photo text-5xl opacity-40" />
          </div>
          <div class="p-4 space-y-2 flex-1 flex flex-col">
            <div class="flex items-center justify-between gap-2">
              <Badge variant="secondary">{{ p.category }}</Badge>
              <Badge :variant="statusVariant(p.status)">{{ p.status }}</Badge>
            </div>
            <h3 class="text-sm font-semibold text-(--text-heading) group-hover:text-(--color-primary) transition-colors">{{ p.title }}</h3>
            <p class="text-xxs text-(--text-muted)">by: {{ p.brand }} · <span class="font-mono">{{ p.sku }}</span></p>
            <div class="mt-auto flex items-center justify-between pt-2">
              <span class="font-mono font-bold text-(--text-heading)">${{ p.price.toFixed(2) }}</span>
              <div class="flex items-center gap-0.5 text-(--color-warning) text-xs">
                <i v-for="n in 5" :key="n" class="ti" :class="n <= Math.round(p.rating) ? 'ti-star-filled' : 'ti-star'" />
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'
import { useToast } from '~/composables/useToast'

const toast = useToast()

interface Product {
  id: number
  title: string
  brand: string
  sku: string
  category: string
  stock: number
  price: number
  rating: number
  status: 'Published' | 'Pending' | 'Out of Stock'
}

const view = ref<'list' | 'grid'>('list')
const search = ref('')
const category = ref('All')
const status = ref('All')
const priceRange = ref('All')
const page = ref(1)
const pageSize = 6
const selected = ref<number[]>([])
const allSelected = ref(false)

const categories = ['Electronics', 'Fashion', 'Home', 'Sports', 'Beauty', 'Furniture', 'Gaming']
const statuses = ['Published', 'Pending', 'Out of Stock']

const products = ref<Product[]>([
  { id: 1,  title: 'Wireless Earbuds',          brand: 'AuroraSound',  sku: 'WB-10245', category: 'Electronics', stock: 56,  price: 59.99,  rating: 4.6, status: 'Published' },
  { id: 2,  title: 'Smart LED Desk Lamp',       brand: 'BrightLite',   sku: 'SL-89012', category: 'Home',        stock: 32,  price: 39.49,  rating: 4.2, status: 'Pending' },
  { id: 3,  title: "Men's Running Shoes",       brand: 'ActiveWear',   sku: 'RS-20450', category: 'Fashion',     stock: 120, price: 89.00,  rating: 4.8, status: 'Published' },
  { id: 4,  title: 'Fitness Tracker Watch',     brand: 'FitPulse',     sku: 'FT-67123', category: 'Sports',      stock: 78,  price: 49.95,  rating: 4.4, status: 'Published' },
  { id: 5,  title: 'Gaming Mouse RGB',          brand: 'HyperClick',   sku: 'GM-72109', category: 'Gaming',      stock: 120, price: 29.99,  rating: 4.5, status: 'Published' },
  { id: 6,  title: 'Modern Lounge Chair',       brand: 'UrbanLiving',  sku: 'FC-31220', category: 'Furniture',   stock: 0,   price: 199.00, rating: 4.1, status: 'Out of Stock' },
  { id: 7,  title: 'Plush Toy Bear',            brand: 'Softies',      sku: 'TY-00788', category: 'Beauty',      stock: 150, price: 15.99,  rating: 4.7, status: 'Published' },
  { id: 8,  title: '55" Ultra HD Smart TV',     brand: 'ViewMaster',   sku: 'TV-5588',  category: 'Electronics', stock: 64,  price: 499.00, rating: 4.9, status: 'Published' },
  { id: 9,  title: 'Ergonomic Office Chair',    brand: 'SteelBase',    sku: 'OC-22104', category: 'Furniture',   stock: 18,  price: 249.00, rating: 4.3, status: 'Published' },
  { id: 10, title: 'Portable Bluetooth Speaker',brand: 'SoundWave',    sku: 'BS-90921', category: 'Electronics', stock: 45,  price: 79.99,  rating: 4.4, status: 'Published' },
  { id: 11, title: 'Cotton Hoodie',             brand: 'ActiveWear',   sku: 'CH-44099', category: 'Fashion',     stock: 88,  price: 49.50,  rating: 4.5, status: 'Pending' },
  { id: 12, title: 'Mechanical Keyboard',       brand: 'HyperClick',   sku: 'KB-66012', category: 'Gaming',      stock: 22,  price: 129.00, rating: 4.7, status: 'Published' }
])

const inRange = (price: number) => {
  switch (priceRange.value) {
    case '0-50':   return price <= 50
    case '51-150': return price > 50 && price <= 150
    case '151-500':return price > 150 && price <= 500
    case '500+':   return price > 500
    default:       return true
  }
}

const filtered = computed(() => products.value.filter(p => {
  const matchSearch = !search.value ||
    p.title.toLowerCase().includes(search.value.toLowerCase()) ||
    p.brand.toLowerCase().includes(search.value.toLowerCase()) ||
    p.sku.toLowerCase().includes(search.value.toLowerCase())
  const matchCategory = category.value === 'All' || p.category === category.value
  const matchStatus   = status.value === 'All' || p.status === status.value
  return matchSearch && matchCategory && matchStatus && inRange(p.price)
}))

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / pageSize)))
const startIndex = computed(() => (page.value - 1) * pageSize)
const endIndex   = computed(() => startIndex.value + pageSize)
const paginated  = computed(() => filtered.value.slice(startIndex.value, endIndex.value))

const statusVariant = (s: Product['status']): 'success' | 'warning' | 'danger' =>
  s === 'Published' ? 'success' : s === 'Pending' ? 'warning' : 'danger'

const toggleSelectAll = () => {
  selected.value = allSelected.value ? paginated.value.map(p => p.id) : []
}

const onAdd = () => toast.info('Add product modal', 'UI scaffold in progress.')
const action = (kind: string, title: string) => toast.info(`${kind} action`, `"${title}" — demo handler.`)
</script>

<style scoped>
.action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 28px;
  height: 28px;
  border-radius: 6px;
  color: var(--text-body);
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  cursor: pointer;
  transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
.action-btn:hover { background: var(--bg-muted); color: var(--color-primary); border-color: rgb(var(--color-primary-rgb) / 0.4); }
.action-btn:disabled { opacity: 0.4; cursor: not-allowed; }
.action-btn-danger:hover { color: var(--color-danger); border-color: rgb(var(--color-danger-rgb) / 0.4); }
</style>
