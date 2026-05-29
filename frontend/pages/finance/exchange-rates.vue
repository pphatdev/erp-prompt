<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Exchange Rates</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Track currency pairs with daily effective rates. Defaults to USD → KHR.</p>
                </div>
                <button v-if="canWrite" type="button" class="btn btn-primary text-xs" @click="openCreateModal()">
                    <i class="ti ti-plus" />New Rate
                </button>
            </header>

            <!-- KPI: current default-pair rate + live converter -->
            <section class="grid grid-cols-1 md:grid-cols-3 gap-3">
                <div class="glass-card rounded-xl p-4 md:col-span-1">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Current {{ defaultPair }}</p>
                    <p class="text-2xl font-semibold text-(--color-primary) mt-1 font-mono">
                        <template v-if="latestRate">{{ formatRate(latestRate.rate) }}</template>
                        <span v-else class="text-(--text-muted) text-sm">No rate set</span>
                    </p>
                    <p v-if="latestRate" class="text-xxs text-(--text-muted) mt-1">
                        Effective {{ formatDate(latestRate.effectiveDate) }}
                        <span v-if="latestRate.source" class="font-mono uppercase tracking-wider">· {{ latestRate.source }}</span>
                    </p>
                </div>
                <div class="glass-card rounded-xl p-4 md:col-span-2">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold mb-2">Quick Convert</p>
                    <div class="grid grid-cols-12 gap-2 items-center">
                        <input v-model.number="converter.amount" type="number" min="0" step="0.01"
                            class="form-control text-xs col-span-3 text-right font-mono" @input="onConvert" />
                        <select v-model="converter.from" class="form-control text-xs col-span-3 appearance-none" @change="onConvert">
                            <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.code }}</option>
                        </select>
                        <div class="col-span-1 flex justify-center">
                            <i class="ti ti-arrow-right text-(--text-muted)" />
                        </div>
                        <select v-model="converter.to" class="form-control text-xs col-span-3 appearance-none" @change="onConvert">
                            <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.code }}</option>
                        </select>
                        <button type="button" class="action-btn col-span-2" title="Swap" @click="swapConverter">
                            <i class="ti ti-arrows-exchange" />
                        </button>
                    </div>
                    <p v-if="convertResult" class="text-sm font-mono mt-2 text-(--text-heading)">
                        = <span class="font-semibold">{{ formatAmount(convertResult.converted) }}</span>
                        <span class="text-xxs text-(--text-muted) ml-2">
                            @ {{ formatRate(convertResult.rate) }}
                            <span v-if="convertResult.inverse" class="text-(--color-warning)">(inverse)</span>
                        </span>
                    </p>
                    <p v-else-if="convertError" class="text-xs text-(--color-danger) mt-2">{{ convertError }}</p>
                </div>
            </section>

            <!-- Filters -->
            <section class="glass-card rounded-xl p-4 flex flex-col md:flex-row md:items-center md:justify-between gap-3">
                <div class="relative w-full md:w-auto md:flex-1">
                    <i class="ti ti-currency-dollar absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
                    <input v-model="search" type="search" placeholder="Search by currency code..."
                        class="form-control pl-9 text-xs" />
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <div class="relative">
                        <i class="ti ti-flag absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
                        <select v-model="filterBase" class="form-control pl-9 text-xs appearance-none">
                            <option value="">Any base</option>
                            <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.code }}</option>
                        </select>
                    </div>
                    <i class="ti ti-arrow-right text-(--text-muted) text-xs" />
                    <div class="relative">
                        <i class="ti ti-flag absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm pointer-events-none" />
                        <select v-model="filterQuote" class="form-control pl-9 text-xs appearance-none">
                            <option value="">Any quote</option>
                            <option v-for="c in currencies" :key="c.code" :value="c.code">{{ c.code }}</option>
                        </select>
                    </div>
                </div>
            </section>

            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading rates...</span>
            </div>

            <div v-else-if="filteredList.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-currency-dollar-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No exchange rates yet</h4>
                <p class="text-xs text-(--text-muted) mt-1">Add a rate to start tracking currency conversions.</p>
            </div>

            <section v-else class="glass-card rounded-2xl overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="text-xxs uppercase tracking-wider text-(--text-muted) border-b border-(--border-color)">
                                <th class="px-4 py-3 font-semibold">Pair</th>
                                <th class="px-4 py-3 font-semibold font-mono text-right">Rate</th>
                                <th class="px-4 py-3 font-semibold">Effective Date</th>
                                <th class="px-4 py-3 font-semibold hidden md:table-cell">Source</th>
                                <th class="px-4 py-3 font-semibold hidden lg:table-cell">Notes</th>
                                <th class="px-4 py-3 font-semibold">Status</th>
                                <th class="px-4 py-3 font-semibold text-right w-32">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-(--border-color)">
                            <tr v-for="r in filteredList" :key="r.id"
                                class="hover:bg-(--bg-muted) transition-colors">
                                <td class="px-4 py-3">
                                    <span class="font-mono font-semibold text-(--text-heading)">{{ r.pair }}</span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono font-semibold">{{ formatRate(r.rate) }}</td>
                                <td class="px-4 py-3">{{ formatDate(r.effectiveDate) }}</td>
                                <td class="px-4 py-3 hidden md:table-cell">
                                    <span class="text-xxs font-mono uppercase tracking-wider px-1.5 py-0.5 rounded bg-(--bg-muted)">
                                        {{ r.source }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 hidden lg:table-cell text-(--text-muted) truncate max-w-[240px]">
                                    {{ r.notes || '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    <Badge :variant="r.isActive ? 'success' : 'secondary'">
                                        {{ r.isActive ? 'Active' : 'Archived' }}
                                    </Badge>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <div class="inline-flex items-center gap-1">
                                        <button v-if="canWrite" type="button" class="action-btn" title="Edit"
                                            @click="openEditModal(r)">
                                            <i class="ti ti-pencil" />
                                        </button>
                                        <button v-if="canDelete" type="button" class="action-btn action-btn-danger"
                                            title="Archive" @click="confirmDelete(r)">
                                            <i class="ti ti-archive" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <!-- Form Modal -->
        <div v-if="showFormModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">{{ isEdit ? 'Edit Rate' : 'New Exchange Rate' }}</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="saveRate">
                    <div class="p-5 space-y-4">
                        <div class="grid grid-cols-12 gap-2 items-end">
                            <div class="space-y-1 col-span-5">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">From *</label>
                                <select v-model="form.base_currency" required class="form-control text-xs">
                                    <option v-for="c in currencies" :key="c.code" :value="c.code">
                                        {{ c.code }} — {{ c.label }}
                                    </option>
                                </select>
                            </div>
                            <div class="col-span-2 flex justify-center pb-2">
                                <i class="ti ti-arrow-right text-(--text-muted)" />
                            </div>
                            <div class="space-y-1 col-span-5">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">To *</label>
                                <select v-model="form.quote_currency" required class="form-control text-xs">
                                    <option v-for="c in currencies" :key="c.code" :value="c.code">
                                        {{ c.code }} — {{ c.label }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Rate *</label>
                                <input v-model.number="form.rate" type="number" min="0" step="0.000001" required
                                    placeholder="e.g. 4100" class="form-control text-xs text-right font-mono" />
                                <p class="text-xxs text-(--text-muted)">1 {{ form.base_currency }} = ? {{ form.quote_currency }}</p>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Effective Date *</label>
                                <input v-model="form.effective_date" type="date" required class="form-control text-xs" />
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Source</label>
                            <select v-model="form.source" class="form-control text-xs">
                                <option value="manual">Manual</option>
                                <option value="nbc">NBC (National Bank of Cambodia)</option>
                                <option value="bank">Bank quote</option>
                                <option value="api">API</option>
                                <option value="other">Other</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Notes</label>
                            <textarea v-model="form.notes" rows="2" maxlength="2000"
                                placeholder="Optional reference or context"
                                class="form-control text-xs resize-none" />
                        </div>

                        <label class="inline-flex items-center gap-2 cursor-pointer">
                            <input v-model="form.is_active" type="checkbox" class="rounded border-(--border-color)" />
                            <span class="text-xs">Active</span>
                        </label>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showFormModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="submitting">
                            <i v-if="submitting" class="ti ti-loader-2 animate-spin" />
                            {{ isEdit ? 'Save Changes' : 'Create Rate' }}
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Delete Modal -->
        <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Archive Rate</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="deleteTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5">
                    <p class="text-xs text-(--text-body)">
                        Archive <span class="font-mono font-semibold text-(--text-heading)">{{ deleteTarget.pair }}</span>
                        rate dated {{ formatDate(deleteTarget.effectiveDate) }}?
                    </p>
                    <p class="text-xxs text-(--text-muted) mt-2">
                        Historical conversions referencing this rate keep their stored values.
                    </p>
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
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useFinance, COMMON_CURRENCIES } from '~/composables/useFinance'
import { useToast } from '~/composables/useToast'
import { useAuthStore } from '~/stores/auth'
import type { ConvertResult, CreateExchangeRatePayload, ExchangeRate } from '~/types/finance'

definePageMeta({ breadcrumb: 'Exchange Rates' })

const finance = useFinance()
const toast = useToast()
const authStore = useAuthStore()

const canWrite = computed(() => authStore.hasPermission('fms.exchange_rate.write'))
const canDelete = computed(() => authStore.hasPermission('fms.exchange_rate.delete'))

const currencies = COMMON_CURRENCIES
const defaultBase = 'USD'
const defaultQuote = 'KHR'
const defaultPair = `${defaultBase} / ${defaultQuote}`

const loading = ref(false)
const submitting = ref(false)
const deleting = ref(false)

const list = ref<ExchangeRate[]>([])
const latestRate = ref<ExchangeRate | null>(null)

const search = ref('')
const filterBase = ref('')
const filterQuote = ref('')

const filteredList = computed(() => list.value.filter(r => {
    const q = search.value.trim().toUpperCase()
    const matchSearch = !q || r.baseCurrency.includes(q) || r.quoteCurrency.includes(q) || r.pair.includes(q)
    const matchBase = !filterBase.value || r.baseCurrency === filterBase.value
    const matchQuote = !filterQuote.value || r.quoteCurrency === filterQuote.value
    return matchSearch && matchBase && matchQuote
}))

const showFormModal = ref(false)
const isEdit = ref(false)
const editId = ref<string | null>(null)
const today = () => new Date().toISOString().slice(0, 10)

const form = reactive<CreateExchangeRatePayload>({
    base_currency: defaultBase,
    quote_currency: defaultQuote,
    rate: 0,
    effective_date: today(),
    source: 'manual',
    notes: null,
    is_active: true,
})

const resetForm = () => {
    form.base_currency = defaultBase
    form.quote_currency = defaultQuote
    form.rate = 0
    form.effective_date = today()
    form.source = 'manual'
    form.notes = null
    form.is_active = true
}

const openCreateModal = () => {
    isEdit.value = false
    editId.value = null
    resetForm()
    showFormModal.value = true
}

const openEditModal = (r: ExchangeRate) => {
    isEdit.value = true
    editId.value = r.id
    form.base_currency = r.baseCurrency
    form.quote_currency = r.quoteCurrency
    form.rate = r.rate
    form.effective_date = r.effectiveDate ?? today()
    form.source = r.source
    form.notes = r.notes
    form.is_active = r.isActive
    showFormModal.value = true
}

const saveRate = async () => {
    submitting.value = true
    try {
        const payload: CreateExchangeRatePayload = {
            ...form,
            base_currency: form.base_currency.toUpperCase(),
            quote_currency: form.quote_currency.toUpperCase(),
            source: form.source || 'manual',
            notes: form.notes || null,
        }
        if (isEdit.value && editId.value) {
            await finance.exchangeRates.update(editId.value, payload)
            toast.success('Rate updated', `${payload.base_currency}/${payload.quote_currency}`)
        } else {
            await finance.exchangeRates.create(payload)
            toast.success('Rate created', `${payload.base_currency}/${payload.quote_currency}`)
        }
        showFormModal.value = false
        await Promise.all([load(), loadLatest()])
    } catch (err: any) {
        toast.error('Save failed', err?.data?.message)
    } finally {
        submitting.value = false
    }
}

const deleteTarget = ref<ExchangeRate | null>(null)
const confirmDelete = (r: ExchangeRate) => { deleteTarget.value = r }
const onConfirmDelete = async () => {
    if (!deleteTarget.value) return
    deleting.value = true
    try {
        await finance.exchangeRates.destroy(deleteTarget.value.id)
        toast.success('Rate archived', deleteTarget.value.pair)
        deleteTarget.value = null
        await Promise.all([load(), loadLatest()])
    } catch (err: any) {
        toast.error('Archive failed', err?.data?.message)
    } finally {
        deleting.value = false
    }
}

const converter = reactive({ amount: 1, from: defaultBase, to: defaultQuote })
const convertResult = ref<ConvertResult | null>(null)
const convertError = ref<string | null>(null)
let convertSeq = 0

const onConvert = async () => {
    if (!converter.amount || converter.amount <= 0) {
        convertResult.value = null
        convertError.value = null
        return
    }
    const seq = ++convertSeq
    try {
        const res = await finance.exchangeRates.convert(
            converter.amount, converter.from, converter.to,
        )
        if (seq !== convertSeq) return
        convertResult.value = res.data
        convertError.value = null
    } catch (err: any) {
        if (seq !== convertSeq) return
        convertResult.value = null
        convertError.value = err?.data?.message ?? 'Conversion unavailable'
    }
}

const swapConverter = () => {
    const tmp = converter.from
    converter.from = converter.to
    converter.to = tmp
    onConvert()
}

watch(() => [converter.amount, converter.from, converter.to], onConvert, { immediate: false })

const load = async () => {
    loading.value = true
    try {
        const res = await finance.exchangeRates.list({ limit: 100 })
        list.value = res.data
    } catch (err: any) {
        toast.error('Failed to load rates', err?.data?.message)
    } finally {
        loading.value = false
    }
}

const loadLatest = async () => {
    try {
        const res = await finance.exchangeRates.latest(defaultBase, defaultQuote)
        latestRate.value = res.data
    } catch {
        latestRate.value = null
    }
}

const formatRate = (v: number) => {
    const n = Number(v) || 0
    return n < 1 ? n.toFixed(6) : n.toLocaleString('en-US', { maximumFractionDigits: 4 })
}
const formatAmount = (v: number) =>
    new Intl.NumberFormat('en-US', { maximumFractionDigits: 4 }).format(Number(v) || 0)
const formatDate = (d: string | null) =>
    d ? new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—'

onMounted(async () => {
    await Promise.all([load(), loadLatest()])
    onConvert()
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
