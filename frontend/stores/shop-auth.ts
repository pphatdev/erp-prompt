import { defineStore } from 'pinia'
import { useTenantStore } from '~/stores/tenant'

export interface Shopper {
    id: string
    email: string
    firstName: string | null
    lastName: string | null
    phone: string | null
    isGuest: boolean
    isActive: boolean
    addresses?: ShopperAddress[]
}

export interface ShopperAddress {
    id: string
    customerId: string
    label: string | null
    recipientName: string
    phone: string | null
    line1: string
    line2: string | null
    city: string
    state: string | null
    postalCode: string | null
    country: string
    isDefaultShipping: boolean
    isDefaultBilling: boolean
}

interface AuthResponse {
    customer: Shopper
    token_type: string
    access_token: string
    expires_at: string | null
}

const TOKEN_KEY = 'shop_token'
const TOKEN_EXPIRES_KEY = 'shop_token_expires'
const SHOPPER_KEY = 'shop_shopper'
const CART_SESSION_KEY = 'shop_cart_session'
// Track which tenant a token was issued against so the auth gate can
// invalidate when the shopper navigates to a different subdomain.
const TOKEN_TENANT_KEY = 'shop_token_tenant'

export const useShopAuthStore = defineStore('shop-auth', {
    state: () => ({
        shopper: null as Shopper | null,
        accessToken: null as string | null,
        tokenExpiresAt: null as string | null,
        tokenTenantHandle: null as string | null,
        cartSessionToken: null as string | null,
        loading: false,
        error: null as string | null,
    }),

    getters: {
        isAuthenticated: (s) => !!s.accessToken && !!s.shopper && !s.shopper.isGuest,
    },

    actions: {
        initFromStorage() {
            if (import.meta.client) {
                this.accessToken = localStorage.getItem(TOKEN_KEY)
                this.tokenExpiresAt = localStorage.getItem(TOKEN_EXPIRES_KEY)
                this.tokenTenantHandle = localStorage.getItem(TOKEN_TENANT_KEY)
                const raw = localStorage.getItem(SHOPPER_KEY)
                this.shopper = raw ? JSON.parse(raw) : null
                let session = localStorage.getItem(CART_SESSION_KEY)
                if (!session) {
                    session = randomUUID()
                    localStorage.setItem(CART_SESSION_KEY, session)
                }
                this.cartSessionToken = session
            }
        },

        /**
         * Clear the in-memory token + shopper without touching the cart
         * session. Used by the auth gate when the persisted token was
         * issued for a tenant other than the one the shopper is currently
         * on — the token would 401 anyway, this just short-circuits.
         */
        clearTokenForTenantMismatch() {
            this.shopper = null
            this.accessToken = null
            this.tokenExpiresAt = null
            this.tokenTenantHandle = null
            if (import.meta.client) {
                localStorage.removeItem(TOKEN_KEY)
                localStorage.removeItem(TOKEN_EXPIRES_KEY)
                localStorage.removeItem(SHOPPER_KEY)
                localStorage.removeItem(TOKEN_TENANT_KEY)
            }
        },

        persist(payload: AuthResponse) {
            this.shopper = payload.customer
            this.accessToken = payload.access_token
            this.tokenExpiresAt = payload.expires_at
            // Tag the persisted token with the tenant it was issued for so
            // the auth gate can detect cross-subdomain mismatches.
            const tenantStore = useTenantStore()
            this.tokenTenantHandle = tenantStore.activeHandle
            if (import.meta.client) {
                localStorage.setItem(TOKEN_KEY, payload.access_token)
                if (payload.expires_at) localStorage.setItem(TOKEN_EXPIRES_KEY, payload.expires_at)
                localStorage.setItem(SHOPPER_KEY, JSON.stringify(payload.customer))
                localStorage.setItem(TOKEN_TENANT_KEY, tenantStore.activeHandle)
            }
        },

        async register(payload: Record<string, any>) {
            this.loading = true
            this.error = null
            try {
                const { useShop } = await import('~/composables/useShop')
                const data = await useShop().auth.register(payload) as AuthResponse
                this.persist(data)
                return data
            } catch (e: any) {
                this.error = e?.data?.message || 'Registration failed.'
                throw e
            } finally {
                this.loading = false
            }
        },

        async login(email: string, password: string) {
            this.loading = true
            this.error = null
            try {
                const { useShop } = await import('~/composables/useShop')
                const data = await useShop().auth.login(email, password) as AuthResponse
                this.persist(data)
                return data
            } catch (e: any) {
                this.error = e?.data?.message || 'Invalid credentials.'
                throw e
            } finally {
                this.loading = false
            }
        },

        async refreshMe() {
            if (!this.accessToken) return
            try {
                const { useShop } = await import('~/composables/useShop')
                const res = await useShop().auth.me() as { data: Shopper }
                this.shopper = res.data
                if (import.meta.client) localStorage.setItem(SHOPPER_KEY, JSON.stringify(res.data))
            } catch (e: any) {
                if (e?.status === 401) this.logout()
            }
        },

        async logout() {
            try {
                if (this.accessToken) {
                    const { useShop } = await import('~/composables/useShop')
                    await useShop().auth.logout().catch(() => {})
                }
            } finally {
                this.shopper = null
                this.accessToken = null
                this.tokenExpiresAt = null
                this.tokenTenantHandle = null
                if (import.meta.client) {
                    localStorage.removeItem(TOKEN_KEY)
                    localStorage.removeItem(TOKEN_EXPIRES_KEY)
                    localStorage.removeItem(SHOPPER_KEY)
                    localStorage.removeItem(TOKEN_TENANT_KEY)
                }
            }
        },
    },
})
