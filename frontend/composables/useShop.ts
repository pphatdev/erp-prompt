import type { NitroFetchOptions, NitroFetchRequest } from 'nitropack'
import { useShopAuthStore } from '~/stores/shop-auth'
import { useTenantStore } from '~/stores/tenant'

type FetchOptions = NitroFetchOptions<NitroFetchRequest>

export interface PaginatedResponse<T> {
    data: T[]
    pagination: { page: number; limit: number; total: number; totalPages: number }
}

export interface StorefrontProduct {
    id: string
    sku: string
    name: string
    description?: string | null
    descriptionLong?: string | null
    productType?: string
    categoryId?: string | null
    categoryName?: string | null
    categorySlug?: string | null
    availableStock?: number
    inStock?: boolean
    /**
     * Set by `PublicCatalogController` when the request hits an aggregator
     * handle (demo, root). Each product carries the owning tenant so the
     * storefront can route detail clicks + show attribution.
     */
    tenantHandle?: string | null
    tenantName?: string | null
    createdAt?: string | null
    updatedAt?: string | null
    // Accept both shapes — PublicCatalogController returns camelCase
    // (unitPrice/imagePath) but earlier consumers read snake_case.
    unitPrice?: number
    imagePath?: string | null
    unit_price?: number
    image_path?: string | null
    is_active?: boolean
    variants?: Array<{
        id: string
        sku: string
        name?: string | null
        attributes?: Record<string, any> | null
        price?: number | null
    }>
}

export interface StorefrontAvailability {
    productId: string
    sku: string
    totalAvailable: number
    warehouseBreakdown: Array<{
        warehouseId: string
        warehouseCode: string
        warehouseName: string
        physicalStock: number
        reservedStock: number
        availableStock: number
    }>
}

export interface StorefrontCategory {
    id: string
    slug: string
    name: string
    color?: string | null
    parentId?: string | null
    productCount: number
}

export type CatalogSort = 'featured' | 'price_asc' | 'price_desc' | 'newest'

export interface CatalogListQuery {
    search?: string
    category_id?: string
    category_ids?: string[]
    min_price?: number
    max_price?: number
    in_stock?: 'all' | 'in' | 'out'
    sort?: CatalogSort
    page?: number
    limit?: number
}

export interface CartItem {
    id: string
    cartId: string
    productId: string
    variantId: string | null
    productName?: string
    productSku?: string
    productImage?: string | null
    variantSku?: string | null
    variantName?: string | null
    variantAttributes?: Record<string, any> | null
    quantity: number
    unitPrice: number
    lineTotal: number
    reservationExpiresAt?: string | null
}

export interface Cart {
    id: string
    customerId: string | null
    sessionToken: string | null
    status: string
    subtotal: number
    currency: string
    expiresAt: string | null
    items?: CartItem[]
    itemCount?: number
}

export interface EcomOrder {
    id: string
    orderNumber: string
    status: string
    subtotal: number
    taxAmount: number
    shippingAmount: number
    discountAmount: number
    totalAmount: number
    currency: string
    shippingAddress: Record<string, any> | null
    billingAddress: Record<string, any> | null
    carrier: string | null
    trackingNumber: string | null
    placedAt: string | null
    paidAt: string | null
    items?: Array<{ id: string; productName: string; productSku: string; variantSku: string | null; quantity: number; unitPrice: number; lineTotal: number }>
    payments?: any[]
    refunds?: any[]
}

/**
 * Storefront API. Uses a separate `Authorization` header bound to the
 * shopper's Passport token (issued against the `shop` guard) — NOT the
 * admin token in stores/auth. Guest carts ride the X-Cart-Session header.
 */
export const useShop = () => {
    const config = useRuntimeConfig()
    const tenantStore = useTenantStore()
    const shopAuth = useShopAuthStore()

    const request = async <T = any>(endpoint: string, options: FetchOptions = {}): Promise<T> => {
        const url = `${config.public.apiBase}/${endpoint.replace(/^\//, '')}`
        const headers: Record<string, string> = {
            Accept: 'application/json',
            'X-Tenant-Handle': tenantStore.activeHandle,
            ...((options.headers as Record<string, string>) || {}),
        }
        if (shopAuth.accessToken) headers['Authorization'] = `Bearer ${shopAuth.accessToken}`
        if (shopAuth.cartSessionToken) headers['X-Cart-Session'] = shopAuth.cartSessionToken

        try {
            return await $fetch<T>(url, { ...options, headers })
        } catch (err: any) {
            if (err?.status === 401 && shopAuth.accessToken) {
                shopAuth.logout()
            }
            throw err
        }
    }

    const get = <T = any>(u: string, o?: FetchOptions) => request<T>(u, { ...o, method: 'GET' })
    const post = <T = any>(u: string, body?: any, o?: FetchOptions) => request<T>(u, { ...o, method: 'POST', body })
    const put = <T = any>(u: string, body?: any, o?: FetchOptions) => request<T>(u, { ...o, method: 'PUT', body })
    const del = <T = any>(u: string, o?: FetchOptions) => request<T>(u, { ...o, method: 'DELETE' })

    // ───── Public catalog ─────
    const catalog = {
        list: (q: CatalogListQuery = {}) => {
            const qs = new URLSearchParams()
            const { category_ids, in_stock, ...rest } = q
            Object.entries(rest).forEach(([k, v]) => {
                if (v === undefined || v === null || v === '') return
                qs.set(k, String(v))
            })
            // Skip the all sentinel — backend already treats absence as "all".
            if (in_stock && in_stock !== 'all') {
                qs.set('in_stock', in_stock === 'in' ? 'true' : 'false')
            }
            // `category_ids[]=A&category_ids[]=B` — Laravel parses repeated
            // params with `[]` suffix into an array.
            if (Array.isArray(category_ids)) {
                category_ids.filter(Boolean).forEach(id => qs.append('category_ids[]', id))
            }
            const s = qs.toString()
            return get<PaginatedResponse<StorefrontProduct>>(`public/catalog${s ? `?${s}` : ''}`)
        },
        // `tenant` is required when the active tenant is an aggregator (demo /
        // root). The backend resolves the owning tenant and switches DB
        // context before reading the product. Ignored on tenant-local routes.
        show: (id: string, tenant?: string | null) => {
            const qs = tenant ? `?tenant=${encodeURIComponent(tenant)}` : ''
            return get<{ data: StorefrontProduct }>(`public/catalog/${id}${qs}`)
        },
        availability: (id: string, tenant?: string | null) => {
            const qs = tenant ? `?tenant=${encodeURIComponent(tenant)}` : ''
            return get<StorefrontAvailability>(`public/catalog/${id}/availability${qs}`)
        },
        categories: () => get<{ data: StorefrontCategory[] }>('public/catalog/categories'),
    }

    // ───── Cart ─────
    const cart = {
        show: () => get<{ data: Cart }>('shop/cart'),
        addItem: (body: { product_id: string; variant_id?: string | null; quantity?: number }) =>
            post<{ data: Cart }>('shop/cart/items', body),
        updateItem: (itemId: string, quantity: number) =>
            put<{ data: Cart }>(`shop/cart/items/${itemId}`, { quantity }),
        removeItem: (itemId: string) =>
            del<{ data: Cart }>(`shop/cart/items/${itemId}`),
    }

    // ───── Checkout ─────
    const checkout = {
        initiate: (body: {
            client_uuid: string
            provider: 'stripe' | 'aba' | 'wing' | 'manual'
            shipping_address_id?: string | null
            billing_address_id?: string | null
            guest_email?: string | null
        }) => post<{ order: EcomOrder; payment: any }>('shop/checkout/initiate', body),
        confirmDirect: (orderId: string, body: { charge_id: string; gateway_fee?: number }) =>
            post<{ data: EcomOrder }>(`shop/orders/${orderId}/confirm-direct`, body),
        cancel: (orderId: string, reason?: string) =>
            post<{ data: EcomOrder }>(`shop/orders/${orderId}/cancel`, { reason }),
    }

    // ───── Auth (shopper) ─────
    const auth = {
        register: (body: Record<string, any>) => post('shop/auth/register', body),
        login: (email: string, password: string) => post('shop/auth/login', { email, password }),
        me: () => get('shop/auth/me'),
        logout: () => post('shop/auth/logout'),
    }

    // ───── Addresses ─────
    const addresses = {
        list: () => get('shop/addresses'),
        create: (body: Record<string, any>) => post('shop/addresses', body),
        update: (id: string, body: Record<string, any>) => put(`shop/addresses/${id}`, body),
        destroy: (id: string) => del(`shop/addresses/${id}`),
    }

    // ───── Shopper Orders ─────
    const orders = {
        list: (q: { status?: string; page?: number; limit?: number } = {}) => {
            const qs = new URLSearchParams()
            Object.entries(q).forEach(([k, v]) => v !== undefined && v !== null && v !== '' && qs.set(k, String(v)))
            const s = qs.toString()
            return get<PaginatedResponse<EcomOrder>>(`shop/orders${s ? `?${s}` : ''}`)
        },
        show: (id: string) => get<{ data: EcomOrder }>(`shop/orders/${id}`),
    }

    return { catalog, cart, checkout, auth, addresses, orders }
}
