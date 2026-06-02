import { useApi } from '~/composables/useApi'

export interface PosTerminal {
    id: string
    code: string
    name: string
    warehouseId: string
    warehouseCode?: string | null
    warehouseName?: string | null
    pettyCashAccountId: string | null
    pettyCashAccountCode?: string | null
    location: string | null
    status: 'active' | 'disabled'
    notes: string | null
    createdAt: string | null
}

export interface PosShift {
    id: string
    terminalId: string
    terminal?: PosTerminal | null
    cashierId: string
    cashierName?: string | null
    openedAt: string | null
    closedAt: string | null
    openingFloat: number
    expectedCash: number | null
    closingCash: number | null
    variance: number | null
    status: 'open' | 'closed' | 'variance_pending' | 'reconciled'
    reconciledBy: string | null
    reconciledAt: string | null
    varianceJournalEntryId: string | null
    notes: string | null
    orderCount?: number
    createdAt: string | null
    /**
     * Set true by PosShiftController::me when the returned shift belongs
     * to a different cashier and the actor is reading it via the
     * `pos.shift.approve` admin override. Cashier dashboards surface a
     * banner / badge so the supervisor knows they're acting on behalf
     * of someone else.
     */
    isOverride?: boolean
}

export interface PosOrderItem {
    id: string
    orderId: string
    productId: string
    variantId: string | null
    productName: string
    productSku: string
    variantSku: string | null
    quantity: number
    unitPrice: number
    discount: number
    taxAmount: number
    lineTotal: number
}

export interface PosPayment {
    id: string
    orderId: string
    paymentMethod: 'cash' | 'card' | 'wallet' | 'manual'
    amount: number
    tendered: number | null
    changeDue: number
    referenceNumber: string | null
    currency: string
}

export interface PosOrder {
    id: string
    orderNumber: string
    shiftId: string
    terminalId: string
    cashierId: string
    cashierName?: string | null
    clientUuid: string | null
    customerId: string | null
    customerName?: string | null
    subtotal: number
    discountTotal: number
    taxTotal: number
    grandTotal: number
    currency: string
    status: 'paid' | 'voided' | 'refunded'
    journalEntryId: string | null
    voidJournalEntryId: string | null
    placedAt: string | null
    voidedAt: string | null
    voidedBy: string | null
    voidReason: string | null
    notes: string | null
    items?: PosOrderItem[]
    payments?: PosPayment[]
    createdAt: string | null
}

export interface CheckoutItemPayload {
    product_id: string
    variant_id?: string | null
    quantity: number
    unit_price?: number | null
    discount?: number | null
    tax_amount?: number | null
}

export interface CheckoutPaymentPayload {
    payment_method: 'cash' | 'card' | 'wallet' | 'manual'
    amount: number
    tendered?: number | null
    reference_number?: string | null
}

export interface CheckoutPayload {
    shift_id: string
    client_uuid?: string | null
    customer_id?: string | null
    notes?: string | null
    items: CheckoutItemPayload[]
    payments: CheckoutPaymentPayload[]
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

export const usePos = () => {
    const api = useApi()

    const terminals = {
        list: (q: { status?: string; page?: number; limit?: number } = {}) =>
            api.get<PaginatedResponse<PosTerminal>>(`pos/terminals${buildQuery(q)}`),
        show: (id: string) => api.get<{ data: PosTerminal }>(`pos/terminals/${id}`),
        create: (body: Record<string, any>) => api.post<{ data: PosTerminal }>('pos/terminals', body),
        update: (id: string, body: Record<string, any>) => api.put<{ data: PosTerminal }>(`pos/terminals/${id}`, body),
        destroy: (id: string) => api.delete(`pos/terminals/${id}`),
    }

    const shifts = {
        list: (q: { status?: string; terminal_id?: string; cashier_id?: string; page?: number; limit?: number } = {}) =>
            api.get<PaginatedResponse<PosShift>>(`pos/shifts${buildQuery(q)}`),
        show: (id: string) => api.get<{ data: PosShift }>(`pos/shifts/${id}`),
        me: () => api.get<{ data: PosShift | null }>('pos/shifts/me'),
        open: (body: { terminal_id: string; opening_float: number }) =>
            api.post<{ data: PosShift }>('pos/shifts/open', body),
        close: (id: string, body: { closing_cash: number; notes?: string | null }) =>
            api.post<{ data: PosShift }>(`pos/shifts/${id}/close`, body),
        reconcile: (id: string, notes?: string | null) =>
            api.post<{ data: PosShift }>(`pos/shifts/${id}/reconcile`, { notes }),
    }

    const orders = {
        list: (q: { status?: string; shift_id?: string; terminal_id?: string; cashier_id?: string; page?: number; limit?: number } = {}) =>
            api.get<PaginatedResponse<PosOrder>>(`pos/orders${buildQuery(q)}`),
        show: (id: string) => api.get<{ data: PosOrder }>(`pos/orders/${id}`),
        checkout: (body: CheckoutPayload) => api.post<{ data: PosOrder }>('pos/orders', body),
        void: (id: string, reason?: string) =>
            api.post<{ data: PosOrder }>(`pos/orders/${id}/void`, { reason }),
    }

    const statusBadgeVariant = (status: string): string => {
        switch (status) {
            case 'open': return 'soft-success'
            case 'closed': return 'soft-secondary'
            case 'variance_pending': return 'soft-warning'
            case 'reconciled': return 'soft-info'
            case 'paid': return 'soft-success'
            case 'voided': return 'soft-danger'
            case 'refunded': return 'soft-secondary'
            case 'active': return 'soft-success'
            case 'disabled': return 'soft-secondary'
            default: return 'soft-secondary'
        }
    }

    return { terminals, shifts, orders, statusBadgeVariant }
}
