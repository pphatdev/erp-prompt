import { defineStore } from 'pinia'

export interface TenantConfig {
    id: string
    handle: string
    name: string
    primaryColor?: string
    logoUrl?: string
}

interface PublicSettingRow {
    key: string
    value: unknown
    isPublic: boolean
}

const DEFAULT_PRIMARY = '59 130 246' // #3b82f6 Electric Indigo (design.md §2.1)

export const useTenantStore = defineStore('tenant', {
    state: () => ({
        currentTenant: null as TenantConfig | null,
        availableTenants: [
            { id: 'test-tenant', handle: 'test', name: 'Test Company', primaryColor: '59 130 246' },
            { id: 'acme-corp', handle: 'acme', name: 'ACME Corporation', primaryColor: '14 165 233' },
            { id: 'cyberdyne', handle: 'cyberdyne', name: 'Cyberdyne Systems', primaryColor: '239 68 68' }
        ] as TenantConfig[]
    }),

    getters: {
        activeHandle: (state) => state.currentTenant?.handle || 'test',
        activeName: (state) => state.currentTenant?.name || 'Select Company',
        activeTenantId: (state) => state.currentTenant?.id || 'test-tenant'
    },

    actions: {
        setTenantByHandle(handle: string) {
            // If the handle hasn't changed, do NOT rebuild currentTenant.
            // Rebuilding clobbers backend-synced fields (primaryColor, logoUrl)
            // with static defaults — which is the bug that caused theme color
            // to flip back to default on every page navigation, since each
            // page wraps its own <NuxtLayout> and the layout's onMounted
            // re-calls this for the same handle.
            if (this.currentTenant?.handle === handle) {
                this.applyBrandToDocument()
                return
            }

            const tenant = this.availableTenants.find(t => t.handle === handle)
            if (tenant) {
                // Spread so subsequent mutations (e.g. syncBranding) don't
                // poison the static availableTenants source.
                this.currentTenant = { ...tenant }
            } else {
                // Unknown handle: seed primaryColor from the localStorage cache
                // populated by a previous syncBranding so the first paint after
                // reload is correct before the async sync completes.
                const cached = import.meta.client
                    ? localStorage.getItem(`tenant_primary:${handle}`)
                    : null
                this.currentTenant = {
                    id: `${handle}-id`,
                    handle,
                    name: `${handle.charAt(0).toUpperCase() + handle.slice(1)} ERP`,
                    primaryColor: cached || DEFAULT_PRIMARY,
                }
            }
            this.applyBrandToDocument()
        },

        /**
         * Push the active tenant brand onto the `--color-primary-rgb` CSS var.
         * User's local accent override (CustomizerOffcanvas) always wins so
         * personalization survives tenant switches.
         */
        applyBrandToDocument() {
            if (!import.meta.client) return
            const userAccent = localStorage.getItem('accent')
            document.documentElement.style.setProperty(
                '--color-primary-rgb',
                userAccent || this.currentTenant?.primaryColor || DEFAULT_PRIMARY
            )
        },

        initializeTenant() {
            if (import.meta.client) {
                const stored = localStorage.getItem('tenant_handle')
                this.setTenantByHandle(stored || 'test')
            }
        },

        /**
         * Pull tenant branding (primary color, logo) from the backend public
         * settings endpoint. Safe to call without auth — the route is whitelisted.
         * User's local accent override (localStorage.accent) still wins.
         */
        async syncBranding() {
            if (!import.meta.client) return
            try {
                const config = useRuntimeConfig()
                const res = await $fetch<{ data: PublicSettingRow[] }>(
                    `${config.public.apiBase}/settings/public`,
                    {
                        headers: {
                            'X-Tenant-Handle': this.activeHandle,
                            'Accept': 'application/json'
                        }
                    }
                )

                const map = new Map(res.data.map(r => [r.key, r.value]))
                const tenantPrimary = map.get('branding.primary_color')
                const logo = map.get('branding.logo_url')

                if (this.currentTenant && typeof tenantPrimary === 'string') {
                    this.currentTenant.primaryColor = tenantPrimary
                    // Cache per-handle so the next page load can paint the
                    // correct brand before the async sync completes — keeps
                    // unknown tenants (e.g. handle="demo") from defaulting.
                    localStorage.setItem(`tenant_primary:${this.activeHandle}`, tenantPrimary)
                }
                if (this.currentTenant && typeof logo === 'string') {
                    this.currentTenant.logoUrl = logo
                }

                this.applyBrandToDocument()
            } catch {
                // Best-effort — branding falls back to local defaults on failure.
            }
        }
    }
})
