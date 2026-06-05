<template>
    <NuxtLayout name="default">
        <div class="space-y-6 pb-24">
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
            </header>

            <!-- ============================ Sidebar tabs + content ====================== -->
            <div class="flex flex-col lg:flex-row gap-4">
                <!-- Vertical rail — desktop -->
                <aside class="lg:w-60 lg:shrink-0">
                    <nav class="lg:hidden glass-card rounded-xl px-2 py-1.5 flex items-center gap-1 overflow-x-auto">
                        <button v-for="t in tabs" :key="t.key" type="button" class="tab-pill"
                            :class="{ 'tab-pill-active': activeTab === t.key }" @click="setTab(t.key)">
                            <i :class="['ti', t.icon]" />
                            <span>{{ t.label }}</span>
                        </button>
                    </nav>
                    <nav class="hidden lg:block glass-card rounded-2xl p-2 sticky top-4 space-y-1">
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
                        <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 items-start">
                            <!-- Roles rail -->
                            <aside class="xl:col-span-4 glass-card rounded-2xl p-4 space-y-3">
                                <header class="flex items-center justify-between px-1">
                                    <h4 class="flex items-center gap-2">
                                        <i class="ti ti-shield-lock text-(--color-primary)" />
                                        <span class="text-sm font-semibold text-(--text-heading)">Security groups</span>
                                        <span class="text-xxs text-(--text-muted) font-mono">{{ roles.length }}</span>
                                    </h4>
                                    <button v-if="canWrite" type="button" class="chip-btn"
                                        title="Create role group" @click="openCreateRoleModal">
                                        <i class="ti ti-plus" />
                                    </button>
                                </header>

                                <div v-if="roles.length >= 8" class="relative">
                                    <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-xs" />
                                    <input v-model.lazy="roleSearch" type="search" placeholder="Find a role..."
                                        class="form-control text-xs pl-8 rounded-full px-4" />
                                </div>

                                <div v-if="loadingRoles" class="py-12 flex justify-center">
                                    <span class="w-6 h-6 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                                </div>

                                <div v-else-if="filteredRoles.length === 0" class="py-12 text-center">
                                    <i class="ti ti-shield-off text-2xl text-(--text-muted)" />
                                    <p class="text-xs text-(--text-muted) mt-2">No matching roles.</p>
                                </div>

                                <ul v-else class="space-y-1.5">
                                    <li v-for="role in filteredRoles" :key="role.id">
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
                                            <p class="text-xxs text-(--text-muted) truncate mt-1">
                                                {{ role.description || 'No description' }}
                                            </p>
                                            <div class="flex items-center gap-2 mt-2 text-xxs font-mono">
                                                <span class="role-meta-chip role-meta-chip--primary">
                                                    <i class="ti ti-key text-[10px]" />
                                                    {{ role.permissions?.length ?? 0 }}
                                                </span>
                                                <span v-if="role.usersCount != null" class="role-meta-chip">
                                                    <i class="ti ti-users text-[10px]" />
                                                    {{ role.usersCount }}
                                                </span>
                                                <span v-if="role.slug === 'admin'"
                                                    class="role-meta-chip role-meta-chip--warning ml-auto">
                                                    <i class="ti ti-star text-[10px]" /> super
                                                </span>
                                            </div>
                                        </button>
                                    </li>
                                </ul>
                            </aside>

                            <!-- Matrix pane -->
                            <div class="xl:col-span-8 space-y-4">
                                <div v-if="!selectedRole" class="glass-card rounded-2xl py-24 text-center">
                                    <i class="ti ti-shield text-4xl text-(--text-muted)" />
                                    <h4 class="text-sm font-semibold text-(--text-heading) mt-3">
                                        Pick a role to inspect
                                    </h4>
                                    <p class="text-xs text-(--text-muted) mt-1 max-w-xs mx-auto">
                                        Each role bundles a set of API scopes. Toggle them as a feature × action matrix.
                                    </p>
                                </div>

                                <template v-else>
                                    <!-- Role meta + filter strip -->
                                    <div class="glass-card rounded-2xl p-5 space-y-4">
                                        <header class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                                            <div class="min-w-0 space-y-1">
                                                <h3 class="text-sm font-semibold text-(--text-heading) flex items-center gap-2 flex-wrap max-sm:justify-center">
                                                    <span class="text-xxs uppercase tracking-widest text-(--text-muted) font-bold">Policy</span>
                                                    <span class="text-(--color-primary)">{{ selectedRole.name }}</span>
                                                    <span v-if="selectedRole.slug === 'admin'"
                                                        class="badge-soft-warning text-xxs px-1.5 py-0.5 rounded">
                                                        <i class="ti ti-star text-[10px]" /> super-admin
                                                    </span>
                                                </h3>
                                                <p class="text-xs text-(--text-muted)">
                                                    <span v-if="selectedRole.slug === 'admin'">
                                                        <i class="ti ti-info-circle" /> Admins bypass every permission check via `Gate::before` — toggles below are read-only.
                                                    </span>
                                                    <span v-else>
                                                        <span class="font-mono">{{ activePermissionIds.length }}</span> of
                                                        <span class="font-mono">{{ flatPermissions.length }}</span>
                                                        permissions enabled
                                                        <span v-if="selectedRole.usersCount != null" class="ml-2">
                                                            · {{ selectedRole.usersCount }} user{{ selectedRole.usersCount === 1 ? '' : 's' }}
                                                        </span>
                                                        <span v-if="selectedRole.updatedAt" class="ml-2">
                                                            · updated {{ relativeTime(selectedRole.updatedAt) }}
                                                        </span>
                                                    </span>
                                                </p>
                                            </div>
                                            <button v-if="canWrite && selectedRole.slug !== 'admin'" type="button"
                                                class="btn text-xs text-(--color-danger) border border-(--color-danger)/20 hover:bg-(--color-danger-subtle) shrink-0"
                                                :disabled="busy" @click="confirmDeleteRole">
                                                <i class="ti ti-trash" /> Delete role
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
                                                    placeholder="Filter by name, slug, module..."
                                                    class="form-control pl-9 text-xs rounded-full px-4" />
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
                                        <button type="button" class="btn btn-ghost text-xs mt-3" @click="search = ''">
                                            Clear filter
                                        </button>
                                    </div>

                                    <article v-for="mod in visibleModules" :key="mod.key" class="glass-card rounded-2xl overflow-hidden">
                                        <header class="flex items-center justify-between p-4 border-b border-(--border-color)/60">
                                            <button type="button" class="flex items-center gap-2 text-left min-w-0"
                                                @click="toggleCollapsed(mod.key)">
                                                <i :class="['ti text-(--text-muted) text-sm',
                                                    collapsed[mod.key] ? 'ti-chevron-right' : 'ti-chevron-down']" />
                                                <h5 class="text-xs font-bold uppercase tracking-widest text-(--text-heading) truncate">
                                                    {{ mod.label }}
                                                </h5>
                                                <span class="text-xxs font-mono text-(--text-muted) shrink-0">
                                                    {{ mod.enabledCount }} / {{ mod.totalCount }}
                                                </span>
                                            </button>
                                            <div class="flex items-center gap-1.5 shrink-0">
                                                <button type="button" class="chip-btn"
                                                    :disabled="!matrixEditable || mod.enabledCount === mod.totalCount"
                                                    @click="enableModule(mod)">
                                                    Enable all
                                                </button>
                                                <button type="button" class="chip-btn"
                                                    :disabled="!matrixEditable || mod.enabledCount === 0"
                                                    @click="clearModule(mod)">
                                                    Clear all
                                                </button>
                                            </div>
                                        </header>

                                        <div v-if="!collapsed[mod.key]" class="overflow-x-auto">
                                            <table class="matrix">
                                                <thead>
                                                    <tr>
                                                        <th class="matrix-row-header">Feature</th>
                                                        <th v-for="action in mod.actions" :key="action.key"
                                                            class="matrix-col-header"
                                                            :title="`Toggle '${action.label}' across all features in ${mod.label}`">
                                                            <button type="button" class="matrix-col-btn"
                                                                :disabled="!matrixEditable || action.totalCount === 0"
                                                                @click="toggleColumn(mod, action)">
                                                                <span>{{ action.label }}</span>
                                                                <span class="text-xxs font-mono text-(--text-muted)">
                                                                    {{ action.enabledCount }}/{{ action.totalCount }}
                                                                </span>
                                                            </button>
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <tr v-for="feat in mod.features" :key="feat.key">
                                                        <th class="matrix-feature-name">
                                                            <button type="button" class="matrix-feature-btn"
                                                                :disabled="!matrixEditable"
                                                                :title="`Toggle every action on '${feat.label}'`"
                                                                @click="toggleRow(feat)">
                                                                <span class="truncate">{{ feat.label }}</span>
                                                                <span class="text-xxs font-mono text-(--text-muted)">
                                                                    {{ feat.enabledCount }}/{{ feat.permissions.length }}
                                                                </span>
                                                            </button>
                                                        </th>
                                                        <td v-for="action in mod.actions" :key="`${feat.key}.${action.key}`"
                                                            class="matrix-cell">
                                                            <template v-if="getCellPermission(feat, action)">
                                                                <label class="matrix-checkbox-wrap"
                                                                    :title="getCellPermission(feat, action)!.slug">
                                                                    <input type="checkbox"
                                                                        :checked="isPermissionChecked(getCellPermission(feat, action)!.id)"
                                                                        :disabled="!matrixEditable"
                                                                        @change="togglePermission(getCellPermission(feat, action)!.id)" />
                                                                </label>
                                                            </template>
                                                            <span v-else class="matrix-empty" title="No permission slug for this combination">—</span>
                                                        </td>
                                                    </tr>
                                                </tbody>
                                            </table>
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
                                        <strong>Role groups</strong> tab to attach them.
                                    </p>
                                </div>
                                <div class="text-xxs font-mono text-(--text-muted)">
                                    {{ flatPermissions.length }} permissions · {{ catalogueModules.length }} modules
                                </div>
                            </header>

                            <div class="relative">
                                <i class="ti ti-search absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm" />
                                <input v-model.lazy="catalogueSearch" type="search"
                                    placeholder="Search by name, slug, or module..."
                                    class="form-control pl-9 text-xs rounded-full px-4" />
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
                            <header class="flex items-center justify-between mb-4">
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
        </div>

        <!-- ============================ Sticky action bar ====================== -->
        <Transition name="action-bar">
            <div v-if="activeTab === 'roles' && selectedRole && selectedRole.slug !== 'admin' && isDirty"
                class="action-bar">
                <div class="flex items-center gap-3 text-xs">
                    <span class="font-semibold text-(--text-heading)">
                        Unsaved changes to <span class="text-(--color-primary)">{{ selectedRole.name }}</span>
                    </span>
                    <span v-if="diff.added > 0" class="diff-chip diff-chip--add">+{{ diff.added }}</span>
                    <span v-if="diff.removed > 0" class="diff-chip diff-chip--remove">−{{ diff.removed }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" class="btn btn-ghost text-xs" :disabled="busy" @click="resetSelected">
                        <i class="ti ti-restore" /> Revert
                    </button>
                    <button type="button" class="btn btn-primary text-xs"
                        :disabled="busy || !canWrite" @click="updateRolePermissions">
                        <i :class="['ti', busy ? 'ti-loader-2 animate-spin' : 'ti-device-floppy']" />
                        {{ busy ? 'Saving...' : 'Save changes' }}
                    </button>
                </div>
            </div>
        </Transition>

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
                                placeholder="e.g. Sales Representative" autofocus />
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
                            <label class="form-label">Clone from</label>
                            <select v-model="newRoleForm.cloneFromId" class="form-control text-xs">
                                <option :value="''">— Start blank (no permissions)</option>
                                <option v-for="r in cloneCandidates" :key="r.id" :value="r.id">
                                    {{ r.name }} ({{ r.permissions?.length ?? 0 }} permissions)
                                </option>
                            </select>
                            <p class="text-xxs text-(--text-muted) mt-1">
                                Optional. Copies the source role's permission set as the starting point.
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
                        <span v-if="deleteTarget.usersCount && deleteTarget.usersCount > 0">
                            <strong class="text-(--color-warning)">
                                {{ deleteTarget.usersCount }} user{{ deleteTarget.usersCount === 1 ? '' : 's' }}
                            </strong>
                            currently in this group will lose every permission attached here.
                        </span>
                        <span v-else>
                            No users currently hold this role.
                        </span>
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
    usersCount?: number
    updatedAt?: string
    permissions?: Permission[]
}
interface ActionColumn {
    key: string
    label: string
    enabledCount: number
    totalCount: number
}
interface FeatureRow {
    key: string
    label: string
    permissions: Permission[]
    /** Quick lookup: action key → permission row. */
    byAction: Record<string, Permission>
    enabledCount: number
}
interface ModuleGroup {
    key: string
    label: string
    features: FeatureRow[]
    actions: ActionColumn[]
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
const newRoleForm = reactive({ name: '', slug: '', description: '', cloneFromId: '' })

const search = ref('')
const catalogueSearch = ref('')
const roleSearch = ref('')
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
const friendlyActionLabel = (s: string) => s.replace(/[_.-]/g, ' ')

const matrixEditable = computed(() =>
    canWrite.value && !!selectedRole.value && selectedRole.value.slug !== 'admin'
)

const isDirty = computed(() => {
    if (!selectedRole.value) return false
    const a = [...activePermissionIds.value].sort().join(',')
    const b = [...initialPermissionIds.value].sort().join(',')
    return a !== b
})

const diff = computed(() => {
    const before = new Set(initialPermissionIds.value)
    const after = new Set(activePermissionIds.value)
    let added = 0
    let removed = 0
    for (const id of after) if (!before.has(id)) added++
    for (const id of before) if (!after.has(id)) removed++
    return { added, removed }
})

// Common-first ordering for action columns within a module.
const ACTION_RANK: Record<string, number> = {
    read: 0,
    write: 1,
    delete: 2,
}
const sortActions = (a: string, b: string) => {
    const ra = ACTION_RANK[a] ?? 10
    const rb = ACTION_RANK[b] ?? 10
    if (ra !== rb) return ra - rb
    return a.localeCompare(b)
}

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
        const actionSet = new Set<string>()
        for (const perms of byFeat.values()) {
            for (const p of perms) actionSet.add(p.action)
        }
        const actionKeys = Array.from(actionSet).sort(sortActions)

        const features: FeatureRow[] = []
        let modEnabled = 0
        let modTotal = 0
        for (const [featKey, perms] of byFeat) {
            const byAction: Record<string, Permission> = {}
            for (const p of perms) byAction[p.action] = p
            const enabled = perms.filter(p => activePermissionIds.value.includes(p.id)).length
            features.push({
                key: `${moduleKey}.${featKey}`,
                label: friendlyFeatureLabel(featKey),
                permissions: perms,
                byAction,
                enabledCount: enabled,
            })
            modEnabled += enabled
            modTotal += perms.length
        }
        features.sort((a, b) => a.label.localeCompare(b.label))

        const actions: ActionColumn[] = actionKeys.map((actKey) => {
            const colPerms = features
                .map(f => f.byAction[actKey])
                .filter((p): p is Permission => !!p)
            return {
                key: actKey,
                label: friendlyActionLabel(actKey),
                totalCount: colPerms.length,
                enabledCount: colPerms.filter(p => activePermissionIds.value.includes(p.id)).length,
            }
        })

        modules.push({
            key: moduleKey,
            label: friendlyModuleLabels[moduleKey] ?? moduleKey,
            features,
            actions,
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

const filteredRoles = computed(() => {
    const term = roleSearch.value.trim().toLowerCase()
    if (!term) return roles.value
    return roles.value.filter(r =>
        r.name.toLowerCase().includes(term) ||
        r.slug.toLowerCase().includes(term) ||
        (r.description ?? '').toLowerCase().includes(term)
    )
})

const cloneCandidates = computed(() =>
    roles.value.slice().sort((a, b) => a.name.localeCompare(b.name))
)

const isAllOn = computed(() => {
    const ids = filteredPermissions.value.map(p => p.id)
    return ids.length > 0 && ids.every(id => activePermissionIds.value.includes(id))
})
const isAllOff = computed(() => {
    const ids = filteredPermissions.value.map(p => p.id)
    return ids.length > 0 && ids.every(id => !activePermissionIds.value.includes(id))
})

const isPermissionChecked = (id: string) => activePermissionIds.value.includes(id)
const getCellPermission = (feat: FeatureRow, action: ActionColumn): Permission | undefined =>
    feat.byAction[action.key]

const setIdsState = (set: Set<string>) => {
    activePermissionIds.value = Array.from(set)
}

const togglePermission = (id: string) => {
    if (!matrixEditable.value) return
    const set = new Set(activePermissionIds.value)
    if (set.has(id)) set.delete(id)
    else set.add(id)
    setIdsState(set)
}

const toggleRow = (feat: FeatureRow) => {
    if (!matrixEditable.value) return
    const ids = feat.permissions.map(p => p.id)
    const allOn = ids.every(id => activePermissionIds.value.includes(id))
    const set = new Set(activePermissionIds.value)
    for (const id of ids) {
        if (allOn) set.delete(id)
        else set.add(id)
    }
    setIdsState(set)
}

const toggleColumn = (mod: ModuleGroup, action: ActionColumn) => {
    if (!matrixEditable.value) return
    const ids = mod.features
        .map(f => f.byAction[action.key])
        .filter((p): p is Permission => !!p)
        .map(p => p.id)
    if (ids.length === 0) return
    const allOn = ids.every(id => activePermissionIds.value.includes(id))
    const set = new Set(activePermissionIds.value)
    for (const id of ids) {
        if (allOn) set.delete(id)
        else set.add(id)
    }
    setIdsState(set)
}

const enableModule = (mod: ModuleGroup) => {
    if (!matrixEditable.value) return
    const set = new Set(activePermissionIds.value)
    for (const f of mod.features) for (const p of f.permissions) set.add(p.id)
    setIdsState(set)
}
const clearModule = (mod: ModuleGroup) => {
    if (!matrixEditable.value) return
    const drop = new Set<string>()
    for (const f of mod.features) for (const p of f.permissions) drop.add(p.id)
    activePermissionIds.value = activePermissionIds.value.filter(id => !drop.has(id))
}

const selectAllVisible = () => {
    if (!matrixEditable.value) return
    const set = new Set(activePermissionIds.value)
    for (const p of filteredPermissions.value) set.add(p.id)
    setIdsState(set)
}
const clearAllVisible = () => {
    if (!matrixEditable.value) return
    const drop = new Set(filteredPermissions.value.map(p => p.id))
    activePermissionIds.value = activePermissionIds.value.filter(id => !drop.has(id))
}

const toggleCollapsed = (key: string) => {
    collapsed[key] = !collapsed[key]
}

/**
 * Switch the selected role. `force: true` skips the dirty-state confirm —
 * use it for the initial auto-select on first load. Manual user clicks
 * never pass `force`, so unsaved permission changes can't be silently
 * dropped by an accidental click on another role.
 */
const selectRole = (role: Role, opts: { force?: boolean } = {}) => {
    if (!opts.force && isDirty.value && selectedRole.value && selectedRole.value.id !== role.id) {
        const ok = window.confirm(`Discard unsaved changes to "${selectedRole.value.name}"?`)
        if (!ok) return
    }
    selectedRole.value = { ...role }
    const ids = role.permissions?.map(p => p.id) ?? []
    activePermissionIds.value = [...ids]
    initialPermissionIds.value = [...ids]
    search.value = ''
}
const resetSelected = () => {
    activePermissionIds.value = [...initialPermissionIds.value]
}

const relativeTime = (iso: string | undefined) => {
    if (!iso) return ''
    const ms = Date.now() - new Date(iso).getTime()
    if (ms < 0) return 'just now'
    const min = Math.floor(ms / 60_000)
    if (min < 1) return 'just now'
    if (min < 60) return `${min}m ago`
    const hr = Math.floor(min / 60)
    if (hr < 24) return `${hr}h ago`
    const day = Math.floor(hr / 24)
    if (day < 30) return `${day}d ago`
    return new Date(iso).toLocaleDateString()
}

/**
 * Load both roles and permissions. Resilient against the auth store
 * hydrating after this component mounts — we let the backend gate the
 * call rather than pre-checking `canRead.value`, which returns false
 * until `authStore.user` lands and would otherwise leave the page empty.
 */
const loadRoles = async () => {
    if (loadingRoles.value) return
    loadingRoles.value = true
    try {
        const res = await api.get<{ data: Role[] }>('/roles?limit=200')
        roles.value = (res as any).data ?? (res as Role[])
        if (roles.value.length && !selectedRole.value) selectRole(roles.value[0]!, { force: true })
    } catch (err: any) {
        // Silently skip 401/403 — the watcher below will retry once
        // `canRead` flips true after auth hydrates. Surface anything else.
        if (err?.status !== 401 && err?.status !== 403) {
            toast.error('Failed to load roles', err?.data?.message)
        }
    } finally {
        loadingRoles.value = false
    }
}

const loadPermissions = async () => {
    if (loadingPermissions.value) return
    loadingPermissions.value = true
    try {
        const res = await api.get<{ data: Permission[] }>('/permissions')
        flatPermissions.value = (res as any).data ?? (res as Permission[])
    } catch (err: any) {
        if (err?.status !== 401 && err?.status !== 403) {
            toast.error('Failed to load permission catalogue', err?.data?.message)
        }
    } finally {
        loadingPermissions.value = false
    }
}

const openCreateRoleModal = () => {
    newRoleForm.name = ''
    newRoleForm.slug = ''
    newRoleForm.description = ''
    newRoleForm.cloneFromId = ''
    showCreateModal.value = true
}

const createRole = async () => {
    if (!canWrite.value) return
    busy.value = true
    try {
        const payload: Record<string, any> = {
            name: newRoleForm.name,
            slug: newRoleForm.slug,
            description: newRoleForm.description || null,
        }
        if (newRoleForm.cloneFromId) {
            const source = roles.value.find(r => r.id === newRoleForm.cloneFromId)
            if (source?.permissions?.length) {
                payload.permission_ids = source.permissions.map(p => p.id)
            }
        }
        const res = await api.post<{ data: Role }>('/roles', payload)
        const created = (res as any).data ?? (res as Role)
        roles.value.push(created)
        selectRole(created)
        showCreateModal.value = false
        toast.success('Role created', `"${created.name}" is ready.`)
    } catch (err: any) {
        toast.error('Create failed', err?.data?.message || 'Slug must be unique.')
    } finally {
        busy.value = false
    }
}

const updateRolePermissions = async () => {
    if (!selectedRole.value || !canWrite.value) return
    // Snapshot the target + diff before awaiting so a concurrent role
    // switch can't poison the response handler with mismatched state.
    const targetId = selectedRole.value.id
    const targetName = selectedRole.value.name
    const targetDescription = selectedRole.value.description ?? null
    const beforeIds = new Set(initialPermissionIds.value)
    const afterIds = new Set(activePermissionIds.value)
    let added = 0
    let removed = 0
    for (const id of afterIds) if (!beforeIds.has(id)) added++
    for (const id of beforeIds) if (!afterIds.has(id)) removed++

    busy.value = true
    try {
        const payload = {
            name: targetName,
            description: targetDescription,
            permission_ids: Array.from(afterIds),
        }
        const res = await api.put<{ data: Role }>(`/roles/${targetId}`, payload)
        const updated = (res as any).data ?? (res as Role)
        const idx = roles.value.findIndex(r => r.id === targetId)
        if (idx !== -1) roles.value[idx] = { ...roles.value[idx]!, ...updated }
        // Only re-sync the editor if the user is still on the role we saved.
        if (selectedRole.value?.id === targetId) {
            selectRole(updated, { force: true })
        }
        const summary = added + removed === 0
            ? 'No permission changes saved.'
            : `${added ? `+${added}` : ''}${added && removed ? ' / ' : ''}${removed ? `-${removed}` : ''} permissions.`
        toast.success('Policy saved', summary)
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

// Defensive retry: if the auth store hydrates AFTER this component mounts
// (cold reload, deep link), the first load may have been skipped by a
// 401/403. Re-fire as soon as the user object lands and the read perm
// becomes truthy.
watch(canRead, async (now) => {
    if (!now) return
    const tasks: Promise<unknown>[] = []
    if (roles.value.length === 0 && !loadingRoles.value) tasks.push(loadRoles())
    if (flatPermissions.value.length === 0 && !loadingPermissions.value) tasks.push(loadPermissions())
    if (tasks.length) await Promise.all(tasks)
})

// Guard against losing unsaved permission edits when the user navigates
// away (closes the tab, hits Back). Browsers ignore custom strings — the
// generic "Leave site?" prompt is what shows up.
if (import.meta.client) {
    window.addEventListener('beforeunload', (e) => {
        if (isDirty.value) {
            e.preventDefault()
            e.returnValue = ''
        }
    })
}
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

/* Role card in the rail */
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

.role-meta-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 7px;
    border-radius: 999px;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    color: var(--text-muted);
    font-size: 10px;
}
.role-meta-chip--primary {
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.3);
    background: rgb(var(--color-primary-rgb) / 0.08);
}
.role-meta-chip--warning {
    color: var(--color-warning);
    border-color: rgb(var(--color-warning-rgb) / 0.3);
    background: rgb(var(--color-warning-rgb) / 0.08);
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

.form-label {
    display: block;
    font-size: 0.625rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--text-muted);
    margin-bottom: 0.375rem;
}

/* ============================ Action matrix ============================ */
.matrix {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}
.matrix-row-header {
    text-align: left;
    padding: 10px 14px;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--text-muted);
    background: color-mix(in srgb, var(--bg-muted) 50%, transparent);
    border-bottom: 1px solid var(--border-color);
    white-space: nowrap;
}
.matrix-col-header {
    padding: 6px 10px;
    border-bottom: 1px solid var(--border-color);
    background: color-mix(in srgb, var(--bg-muted) 50%, transparent);
    text-align: center;
    vertical-align: middle;
    min-width: 96px;
}
.matrix-col-btn {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    gap: 2px;
    padding: 4px 8px;
    border-radius: 6px;
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-heading);
    width: 100%;
    transition: background 0.15s ease, color 0.15s ease;
}
.matrix-col-btn:hover:not(:disabled) { background: var(--bg-card); color: var(--color-primary); }
.matrix-col-btn:disabled { cursor: not-allowed; opacity: 0.7; }

.matrix-feature-name {
    text-align: left;
    padding: 0;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
    background: color-mix(in srgb, var(--bg-card) 60%, transparent);
}
.matrix-feature-btn {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    width: 100%;
    padding: 10px 14px;
    background: transparent;
    border: none;
    cursor: pointer;
    text-align: left;
    font-size: 12px;
    font-weight: 600;
    color: var(--text-heading);
    text-transform: capitalize;
    transition: background 0.15s ease, color 0.15s ease;
}
.matrix-feature-btn:hover:not(:disabled) { background: var(--bg-muted); color: var(--color-primary); }
.matrix-feature-btn:disabled { cursor: not-allowed; }

.matrix-cell {
    text-align: center;
    padding: 8px 10px;
    border-bottom: 1px solid var(--border-color);
}
.matrix-checkbox-wrap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.15s ease;
}
.matrix-checkbox-wrap:hover { background: rgb(var(--color-primary-rgb) / 0.08); }
.matrix-checkbox-wrap input[type="checkbox"] {
    width: 16px;
    height: 16px;
    accent-color: var(--color-primary);
    cursor: pointer;
}
.matrix-checkbox-wrap input[type="checkbox"]:disabled { cursor: not-allowed; opacity: 0.6; }
.matrix-empty {
    display: inline-block;
    color: var(--text-muted);
    opacity: 0.4;
    font-size: 14px;
}

tbody tr:hover .matrix-feature-name { background: var(--bg-muted); }
tbody tr:last-child .matrix-cell,
tbody tr:last-child .matrix-feature-name { border-bottom: none; }

/* ============================ Sticky action bar ============================ */
.action-bar {
    position: fixed;
    left: 50%;
    bottom: 1.25rem;
    transform: translateX(-50%);
    z-index: 40;
    display: flex;
    align-items: center;
    gap: 1.25rem;
    padding: 0.75rem 1.25rem;
    border-radius: 14px;
    background: color-mix(in srgb, var(--bg-card) 95%, transparent);
    backdrop-filter: blur(10px);
    border: 1px solid var(--border-color);
    box-shadow: 0 12px 32px -16px rgb(0 0 0 / 0.35);
}
.diff-chip {
    display: inline-flex;
    align-items: center;
    padding: 2px 8px;
    border-radius: 999px;
    font-size: 11px;
    font-weight: 700;
    font-family: ui-monospace, SFMono-Regular, monospace;
}
.diff-chip--add {
    color: var(--color-success);
    background: rgb(var(--color-success-rgb) / 0.12);
    border: 1px solid rgb(var(--color-success-rgb) / 0.35);
}
.diff-chip--remove {
    color: var(--color-danger);
    background: rgb(var(--color-danger-rgb) / 0.12);
    border: 1px solid rgb(var(--color-danger-rgb) / 0.35);
}

.action-bar-enter-active,
.action-bar-leave-active {
    transition: opacity 0.2s ease, transform 0.2s ease;
}
.action-bar-enter-from,
.action-bar-leave-to {
    opacity: 0;
    transform: translate(-50%, 20px);
}
</style>
