/**
 * Storefront tenancy/session housekeeping.
 *
 * The whole `/shop/*` tree is browsable without login — guest shoppers
 * can hit the home, catalog, detail, cart, and checkout (via guest
 * checkout) directly. Pages that only make sense for a signed-in shopper
 * (e.g., `/shop/account`) render a "please log in" branch on their own
 * rather than being globally redirected.
 *
 * This middleware no longer redirects; it just keeps the local stores
 * coherent on every shop navigation:
 *   1. Resolves the active tenant from the request's subdomain.
 *   2. Hydrates the shop auth store from localStorage so the layout's
 *      Login/Logout chrome matches reality on first paint.
 *   3. Clears the persisted token if it was issued for a different
 *      tenant than the one the shopper is on now (cross-subdomain
 *      navigation) — the token would 401 on every API call anyway.
 *
 * SSR is off project-wide; auth lives in localStorage; we early-return
 * on the server.
 */
export default defineNuxtRouteMiddleware((to) => {
    if (!to.path.startsWith('/shop')) return
    if (import.meta.server) return

    const tenantStore = useTenantStore()
    tenantStore.initializeTenant()

    const shopAuth = useShopAuthStore()
    shopAuth.initFromStorage()

    if (
        shopAuth.accessToken
        && shopAuth.tokenTenantHandle
        && shopAuth.tokenTenantHandle !== tenantStore.activeHandle
    ) {
        shopAuth.clearTokenForTenantMismatch()
    }
})
