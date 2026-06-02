import { defineStore } from 'pinia'
import type { Cart } from '~/composables/useShop'

/**
 * Tiny shared state for the storefront cart badge.
 *
 * The layout's cart icon used to read its count from a local ref hydrated
 * once in `onMounted`; mutations on detail / cart / checkout pages didn't
 * bubble back up, so the badge went stale until a full reload. This store
 * lets any page that touches the cart push the new `itemCount` and have
 * the layout's badge re-render immediately.
 */
export const useCartStateStore = defineStore('cart-state', {
    state: () => ({
        count: 0,
        ready: false,
    }),

    actions: {
        /**
         * Apply a fresh Cart payload — the storefront API echoes the full
         * cart on every mutation, so this is what addItem/updateItem/
         * removeItem callers should hand off.
         */
        applyCart(cart: Cart | null | undefined) {
            const n = cart?.itemCount ?? cart?.items?.length ?? 0
            this.count = Number.isFinite(n) ? Number(n) : 0
            this.ready = true
        },

        /**
         * One-shot fetch — used by the layout on mount to seed the badge
         * before the shopper navigates anywhere. Swallows errors so a
         * 401/network blip doesn't spam the console; the badge just
         * stays at 0.
         */
        async refresh() {
            try {
                const res = await useShop().cart.show()
                this.applyCart(res?.data ?? null)
            } catch {
                this.count = 0
                this.ready = true
            }
        },

        reset() {
            this.count = 0
            this.ready = false
        },
    },
})
