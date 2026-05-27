<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Product Categories</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Catalogue taxonomy. Categories can nest under a parent for hierarchical browsing.</p>
                </div>
                <button v-if="canWrite" type="button" class="btn btn-primary text-xs" @click="openCreateModal()">
                    <i class="ti ti-plus" />New Category
                </button>
            </header>

            <!-- Metrics -->
            <section class="grid grid-cols-2 md:grid-cols-4 gap-3">
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Total</p>
                    <p class="text-xl font-semibold text-(--text-heading) mt-1">{{ totalCount }}</p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Root</p>
                    <p class="text-xl font-semibold text-(--color-primary) mt-1">{{ tree.length }}</p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Active</p>
                    <p class="text-xl font-semibold text-(--color-success) mt-1">{{ activeCount }}</p>
                </div>
                <div class="glass-card rounded-xl p-4">
                    <p class="text-xxs text-(--text-muted) uppercase tracking-widest font-bold">Assigned Products</p>
                    <p class="text-xl font-semibold text-(--color-info) mt-1">{{ assignedProducts }}</p>
                </div>
            </section>

            <!-- Loading -->
            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading categories...</span>
            </div>

            <!-- Empty -->
            <div v-else-if="tree.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-category text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No categories yet</h4>
                <p class="text-xs text-(--text-muted) mt-1">Create a top-level category to start organising products.</p>
            </div>

            <!-- Tree -->
            <section v-else class="glass-card rounded-2xl p-2">
                <CategoryNode v-for="node in tree" :key="node.id" :node="node" :depth="0"
                    :can-write="canWrite" :can-delete="canDelete"
                    @add-child="openCreateModal($event)" @edit="openEditModal($event)" @delete="confirmDelete($event)" />
            </section>
        </div>

        <!-- Form Modal -->
        <div v-if="showFormModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-lg bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">{{ isEdit ? 'Edit Category' : 'New Category' }}</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="saveCategory">
                    <div class="p-5 space-y-4 max-h-[70vh] overflow-y-auto">
                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Name *</label>
                            <input v-model="form.name" type="text" required maxlength="160" placeholder="e.g. Laptops"
                                class="form-control text-xs" />
                        </div>

                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Slug</label>
                                <input v-model="form.slug" type="text" maxlength="120" placeholder="auto from name"
                                    class="form-control text-xs font-mono" />
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Parent</label>
                                <select v-model="form.parent_id" class="form-control text-xs">
                                    <option :value="null">— Root (top-level) —</option>
                                    <option v-for="c in parentOptions" :key="c.id" :value="c.id"
                                        :disabled="isEdit && (c.id === editId || isDescendant(c.id, editId))">
                                        {{ indentLabel(c) }}
                                    </option>
                                </select>
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Description</label>
                            <textarea v-model="form.description" rows="2" maxlength="2000"
                                placeholder="Optional notes about this category"
                                class="form-control text-xs resize-none" />
                        </div>

                        <div class="grid grid-cols-3 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Color</label>
                                <div class="flex items-center gap-2">
                                    <input v-model="form.color" type="color" class="w-10 h-9 rounded border border-(--border-color) cursor-pointer p-1 bg-(--bg-card)" />
                                    <input v-model="form.color" type="text" maxlength="32" placeholder="#6366f1"
                                        class="form-control text-xs font-mono flex-1" />
                                </div>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Sort Order</label>
                                <input v-model.number="form.sort_order" type="number" min="0"
                                    class="form-control text-xs" />
                            </div>
                            <div class="flex items-end pb-1">
                                <label class="inline-flex items-center gap-2 cursor-pointer">
                                    <input v-model="form.is_active" type="checkbox" class="rounded border-(--border-color)" />
                                    <span class="text-xs">Active</span>
                                </label>
                            </div>
                        </div>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showFormModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="submitting">
                            <i v-if="submitting" class="ti ti-loader-2 animate-spin" />
                            {{ isEdit ? 'Save Changes' : 'Create Category' }}
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Delete Modal -->
        <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Archive Category</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="deleteTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5">
                    <p class="text-xs text-(--text-body)">
                        Archive category <span class="font-semibold text-(--text-heading)">{{ deleteTarget.name }}</span>?
                    </p>
                    <p class="text-xxs text-(--text-muted) mt-2">
                        Archiving will fail if the category still has children or assigned products.
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
import { computed, h, onMounted, reactive, ref, defineComponent, type PropType } from 'vue'
import { useInventory } from '~/composables/useInventory'
import { useToast } from '~/composables/useToast'
import { useAuthStore } from '~/stores/auth'
import type { Category, CreateCategoryPayload } from '~/types/inventory'

definePageMeta({ breadcrumb: 'Categories' })

const inventory = useInventory()
const toast = useToast()
const authStore = useAuthStore()

const canWrite = computed(() => authStore.hasPermission('inventory.category.write'))
const canDelete = computed(() => authStore.hasPermission('inventory.category.delete'))

const loading = ref(false)
const submitting = ref(false)
const deleting = ref(false)
const tree = ref<Category[]>([])

const flattenTree = (nodes: Category[]): Category[] => {
    const out: Category[] = []
    const walk = (list: Category[]) => list.forEach(n => {
        out.push(n)
        if (n.children?.length) walk(n.children)
    })
    walk(nodes)
    return out
}

const flatList = computed(() => flattenTree(tree.value))
const totalCount = computed(() => flatList.value.length)
const activeCount = computed(() => flatList.value.filter(c => c.isActive).length)
const assignedProducts = computed(() => flatList.value.reduce((sum, c) => sum + (c.productsCount ?? 0), 0))

const parentOptions = computed(() => flatList.value)

const depthOf = (id: string): number => {
    let d = 0
    let current = flatList.value.find(c => c.id === id)
    while (current?.parentId) {
        d++
        current = flatList.value.find(c => c.id === current!.parentId)
    }
    return d
}

const indentLabel = (c: Category) => `${'— '.repeat(depthOf(c.id))}${c.name}`

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
const form = reactive<CreateCategoryPayload>({
    name: '',
    slug: null,
    description: null,
    color: '#6366f1',
    sort_order: 0,
    is_active: true,
    parent_id: null,
})

const resetForm = () => {
    form.name = ''
    form.slug = null
    form.description = null
    form.color = '#6366f1'
    form.sort_order = 0
    form.is_active = true
    form.parent_id = null
}

const openCreateModal = (parentId?: string | null) => {
    isEdit.value = false
    editId.value = null
    resetForm()
    form.parent_id = parentId ?? null
    showFormModal.value = true
}

const openEditModal = (c: Category) => {
    isEdit.value = true
    editId.value = c.id
    form.name = c.name
    form.slug = c.slug
    form.description = c.description
    form.color = c.color || '#6366f1'
    form.sort_order = c.sortOrder
    form.is_active = c.isActive
    form.parent_id = c.parentId
    showFormModal.value = true
}

const saveCategory = async () => {
    submitting.value = true
    try {
        const payload: CreateCategoryPayload = {
            ...form,
            slug: form.slug || null,
            description: form.description || null,
            color: form.color || null,
        }
        if (isEdit.value && editId.value) {
            await inventory.categories.update(editId.value, payload)
            toast.success('Category updated', payload.name)
        } else {
            await inventory.categories.create(payload)
            toast.success('Category created', payload.name)
        }
        showFormModal.value = false
        await load()
    } catch (err: any) {
        toast.error('Save failed', err?.data?.message)
    } finally {
        submitting.value = false
    }
}

const deleteTarget = ref<Category | null>(null)
const confirmDelete = (c: Category) => { deleteTarget.value = c }
const onConfirmDelete = async () => {
    if (!deleteTarget.value) return
    deleting.value = true
    try {
        await inventory.categories.destroy(deleteTarget.value.id)
        toast.success('Category archived', deleteTarget.value.name)
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
        const res = await inventory.categories.tree()
        tree.value = res.data
    } catch (err: any) {
        toast.error('Failed to load categories', err?.data?.message)
    } finally {
        loading.value = false
    }
}

onMounted(load)

const CategoryNode = defineComponent({
    name: 'CategoryNode',
    props: {
        node: { type: Object as PropType<Category>, required: true },
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
                    class: 'flex items-center gap-2 px-2 py-2 rounded-lg hover:bg-(--bg-muted)/60 transition-colors',
                    style: { paddingLeft: indent },
                }, [
                    h('button', {
                        type: 'button',
                        class: 'w-5 h-5 inline-flex items-center justify-center text-(--text-muted) hover:text-(--color-primary)',
                        onClick: () => { if (hasChildren) open.value = !open.value },
                    }, hasChildren
                        ? h('i', { class: `ti ${open.value ? 'ti-chevron-down' : 'ti-chevron-right'} text-xs` })
                        : h('i', { class: 'ti ti-point text-xs opacity-40' })),
                    h('span', {
                        class: 'w-3 h-3 rounded-sm shrink-0 border border-(--border-color)',
                        style: { backgroundColor: props.node.color || 'transparent' },
                    }),
                    h('div', { class: 'flex-1 min-w-0' }, [
                        h('p', { class: 'text-xs font-semibold text-(--text-heading) truncate' }, props.node.name),
                        h('p', { class: 'text-xxs text-(--text-muted) font-mono truncate' },
                            `${props.node.slug} · ${props.node.productsCount ?? 0} product${(props.node.productsCount ?? 0) === 1 ? '' : 's'}`),
                    ]),
                    !props.node.isActive
                        ? h('span', { class: 'text-xxs px-1.5 py-0.5 rounded bg-(--bg-muted) text-(--text-muted) font-mono' }, 'inactive')
                        : null,
                    h('div', { class: 'flex items-center gap-1' }, [
                        props.canWrite ? h('button', {
                            type: 'button',
                            class: 'action-btn',
                            title: 'Add sub-category',
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
                            h(CategoryNode, {
                                key: child.id,
                                node: child,
                                depth: props.depth + 1,
                                canWrite: props.canWrite,
                                canDelete: props.canDelete,
                                onAddChild: (id: string) => emit('add-child', id),
                                onEdit: (n: Category) => emit('edit', n),
                                onDelete: (n: Category) => emit('delete', n),
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
</style>
