import type { PaginatedResponse } from '~/types/sales'

export type { PaginatedResponse }

export interface WarehouseManagerRef {
    id: string
    name: string
}

export interface Warehouse {
    id: string
    code: string
    name: string
    location: string | null
    managerId: string | null
    manager?: WarehouseManagerRef | null
    addressLine: string | null
    city: string | null
    country: string | null
    capacity: number | null
    isActive: boolean
    notes: string | null
    createdAt: string
    updatedAt: string
}

export interface WarehouseStockRow {
    productId: string
    name: string
    sku: string
    onHand: number
}

export interface WarehouseDetail extends Warehouse {
    onHand?: number
    inventory?: WarehouseStockRow[]
}

export interface CreateWarehousePayload {
    code: string
    name: string
    location?: string | null
    manager_id?: string | null
    address_line?: string | null
    city?: string | null
    country?: string | null
    capacity?: number | null
    is_active?: boolean
    notes?: string | null
}

export type SupplierPaymentMethod = 'bank_transfer' | 'cheque' | 'cash' | 'wire' | string

export interface Supplier {
    id: string
    code: string | null
    name: string
    contactName: string | null
    email: string | null
    phone: string | null
    address: string | null
    website: string | null
    taxId: string | null
    paymentTerms: string | null
    leadTimeDays: number | null
    rating: number | null
    isActive: boolean
    notes: string | null

    // AP / Vendor extension.
    isVendor: boolean
    paymentMethod: SupplierPaymentMethod | null
    bankName: string | null
    bankAccountName: string | null
    bankAccountNumber: string | null
    bankSwift: string | null
    defaultPayableAccountId: string | null
    defaultExpenseAccountId: string | null

    createdAt: string
    updatedAt: string
}

export interface CreateSupplierPayload {
    code?: string | null
    name: string
    contact_name?: string | null
    email?: string | null
    phone?: string | null
    address?: string | null
    website?: string | null
    tax_id?: string | null
    payment_terms?: string | null
    lead_time_days?: number | null
    rating?: number | null
    is_active?: boolean
    notes?: string | null

    // AP / Vendor extension.
    is_vendor?: boolean
    payment_method?: SupplierPaymentMethod | null
    bank_name?: string | null
    bank_account_name?: string | null
    bank_account_number?: string | null
    bank_swift?: string | null
    default_payable_account_id?: string | null
    default_expense_account_id?: string | null
}

export type PurchaseOrderStatus =
    | 'draft'
    | 'submitted'
    | 'approved'
    | 'receiving'
    | 'received'
    | 'cancelled'

export interface SupplierRef {
    id: string
    name: string
    code?: string | null
}

export interface WarehouseRef {
    id: string
    name: string
    code?: string | null
}

export interface PurchaseOrderItem {
    id: string
    productId: string
    variantId: string | null
    productName: string
    variantSku: string | null
    orderedQty: number
    receivedQty: number
    outstandingQty: number
    unitCost: number
    lineTotal: number
    notes: string | null
}

export interface PurchaseOrder {
    id: string
    poNumber: string
    supplierId: string
    warehouseId: string
    supplier?: SupplierRef | null
    warehouse?: WarehouseRef | null
    status: PurchaseOrderStatus
    orderDate: string | null
    expectedAt: string | null
    receivedAt: string | null
    subtotal: number
    taxAmount: number
    totalAmount: number
    notes: string | null
    orderedBy: string | null
    submittedAt: string | null
    approvedBy: string | null
    approvedAt: string | null
    cancelledAt: string | null
    cancelReason: string | null
    items: PurchaseOrderItem[]
    createdAt: string
    updatedAt: string
}

export interface CreatePurchaseOrderItemPayload {
    product_id: string
    variant_id?: string | null
    variant_sku?: string | null
    ordered_qty: number
    unit_cost?: number | null
    notes?: string | null
}

export interface CreatePurchaseOrderPayload {
    supplier_id: string
    warehouse_id: string
    order_date?: string | null
    expected_at?: string | null
    notes?: string | null
    items: CreatePurchaseOrderItemPayload[]
}

export interface ReceivePurchaseOrderItem {
    id: string
    qty: number
}

export interface ReceivePurchaseOrderPayload {
    items: ReceivePurchaseOrderItem[]
    notes?: string | null
}

export interface CategoryRef {
    id: string
    name: string
    slug: string
    color: string | null
}

export interface Category {
    id: string
    slug: string
    name: string
    description: string | null
    color: string | null
    sortOrder: number
    isActive: boolean
    parentId: string | null
    parent?: { id: string; name: string; slug: string } | null
    children?: Category[]
    productsCount?: number
    createdAt: string | null
    updatedAt: string | null
}

export interface CreateCategoryPayload {
    name: string
    slug?: string | null
    description?: string | null
    color?: string | null
    sort_order?: number | null
    is_active?: boolean
    parent_id?: string | null
}

export interface ProductVariantPayload {
    sku: string
    name: string
    unit_price?: number
    attributes?: Record<string, unknown> | null
    is_active?: boolean
}

export interface InventoryProductVariant {
    id: string
    product_id: string
    sku: string
    name: string
    unit_price: number
    attributes: Record<string, unknown> | null
    is_active: boolean
}

export interface InventoryProduct {
    id: string
    sku: string
    name: string
    product_type: 'hardware' | 'software'
    unit_price: number
    is_active: boolean
    current_stock?: number
    total_quantity?: number
    average_cost?: number
    last_cost?: number | null
    variants?: InventoryProductVariant[]
}
