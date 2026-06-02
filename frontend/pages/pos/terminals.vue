<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold text-(--text-heading) leading-tight">POS Terminals</h1>
                    <p class="text-xs text-(--text-muted) mt-1">{{ pageHint }}</p>
                </div>
                <button class="btn btn-primary text-xs inline-flex items-center gap-2" @click="openCreate">
                    <i class="ti ti-plus" />New terminal
                </button>
            </header>

            <!-- KPI metrics row (§5.1) -->
            <section class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <article v-for="kpi in kpis" :key="kpi.label" class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">{{ kpi.label }}</span>
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center" :class="kpi.badgeClass">
                            <i class="ti text-sm" :class="kpi.icon" />
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono leading-none" :class="kpi.toneClass">
                        {{ kpi.value }}
                    </p>
                    <p class="text-xxs text-(--text-muted)">{{ kpi.sub }}</p>
                </article>
            </section>

            <!-- Filter chips -->
            <section class="flex items-center gap-2 flex-wrap">
                <button type="button" class="chip" :class="{ active: filterStatus === 'all' }"
                    @click="filterStatus = 'all'; load()">
                    <i class="ti ti-list" /> All
                </button>
                <button v-for="s in STATUSES" :key="s.value" type="button"
                    class="chip" :class="{ active: filterStatus === s.value }"
                    @click="filterStatus = s.value; load()">
                    <i class="ti" :class="s.icon" /> {{ s.label }}
                </button>
            </section>

            <div v-if="loading" class="py-24 flex justify-center">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
            </div>

            <div v-else-if="terminals.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-device-desktop-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No terminals</h4>
                <p class="text-xs text-(--text-muted) mt-1">Create one to start taking sales.</p>
            </div>

            <section v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <article v-for="t in terminals" :key="t.id"
                    class="glass-card rounded-2xl p-5 flex flex-col gap-3 relative overflow-hidden">
                    <div class="absolute -right-8 -top-8 w-20 h-20 rounded-full bg-(--color-primary)/10 blur-xl pointer-events-none" />
                    <div class="relative z-10 space-y-3">
                        <header class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="text-sm font-semibold text-(--text-heading) truncate">{{ t.name }}</h3>
                                <p class="text-xxs text-(--text-muted) font-mono">{{ t.code }}</p>
                            </div>
                            <Badge :variant="statusMeta(t.status).variant" :icon="statusMeta(t.status).icon">
                                {{ statusMeta(t.status).label }}
                            </Badge>
                        </header>
                        <dl class="text-xxs space-y-1 text-(--text-muted)">
                            <div class="flex justify-between"><dt>Warehouse</dt><dd class="font-mono text-(--text-heading)">{{ t.warehouseCode || '-' }}</dd></div>
                            <div class="flex justify-between"><dt>Cash account</dt><dd class="font-mono text-(--text-heading)">{{ t.pettyCashAccountCode || 'default' }}</dd></div>
                            <div v-if="t.location" class="flex justify-between"><dt>Location</dt><dd class="text-(--text-heading) truncate ml-2">{{ t.location }}</dd></div>
                        </dl>
                        <div class="flex gap-2 pt-2 border-t border-(--border-color)">
                            <button class="btn btn-soft-secondary text-xs flex-1" @click="openEdit(t)">
                                <i class="ti ti-edit" /> Edit
                            </button>
                            <button class="btn btn-soft-danger text-xs" @click="remove(t)">
                                <i class="ti ti-trash" />
                            </button>
                        </div>
                    </div>
                </article>
            </section>
        </div>

        <!-- Form modal -->
        <Teleport to="body">
            <div v-if="modalOpen" class="fixed inset-0 z-50 bg-black/40 backdrop-blur-sm flex items-center justify-center p-4"
                @click.self="modalOpen = false">
                <div class="glass-card rounded-2xl max-w-lg w-full p-6 space-y-4">
                    <header class="flex items-center justify-between">
                        <h3 class="text-sm font-semibold text-(--text-heading)">{{ form.id ? 'Edit terminal' : 'New terminal' }}</h3>
                        <button class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="modalOpen = false">
                            <i class="ti ti-x" />
                        </button>
                    </header>
                    <form @submit.prevent="save" class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Code *</label>
                                <input v-model="form.code" required maxlength="32" class="form-control text-xs font-mono mt-1" />
                            </div>
                            <div>
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Name *</label>
                                <input v-model="form.name" required maxlength="120" class="form-control text-xs mt-1" />
                            </div>
                        </div>
                        <div>
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Warehouse *</label>
                            <select v-model="form.warehouse_id" required class="form-control text-xs mt-1">
                                <option value="">Choose warehouse</option>
                                <option v-for="w in warehouses" :key="w.id" :value="w.id">{{ w.code }} - {{ w.name }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Cash drawer account</label>
                            <select v-model="form.petty_cash_account_id" class="form-control text-xs mt-1">
                                <option :value="null">Use POS default (1100)</option>
                                <option v-for="a in cashAccounts" :key="a.id" :value="a.id">{{ a.code }} - {{ a.name }}</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Location</label>
                                <input v-model="form.location" maxlength="255" class="form-control text-xs mt-1" />
                            </div>
                            <div>
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Status</label>
                                <select v-model="form.status" class="form-control text-xs mt-1">
                                    <option value="active">Active</option>
                                    <option value="disabled">Disabled</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Notes</label>
                            <textarea v-model="form.notes" rows="2" maxlength="1000" class="form-control text-xs mt-1" />
                        </div>
                        <div v-if="error" class="text-xs text-(--color-danger)">{{ error }}</div>
                        <div class="flex justify-end gap-2 pt-2">
                            <button type="button" class="btn btn-soft-secondary text-xs" @click="modalOpen = false">Cancel</button>
                            <button type="submit" class="btn btn-primary text-xs" :disabled="saving">
                                {{ saving ? 'Saving...' : 'Save' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { usePos, type PosTerminal } from '~/composables/usePos'
import { useApi } from '~/composables/useApi'
import { useToast } from '~/composables/useToast'

definePageMeta({ title: 'Terminals' })

const pos = usePos()
const api = useApi()
const toast = useToast()

const STATUSES = [
    { value: 'active', label: 'Active', icon: 'ti-check' },
    { value: 'disabled', label: 'Disabled', icon: 'ti-x' },
] as const

// Status badge map per design.md §10.2 / §10.4 - canonical Badge variant + icon + label.
type TerminalVariant = 'success' | 'secondary'
const TERMINAL_STATUS_META: Record<string, { variant: TerminalVariant; icon: string; label: string }> = {
    active: { variant: 'success', icon: 'ti-circle-check', label: 'Active' },
    disabled: { variant: 'secondary', icon: 'ti-circle-off', label: 'Disabled' },
}
function statusMeta(status: string) {
    return TERMINAL_STATUS_META[status]
        ?? { variant: 'secondary' as TerminalVariant, icon: 'ti-help', label: status }
}

const openShifts = ref(0)

const terminals = ref<PosTerminal[]>([])
const warehouses = ref<any[]>([])
const cashAccounts = ref<any[]>([])
const loading = ref(true)
const filterStatus = ref<'all' | string>('all')
const modalOpen = ref(false)
const saving = ref(false)
const error = ref('')

const form = ref({
    id: '' as string,
    code: '',
    name: '',
    warehouse_id: '',
    petty_cash_account_id: null as string | null,
    location: '',
    status: 'active' as 'active' | 'disabled',
    notes: '',
})

async function load() {
    loading.value = true
    try {
        const res = await pos.terminals.list({
            status: filterStatus.value === 'all' ? undefined : filterStatus.value,
            limit: 50,
        })
        terminals.value = res.data ?? []
    } finally {
        loading.value = false
    }
}

async function loadDeps() {
    try {
        const wh = await api.get<{ data: any[] }>('warehouses?limit=100')
        warehouses.value = wh.data ?? []
    } catch {}
    try {
        const acc = await api.get<{ data: any[] }>('accounts?type=asset&limit=200')
        cashAccounts.value = acc.data ?? []
    } catch {}
    try {
        const res = await pos.shifts.list({ status: 'open', limit: 100 })
        openShifts.value = res.pagination?.total ?? res.data?.length ?? 0
    } catch { openShifts.value = 0 }
}

const activeCount = computed(() => terminals.value.filter(t => t.status === 'active').length)
const disabledCount = computed(() => terminals.value.filter(t => t.status === 'disabled').length)

const kpis = computed(() => [
    {
        label: 'Total',
        value: String(terminals.value.length),
        sub: 'registered stations',
        icon: 'ti-device-desktop',
        badgeClass: 'badge-soft-primary',
        toneClass: '',
    },
    {
        label: 'Active',
        value: String(activeCount.value),
        sub: 'ready to take sales',
        icon: 'ti-circle-check',
        badgeClass: 'badge-soft-success',
        toneClass: '',
    },
    {
        label: 'Disabled',
        value: String(disabledCount.value),
        sub: 'offline / decommissioned',
        icon: 'ti-circle-off',
        badgeClass: 'badge-soft-secondary',
        toneClass: disabledCount.value > 0 ? 'text-(--text-muted)' : '',
    },
    {
        label: 'On shift now',
        value: String(openShifts.value),
        sub: openShifts.value === 1 ? 'cashier signed in' : 'cashiers signed in',
        icon: 'ti-clock-play',
        badgeClass: openShifts.value > 0 ? 'badge-soft-info' : 'badge-soft-secondary',
        toneClass: openShifts.value > 0 ? 'text-(--color-info)' : '',
    },
])

const pageHint = computed(() => {
    if (loading.value) return 'Register stations that take payments. Each terminal pins a default warehouse for stock-out.'
    const n = terminals.value.length
    if (n === 0) return 'No terminals registered yet. Add one to start taking sales.'
    return `${n} register${n === 1 ? '' : 's'} configured · ${activeCount.value} active · ${openShifts.value} on shift right now.`
})

function reset() {
    form.value = { id: '', code: '', name: '', warehouse_id: '', petty_cash_account_id: null, location: '', status: 'active', notes: '' }
    error.value = ''
}

function openCreate() {
    reset()
    modalOpen.value = true
}

function openEdit(t: PosTerminal) {
    form.value = {
        id: t.id,
        code: t.code,
        name: t.name,
        warehouse_id: t.warehouseId,
        petty_cash_account_id: t.pettyCashAccountId,
        location: t.location || '',
        status: t.status,
        notes: t.notes || '',
    }
    error.value = ''
    modalOpen.value = true
}

async function save() {
    saving.value = true
    error.value = ''
    try {
        const payload: Record<string, any> = {
            code: form.value.code,
            name: form.value.name,
            warehouse_id: form.value.warehouse_id,
            petty_cash_account_id: form.value.petty_cash_account_id,
            location: form.value.location || null,
            status: form.value.status,
            notes: form.value.notes || null,
        }
        if (form.value.id) {
            await pos.terminals.update(form.value.id, payload)
            toast.success('Terminal updated')
        } else {
            await pos.terminals.create(payload)
            toast.success('Terminal created')
        }
        modalOpen.value = false
        await load()
    } catch (e: any) {
        error.value = e?.data?.message || 'Save failed.'
    } finally {
        saving.value = false
    }
}

async function remove(t: PosTerminal) {
    const ok = await toast.confirm({
        title: `Delete terminal ${t.code}?`,
        description: 'Deletion is blocked if this terminal has any order history. Use Disable instead for active stations.',
        confirmLabel: 'Delete',
        color: 'danger',
    })
    if (!ok) return
    try {
        await pos.terminals.destroy(t.id)
        toast.success('Terminal deleted')
        await load()
    } catch (e: any) {
        toast.error('Delete failed', e?.data?.message || '')
    }
}

onMounted(() => {
    load()
    loadDeps()
})
</script>

<style scoped>
.chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 12px; border-radius: 999px;
    border: 1px solid var(--border-color); background: var(--bg-card);
    font-size: 11px; color: var(--text-body); cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
.chip:hover { background: var(--bg-muted); }
.chip.active {
    background: rgb(var(--color-primary-rgb) / 0.12);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}
</style>
