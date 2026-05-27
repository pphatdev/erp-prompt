<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Page header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div class="flex items-center gap-3">
                    <NuxtLink to="/sales/quotations"
                        class="w-9 h-9 rounded-lg border border-(--border-color) bg-(--bg-card) hover:bg-(--bg-muted) flex items-center justify-center text-(--text-muted) hover:text-(--color-primary) transition-colors"
                        title="Back to quotations">
                        <i class="ti ti-arrow-left" />
                    </NuxtLink>
                    <div>
                        <h1 class="text-xl font-semibold">New quotation</h1>
                        <p class="text-xs text-(--text-muted) mt-0.5">Build a quote, confirm it, then convert it to a
                            sales order.</p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <NuxtLink to="/sales/quotations" class="btn btn-ghost text-xs">
                        <i class="ti ti-x" />Cancel
                    </NuxtLink>
                    <button type="button" class="btn btn-primary text-xs" :disabled="submitting || !canSubmit"
                        @click="submit">
                        <i :class="['ti', submitting ? 'ti-loader-2 animate-spin' : 'ti-device-floppy']" />
                        {{ submitting ? 'Saving…' : 'Create quotation' }}
                    </button>
                </div>
            </header>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- ── Main form ── -->
                <section class="glass-card rounded-2xl p-6 lg:col-span-2 space-y-6">

                    <!-- Source: Qualified Lead / Opportunity -->
                    <div>
                        <h2 class="section-heading"><i class="ti ti-target" />Source</h2>
                        <div class="grid grid-cols-1 gap-4 mt-4">
                            <div>
                                <label class="form-label">
                                    From Qualified Lead / Opportunity
                                    <span class="text-(--text-muted) normal-case font-normal lowercase">— optional, pre-fills items + customer · shows only Won (Qualified) opportunities</span>
                                </label>
                                <select v-model="form.from_opportunity_id" class="form-control"
                                    @change="onOpportunityChange" :disabled="loadingSchedule">
                                    <option :value="null">— Standalone quotation (no lead) —</option>
                                    <option v-for="o in selectableOpportunities" :key="o.id" :value="o.id">
                                        {{ o.title }}
                                        <template v-if="o.lead">· Lead: {{ o.lead.title }}</template>
                                        <template v-else-if="o.customer">· {{ o.customer.name }}</template>
                                        <template v-else>· No account yet</template>
                                    </option>
                                </select>
                                <p v-if="!loadingSchedule && selectableOpportunities.length === 0 && opportunities.length > 0"
                                    class="text-xxs text-(--text-muted) mt-1">
                                    <i class="ti ti-info-circle" />
                                    No qualified opportunities yet — drag a deal to the
                                    <strong>Won (Qualified)</strong> column in the Sales Pipeline first.
                                </p>
                                <p v-if="loadingSchedule" class="text-xxs text-(--text-muted) mt-1">
                                    <i class="ti ti-loader-2 animate-spin" /> Loading product schedule…
                                </p>
                                <p v-else-if="form.from_opportunity_id && scheduleLineCount > 0"
                                    class="text-xxs text-(--color-success) mt-1">
                                    <i class="ti ti-circle-check-filled" />
                                    Pre-filled {{ scheduleLineCount }} line(s) from the Opportunity's B2B Product Schedule.
                                </p>
                                <p v-else-if="form.from_opportunity_id && scheduleLineCount === 0"
                                    class="text-xxs text-(--color-warning) mt-1">
                                    <i class="ti ti-info-circle" />
                                    This Opportunity has no Product Schedule yet — add line items below manually.
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Customer & dates -->
                    <div>
                        <h2 class="section-heading"><i class="ti ti-users" />Customer & dates</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                            <div class="md:col-span-2">
                                <label class="form-label">
                                    Customer<span v-if="!form.from_opportunity_id"> *</span>
                                    <span v-if="form.from_opportunity_id"
                                        class="text-(--text-muted) normal-case font-normal lowercase">— optional, created on Quotation Won if blank</span>
                                </label>
                                <select v-model="form.customer_id" class="form-control"
                                    :class="{ 'ring-1 ring-(--color-danger)': showErrors && !canSubmit && !form.customer_id && !form.from_opportunity_id }">
                                    <option value="">{{ form.from_opportunity_id ? '— No account yet (prospect) —' : 'Select customer…' }}</option>
                                    <option v-for="c in customers" :key="c.id" :value="c.id">{{ c.name }}</option>
                                </select>
                                <p v-if="showErrors && !form.customer_id && !form.from_opportunity_id" class="form-error">
                                    Pick a Customer or a Qualified Lead.
                                </p>
                            </div>
                            <div>
                                <label class="form-label">Quote date</label>
                                <input v-model="form.quote_date" type="date" class="form-control" />
                            </div>
                            <div>
                                <label class="form-label">Valid until</label>
                                <input v-model="form.valid_until" type="date" class="form-control" />
                            </div>
                            <div>
                                <label class="form-label">Due date</label>
                                <input v-model="form.due_date" type="date" class="form-control" />
                            </div>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <h2 class="section-heading"><i class="ti ti-notes" />Notes</h2>
                        <textarea v-model="form.notes" rows="3" class="form-control mt-4"
                            placeholder="Optional context or terms for the customer…" />
                    </div>

                    <!-- Line items -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <h2 class="section-heading"><i class="ti ti-list-details" />Line items</h2>
                            <button type="button" class="btn btn-ghost text-xxs" @click="addLine">
                                <i class="ti ti-plus" />Add line
                            </button>
                        </div>

                        <div v-if="showErrors && form.items.length === 0"
                            class="rounded-lg bg-(--color-danger-subtle)/40 border border-(--color-danger)/20 px-4 py-2 text-xs text-(--color-danger) mb-3">
                            At least one line item is required.
                        </div>

                        <div class="space-y-3">
                            <div v-for="(line, idx) in form.items" :key="idx"
                                class="rounded-xl border border-(--border-color) bg-(--bg-muted)/40 p-4 space-y-3">
                                <!-- Product & variant row -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div>
                                        <label class="form-label">Product *</label>
                                        <select v-model="line.product_id" class="form-control text-xs"
                                            :class="{ 'ring-1 ring-(--color-danger)': showErrors && !line.product_id }"
                                            @change="onProductChange(idx)">
                                            <option value="" disabled>Select product…</option>
                                            <option v-for="p in products" :key="p.id" :value="p.id">
                                                {{ p.name }}
                                            </option>
                                        </select>
                                    </div>
                                    <div v-if="variantsFor(line.product_id).length">
                                        <label class="form-label">Variant</label>
                                        <select v-model="line.variant_id" class="form-control text-xs"
                                            @change="onVariantChange(idx)">
                                            <option :value="null">— default —</option>
                                            <option v-for="v in variantsFor(line.product_id)" :key="v.id" :value="v.id">
                                                {{ v.name }} · {{ fmt(v.unit_price) }}
                                            </option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Qty / price / line total -->
                                <div class="grid grid-cols-3 md:grid-cols-4 gap-3 items-end">
                                    <div>
                                        <label class="form-label">Qty *</label>
                                        <input v-model.number="line.quantity" type="number" min="0.01" step="0.01"
                                            class="form-control text-xs"
                                            :class="{ 'ring-1 ring-(--color-danger)': showErrors && !(line.quantity > 0) }" />
                                    </div>
                                    <div>
                                        <label class="form-label">Unit price</label>
                                        <input v-model.number="line.unit_price" type="number" min="0" step="0.01"
                                            class="form-control text-xs" />
                                    </div>
                                    <div>
                                        <label class="form-label">Discount %</label>
                                        <input v-model.number="line.discount_pct" type="number" min="0" max="100"
                                            step="0.1" class="form-control text-xs" placeholder="0" />
                                    </div>
                                    <div class="text-right">
                                        <label class="form-label">Line total</label>
                                        <p class="text-sm font-semibold text-(--text-heading) py-2 font-mono">
                                            {{ fmt(lineTotal(line)) }}
                                        </p>
                                    </div>
                                </div>

                                <!-- Notes -->
                                <div class="flex items-start gap-3">
                                    <div class="flex-1">
                                        <label class="form-label">Line note</label>
                                        <input v-model="line.notes" type="text" class="form-control text-xs"
                                            placeholder="Optional note for this line…" />
                                    </div>
                                    <button type="button"
                                        class="mt-5 text-xxs text-(--color-danger) hover:bg-(--color-danger-subtle) px-2 py-1.5 rounded-lg transition-colors flex items-center gap-1"
                                        @click="removeLine(idx)">
                                        <i class="ti ti-trash" />Remove
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Empty state -->
                        <div v-if="form.items.length === 0"
                            class="rounded-xl border-2 border-dashed border-(--border-color) py-10 text-center">
                            <i class="ti ti-package-off text-3xl text-(--text-muted)" />
                            <p class="text-xs text-(--text-muted) mt-2">No lines yet — click <strong>Add line</strong>
                                to start.</p>
                        </div>
                    </div>
                </section>

                <!-- ── Sidebar ── -->
                <aside class="space-y-4">
                    <!-- Summary card -->
                    <div class="glass-card rounded-2xl p-5">
                        <h3 class="text-xs font-bold text-(--text-heading) uppercase tracking-widest mb-4">
                            <i class="ti ti-calculator mr-1" />Quote summary
                        </h3>
                        <dl class="space-y-2 text-xs">
                            <div class="flex justify-between">
                                <dt class="text-(--text-muted)">Lines</dt>
                                <dd class="font-semibold">{{ form.items.length }}</dd>
                            </div>
                            <div class="flex justify-between">
                                <dt class="text-(--text-muted)">Subtotal</dt>
                                <dd class="font-semibold font-mono">{{ fmt(grandTotal) }}</dd>
                            </div>
                            <div class="flex justify-between border-t border-(--border-color) pt-2 mt-2">
                                <dt class="font-bold text-(--text-heading)">Total</dt>
                                <dd class="text-base font-bold text-(--text-heading) font-mono">{{ fmt(grandTotal) }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Checklist -->
                    <div class="glass-card rounded-2xl p-5">
                        <h3 class="text-xs font-bold text-(--text-heading) uppercase tracking-widest mb-3">
                            <i class="ti ti-checklist mr-1" />Required
                        </h3>
                        <ul class="space-y-2">
                            <li class="flex items-center gap-2 text-xs"
                                :class="(form.customer_id || form.from_opportunity_id) ? 'text-(--color-success)' : 'text-(--text-muted)'">
                                <i :class="['ti', (form.customer_id || form.from_opportunity_id) ? 'ti-circle-check-filled' : 'ti-circle-dashed']" />
                                Customer or qualified lead selected
                            </li>
                            <li class="flex items-center gap-2 text-xs"
                                :class="form.items.length > 0 ? 'text-(--color-success)' : 'text-(--text-muted)'">
                                <i
                                    :class="['ti', form.items.length > 0 ? 'ti-circle-check-filled' : 'ti-circle-dashed']" />
                                At least one line
                            </li>
                            <li class="flex items-center gap-2 text-xs"
                                :class="allLinesValid ? 'text-(--color-success)' : 'text-(--text-muted)'">
                                <i :class="['ti', allLinesValid ? 'ti-circle-check-filled' : 'ti-circle-dashed']" />
                                All lines valid
                            </li>
                        </ul>
                    </div>

                    <!-- Customer preview -->
                    <div v-if="selectedCustomer" class="glass-card rounded-2xl p-5">
                        <h3 class="text-xs font-bold text-(--text-heading) uppercase tracking-widest mb-3">
                            <i class="ti ti-user mr-1" />Customer
                        </h3>
                        <p class="text-sm font-semibold text-(--text-heading)">{{ selectedCustomer.name }}</p>
                        <p v-if="selectedCustomer.email" class="text-xxs text-(--text-muted) mt-0.5">
                            {{ selectedCustomer.email }}
                        </p>
                    </div>
                </aside>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useCrm } from '~/composables/useCrm'
import { useSales } from '~/composables/useSales'
import { useToast } from '~/composables/useToast'
import type { CreateQuotationItemPayload, CustomerLite, ProductLite } from '~/types/sales'
import type { Opportunity } from '~/types/crm'

definePageMeta({ breadcrumb: 'New quotation' })

const router = useRouter()
const sales = useSales()
const crm = useCrm()
const toast = useToast()

const submitting = ref(false)
const showErrors = ref(false)
const loadingSchedule = ref(false)
const customers = ref<CustomerLite[]>([])
const products = ref<ProductLite[]>([])
const opportunities = ref<Opportunity[]>([])

const form = reactive<{
    customer_id: string
    from_opportunity_id: string | null
    quote_date: string
    valid_until: string
    due_date: string
    notes: string
    items: (CreateQuotationItemPayload & { unit_price?: number | null; discount_pct?: number | null; notes?: string | null })[]
}>({
    customer_id: '',
    from_opportunity_id: null,
    quote_date: '',
    valid_until: '',
    due_date: '',
    notes: '',
    items: [],
})

// Only Won (Qualified) opportunities surface here. In this flow:
//   Opportunity Won  = lead is qualified, ready to quote
//   Quotation Won    = deal is closed (Customer + Sale Order created)
// So the rep picks a qualified opportunity and produces a draft Quotation
// from its B2B/B2C Product Schedule.
const selectableOpportunities = computed(() =>
    opportunities.value.filter(o => o.stage === 'won')
)

const scheduleLineCount = computed(() => form.items.length)

const selectedCustomer = computed(() =>
    customers.value.find(c => c.id === form.customer_id) ?? null
)

const variantsFor = (productId: string) =>
    products.value.find(p => p.id === productId)?.variants ?? []

const lineTotal = (line: { quantity?: number; unit_price?: number | null; discount_pct?: number | null }) => {
    const base = (line.quantity || 0) * (line.unit_price || 0)
    const disc = (line.discount_pct || 0) / 100
    return base * (1 - disc)
}

const grandTotal = computed(() => form.items.reduce((sum, l) => sum + lineTotal(l), 0))

const allLinesValid = computed(() =>
    form.items.length > 0 && form.items.every(l => !!l.product_id && (l.quantity || 0) > 0)
)

// Backend requires either customer_id OR from_opportunity_id. The Sales-side
// QuotationService::win creates the Customer from the linked Lead later if
// the quotation goes out without one.
const canSubmit = computed(() =>
    (!!form.customer_id || !!form.from_opportunity_id) && allLinesValid.value
)

const fmt = (v: number) =>
    new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v || 0)

const addLine = () => {
    form.items.push({ product_id: '', variant_id: null, quantity: 1, unit_price: null, discount_pct: null, notes: null })
}

const removeLine = (idx: number) => { form.items.splice(idx, 1) }

/**
 * Picking an Opportunity auto-fills the customer (if linked) and replaces
 * line items with the snapshot of its B2B/B2C Product Schedule. Choosing
 * "Standalone" clears the link but keeps any items the user has entered.
 */
const onOpportunityChange = async () => {
    if (!form.from_opportunity_id) return

    const opp = opportunities.value.find(o => o.id === form.from_opportunity_id)
    if (opp?.customerId) form.customer_id = opp.customerId

    loadingSchedule.value = true
    try {
        const res = await crm.opportunities.listSchedule(form.from_opportunity_id)
        if (res.data.length > 0) {
            form.items = res.data.map(line => ({
                product_id: line.productId,
                variant_id: line.variantId ?? null,
                quantity: line.quantity,
                unit_price: line.estimatedUnitPrice,
                discount_pct: null,
                notes: line.notes ?? null,
            }))
        } else if (form.items.length === 0) {
            // Keep at least one empty row so the user can start typing.
            addLine()
        }
    } catch (err: any) {
        toast.error('Failed to load product schedule', err?.data?.message)
    } finally {
        loadingSchedule.value = false
    }
}

const onProductChange = (idx: number) => {
    const line = form.items[idx]
    const product = products.value.find(p => p.id === line.product_id)
    line.variant_id = null
    line.unit_price = product?.unit_price ?? null
}

const onVariantChange = (idx: number) => {
    const line = form.items[idx]
    const variant = variantsFor(line.product_id).find(v => v.id === line.variant_id)
    if (variant) line.unit_price = variant.unit_price
}

const submit = async () => {
    showErrors.value = true
    if (!canSubmit.value) return
    submitting.value = true
    try {
        // Backend accepts customer_id OR from_opportunity_id (or both). Send
        // only what's set so empty strings don't trip the uuid|exists rules.
        const payload = {
            customer_id: form.customer_id || undefined,
            from_opportunity_id: form.from_opportunity_id || undefined,
            quote_date: form.quote_date || undefined,
            valid_until: form.valid_until || undefined,
            due_date: form.due_date || undefined,
            notes: form.notes || undefined,
            items: form.items.map(l => ({
                product_id: l.product_id,
                variant_id: l.variant_id || undefined,
                quantity: l.quantity,
                unit_price: l.unit_price ?? undefined,
                discount_pct: l.discount_pct ?? undefined,
                notes: l.notes || undefined,
            })),
        }
        const res = await sales.quotations.create(payload)
        toast.success('Quotation created', res.data.quoteNumber)
        router.push(`/sales/quotations/${res.data.id}`)
    } catch (err: any) {
        toast.error('Failed to create quotation', err?.data?.message || 'Check the form and try again.')
    } finally {
        submitting.value = false
    }
}

onMounted(async () => {
    try {
        const [cRes, pRes, oRes] = await Promise.all([
            sales.catalogue.listCustomers(),
            sales.catalogue.listProducts(),
            crm.opportunities.list({ limit: 150 }),
        ])
        customers.value = cRes.data
        products.value = pRes.data
        opportunities.value = oRes.data
        addLine()
    } catch (err: any) {
        toast.error('Failed to load data', err?.data?.message)
    }
})
</script>

<style scoped>
.section-heading {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    color: var(--text-heading);
}

.form-label {
    display: block;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-muted);
    margin-bottom: 0.375rem;
}

.form-error {
    font-size: 0.65rem;
    color: var(--color-danger);
    margin-top: 0.25rem;
}
</style>
