<template>
    <NuxtLayout name="default">
        <div class="space-y-5">
            <!-- Page header (§3 typography) -->
            <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-(--text-heading) leading-tight">POS Register</h1>
                    <p class="text-xs text-(--text-muted) mt-1">{{ pageHint }}</p>
                </div>
                <div v-if="myShift" class="flex items-center gap-2">
                    <NuxtLink to="/pos/orders" class="btn btn-soft-secondary text-xs inline-flex items-center gap-2">
                        <i class="ti ti-receipt" /> View sales
                    </NuxtLink>
                    <NuxtLink to="/pos/shifts" class="btn btn-soft-warning text-xs inline-flex items-center gap-2">
                        <i class="ti ti-lock" /> Close shift
                    </NuxtLink>
                </div>
            </header>

            <!-- Initial shift lookup spinner — prevents the no-shift hero
                 from flashing while /pos/shifts/me is still in flight. -->
            <section v-if="shiftLoading" class="glass-card rounded-2xl p-12 text-center space-y-3">
                <span class="w-10 h-10 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin inline-block" />
                <p class="text-xs text-(--text-muted)">Checking your shift...</p>
            </section>

            <!-- No active shift hero -->
            <section v-else-if="!myShift" class="glass-card rounded-2xl p-10 text-center space-y-4 relative overflow-hidden">
                <span class="absolute top-0 right-0 h-[2px] w-[100px] bg-linear-to-r from-(--color-primary) to-transparent pointer-events-none" />
                <div class="absolute -right-16 -top-16 w-56 h-56 rounded-full bg-(--color-primary)/10 blur-3xl pointer-events-none" />
                <div class="relative z-10 flex flex-col items-center gap-3">
                    <span class="w-16 h-16 rounded-full bg-(--color-primary-subtle) text-(--color-primary) flex items-center justify-center">
                        <i class="ti" :class="otherShifts.length ? 'ti-user-exclamation' : 'ti-lock'" />
                    </span>

                    <!-- Case A: a shift IS open, but on a different cashier. -->
                    <template v-if="otherShifts.length > 0">
                        <Badge variant="info" icon="ti-info-circle">Shift open by another cashier</Badge>
                        <h2 class="text-lg font-semibold text-(--text-heading)">You're not on this register</h2>
                        <p class="text-xs text-(--text-muted) max-w-md">
                            The register endpoint is scoped to the signed-in cashier. The active shift on
                            <span class="font-mono text-(--text-heading)">{{ otherShifts[0].terminal?.code || otherShifts[0].terminalId.slice(0, 8) }}</span>
                            is held by
                            <span class="text-(--text-heading)">{{ otherShifts[0].cashierName || 'another user' }}</span>.
                            Sign in as that cashier, close their shift first, or open your own on another terminal.
                        </p>
                        <div class="flex flex-wrap items-center justify-center gap-2 mt-1">
                            <NuxtLink to="/pos/shifts" class="btn btn-primary text-xs inline-flex items-center gap-2">
                                <i class="ti ti-cash-register" /> Manage shifts
                            </NuxtLink>
                            <button class="btn btn-soft-secondary text-xs inline-flex items-center gap-2" @click="bootstrap">
                                <i class="ti ti-refresh" /> Refresh
                            </button>
                        </div>
                    </template>

                    <!-- Case B: no open shifts anywhere. -->
                    <template v-else>
                        <Badge variant="warning" icon="ti-alert-triangle">Shift required</Badge>
                        <h2 class="text-lg font-semibold text-(--text-heading)">No active shift</h2>
                        <p class="text-xs text-(--text-muted) max-w-md">
                            A cashier shift must be open before the register can take sales. Open one on an active
                            terminal to begin.
                        </p>
                        <NuxtLink to="/pos/shifts" class="btn btn-primary text-xs inline-flex items-center gap-2 mt-1">
                            <i class="ti ti-cash-register" /> Go to shifts
                        </NuxtLink>
                    </template>

                    <!-- Diagnostic block when the lookup itself failed. -->
                    <div v-if="shiftError" class="mt-3 text-xxs text-(--color-danger) px-3 py-2 rounded-lg bg-(--color-danger)/10 max-w-md">
                        <i class="ti ti-alert-circle" /> {{ shiftError }}
                    </div>
                </div>
            </section>

            <!-- Active shift status bar -->
            <section v-else-if="myShift" class="glass-card rounded-2xl overflow-hidden"
                :class="myShift.isOverride ? 'border border-(--color-warning)/40' : ''">
                <!-- Admin-override banner -->
                <div v-if="myShift.isOverride" class="px-4 py-2 bg-(--color-warning)/10 border-b border-(--color-warning)/30 flex items-center gap-2 text-xs">
                    <i class="ti ti-shield-check text-(--color-warning)" />
                    <span class="text-(--text-heading) font-semibold">Admin override</span>
                    <span class="text-(--text-muted)">- ringing on behalf of
                        <span class="text-(--text-heading)">{{ myShift.cashierName || 'another cashier' }}</span>.
                        Sales will record their <span class="font-mono">cashier_id</span>, not yours.
                    </span>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-px bg-(--border-color)">
                    <!-- Shift identity -->
                    <div class="px-4 py-3 bg-(--bg-card) flex items-center gap-3">
                        <Badge :variant="myShift.isOverride ? 'warning' : 'success'"
                            :icon="myShift.isOverride ? 'ti-shield-check' : 'ti-clock-play'">
                            {{ myShift.isOverride ? 'Override' : 'Shift open' }}
                        </Badge>
                        <div class="text-xs min-w-0">
                            <div class="text-(--text-heading) font-semibold font-mono truncate">
                                {{ myShift.terminal?.code || myShift.terminalId.slice(0, 8) }}
                            </div>
                            <div class="text-xxs text-(--text-muted) truncate">{{ myShift.terminal?.name || 'terminal' }}</div>
                        </div>
                    </div>
                    <!-- Cashier -->
                    <div class="px-4 py-3 bg-(--bg-card) flex items-center gap-3">
                        <span class="w-8 h-8 rounded-full bg-(--color-primary)/10 text-(--color-primary) flex items-center justify-center text-xxs font-bold">
                            {{ (myShift.cashierName || '?').slice(0, 1).toUpperCase() }}
                        </span>
                        <div class="text-xs min-w-0">
                            <div class="text-xxs uppercase tracking-widest text-(--text-muted) font-bold">Cashier</div>
                            <div class="text-(--text-heading) truncate">{{ myShift.cashierName || '-' }}</div>
                        </div>
                    </div>
                    <!-- Cart counter -->
                    <div class="px-4 py-3 bg-(--bg-card) flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg badge-soft-info flex items-center justify-center">
                            <i class="ti ti-shopping-cart text-sm" />
                        </span>
                        <div class="text-xs min-w-0">
                            <div class="text-xxs uppercase tracking-widest text-(--text-muted) font-bold">Cart lines</div>
                            <div class="text-(--text-heading) font-mono font-semibold">{{ cart.length }}</div>
                        </div>
                    </div>
                    <!-- Running total -->
                    <div class="px-4 py-3 bg-(--bg-card) flex items-center gap-3">
                        <span class="w-8 h-8 rounded-lg badge-soft-primary flex items-center justify-center">
                            <i class="ti ti-cash text-sm" />
                        </span>
                        <div class="text-xs min-w-0">
                            <div class="text-xxs uppercase tracking-widest text-(--text-muted) font-bold">Total</div>
                            <div class="text-(--color-primary) font-mono font-bold text-base leading-tight">{{ formatMoney(grandTotal) }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <div v-if="myShift && !shiftLoading" class="grid grid-cols-1 lg:grid-cols-5 gap-4">
                <!-- ─────────────────  Catalogue panel (3/5)  ───────────────── -->
                <section class="lg:col-span-3 space-y-3">
                    <!-- Search toolbar -->
                    <div class="glass-card rounded-2xl p-3 flex gap-2 items-center">
                        <div class="relative flex-1">
                            <i class="ti ti-barcode absolute left-3 top-1/2 -translate-y-1/2 text-(--color-primary) text-base" />
                            <input v-model="search" type="search"
                                placeholder="Scan barcode or search SKU / product name..."
                                ref="searchInput"
                                class="form-control pl-10 text-sm" @input="onSearch" @keyup.enter="quickAddFirst" />
                        </div>
                        <button class="action-trigger w-10 h-10 rounded-xl inline-flex items-center justify-center"
                            title="Reload catalogue" @click="loadProducts">
                            <i class="ti ti-refresh text-base" />
                        </button>
                    </div>

                    <!-- Section header -->
                    <header class="flex items-center justify-between px-1">
                        <span class="text-xxs uppercase tracking-widest text-(--text-muted) font-bold">
                            <i class="ti ti-package mr-1" /> Catalogue
                        </span>
                        <span class="text-xxs text-(--text-muted)">{{ products.length }} item{{ products.length === 1 ? '' : 's' }}</span>
                    </header>

                    <!-- Loading -->
                    <div v-if="productsLoading" class="py-20 flex flex-col items-center gap-3">
                        <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                        <span class="text-xs text-(--text-muted)">Loading catalogue...</span>
                    </div>

                    <!-- Error -->
                    <div v-else-if="productsError" class="glass-card rounded-2xl py-12 text-center border border-(--color-danger)/30">
                        <i class="ti ti-alert-triangle text-3xl text-(--color-danger)" />
                        <h4 class="text-sm font-semibold text-(--text-heading) mt-3">Catalogue unavailable</h4>
                        <p class="text-xs text-(--text-muted) mt-1 max-w-md mx-auto">{{ productsError }}</p>
                        <button class="btn btn-soft-secondary text-xs mt-3 inline-flex items-center gap-2" @click="loadProducts">
                            <i class="ti ti-refresh" /> Retry
                        </button>
                    </div>

                    <!-- Empty -->
                    <div v-else-if="products.length === 0" class="glass-card rounded-2xl py-16 text-center">
                        <i class="ti ti-package-off text-4xl text-(--text-muted)" />
                        <h4 class="text-sm font-semibold text-(--text-heading) mt-3">
                            {{ search ? 'No products match' : 'No products in this catalogue yet' }}
                        </h4>
                        <p class="text-xs text-(--text-muted) mt-1 max-w-md mx-auto">
                            {{ search
                                ? 'Try a different barcode or term, or clear the search to see the full catalogue.'
                                : 'A product must be created in Inventory and marked active before it can be rung up here.'
                            }}
                        </p>
                        <NuxtLink v-if="!search" to="/inventory/products" class="btn btn-soft-primary text-xs mt-3 inline-flex items-center gap-2">
                            <i class="ti ti-plus" /> Add products
                        </NuxtLink>
                    </div>

                    <!-- Product grid -->
                    <div v-else class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-4 gap-3">
                        <button v-for="p in products" :key="p.id" type="button" class="product-tile glass-card rounded-2xl p-3 text-left flex flex-col gap-2 group" @click="addToCart(p)">
                            <div class="aspect-square rounded-xl bg-(--bg-muted) flex items-center justify-center overflow-hidden relative">
                                <img v-if="p.image_url" :src="p.image_url" :alt="p.name" class="w-full h-full object-cover transition-transform duration-300 group-hover:scale-105" />
                                <i v-else class="ti ti-package text-3xl text-(--text-muted)" />
                                <span class="absolute top-1.5 right-1.5 w-6 h-6 rounded-full bg-(--color-primary) text-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-(--shadow-md)">
                                    <i class="ti ti-plus text-xs" />
                                </span>
                            </div>
                            <div class="space-y-0.5">
                                <div class="text-xs font-semibold text-(--text-heading) truncate">{{ p.name }}</div>
                                <div class="text-xxs text-(--text-muted) font-mono truncate">{{ p.sku }}</div>
                                <div class="text-sm font-mono font-bold text-(--color-primary)">{{ formatMoney(p.unit_price) }}</div>
                            </div>
                        </button>
                    </div>
                </section>

                <!-- ─────────────────  Sale panel (2/5)  ───────────────── -->
                <aside class="lg:col-span-2 space-y-3">
                    <!-- Customer card -->
                    <div class="glass-card rounded-2xl p-4 space-y-3 relative">
                        <div class="flex items-center justify-between">
                            <span class="text-xxs uppercase tracking-widest text-(--text-muted) font-bold">
                                <i class="ti ti-user mr-1" /> Customer
                            </span>
                            <Badge v-if="!customer" variant="secondary">Walk-in</Badge>
                            <button v-else class="text-xxs text-(--text-muted) hover:text-(--color-danger) inline-flex items-center gap-1"
                                @click="clearCustomer">
                                <i class="ti ti-x" /> Switch to walk-in
                            </button>
                        </div>
                        <div v-if="customer" class="flex items-center gap-3">
                            <span class="w-10 h-10 rounded-full bg-(--color-primary)/10 text-(--color-primary) flex items-center justify-center text-sm font-bold">
                                {{ (customer.name || '?').slice(0, 1).toUpperCase() }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="text-sm text-(--text-heading) font-semibold truncate">{{ customer.name }}</div>
                                <div v-if="customer.email" class="text-xxs text-(--text-muted) truncate">{{ customer.email }}</div>
                            </div>
                        </div>
                        <div v-else class="relative">
                            <div class="relative">
                                <i class="ti ti-user-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                                <input v-model="customerSearch" type="search"
                                    placeholder="Type to find an existing customer..."
                                    class="form-control pl-9 text-sm" @input="onCustomerSearch" @focus="customerOpen = true" />
                            </div>
                            <div v-if="customerOpen && (customerSearch || customerSuggestions.length)"
                                class="absolute left-0 right-0 top-full mt-1 glass-card rounded-xl shadow-(--shadow-lg) overflow-hidden z-20 max-h-60 overflow-y-auto">
                                <button v-for="c in customerSuggestions" :key="c.id" type="button"
                                    class="w-full text-left px-3 py-2 text-xs hover:bg-(--bg-muted) flex items-center gap-2 border-b border-(--border-color) last:border-0"
                                    @click="selectCustomer(c)">
                                    <span class="w-7 h-7 rounded-full bg-(--color-primary)/10 text-(--color-primary) flex items-center justify-center text-xxs font-bold">
                                        {{ (c.name || '?').slice(0, 1).toUpperCase() }}
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <div class="text-(--text-heading) truncate">{{ c.name }}</div>
                                        <div v-if="c.email" class="text-xxs text-(--text-muted) truncate">{{ c.email }}</div>
                                    </div>
                                </button>
                                <div v-if="!customerSuggestions.length && customerSearch" class="px-3 py-3 text-xxs text-(--text-muted) text-center">
                                    No customers found. Sale will record as walk-in.
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Cart card -->
                    <div class="glass-card rounded-2xl overflow-hidden">
                        <header class="px-4 py-3 border-b border-(--border-color) flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="text-xxs uppercase tracking-widest text-(--text-muted) font-bold">
                                    <i class="ti ti-shopping-cart mr-1" /> Current sale
                                </span>
                                <span v-if="cart.length > 0" class="badge-soft-info text-xxs font-mono px-2 py-0.5 rounded-full">
                                    {{ cart.length }}
                                </span>
                            </div>
                            <button v-if="cart.length > 0"
                                class="text-xxs text-(--color-danger) hover:underline inline-flex items-center gap-1"
                                @click="clearCart">
                                <i class="ti ti-trash" /> Clear
                            </button>
                        </header>
                        <div class="max-h-[40vh] overflow-y-auto">
                            <div v-if="cart.length === 0" class="py-14 text-center px-4 space-y-2">
                                <i class="ti ti-scan text-3xl text-(--text-muted)" />
                                <p class="text-xs text-(--text-muted)">Scan a barcode or tap a product tile to begin.</p>
                            </div>
                            <div v-else>
                                <div v-for="(line, idx) in cart" :key="line.product.id + (line.variantId || '')"
                                    class="px-4 py-3 border-b border-(--border-color) last:border-0 flex items-start gap-3">
                                    <div class="flex-1 min-w-0">
                                        <div class="text-xs font-semibold text-(--text-heading) truncate">{{ line.product.name }}</div>
                                        <div class="text-xxs text-(--text-muted) font-mono truncate">{{ line.product.sku }}</div>
                                        <div class="text-xxs text-(--text-muted) mt-0.5 font-mono">{{ formatMoney(line.unitPrice) }} ea</div>
                                    </div>
                                    <div class="flex items-center gap-1">
                                        <button class="qty-btn" :aria-label="`Decrease ${line.product.name}`"
                                            @click="line.quantity = Math.max(1, line.quantity - 1)">
                                            <i class="ti ti-minus" />
                                        </button>
                                        <input v-model.number="line.quantity" type="number" min="1"
                                            class="form-control w-12 text-xs text-center px-1 font-mono" />
                                        <button class="qty-btn" :aria-label="`Increase ${line.product.name}`"
                                            @click="line.quantity += 1">
                                            <i class="ti ti-plus" />
                                        </button>
                                        <button class="qty-btn qty-btn-danger ml-1" :aria-label="`Remove ${line.product.name}`"
                                            @click="cart.splice(idx, 1)">
                                            <i class="ti ti-trash" />
                                        </button>
                                    </div>
                                    <div class="font-mono text-xs font-semibold text-(--text-heading) w-20 text-right tabular-nums">
                                        {{ formatMoney(line.unitPrice * line.quantity) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                        <footer class="px-4 py-3 border-t border-(--border-color) space-y-1.5 text-xs">
                            <div class="flex justify-between">
                                <span class="text-(--text-muted)">Subtotal</span>
                                <span class="font-mono tabular-nums">{{ formatMoney(subtotal) }}</span>
                            </div>
                            <div class="flex justify-between items-baseline pt-1 border-t border-dashed border-(--border-color)">
                                <span class="text-sm font-semibold text-(--text-heading)">Total</span>
                                <span class="font-mono tabular-nums font-bold text-(--color-primary) text-lg leading-none">
                                    {{ formatMoney(grandTotal) }}
                                </span>
                            </div>
                        </footer>
                    </div>

                    <!-- Charge CTA -->
                    <button class="btn btn-primary w-full inline-flex justify-center items-center gap-2 text-sm py-3.5 shadow-(--shadow-md)"
                        :disabled="cart.length === 0" @click="openPaymentModal">
                        <i class="ti ti-credit-card text-base" />
                        <span>Charge</span>
                        <span class="font-mono font-bold tabular-nums">{{ formatMoney(grandTotal) }}</span>
                        <i class="ti ti-arrow-right" />
                    </button>
                </aside>
            </div>
        </div>

        <!-- Payment modal -->
        <Teleport to="body">
            <div v-if="payModalOpen" class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4" @click.self="payModalOpen = false">
                <div class="glass-card rounded-2xl max-w-md w-full p-6 space-y-5">
                    <header class="flex items-center justify-between">
                        <div>
                            <span class="text-xxs uppercase tracking-widest text-(--text-muted) font-bold">Take payment</span>
                            <div class="text-2xl font-bold font-mono text-(--color-primary) leading-tight tabular-nums">
                                {{ formatMoney(grandTotal) }}
                            </div>
                        </div>
                        <button class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="payModalOpen = false">
                            <i class="ti ti-x" />
                        </button>
                    </header>

                    <!-- Method picker -->
                    <div>
                        <label class="text-xxs uppercase tracking-wider font-bold text-(--text-muted) block mb-2">Method</label>
                        <div class="grid grid-cols-4 gap-2">
                            <button v-for="m in METHODS" :key="m.value"
                                class="method-tile"
                                :class="payForm.method === m.value ? 'method-tile-active' : ''"
                                @click="setMethod(m.value)">
                                <i class="ti text-xl" :class="m.icon" />
                                <span class="text-xxs font-semibold">{{ m.label }}</span>
                            </button>
                        </div>
                    </div>

                    <!-- Amount + change -->
                    <div>
                        <label class="text-xxs uppercase tracking-wider font-bold text-(--text-muted) block mb-1">
                            Amount tendered
                        </label>
                        <input v-model.number="payForm.tendered" type="number" step="0.01" min="0"
                            class="form-control text-lg mt-1 font-mono text-right tabular-nums" />
                    </div>

                    <div v-if="payForm.method === 'cash' && payForm.tendered >= grandTotal"
                        class="rounded-xl p-4 text-center border border-(--color-success)/30 bg-(--color-success)/5">
                        <div class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">Change due</div>
                        <div class="text-3xl font-bold font-mono text-(--color-success) tabular-nums leading-tight mt-1">
                            {{ formatMoney(payForm.tendered - grandTotal) }}
                        </div>
                    </div>

                    <div v-if="payForm.method === 'cash' && payForm.tendered < grandTotal && payForm.tendered > 0"
                        class="rounded-xl p-3 text-center border border-(--color-warning)/30 bg-(--color-warning)/5 text-xs text-(--color-warning)">
                        <i class="ti ti-alert-triangle" /> Short by
                        <span class="font-mono font-bold tabular-nums">{{ formatMoney(grandTotal - payForm.tendered) }}</span>
                    </div>

                    <div v-if="payForm.method === 'card' || payForm.method === 'wallet'">
                        <label class="text-xxs uppercase tracking-wider font-bold text-(--text-muted) block mb-1">
                            Reference / auth code
                        </label>
                        <input v-model="payForm.reference" placeholder="Optional"
                            class="form-control text-sm mt-1 font-mono" />
                    </div>

                    <div v-if="checkoutError" class="text-xs text-(--color-danger) px-3 py-2 rounded-lg bg-(--color-danger)/10">
                        <i class="ti ti-alert-circle" /> {{ checkoutError }}
                    </div>

                    <div class="flex gap-2 pt-2">
                        <button class="btn btn-soft-secondary text-xs flex-1" @click="payModalOpen = false">Cancel</button>
                        <button class="btn btn-primary text-xs flex-1 inline-flex items-center justify-center gap-2"
                            :disabled="!canCheckout || saving" @click="checkout">
                            <i class="ti" :class="saving ? 'ti-loader animate-spin' : 'ti-circle-check'" />
                            {{ saving ? 'Processing...' : 'Complete sale' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Receipt modal -->
        <Teleport to="body">
            <div v-if="receiptOrder" class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4" @click.self="receiptOrder = null">
                <div class="glass-card rounded-2xl max-w-sm w-full p-6 space-y-4">
                    <div class="thermal-receipt">
                        <header class="text-center space-y-1 pb-2 border-b border-dashed border-(--border-color)">
                            <h3 class="text-sm font-bold">Receipt</h3>
                            <p class="text-xxs font-mono">{{ receiptOrder.orderNumber }}</p>
                            <p class="text-xxs text-(--text-muted)">{{ formatDateTime(receiptOrder.placedAt) }}</p>
                            <p class="text-xxs text-(--text-muted)">
                                Customer: <span class="text-(--text-heading)">{{ receiptCustomerLabel || 'Walk-in' }}</span>
                            </p>
                        </header>
                        <div class="py-2 space-y-1 text-xxs">
                            <div v-for="i in receiptOrder.items" :key="i.id" class="flex justify-between gap-2">
                                <div class="flex-1 min-w-0 truncate">{{ i.quantity }}x {{ i.productName }}</div>
                                <div class="font-mono">{{ formatMoney(i.lineTotal) }}</div>
                            </div>
                        </div>
                        <div class="border-t border-dashed border-(--border-color) pt-2 space-y-1 text-xxs">
                            <div class="flex justify-between"><span>Subtotal</span><span class="font-mono">{{ formatMoney(receiptOrder.subtotal) }}</span></div>
                            <div v-if="receiptOrder.taxTotal > 0" class="flex justify-between"><span>Tax</span><span class="font-mono">{{ formatMoney(receiptOrder.taxTotal) }}</span></div>
                            <div class="flex justify-between font-semibold pt-1">
                                <span>Total</span><span class="font-mono">{{ formatMoney(receiptOrder.grandTotal) }}</span>
                            </div>
                        </div>
                        <div class="border-t border-dashed border-(--border-color) pt-2 space-y-1 text-xxs">
                            <div v-for="p in receiptOrder.payments" :key="p.id" class="flex justify-between">
                                <span class="capitalize">{{ p.paymentMethod }}</span>
                                <span class="font-mono">{{ formatMoney(p.amount) }}</span>
                            </div>
                            <div v-if="lastChange > 0" class="flex justify-between font-semibold">
                                <span>Change</span><span class="font-mono">{{ formatMoney(lastChange) }}</span>
                            </div>
                        </div>
                        <p class="text-center text-xxs text-(--text-muted) pt-3">Thank you!</p>
                    </div>
                    <div class="flex gap-2 print:hidden">
                        <button class="btn btn-soft-secondary text-xs flex-1" @click="printReceipt">
                            <i class="ti ti-printer" /> Print
                        </button>
                        <button class="btn btn-primary text-xs flex-1" @click="dismissReceipt">
                            <i class="ti ti-arrow-right" /> New sale
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { usePos, type PosOrder, type PosShift } from '~/composables/usePos'
import { useApi } from '~/composables/useApi'
import { useToast } from '~/composables/useToast'

definePageMeta({ title: 'Register' })

interface ProductLite {
    id: string
    sku: string
    name: string
    unit_price: number
    image_url?: string | null
    is_active?: boolean
}

interface CartLine {
    product: ProductLite
    variantId: string | null
    unitPrice: number
    quantity: number
}

const pos = usePos()
const api = useApi()
const toast = useToast()

const METHODS = [
    { value: 'cash', label: 'Cash', icon: 'ti-cash' },
    { value: 'card', label: 'Card', icon: 'ti-credit-card' },
    { value: 'wallet', label: 'Wallet', icon: 'ti-wallet' },
    { value: 'manual', label: 'Manual', icon: 'ti-receipt' },
] as const

interface CustomerLite { id: string; name: string; email?: string | null }

const myShift = ref<PosShift | null>(null)
const otherShifts = ref<PosShift[]>([])
const shiftError = ref('')
// Defaults true so the page never flashes the no-shift hero before
// /pos/shifts/me has had a chance to resolve.
const shiftLoading = ref(true)
const products = ref<ProductLite[]>([])
const productsLoading = ref(false)
const productsError = ref('')
const search = ref('')
const cart = ref<CartLine[]>([])
const customer = ref<CustomerLite | null>(null)
const customerSearch = ref('')
const customerSuggestions = ref<CustomerLite[]>([])
const customerOpen = ref(false)
const receiptCustomerLabel = ref<string | null>(null)
const payModalOpen = ref(false)
const payForm = ref<{ method: 'cash' | 'card' | 'wallet' | 'manual'; tendered: number; reference: string }>({
    method: 'cash',
    tendered: 0,
    reference: '',
})
const saving = ref(false)
const checkoutError = ref('')
const receiptOrder = ref<PosOrder | null>(null)
const lastChange = ref(0)
const shiftClock = ref('-')

const subtotal = computed(() => cart.value.reduce((a, l) => a + l.unitPrice * l.quantity, 0))
const grandTotal = computed(() => Math.round(subtotal.value * 100) / 100)
const canCheckout = computed(() => cart.value.length > 0 && payForm.value.tendered >= grandTotal.value)
const pageHint = computed(() => {
    if (!myShift.value) return 'Open a shift before taking sales. The register engine fans out to Inventory + FMS on every checkout.'
    if (cart.value.length === 0) return 'Shift is live. Scan a barcode or tap a product to start a sale.'
    return `${cart.value.length} item${cart.value.length === 1 ? '' : 's'} in cart - choose a tender to complete the sale.`
})

import { formatDateTime } from '~/composables/useDateFormat'

const formatMoney = (n: number | null | undefined) =>
    (Number.isFinite(Number(n)) ? Number(n) : 0).toLocaleString(undefined, { style: 'currency', currency: 'USD' })

async function loadMe() {
    shiftError.value = ''
    try {
        const res = await pos.shifts.me()
        myShift.value = res.data ?? null
        if (myShift.value?.openedAt) {
            shiftClock.value = new Date(myShift.value.openedAt).toLocaleTimeString()
        }
    } catch (e: any) {
        myShift.value = null
        const status = e?.status ?? e?.response?.status
        if (status === 401) shiftError.value = 'Session expired. Sign in again to access the register.'
        else if (status === 403) shiftError.value = 'You do not have permission to read shifts (pos.shift.read).'
        else shiftError.value = e?.data?.message || 'Could not reach the shifts endpoint.'
    }
}

/**
 * Diagnostic: if /pos/shifts/me returns null, the user might still be blocked
 * by a shift opened under a different cashier. Hit /pos/shifts to surface that
 * case explicitly so the hero can guide the actor.
 */
async function loadOtherShifts() {
    otherShifts.value = []
    if (myShift.value) return
    try {
        const res = await pos.shifts.list({ status: 'open', limit: 10 })
        otherShifts.value = res.data ?? []
    } catch {
        // Non-fatal - the hero falls back to the generic "no active shift" message.
        otherShifts.value = []
    }
}

async function bootstrap() {
    shiftLoading.value = true
    try {
        await loadMe()
        await Promise.all([loadProducts(), loadOtherShifts()])
    } finally {
        shiftLoading.value = false
    }
}

let searchTimer: ReturnType<typeof setTimeout> | null = null
function onSearch() {
    if (searchTimer) clearTimeout(searchTimer)
    searchTimer = setTimeout(loadProducts, 250)
}

async function loadProducts() {
    productsLoading.value = true
    productsError.value = ''
    try {
        const qs = new URLSearchParams({ limit: '40', is_active: 'true' })
        if (search.value) qs.set('search', search.value)
        const res = await api.get<{ data: ProductLite[] }>(`products?${qs.toString()}`)
        products.value = res.data ?? []
    } catch (e: any) {
        products.value = []
        const status = e?.status ?? e?.response?.status
        if (status === 401 || status === 403) {
            productsError.value = 'You do not have permission to read the product catalogue. Ask an admin to grant `inventory.product.read`.'
        } else {
            productsError.value = e?.data?.message || 'Could not reach the product catalogue. Check the backend or refresh.'
        }
    } finally { productsLoading.value = false }
}

function addToCart(p: ProductLite) {
    const existing = cart.value.find(l => l.product.id === p.id && !l.variantId)
    if (existing) {
        existing.quantity += 1
    } else {
        cart.value.push({
            product: p,
            variantId: null,
            unitPrice: Number(p.unit_price) || 0,
            quantity: 1,
        })
    }
}

function quickAddFirst() {
    if (products.value.length > 0) {
        addToCart(products.value[0])
        search.value = ''
        loadProducts()
    }
}

function clearCart() {
    cart.value = []
}

let customerTimer: ReturnType<typeof setTimeout> | null = null
function onCustomerSearch() {
    if (customerTimer) clearTimeout(customerTimer)
    customerTimer = setTimeout(loadCustomers, 220)
}

async function loadCustomers() {
    const term = customerSearch.value.trim()
    if (!term) {
        customerSuggestions.value = []
        return
    }
    try {
        const qs = new URLSearchParams({ search: term, limit: '10' })
        const res = await api.get<{ data: CustomerLite[] }>(`customers?${qs.toString()}`)
        customerSuggestions.value = res.data ?? []
    } catch {
        customerSuggestions.value = []
    }
}

function selectCustomer(c: CustomerLite) {
    customer.value = c
    customerSearch.value = ''
    customerSuggestions.value = []
    customerOpen.value = false
}

function clearCustomer() {
    customer.value = null
    customerSearch.value = ''
    customerSuggestions.value = []
    customerOpen.value = false
}

// Close customer suggestion dropdown when clicking outside the picker.
function dismissCustomerDropdown(ev: MouseEvent) {
    const target = ev.target as HTMLElement | null
    if (!target?.closest('.glass-card')) {
        customerOpen.value = false
    }
}
if (import.meta.client) {
    document.addEventListener('click', dismissCustomerDropdown)
}

function setMethod(m: typeof METHODS[number]['value']) {
    payForm.value.method = m
    // For card/wallet/manual, default tendered to exact amount
    if (m !== 'cash') {
        payForm.value.tendered = grandTotal.value
    }
}

function openPaymentModal() {
    payForm.value = { method: 'cash', tendered: grandTotal.value, reference: '' }
    checkoutError.value = ''
    payModalOpen.value = true
}

async function checkout() {
    if (!myShift.value || !canCheckout.value) return
    saving.value = true
    checkoutError.value = ''
    try {
        const payload = {
            shift_id: myShift.value.id,
            client_uuid: randomUUID(),
            customer_id: customer.value?.id ?? null,
            items: cart.value.map(l => ({
                product_id: l.product.id,
                variant_id: l.variantId || undefined,
                quantity: l.quantity,
                unit_price: l.unitPrice,
            })),
            payments: [{
                payment_method: payForm.value.method,
                amount: grandTotal.value,
                tendered: payForm.value.method === 'cash' ? payForm.value.tendered : undefined,
                reference_number: (payForm.value.method === 'card' || payForm.value.method === 'wallet')
                    ? (payForm.value.reference || undefined)
                    : undefined,
            }],
        }
        const res = await pos.orders.checkout(payload as any)
        receiptOrder.value = res.data
        receiptCustomerLabel.value = customer.value?.name ?? null
        lastChange.value = payForm.value.method === 'cash' ? Math.max(0, payForm.value.tendered - grandTotal.value) : 0
        payModalOpen.value = false
        cart.value = []
        customer.value = null
        toast.success('Sale complete', res.data.orderNumber)
    } catch (e: any) {
        checkoutError.value = e?.data?.message || 'Checkout failed.'
    } finally {
        saving.value = false
    }
}

function dismissReceipt() {
    receiptOrder.value = null
    lastChange.value = 0
}

function printReceipt() {
    window.print()
}

onMounted(bootstrap)
</script>

<style scoped>
/* Cart quantity controls */
.qty-btn {
    width: 28px; height: 28px;
    display: inline-flex; align-items: center; justify-content: center;
    border: 1px solid var(--border-color); border-radius: 8px;
    background: var(--bg-card); color: var(--text-body);
    transition: background 0.12s ease, color 0.12s ease, border-color 0.12s ease, transform 0.12s ease;
}
.qty-btn:hover { background: var(--bg-muted); transform: translateY(-1px); }
.qty-btn-danger { color: var(--color-danger); }
.qty-btn-danger:hover { background: rgb(var(--color-danger-rgb) / 0.1); }

/* Catalogue tile (touch product) */
.product-tile {
    border: 1px solid var(--border-color);
    transition: border-color 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
}
.product-tile:hover {
    border-color: var(--color-primary);
    transform: translateY(-2px);
    box-shadow: 0 6px 16px -6px rgb(var(--color-primary-rgb) / 0.25);
}
.product-tile:active { transform: translateY(0); }

/* Payment method tile (touch-friendly larger target) */
.method-tile {
    display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 4px;
    padding: 14px 8px; border-radius: 12px;
    border: 1px solid var(--border-color);
    background: var(--bg-card); color: var(--text-body);
    cursor: pointer;
    transition: border-color 0.15s ease, background 0.15s ease, color 0.15s ease, transform 0.15s ease;
}
.method-tile:hover {
    border-color: var(--color-primary);
    background: rgb(var(--color-primary-rgb) / 0.04);
    transform: translateY(-1px);
}
.method-tile-active {
    border-color: var(--color-primary);
    background: rgb(var(--color-primary-rgb) / 0.08);
    color: var(--color-primary);
    box-shadow: 0 0 0 3px rgb(var(--color-primary-rgb) / 0.15);
}

/* Action trigger (kebab / reload) */
.action-trigger {
    color: var(--text-muted);
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    transition: background 0.12s ease, color 0.12s ease, border-color 0.12s ease;
}
.action-trigger:hover { background: var(--bg-muted); color: var(--text-heading); border-color: var(--color-primary); }

/* Print: only the .thermal-receipt block renders; everything else hides. */
@media print {
    :deep(body *) { visibility: hidden; }
    .thermal-receipt, .thermal-receipt * { visibility: visible; }
    .thermal-receipt {
        position: absolute; left: 0; top: 0;
        width: 80mm; padding: 4mm;
        font-family: 'JetBrains Mono', ui-monospace, monospace;
        color: #000; background: #fff;
    }
}
</style>
