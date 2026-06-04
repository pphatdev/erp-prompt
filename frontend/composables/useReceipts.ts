import { useApi } from '~/composables/useApi'

export type ReceiptStatus = 'posted' | 'cancelled'

export interface ReceiptCustomerLite { id: string; name: string }
export interface ReceiptBankLite { id: string; name: string; bankName: string | null; currency: string | null }
export interface ReceiptArAccountLite { id: string; code: string; name: string; type: string }

export interface ReceiptInvoiceApplication {
    id: string
    receiptId: string
    invoiceId: string
    appliedAmount: number
    invoice?: {
        id: string
        invoiceNumber: string
        totalAmount: number
        paidAmount: number
        status: string
    } | null
}

export interface Receipt {
    id: string
    receiptNumber: string
    customerId: string
    customer?: ReceiptCustomerLite | null
    bankAccountId: string
    bankAccount?: ReceiptBankLite | null
    arAccountId: string
    arAccount?: ReceiptArAccountLite | null
    receivedOn: string | null
    amount: number
    currency: string | null
    paymentMethod: string | null
    referenceNumber: string | null
    status: ReceiptStatus
    isCancellable: boolean
    journalEntryId: string | null
    reversalJournalEntryId: string | null
    notes: string | null
    applications: ReceiptInvoiceApplication[]
    createdAt: string | null
    updatedAt: string | null
}

export interface OpenInvoice {
    id: string
    invoiceNumber: string
    status: string
    invoiceDate: string | null
    dueDate: string | null
    totalAmount: number
    paidAmount: number
    outstandingAmount: number
}

export interface ReceiptListFilters {
    search?: string
    status?: ReceiptStatus | ''
    customerId?: string
    bankAccountId?: string
    from?: string
    to?: string
    page?: number
    limit?: number
}

export interface ReceiptCreatePayload {
    receipt_number: string
    customer_id: string
    bank_account_id: string
    ar_account_id: string
    received_on: string
    amount: number
    currency?: string | null
    payment_method?: string | null
    reference_number?: string | null
    notes?: string | null
    applications: Array<{ invoice_id: string; applied_amount: number }>
}

interface Paginated<T> {
    data: T[]
    pagination: { page: number; limit: number; total: number; totalPages: number }
}

const buildQuery = (params: Record<string, unknown>): string => {
    const qs = new URLSearchParams()
    for (const [k, v] of Object.entries(params)) {
        if (v === undefined || v === null || v === '') continue
        // Backend uses snake_case query params.
        const key = k === 'customerId' ? 'customer_id'
            : k === 'bankAccountId' ? 'bank_account_id'
            : k
        qs.set(key, String(v))
    }
    const s = qs.toString()
    return s ? `?${s}` : ''
}

/**
 * Thin client for `/receipts` (FMS Phase 1 AR payments). The backend uses
 * the accountant's term "Receipt" — same domain object as the
 * `.task/fms/task.md` "Payment" spec (DR Bank / CR AR with multiple invoice
 * applications and cancel-via-JE-reversal).
 */
export const useReceipts = () => {
    const api = useApi()

    return {
        list: (filters: ReceiptListFilters = {}) =>
            api.get<Paginated<Receipt>>(`receipts${buildQuery(filters)}`),
        show: (id: string) => api.get<{ data: Receipt }>(`receipts/${id}`),
        record: (payload: ReceiptCreatePayload) =>
            api.post<{ data: Receipt }>('receipts', payload),
        cancel: (id: string) =>
            api.post<{ data: Receipt }>(`receipts/${id}/cancel`, {}),
        openInvoices: (customerId: string) =>
            api.get<{ data: OpenInvoice[] }>(`receipts/open-invoices/${customerId}`),
    }
}
