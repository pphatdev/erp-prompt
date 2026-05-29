<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Page header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Modules Management</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        Enable, disable, and organize modules for
                        <span class="text-(--color-primary) font-semibold">{{ tenantStore.activeName }}</span>.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button class="btn text-xs"
                        :class="modulesDirty ? 'text-(--text-body) border border-(--border-color) hover:bg-(--bg-muted)' : 'text-(--text-muted) cursor-not-allowed'"
                        :disabled="!modulesDirty || saving" @click="reset">
                        <i class="ti ti-restore" /> Revert
                    </button>
                    <button class="btn btn-primary text-xs" :disabled="!modulesDirty || saving" @click="save">
                        <i :class="['ti', saving ? 'ti-loader-2 animate-spin' : 'ti-device-floppy']" />
                        {{ saving ? 'Saving...' : 'Save changes' }}
                    </button>
                </div>
            </header>

            <div class="flex-1 min-w-0">
                <div class="space-y-4">
                    <div class="glass-card rounded-2xl p-5 flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-2">
                                <i class="ti ti-puzzle text-(--color-primary)" />
                                <h3 class="text-sm font-semibold">Module Visibility</h3>
                            </div>
                            <p class="text-xs text-(--text-muted) mt-1">Enable or disable modules for this tenant.
                                Core
                                modules are always on.</p>
                        </div>
                        <button class="btn text-xs" :disabled="loading" @click="loadAllModules">
                            <i :class="['ti', loading ? 'ti-loader-2 animate-spin' : 'ti-refresh']" />
                            Refresh
                        </button>
                    </div>

                    <div v-if="loading" class="py-12 flex justify-center">
                        <span
                            class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                    </div>

                    <template v-else>
                        <div v-for="group in moduleGroups" :key="group.key"
                            class="glass-card rounded-2xl overflow-hidden">
                            <div
                                class="px-6 py-3 bg-(--bg-muted)/60 border-b border-(--border-color) flex items-center gap-2">
                                <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">{{
                                    group.label }}</span>
                                <span class="text-xxs font-mono text-(--text-muted)">({{ group.items.length
                                    }})</span>
                            </div>

                            <div class="divide-y divide-(--border-color)">
                                <div v-for="(mod, index) in group.items" :key="mod.id"
                                    draggable="true"
                                    @dragstart="onDragStart($event, mod.id, group.key)"
                                    @dragover.prevent
                                    @dragenter.prevent="onDragEnter($event, mod.id, group.key)"
                                    @drop="onDrop($event, mod.id, group.key)"
                                    @dragend="onDragEnd"
                                    class="transition-all duration-200 relative"
                                    :class="{ 'opacity-50 border-2 border-dashed border-(--color-primary) bg-(--bg-muted)/40 scale-[0.98] *:opacity-20': draggedId === mod.id }">
                                    <!-- Parent module row -->
                                    <div class="flex items-center gap-4 px-6 py-3.5 transition-colors cursor-grab active:cursor-grabbing"
                                        :class="!mod.isActive && !mod.isCore ? 'opacity-60' : ''">
                                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 transition-colors"
                                            :class="mod.isActive ? 'bg-(--color-primary-subtle) text-(--color-primary)' : 'bg-(--bg-muted) text-(--text-muted)'">
                                            <i :class="['ti', mod.icon || 'ti-puzzle']" />
                                        </div>

                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center flex-wrap gap-1.5">
                                                <span class="text-sm font-semibold"
                                                    :class="mod.isActive ? 'text-(--text-heading)' : 'text-(--text-muted)'">
                                                    {{ mod.name }}
                                                </span>
                                                <div v-if="mod.products?.length" class="flex flex-wrap gap-1">
                                                    <span v-for="p in mod.products" :key="p.id"
                                                        class="badge-soft-success text-xxs px-1.5 py-0.5 rounded">
                                                        {{ p.name }}
                                                    </span>
                                                </div>
                                                <span
                                                    class="text-xxs font-mono px-1.5 py-0.5 rounded border border-(--border-color) text-(--text-muted)">
                                                    {{ mod.prefix }}
                                                </span>
                                                <span v-if="mod.isCore"
                                                    class="badge-soft-info text-xxs px-1.5 py-0.5 rounded flex items-center gap-1">
                                                    <i class="ti ti-lock text-[10px]" /> Core
                                                </span>
                                            </div>
                                        </div>

                                        <!-- Toggle switch -->
                                        <button type="button"
                                            :title="mod.isCore ? 'Core module — always enabled' : mod.isActive ? 'Disable module' : 'Enable module'"
                                            class="relative inline-flex h-5 w-9 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none"
                                            :class="[
                                                mod.isCore ? 'cursor-not-allowed' : 'cursor-pointer',
                                                mod.isActive ? 'bg-(--color-primary)' : 'bg-(--bg-subtle) border border-(--border-color)',
                                            ]" :disabled="mod.isCore || togglingId === mod.id"
                                            @click="toggleModule(mod)">
                                            <span
                                                class="pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow transform transition duration-200"
                                                :class="mod.isActive ? 'translate-x-4' : 'translate-x-0'" />
                                            <span v-if="togglingId === mod.id"
                                                class="absolute inset-0 flex items-center justify-center rounded-full">
                                                <span
                                                    class="w-3 h-3 rounded-full border border-(--color-primary)/40 border-t-(--color-primary) animate-spin" />
                                            </span>
                                        </button>
                                    </div>

                                    <!-- Children -->
                                    <div v-if="mod.children?.length"
                                        class="bg-(--bg-muted)/30 border-t border-(--border-color)/60">
                                        <div v-for="(child, childIdx) in mod.children" :key="child.id">
                                            <!-- Child row (drag + toggle target) -->
                                            <div draggable="true"
                                                @dragstart.stop="onChildDragStart($event, child.id, mod.id)"
                                                @dragover.prevent.stop
                                                @dragenter.prevent.stop="onChildDragEnter($event, child.id, mod.id)"
                                                @drop.stop="onChildDrop($event, child.id, mod.id)"
                                                @dragend.stop="onChildDragEnd"
                                                class="flex items-center gap-3 px-6 py-2.5 pl-16 border-b border-(--border-color)/40 transition-all duration-200 cursor-grab active:cursor-grabbing"
                                                :class="[
                                                    !child.isActive ? 'opacity-60' : '',
                                                    draggedChildId === child.id ? 'opacity-50 border-2 border-dashed border-(--color-primary) bg-(--bg-muted)/40 scale-[0.98] *:opacity-20' : ''
                                                ]">
                                                <div class="w-6 h-6 rounded-md flex items-center justify-center shrink-0"
                                                    :class="child.isActive ? 'bg-(--color-primary-subtle) text-(--color-primary)' : 'bg-(--bg-muted) text-(--text-muted)'">
                                                    <i :class="['ti', child.icon || 'ti-circle', 'text-xs']" />
                                                </div>
                                                <div class="flex-1 min-w-0 flex items-center gap-1.5">
                                                    <span class="text-xs font-medium"
                                                        :class="child.isActive ? 'text-(--text-heading)' : 'text-(--text-muted)'">
                                                        {{ child.name }}
                                                    </span>
                                                    <span
                                                        class="text-xxs font-mono px-1 py-0.5 rounded border border-(--border-color) text-(--text-muted)">
                                                        {{ child.prefix }}
                                                    </span>
                                                </div>
                                                <button type="button"
                                                    class="relative inline-flex h-4 w-7 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none cursor-pointer"
                                                    :class="child.isActive ? 'bg-(--color-primary)' : 'bg-(--bg-subtle) border border-(--border-color)'"
                                                    :disabled="togglingId === child.id" @click="toggleModule(child)">
                                                    <span
                                                        class="pointer-events-none inline-block h-3 w-3 rounded-full bg-white shadow transform transition duration-200"
                                                        :class="child.isActive ? 'translate-x-3' : 'translate-x-0'" />
                                                </button>
                                            </div>

                                            <!-- Grandchildren (3rd level — e.g. HRM > Employees > List). Drag handlers reuse the child set since findById walks any depth. -->
                                            <div v-if="child.children?.length" class="bg-(--bg-muted)/50 border-b border-(--border-color)/40">
                                                <div v-for="grand in child.children" :key="grand.id"
                                                    draggable="true"
                                                    @dragstart.stop="onChildDragStart($event, grand.id, child.id)"
                                                    @dragover.prevent.stop
                                                    @dragenter.prevent.stop="onChildDragEnter($event, grand.id, child.id)"
                                                    @drop.stop="onChildDrop($event, grand.id, child.id)"
                                                    @dragend.stop="onChildDragEnd"
                                                    class="flex items-center gap-3 px-6 py-2 pl-24 border-b border-(--border-color)/30 last:border-b-0 cursor-grab active:cursor-grabbing"
                                                    :class="[
                                                        !grand.isActive ? 'opacity-60' : '',
                                                        draggedChildId === grand.id ? 'opacity-50 border-2 border-dashed border-(--color-primary) bg-(--bg-muted)/40 scale-[0.98] *:opacity-20' : ''
                                                    ]">
                                                    <div class="w-5 h-5 rounded-md flex items-center justify-center shrink-0"
                                                        :class="grand.isActive ? 'bg-(--color-primary-subtle) text-(--color-primary)' : 'bg-(--bg-muted) text-(--text-muted)'">
                                                        <i :class="['ti', grand.icon || 'ti-circle-dot', 'text-[10px]']" />
                                                    </div>
                                                    <div class="flex-1 min-w-0 flex items-center gap-1.5">
                                                        <span class="text-xs"
                                                            :class="grand.isActive ? 'text-(--text-body)' : 'text-(--text-muted)'">
                                                            {{ grand.name }}
                                                        </span>
                                                        <span
                                                            class="text-xxs font-mono px-1 py-0.5 rounded border border-(--border-color) text-(--text-muted)">
                                                            {{ grand.prefix }}
                                                        </span>
                                                    </div>
                                                    <button type="button"
                                                        class="relative inline-flex h-4 w-7 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none cursor-pointer"
                                                        :class="grand.isActive ? 'bg-(--color-primary)' : 'bg-(--bg-subtle) border border-(--border-color)'"
                                                        :disabled="togglingId === grand.id" @click="toggleModule(grand)">
                                                        <span
                                                            class="pointer-events-none inline-block h-3 w-3 rounded-full bg-white shadow transform transition duration-200"
                                                            :class="grand.isActive ? 'translate-x-3' : 'translate-x-0'" />
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useAuthStore } from '~/stores/auth'
import { useTenantStore } from '~/stores/tenant'
import { useToast } from '~/composables/useToast'

const tenantStore = useTenantStore()
const authStore = useAuthStore()
const toast = useToast()
const api = useApi()
const { reload: reloadSidebar } = useModules()

definePageMeta({
    breadcrumb: 'Modules Management',
    middleware: [
        function (to, from) {
            const authStore = useAuthStore()
            if (!authStore.isAdmin) return navigateTo('/dashboard')
        }
    ]
})

const loading = ref(true)
const saving = ref(false)

interface ModuleItem {
    id: string
    slug: string
    prefix: string
    name: string
    icon: string | null
    group: string
    isActive: boolean
    isCore: boolean
    products?: { id: string; name: string; sku: string }[]
    children?: ModuleItem[]
}

const GROUP_LABELS: Record<string, string> = {
    main: 'Main',
    'self-service': 'Self Service',
    apps: 'Apps & Modules',
}

const pristineModules = ref<ModuleItem[]>([])
const allModules = ref<ModuleItem[]>([])
const togglingId = ref<string | null>(null)

const modulesDirty = computed(() => {
    return JSON.stringify(allModules.value) !== JSON.stringify(pristineModules.value)
})

// Drag and Drop state
const draggedId = ref<string | null>(null)
const draggedGroupKey = ref<string | null>(null)

const onDragStart = (e: DragEvent, id: string, groupKey: string) => {
    draggedGroupKey.value = groupKey
    if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'move'
        e.dataTransfer.setData('text/plain', id)
    }
    // Defer setting the dragged state so the browser clones the normal DOM element for the ghost image
    setTimeout(() => { draggedId.value = id }, 0)
}

const onDragEnter = (e: DragEvent, overId: string, groupKey: string) => {
    if (!draggedId.value || draggedId.value === overId || draggedGroupKey.value !== groupKey) return
    
    const fromIdx = allModules.value.findIndex(m => m.id === draggedId.value)
    const toIdx = allModules.value.findIndex(m => m.id === overId)
    
    if (fromIdx !== -1 && toIdx !== -1) {
        const [moved] = allModules.value.splice(fromIdx, 1)
        allModules.value.splice(toIdx, 0, moved)
    }
}

const onDrop = (e: DragEvent, targetId: string, groupKey: string) => {
    draggedId.value = null
    draggedGroupKey.value = null
}

const onDragEnd = () => {
    draggedId.value = null
    draggedGroupKey.value = null
}

// Child Drag and Drop state
const draggedChildId = ref<string | null>(null)
const draggedParentId = ref<string | null>(null)

const onChildDragStart = (e: DragEvent, childId: string, parentId: string) => {
    draggedParentId.value = parentId
    if (e.dataTransfer) {
        e.dataTransfer.effectAllowed = 'move'
        e.dataTransfer.setData('text/plain', childId)
    }
    setTimeout(() => { draggedChildId.value = childId }, 0)
}

// HRM is 3 levels deep (HRM > Employee Management > List), so parentId may
// resolve to a 2nd-level group like hrm-employees. Plain Array.find on the
// top-level list misses those — walk the whole tree.
const findById = (id: string, mods: ModuleItem[] = allModules.value): ModuleItem | null => {
    for (const m of mods) {
        if (m.id === id) return m
        if (m.children?.length) {
            const f = findById(id, m.children)
            if (f) return f
        }
    }
    return null
}

const onChildDragEnter = (e: DragEvent, overChildId: string, parentId: string) => {
    if (!draggedChildId.value || draggedChildId.value === overChildId || draggedParentId.value !== parentId) return

    const parentModule = findById(parentId)
    if (parentModule && parentModule.children) {
        const fromIdx = parentModule.children.findIndex(c => c.id === draggedChildId.value)
        const toIdx = parentModule.children.findIndex(c => c.id === overChildId)

        if (fromIdx !== -1 && toIdx !== -1) {
            const [moved] = parentModule.children.splice(fromIdx, 1)
            parentModule.children.splice(toIdx, 0, moved)
        }
    }
}

const onChildDrop = (e: DragEvent, targetChildId: string, parentId: string) => {
    draggedChildId.value = null
    draggedParentId.value = null
}

const onChildDragEnd = () => {
    draggedChildId.value = null
    draggedParentId.value = null
}

const moduleGroups = computed(() => {
    const map: Record<string, ModuleItem[]> = {}
    for (const m of allModules.value) {
        if (!map[m.group]) map[m.group] = []
        map[m.group].push(m)
    }
    return Object.entries(map).map(([key, items]) => ({
        key,
        label: GROUP_LABELS[key] ?? key,
        items,
    }))
})

const loadAllModules = async () => {
    loading.value = true
    try {
        const res = await api.get<{ data: ModuleItem[] }>('modules/all')
        allModules.value = res.data
        pristineModules.value = JSON.parse(JSON.stringify(res.data))
    } catch {
        toast.error('Failed to load modules', 'Could not fetch module list.')
    } finally {
        loading.value = false
    }
}

const toggleModule = (mod: ModuleItem) => {
    if (mod.isCore) return
    mod.isActive = !mod.isActive
}

const save = async () => {
    if (!modulesDirty.value) return
    saving.value = true
    try {
        const payload: Array<{ id: string; is_active: boolean; sort_order: number }> = []
        let sortOrder = 0
        const walk = (m: ModuleItem) => {
            payload.push({ id: m.id, is_active: m.isActive, sort_order: ++sortOrder })
            if (m.children) m.children.forEach(walk)
        }
        for (const group of moduleGroups.value) {
            for (const mod of group.items) walk(mod)
        }

        await api.put('modules/bulk', { modules: payload })
        pristineModules.value = JSON.parse(JSON.stringify(allModules.value))
        reloadSidebar()
        
        toast.success('Modules updated', 'Module configuration has been saved successfully.')
    } catch (err: any) {
        toast.error('Failed to save', err?.data?.message || 'Failed to save module configuration.')
    } finally {
        saving.value = false
    }
}

const reset = () => {
    if (pristineModules.value.length) {
        allModules.value = JSON.parse(JSON.stringify(pristineModules.value))
    }
}

onMounted(() => {
    loadAllModules()
})
</script>
