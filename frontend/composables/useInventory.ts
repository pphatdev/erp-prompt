import { useApi } from '~/composables/useApi'
import type {
    Warehouse,
    WarehouseDetail,
    CreateWarehousePayload,
    Supplier,
    CreateSupplierPayload,
    PurchaseOrder,
    PurchaseOrderStatus,
    CreatePurchaseOrderPayload,
    ReceivePurchaseOrderPayload,
    InventoryProduct,
    InventoryProductVariant,
    Category,
    CreateCategoryPayload,
    ProductVariantPayload,
    PaginatedResponse,
} from '~/types/inventory'

interface ListQuery {
    page?: number
    limit?: number
    search?: string
    is_active?: boolean | string
    min_rating?: number
    status?: PurchaseOrderStatus | string
    supplier_id?: string
    warehouse_id?: string
    product_type?: string
    parent_id?: string | null
    category_id?: string | null
    tree?: boolean
    vendor_only?: boolean | string
}

const buildQuery = (q: ListQuery = {}): string => {
    const params = new URLSearchParams()
    for (const [k, v] of Object.entries(q)) {
        if (v === undefined || v === null || v === '') continue
        params.set(k, String(v))
    }
    const qs = params.toString()
    return qs ? `?${qs}` : ''
}

export const useInventory = () => {
    const api = useApi()

    const warehouses = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<Warehouse>>(`warehouses${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: WarehouseDetail }>(`warehouses/${id}`),

        create: (body: CreateWarehousePayload) =>
            api.post<{ data: Warehouse }>('warehouses', body),

        update: (id: string, body: Partial<CreateWarehousePayload>) =>
            api.put<{ data: Warehouse }>(`warehouses/${id}`, body),

        destroy: (id: string) =>
            api.delete(`warehouses/${id}`),
    }

    const suppliers = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<Supplier>>(`suppliers${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: Supplier }>(`suppliers/${id}`),

        create: (body: CreateSupplierPayload) =>
            api.post<{ data: Supplier }>('suppliers', body),

        update: (id: string, body: Partial<CreateSupplierPayload>) =>
            api.put<{ data: Supplier }>(`suppliers/${id}`, body),

        destroy: (id: string) =>
            api.delete(`suppliers/${id}`),
    }

    const purchaseOrders = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<PurchaseOrder>>(`purchase-orders${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: PurchaseOrder }>(`purchase-orders/${id}`),

        create: (body: CreatePurchaseOrderPayload) =>
            api.post<{ data: PurchaseOrder }>('purchase-orders', body),

        destroy: (id: string) =>
            api.delete(`purchase-orders/${id}`),

        submit: (id: string) =>
            api.post<{ data: PurchaseOrder }>(`purchase-orders/${id}/submit`),

        approve: (id: string) =>
            api.post<{ data: PurchaseOrder }>(`purchase-orders/${id}/approve`),

        receive: (id: string, body: ReceivePurchaseOrderPayload) =>
            api.post<{ data: PurchaseOrder }>(`purchase-orders/${id}/receive`, body),

        cancel: (id: string, reason?: string) =>
            api.post<{ data: PurchaseOrder }>(`purchase-orders/${id}/cancel`, { reason }),
    }

    const catalogue = {
        listProducts: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<InventoryProduct>>(`products${buildQuery({ limit: 100, ...q })}`),
    }

    const categories = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<Category> | { data: Category[] }>(`categories${buildQuery(q)}`),

        tree: () =>
            api.get<{ data: Category[] }>('categories?tree=1'),

        show: (id: string) =>
            api.get<{ data: Category }>(`categories/${id}`),

        create: (body: CreateCategoryPayload) =>
            api.post<{ data: Category }>('categories', body),

        update: (id: string, body: Partial<CreateCategoryPayload>) =>
            api.put<{ data: Category }>(`categories/${id}`, body),

        destroy: (id: string) =>
            api.delete(`categories/${id}`),
    }

    const productVariants = {
        list: (productId: string) =>
            api.get<{ data: InventoryProductVariant[] }>(`products/${productId}/variants`),

        create: (productId: string, body: ProductVariantPayload) =>
            api.post<{ data: InventoryProductVariant }>(`products/${productId}/variants`, body),

        update: (variantId: string, body: Partial<ProductVariantPayload>) =>
            api.put<{ data: InventoryProductVariant }>(`variants/${variantId}`, body),

        destroy: (variantId: string) =>
            api.delete(`variants/${variantId}`),
    }

    return { warehouses, suppliers, purchaseOrders, catalogue, categories, productVariants }
}

export const poStatusBadgeVariant = (
    status: PurchaseOrderStatus
): 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'secondary' => {
    switch (status) {
        case 'draft':      return 'info'
        case 'submitted':  return 'warning'
        case 'approved':   return 'primary'
        case 'receiving':  return 'warning'
        case 'received':   return 'success'
        case 'cancelled':  return 'danger'
        default:           return 'secondary'
    }
}
