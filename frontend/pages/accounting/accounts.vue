<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Chart of Accounts</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Hierarchical ledger taxonomy. Parent balances roll up from sub-accounts.</p>
                </div>
                <button v-if="canWrite" type="button" class="btn btn-primary text-xs" @click="openCreateModal()">
                    <i class="ti ti-plus" />New Account
                </button>
            </header>

            <!-- Metrics: per type aggregated balance -->
            <section class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-4">
                <div v-for="t in ACCOUNT_TYPES" :key="t.value" class="glass-card rounded-2xl p-4 space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">{{ t.label }}</span>
                        <span class="w-7 h-7 rounded-lg flex items-center justify-center" :class="t.badge">
                            <i class="ti text-sm" :class="t.icon" />
                        </span>
                    </div>
                    <p class="text-2xl font-bold text-(--text-heading) font-mono">{{ formatMoney(typeTotalsAnim[t.value].value) }}</p>
                    <p class="text-xxs text-(--text-muted)">{{ typeCounts[t.value] }} account{{ typeCounts[t.value] === 1 ? '' : 's' }}</p>
                </div>
            </section>

            <!-- Filter chips -->
            <section class="flex items-center gap-2 flex-wrap">
                <button type="button" class="chip" :class="{ active: typeFilter === '' }" @click="typeFilter = ''">All</button>
                <button v-for="t in ACCOUNT_TYPES" :key="t.value" type="button"
                    class="chip" :class="{ active: typeFilter === t.value }" @click="typeFilter = t.value">
                    <i class="ti" :class="t.icon" /> {{ t.label }}
                </button>
            </section>

            <!-- Loading -->
            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading chart of accounts...</span>
            </div>

            <!-- Empty -->
            <div v-else-if="filteredTree.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-tree text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">
                    {{ typeFilter ? 'No accounts of this type' : 'No accounts yet' }}
                </h4>
                <p class="text-xs text-(--text-muted) mt-1">
                    {{ typeFilter ? 'Try clearing the filter or add an account of this type.' : 'Create a top-level account to start building your ledger.' }}
                </p>
            </div>

            <!-- Tree -->
            <section v-else class="glass-card rounded-2xl p-2">
                <header class="grid grid-cols-12 px-3 py-2 text-xxs font-bold uppercase tracking-widest text-(--text-muted) border-b border-(--border-color)">
                    <div class="col-span-6">Account</div>
                    <div class="col-span-2 text-right">Own Balance</div>
                    <div class="col-span-2 text-right">Aggregated</div>
                    <div class="col-span-2 text-right">Actions</div>
                </header>
                <AccountNode v-for="node in filteredTree" :key="node.id" :node="node" :depth="0"
                    :can-write="canWrite" :can-delete="canDelete"
                    @add-child="openCreateModal($event)" @edit="openEditModal($event)" @delete="confirmDelete($event)" />
            </section>
        </div>

        <!-- Form Modal -->
        <div v-if="showFormModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-lg bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">{{ isEdit ? 'Edit Account' : 'New Account' }}</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="saveAccount">
                    <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
                        <div class="grid grid-cols-3 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Code *</label>
                                <input v-model="form.code" type="text" required maxlength="32" placeholder="e.g. 1000"
                                    class="form-control text-xs font-mono" />
                            </div>
                            <div class="col-span-2 space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Name *</label>
                                <input v-model="form.name" type="text" required maxlength="160" placeholder="e.g. Cash on Hand"
                                    class="form-control text-xs" />
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Type *</label>
                                <select v-model="form.type" class="form-control text-xs" required :disabled="isEdit && editHasChildren">
                                    <option v-for="t in ACCOUNT_TYPES" :key="t.value" :value="t.value">{{ t.label }}</option>
                                </select>
                                <p v-if="isEdit && editHasChildren" class="text-xxs text-(--text-muted)">Type is locked while this account has sub-accounts.</p>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Parent</label>
                                <select v-model="form.parent_id" class="form-control text-xs">
                                    <option :value="null">— Root (top-level) —</option>
                                    <option v-for="c in eligibleParents" :key="c.id" :value="c.id">
                                        {{ indentLabel(c) }}
                                    </option>
                                </select>
                                <p class="text-xxs text-(--text-muted)">Sub-account type must match the parent's type.</p>
                            </div>
                        </div>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showFormModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="submitting">
                            <i v-if="submitting" class="ti ti-loader-2 animate-spin" />
                            {{ isEdit ? 'Save Changes' : 'Create Account' }}
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Delete Modal -->
        <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Archive Account</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="deleteTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5">
                    <p class="text-xs text-(--text-body)">
                        Archive account <span class="font-mono font-semibold text-(--text-heading)">{{ deleteTarget.code }}</span>
                        — <span class="font-semibold text-(--text-heading)">{{ deleteTarget.name }}</span>?
                    </p>
                    <p class="text-xxs text-(--text-muted) mt-2">
                        Archiving will fail if the account has sub-accounts or posted ledger entries. Accounts with history must remain for audit traceability.
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
import { computed, defineComponent, h, onMounted, reactive, ref, type Component, type PropType } from 'vue'
import { useFinance, ACCOUNT_TYPES } from '~/composables/useFinance'
import { useToast } from '~/composables/useToast'
import { useCountUp } from '~/composables/useCountUp'
import { useAuthStore } from '~/stores/auth'
import type { Account, AccountType, CreateAccountPayload } from '~/types/finance'

definePageMeta({ breadcrumb: 'Chart of Accounts' })

const finance = useFinance()
const toast = useToast()
const authStore = useAuthStore()

const canWrite = computed(() => authStore.hasPermission('fms.accounts.write'))
const canDelete = computed(() => authStore.hasPermission('fms.accounts.delete'))

const loading = ref(false)
const submitting = ref(false)
const deleting = ref(false)
const tree = ref<Account[]>([])
const typeFilter = ref<AccountType | ''>('')

const flatten = (nodes: Account[]): Account[] => {
    const out: Account[] = []
    const walk = (list: Account[]) => list.forEach(n => { out.push(n); if (n.children?.length) walk(n.children) })
    walk(nodes)
    return out
}

const flatList = computed(() => flatten(tree.value))

const filteredTree = computed(() => {
    if (!typeFilter.value) return tree.value
    return tree.value.filter(n => n.type === typeFilter.value)
})

const typeCounts = computed(() => {
    const out: Record<AccountType, number> = { asset: 0, liability: 0, equity: 0, revenue: 0, expense: 0 }
    flatList.value.forEach(a => { out[a.type]++ })
    return out
})

const typeTotals = computed(() => {
    const out: Record<AccountType, number> = { asset: 0, liability: 0, equity: 0, revenue: 0, expense: 0 }
    tree.value.forEach(root => { out[root.type] += root.aggregatedBalance })
    return out
})

const typeTotalsAnim = {
    asset:     useCountUp(() => typeTotals.value.asset),
    liability: useCountUp(() => typeTotals.value.liability),
    equity:    useCountUp(() => typeTotals.value.equity),
    revenue:   useCountUp(() => typeTotals.value.revenue),
    expense:   useCountUp(() => typeTotals.value.expense),
}

const formatMoney = (n: number) => {
    const abs = Math.abs(n)
    if (abs >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`
    if (abs >= 1_000)     return `${(n / 1_000).toFixed(1)}K`
    return n.toFixed(2)
}

const eligibleParents = computed(() => {
    return flatList.value.filter(a => {
        if (form.type && a.type !== form.type) return false
        if (isEdit.value && editId.value && (a.id === editId.value || isDescendant(a.id, editId.value))) return false
        return true
    })
})

const depthOf = (id: string): number => {
    let d = 0
    let current = flatList.value.find(c => c.id === id)
    while (current?.parentId) {
        d++
        current = flatList.value.find(c => c.id === current!.parentId)
    }
    return d
}

const indentLabel = (a: Account) => `${'— '.repeat(depthOf(a.id))}${a.code} · ${a.name}`

const isDescendant = (candidateId: string, ancestorId: string | null): boolean => {
    if (!ancestorId) return false
    let current = flatList.value.find(c => c.id === candidateId)
    while (current?.parentId) {
        if (current.parentId === ancestorId) return true
        current = flatList.value.find(c => c.id === current!.parentId)
    }
    return false
}

const showFormModal = ref(false)
const isEdit = ref(false)
const editId = ref<string | null>(null)
const editHasChildren = ref(false)

const form = reactive<CreateAccountPayload>({
    code: '',
    name: '',
    type: 'asset',
    parent_id: null,
})

const resetForm = () => {
    form.code = ''
    form.name = ''
    form.type = 'asset'
    form.parent_id = null
}

const openCreateModal = (parentId?: string | null) => {
    isEdit.value = false
    editId.value = null
    editHasChildren.value = false
    resetForm()
    if (parentId) {
        const parent = flatList.value.find(a => a.id === parentId)
        if (parent) {
            form.parent_id = parent.id
            form.type = parent.type
        }
    }
    showFormModal.value = true
}

const openEditModal = (a: Account) => {
    isEdit.value = true
    editId.value = a.id
    editHasChildren.value = (a.children?.length ?? 0) > 0
    form.code = a.code
    form.name = a.name
    form.type = a.type
    form.parent_id = a.parentId
    showFormModal.value = true
}

const saveAccount = async () => {
    submitting.value = true
    try {
        const payload: CreateAccountPayload = {
            code: form.code.trim(),
            name: form.name.trim(),
            type: form.type,
            parent_id: form.parent_id || null,
        }
        if (isEdit.value && editId.value) {
            await finance.accounts.update(editId.value, payload)
            toast.success('Account updated', `${payload.code} · ${payload.name}`)
        } else {
            await finance.accounts.create(payload)
            toast.success('Account created', `${payload.code} · ${payload.name}`)
        }
        showFormModal.value = false
        await load()
    } catch (err: any) {
        toast.error('Save failed', err?.data?.message)
    } finally {
        submitting.value = false
    }
}

const deleteTarget = ref<Account | null>(null)
const confirmDelete = (a: Account) => { deleteTarget.value = a }
const onConfirmDelete = async () => {
    if (!deleteTarget.value) return
    deleting.value = true
    try {
        await finance.accounts.destroy(deleteTarget.value.id)
        toast.success('Account archived', `${deleteTarget.value.code} · ${deleteTarget.value.name}`)
        deleteTarget.value = null
        await load()
    } catch (err: any) {
        toast.error('Archive failed', err?.data?.message)
    } finally {
        deleting.value = false
    }
}

const load = async () => {
    loading.value = true
    try {
        const res = await finance.accounts.tree()
        tree.value = res.data
    } catch (err: any) {
        toast.error('Failed to load accounts', err?.data?.message)
    } finally {
        loading.value = false
    }
}

onMounted(load)

const typeBadgeClass = (t: AccountType) => ACCOUNT_TYPES.find(x => x.value === t)?.badge ?? 'badge-soft-primary'

const AccountNode: Component = defineComponent({
    name: 'AccountNode',
    props: {
        node: { type: Object as PropType<Account>, required: true },
        depth: { type: Number, default: 0 },
        canWrite: { type: Boolean, default: false },
        canDelete: { type: Boolean, default: false },
    },
    emits: ['add-child', 'edit', 'delete'],
    setup(props, { emit }) {
        const open = ref(true)
        return () => {
            const indent = `${props.depth * 18}px`
            const hasChildren = !!props.node.children?.length

            return h('div', { class: 'space-y-1' }, [
                h('div', {
                    class: 'grid grid-cols-12 items-center px-2 py-2 rounded-lg hover:bg-(--bg-muted)/60 transition-colors',
                }, [
                    h('div', { class: 'col-span-6 flex items-center gap-2 min-w-0', style: { paddingLeft: indent } }, [
                        h('button', {
                            type: 'button',
                            class: 'w-5 h-5 inline-flex items-center justify-center text-(--text-muted) hover:text-(--color-primary)',
                            onClick: () => { if (hasChildren) open.value = !open.value },
                        }, hasChildren
                            ? h('i', { class: `ti ${open.value ? 'ti-chevron-down' : 'ti-chevron-right'} text-xs` })
                            : h('i', { class: 'ti ti-point text-xs opacity-40' })),
                        h('span', {
                            class: `text-xxs px-1.5 py-0.5 rounded font-mono ${typeBadgeClass(props.node.type)}`,
                        }, props.node.type),
                        h('div', { class: 'flex-1 min-w-0' }, [
                            h('p', { class: 'text-xs font-semibold text-(--text-heading) truncate' },
                                [h('span', { class: 'font-mono mr-2' }, props.node.code), props.node.name]),
                        ]),
                    ]),
                    h('div', { class: 'col-span-2 text-right text-xs font-mono text-(--text-body)' }, props.node.balance.toFixed(2)),
                    h('div', { class: 'col-span-2 text-right text-xs font-mono font-semibold text-(--text-heading)' }, props.node.aggregatedBalance.toFixed(2)),
                    h('div', { class: 'col-span-2 flex items-center justify-end gap-1' }, [
                        props.canWrite ? h('button', {
                            type: 'button',
                            class: 'action-btn',
                            title: 'Add sub-account',
                            onClick: () => emit('add-child', props.node.id),
                        }, h('i', { class: 'ti ti-plus' })) : null,
                        props.canWrite ? h('button', {
                            type: 'button',
                            class: 'action-btn',
                            title: 'Edit',
                            onClick: () => emit('edit', props.node),
                        }, h('i', { class: 'ti ti-pencil' })) : null,
                        props.canDelete ? h('button', {
                            type: 'button',
                            class: 'action-btn action-btn-danger',
                            title: 'Archive',
                            onClick: () => emit('delete', props.node),
                        }, h('i', { class: 'ti ti-archive' })) : null,
                    ]),
                ]),
                hasChildren && open.value
                    ? h('div', { class: 'space-y-1' },
                        props.node.children!.map(child =>
                            h(AccountNode, {
                                key: child.id,
                                node: child,
                                depth: props.depth + 1,
                                canWrite: props.canWrite,
                                canDelete: props.canDelete,
                                onAddChild: (id: string) => emit('add-child', id),
                                onEdit: (n: Account) => emit('edit', n),
                                onDelete: (n: Account) => emit('delete', n),
                            })))
                    : null,
            ])
        }
    },
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

.chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 999px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    font-size: 11px;
    color: var(--text-body);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}

.chip:hover {
    background: var(--bg-muted);
}

.chip.active {
    background: rgb(var(--color-primary-rgb) / 0.12);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}
</style>
