<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">New Purchase Order</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Drafts can be saved and edited later. Submitting routes to eApprovals if a workflow is configured.</p>
                </div>
                <NuxtLink to="/inventory/purchase-orders" class="btn btn-ghost text-xs">
                    <i class="ti ti-arrow-left" />Cancel
                </NuxtLink>
            </header>

            <!-- PO header form -->
            <section class="glass-card rounded-2xl p-5 space-y-4">
                <h3 class="text-xs font-bold text-(--text-heading) uppercase tracking-wider">Order Details</h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Supplier *</label>
                        <select v-model="form.supplier_id" required class="form-control text-xs">
                            <option value="">-- Select supplier --</option>
                            <option v-for="s in activeSuppliers" :key="s.id" :value="s.id">
                                {{ s.name }}<template v-if="s.code"> ({{ s.code }})</template>
                            </option>
                        </select>
                    </div>
                    <div class="space-y-1">
                        <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Receive Into Warehouse *</label>
                        <select v-model="form.warehouse_id" required class="form-control text-xs">
                            <option value="">-- Select warehouse --</option>
                            <option v-for="w in activeWarehouses" :key="w.id" :value="w.id">
                                {{ w.name }} ({{ w.code }})
                            </option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Order Date</label>
                        <input v-model="form.order_date" type="date" class="form-control text-xs" />
                    </div>
                    <div class="space-y-1">
                        <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Expected Delivery</label>
                        <input v-model="form.expected_at" type="date" :min="form.order_date || undefined"
                            class="form-control text-xs" />
                    </div>
                </div>

                <div class="space-y-1">
                    <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Notes</label>
                    <textarea v-model="form.notes" rows="2" maxlength="2000" placeholder="Special instructions, internal reference..."
                        class="form-control text-xs resize-none" />
                </div>
            </section>

            <!-- Line items -->
            <section class="glass-card rounded-2xl p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <h3 class="text-xs font-bold text-(--text-heading) uppercase tracking-wider">Line Items ({{ form.items.length }})</h3>
                    <button type="button" class="btn btn-ghost text-xs" @click="addLine">
                        <i class="ti ti-plus" />Add Line
                    </button>
                </div>

                <div v-if="form.items.length === 0" class="py-8 text-center text-xs text-(--text-muted)">
                    No line items yet — add one to build the purchase order.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="w-full text-xs">
                        <thead class="text-xxs uppercase tracking-widest font-bold text-(--text-muted)">
                            <tr>
                                <th class="text-left px-2 py-2">Product</th>
                                <th class="text-left px-2 py-2 hidden md:table-cell">Variant</th>
                                <th class="text-right px-2 py-2 w-24">Qty</th>
                                <th class="text-right px-2 py-2 w-32">Unit Cost</th>
                                <th class="text-right px-2 py-2 w-32">Line Total</th>
                                <th class="px-2 py-2 w-10" />
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="(line, idx) in form.items" :key="idx"
                                class="border-t border-(--border-color)/60 align-top">
                                <td class="px-2 py-2">
                                    <select v-model="line.product_id" class="form-control text-xs"
                                        @change="onProductChange(line)">
                                        <option value="">-- Product --</option>
                                        <option v-for="p in products" :key="p.id" :value="p.id">
                                            {{ p.name }} ({{ p.sku }})
                                        </option>
                                    </select>
                                    <input v-model="line.notes" type="text" placeholder="Line note (optional)"
                                        maxlength="500" class="form-control text-xxs mt-1" />
                                </td>
                                <td class="px-2 py-2 hidden md:table-cell">
                                    <select v-model="line.variant_id"
                                        class="form-control text-xs"
                                        :disabled="!variantsFor(line.product_id).length">
                                        <option :value="null">— Base SKU —</option>
                                        <option v-for="v in variantsFor(line.product_id)" :key="v.id" :value="v.id">
                                            {{ v.name }} ({{ v.sku }})
                                        </option>
                                    </select>
                                </td>
                                <td class="px-2 py-2 text-right">
                                    <input v-model.number="line.ordered_qty" type="number" min="0.01" step="0.01"
                                        class="form-control text-xs text-right" />
                                </td>
                                <td class="px-2 py-2 text-right">
                                    <input v-model.number="line.unit_cost" type="number" min="0" step="0.01"
                                        class="form-control text-xs text-right" />
                                </td>
                                <td class="px-2 py-2 text-right font-mono font-semibold">
                                    {{ formatCurrency(lineTotal(line)) }}
                                </td>
                                <td class="px-2 py-2 text-right">
                                    <button type="button" class="action-btn action-btn-danger" title="Remove"
                                        @click="removeLine(idx)">
                                        <i class="ti ti-trash" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr class="border-t-2 border-(--border-color)">
                                <td colspan="4" class="px-2 py-3 text-right text-xs font-bold uppercase tracking-wider text-(--text-muted)">
                                    Subtotal
                                </td>
                                <td class="px-2 py-3 text-right font-mono font-bold text-(--text-heading) text-sm">
                                    {{ formatCurrency(subtotal) }}
                                </td>
                                <td />
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </section>

            <!-- Action footer -->
            <footer class="flex flex-col sm:flex-row justify-end gap-2">
                <NuxtLink to="/inventory/purchase-orders" class="btn btn-ghost text-xs">Discard</NuxtLink>
                <button type="button" class="btn btn-secondary text-xs" :disabled="!canSave || saving"
                    @click="save(false)">
                    <i v-if="saving && intent === 'draft'" class="ti ti-loader-2 animate-spin" />
                    Save Draft
                </button>
                <button type="button" class="btn btn-primary text-xs" :disabled="!canSave || saving"
                    @click="save(true)">
                    <i v-if="saving && intent === 'submit'" class="ti ti-loader-2 animate-spin" />
                    Save & Submit for Approval
                </button>
            </footer>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useInventory } from '~/composables/useInventory'
import { useToast } from '~/composables/useToast'
import type {
    CreatePurchaseOrderItemPayload,
    CreatePurchaseOrderPayload,
    InventoryProduct,
    Supplier,
    Warehouse,
} from '~/types/inventory'

definePageMeta({ breadcrumb: 'New PO' })

const router = useRouter()
const inventory = useInventory()
const toast = useToast()

const saving = ref(false)
const intent = ref<'draft' | 'submit' | null>(null)

const suppliers = ref<Supplier[]>([])
const warehouses = ref<Warehouse[]>([])
const products = ref<InventoryProduct[]>([])

const activeSuppliers = computed(() => suppliers.value.filter(s => s.isActive))
const activeWarehouses = computed(() => warehouses.value.filter(w => w.isActive))

type LineRow = CreatePurchaseOrderItemPayload & {
    ordered_qty: number
    unit_cost: number | null
}

const form = reactive<CreatePurchaseOrderPayload & { items: LineRow[] }>({
    supplier_id: '',
    warehouse_id: '',
    order_date: new Date().toISOString().slice(0, 10),
    expected_at: null,
    notes: null,
    items: [],
})

const addLine = () => {
    form.items.push({
        product_id: '',
        variant_id: null,
        ordered_qty: 1,
        unit_cost: null,
        notes: null,
    })
}

const removeLine = (idx: number) => { form.items.splice(idx, 1) }

const variantsFor = (productId: string) =>
    products.value.find(p => p.id === productId)?.variants ?? []

const onProductChange = (line: LineRow) => {
    line.variant_id = null
    if (line.unit_cost === null || line.unit_cost === 0) {
        const product = products.value.find(p => p.id === line.product_id)
        if (product) line.unit_cost = Number(product.unit_price) || 0
    }
}

const lineTotal = (line: LineRow) =>
    (Number(line.ordered_qty) || 0) * (Number(line.unit_cost) || 0)

const subtotal = computed(() => form.items.reduce((sum, l) => sum + lineTotal(l), 0))

const allLinesValid = computed(() =>
    form.items.length > 0 &&
    form.items.every(l => !!l.product_id && (Number(l.ordered_qty) || 0) > 0)
)

const canSave = computed(() =>
    !!form.supplier_id && !!form.warehouse_id && allLinesValid.value
)

const formatCurrency = (v: number) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(Number(v) || 0)

const save = async (submitAfter: boolean) => {
    if (!canSave.value) return
    saving.value = true
    intent.value = submitAfter ? 'submit' : 'draft'
    try {
        const payload: CreatePurchaseOrderPayload = {
            supplier_id: form.supplier_id,
            warehouse_id: form.warehouse_id,
            order_date: form.order_date || null,
            expected_at: form.expected_at || null,
            notes: form.notes || null,
            items: form.items.map(l => ({
                product_id: l.product_id,
                variant_id: l.variant_id || null,
                ordered_qty: Number(l.ordered_qty),
                unit_cost: l.unit_cost !== null ? Number(l.unit_cost) : null,
                notes: l.notes || null,
            })),
        }

        const res = await inventory.purchaseOrders.create(payload)
        const created = res.data

        if (submitAfter) {
            try {
                await inventory.purchaseOrders.submit(created.id)
                toast.success('PO created and submitted', created.poNumber)
            } catch (err: any) {
                toast.warning('PO saved but submit failed', err?.data?.message)
            }
        } else {
            toast.success('PO draft saved', created.poNumber)
        }

        router.push(`/inventory/purchase-orders/${created.id}`)
    } catch (err: any) {
        toast.error('Failed to create PO', err?.data?.message)
    } finally {
        saving.value = false
        intent.value = null
    }
}

onMounted(async () => {
    try {
        const [sRes, wRes, pRes] = await Promise.all([
            inventory.suppliers.list({ limit: 200, is_active: true }),
            inventory.warehouses.list({ limit: 200, is_active: true }),
            inventory.catalogue.listProducts({ limit: 200, is_active: true }),
        ])
        suppliers.value = sRes.data
        warehouses.value = wRes.data
        products.value = pRes.data
        addLine()
    } catch (err: any) {
        toast.error('Failed to load catalogue', err?.data?.message)
    }
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

.action-btn-danger:hover {
    color: var(--color-danger);
    border-color: rgb(var(--color-danger-rgb) / 0.4);
}
</style>
