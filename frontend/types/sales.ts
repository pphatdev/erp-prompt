/**
 * Hybrid Sales — types matching the backend Resources (camelCase wire format).
 * Source of truth: backend `App\Tenants\Modules\Sales\Resources\*`.
 */

export type ProductType = 'hardware' | 'software'

export type QuotationStatus = 'draft' | 'won' | 'lost'
export type OrderStatus = 'draft' | 'confirm' | 'cancel'
export type InvoiceStatus = 'new' | 'confirmed' | 'cancelled' | 'paid'
export type SubscriptionStatus = 'active' | 'expired' | 'cancelled'

export interface CustomerLite {
    id: string
    name: string
    email?: string
    company_name?: string
}

export type CustomerStatus = 'active' | 'inactive'
export type CustomerType = 'individual' | 'business' | 'tenant'
export type CustomerTier = 'standard' | 'premium' | 'enterprise'

export interface CustomerOrderRef {
    id: string
    orderNumber: string
    status: OrderStatus
    totalAmount: number
    createdAt: string
}

export interface CustomerLeadRef {
    id: string
    title: string
    status: string
    estimatedValue: number
}

export interface AccountManagerRef {
    id: string
    name: string
    email: string
}

export interface Customer {
    // Identity
    id: string
    name: string
    email: string
    phone: string | null
    companyName: string | null
    status: CustomerStatus

    // Classification
    customerType: CustomerType
    externalCode: string | null
    tier: CustomerTier

    // Business identifiers
    taxId: string | null
    industry: string | null
    website: string | null

    // Address (legacy free-form + structured)
    address: string | null
    billingCity: string | null
    billingState: string | null
    billingPostalCode: string | null
    billingCountry: string | null

    // Locale
    currency: string
    language: string
    timezone: string

    // Account ownership
    accountManagerId: string | null
    accountManager?: AccountManagerRef | null

    // Notes
    notes: string | null

    // Branding (seeds the customer's tenant Settings on provisioning)
    brandPrimaryColor: string | null   // RGB triple, e.g. "59 130 246"
    brandLogoUrl: string | null

    // Tenant linkage
    tenantHandle: string | null
    provisionedTenantId: string | null
    provisionedAt: string | null
    provisionedSubdomain: string | null  // e.g. "acme-corp.systemdomain.app"

    // Timestamps
    createdAt: string | null
    updatedAt: string | null

    /** Only populated by GET /customers/{id} */
    orders?: CustomerOrderRef[]
    leads?: CustomerLeadRef[]
}

export interface CreateCustomerPayload {
    // Identity
    name: string
    email: string
    phone?: string | null
    company_name?: string | null
    status?: CustomerStatus

    // Classification
    customer_type?: CustomerType
    external_code?: string | null
    tier?: CustomerTier

    // Business identifiers
    tax_id?: string | null
    industry?: string | null
    website?: string | null

    // Address
    address?: string | null
    billing_city?: string | null
    billing_state?: string | null
    billing_postal_code?: string | null
    billing_country?: string | null

    // Locale
    currency?: string
    language?: string
    timezone?: string

    // Account ownership
    account_manager_id?: string | null

    // Notes
    notes?: string | null

    // Branding
    brand_primary_color?: string | null
    brand_logo_url?: string | null

    // Tenant linkage (writable only when customer_type === 'tenant')
    tenant_handle?: string | null
}

export interface ProductVariant {
    id: string
    product_id: string
    sku: string
    name: string
    unit_price: number
    attributes: Record<string, unknown> | null
    is_active: boolean
}

export interface ProductLite {
    id: string
    sku: string
    name: string
    product_type: ProductType
    unit_price: number
    is_active: boolean
    /** Eager-loaded by GET /products. Empty array when product has no variants. */
    variants?: ProductVariant[]
}

// ───── Quotation ─────────────────────────────────────────────────────────────

export interface QuotationItem {
    id: string
    productId: string
    variantId: string | null
    productName: string
    productType: ProductType
    variantSku: string | null
    quantity: number
    unitPrice: number
    lineTotal: number
    dueDate: string | null
    notes: string | null
}

export interface Quotation {
    id: string
    quoteNumber: string
    customerId: string | null
    fromOpportunityId?: string | null
    customer?: CustomerLite
    status: QuotationStatus
    quoteDate: string | null
    validUntil: string | null
    dueDate: string | null
    subtotal: number
    taxAmount: number
    totalAmount: number
    notes: string | null
    lossReason: string | null
    wonAt: string | null
    lostAt: string | null
    items: QuotationItem[]
    orderId?: string | null
    createdAt: string
    updatedAt: string
}

export interface CreateQuotationItemPayload {
    product_id: string
    variant_id?: string | null
    quantity: number
    unit_price?: number | null
    due_date?: string | null
    notes?: string | null
}

export interface CreateQuotationPayload {
    /** One of customer_id or from_opportunity_id is required. */
    customer_id?: string | null
    from_opportunity_id?: string | null
    quote_date?: string
    valid_until?: string | null
    due_date?: string | null
    notes?: string | null
    items: CreateQuotationItemPayload[]
}

// ───── Order ─────────────────────────────────────────────────────────────────

export interface OrderItem {
    id: string
    productId: string | null
    variantId: string | null
    productName: string
    productType: ProductType | null
    variantSku: string | null
    quantity: number
    unitPrice: number
    total: number
    dueDate: string | null
    notes: string | null
}

export interface Order {
    id: string
    orderNumber: string
    quotationId: string | null
    customerId: string
    customer?: CustomerLite
    status: OrderStatus
    subtotal: number
    taxAmount: number
    totalAmount: number
    dueDate: string | null
    orderedAt: string | null
    confirmedAt: string | null
    cancelledAt: string | null
    cancelReason: string | null
    items: OrderItem[]
    invoiceId?: string | null
    subscriptionId?: string | null
    createdAt: string
}

// ───── Invoice ───────────────────────────────────────────────────────────────

export interface InvoiceItem {
    id: string
    orderItemId: string | null
    productId: string | null
    variantId: string | null
    productName: string
    productType: ProductType | null
    variantSku: string | null
    quantity: number
    unitPrice: number
    lineTotal: number
}

export interface Invoice {
    id: string
    invoiceNumber: string
    orderId: string
    customerId: string
    customer?: CustomerLite
    status: InvoiceStatus
    invoiceDate: string | null
    dueDate: string | null
    subtotal: number
    taxAmount: number
    totalAmount: number
    paidAmount: number
    journalEntryId: string | null
    confirmedAt: string | null
    cancelledAt: string | null
    cancelReason: string | null
    items: InvoiceItem[]
    createdAt: string
}

// ───── Subscription ──────────────────────────────────────────────────────────

export type BillingCycle = 'monthly' | 'annual' | 'one_time'

export interface SubscriptionItem {
    id: string
    orderItemId: string | null
    productId: string | null
    variantId: string | null
    productName: string
    variantSku: string | null
    quantity: number
    unitPrice: number
    lineTotal: number
}

export interface Subscription {
    id: string
    subscriptionNumber: string
    orderId: string
    customerId: string
    customer?: CustomerLite
    status: SubscriptionStatus
    startDate: string | null
    endDate: string | null
    billingCycle: BillingCycle
    totalAmount: number
    provisionedTenantId: string | null
    provisionedAt: string | null
    /** Click-ready URL once provisioning completes. e.g. "https://acme.system.app" */
    liveAccessUrl: string | null
    /** Tenant subdomain handle — same as customer.tenantHandle. */
    tenantHandle: string | null
    cancelledAt: string | null
    cancelReason: string | null
    items: SubscriptionItem[]
    createdAt: string
}

export interface RenewSubscriptionPayload {
    cycle?: BillingCycle
}

export interface ChangeSubscriptionPlanPayload {
    product_id: string
    variant_id?: string | null
    /** Optional — defaults to the first existing line on the subscription. */
    target_product_id?: string | null
    action: 'upgrade' | 'downgrade'
}

// ───── Pagination envelope ───────────────────────────────────────────────────

export interface PaginatedResponse<T> {
    data: T[]
    pagination: {
        page: number
        limit: number
        total: number
        totalPages: number
    }
}
