<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- ============================ Page header ========================== -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Access Control</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        Tenant-scoped role groups and granular API scopes for
                        <span class="text-(--color-primary) font-semibold">{{ tenantStore.activeName }}</span>.
                        Permissions are seeded automatically — every module surfaces here.
                    </p>
                </div>
                
                <!-- Save/Revert only when editing a role policy and there are pending changes -->
                <div v-if="activeTab === 'roles' && selectedRole && selectedRole.slug !== 'admin'"
                    class="flex items-center gap-2">
                    <button class="btn text-xs"
                        :class="isDirty ? 'text-(--text-body) border border-(--border-color) hover:bg-(--bg-muted)' : 'text-(--text-muted) cursor-not-allowed'"
                        :disabled="!isDirty || busy" @click="resetSelected">
                        <i class="ti ti-restore" /> Revert
                    </button>
                    <button class="btn btn-primary text-xs"
                        :disabled="!isDirty || busy || !canWrite" @click="updateRolePermissions">
                        <i :class="['ti', busy ? 'ti-loader-2 animate-spin' : 'ti-device-floppy']" />
                        {{ busy ? 'Saving...' : 'Save changes' }}
                    </button>
                </div>
            </header>

            <!-- ============================ Sidebar tabs + content ====================== -->
            <div class="flex flex-col lg:flex-row gap-4">
                <!-- Vertical rail — desktop -->
                <aside class="lg:w-60 lg:shrink-0">
                    <!-- Mobile: horizontal scroll strip -->
                    <nav class="lg:hidden glass-card rounded-xl px-2 py-1.5 flex items-center gap-1 overflow-x-auto">
                        <button v-for="t in tabs" :key="t.key" type="button" class="tab-pill"
                            :class="{ 'tab-pill-active': activeTab === t.key }" @click="setTab(t.key)">
                            <i :class="['ti', t.icon]" />
                            <span>{{ t.label }}</span>
                        </button>
                    </nav>
                    <!-- Desktop: vertical sidebar -->
                    <nav class="hidden lg:block glass-card rounded-2xl p-2 sticky top-24 space-y-1">
                        <button v-for="t in tabs" :key="t.key" type="button" class="tab-rail-item"
                            :class="{ 'tab-rail-item-active': activeTab === t.key }" @click="setTab(t.key)">
                            <i :class="['ti', t.icon, 'tab-rail-icon']" />
                            <span class="flex-1 text-left truncate">{{ t.label }}</span>
                            <i v-if="activeTab === t.key" class="ti ti-chevron-right text-base" />
                        </button>
                    </nav>
                </aside>

                <!-- Active tab pane -->
                <div class="flex-1 min-w-0 space-y-4">
                    <!-- ============================ Role groups tab ============================ -->
                    <template v-if="activeTab === 'roles'">
                        <!-- Roles list + matrix bento -->
                        <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 items-start">
                            <!-- Roles list -->
                            <aside class="xl:col-span-4 glass-card rounded-2xl p-4 space-y-3">
                                <header class="flex items-center justify-between px-1">
                                    <h4 class="flex items-center gap-2">
                                        <i class="ti ti-shield-lock text-(--color-primary)" />
                                        <span class="text-sm font-semibold text-(--text-heading)">Security groups</span>
                                    </h4>
                                    <button v-if="canWrite" type="button" class="chip-btn"
                                        title="Create role group" @click="openCreateRoleModal">
                                        <i class="ti ti-plus" />
                                    </button>
                                </header>

                                <div v-if="loadingRoles" class="py-12 flex justify-center">
                                    <span class="w-6 h-6 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                                </div>

                                <ul v-else class="space-y-1.5">
                                    <li v-for="role in roles" :key="role.id">
                                        <button type="button" class="role-card"
                                            :class="selectedRole?.id === role.id ? 'role-card--active' : ''"
                                            @click="selectRole(role)">
                                            <div class="flex items-center justify-between gap-2 w-full">
                                                <span class="text-xs font-semibold truncate"
                                                    :class="selectedRole?.id === role.id ? 'text-(--color-primary)' : 'text-(--text-heading)'">
                                                    {{ role.name }}
                                                </span>
                                                <span class="text-xxs font-mono px-1.5 py-0.5 rounded border border-(--border-color) text-(--text-muted) uppercase shrink-0">
                                                    {{ role.slug }}
                                                </span>
                                            </div>
                                            <div class="flex items-center justify-between gap-2 w-full mt-1">
                                                <span class="text-xxs text-(--text-muted) truncate">{{ role.description || 'No description' }}</span>
                                                <span class="text-xxs font-mono text-(--color-primary) shrink-0">
                                                    <i class="ti ti-key text-[10px]" />
                                                    {{ role.permissions?.length ?? 0 }}
                                                </span>
                                            </div>
                                        </button>
                                    </li>
                                </ul>
                            </aside>

                            <!-- Permission matrix -->
                            <div class="xl:col-span-8 space-y-4">
                                <div v-if="!selectedRole" class="glass-card rounded-2xl py-20 text-center">
                                    <i class="ti ti-shield text-4xl text-(--text-muted)" />
                                    <h4 class="text-sm font-semibold text-(--text-heading) mt-3">
                                        Select a security group to inspect
                                    </h4>
                                    <p class="text-xs text-(--text-muted) mt-1">
                                        Toggle granular permissions to commit policy updates.
                                    </p>
                                </div>

                                <template v-else>
                                    <!-- Role metadata + filter strip -->
                                    <div class="glass-card rounded-2xl p-5 space-y-4">
                                        <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                            <div class="min-w-0">
                                                <h3 class="text-sm font-semibold text-(--text-heading) flex items-center gap-2 flex-wrap max-sm:justify-center">
                                                    Role policy
                                                    <span class="text-(--color-primary)">{{ selectedRole.name }}</span>
                                                    <span v-if="selectedRole.slug === 'admin'"
                                                        class="badge-soft-warning text-xxs px-1.5 py-0.5 rounded">super-admin</span>
                                                </h3>
                                                <p class="text-xs text-(--text-muted) mt-1">
                                                    <span v-if="selectedRole.slug === 'admin'">
                                                        <i class="ti ti-info-circle" /> Admins bypass every permission check via `Gate::before` — toggles below are read-only.
                                                    </span>
                                                    <span v-else>
                                                        {{ activePermissionIds.length }} of {{ flatPermissions.length }} permissions enabled
                                                        <span v-if="isDirty" class="text-(--color-warning) ml-2">
                                                            <i class="ti ti-circle-filled text-[6px]" /> unsaved changes
                                                        </span>
                                                    </span>
                                                </p>
                                            </div>
                                            <button v-if="canWrite && selectedRole.slug !== 'admin'" type="button"
                                                class="btn text-xs text-(--color-danger) border border-(--color-danger)/20 hover:bg-(--color-danger-subtle)"
                                                :disabled="busy" @click="confirmDeleteRole">
                                                <i class="ti ti-trash" /> Delete
                                            </button>
                                        </header>

                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                            <div>
                                                <label class="form-label">Role name</label>
                                                <input v-model="selectedRole.name" type="text" class="form-control text-xs"
                                                    :disabled="selectedRole.slug === 'admin' || !canWrite" />
                                            </div>
                                            <div>
                                                <label class="form-label">Description</label>
                                                <input v-model="selectedRole.description" type="text" class="form-control text-xs"
                                                    :disabled="selectedRole.slug === 'admin' || !canWrite" />
                                            </div>
                                        </div>

                                        <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
                                            <div class="relative flex-1">
                                                <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                                                <input v-model.lazy="search" type="search"
                                                    placeholder="Filter by name, slug, or module..."
                                                    class="form-control pl-9 text-xs" />
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                <button type="button" class="btn btn-ghost text-xs"
                                                    :disabled="!canWrite || selectedRole.slug === 'admin' || isAllOn"
                                                    @click="selectAllVisible">
                                                    <i class="ti ti-check-all" /> Enable visible
                                                </button>
                                                <button type="button" class="btn btn-ghost text-xs"
                                                    :disabled="!canWrite || selectedRole.slug === 'admin' || isAllOff"
                                                    @click="clearAllVisible">
                                                    <i class="ti ti-square-off" /> Clear visible
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="loadingPermissions"
                                        class="glass-card rounded-2xl py-16 flex flex-col items-center gap-3">
                                        <span class="w-7 h-7 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                                        <span class="text-xs text-(--text-muted)">Loading permission catalogue...</span>
                                    </div>

                                    <div v-else-if="visibleModules.length === 0"
                                        class="glass-card rounded-2xl py-16 text-center">
                                        <i class="ti ti-search-off text-3xl text-(--text-muted)" />
                                        <p class="text-sm text-(--text-heading) mt-2">No permissions match "{{ search }}"</p>
                                    </div>

                                    <article v-for="mod in visibleModules" :key="mod.key"
                                        class="glass-card rounded-2xl p-5">
                                        <header class="flex items-center justify-between mb-4">
                                            <button type="button" class="flex items-center gap-2 text-left"
                                                @click="toggleCollapsed(mod.key)">
                                                <i :class="['ti text-(--text-muted) text-sm',
                                                    collapsed[mod.key] ? 'ti-chevron-right' : 'ti-chevron-down']" />
                                                <h5 class="text-xs font-bold uppercase tracking-widest text-(--text-heading)">
                                                    {{ mod.label }}
                                                </h5>
                                                <span class="text-xxs font-mono text-(--text-muted)">
                                                    {{ mod.enabledCount }} / {{ mod.totalCount }}
                                                </span>
                                            </button>
                                            <div class="flex items-center gap-1.5">
                                                <button type="button" class="chip-btn"
                                                    :disabled="!canWrite || selectedRole.slug === 'admin' || mod.enabledCount === mod.totalCount"
                                                    @click="enableModule(mod)">
                                                    Enable all
                                                </button>
                                                <button type="button" class="chip-btn"
                                                    :disabled="!canWrite || selectedRole.slug === 'admin' || mod.enabledCount === 0"
                                                    @click="clearModule(mod)">
                                                    Clear all
                                                </button>
                                            </div>
                                        </header>

                                        <div v-if="!collapsed[mod.key]" class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                            <div v-for="feat in mod.features" :key="feat.key"
                                                class="rounded-xl bg-(--bg-muted)/40 border border-(--border-color) p-3">
                                                <header class="flex items-center justify-between mb-2">
                                                    <h6 class="text-xs font-semibold text-(--text-heading) capitalize">
                                                        {{ feat.label }}
                                                    </h6>
                                                    <span class="text-xxs font-mono text-(--text-muted)">
                                                        {{ feat.enabledCount }} / {{ feat.permissions.length }}
                                                    </span>
                                                </header>
                                                <ul class="space-y-1">
                                                    <li v-for="perm in feat.permissions" :key="perm.id">
                                                        <label class="perm-row"
                                                            :class="isPermissionChecked(perm.id) ? 'perm-row--on' : ''">
                                                            <span class="min-w-0 pr-3">
                                                                <span class="block text-xs text-(--text-heading)">{{ perm.name }}</span>
                                                                <span class="block text-xxs font-mono text-(--text-muted) truncate">
                                                                    {{ perm.slug }}
                                                                </span>
                                                            </span>
                                                            <input type="checkbox" :checked="isPermissionChecked(perm.id)"
                                                                :disabled="!canWrite || selectedRole.slug === 'admin'"
                                                                class="w-4 h-4 rounded border-(--border-color) text-(--color-primary) shrink-0"
                                                                @change="togglePermission(perm.id)" />
                                                        </label>
                                                    </li>
                                                </ul>
                                            </div>
                                        </div>
                                    </article>
                                </template>
                            </div>
                        </div>
                    </template>

                    <!-- ============================ Permission catalogue tab ============================ -->
                    <template v-else-if="activeTab === 'catalogue'">
                        <div class="glass-card rounded-2xl p-5 space-y-3">
                            <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <h3 class="text-sm font-semibold text-(--text-heading)">Permission catalogue</h3>
                                    <p class="text-xs text-(--text-muted) mt-1">
                                        Every permission slug seeded into this tenant. Read-only — use the
                                        <strong>Role groups</strong> tab to attach them to a role.
                                    </p>
                                </div>
                                <div class="text-xxs font-mono text-(--text-muted)">
                                    {{ flatPermissions.length }} permissions across {{ catalogueModules.length }} modules
                                </div>
                            </header>

                            <div class="relative">
                                <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                                <input v-model.lazy="catalogueSearch" type="search"
                                    placeholder="Search by name, slug, or module..."
                                    class="form-control pl-9 text-xs" />
                            </div>
                        </div>

                        <div v-if="loadingPermissions" class="glass-card rounded-2xl py-16 flex flex-col items-center gap-3">
                            <span class="w-7 h-7 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                            <span class="text-xs text-(--text-muted)">Loading permission catalogue...</span>
                        </div>

                        <div v-else-if="catalogueModules.length === 0" class="glass-card rounded-2xl py-16 text-center">
                            <i class="ti ti-search-off text-3xl text-(--text-muted)" />
                            <p class="text-sm text-(--text-heading) mt-2">No permissions match "{{ catalogueSearch }}"</p>
                        </div>

                        <article v-for="mod in catalogueModules" :key="mod.key" class="glass-card rounded-2xl p-5">
                            <header class="flex items-center justify-between">
                                <h5 class="text-xs font-bold uppercase tracking-widest text-(--text-heading)">
                                    {{ mod.label }}
                                </h5>
                                <span class="text-xxs font-mono text-(--text-muted)">{{ mod.totalCount }} slugs</span>
                            </header>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                <div v-for="feat in mod.features" :key="feat.key"
                                    class="rounded-xl bg-(--bg-muted)/40 border border-(--border-color) p-3">
                                    <h6 class="text-xs font-semibold text-(--text-heading) capitalize mb-2">
                                        {{ feat.label }}
                                    </h6>
                                    <ul class="space-y-1">
                                        <li v-for="perm in feat.permissions" :key="perm.id"
                                            class="flex items-center justify-between gap-2 px-2 py-1.5 rounded hover:bg-(--bg-card)">
                                            <span class="min-w-0">
                                                <span class="block text-xs text-(--text-heading)">{{ perm.name }}</span>
                                                <span class="block text-xxs font-mono text-(--text-muted) truncate">{{ perm.slug }}</span>
                                            </span>
                                            <span class="text-xxs font-mono px-1.5 py-0.5 rounded badge-soft-secondary uppercase shrink-0">
                                                {{ perm.action }}
                                            </span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </article>
                    </template>
                </div>
            </div>

            <!-- ============================ Create role modal ====================== -->
            <div v-if="showCreateModal"
                class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                    <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                        <h3 class="font-semibold text-sm">Create role group</h3>
                        <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center"
                            @click="showCreateModal = false">
                            <i class="ti ti-x" />
                        </button>
                    </header>
                    <form @submit.prevent="createRole">
                        <div class="p-5 space-y-4">
                            <div>
                                <label class="form-label">Role name *</label>
                                <input v-model="newRoleForm.name" type="text" required class="form-control text-xs"
                                    placeholder="e.g. Sales Representative" />
                            </div>
                            <div>
                                <label class="form-label">Slug *</label>
                                <input v-model="newRoleForm.slug" type="text" required pattern="[a-z0-9-]+"
                                    class="form-control text-xs font-mono" placeholder="sales-rep" />
                                <p class="text-xxs text-(--text-muted) mt-1">
                                    Lowercase letters, numbers, and hyphens only. Used in API checks.
                                </p>
                            </div>
                            <div>
                                <label class="form-label">Description</label>
                                <textarea v-model="newRoleForm.description" rows="3" class="form-control text-xs resize-none"
                                    placeholder="Standard CRM access for inbound lead handling." />
                            </div>
                        </div>
                        <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                            <button type="button" class="btn btn-ghost text-xs" @click="showCreateModal = false">Cancel</button>
                            <button type="submit" class="btn btn-primary text-xs" :disabled="busy">
                                <i v-if="busy" class="ti ti-loader-2 animate-spin" />
                                Create
                            </button>
                        </footer>
                    </form>
                </div>
            </div>

            <!-- ============================ Delete confirm ====================== -->
            <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                    <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                        <h3 class="font-semibold text-sm">Delete role group</h3>
                        <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center"
                            @click="deleteTarget = null">
                            <i class="ti ti-x" />
                        </button>
                    </header>
                    <div class="p-5 text-xs text-(--text-muted) space-y-2">
                        <p>
                            Delete <span class="font-semibold text-(--text-heading)">{{ deleteTarget.name }}</span>?
                            Users currently in this group will lose every permission attached here.
                        </p>
                        <p class="font-semibold text-(--color-danger)">
                            <i class="ti ti-alert-triangle" /> This cannot be undone.
                        </p>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="deleteTarget = null">Cancel</button>
                        <button type="button" class="btn btn-danger text-xs" :disabled="busy" @click="deleteRoleConfirmed">
                            <i v-if="busy" class="ti ti-loader-2 animate-spin" />
                            Delete role
                        </button>
                    </footer>
                </div>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useApi } from '~/composables/useApi'
import { useAuthStore } from '~/stores/auth'
import { useToast } from '~/composables/useToast'
import { useTenantStore } from '~/stores/tenant'

definePageMeta({
    breadcrumb: 'Access Control',
    breadcrumbTrail: [
        { label: 'Settings' },
        { label: 'Access Control' },
    ],
})

interface Permission {
    id: string
    name: string
    slug: string
    module: string
    feature: string
    action: string
}
interface Role {
    id: string
    name: string
    slug: string
    description?: string | null
    permissions?: Permission[]
}
interface FeatureGroup {
    key: string
    label: string
    permissions: Permission[]
    enabledCount: number
}
interface ModuleGroup {
    key: string
    label: string
    features: FeatureGroup[]
    enabledCount: number
    totalCount: number
}

type TabKey = 'roles' | 'catalogue'

const tabs: { key: TabKey; label: string; icon: string }[] = [
    { key: 'roles',     label: 'Role groups',          icon: 'ti-shield-lock' },
    { key: 'catalogue', label: 'Permission catalogue', icon: 'ti-list-search' },
]
const validTabs = new Set<TabKey>(tabs.map(t => t.key))

const route = useRoute()
const router = useRouter()
const api = useApi()
const authStore = useAuthStore()
const toast = useToast()
const tenantStore = useTenantStore()

const initialTab = (): TabKey => {
    const raw = route.query.tab
    return typeof raw === 'string' && validTabs.has(raw as TabKey) ? (raw as TabKey) : 'roles'
}
const activeTab = ref<TabKey>(initialTab())

const setTab = (key: TabKey) => {
    activeTab.value = key
    router.replace({ query: { ...route.query, tab: key } })
}

watch(() => route.query.tab, (q) => {
    if (typeof q === 'string' && validTabs.has(q as TabKey) && q !== activeTab.value) {
        activeTab.value = q as TabKey
    }
})

const canRead = computed(() => authStore.hasPermission('iam.roles.read'))
const canWrite = computed(() => authStore.hasPermission('iam.roles.write'))

const roles = ref<Role[]>([])
const selectedRole = ref<Role | null>(null)
const flatPermissions = ref<Permission[]>([])
const activePermissionIds = ref<string[]>([])
const initialPermissionIds = ref<string[]>([])

const loadingRoles = ref(false)
const loadingPermissions = ref(false)
const busy = ref(false)

const showCreateModal = ref(false)
const deleteTarget = ref<Role | null>(null)
const newRoleForm = reactive({ name: '', slug: '', description: '' })

const search = ref('')
const catalogueSearch = ref('')
const collapsed = reactive<Record<string, boolean>>({})

const friendlyModuleLabels: Record<string, string> = {
    iam: 'Identity & Access',
    hrm: 'Human Resources',
    sales: 'Sales',
    crm: 'CRM',
    inventory: 'Inventory',
    fms: 'Finance',
    accounting: 'Accounting',
    assets: 'Fixed Assets',
    fleet: 'Fleet',
    projects: 'Projects',
    eapprovals: 'eApprovals',
    edocs: 'eDocuments',
    settings: 'Settings',
    reporting: 'Reporting',
    pos: 'Point of Sale',
    ecommerce: 'eCommerce',
    calendar: 'Calendar',
    documents: 'Documents',
}

const friendlyFeatureLabel = (s: string) => s.replace(/[_-]/g, ' ')

const isDirty = computed(() => {
    if (!selectedRole.value) return false
    const a = [...activePermissionIds.value].sort().join(',')
    const b = [...initialPermissionIds.value].sort().join(',')
    return a !== b
})

const buildModuleGroups = (permissions: Permission[]): ModuleGroup[] => {
    const byModule = new Map<string, Map<string, Permission[]>>()
    for (const p of permissions) {
        if (!byModule.has(p.module)) byModule.set(p.module, new Map())
        const byFeat = byModule.get(p.module)!
        if (!byFeat.has(p.feature)) byFeat.set(p.feature, [])
        byFeat.get(p.feature)!.push(p)
    }

    const modules: ModuleGroup[] = []
    for (const [moduleKey, byFeat] of byModule) {
        const features: FeatureGroup[] = []
        let modEnabled = 0
        let modTotal = 0
        for (const [featKey, perms] of byFeat) {
            const enabled = perms.filter(p => activePermissionIds.value.includes(p.id)).length
            features.push({
                key: `${moduleKey}.${featKey}`,
                label: friendlyFeatureLabel(featKey),
                permissions: perms,
                enabledCount: enabled,
            })
            modEnabled += enabled
            modTotal += perms.length
        }
        modules.push({
            key: moduleKey,
            label: friendlyModuleLabels[moduleKey] ?? moduleKey,
            features,
            enabledCount: modEnabled,
            totalCount: modTotal,
        })
    }
    modules.sort((a, b) => a.label.localeCompare(b.label))
    return modules
}

const filteredPermissions = computed(() => {
    const term = search.value.trim().toLowerCase()
    if (!term) return flatPermissions.value
    return flatPermissions.value.filter(p =>
        p.name.toLowerCase().includes(term) ||
        p.slug.toLowerCase().includes(term) ||
        p.module.toLowerCase().includes(term)
    )
})

const filteredCataloguePermissions = computed(() => {
    const term = catalogueSearch.value.trim().toLowerCase()
    if (!term) return flatPermissions.value
    return flatPermissions.value.filter(p =>
        p.name.toLowerCase().includes(term) ||
        p.slug.toLowerCase().includes(term) ||
        p.module.toLowerCase().includes(term)
    )
})

const visibleModules = computed(() => buildModuleGroups(filteredPermissions.value))
const catalogueModules = computed(() => buildModuleGroups(filteredCataloguePermissions.value))

const isAllOn = computed(() => {
    const ids = filteredPermissions.value.map(p => p.id)
    return ids.length > 0 && ids.every(id => activePermissionIds.value.includes(id))
})
const isAllOff = computed(() => {
    const ids = filteredPermissions.value.map(p => p.id)
    return ids.length > 0 && ids.every(id => !activePermissionIds.value.includes(id))
})

const isPermissionChecked = (id: string) => activePermissionIds.value.includes(id)

const togglePermission = (id: string) => {
    if (!canWrite.value || selectedRole.value?.slug === 'admin') return
    const idx = activePermissionIds.value.indexOf(id)
    if (idx === -1) activePermissionIds.value.push(id)
    else activePermissionIds.value.splice(idx, 1)
}

const enableModule = (mod: ModuleGroup) => {
    if (!canWrite.value || selectedRole.value?.slug === 'admin') return
    const ids = mod.features.flatMap(f => f.permissions.map(p => p.id))
    const set = new Set(activePermissionIds.value)
    ids.forEach(id => set.add(id))
    activePermissionIds.value = Array.from(set)
}
const clearModule = (mod: ModuleGroup) => {
    if (!canWrite.value || selectedRole.value?.slug === 'admin') return
    const ids = new Set(mod.features.flatMap(f => f.permissions.map(p => p.id)))
    activePermissionIds.value = activePermissionIds.value.filter(id => !ids.has(id))
}

const selectAllVisible = () => {
    if (!canWrite.value || selectedRole.value?.slug === 'admin') return
    const ids = filteredPermissions.value.map(p => p.id)
    const set = new Set(activePermissionIds.value)
    ids.forEach(id => set.add(id))
    activePermissionIds.value = Array.from(set)
}
const clearAllVisible = () => {
    if (!canWrite.value || selectedRole.value?.slug === 'admin') return
    const ids = new Set(filteredPermissions.value.map(p => p.id))
    activePermissionIds.value = activePermissionIds.value.filter(id => !ids.has(id))
}

const toggleCollapsed = (key: string) => {
    collapsed[key] = !collapsed[key]
}

const selectRole = (role: Role) => {
    selectedRole.value = { ...role }
    const ids = role.permissions?.map(p => p.id) ?? []
    activePermissionIds.value = [...ids]
    initialPermissionIds.value = [...ids]
    search.value = ''
}
const resetSelected = () => {
    activePermissionIds.value = [...initialPermissionIds.value]
}

const loadRoles = async () => {
    if (!canRead.value) return
    loadingRoles.value = true
    try {
        const res = await api.get<{ data: Role[] } | Role[]>('/roles?limit=200')
        roles.value = (res as any).data ?? (res as Role[])
        if (roles.value.length && !selectedRole.value) selectRole(roles.value[0]!)
    } catch (err: any) {
        toast.error('Failed to load roles', err?.data?.message)
    } finally {
        loadingRoles.value = false
    }
}

const loadPermissions = async () => {
    if (!canRead.value) return
    loadingPermissions.value = true
    try {
        const res = await api.get<{ data: Permission[] } | Permission[]>('/permissions')
        flatPermissions.value = (res as any).data ?? (res as Permission[])
    } catch (err: any) {
        toast.error('Failed to load permission catalogue', err?.data?.message)
    } finally {
        loadingPermissions.value = false
    }
}

const openCreateRoleModal = () => {
    newRoleForm.name = ''
    newRoleForm.slug = ''
    newRoleForm.description = ''
    showCreateModal.value = true
}

const createRole = async () => {
    if (!canWrite.value) return
    busy.value = true
    try {
        const res = await api.post<{ data: Role } | Role>('/roles', { ...newRoleForm })
        const created = (res as any).data ?? (res as Role)
        roles.value.push(created)
        selectRole(created)
        showCreateModal.value = false
        toast.success('Role created', `"${created.name}" is ready to receive permissions.`)
    } catch (err: any) {
        toast.error('Create failed', err?.data?.message || 'Slug must be unique and use lowercase letters, numbers, or hyphens.')
    } finally {
        busy.value = false
    }
}

const updateRolePermissions = async () => {
    if (!selectedRole.value || !canWrite.value) return
    busy.value = true
    try {
        const payload = {
            name: selectedRole.value.name,
            description: selectedRole.value.description ?? null,
            permission_ids: activePermissionIds.value,
        }
        const res = await api.put<{ data: Role } | Role>(`/roles/${selectedRole.value.id}`, payload)
        const updated = (res as any).data ?? (res as Role)
        const idx = roles.value.findIndex(r => r.id === selectedRole.value?.id)
        if (idx !== -1) roles.value[idx] = updated
        selectRole(updated)
        toast.success('Policy saved', `${updated.permissions?.length ?? 0} permissions on "${updated.name}".`)
    } catch (err: any) {
        toast.error('Save failed', err?.data?.message || 'The server rejected the policy update.')
    } finally {
        busy.value = false
    }
}

const confirmDeleteRole = () => {
    if (!selectedRole.value || selectedRole.value.slug === 'admin') return
    deleteTarget.value = selectedRole.value
}

const deleteRoleConfirmed = async () => {
    if (!deleteTarget.value || !canWrite.value) return
    busy.value = true
    try {
        await api.delete(`/roles/${deleteTarget.value.id}`)
        const removedId = deleteTarget.value.id
        roles.value = roles.value.filter(r => r.id !== removedId)
        if (selectedRole.value?.id === removedId) {
            if (roles.value.length) selectRole(roles.value[0]!)
            else selectedRole.value = null
        }
        deleteTarget.value = null
        toast.success('Role removed')
    } catch (err: any) {
        toast.error('Delete failed', err?.data?.message)
    } finally {
        busy.value = false
    }
}

onMounted(async () => {
    await Promise.all([loadRoles(), loadPermissions()])
})
</script>

<style scoped>
/* Vertical rail items (desktop). Mirrors the HRM Settings shell. */
.tab-rail-item {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    width: 100%;
    padding: 0.625rem 0.75rem;
    border-radius: 0.625rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
    border: 1px solid transparent;
}
.tab-rail-item:hover { color: var(--text-heading); background: var(--bg-muted); }
.tab-rail-item-active {
    color: var(--color-primary);
    background: var(--color-primary-subtle);
    border-color: rgba(var(--color-primary-rgb), 0.18);
}
.tab-rail-icon { font-size: 1rem; flex-shrink: 0; }

/* Mobile horizontal pill strip (visible below lg). */
.tab-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.875rem;
    border-radius: 0.625rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    white-space: nowrap;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}
.tab-pill:hover { color: var(--text-heading); background: var(--bg-muted); }
.tab-pill-active { color: var(--color-primary); background: var(--color-primary-subtle); }

.role-card {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    width: 100%;
    text-align: left;
    padding: 0.625rem 0.75rem;
    border-radius: 0.75rem;
    border: 1px solid var(--border-color);
    background: color-mix(in srgb, var(--bg-muted) 60%, transparent);
    cursor: pointer;
    transition: background 0.15s ease, border-color 0.15s ease;
}
.role-card:hover { border-color: rgb(var(--color-primary-rgb) / 0.4); }
.role-card--active {
    background: rgb(var(--color-primary-rgb) / 0.08);
    border-color: rgb(var(--color-primary-rgb) / 0.5);
}

.chip-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 4px 10px;
    border-radius: 9999px;
    border: 1px solid var(--border-color);
    background: var(--bg-card);
    color: var(--text-body);
    font-size: 10px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
}
.chip-btn:hover:not(:disabled) {
    background: rgb(var(--color-primary-rgb) / 0.08);
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.4);
}
.chip-btn:disabled { opacity: 0.45; cursor: not-allowed; }

.perm-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    padding: 0.4rem 0.5rem;
    border-radius: 0.5rem;
    transition: background 0.15s ease;
}
.perm-row:hover { background: var(--bg-card); }
.perm-row--on { background: rgb(var(--color-primary-rgb) / 0.05); }

.form-label {
    display: block;
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-muted);
    margin-bottom: 0.375rem;
}
</style>
