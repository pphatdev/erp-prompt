<template>
    <div class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="glass-card rounded-2xl w-full max-w-3xl bg-(--bg-card) shadow-(--shadow-lg) flex flex-col max-h-[90vh]">
            <header class="flex items-center justify-between p-5 border-b border-(--border-color) shrink-0">
                <div class="min-w-0">
                    <h3 class="font-semibold text-sm truncate">B2B Product Schedule — {{ opportunity.title }}</h3>
                    <p class="text-xxs text-(--text-muted) mt-0.5">
                        Prospect's products-of-interest. Snapshotted into the Quotation when the Opportunity is won.
                    </p>
                </div>
                <button class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="$emit('close')">
                    <i class="ti ti-x" />
                </button>
            </header>

            <div class="p-5 overflow-y-auto space-y-4">
                <!-- Terminal-stage warning -->
                <div v-if="locked" class="px-3 py-2 rounded-lg bg-(--color-warning-subtle) text-(--color-warning) text-xxs">
                    <i class="ti ti-lock" />
                    Opportunity is <strong>{{ opportunity.stage }}</strong>. Schedule is read-only.
                </div>

                <div v-if="loading" class="py-12 flex justify-center">
                    <span class="w-6 h-6 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                </div>

                <div v-else-if="lines.length === 0 && !locked"
                    class="rounded-xl border border-dashed border-(--border-color) text-(--text-muted) text-xs py-8 text-center">
                    No lines yet. Use the form below to add one.
                </div>

                <!-- Line item table -->
                <div v-if="lines.length > 0" class="overflow-x-auto -mx-5">
                    <table class="w-full text-xs">
                        <thead class="text-xxs uppercase tracking-widest text-(--text-muted) border-b border-(--border-color)">
                            <tr>
                                <th class="text-left px-5 py-2">Product</th>
                                <th class="text-left px-2 py-2">Variant</th>
                                <th class="text-right px-2 py-2">Qty</th>
                                <th class="text-right px-2 py-2">Est. Price</th>
                                <th class="text-left px-2 py-2">Cadence</th>
                                <th class="text-right px-5 py-2 w-12"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="line in lines" :key="line.id" class="border-b border-(--border-color)/40">
                                <td class="px-5 py-3 text-(--text-heading) font-medium">{{ line.productName || productName(line.productId) }}</td>
                                <td class="px-2 py-3 font-mono text-xxs">{{ line.variantSku || '—' }}</td>
                                <td class="px-2 py-3 text-right font-mono">{{ line.quantity }}</td>
                                <td class="px-2 py-3 text-right font-mono">{{ fmt(line.estimatedUnitPrice) }}</td>
                                <td class="px-2 py-3 capitalize">{{ line.cadence.replace('_', ' ') }}</td>
                                <td class="px-5 py-3 text-right">
                                    <button v-if="!locked" class="mini-action-btn hover:text-(--color-danger)"
                                        :disabled="acting" title="Remove" @click="removeLine(line)">
                                        <i class="ti ti-trash text-[12px]" />
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Add line form -->
                <form v-if="!locked" class="rounded-xl border border-(--border-color) bg-(--bg-muted)/40 p-4 space-y-3"
                    @submit.prevent="addLine">
                    <h4 class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">Add line</h4>
                    <div class="grid grid-cols-1 md:grid-cols-6 gap-3">
                        <div class="md:col-span-2">
                            <label class="form-label">Product *</label>
                            <select v-model="form.product_id" class="form-control text-xs" required>
                                <option value="" disabled>Select…</option>
                                <option v-for="p in catalogue" :key="p.id" :value="p.id">
                                    {{ p.name }} ({{ p.product_type }})
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Qty</label>
                            <input v-model.number="form.quantity" type="number" min="0.01" step="0.01"
                                class="form-control text-xs" />
                        </div>
                        <div>
                            <label class="form-label">Est. price</label>
                            <input v-model.number="form.estimated_unit_price" type="number" min="0" step="0.01"
                                class="form-control text-xs" placeholder="Auto" />
                        </div>
                        <div>
                            <label class="form-label">Cadence</label>
                            <select v-model="form.cadence" class="form-control text-xs">
                                <option value="one_time">One-time</option>
                                <option value="monthly">Monthly</option>
                                <option value="annual">Annual</option>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button type="submit" class="btn btn-primary text-xs w-full"
                                :disabled="acting || !form.product_id">
                                <i class="ti ti-plus" />Add
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="form-label">Notes</label>
                        <input v-model="form.notes" type="text" maxlength="1000"
                            placeholder="Why this product is on the schedule" class="form-control text-xs" />
                    </div>
                </form>
            </div>

            <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2 shrink-0">
                <button class="btn btn-ghost text-xs" @click="$emit('close')">Close</button>
            </footer>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useCrm } from '~/composables/useCrm'
import { useSales } from '~/composables/useSales'
import { useToast } from '~/composables/useToast'
import type {
    CreateProductScheduleLinePayload,
    Opportunity,
    OpportunityProductScheduleLine,
    ScheduleCadence,
} from '~/types/crm'
import type { ProductLite } from '~/types/sales'

const props = defineProps<{ opportunity: Opportunity }>()
defineEmits<{ (e: 'close'): void }>()

const crm = useCrm()
const sales = useSales()
const toast = useToast()

const lines = ref<OpportunityProductScheduleLine[]>([])
const catalogue = ref<ProductLite[]>([])
const loading = ref(true)
const acting = ref(false)

const form = reactive<CreateProductScheduleLinePayload>({
    product_id: '',
    quantity: 1,
    estimated_unit_price: null,
    cadence: 'one_time' as ScheduleCadence,
    notes: null,
})

const locked = computed(() => props.opportunity.stage === 'won' || props.opportunity.stage === 'lost')

const fmt = (v: number) => new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(v || 0)

const productName = (id: string): string => catalogue.value.find((p) => p.id === id)?.name || '—'

const load = async () => {
    loading.value = true
    try {
        const [schedRes, prodRes] = await Promise.all([
            crm.opportunities.listSchedule(props.opportunity.id),
            sales.catalogue.listProducts(),
        ])
        lines.value = schedRes.data
        catalogue.value = prodRes.data.filter((p) => p.is_active)
    } catch (err: any) {
        toast.error('Failed to load product schedule', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const resetForm = () => {
    form.product_id = ''
    form.quantity = 1
    form.estimated_unit_price = null
    form.cadence = 'one_time'
    form.notes = null
}

const addLine = async () => {
    if (!form.product_id) return
    acting.value = true
    try {
        const res = await crm.opportunities.addScheduleLine(props.opportunity.id, form)
        lines.value.push(res.data)
        toast.success('Schedule line added')
        resetForm()
    } catch (err: any) {
        toast.error('Add failed', err?.data?.message)
    } finally {
        acting.value = false
    }
}

const removeLine = async (line: OpportunityProductScheduleLine) => {
    acting.value = true
    try {
        await crm.opportunities.removeScheduleLine(props.opportunity.id, line.id)
        lines.value = lines.value.filter((l) => l.id !== line.id)
        toast.success('Schedule line removed')
    } catch (err: any) {
        toast.error('Remove failed', err?.data?.message)
    } finally {
        acting.value = false
    }
}

onMounted(load)
</script>
