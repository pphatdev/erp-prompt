import { defineStore } from 'pinia'

export interface TenantConfig {
  id: string
  handle: string
  name: string
  primaryColor?: string
  logoUrl?: string
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
        if (import.meta.client) {
          document.documentElement.style.setProperty('--color-primary-rgb', tenant.primaryColor || DEFAULT_PRIMARY)
        }
      } else {
        const newTenant: TenantConfig = {
          id: `${handle}-id`,
          handle,
          name: `${handle.charAt(0).toUpperCase() + handle.slice(1)} ERP`,
          primaryColor: DEFAULT_PRIMARY
        }
        this.currentTenant = newTenant
        if (import.meta.client) {
          document.documentElement.style.setProperty('--color-primary-rgb', DEFAULT_PRIMARY)
        }
      }
    },
    initializeTenant() {
      if (import.meta.client) {
        const stored = localStorage.getItem('tenant_handle')
        this.setTenantByHandle(stored || 'test')
      }
    }
  }
})
