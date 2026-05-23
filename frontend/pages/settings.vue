<template>
  <NuxtLayout name="default">
    <div class="space-y-6">
      <!-- Page header -->
      <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
        <div>
          <h1 class="text-xl font-semibold">Configuration &amp; Tenant Settings</h1>
          <p class="text-xs text-(--text-muted) mt-1">
            Branding, locale, notification, and security defaults for
            <span class="text-(--color-primary) font-semibold">{{ tenantStore.activeName }}</span>.
          </p>
        </div>
        <div class="flex items-center gap-2">
          <button class="btn text-xs" :class="dirty ? 'text-(--text-body) border border-(--border-color) hover:bg-(--bg-muted)' : 'text-(--text-muted) cursor-not-allowed'"
            :disabled="!dirty || saving" @click="reset">
            <i class="ti ti-restore" /> Revert
          </button>
          <button class="btn btn-primary text-xs" :disabled="!dirty || saving" @click="save">
            <i :class="['ti', saving ? 'ti-loader-2 animate-spin' : 'ti-device-floppy']" />
            {{ saving ? 'Saving...' : 'Save changes' }}
          </button>
        </div>
      </header>

      <!-- Alert -->
      <div v-if="alert.msg"
        class="px-4 py-3 rounded-lg flex items-center justify-between text-xs font-semibold"
        :class="alert.type === 'success' ? 'badge-soft-success' : 'badge-soft-danger'">
        <span class="flex items-center gap-2">
          <i :class="['ti', alert.type === 'success' ? 'ti-check' : 'ti-alert-triangle']" />
          {{ alert.msg }}
        </span>
        <button class="text-current" @click="alert.msg = ''"><i class="ti ti-x" /></button>
      </div>

      <!-- Tabs -->
      <nav class="flex flex-wrap gap-2 border-b border-(--border-color)">
        <button v-for="tab in tabs" :key="tab.key" type="button"
          class="px-4 py-2 text-xs font-semibold rounded-t-md border-b-2 transition-colors flex items-center gap-2"
          :class="activeTab === tab.key
            ? 'text-(--color-primary) border-(--color-primary)'
            : 'text-(--text-muted) border-transparent hover:text-(--text-heading)'"
          @click="activeTab = tab.key">
          <i :class="['ti', tab.icon]" />
          {{ tab.label }}
        </button>
      </nav>

      <!-- Loading -->
      <div v-if="loading" class="py-16 flex justify-center">
        <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
      </div>

      <!-- Branding -->
      <section v-else-if="activeTab === 'branding'" class="glass-card rounded-2xl p-6 space-y-6">
        <header class="flex items-center gap-2 pb-3 border-b border-(--border-color)">
          <i class="ti ti-palette text-(--color-primary)" />
          <h3 class="text-sm font-semibold">Branding</h3>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-2">
              Primary accent color
            </label>
            <div class="flex flex-wrap gap-2">
              <button v-for="swatch in accentSwatches" :key="swatch.rgb" type="button"
                class="w-9 h-9 rounded-full border-2 transition-transform hover:scale-110"
                :class="draft['branding.primary_color'] === swatch.rgb
                  ? 'border-(--text-heading)' : 'border-transparent'"
                :style="{ background: `rgb(${swatch.rgb})` }" :title="swatch.label"
                @click="setBranding('primary_color', swatch.rgb)" />
            </div>
            <p class="text-xxs text-(--text-muted) mt-2 font-mono">
              {{ draft['branding.primary_color'] || '—' }}
            </p>
          </div>

          <div>
            <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-2">
              Default theme mode
            </label>
            <div class="grid grid-cols-3 gap-2">
              <button v-for="mode in ['light', 'dark', 'system']" :key="mode" type="button"
                class="rounded-lg border px-2 py-2.5 text-xxs font-semibold flex flex-col items-center gap-1 transition-all"
                :class="draft['branding.theme_mode'] === mode
                  ? 'border-(--color-primary) bg-(--color-primary-subtle) text-(--color-primary)'
                  : 'border-(--border-color) text-(--text-body) hover:border-(--color-primary)/40'"
                @click="draft['branding.theme_mode'] = mode">
                {{ mode }}
              </button>
            </div>
          </div>

          <div class="md:col-span-2">
            <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
              Logo URL
            </label>
            <input v-model="draft['branding.logo_url']" type="url" placeholder="https://cdn.example.com/logo.svg"
              class="form-control" />
            <p class="text-xxs text-(--text-muted) mt-1">Shown on the login screen and sidebar header.</p>
          </div>
        </div>
      </section>

      <!-- Locale -->
      <section v-else-if="activeTab === 'locale'" class="glass-card rounded-2xl p-6 space-y-6">
        <header class="flex items-center gap-2 pb-3 border-b border-(--border-color)">
          <i class="ti ti-world text-(--color-primary)" />
          <h3 class="text-sm font-semibold">Locale &amp; formatting</h3>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
              Timezone
            </label>
            <input v-model="draft['locale.timezone']" type="text" placeholder="UTC" class="form-control" />
          </div>
          <div>
            <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
              Default language
            </label>
            <select v-model="draft['locale.language']" class="form-control">
              <option value="en">English</option>
              <option value="km">ខ្មែរ</option>
              <option value="th">ไทย</option>
              <option value="vi">Tiếng Việt</option>
            </select>
          </div>
          <div>
            <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
              Date format
            </label>
            <select v-model="draft['locale.date_format']" class="form-control">
              <option value="YYYY-MM-DD">2026-05-22 (ISO)</option>
              <option value="DD/MM/YYYY">22/05/2026</option>
              <option value="MM/DD/YYYY">05/22/2026</option>
              <option value="DD MMM YYYY">22 May 2026</option>
            </select>
          </div>
          <div>
            <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
              Default currency
            </label>
            <select v-model="draft['locale.currency']" class="form-control">
              <option value="USD">USD — US Dollar</option>
              <option value="EUR">EUR — Euro</option>
              <option value="KHR">KHR — Cambodian Riel</option>
              <option value="THB">THB — Thai Baht</option>
              <option value="VND">VND — Vietnamese Dong</option>
            </select>
          </div>
        </div>
      </section>

      <!-- Notifications -->
      <section v-else-if="activeTab === 'notifications'" class="glass-card rounded-2xl p-6 space-y-6">
        <header class="flex items-center gap-2 pb-3 border-b border-(--border-color)">
          <i class="ti ti-bell text-(--color-primary)" />
          <h3 class="text-sm font-semibold">Notifications</h3>
        </header>

        <label class="flex items-center justify-between p-4 rounded-xl border border-(--border-color) cursor-pointer">
          <div>
            <p class="text-sm font-semibold text-(--text-heading)">Send transactional emails</p>
            <p class="text-xxs text-(--text-muted) mt-0.5">Approvals, payslip releases, password resets.</p>
          </div>
          <input v-model="draft['notifications.email_enabled']" type="checkbox"
            class="w-5 h-5 rounded border-(--border-color)" />
        </label>

        <div>
          <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
            From email address
          </label>
          <input v-model="draft['notifications.from_address']" type="email"
            placeholder="noreply@your-tenant.com" class="form-control" />
        </div>
      </section>

      <!-- Security -->
      <section v-else-if="activeTab === 'security'" class="glass-card rounded-2xl p-6 space-y-6">
        <header class="flex items-center gap-2 pb-3 border-b border-(--border-color)">
          <i class="ti ti-shield-lock text-(--color-primary)" />
          <h3 class="text-sm font-semibold">Security defaults</h3>
        </header>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <div>
            <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
              Idle session timeout (minutes)
            </label>
            <input v-model.number="draft['security.session_timeout_minutes']" type="number" min="5" max="1440"
              class="form-control" />
          </div>
          <div>
            <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
              Minimum password length
            </label>
            <input v-model.number="draft['security.password_min_length']" type="number" min="6" max="64"
              class="form-control" />
          </div>
        </div>
      </section>

      <!-- Modules -->
      <section v-else-if="activeTab === 'modules'" class="space-y-4">
        <div class="glass-card rounded-2xl p-5 flex items-center justify-between">
          <div>
            <div class="flex items-center gap-2">
              <i class="ti ti-puzzle text-(--color-primary)" />
              <h3 class="text-sm font-semibold">Module Visibility</h3>
            </div>
            <p class="text-xs text-(--text-muted) mt-1">Enable or disable modules for this tenant. Core modules are always on.</p>
          </div>
          <button class="btn text-xs" :disabled="modulesLoading" @click="loadAllModules">
            <i :class="['ti', modulesLoading ? 'ti-loader-2 animate-spin' : 'ti-refresh']" />
            Refresh
          </button>
        </div>

        <div v-if="modulesLoading" class="py-12 flex justify-center">
          <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
        </div>

        <template v-else>
          <div v-for="group in moduleGroups" :key="group.key" class="glass-card rounded-2xl overflow-hidden">
            <div class="px-6 py-3 bg-(--bg-muted)/60 border-b border-(--border-color) flex items-center gap-2">
              <span class="text-xxs font-bold uppercase tracking-widest text-(--text-muted)">{{ group.label }}</span>
              <span class="text-xxs font-mono text-(--text-muted)">({{ group.items.length }})</span>
            </div>

            <div class="divide-y divide-(--border-color)">
              <div v-for="mod in group.items" :key="mod.id">
                <!-- Parent module row -->
                <div class="flex items-center gap-4 px-6 py-3.5 transition-colors"
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
                      <span class="text-xxs font-mono px-1.5 py-0.5 rounded border border-(--border-color) text-(--text-muted)">
                        {{ mod.prefix }}
                      </span>
                      <span v-if="mod.isCore" class="badge-soft-info text-xxs px-1.5 py-0.5 rounded flex items-center gap-1">
                        <i class="ti ti-lock text-[10px]" /> Core
                      </span>
                    </div>
                    <div v-if="mod.products?.length" class="flex flex-wrap gap-1 mt-1">
                      <span v-for="p in mod.products" :key="p.id"
                        class="badge-soft-success text-xxs px-1.5 py-0.5 rounded">
                        {{ p.name }}
                      </span>
                    </div>
                  </div>

                  <!-- Toggle switch -->
                  <button
                    type="button"
                    :title="mod.isCore ? 'Core module — always enabled' : mod.isActive ? 'Disable module' : 'Enable module'"
                    class="relative inline-flex h-5 w-9 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none"
                    :class="[
                      mod.isCore ? 'cursor-not-allowed' : 'cursor-pointer',
                      mod.isActive ? 'bg-(--color-primary)' : 'bg-(--bg-subtle) border border-(--border-color)',
                    ]"
                    :disabled="mod.isCore || togglingId === mod.id"
                    @click="toggleModule(mod)"
                  >
                    <span
                      class="pointer-events-none inline-block h-4 w-4 rounded-full bg-white shadow transform transition duration-200"
                      :class="mod.isActive ? 'translate-x-4' : 'translate-x-0'"
                    />
                    <span v-if="togglingId === mod.id"
                      class="absolute inset-0 flex items-center justify-center rounded-full">
                      <span class="w-3 h-3 rounded-full border border-(--color-primary)/40 border-t-(--color-primary) animate-spin" />
                    </span>
                  </button>
                </div>

                <!-- Children -->
                <div v-if="mod.children?.length" class="bg-(--bg-muted)/30 border-t border-(--border-color)/60">
                  <div
                    v-for="child in mod.children" :key="child.id"
                    class="flex items-center gap-3 px-6 py-2.5 pl-16 border-b border-(--border-color)/40 last:border-b-0 transition-colors"
                    :class="!child.isActive ? 'opacity-60' : ''"
                  >
                    <div class="w-6 h-6 rounded-md flex items-center justify-center shrink-0"
                      :class="child.isActive ? 'bg-(--color-primary-subtle) text-(--color-primary)' : 'bg-(--bg-muted) text-(--text-muted)'">
                      <i :class="['ti', child.icon || 'ti-circle', 'text-xs']" />
                    </div>
                    <div class="flex-1 min-w-0 flex items-center gap-1.5">
                      <span class="text-xs font-medium"
                        :class="child.isActive ? 'text-(--text-heading)' : 'text-(--text-muted)'">
                        {{ child.name }}
                      </span>
                      <span class="text-xxs font-mono px-1 py-0.5 rounded border border-(--border-color) text-(--text-muted)">
                        {{ child.prefix }}
                      </span>
                    </div>
                    <button
                      type="button"
                      class="relative inline-flex h-4 w-7 shrink-0 rounded-full border-2 border-transparent transition-colors duration-200 focus:outline-none cursor-pointer"
                      :class="child.isActive ? 'bg-(--color-primary)' : 'bg-(--bg-subtle) border border-(--border-color)'"
                      :disabled="togglingId === child.id"
                      @click="toggleModule(child)"
                    >
                      <span
                        class="pointer-events-none inline-block h-3 w-3 rounded-full bg-white shadow transform transition duration-200"
                        :class="child.isActive ? 'translate-x-3' : 'translate-x-0'"
                      />
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </template>
      </section>

      <!-- Platform -->
      <section v-else-if="activeTab === 'platform'" class="glass-card rounded-2xl p-6 space-y-6">
        <header class="flex items-center gap-2 pb-3 border-b border-(--border-color)">
          <i class="ti ti-server text-(--color-primary)" />
          <h3 class="text-sm font-semibold">Platform &amp; subdomains</h3>
        </header>

        <div class="rounded-xl border border-(--color-info)/30 bg-(--color-info)/8 px-4 py-3 text-xs text-(--color-info) flex items-start gap-2">
          <i class="ti ti-info-circle shrink-0 mt-0.5" />
          <span>
            The system domain is set by <code class="font-mono">APP_SYSTEM_DOMAIN</code> in the server's
            <code class="font-mono">.env</code> file and applies to every tenant on this platform.
            It cannot be changed per-tenant here.
          </span>
        </div>

        <div class="space-y-4">
          <div>
            <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
              System domain
            </label>
            <div class="flex items-center gap-3">
              <input :value="draft['platform.system_domain'] ?? '—'" type="text" readonly
                class="form-control bg-(--bg-muted) cursor-not-allowed font-mono" />
            </div>
            <p class="text-xxs text-(--text-muted) mt-1">
              Wildcard DNS must resolve <code class="font-mono">*.{{ draft['platform.system_domain'] ?? '…' }}</code>
              to this server's IP address.
            </p>
          </div>

          <div>
            <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
              Subdomain format
            </label>
            <div class="rounded-lg bg-(--bg-muted) border border-(--border-color) px-4 py-3 font-mono text-sm text-(--text-heading)">
              <span class="text-(--color-primary)">{tenant-handle}</span>.{{ draft['platform.system_domain'] ?? 'systemdomain.app' }}
            </div>
            <p class="text-xxs text-(--text-muted) mt-1">
              Each tenant customer gets a unique subdomain derived from their handle.
            </p>
          </div>

          <div>
            <label class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
              Example
            </label>
            <a :href="`https://acme-corp.${draft['platform.system_domain'] ?? 'systemdomain.app'}`"
              target="_blank" rel="noopener"
              class="inline-flex items-center gap-1.5 text-xs font-mono text-(--color-primary) hover:underline">
              <i class="ti ti-external-link" />
              acme-corp.{{ draft['platform.system_domain'] ?? 'systemdomain.app' }}
            </a>
          </div>
        </div>
      </section>
    </div>
  </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useSettings, type SettingRow } from '~/composables/useSettings'
import { useAuthStore } from '~/stores/auth'
import { useTenantStore } from '~/stores/tenant'
import { useToast } from '~/composables/useToast'

const tenantStore = useTenantStore()
const authStore = useAuthStore()
const settingsApi = useSettings()
const toast = useToast()
const api = useApi()
const { reload: reloadSidebar } = useModules()

const ALL_TABS = [
  { key: 'branding',      label: 'Branding',      icon: 'ti-palette',     adminOnly: false },
  { key: 'locale',        label: 'Locale',        icon: 'ti-world',       adminOnly: false },
  { key: 'notifications', label: 'Notifications', icon: 'ti-bell',        adminOnly: false },
  { key: 'security',      label: 'Security',      icon: 'ti-shield-lock', adminOnly: false },
  { key: 'modules',       label: 'Modules',       icon: 'ti-puzzle',      adminOnly: true  },
  { key: 'platform',      label: 'Platform',      icon: 'ti-server',      adminOnly: true  },
] as const

type TabKey = typeof ALL_TABS[number]['key']

// Only admins see Modules + Platform; all authenticated users see Branding/Locale/Notifications/Security
const tabs = computed(() =>
  ALL_TABS.filter(t => !t.adminOnly || authStore.isAdmin)
)

const activeTab = ref<TabKey>('branding')

const accentSwatches = [
  { label: 'Electric Indigo', rgb: '59 130 246' },
  { label: 'Sky',             rgb: '14 165 233' },
  { label: 'Emerald',         rgb: '16 185 129' },
  { label: 'Amber',           rgb: '245 158 11' },
  { label: 'Crimson',         rgb: '239 68 68' },
  { label: 'Violet',          rgb: '139 92 246' }
]

const loading = ref(true)
const saving = ref(false)
const alert = reactive({ msg: '', type: 'success' as 'success' | 'danger' })

// `pristine` is the server snapshot, `draft` is the working copy.
const pristine = ref<Record<string, unknown>>({})
const draft = reactive<Record<string, unknown>>({})

const dirty = computed(() =>
  Object.keys(draft).some(k => !valuesEqual(draft[k], pristine.value[k]))
)

const valuesEqual = (a: unknown, b: unknown) => JSON.stringify(a) === JSON.stringify(b)

const setBranding = (suffix: string, value: unknown) => {
  draft[`branding.${suffix}`] = value
}

const hydrate = (rows: SettingRow[]) => {
  pristine.value = settingsApi.toMap(rows)
  for (const k of Object.keys(draft)) delete draft[k]
  Object.assign(draft, JSON.parse(JSON.stringify(pristine.value)))
}

const load = async () => {
  loading.value = true
  try {
    const { data } = await settingsApi.list()
    hydrate(data)
  } catch (err: any) {
    alert.msg = err?.data?.message || 'Failed to load settings'
    alert.type = 'danger'
  } finally {
    loading.value = false
  }
}

const save = async () => {
  if (!dirty.value) return
  saving.value = true
  try {
    const changed = Object.keys(draft)
      .filter(k => !valuesEqual(draft[k], pristine.value[k]))
      .map(k => ({ key: k, value: draft[k] }))

    const { data } = await settingsApi.update(changed)
    hydrate(data)

    // Apply branding immediately so the UI reflects saved state without a reload.
    const primary = draft['branding.primary_color']
    if (typeof primary === 'string') {
      document.documentElement.style.setProperty('--color-primary-rgb', primary)
      localStorage.setItem('accent', primary)
    }

    const theme = draft['branding.theme_mode']
    if (theme === 'dark') {
      document.documentElement.setAttribute('data-bs-theme', 'dark')
      localStorage.setItem('theme', 'dark')
    } else if (theme === 'light' || theme === 'system') {
      document.documentElement.removeAttribute('data-bs-theme')
      localStorage.setItem('theme', theme === 'system' ? 'light' : 'light')
    }

    alert.type = 'success'
    alert.msg = 'Settings saved.'
    toast.success('Settings saved', 'Configuration updated.')
  } catch (err: any) {
    alert.type = 'danger'
    alert.msg = err?.data?.message || 'Failed to save settings'
  } finally {
    saving.value = false
  }
}

const reset = () => {
  Object.assign(draft, JSON.parse(JSON.stringify(pristine.value)))
  alert.msg = ''
}

// ─── Modules tab ─────────────────────────────────────────────────────────────
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

const allModules = ref<ModuleItem[]>([])
const modulesLoading = ref(false)
const togglingId = ref<string | null>(null)

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
  modulesLoading.value = true
  try {
    const res = await api.get<{ data: ModuleItem[] }>('modules/all')
    allModules.value = res.data
  } catch {
    toast.error('Failed to load modules', 'Could not fetch module list.')
  } finally {
    modulesLoading.value = false
  }
}

const toggleModule = async (mod: ModuleItem) => {
  if (mod.isCore || togglingId.value === mod.id) return
  togglingId.value = mod.id
  try {
    const res = await api.patch<{ data: ModuleItem }>(`modules/${mod.id}/toggle`)
    mod.isActive = res.data.isActive
    reloadSidebar()
  } catch (err: any) {
    toast.error('Toggle failed', err?.data?.message || 'Could not update module.')
  } finally {
    togglingId.value = null
  }
}

// Lazy-load modules only when the tab is first opened
watch(activeTab, (tab) => {
  if (tab === 'modules' && !allModules.value.length) loadAllModules()
})

onMounted(load)
</script>
