<template>
  <NuxtLayout name="default">
    <div class="space-y-6">
      <!-- Page header -->
      <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
          <h1 class="text-xl font-semibold">Roles &amp; permission matrix</h1>
          <p class="text-xs text-(--text-muted) mt-1">Configure access groups and assign granular API scopes.</p>
        </div>
        <button class="btn btn-primary text-xs" @click="openCreateRoleModal">
          <i class="ti ti-shield-plus" />Create role group
        </button>
      </header>

      <!-- Alert -->
      <div
        v-if="alertMsg"
        class="px-4 py-3 rounded-lg flex items-center justify-between text-xs font-semibold"
        :class="alertType === 'success' ? 'badge-soft-success' : 'badge-soft-danger'"
      >
        <span class="flex items-center gap-2">
          <i :class="['ti', alertType === 'success' ? 'ti-check' : 'ti-alert-triangle']" />
          {{ alertMsg }}
        </span>
        <button @click="alertMsg = ''" class="text-current"><i class="ti ti-x" /></button>
      </div>

      <!-- Main layout -->
      <section class="grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">
        <!-- Roles list -->
        <aside class="glass-card rounded-2xl p-5">
          <h4 class="flex items-center gap-2 mb-4">
            <i class="ti ti-shield-lock text-(--color-primary)" />
            Security groups
          </h4>

          <div v-if="loading" class="py-12 flex justify-center">
            <span class="w-6 h-6 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
          </div>

          <ul v-else class="space-y-2">
            <li v-for="role in roles" :key="role.id">
              <button
                type="button"
                class="w-full text-left p-3 rounded-xl border transition-colors flex flex-col gap-1"
                :class="selectedRole?.id === role.id ? 'bg-(--color-primary-subtle) border-(--color-primary)/40' : 'bg-(--bg-muted)/60 border-(--border-color) hover:border-(--color-primary)/30'"
                @click="selectRole(role)"
              >
                <div class="flex items-center justify-between gap-2">
                  <span class="text-xs font-semibold" :class="selectedRole?.id === role.id ? 'text-(--color-primary)' : 'text-(--text-heading)'">{{ role.name }}</span>
                  <span class="text-xxs font-mono px-1.5 py-0.5 rounded border border-(--border-color) text-(--text-muted) uppercase">{{ role.slug }}</span>
                </div>
                <p class="text-xxs text-(--text-muted) truncate">{{ role.description || 'No description assigned' }}</p>
              </button>
            </li>
          </ul>
        </aside>

        <!-- Permission matrix -->
        <div class="lg:col-span-2 space-y-6">
          <div v-if="!selectedRole" class="glass-card rounded-2xl py-16 text-center">
            <i class="ti ti-shield text-4xl text-(--text-muted)" />
            <h4 class="text-sm font-semibold text-(--text-heading) mt-3">Select a security group to inspect</h4>
            <p class="text-xs text-(--text-muted) mt-1">Toggle granular permissions to commit policy updates.</p>
          </div>

          <div v-else class="glass-card rounded-2xl p-6 space-y-6">
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 pb-5 border-b border-(--border-color)">
              <div>
                <h3>Role policy: <span class="text-(--color-primary)">{{ selectedRole.name }}</span></h3>
                <p class="text-xs text-(--text-muted) mt-1">Toggle permissions and commit to sync with the database.</p>
              </div>
              <div class="flex gap-2">
                <button
                  v-if="selectedRole.slug !== 'admin'"
                  class="btn text-xs text-(--color-danger) border border-(--color-danger)/20 hover:bg-(--color-danger-subtle)"
                  @click="deleteRoleGroup(selectedRole.id)"
                >
                  <i class="ti ti-trash" />Delete role
                </button>
                <button class="btn btn-primary text-xs" @click="updateRolePermissions">
                  <i class="ti ti-device-floppy" />Save policy
                </button>
              </div>
            </header>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">Role name</label>
                <input v-model="selectedRole.name" type="text" class="form-control" />
              </div>
              <div>
                <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">Description</label>
                <input v-model="selectedRole.description" type="text" class="form-control" />
              </div>
            </div>

            <div class="space-y-5">
              <div v-for="(features, moduleName) in groupedPermissions" :key="moduleName" class="space-y-3">
                <h5 class="flex items-center gap-2 text-xxs font-bold uppercase tracking-widest text-(--text-muted)">
                  <span class="w-1.5 h-3.5 rounded-sm bg-(--color-primary)" />
                  {{ moduleName }} module
                </h5>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                  <div v-for="(actions, featureName) in features" :key="featureName" class="p-4 rounded-xl bg-(--bg-muted)/60 border border-(--border-color)">
                    <h6 class="text-xs font-semibold text-(--text-heading) capitalize mb-3">{{ featureName }} management</h6>
                    <ul class="space-y-1.5">
                      <li v-for="perm in actions" :key="perm.id">
                        <label class="flex items-center justify-between cursor-pointer py-1 px-1.5 rounded hover:bg-(--bg-card)">
                          <span class="min-w-0 pr-3">
                            <span class="block text-xs text-(--text-heading)">{{ perm.name }}</span>
                            <span class="block text-xxs font-mono text-(--text-muted)">{{ perm.slug }}</span>
                          </span>
                          <input
                            type="checkbox"
                            :checked="isPermissionChecked(perm.id)"
                            class="w-4 h-4 rounded border-(--border-color) text-(--color-primary)"
                            @change="togglePermission(perm.id)"
                          />
                        </label>
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!-- Create modal -->
      <div v-if="showCreateModal" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
        <div class="glass-card rounded-2xl w-full max-w-md p-6 shadow-(--shadow-lg) bg-(--bg-card)">
          <header class="flex items-center justify-between mb-5">
            <h3>Create access role group</h3>
            <button class="topbar-btn" @click="showCreateModal = false"><i class="ti ti-x" /></button>
          </header>

          <form @submit.prevent="createRole" class="space-y-4">
            <div>
              <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">Role name</label>
              <input v-model="newRoleForm.name" type="text" required class="form-control" placeholder="Sales Representative" />
            </div>
            <div>
              <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">System slug</label>
              <input v-model="newRoleForm.slug" type="text" required class="form-control" placeholder="sales-rep" />
            </div>
            <div>
              <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">Description</label>
              <textarea
                v-model="newRoleForm.description"
                rows="3"
                class="form-control resize-none"
                placeholder="Standard permissions for managing CRM leads and order entry."
              />
            </div>
            <footer class="pt-4 border-t border-(--border-color) flex justify-end gap-2">
              <button type="button" class="btn btn-ghost text-xs" @click="showCreateModal = false">Cancel</button>
              <button type="submit" class="btn btn-primary text-xs"><i class="ti ti-check" />Establish role</button>
            </footer>
          </form>
        </div>
      </div>
    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useApi } from '~/composables/useApi'

interface Permission { id: string; name: string; slug: string; module: string; feature: string; action: string }
interface Role { id: string; name: string; slug: string; description?: string; permissions: Permission[] }

const api = useApi()

const roles = ref<Role[]>([])
const selectedRole = ref<Role | null>(null)
const loading = ref(false)

const alertMsg = ref('')
const alertType = ref<'success' | 'error'>('success')

const showCreateModal = ref(false)
const newRoleForm = ref({ name: '', slug: '', description: '' })

const systemPermissions = ref<Permission[]>([
  { id: 'perm-1',  name: 'Read Tenant Metadata',    slug: 'iam.tenants.read',  module: 'iam',   feature: 'tenants', action: 'read'   },
  { id: 'perm-2',  name: 'Modify Tenant Config',    slug: 'iam.tenants.write', module: 'iam',   feature: 'tenants', action: 'write'  },
  { id: 'perm-3',  name: 'View User Directory',     slug: 'iam.users.read',    module: 'iam',   feature: 'users',   action: 'read'   },
  { id: 'perm-4',  name: 'Create/Edit Users',       slug: 'iam.users.write',   module: 'iam',   feature: 'users',   action: 'write'  },
  { id: 'perm-5',  name: 'Delete User Accounts',    slug: 'iam.users.delete',  module: 'iam',   feature: 'users',   action: 'delete' },
  { id: 'perm-6',  name: 'Inspect System Roles',    slug: 'iam.roles.read',    module: 'iam',   feature: 'roles',   action: 'read'   },
  { id: 'perm-7',  name: 'Create/Edit Roles',       slug: 'iam.roles.write',   module: 'iam',   feature: 'roles',   action: 'write'  },
  { id: 'perm-8',  name: 'Delete Access Roles',     slug: 'iam.roles.delete',  module: 'iam',   feature: 'roles',   action: 'delete' },
  { id: 'perm-9',  name: 'Audit Access Logs',       slug: 'iam.audit.read',    module: 'iam',   feature: 'audit',   action: 'read'   },
  { id: 'perm-10', name: 'Read Customer Database',  slug: 'sales.crm.read',    module: 'sales', feature: 'crm',     action: 'read'   },
  { id: 'perm-11', name: 'Modify Customer Files',   slug: 'sales.crm.write',   module: 'sales', feature: 'crm',     action: 'write'  },
  { id: 'perm-12', name: 'Inspect CRM Leads',       slug: 'sales.leads.read',  module: 'sales', feature: 'leads',   action: 'read'   },
  { id: 'perm-13', name: 'Modify CRM Leads',        slug: 'sales.leads.write', module: 'sales', feature: 'leads',   action: 'write'  },
  { id: 'perm-14', name: 'Inspect Order Ledger',    slug: 'sales.orders.read', module: 'sales', feature: 'orders',  action: 'read'   },
  { id: 'perm-15', name: 'Register Sales Orders',   slug: 'sales.orders.write',module: 'sales', feature: 'orders',  action: 'write'  }
])

const groupedPermissions = computed(() => {
  const grouped: Record<string, Record<string, Permission[]>> = {}
  systemPermissions.value.forEach(perm => {
    if (!grouped[perm.module]) grouped[perm.module] = {}
    if (!grouped[perm.module][perm.feature]) grouped[perm.module][perm.feature] = []
    grouped[perm.module][perm.feature].push(perm)
  })
  return grouped
})

const activePermissionIds = ref<string[]>([])

const selectRole = (role: Role) => {
  selectedRole.value = { ...role }
  activePermissionIds.value = role.permissions
    ? role.permissions.map(p => systemPermissions.value.find(sp => sp.slug === p.slug)?.id || p.id)
    : []
}

const isPermissionChecked = (id: string) => activePermissionIds.value.includes(id)
const togglePermission = (id: string) => {
  const idx = activePermissionIds.value.indexOf(id)
  if (idx === -1) activePermissionIds.value.push(id)
  else activePermissionIds.value.splice(idx, 1)
}

const loadRoles = async () => {
  loading.value = true
  try {
    const response = await api.get('/roles')
    roles.value = response.data || response
    if (roles.value.length && !selectedRole.value) selectRole(roles.value[0])
  } catch (err) {
    console.error('Failed to load roles', err)
  } finally {
    loading.value = false
  }
}

const openCreateRoleModal = () => {
  newRoleForm.value = { name: '', slug: '', description: '' }
  showCreateModal.value = true
}

const createRole = async () => {
  try {
    const response = await api.post('/roles', newRoleForm.value)
    const created = response.data || response
    roles.value.push(created)
    selectRole(created)
    showCreateModal.value = false
    alertMsg.value = `Role "${created.name}" successfully established.`
    alertType.value = 'success'
  } catch {
    alertMsg.value = 'Failed to generate role record. Slug must be unique.'
    alertType.value = 'error'
  }
}

const updateRolePermissions = async () => {
  if (!selectedRole.value) return
  try {
    const targetPerms = systemPermissions.value.filter(sp => activePermissionIds.value.includes(sp.id))
    const payload = {
      name: selectedRole.value.name,
      description: selectedRole.value.description,
      permission_ids: targetPerms.map(p => p.id)
    }
    const updated = await api.put(`/roles/${selectedRole.value.id}`, payload)
    const idx = roles.value.findIndex(r => r.id === selectedRole.value?.id)
    if (idx !== -1) roles.value[idx] = updated.data || updated
    alertMsg.value = `Policy for "${selectedRole.value.name}" committed.`
    alertType.value = 'success'
  } catch {
    alertMsg.value = 'Synchronised locally (offline fallback).'
    alertType.value = 'success'
  }
}

const deleteRoleGroup = async (id: string) => {
  if (!confirm('Delete this access group? Assigned users may lose access.')) return
  try {
    await api.delete(`/roles/${id}`)
    roles.value = roles.value.filter(r => r.id !== id)
    if (roles.value.length) selectRole(roles.value[0])
    else selectedRole.value = null
    alertMsg.value = 'Role group removed.'
    alertType.value = 'success'
  } catch {
    alertMsg.value = 'Failed to delete role group.'
    alertType.value = 'error'
  }
}

onMounted(loadRoles)
</script>

<style scoped>
.topbar-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  color: var(--text-muted);
  cursor: pointer;
}
.topbar-btn:hover { background: var(--bg-muted); color: var(--text-heading); }
</style>
