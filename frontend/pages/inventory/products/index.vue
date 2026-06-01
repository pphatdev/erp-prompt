<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Page header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Product catalog</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Hardware + software products available for quotations
                        and orders.</p>
                </div>
                <div class="flex items-center gap-2">
                    <button class="btn btn-primary text-xs" @click="openCreate">
                        <i class="ti ti-plus" />Add product
                    </button>
                </div>
            </header>

            <!-- Metric row — computed from the actual loaded catalogue. -->
            <section class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4">
                <MetricCard variant="primary" icon="ti-package" label="Products" :value="fmtInt(metrics.total)"
                    sub="Active" :sub-value="fmtInt(metrics.active)" />
                <MetricCard variant="info" icon="ti-device-laptop" label="Hardware" :value="fmtInt(metrics.hardware)"
                    sub="Software" :sub-value="fmtInt(metrics.software)" />
                <MetricCard variant="warning" icon="ti-alert-triangle" label="Low stock"
                    :value="fmtInt(metrics.lowStock)" sub="Min thresholds" sub-value="hit" />
                <MetricCard variant="danger" icon="ti-package-off" label="Out of stock"
                    :value="fmtInt(metrics.outOfStock)" sub="Hardware only" sub-value="" />
                <MetricCard variant="success" icon="ti-currency-dollar" label="Inventory $"
                    :value="fmtMoney(metrics.inventoryValue)" sub="Avg unit price"
                    :sub-value="fmtMoney(metrics.avgUnit)" />
            </section>

            <!-- Filter toolbar -->
            <section class="glass-card rounded-xl p-4">
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <div class="relative md:col-span-4">
                        <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                        <input v-model="search" type="search" placeholder="Search by name or SKU..."
                            class="form-control pl-9" />
                    </div>

                    <div class="relative md:col-span-2">
                        <i
                            class="ti ti-category absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
                        <select v-model="filterType" class="form-control pl-9 appearance-none">
                            <option value="all">All types</option>
                            <option value="hardware">Hardware</option>
                            <option value="software">Software</option>
                        </select>
                    </div>

                    <div class="relative md:col-span-2">
                        <i
                            class="ti ti-tag absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
                        <select v-model="filterCategory" class="form-control pl-9 appearance-none">
                            <option value="">All categories</option>
                            <option value="__none__">— Uncategorised —</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ categoryIndentLabel(c) }}
                            </option>
                        </select>
                    </div>

                    <div class="relative md:col-span-2">
                        <i
                            class="ti ti-activity absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
                        <select v-model="filterStatus" class="form-control pl-9 appearance-none">
                            <option value="all">All status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="low_stock">Low stock</option>
                            <option value="out_of_stock">Out of stock</option>
                        </select>
                    </div>

                    <div
                        class="md:col-span-2 flex items-center justify-end gap-1 border border-(--border-color) rounded-lg p-1 bg-(--bg-muted)">
                        <button type="button"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-2 py-1 rounded text-xs font-semibold transition-colors"
                            :class="view === 'list' ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted)'"
                            @click="view = 'list'">
                            <i class="ti ti-list-check" />List
                        </button>
                        <button type="button"
                            class="flex-1 inline-flex items-center justify-center gap-1.5 px-2 py-1 rounded text-xs font-semibold transition-colors"
                            :class="view === 'grid' ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted)'"
                            @click="view = 'grid'">
                            <i class="ti ti-layout-grid" />Grid
                        </button>
                    </div>
                </div>
            </section>

            <!-- Loading / empty -->
            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span
                    class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading catalogue...</span>
            </div>

            <div v-else-if="filtered.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-package-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No products</h4>
                <p class="text-xs text-(--text-muted) mt-1">Try adjusting filters or add a new product.</p>
            </div>

            <!-- List view -->
            <section v-else-if="view === 'list'" class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr
                                class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                <th class="px-4 py-3 font-semibold">Product</th>
                                <th class="px-4 py-3 font-semibold font-mono">SKU</th>
                                <th class="px-4 py-3 font-semibold">Type</th>
                                <th class="px-4 py-3 font-semibold">Category</th>
                                <th class="px-4 py-3 font-semibold text-right font-mono">Stock</th>
                                <th class="px-4 py-3 font-semibold text-right font-mono">Min</th>
                                <th class="px-4 py-3 font-semibold text-right font-mono">Price</th>
                                <th class="px-4 py-3 font-semibold">Variants</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-color)">
                            <tr v-for="p in paginated" :key="p.id"
                                class="hover:bg-(--bg-muted)/60 transition-colors group/row">
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-3 min-w-[220px]">
                                        <div class="w-10 h-10 rounded-lg flex items-center justify-center text-(--color-primary) overflow-hidden"
                                            :class="p.product_type === 'software' ? 'bg-(--color-primary-subtle)' : 'bg-(--bg-muted) border border-(--border-color)'">
                                            <img v-if="p.image_url" :src="p.image_url" :alt="p.name"
                                                class="w-full h-full object-cover" />
                                            <i v-else
                                                :class="['ti', p.product_type === 'software' ? 'ti-cloud' : 'ti-device-laptop']" />
                                        </div>
                                        <div class="min-w-0">
                                            <p
                                                class="text-xs font-semibold text-(--text-heading) truncate group-hover/row:text-(--color-primary) transition-colors">
                                                {{ p.name }}</p>
                                            <p class="text-[10px] text-(--text-muted) truncate">{{ p.description || '—'
                                            }}</p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-mono text-xxs text-(--text-body)">{{ p.sku }}</td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-col gap-1">
                                        <span class="px-2 py-0.5 rounded text-xxs font-mono uppercase w-fit"
                                            :class="p.product_type === 'software' ? 'badge-soft-primary' : 'badge-soft-info'">
                                            {{ p.product_type }}
                                        </span>
                                        <div v-if="p.product_type === 'software' && p.modules?.length"
                                            class="flex flex-wrap gap-1">
                                            <span v-for="m in p.modules" :key="m.id"
                                                class="inline-flex items-center gap-0.5 text-xxs font-mono px-1.5 py-0.5 rounded badge-soft-success"
                                                :title="m.name">
                                                <i :class="['ti', m.icon || 'ti-puzzle', 'text-[10px]']" />
                                                {{ m.prefix }}
                                            </span>
                                        </div>
                                        <span v-else-if="p.product_type === 'software'"
                                            class="text-xxs text-(--text-muted) italic">no modules</span>
                                    </div>
                                </td>
                                <td class="px-4 py-3">
                                    <span v-if="p.category" class="inline-flex items-center gap-1.5 text-xxs">
                                        <span class="w-2.5 h-2.5 rounded-sm border border-(--border-color)"
                                            :style="{ backgroundColor: p.category.color || 'transparent' }" />
                                        <span class="text-(--text-body)">{{ p.category.name }}</span>
                                    </span>
                                    <span v-else class="text-xxs text-(--text-muted) italic">uncategorised</span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono text-xs" :class="stockClass(p)">{{
                                    p.current_stock }}</td>
                                <td class="px-4 py-3 text-right font-mono text-xxs text-(--text-muted)">{{
                                    p.minimum_stock_level ?? '—' }}</td>
                                <td class="px-4 py-3 text-right font-mono text-xs font-semibold text-(--text-heading)">
                                    {{ fmtMoney(p.unit_price) }}</td>
                                <td class="px-4 py-3">
                                    <button v-if="p.variants?.length" type="button"
                                        class="text-xxs text-(--color-primary) hover:underline"
                                        @click="toggleVariants(p.id)">
                                        {{ p.variants.length }} variant<span v-if="p.variants.length > 1">s</span>
                                        <i :class="['ti', expanded[p.id] ? 'ti-chevron-up' : 'ti-chevron-down']"
                                            class="ml-0.5" />
                                    </button>
                                    <span v-else class="text-xxs text-(--text-muted) italic">none</span>
                                </td>
                                <td class="px-4 py-3">
                                    <Badge :variant="statusVariant(p)">{{ statusLabel(p) }}</Badge>
                                </td>
                                <td class="px-4 py-3 text-center">
                                    <button type="button" class="action-trigger" title="Actions"
                                        :class="{ 'action-trigger-open': actionMenu.open && actionMenu.product?.id === p.id }"
                                        @click.stop="openActionMenu(p, $event)">
                                        <i class="ti ti-dots-vertical" />
                                    </button>
                                </td>
                            </tr>

                            <!-- Variant expansion rows -->
                            <template v-for="p in paginated" :key="`v-${p.id}`">
                                <tr v-if="expanded[p.id] && p.variants?.length" class="bg-(--bg-muted)/40">
                                    <td colspan="10" class="px-4 py-3">
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                                            <div v-for="v in p.variants" :key="v.id"
                                                class="rounded-lg border border-(--border-color) bg-(--bg-card) p-3 text-xs">
                                                <div class="flex items-center justify-between">
                                                    <span class="font-semibold text-(--text-heading)">{{ v.name
                                                    }}</span>
                                                    <span class="font-mono font-bold text-(--color-primary)">{{
                                                        fmtMoney(v.unit_price) }}</span>
                                                </div>
                                                <p class="font-mono text-xxs text-(--text-muted) mt-0.5">{{ v.sku }}</p>
                                                <div v-if="v.attributes && Object.keys(v.attributes).length"
                                                    class="mt-1.5 flex flex-wrap gap-1">
                                                    <span v-for="(val, key) in v.attributes" :key="String(key)"
                                                        class="text-xxs px-1.5 py-0.5 rounded bg-(--bg-muted) text-(--text-body) font-mono">
                                                        {{ key }}:{{ val }}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div v-if="totalPages > 1"
                    class="flex flex-col sm:flex-row items-center justify-between gap-3 px-4 py-3 border-t border-(--border-color) text-xxs text-(--text-muted)">
                    <span>
                        Showing <span class="font-mono font-bold text-(--text-heading)">{{ startIndex + 1 }}</span>
                        to <span class="font-mono font-bold text-(--text-heading)">{{ Math.min(endIndex,
                            filtered.length) }}</span>
                        of <span class="font-mono font-bold text-(--text-heading)">{{ filtered.length }}</span>
                    </span>
                    <div class="flex items-center gap-1">
                        <button class="action-btn" :disabled="page === 1" @click="page = Math.max(1, page - 1)"><i
                                class="ti ti-chevron-left" /></button>
                        <button v-for="pg in totalPages" :key="pg"
                            class="w-7 h-7 rounded text-xxs font-mono font-bold flex items-center justify-center border transition-colors"
                            :class="pg === page ? 'bg-(--color-primary) text-white border-(--color-primary)' : 'border-(--border-color) text-(--text-body) hover:bg-(--bg-muted)'"
                            @click="page = pg">{{ pg }}</button>
                        <button class="action-btn" :disabled="page === totalPages || totalPages === 0"
                            @click="page = Math.min(totalPages, page + 1)"><i class="ti ti-chevron-right" /></button>
                    </div>
                </div>
            </section>

            <!-- Grid view -->
            <section v-else-if="view === 'grid'"
                class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                <div v-for="p in paginated" :key="p.id"
                    class="glass-card rounded-2xl overflow-hidden group flex flex-col relative transition-all duration-150 border border-(--border-color) hover:border-(--color-primary)/40">
                    
                    <!-- Glowing shape behind card -->
                    <div class="absolute -right-8 -top-8 w-20 h-20 rounded-full bg-(--color-primary)/10 blur-xl pointer-events-none group-hover:scale-150 transition-transform duration-500" />

                    <div class="aspect-4/3 flex items-center justify-center text-(--color-primary) overflow-hidden relative z-10"
                        :class="p.product_type === 'software' ? 'bg-(--color-primary-subtle)' : 'bg-(--bg-muted)'">
                        <img v-if="p.image_url" :src="p.image_url" :alt="p.name" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" />
                        <i v-else :class="['ti', p.product_type === 'software' ? 'ti-cloud' : 'ti-device-laptop']"
                            class="text-5xl opacity-60 transition-transform duration-300 group-hover:scale-110" />
                    </div>
                    <div class="p-4 space-y-2 flex-1 flex flex-col relative z-10">
                        <div class="flex items-center justify-between gap-2">
                            <span class="px-2 py-0.5 rounded text-xxs font-mono uppercase"
                                :class="p.product_type === 'software' ? 'badge-soft-primary' : 'badge-soft-info'">
                                {{ p.product_type }}
                            </span>
                            <Badge :variant="statusVariant(p)">{{ statusLabel(p) }}</Badge>
                        </div>
                        <h3
                            class="text-sm font-semibold text-(--text-heading) group-hover:text-(--color-primary) transition-colors">
                            {{ p.name }}</h3>
                        <p class="text-xxs text-(--text-muted) truncate">{{ p.description || '—' }}</p>
                        <p class="text-xxs text-(--text-muted) font-mono">{{ p.sku }}</p>
                        <div class="mt-auto flex items-center justify-between pt-2 border-t border-(--border-color)/50">
                            <span class="font-mono font-bold text-(--text-heading)">{{ fmtMoney(p.unit_price) }}</span>
                            <span class="text-xxs" :class="stockClass(p)">stock: {{ p.current_stock }}</span>
                        </div>
                        <div class="flex justify-end gap-1.5 mt-2">
                            <button class="action-btn" title="Edit" @click="openEdit(p)"><i
                                    class="ti ti-pencil" /></button>
                            <button class="action-btn action-btn-danger" title="Archive" @click="confirmDelete(p)"><i
                                    class="ti ti-trash" /></button>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Action menu (anchored via fixed positioning; outside the v-if chain
         on purpose — placing it between list and grid sections previously
         orphaned the grid's v-else and made both views render together) -->
        <div v-if="actionMenu.open && actionMenu.product"
            class="fixed z-50 glass-card rounded-lg shadow-(--shadow-lg) bg-(--bg-card) border border-(--border-color) py-1 min-w-[180px]"
            :style="{ top: actionMenu.y + 'px', left: actionMenu.x + 'px' }" @click.stop>
            <button class="action-item" @click="onEdit"><i class="ti ti-pencil" /> Edit</button>
            <button class="action-item" @click="onToggleActive">
                <i :class="['ti', actionMenu.product.is_active ? 'ti-circle-x' : 'ti-circle-check']" />
                {{ actionMenu.product.is_active ? 'Deactivate' : 'Activate' }}
            </button>
            <hr class="my-1 border-(--border-color)" />
            <button class="action-item action-item-danger" @click="onDelete"><i class="ti ti-trash" /> Archive</button>
        </div>

        <!-- Create / Edit modal -->
        <div v-if="showModal"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div
                class="glass-card rounded-2xl w-full max-w-xl bg-(--bg-card) shadow-(--shadow-lg) max-h-[90vh] flex flex-col">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3>{{ editing ? 'Edit product' : 'New product' }}</h3>
                    <button class="w-8 h-8 rounded-full hover:bg-(--bg-muted)" @click="showModal = false"><i
                            class="ti ti-x" /></button>
                </header>
                <form @submit.prevent="submit" class="p-5 space-y-4 overflow-y-auto custom-scrollbar">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">SKU
                                *</label>
                            <input v-model="form.sku" type="text" required maxlength="120" class="form-control" />
                        </div>
                        <div>
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">Product
                                type *</label>
                            <select v-model="form.product_type" class="form-control">
                                <option value="hardware">Hardware</option>
                                <option value="software">Software</option>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">Name
                            *</label>
                        <input v-model="form.name" type="text" required maxlength="255" class="form-control" />
                    </div>

                    <div>
                        <label
                            class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">Category</label>
                        <select v-model="form.category_id" class="form-control">
                            <option :value="null">— Uncategorised —</option>
                            <option v-for="c in categories" :key="c.id" :value="c.id">{{ categoryIndentLabel(c) }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">Short
                            description</label>
                        <input v-model="form.description" type="text" maxlength="1000" class="form-control"
                            placeholder="One-liner shown on cards & list rows" />
                    </div>

                    <div>
                        <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">Long
                            description</label>
                        <textarea v-model="form.description_long" rows="3" class="form-control"
                            placeholder="Marketing copy, surfaced on detail pages" />
                    </div>

                    <!-- Image upload -->
                    <div>
                        <label
                            class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">Product
                            image</label>
                        <div class="flex items-start gap-4">
                            <!-- Preview -->
                            <div
                                class="w-20 h-20 rounded-xl border border-(--border-color) bg-(--bg-muted) flex items-center justify-center shrink-0 overflow-hidden">
                                <img v-if="imagePreview" :src="imagePreview" alt="preview"
                                    class="w-full h-full object-cover" />
                                <i v-else class="ti ti-photo text-2xl text-(--text-muted)" />
                            </div>
                            <!-- Controls -->
                            <div class="flex-1 space-y-2">
                                <label
                                    class="inline-flex items-center gap-2 btn btn-ghost text-xs cursor-pointer border border-(--border-color) rounded-lg px-3 py-2">
                                    <i class="ti ti-upload text-sm" />
                                    {{ imagePreview ? 'Change image' : 'Upload image' }}
                                    <input ref="imageInput" type="file" accept="image/*" class="hidden"
                                        @change="onImageChange" />
                                </label>
                                <button v-if="imagePreview" type="button"
                                    class="flex items-center gap-1.5 text-xxs text-(--color-danger) hover:underline"
                                    @click="clearImage">
                                    <i class="ti ti-trash text-xs" />Remove
                                </button>
                                <p class="text-xxs text-(--text-muted)">PNG, JPG or WebP · max 2 MB</p>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">Unit
                                price *</label>
                            <input v-model.number="form.unit_price" type="number" min="0" step="0.01" required
                                class="form-control" />
                        </div>
                        <div>
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">Min
                                stock</label>
                            <input v-model.number="form.minimum_stock_level" type="number" min="0" class="form-control"
                                :disabled="form.product_type === 'software'" />
                        </div>
                        <div class="flex items-center pt-6">
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input v-model="form.is_active" type="checkbox"
                                    class="w-4 h-4 rounded border-(--border-color)" />
                                <span class="text-xs text-(--text-heading)">Active</span>
                            </label>
                        </div>
                    </div>

                    <!-- Module entitlements — software only -->
                    <div v-if="form.product_type === 'software'" class="space-y-2">
                        <div class="flex items-center justify-between">
                            <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide">
                                System modules unlocked
                            </label>
                            <span class="text-xxs text-(--color-primary) font-mono">{{ form.module_ids.length }}
                                selected</span>
                        </div>
                        <p class="text-xxs text-(--text-muted)">
                            Customers who subscribe to this product will have access to these modules.
                        </p>

                        <div v-if="!modulesLoaded" class="py-4 flex justify-center">
                            <span
                                class="w-5 h-5 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                        </div>

                        <div v-else-if="moduleOptions.length === 0" class="text-xxs text-(--text-muted) italic">
                            No modules available.
                        </div>

                        <div v-else
                            class="max-h-64 overflow-y-auto custom-scrollbar rounded-xl border border-(--border-color) divide-y divide-(--border-color)">
                            <div v-for="mod in moduleOptions" :key="mod.id">
                                <!-- Parent row -->
                                <label
                                    class="flex items-center gap-3 px-4 py-2.5 hover:bg-(--bg-muted)/60 cursor-pointer transition-colors"
                                    :class="form.module_ids.includes(mod.id) ? 'bg-(--color-primary-subtle)/30' : ''">
                                    <input type="checkbox"
                                        class="w-4 h-4 rounded border-(--border-color) accent-(--color-primary)"
                                        :checked="form.module_ids.includes(mod.id)"
                                        :indeterminate="isParentIndeterminate(mod)" @change="toggleParentModule(mod)" />
                                    <div class="w-6 h-6 rounded flex items-center justify-center shrink-0"
                                        :class="form.module_ids.includes(mod.id) ? 'bg-(--color-primary-subtle) text-(--color-primary)' : 'bg-(--bg-muted) text-(--text-muted)'">
                                        <i :class="['ti', mod.icon || 'ti-puzzle', 'text-xs']" />
                                    </div>
                                    <span class="flex-1 text-xs font-semibold text-(--text-heading)">{{ mod.name
                                    }}</span>
                                    <span
                                        class="text-xxs font-mono px-1 py-0.5 rounded border border-(--border-color) text-(--text-muted)">{{
                                            mod.prefix }}</span>
                                </label>

                                <!-- Children -->
                                <div v-if="mod.children?.length" class="bg-(--bg-muted)/20">
                                    <label v-for="child in mod.children" :key="child.id"
                                        class="flex items-center gap-3 px-4 py-2 pl-10 hover:bg-(--bg-muted)/60 cursor-pointer transition-colors border-t border-(--border-color)/40"
                                        :class="form.module_ids.includes(child.id) ? 'bg-(--color-primary-subtle)/20' : ''">
                                        <input type="checkbox"
                                            class="w-3.5 h-3.5 rounded border-(--border-color) accent-(--color-primary)"
                                            :checked="form.module_ids.includes(child.id)"
                                            @change="toggleModuleId(child.id)" />
                                        <i
                                            :class="['ti', child.icon || 'ti-circle', 'text-xs', form.module_ids.includes(child.id) ? 'text-(--color-primary)' : 'text-(--text-muted)']" />
                                        <span class="flex-1 text-xs text-(--text-body)">{{ child.name }}</span>
                                        <span class="text-xxs font-mono text-(--text-muted)">{{ child.prefix }}</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Variants editor -->
                    <div class="space-y-2 pt-4 border-t border-(--border-color)">
                        <div class="flex items-center justify-between">
                            <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide">
                                Variants
                            </label>
                            <span class="text-xxs text-(--color-primary) font-mono">{{ variantDrafts.length }} row<span
                                    v-if="variantDrafts.length !== 1">s</span></span>
                        </div>
                        <p class="text-xxs text-(--text-muted)">
                            Variations of this product (e.g. colour, size, plan tier). Each variant has its own SKU and
                            price.
                        </p>

                        <div v-if="variantDrafts.length"
                            class="rounded-xl border border-(--border-color) divide-y divide-(--border-color)">
                            <div v-for="(v, idx) in variantDrafts" :key="v.tempId" class="p-3 space-y-2 bg-(--bg-card)">
                                <div class="grid grid-cols-12 gap-2">
                                    <div class="col-span-4 space-y-1">
                                        <label
                                            class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">SKU
                                            *</label>
                                        <input v-model="v.sku" type="text" required maxlength="120"
                                            placeholder="VAR-001" class="form-control text-xs font-mono"
                                            @input="markVariantDirty(v)" />
                                    </div>
                                    <div class="col-span-5 space-y-1">
                                        <label
                                            class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Name
                                            *</label>
                                        <input v-model="v.name" type="text" required maxlength="255"
                                            placeholder="e.g. Red — Large" class="form-control text-xs"
                                            @input="markVariantDirty(v)" />
                                    </div>
                                    <div class="col-span-3 space-y-1">
                                        <label
                                            class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Price</label>
                                        <input v-model.number="v.unit_price" type="number" min="0" step="0.01"
                                            class="form-control text-xs text-right" @input="markVariantDirty(v)" />
                                    </div>
                                </div>
                                <div class="grid grid-cols-12 gap-2 items-end">
                                    <div class="col-span-9 space-y-1">
                                        <label
                                            class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Attributes
                                            (JSON)</label>
                                        <textarea v-model="v.attributes_json" rows="2"
                                            placeholder='{"color":"red","size":"L"}'
                                            class="form-control text-xxs font-mono resize-none"
                                            @input="markVariantDirty(v)" />
                                    </div>
                                    <div class="col-span-3 flex items-center justify-between gap-2 pb-2">
                                        <label class="inline-flex items-center gap-2 cursor-pointer">
                                            <input v-model="v.is_active" type="checkbox"
                                                class="rounded border-(--border-color)" @change="markVariantDirty(v)" />
                                            <span class="text-xxs">Active</span>
                                        </label>
                                        <button type="button" class="action-btn action-btn-danger"
                                            title="Remove variant" @click="removeVariantRow(idx)">
                                            <i class="ti ti-trash" />
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <button type="button"
                            class="btn btn-ghost text-xs w-full justify-center border border-dashed border-(--border-color)"
                            @click="addVariantRow">
                            <i class="ti ti-plus" />Add variant
                        </button>
                    </div>

                    <footer class="pt-4 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="submitting">
                            <i :class="['ti', submitting ? 'ti-loader-2 animate-spin' : 'ti-device-floppy']" />
                            {{ submitting ? 'Saving…' : (editing ? 'Save changes' : 'Create product') }}
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Delete confirm -->
        <div v-if="deleteTarget"
            class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3>Archive product</h3>
                    <button class="w-8 h-8 rounded-full hover:bg-(--bg-muted)" @click="deleteTarget = null"><i
                            class="ti ti-x" /></button>
                </header>
                <div class="p-5 space-y-2">
                    <p class="text-xs text-(--text-body)">
                        Archive <span class="font-semibold text-(--text-heading)">{{ deleteTarget.name }}</span>?
                    </p>
                    <p class="text-xxs text-(--text-muted)">
                        Soft-delete — the product stays referenced in historical quotations, orders and invoices, but is
                        hidden
                        from the catalogue.
                    </p>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button class="btn btn-ghost text-xs" @click="deleteTarget = null">Keep</button>
                    <button class="btn btn-danger text-xs" :disabled="submitting" @click="onConfirmDelete">
                        <i class="ti ti-trash" />Archive
                    </button>
                </footer>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, reactive, ref } from 'vue'
import { useApi } from '~/composables/useApi'
import { useInventory } from '~/composables/useInventory'
import { useToast } from '~/composables/useToast'
import type { Category } from '~/types/inventory'

interface Variant {
    id: string
    product_id: string
    sku: string
    name: string
    unit_price: number
    attributes: Record<string, unknown> | null
    is_active: boolean
}

interface VariantDraft {
    tempId: string
    id: string | null
    sku: string
    name: string
    unit_price: number
    attributes_json: string
    is_active: boolean
    _isNew: boolean
    _dirty: boolean
}

interface ModuleOption {
    id: string
    slug: string
    name: string
    prefix: string
    icon: string | null
    group: string
    isCore: boolean
    children?: ModuleOption[]
}

interface Product {
    id: string
    image_url: string | null
    sku: string
    name: string
    product_type: 'hardware' | 'software'
    category_id: string | null
    category: { id: string; name: string; slug: string; color: string | null } | null
    description: string | null
    description_long: string | null
    unit_price: number
    minimum_stock_level: number | null
    is_active: boolean
    current_stock: number
    variants?: Variant[]
    modules?: { id: string; slug: string; name: string; prefix: string; icon: string | null }[]
}

const api = useApi()
const inventory = useInventory()
const toast = useToast()

// ─── Module options (loaded once, used by software product form) ─────────────
const moduleOptions = ref<ModuleOption[]>([])
const modulesLoaded = ref(false)

const loadModuleOptions = async () => {
    if (modulesLoaded.value) return
    try {
        const res = await api.get<{ data: ModuleOption[] }>('modules/all')
        // Only non-core modules are relevant (core is always on)
        moduleOptions.value = res.data.filter(m => !m.isCore).map(m => ({
            ...m,
            children: m.children?.filter(c => !c.isCore) ?? [],
        }))
        modulesLoaded.value = true
    } catch {
        // silently fail — picker just won't show options
    }
}

const loading = ref(false)
const submitting = ref(false)
const products = ref<Product[]>([])

const view = ref<'list' | 'grid'>('list')
const search = ref('')
const filterType = ref<'all' | 'hardware' | 'software'>('all')
const filterStatus = ref<'all' | 'active' | 'inactive' | 'low_stock' | 'out_of_stock'>('all')
const filterCategory = ref<string>('')

const categories = ref<Category[]>([])
const loadCategories = async () => {
    try {
        const res = await inventory.categories.tree()
        const flatten = (nodes: Category[]): Category[] => {
            const out: Category[] = []
            const walk = (list: Category[]) => list.forEach(n => {
                out.push(n)
                if (n.children?.length) walk(n.children)
            })
            walk(nodes)
            return out
        }
        categories.value = flatten(res.data)
    } catch {
        // non-fatal: picker just shows no options
    }
}
const categoryDepthOf = (id: string): number => {
    let d = 0
    let current = categories.value.find(c => c.id === id)
    while (current?.parentId) {
        d++
        current = categories.value.find(c => c.id === current!.parentId)
    }
    return d
}
const categoryIndentLabel = (c: Category) => `${'— '.repeat(categoryDepthOf(c.id))}${c.name}`
const page = ref(1)
const pageSize = 8
const expanded = reactive<Record<string, boolean>>({})

// ───── Formatters ───────────────────────────────────────────────
const fmtMoney = (v: number) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v || 0)
const fmtInt = (v: number) => new Intl.NumberFormat('en-US').format(v || 0)

// ───── Status helpers ───────────────────────────────────────────
const isOutOfStock = (p: Product) =>
    p.product_type === 'hardware' && (p.current_stock ?? 0) <= 0
const isLowStock = (p: Product) =>
    p.product_type === 'hardware' &&
    p.minimum_stock_level !== null &&
    p.current_stock > 0 &&
    p.current_stock <= p.minimum_stock_level

const statusLabel = (p: Product): string => {
    if (!p.is_active) return 'Inactive'
    if (isOutOfStock(p)) return 'Out of stock'
    if (isLowStock(p)) return 'Low stock'
    return 'Active'
}
const statusVariant = (p: Product): 'success' | 'warning' | 'danger' | 'secondary' => {
    if (!p.is_active) return 'secondary'
    if (isOutOfStock(p)) return 'danger'
    if (isLowStock(p)) return 'warning'
    return 'success'
}
const stockClass = (p: Product): string => {
    if (p.product_type === 'software') return 'text-(--text-muted)'
    if (isOutOfStock(p)) return 'text-(--color-danger) font-bold'
    if (isLowStock(p)) return 'text-(--color-warning) font-semibold'
    return 'text-(--text-body)'
}

// ───── Filtering + pagination ───────────────────────────────────
const filtered = computed(() => products.value.filter(p => {
    const matchSearch = !search.value ||
        p.name.toLowerCase().includes(search.value.toLowerCase()) ||
        p.sku.toLowerCase().includes(search.value.toLowerCase())
    const matchType = filterType.value === 'all' || p.product_type === filterType.value
    const matchStatus = filterStatus.value === 'all'
        || (filterStatus.value === 'active' && p.is_active && !isOutOfStock(p))
        || (filterStatus.value === 'inactive' && !p.is_active)
        || (filterStatus.value === 'low_stock' && isLowStock(p))
        || (filterStatus.value === 'out_of_stock' && isOutOfStock(p))
    const matchCategory = !filterCategory.value
        || (filterCategory.value === '__none__' && !p.category_id)
        || p.category_id === filterCategory.value
    return matchSearch && matchType && matchStatus && matchCategory
}))

const totalPages = computed(() => Math.max(1, Math.ceil(filtered.value.length / pageSize)))
const startIndex = computed(() => (page.value - 1) * pageSize)
const endIndex = computed(() => startIndex.value + pageSize)
const paginated = computed(() => filtered.value.slice(startIndex.value, endIndex.value))

// ───── Metrics (derived live from the loaded catalogue) ──────────
const metrics = computed(() => {
    const total = products.value.length
    const active = products.value.filter(p => p.is_active).length
    const hardware = products.value.filter(p => p.product_type === 'hardware').length
    const software = products.value.filter(p => p.product_type === 'software').length
    const lowStock = products.value.filter(isLowStock).length
    const outOfStock = products.value.filter(isOutOfStock).length
    const inventoryValue = products.value
        .filter(p => p.product_type === 'hardware')
        .reduce((sum, p) => sum + p.unit_price * (p.current_stock || 0), 0)
    const avgUnit = total ? products.value.reduce((s, p) => s + p.unit_price, 0) / total : 0
    return { total, active, hardware, software, lowStock, outOfStock, inventoryValue, avgUnit }
})

// ───── Data load ────────────────────────────────────────────────
const load = async () => {
    loading.value = true
    try {
        const res = await api.get<{ data: Product[]; pagination: any }>('products?limit=100')
        products.value = res.data
    } catch (err: any) {
        toast.error('Failed to load products', err?.data?.message || 'Check API and try again.')
    } finally {
        loading.value = false
    }
}

// ───── Variants expansion ───────────────────────────────────────
const toggleVariants = (id: string) => { expanded[id] = !expanded[id] }

// ───── Add / Edit modal ─────────────────────────────────────────
const showModal = ref(false)
const editing = ref<Product | null>(null)
const imageFile = ref<File | null>(null)
const imagePreview = ref<string | null>(null)
const imageInput = ref<HTMLInputElement | null>(null)
const form = reactive({
    sku: '',
    name: '',
    product_type: 'hardware' as 'hardware' | 'software',
    category_id: null as string | null,
    description: '',
    description_long: '',
    unit_price: 0,
    minimum_stock_level: 0,
    is_active: true,
    module_ids: [] as string[],
})

const variantDrafts = ref<VariantDraft[]>([])
const removedVariantIds = ref<string[]>([])
let tempVariantSeq = 0
const nextTempId = () => `tmp-${++tempVariantSeq}`

const resetForm = () => {
    form.sku = ''
    form.name = ''
    form.product_type = 'hardware'
    form.category_id = null
    form.description = ''
    form.description_long = ''
    form.unit_price = 0
    form.minimum_stock_level = 0
    form.is_active = true
    form.module_ids = []
    variantDrafts.value = []
    removedVariantIds.value = []
    imageFile.value = null
    imagePreview.value = null
    if (imageInput.value) imageInput.value.value = ''
}

const variantFromExisting = (v: Variant): VariantDraft => ({
    tempId: nextTempId(),
    id: v.id,
    sku: v.sku,
    name: v.name,
    unit_price: Number(v.unit_price) || 0,
    attributes_json: v.attributes ? JSON.stringify(v.attributes, null, 2) : '',
    is_active: !!v.is_active,
    _isNew: false,
    _dirty: false,
})

const addVariantRow = () => {
    variantDrafts.value.push({
        tempId: nextTempId(),
        id: null,
        sku: '',
        name: '',
        unit_price: form.unit_price || 0,
        attributes_json: '',
        is_active: true,
        _isNew: true,
        _dirty: false,
    })
}

const removeVariantRow = (idx: number) => {
    const row = variantDrafts.value[idx]
    if (row.id) removedVariantIds.value.push(row.id)
    variantDrafts.value.splice(idx, 1)
}

const markVariantDirty = (row: VariantDraft) => { if (!row._isNew) row._dirty = true }

const parseAttributes = (raw: string): Record<string, unknown> | null => {
    const trimmed = raw.trim()
    if (!trimmed) return null
    try {
        const parsed = JSON.parse(trimmed)
        if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) return parsed
        throw new Error('Attributes must be a JSON object')
    } catch (e: any) {
        throw new Error(`Invalid attributes JSON: ${e.message}`)
    }
}

const persistVariants = async (productId: string): Promise<void> => {
    // Deletes first so a freed SKU can be re-used by a new row in the same save.
    await Promise.all(removedVariantIds.value.map(id =>
        inventory.productVariants.destroy(id).catch(err => {
            console.warn('Variant delete failed', id, err?.data?.message)
        })))
    removedVariantIds.value = []

    const ops: Promise<unknown>[] = []
    for (const row of variantDrafts.value) {
        if (!row.sku.trim() || !row.name.trim()) continue
        const payload = {
            sku: row.sku.trim(),
            name: row.name.trim(),
            unit_price: Number(row.unit_price) || 0,
            attributes: parseAttributes(row.attributes_json),
            is_active: row.is_active,
        }
        if (row._isNew) {
            ops.push(inventory.productVariants.create(productId, payload))
        } else if (row._dirty && row.id) {
            ops.push(inventory.productVariants.update(row.id, payload))
        }
    }
    await Promise.all(ops)
}

const openCreate = () => {
    editing.value = null
    resetForm()
    showModal.value = true
    loadModuleOptions()
}

const openEdit = (p: Product) => {
    editing.value = p
    form.sku = p.sku
    form.name = p.name
    form.product_type = p.product_type
    form.category_id = p.category_id
    form.description = p.description ?? ''
    form.description_long = p.description_long ?? ''
    form.unit_price = p.unit_price
    form.minimum_stock_level = p.minimum_stock_level ?? 0
    form.is_active = p.is_active
    form.module_ids = p.modules?.map(m => m.id) ?? []
    variantDrafts.value = (p.variants ?? []).map(variantFromExisting)
    removedVariantIds.value = []
    imageFile.value = null
    imagePreview.value = p.image_url ?? null
    if (imageInput.value) imageInput.value.value = ''
    showModal.value = true
    loadModuleOptions()
}

const onImageChange = (e: Event) => {
    const file = (e.target as HTMLInputElement).files?.[0] ?? null
    imageFile.value = file
    if (file) {
        const reader = new FileReader()
        reader.onload = ev => { imagePreview.value = ev.target?.result as string }
        reader.readAsDataURL(file)
    }
}

const clearImage = () => {
    imageFile.value = null
    imagePreview.value = null
    if (imageInput.value) imageInput.value.value = ''
}

const toggleModuleId = (id: string) => {
    const idx = form.module_ids.indexOf(id)
    if (idx === -1) form.module_ids.push(id)
    else form.module_ids.splice(idx, 1)
}

const toggleParentModule = (mod: ModuleOption) => {
    const allIds = [mod.id, ...(mod.children?.map(c => c.id) ?? [])]
    const allSelected = allIds.every(id => form.module_ids.includes(id))
    if (allSelected) {
        form.module_ids = form.module_ids.filter(id => !allIds.includes(id))
    } else {
        for (const id of allIds) {
            if (!form.module_ids.includes(id)) form.module_ids.push(id)
        }
    }
}

const isParentIndeterminate = (mod: ModuleOption): boolean => {
    if (!mod.children?.length) return false
    const allIds = [mod.id, ...(mod.children.map(c => c.id))]
    const selectedCount = allIds.filter(id => form.module_ids.includes(id)).length
    return selectedCount > 0 && selectedCount < allIds.length
}

const submit = async () => {
    submitting.value = true
    try {
        const buildPayload = () => {
            if (imageFile.value) {
                const fd = new FormData()
                fd.append('sku', form.sku)
                fd.append('name', form.name)
                fd.append('product_type', form.product_type)
                if (form.category_id) fd.append('category_id', form.category_id)
                if (form.description) fd.append('description', form.description)
                if (form.description_long) fd.append('description_long', form.description_long)
                fd.append('unit_price', String(form.unit_price))
                fd.append('is_active', form.is_active ? '1' : '0')
                if (form.product_type === 'hardware')
                    fd.append('minimum_stock_level', String(form.minimum_stock_level))
                if (form.product_type === 'software')
                    form.module_ids.forEach(id => fd.append('module_ids[]', id))
                fd.append('image', imageFile.value)
                return fd
            }
            const payload: Record<string, unknown> = {
                sku: form.sku,
                name: form.name,
                product_type: form.product_type,
                category_id: form.category_id,
                description: form.description || null,
                description_long: form.description_long || null,
                unit_price: form.unit_price,
                is_active: form.is_active,
            }
            if (form.product_type === 'hardware')
                payload.minimum_stock_level = form.minimum_stock_level
            if (form.product_type === 'software')
                payload.module_ids = form.module_ids
            return payload
        }

        let savedId: string
        if (editing.value) {
            const body = buildPayload()
            // Laravel doesn't parse multipart PUT — spoof the method via POST
            if (body instanceof FormData) body.append('_method', 'PUT')
            const res = body instanceof FormData
                ? await api.post<{ data: Product }>(`products/${editing.value.id}`, body)
                : await api.put<{ data: Product }>(`products/${editing.value.id}`, body)
            savedId = res.data.id
            const idx = products.value.findIndex(p => p.id === editing.value!.id)
            if (idx !== -1) products.value.splice(idx, 1, res.data)
            toast.success('Product updated', res.data.name)
        } else {
            const res = await api.post<{ data: Product }>('products', buildPayload())
            savedId = res.data.id
            products.value.splice(0, 0, res.data)
            toast.success('Product created', res.data.name)
        }

        try {
            await persistVariants(savedId)
        } catch (e: any) {
            toast.error('Variants partially saved', e?.message || e?.data?.message || 'Some variants did not save')
        }

        showModal.value = false
        // Reload to ensure image_url and all derived fields are fresh from the server
        await load()
    } catch (err: any) {
        const msg = err?.data?.message || Object.values(err?.data?.errors || {}).flat()[0] || 'Check the form and try again.'
        toast.error('Failed to save product', String(msg))
    } finally {
        submitting.value = false
    }
}

// ───── Toggle active / archive ──────────────────────────────────
const onToggleActive = async () => {
    const p = actionMenu.product
    closeActionMenu()
    if (!p) return
    try {
        const res = await api.put<{ data: Product }>(`products/${p.id}`, { is_active: !p.is_active })
        const idx = products.value.findIndex(x => x.id === p.id)
        if (idx !== -1) products.value[idx] = res.data
        toast.success(res.data.is_active ? 'Activated' : 'Deactivated', res.data.name)
    } catch (err: any) {
        toast.error('Toggle failed', err?.data?.message)
    }
}

const deleteTarget = ref<Product | null>(null)
const confirmDelete = (p: Product) => { deleteTarget.value = p }
const onDelete = () => {
    const p = actionMenu.product
    closeActionMenu()
    if (p) deleteTarget.value = p
}
const onEdit = () => {
    const p = actionMenu.product
    closeActionMenu()
    if (p) openEdit(p)
}
const onConfirmDelete = async () => {
    if (!deleteTarget.value) return
    submitting.value = true
    try {
        await api.delete(`products/${deleteTarget.value.id}`)
        products.value = products.value.filter(p => p.id !== deleteTarget.value!.id)
        toast.success('Product archived', deleteTarget.value.name)
        deleteTarget.value = null
    } catch (err: any) {
        toast.error('Archive failed', err?.data?.message)
    } finally {
        submitting.value = false
    }
}

// ───── Action menu positioning ──────────────────────────────────
const actionMenu = reactive({
    open: false,
    x: 0,
    y: 0,
    product: null as Product | null,
})

const openActionMenu = (p: Product, ev: MouseEvent) => {
    const rect = (ev.currentTarget as HTMLElement).getBoundingClientRect()
    const menuWidth = 180
    const menuMaxHeight = 160
    const left = Math.min(rect.right - menuWidth, window.innerWidth - menuWidth - 8)
    const wouldOverflow = rect.bottom + menuMaxHeight + 8 > window.innerHeight
    actionMenu.product = p
    actionMenu.x = Math.max(8, left)
    actionMenu.y = wouldOverflow ? rect.top - menuMaxHeight - 6 : rect.bottom + 6
    actionMenu.open = true
}

const closeActionMenu = () => { actionMenu.open = false; actionMenu.product = null }

onMounted(() => {
    load()
    loadCategories()
    if (import.meta.client) document.addEventListener('click', closeActionMenu)
})
onUnmounted(() => {
    if (import.meta.client) document.removeEventListener('click', closeActionMenu)
})
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

.action-btn:hover {
    background: var(--bg-muted);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}

.action-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}

.action-btn-danger:hover {
    color: var(--color-danger);
    border-color: rgb(var(--color-danger-rgb) / 0.4);
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
