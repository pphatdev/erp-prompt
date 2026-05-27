import { useApi } from '~/composables/useApi'
import type {
    ChangeSubscriptionPlanPayload,
    CreateCustomerPayload,
    CreateQuotationItemPayload,
    CreateQuotationPayload,
    Customer,
    CustomerLite,
    Invoice,
    InvoiceStatus,
    Order,
    OrderStatus,
    PaginatedResponse,
    ProductLite,
    Quotation,
    QuotationItem,
    QuotationStatus,
    RenewSubscriptionPayload,
    Subscription,
    SubscriptionStatus,
} from '~/types/sales'

interface ListQuery {
    page?: number
    limit?: number
    status?: string
    customer_id?: string
}

const buildQuery = (q: ListQuery = {}): string => {
    const params = new URLSearchParams()
    for (const [k, v] of Object.entries(q)) {
        if (v !== undefined && v !== null && v !== '') params.set(k, String(v))
    }
    const qs = params.toString()
    return qs ? `?${qs}` : ''
}

/**
 * Hybrid Sales — one composable, four namespaces.
 *
 * Each method returns the raw $fetch response so callers can destructure
 * `data` / `pagination` as needed. Wrapping every error here would swallow
 * the 422 DomainException messages the backend surfaces on status
 * violations (e.g. "Order must be confirmed before invoicing") — let those
 * bubble for the page-level toast.
 */
export const useSales = () => {
    const api = useApi()

    // ───── Quotations ────────────────────────────────────────────────
    const quotations = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<Quotation>>(`quotations${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: Quotation }>(`quotations/${id}`),

        create: (body: CreateQuotationPayload) =>
            api.post<{ data: Quotation }>('quotations', body),

        destroy: (id: string) =>
            api.delete(`quotations/${id}`),

        addItem: (id: string, body: CreateQuotationItemPayload) =>
            api.post<{ data: QuotationItem }>(`quotations/${id}/items`, body),

        win: (id: string) =>
            api.post<{ data: Quotation }>(`quotations/${id}/win`),

        lose: (id: string, lossReason: string) =>
            api.post<{ data: Quotation }>(`quotations/${id}/lose`, { loss_reason: lossReason }),
    }

    // ───── Orders ────────────────────────────────────────────────────
    const orders = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<Order>>(`orders${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: Order }>(`orders/${id}`),

        confirm: (id: string) =>
            api.post<{ data: Order }>(`orders/${id}/confirm`),

        cancel: (id: string, reason?: string) =>
            api.post<{ data: Order }>(`orders/${id}/cancel`, { reason }),
    }

    // ───── Invoices ──────────────────────────────────────────────────
    const invoices = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<Invoice>>(`invoices${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: Invoice }>(`invoices/${id}`),

        confirm: (id: string) =>
            api.post<{ data: Invoice }>(`invoices/${id}/confirm`),

        cancel: (id: string, reason?: string) =>
            api.post<{ data: Invoice }>(`invoices/${id}/cancel`, { reason }),
    }

    // ───── Subscriptions ─────────────────────────────────────────────
    const subscriptions = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<Subscription>>(`subscriptions${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: Subscription }>(`subscriptions/${id}`),

        renew: (id: string, body: RenewSubscriptionPayload = {}) =>
            api.post<{ data: Subscription }>(`subscriptions/${id}/renew`, body),

        changePlan: (id: string, body: ChangeSubscriptionPlanPayload) =>
            api.post<{ data: Subscription }>(`subscriptions/${id}/change-plan`, body),

        cancel: (id: string, reason?: string) =>
            api.post<{ data: Subscription }>(`subscriptions/${id}/cancel`, { reason }),
    }

    // ───── Catalogue helpers (for the Quotation builder) ─────────────
    // GET /products returns variants eager-loaded, so the inline variant
    // dropdown reads from `product.variants` — no extra round trip.
    const catalogue = {
        listCustomers: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<CustomerLite>>(`customers${buildQuery({ limit: 100, ...q })}`),

        listProducts: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<ProductLite>>(`products${buildQuery({ limit: 100, ...q })}`),
    }

    // ───── Customers (full CRUD) ─────────────────────────────────────
    const customers = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<Customer>>(`customers${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: Customer }>(`customers/${id}`),

        create: (body: CreateCustomerPayload) =>
            api.post<{ data: Customer }>('customers', body),

        update: (id: string, body: Partial<CreateCustomerPayload>) =>
            api.put<{ data: Customer }>(`customers/${id}`, body),

        destroy: (id: string) =>
            api.delete(`customers/${id}`),

        checkHandle: (handle: string, ignoreId?: string) =>
            api.get<{ available: boolean }>(`customers/check-handle?handle=${encodeURIComponent(handle)}${ignoreId ? `&ignore_id=${ignoreId}` : ''}`),
    }

    return { quotations, orders, invoices, subscriptions, catalogue, customers }
}

/**
 * Shared status → variant mapping so the status badges stay consistent
 * across every list and detail page.
 */
export const statusBadgeVariant = (
    status: QuotationStatus | OrderStatus | InvoiceStatus | SubscriptionStatus
): 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'secondary' => {
    switch (status) {
        // shared
        case 'active':    return 'success'
        case 'paid':      return 'success'
        case 'expired':   return 'warning'
        case 'cancelled': return 'danger'
        // Quotation
        case 'draft':     return 'info'
        case 'won':       return 'success'
        case 'lost':      return 'danger'
        // Order
        case 'confirm':   return 'primary'
        case 'cancel':    return 'danger'
        // Invoice (unchanged)
        case 'new':       return 'info'
        case 'confirmed': return 'primary'
        default:          return 'secondary'
    }
}
