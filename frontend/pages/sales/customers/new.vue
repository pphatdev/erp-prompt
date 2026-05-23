<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Page header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div class="flex items-center gap-3">
                    <NuxtLink to="/sales/customers"
                        class="w-9 h-9 rounded-lg border border-(--border-color) bg-(--bg-card) hover:bg-(--bg-muted) flex items-center justify-center text-(--text-muted) hover:text-(--color-primary) transition-colors"
                        title="Back to customers">
                        <i class="ti ti-arrow-left" />
                    </NuxtLink>
                    <div>
                        <h1 class="text-xl font-semibold">New customer</h1>
                        <p class="text-xs text-(--text-muted) mt-0.5">
                            Capture identity, classification, and (for platform tenants) provisioning details.
                        </p>
                    </div>
                </div>
                <div class="flex gap-2">
                    <NuxtLink to="/sales/customers" class="btn btn-ghost text-xs">
                        <i class="ti ti-x" />Cancel
                    </NuxtLink>
                    <button type="button" class="btn btn-primary text-xs" :disabled="submitting" @click="submit">
                        <i :class="['ti', submitting ? 'ti-loader-2 animate-spin' : 'ti-device-floppy']" />
                        {{ submitting ? 'Saving…' : 'Create customer' }}
                    </button>
                </div>
            </header>

            <!-- Section nav -->
            <nav class="glass-card rounded-xl p-2 flex gap-1 overflow-x-auto">
                <button v-for="tab in visibleTabs" :key="tab.key" type="button"
                    class="px-3 py-1.5 text-xxs font-bold uppercase tracking-widest rounded-lg transition-colors whitespace-nowrap"
                    :class="activeTab === tab.key
                        ? 'bg-(--color-primary)/15 text-(--color-primary)'
                        : 'text-(--text-muted) hover:text-(--text-heading) hover:bg-(--bg-muted)'" @click="activeTab = tab.key">
                    <i :class="`ti ${tab.icon} mr-1`" />{{ tab.label }}
                </button>
            </nav>

            <!-- Two-column form: section content + side helper -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <section class="glass-card rounded-2xl p-6 lg:col-span-2">

                    <!-- Identity -->
                    <div v-show="activeTab === 'identity'" class="space-y-4">
                        <h2 class="section-heading"><i class="ti ti-user" />Identity</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="form-label">Full name *</label>
                                <input v-model="form.name" type="text" maxlength="255" class="form-control"
                                    :class="{ 'ring-1 ring-(--color-danger)': showErrors && !form.name }" />
                                <p v-if="showErrors && !form.name" class="form-error">Name is required.</p>
                            </div>
                            <div>
                                <label class="form-label">Email *</label>
                                <input v-model="form.email" type="email" class="form-control"
                                    :class="{ 'ring-1 ring-(--color-danger)': showErrors && !form.email }" />
                                <p v-if="showErrors && !form.email" class="form-error">Email is required.</p>
                            </div>
                            <div>
                                <label class="form-label">Phone</label>
                                <input v-model="form.phone" type="tel" maxlength="50" class="form-control" />
                            </div>
                            <div>
                                <label class="form-label">Company name</label>
                                <input v-model="form.company_name" type="text" maxlength="255" class="form-control" />
                            </div>
                            <div>
                                <label class="form-label">Status</label>
                                <select v-model="form.status" class="form-control">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Classification -->
                    <div v-show="activeTab === 'classification'" class="space-y-4">
                        <h2 class="section-heading"><i class="ti ti-tag" />Classification</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div :class="form.customer_type === 'tenant' ? 'md:col-span-2' : ''">
                                <label class="form-label">Customer type</label>
                                <select v-model="form.customer_type" class="form-control">
                                    <option value="individual">Individual</option>
                                    <option value="business">Business</option>
                                    <option value="tenant">Tenant (buys the platform)</option>
                                </select>
                                <p v-if="form.customer_type === 'tenant'"
                                    class="text-xxs text-(--color-primary) mt-1.5">
                                    <i class="ti ti-info-circle" /> Confirming a subscription will provision a new
                                    tenant database.
                                </p>
                            </div>

                            <!-- Handle — shown inline when type=tenant so it can't be missed -->
                            <div v-if="form.customer_type === 'tenant'" class="md:col-span-2">
                                <label class="form-label">
                                    Tenant handle *
                                    <span
                                        class="ml-1 text-xxs font-normal normal-case tracking-normal text-(--text-muted)">
                                        — the subdomain customers use to log in
                                    </span>
                                </label>
                                <div class="relative">
                                    <i class="ti ti-at absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm"></i>
                                    <input v-model="form.tenant_handle" type="text" maxlength="60"
                                        class="form-control pl-7 pr-8 font-mono lowercase"
                                        :class="{
                                            'ring-1 ring-(--color-danger)': (showErrors && !form.tenant_handle) || handleStatus === 'taken' || handleStatus === 'invalid',
                                            'ring-1 ring-(--color-success)': handleStatus === 'available',
                                        }"
                                        placeholder="acme-corp"
                                        @input="form.tenant_handle = (($event.target as HTMLInputElement).value).toLowerCase().replace(/[^a-z0-9-]/g, '')" />
                                    <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm pointer-events-none">
                                        <i v-if="handleStatus === 'checking'" class="ti ti-loader-2 animate-spin text-(--text-muted)" />
                                        <i v-else-if="handleStatus === 'available'" class="ti ti-circle-check-filled text-(--color-success)" />
                                        <i v-else-if="handleStatus === 'taken'" class="ti ti-circle-x-filled text-(--color-danger)" />
                                        <i v-else-if="handleStatus === 'invalid'" class="ti ti-alert-triangle text-(--color-warning)" />
                                    </span>
                                </div>
                                <p v-if="showErrors && !form.tenant_handle" class="form-error">Handle is required for tenant customers.</p>
                                <p v-else-if="handleStatus === 'taken'" class="form-error">This handle is already taken.</p>
                                <p v-else-if="handleStatus === 'invalid'" class="text-xxs text-(--color-warning) mt-1">Use lowercase letters, digits, and hyphens only.</p>
                                <p v-else-if="handleStatus === 'available'" class="text-xxs text-(--color-success) mt-1"><i class="ti ti-check" /> Handle is available.</p>
                                <p v-else class="text-xxs text-(--text-muted) mt-1">Lowercase letters, digits, and hyphens only. E.g. <code class="font-mono">acme-corp</code></p>
                            </div>
                            <div>
                                <label class="form-label">Tier</label>
                                <select v-model="form.tier" class="form-control">
                                    <option value="standard">Standard</option>
                                    <option value="premium">Premium</option>
                                    <option value="enterprise">Enterprise</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label">External code</label>
                                <input v-model="form.external_code" type="text" maxlength="100" class="form-control"
                                    placeholder="CRM / ERP ref" />
                            </div>
                            <div>
                                <label class="form-label">Industry</label>
                                <input v-model="form.industry" type="text" maxlength="100" class="form-control"
                                    placeholder="e.g. Manufacturing" />
                            </div>
                            <div>
                                <label class="form-label">Tax ID</label>
                                <input v-model="form.tax_id" type="text" maxlength="60" class="form-control" />
                            </div>
                            <div>
                                <label class="form-label">Website</label>
                                <input v-model="form.website" type="url" maxlength="255" class="form-control"
                                    placeholder="https://" />
                            </div>
                        </div>
                    </div>

                    <!-- Address -->
                    <div v-show="activeTab === 'address'" class="space-y-4">
                        <h2 class="section-heading"><i class="ti ti-map-pin" />Billing address</h2>
                        <div>
                            <label class="form-label">Street address</label>
                            <textarea v-model="form.address" rows="2" maxlength="1000" class="form-control" />
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">City</label>
                                <input v-model="form.billing_city" type="text" maxlength="100" class="form-control" />
                            </div>
                            <div>
                                <label class="form-label">State / Province</label>
                                <input v-model="form.billing_state" type="text" maxlength="100" class="form-control" />
                            </div>
                            <div>
                                <label class="form-label">Postal code</label>
                                <input v-model="form.billing_postal_code" type="text" maxlength="20"
                                    class="form-control" />
                            </div>
                            <div>
                                <label class="form-label">Country (ISO alpha-2)</label>
                                <input v-model="form.billing_country" type="text" maxlength="2" class="form-control"
                                    placeholder="US" style="text-transform:uppercase" />
                            </div>
                        </div>
                    </div>

                    <!-- Locale & Notes -->
                    <div v-show="activeTab === 'locale'" class="space-y-4">
                        <h2 class="section-heading"><i class="ti ti-world" />Locale & notes</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="form-label">Currency (ISO 4217)</label>
                                <input v-model="form.currency" type="text" maxlength="3" class="form-control"
                                    placeholder="USD" style="text-transform:uppercase" />
                            </div>
                            <div>
                                <label class="form-label">Language (BCP-47)</label>
                                <input v-model="form.language" type="text" maxlength="10" class="form-control"
                                    placeholder="en-US" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="form-label">Timezone (IANA)</label>
                                <input v-model="form.timezone" type="text" maxlength="60" class="form-control"
                                    placeholder="America/New_York" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="form-label">Notes</label>
                                <textarea v-model="form.notes" rows="4" maxlength="2000" class="form-control"
                                    placeholder="Internal notes about this customer..." />
                            </div>
                        </div>
                    </div>

                    <!-- Branding -->
                    <div v-show="activeTab === 'branding'" class="space-y-4">
                        <h2 class="section-heading"><i class="ti ti-palette" />Branding</h2>
                        <p class="text-xs text-(--text-muted)">Seeds the customer's tenant with default branding on
                            provisioning.</p>
                        <div>
                            <label class="form-label">Primary color</label>
                            <div class="flex items-center gap-3">
                                <input :value="brandHex" type="color" class="color-swatch shrink-0" title="Pick a color"
                                    @input="onColorPick" />
                                <input v-model="form.brand_primary_color" type="text" maxlength="15"
                                    class="form-control font-mono" placeholder="59 130 246" />
                            </div>

                            <!-- Preset palette -->
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button v-for="preset in COLOR_PRESETS" :key="preset.rgb" type="button"
                                    class="preset-swatch" :class="{ active: form.brand_primary_color === preset.rgb }"
                                    :style="{ background: `rgb(${preset.rgb})` }" :title="preset.name"
                                    @click="form.brand_primary_color = preset.rgb" />
                            </div>

                            <p class="text-xxs text-(--text-muted) mt-2">
                                Stored as a space-separated R G B triple (e.g. <code>59 130 246</code>) so it can drop
                                straight into <code>rgb()</code> at runtime.
                            </p>
                        </div>

                        <div>
                            <label class="form-label">Logo</label>

                            <!-- Upload dropzone -->
                            <div class="flex items-stretch gap-4">
                                <label
                                    class="flex-1 flex flex-col items-center justify-center gap-2 px-4 py-6 rounded-lg border-2 border-dashed border-(--border-color) bg-(--bg-muted)/40 hover:bg-(--bg-muted) hover:border-(--color-primary)/40 cursor-pointer transition-colors"
                                    :class="{ 'opacity-60 pointer-events-none': uploadingLogo }">
                                    <i
                                        :class="['ti', uploadingLogo ? 'ti-loader-2 animate-spin' : 'ti-cloud-upload', 'text-2xl text-(--color-primary)']" />
                                    <span class="text-xs text-(--text-heading) font-semibold">
                                        {{ uploadingLogo ? 'Processing…' : (form.brand_logo_url ? 'Replace logo' :
                                        'Click to upload logo') }}
                                    </span>
                                    <span class="text-xxs text-(--text-muted)">PNG / JPG / SVG / WebP — max 200KB</span>
                                    <input ref="logoInputEl" type="file"
                                        accept="image/png,image/jpeg,image/gif,image/webp,image/svg+xml" class="hidden"
                                        @change="onLogoSelected" />
                                </label>

                                <!-- Preview tile -->
                                <div v-if="form.brand_logo_url"
                                    class="w-24 h-24 rounded-lg border border-(--border-color) bg-(--bg-muted) shrink-0 flex items-center justify-center p-2 relative group">
                                    <img :src="form.brand_logo_url" class="max-w-full max-h-full object-contain"
                                        alt="Logo preview" />
                                    <button type="button"
                                        class="absolute -top-2 -right-2 w-6 h-6 rounded-full bg-(--color-danger) text-white opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center shadow-(--shadow-sm)"
                                        title="Remove logo" @click="clearLogo">
                                        <i class="ti ti-x text-xs" />
                                    </button>
                                </div>
                            </div>

                            <!-- External URL fallback -->
                            <details class="mt-2">
                                <summary
                                    class="text-xxs text-(--text-muted) cursor-pointer hover:text-(--text-heading)">
                                    …or paste an external URL
                                </summary>
                                <input v-model="form.brand_logo_url" type="url" maxlength="500"
                                    class="form-control mt-2" placeholder="https://example.com/logo.png" />
                            </details>
                        </div>
                    </div>

                    <!-- Tenant -->
                    <div v-show="activeTab === 'tenant'" class="space-y-4">
                        <h2 class="section-heading"><i class="ti ti-server" />Tenant provisioning</h2>
                        <div
                            class="rounded-lg p-4 bg-(--color-primary)/10 border border-(--color-primary)/30 text-xs text-(--color-primary)">
                            <i class="ti ti-info-circle mr-1" />
                            The tenant database is provisioned automatically when a subscription for this customer is
                            confirmed.
                            Reserve the handle now to control the subdomain.
                        </div>
                        <div>
                            <label class="form-label">Tenant handle *</label>
                            <div class="relative">
                                <i class="ti ti-at absolute left-3 top-1/2 -translate-y-1/2 text-(--text-muted) text-sm"></i>
                                <input v-model="form.tenant_handle" type="text" maxlength="60"
                                    class="form-control pl-7 pr-8 font-mono lowercase"
                                    :class="{
                                        'ring-1 ring-(--color-danger)': (showErrors && !form.tenant_handle) || handleStatus === 'taken' || handleStatus === 'invalid',
                                        'ring-1 ring-(--color-success)': handleStatus === 'available',
                                    }"
                                    placeholder="acme-corp"
                                    @input="form.tenant_handle = (($event.target as HTMLInputElement).value).toLowerCase().replace(/[^a-z0-9-]/g, '')" />
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 text-sm pointer-events-none">
                                    <i v-if="handleStatus === 'checking'" class="ti ti-loader-2 animate-spin text-(--text-muted)" />
                                    <i v-else-if="handleStatus === 'available'" class="ti ti-circle-check-filled text-(--color-success)" />
                                    <i v-else-if="handleStatus === 'taken'" class="ti ti-circle-x-filled text-(--color-danger)" />
                                    <i v-else-if="handleStatus === 'invalid'" class="ti ti-alert-triangle text-(--color-warning)" />
                                </span>
                            </div>
                            <p v-if="showErrors && !form.tenant_handle" class="form-error">Handle is required.</p>
                            <p v-else-if="handleStatus === 'taken'" class="form-error">This handle is already taken.</p>
                            <p v-else-if="handleStatus === 'invalid'" class="text-xxs text-(--color-warning) mt-1">Use lowercase letters, digits, and hyphens only.</p>
                            <p v-else-if="handleStatus === 'available'" class="text-xxs text-(--color-success) mt-1"><i class="ti ti-check" /> Handle is available.</p>
                            <p v-else class="text-xxs text-(--text-muted) mt-1">Lowercase letters, digits, hyphens. Used as login subdomain.</p>
                        </div>
                    </div>
                </section>

                <!-- Side helper -->
                <aside class="space-y-4">
                    <section class="glass-card rounded-2xl p-5">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-(--text-muted) mb-3">Summary</h3>
                        <dl class="space-y-2 text-xs">
                            <div class="flex justify-between gap-3">
                                <dt class="text-(--text-muted)">Name</dt>
                                <dd class="text-(--text-heading) font-semibold truncate">{{ form.name || '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-(--text-muted)">Email</dt>
                                <dd class="text-(--text-body) truncate">{{ form.email || '—' }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-(--text-muted)">Type</dt>
                                <dd class="text-(--text-body) capitalize">{{ form.customer_type }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-(--text-muted)">Tier</dt>
                                <dd class="text-(--text-body) capitalize">{{ form.tier }}</dd>
                            </div>
                            <div class="flex justify-between gap-3">
                                <dt class="text-(--text-muted)">Status</dt>
                                <dd class="text-(--text-body) capitalize">{{ form.status }}</dd>
                            </div>
                            <div v-if="form.customer_type === 'tenant' && form.tenant_handle"
                                class="flex justify-between gap-3">
                                <dt class="text-(--text-muted)">Handle</dt>
                                <dd class="text-(--color-primary) font-mono truncate">@{{ form.tenant_handle }}</dd>
                            </div>
                        </dl>
                    </section>

                    <section class="glass-card rounded-2xl p-5">
                        <h3 class="text-xs font-bold uppercase tracking-widest text-(--text-muted) mb-3">Checklist</h3>
                        <ul class="space-y-2 text-xs">
                            <li v-for="step in checklist" :key="step.key" class="flex items-center gap-2"
                                :class="step.done ? 'text-(--color-success)' : 'text-(--text-muted)'">
                                <i :class="step.done ? 'ti ti-check' : 'ti ti-point'" />
                                {{ step.label }}
                            </li>
                        </ul>
                    </section>
                </aside>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, reactive, ref, watch, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useSales } from '~/composables/useSales'
import { useToast } from '~/composables/useToast'

definePageMeta({ breadcrumb: 'New customer' })

const router = useRouter()
const sales = useSales()
const toast = useToast()

const submitting = ref(false)
const showErrors = ref(false)
const uploadingLogo = ref(false)
const logoInputEl = ref<HTMLInputElement | null>(null)

type TabKey = 'identity' | 'classification' | 'address' | 'locale' | 'branding' | 'tenant'
const activeTab = ref<TabKey>('identity')

const TABS = [
    { key: 'identity', label: 'Identity', icon: 'ti-user' },
    { key: 'classification', label: 'Classification', icon: 'ti-tag' },
    { key: 'address', label: 'Address', icon: 'ti-map-pin' },
    { key: 'locale', label: 'Locale & Notes', icon: 'ti-world' },
    { key: 'branding', label: 'Branding', icon: 'ti-palette' },
    { key: 'tenant', label: 'Tenant', icon: 'ti-server' },
] as const

const form = reactive({
    name: '',
    email: '',
    phone: '',
    company_name: '',
    status: 'active' as 'active' | 'inactive',
    customer_type: 'individual' as 'individual' | 'business' | 'tenant',
    external_code: '',
    tier: 'standard' as 'standard' | 'premium' | 'enterprise',
    tax_id: '',
    industry: '',
    website: '',
    address: '',
    billing_city: '',
    billing_state: '',
    billing_postal_code: '',
    billing_country: '',
    currency: 'USD',
    language: 'en-US',
    timezone: 'UTC',
    notes: '',
    brand_primary_color: '',
    brand_logo_url: '',
    tenant_handle: '',
})

watch(() => form.customer_type, (type) => {
    if (type === 'tenant') activeTab.value = 'tenant'
})

// ───── Handle availability check ────────────────────────────
type HandleStatus = 'idle' | 'checking' | 'available' | 'taken' | 'invalid'
const handleStatus = ref<HandleStatus>('idle')
let handleTimer: ReturnType<typeof setTimeout> | null = null

watch(() => form.tenant_handle, (handle) => {
    if (handleTimer) clearTimeout(handleTimer)
    if (!handle) { handleStatus.value = 'idle'; return }
    if (!/^[a-z0-9](?:[a-z0-9-]{0,58}[a-z0-9])?$/.test(handle)) {
        handleStatus.value = 'invalid'; return
    }
    handleStatus.value = 'checking'
    handleTimer = setTimeout(async () => {
        try {
            const res = await sales.customers.checkHandle(handle)
            handleStatus.value = res.available ? 'available' : 'taken'
        } catch {
            handleStatus.value = 'idle'
        }
    }, 450)
})

onUnmounted(() => { if (handleTimer) clearTimeout(handleTimer) })

const visibleTabs = computed(() =>
    form.customer_type === 'tenant' ? TABS : TABS.filter(t => t.key !== 'tenant'),
)

// ───── Color picker bridge ────────────────────────────────
// The backend stores brand color as a space-separated RGB triple
// ("59 130 246"). <input type="color"> only speaks hex, so we
// translate both directions.
const COLOR_PRESETS = [
    { name: 'Blue', rgb: '59 130 246' },
    { name: 'Indigo', rgb: '99 102 241' },
    { name: 'Violet', rgb: '139 92 246' },
    { name: 'Purple', rgb: '168 85 247' },
    { name: 'Pink', rgb: '236 72 153' },
    { name: 'Rose', rgb: '244 63 94' },
    { name: 'Red', rgb: '239 68 68' },
    { name: 'Orange', rgb: '249 115 22' },
    { name: 'Amber', rgb: '245 158 11' },
    { name: 'Green', rgb: '34 197 94' },
    { name: 'Emerald', rgb: '16 185 129' },
    { name: 'Teal', rgb: '20 184 166' },
    { name: 'Cyan', rgb: '6 182 212' },
    { name: 'Sky', rgb: '14 165 233' },
    { name: 'Slate', rgb: '100 116 139' },
    { name: 'Zinc', rgb: '113 113 122' },
]

const brandHex = computed(() => {
    const v = form.brand_primary_color?.trim()
    if (!v) return '#3b82f6'
    const parts = v.split(/\s+/).map(p => parseInt(p, 10))
    if (parts.length !== 3 || parts.some(p => isNaN(p) || p < 0 || p > 255)) {
        return '#3b82f6'
    }
    return '#' + parts.map(p => p.toString(16).padStart(2, '0')).join('')
})

const onColorPick = (e: Event) => {
    const hex = (e.target as HTMLInputElement).value
    const r = parseInt(hex.slice(1, 3), 16)
    const g = parseInt(hex.slice(3, 5), 16)
    const b = parseInt(hex.slice(5, 7), 16)
    form.brand_primary_color = `${r} ${g} ${b}`
}

// ───── Logo upload (base64, client-side encoded) ─────────────
const LOGO_MAX_BYTES = 200 * 1024 // 200 KB raw — base64 inflates to ~267 KB

const onLogoSelected = async (e: Event) => {
    const target = e.target as HTMLInputElement
    const file = target.files?.[0]
    if (!file) return

    if (!file.type.startsWith('image/')) {
        toast.error('Invalid file', 'Please choose an image (PNG, JPG, GIF, WebP, or SVG).')
        target.value = ''
        return
    }
    if (file.size > LOGO_MAX_BYTES) {
        const kb = Math.round(file.size / 1024)
        toast.error('Logo too large', `${kb}KB exceeds the 200KB cap. Resize and retry.`)
        target.value = ''
        return
    }

    uploadingLogo.value = true
    try {
        form.brand_logo_url = await fileToDataUrl(file)
    } catch (err: any) {
        toast.error('Read failed', err?.message ?? 'Could not read the file.')
    } finally {
        uploadingLogo.value = false
        target.value = ''
    }
}

const fileToDataUrl = (file: File): Promise<string> =>
    new Promise((resolve, reject) => {
        const reader = new FileReader()
        reader.onload = () => resolve(reader.result as string)
        reader.onerror = () => reject(reader.error ?? new Error('FileReader failed'))
        reader.readAsDataURL(file)
    })

const clearLogo = () => {
    form.brand_logo_url = ''
    if (logoInputEl.value) logoInputEl.value.value = ''
}

const checklist = computed(() => [
    { key: 'name', label: 'Name set', done: !!form.name },
    { key: 'email', label: 'Email set', done: !!form.email },
    { key: 'address', label: 'Address provided', done: !!(form.address || form.billing_city) },
    { key: 'locale', label: 'Locale configured', done: !!(form.currency && form.timezone) },
    ...(form.customer_type === 'tenant'
        ? [{ key: 'handle', label: 'Tenant handle reserved', done: !!form.tenant_handle }]
        : []),
])

const buildPayload = () => ({
    name: form.name,
    email: form.email,
    phone: form.phone || null,
    company_name: form.company_name || null,
    status: form.status,
    customer_type: form.customer_type,
    external_code: form.external_code || null,
    tier: form.tier,
    tax_id: form.tax_id || null,
    industry: form.industry || null,
    website: form.website || null,
    address: form.address || null,
    billing_city: form.billing_city || null,
    billing_state: form.billing_state || null,
    billing_postal_code: form.billing_postal_code || null,
    billing_country: form.billing_country ? form.billing_country.toUpperCase() : null,
    currency: form.currency || 'USD',
    language: form.language || 'en-US',
    timezone: form.timezone || 'UTC',
    notes: form.notes || null,
    brand_primary_color: form.brand_primary_color || null,
    brand_logo_url: form.brand_logo_url || null,
    tenant_handle: form.customer_type === 'tenant' ? (form.tenant_handle || null) : undefined,
})

const submit = async () => {
    showErrors.value = true
    if (!form.name || !form.email) {
        toast.error('Missing required fields', 'Name and email are required.')
        activeTab.value = 'identity'
        return
    }
    if (form.customer_type === 'tenant' && !form.tenant_handle) {
        toast.error('Tenant handle required', 'Set a handle so the customer can log in via their subdomain.')
        activeTab.value = 'tenant'
        return
    }
    if (form.customer_type === 'tenant' && handleStatus.value === 'taken') {
        toast.error('Handle already in use', 'Choose a different handle for this tenant.')
        activeTab.value = 'classification'
        return
    }
    if (form.customer_type === 'tenant' && handleStatus.value === 'invalid') {
        toast.error('Invalid handle', 'Use lowercase letters, numbers, and hyphens only.')
        activeTab.value = 'classification'
        return
    }
    submitting.value = true
    try {
        const res = await sales.customers.create(buildPayload())
        toast.success('Customer created', res.data.name)
        router.push(`/sales/customers/${res.data.id}`)
    } catch (err: any) {
        const msg = err?.data?.message
            || Object.values(err?.data?.errors || {}).flat()[0]
            || 'Check the form and try again.'
        toast.error('Failed to save customer', String(msg))
    } finally {
        submitting.value = false
    }
}
</script>

<style scoped>
.section-heading {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: var(--text-heading);
    padding-bottom: 0.5rem;
    border-bottom: 1px solid var(--border-color);
}

.section-heading i {
    color: var(--color-primary);
}

.form-label {
    display: block;
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-muted);
    margin-bottom: 0.375rem;
}

.form-error {
    margin-top: 0.25rem;
    font-size: 0.65rem;
    color: var(--color-danger);
}

.color-swatch {
    width: 3rem;
    height: 3rem;
    padding: 0;
    border: 1px solid var(--border-color);
    border-radius: 0.5rem;
    background: transparent;
    cursor: pointer;
    transition: border-color 0.15s ease, transform 0.15s ease;
}

.color-swatch:hover {
    border-color: var(--color-primary);
    transform: scale(1.02);
}

.color-swatch::-webkit-color-swatch-wrapper {
    padding: 3px;
    border-radius: 0.5rem;
}

.color-swatch::-webkit-color-swatch {
    border: none;
    border-radius: 0.35rem;
}

.color-swatch::-moz-color-swatch {
    border: none;
    border-radius: 0.35rem;
}

.preset-swatch {
    width: 1.75rem;
    height: 1.75rem;
    border-radius: 0.375rem;
    border: 2px solid transparent;
    cursor: pointer;
    transition: transform 0.15s ease, border-color 0.15s ease;
    box-shadow: 0 0 0 1px rgb(0 0 0 / 0.08);
}

.preset-swatch:hover {
    transform: scale(1.12);
}

.preset-swatch.active {
    border-color: var(--text-heading);
    transform: scale(1.1);
}
</style>
