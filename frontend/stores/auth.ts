import { defineStore } from 'pinia'
import { useTenantStore } from './tenant'

export interface User {
  id: string
  name: string
  email: string
  is_active: boolean
  roles: Role[]
}

export interface Role {
  id: string
  name: string
  slug: string
  description?: string
  permissions: Permission[]
}

export interface Permission {
  id: string
  name: string
  slug: string
  module: string
  feature: string
  action: string
}

interface LoginResponse {
  user: User
  token_type: string
  access_token: string
  refresh_token: string | null
  expires_in: number | null
}

interface RefreshResponse {
  token_type: string
  access_token: string
  refresh_token: string | null
  expires_in: number | null
}

const ACCESS_KEY = 'auth_token'
const REFRESH_KEY = 'auth_refresh_token'
const EXPIRES_AT_KEY = 'auth_expires_at'

/**
 * Module-scoped in-flight refresh. Prevents concurrent rotateToken() calls
 * from burning each other's refresh_token. Lives outside the store so it
 * isn't serialised into devtools / HMR state.
 */
let refreshInFlight: Promise<boolean> | null = null

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as User | null,
    accessToken: null as string | null,
    refreshToken: null as string | null,
    expiresAt: null as number | null,
    loading: false,
    error: null as string | null
  }),

  getters: {
    isAuthenticated: (state) => !!state.accessToken,
    userPermissions: (state) => {
      if (!state.user) return []
      const set = new Set<string>()
      state.user.roles.forEach(role => {
        role.permissions?.forEach(p => set.add(p.slug))
      })
      return Array.from(set)
    }
  },

  actions: {
    hasPermission(slug: string): boolean {
      if (!this.user) return false
      const isSuperAdmin = this.user.roles.some(r => r.slug === 'admin' || r.slug === 'super-admin')
      if (isSuperAdmin) return true
      return this.userPermissions.includes(slug)
    },

    async login(email: string, password: string) {
      this.loading = true
      this.error = null
      const tenantStore = useTenantStore()

      try {
        const config = useRuntimeConfig()
        const response = await $fetch<LoginResponse>(`${config.public.apiBase}/auth/login`, {
          method: 'POST',
          headers: {
            'X-Tenant-Handle': tenantStore.activeHandle,
            'Accept': 'application/json'
          },
          body: { email, password }
        })

        this.setTokens(response.access_token, response.refresh_token, response.expires_in)
        this.user = response.user

        if (import.meta.client) {
          localStorage.setItem('tenant_handle', tenantStore.activeHandle)
        }
        return true
      } catch (err: any) {
        this.error = err.data?.message || 'Login failed. Please check your credentials.'
        throw err
      } finally {
        this.loading = false
      }
    },

    async fetchProfile() {
      if (!this.accessToken) return
      const tenantStore = useTenantStore()

      try {
        const config = useRuntimeConfig()
        const response = await $fetch<{ data: User }>(`${config.public.apiBase}/auth/me`, {
          headers: {
            'Authorization': `Bearer ${this.accessToken}`,
            'X-Tenant-Handle': tenantStore.activeHandle,
            'Accept': 'application/json'
          }
        })
        this.user = response.data
      } catch (err) {
        this.logout()
      }
    },

    /**
     * Exchange refresh_token for a fresh access_token + rotated refresh_token.
     * Returns true on success, false (and force-logs-out) on failure.
     *
     * Multiple callers in parallel share the same in-flight Promise so we
     * don't double-spend the (single-use) refresh token.
     */
    async rotateToken(): Promise<boolean> {
      if (refreshInFlight) return refreshInFlight

      if (!this.refreshToken) {
        this.logout()
        return false
      }

      const tenantStore = useTenantStore()
      const config = useRuntimeConfig()

      refreshInFlight = (async () => {
        try {
          const response = await $fetch<RefreshResponse>(`${config.public.apiBase}/auth/refresh`, {
            method: 'POST',
            headers: {
              'X-Tenant-Handle': tenantStore.activeHandle,
              'Accept': 'application/json'
            },
            body: { refresh_token: this.refreshToken }
          })

          this.setTokens(response.access_token, response.refresh_token, response.expires_in)
          return true
        } catch (err) {
          await this.logout({ silent: true })
          if (import.meta.client) {
            const router = useRouter()
            const current = router.currentRoute.value.fullPath
            if (!current.startsWith('/login')) {
              router.push({ path: '/login', query: { redirect: current } })
            }
          }
          return false
        } finally {
          refreshInFlight = null
        }
      })()

      return refreshInFlight
    },

    async logout(options: { silent?: boolean } = {}) {
      const tenantStore = useTenantStore()

      if (this.accessToken && !options.silent) {
        try {
          const config = useRuntimeConfig()
          await $fetch(`${config.public.apiBase}/auth/logout`, {
            method: 'POST',
            headers: {
              'Authorization': `Bearer ${this.accessToken}`,
              'X-Tenant-Handle': tenantStore.activeHandle,
              'Accept': 'application/json'
            }
          })
        } catch {
          // Ignore — backend revokes the chain on its side anyway.
        }
      }

      this.clearTokens()
      this.user = null
    },

    initializeAuth() {
      if (!import.meta.client) return

      const access = localStorage.getItem(ACCESS_KEY)
      const refresh = localStorage.getItem(REFRESH_KEY)
      const expiresAtRaw = localStorage.getItem(EXPIRES_AT_KEY)

      if (!access) return

      this.accessToken = access
      this.refreshToken = refresh
      this.expiresAt = expiresAtRaw ? Number(expiresAtRaw) : null

      // If the access token has already expired (or is about to), refresh
      // proactively so the next API call doesn't 401.
      const expiresSoon = this.expiresAt !== null && this.expiresAt - Date.now() < 30_000

      if (expiresSoon && this.refreshToken) {
        this.rotateToken().then((ok) => {
          if (ok) this.fetchProfile()
        })
      } else {
        this.fetchProfile()
      }
    },

    setTokens(access: string, refresh: string | null, expiresIn: number | null) {
      this.accessToken = access
      this.refreshToken = refresh ?? this.refreshToken
      this.expiresAt = expiresIn ? Date.now() + expiresIn * 1000 : null

      if (!import.meta.client) return
      localStorage.setItem(ACCESS_KEY, access)
      if (refresh) localStorage.setItem(REFRESH_KEY, refresh)
      if (this.expiresAt) {
        localStorage.setItem(EXPIRES_AT_KEY, String(this.expiresAt))
      } else {
        localStorage.removeItem(EXPIRES_AT_KEY)
      }
    },

    clearTokens() {
      this.accessToken = null
      this.refreshToken = null
      this.expiresAt = null
      if (!import.meta.client) return
      localStorage.removeItem(ACCESS_KEY)
      localStorage.removeItem(REFRESH_KEY)
      localStorage.removeItem(EXPIRES_AT_KEY)
    }
  }
})
