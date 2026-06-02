import { useApi } from '~/composables/useApi'

export interface EcomOrderAdmin {
    id: string
    orderNumber: string
    customerId: string | null
    salesOrderId: string | null
    invoiceId: string | null
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
    shippedAt: string | null
    deliveredAt: string | null
    cancelledAt: string | null
    cancelReason: string | null
    notes: string | null
    customer?: { id: string; email: string; firstName: string | null; lastName: string | null } | null
    items?: Array<{ id: string; productName: string; productSku: string; variantSku: string | null; quantity: number; unitPrice: number; lineTotal: number }>
    payments?: Array<{ id: string; provider: string; status: string; amount: number; gatewayFee: number; capturedAt: string | null }>
    refunds?: Array<{ id: string; refundNumber: string; status: string; amount: number; isPartial: boolean }>
}

export interface EcomRefundAdmin {
    id: string
    refundNumber: string
    orderId: string
    paymentId: string | null
    creditNoteId: string | null
    status: string
    isPartial: boolean
    amount: number
    currency: string
    reason: string | null
    rejectionReason: string | null
    providerRefundId: string | null
    requestedAt: string | null
    approvedAt: string | null
    rejectedAt: string | null
    completedAt: string | null
    items?: Array<{ id: string; orderItemId: string; quantity: number; lineTotal: number; restock: boolean }>
}

export interface EcomCustomerAdmin {
    id: string
    email: string
    firstName: string | null
    lastName: string | null
    phone: string | null
    isGuest: boolean
    isActive: boolean
    lastLoginAt: string | null
    createdAt: string | null
    orderCount?: number
    addresses?: any[]
}

export interface PaginatedResponse<T> {
    data: T[]
    pagination: { page: number; limit: number; total: number; totalPages: number }
}

const buildQuery = (q: Record<string, any> = {}): string => {
    const params = new URLSearchParams()
    for (const [k, v] of Object.entries(q)) {
        if (v !== undefined && v !== null && v !== '') params.set(k, String(v))
    }
    const qs = params.toString()
    return qs ? `?${qs}` : ''
}

export const useEcommerce = () => {
    const api = useApi()

    const orders = {
        list: (q: { status?: string; customer_id?: string; search?: string; page?: number; limit?: number } = {}) =>
            api.get<PaginatedResponse<EcomOrderAdmin>>(`ecommerce/orders${buildQuery(q)}`),
        show: (id: string) => api.get<{ data: EcomOrderAdmin }>(`ecommerce/orders/${id}`),
        markFulfilling: (id: string) => api.post<{ data: EcomOrderAdmin }>(`ecommerce/orders/${id}/fulfilling`),
        ship: (id: string, carrier: string, trackingNumber: string) =>
            api.post<{ data: EcomOrderAdmin }>(`ecommerce/orders/${id}/ship`, { carrier, tracking_number: trackingNumber }),
        markDelivered: (id: string) => api.post<{ data: EcomOrderAdmin }>(`ecommerce/orders/${id}/delivered`),
        cancel: (id: string, reason?: string) =>
            api.post<{ data: EcomOrderAdmin }>(`ecommerce/orders/${id}/cancel`, { reason }),
    }

    const refunds = {
        list: (q: { status?: string; order_id?: string; page?: number; limit?: number } = {}) =>
            api.get<PaginatedResponse<EcomRefundAdmin>>(`ecommerce/refunds${buildQuery(q)}`),
        show: (id: string) => api.get<{ data: EcomRefundAdmin }>(`ecommerce/refunds/${id}`),
        create: (body: { order_id: string; reason?: string; items: Array<{ order_item_id: string; quantity: number; restock?: boolean }> }) =>
            api.post<{ data: EcomRefundAdmin }>('ecommerce/refunds', body),
        approve: (id: string, providerRefundId?: string) =>
            api.post<{ data: EcomRefundAdmin }>(`ecommerce/refunds/${id}/approve`, { provider_refund_id: providerRefundId }),
        reject: (id: string, reason: string) =>
            api.post<{ data: EcomRefundAdmin }>(`ecommerce/refunds/${id}/reject`, { reason }),
    }

    const customers = {
        list: (q: { search?: string; exclude_guests?: boolean; page?: number; limit?: number } = {}) =>
            api.get<PaginatedResponse<EcomCustomerAdmin>>(`ecommerce/customers${buildQuery(q)}`),
        show: (id: string) => api.get<{ data: EcomCustomerAdmin }>(`ecommerce/customers/${id}`),
    }

    const statusBadgeVariant = (status: string): string => {
        switch (status) {
            case 'pending_payment': return 'soft-warning'
            case 'paid': return 'soft-primary'
            case 'fulfilling': return 'soft-info'
            case 'shipped': return 'soft-info'
            case 'delivered': return 'soft-success'
            case 'cancelled': return 'soft-danger'
            case 'refunded': return 'soft-secondary'
            case 'requested': return 'soft-warning'
            case 'approved': return 'soft-info'
            case 'processing': return 'soft-info'
            case 'completed': return 'soft-success'
            case 'rejected': return 'soft-danger'
            default: return 'soft-secondary'
        }
    }

    return { orders, refunds, customers, statusBadgeVariant }
}
