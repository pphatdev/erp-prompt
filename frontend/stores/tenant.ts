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
      { id: 'test-tenant', handle: 'test',      name: 'Test Company',       primaryColor: '59 130 246' },
      { id: 'acme-corp',   handle: 'acme',      name: 'ACME Corporation',   primaryColor: '14 165 233' },
      { id: 'cyberdyne',   handle: 'cyberdyne', name: 'Cyberdyne Systems',  primaryColor: '239 68 68' }
    ] as TenantConfig[]
  }),

  getters: {
    activeHandle:   (state) => state.currentTenant?.handle || 'test',
    activeName:     (state) => state.currentTenant?.name || 'Select Company',
    activeTenantId: (state) => state.currentTenant?.id || 'test-tenant'
  },

  actions: {
    setTenantByHandle(handle: string) {
      const tenant = this.availableTenants.find(t => t.handle === handle)
      if (tenant) {
        this.currentTenant = tenant
      } else {
        this.currentTenant = {
          id: `${handle}-id`,
          handle,
          name: `${handle.charAt(0).toUpperCase() + handle.slice(1)} ERP`,
          primaryColor: DEFAULT_PRIMARY
        }
      }
      if (import.meta.client) {
        const userAccent = localStorage.getItem('accent')
        document.documentElement.style.setProperty(
          '--color-primary-rgb',
          userAccent || this.currentTenant.primaryColor || DEFAULT_PRIMARY
        )
      }
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
        }
        if (this.currentTenant && typeof logo === 'string') {
          this.currentTenant.logoUrl = logo
        }

        // Re-apply CSS — userAccent override takes precedence over tenant.
        const userAccent = localStorage.getItem('accent')
        document.documentElement.style.setProperty(
          '--color-primary-rgb',
          userAccent || (typeof tenantPrimary === 'string' ? tenantPrimary : null) ||
            this.currentTenant?.primaryColor || DEFAULT_PRIMARY
        )
      } catch {
        // Best-effort — branding falls back to local defaults on failure.
      }
    }
  }
})
