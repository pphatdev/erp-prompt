import type { PaginatedResponse } from '~/types/sales'

export type { PaginatedResponse }

export interface ExchangeRate {
    id: string
    baseCurrency: string
    quoteCurrency: string
    pair: string
    rate: number
    effectiveDate: string | null
    source: string
    notes: string | null
    isActive: boolean
    createdAt: string | null
    updatedAt: string | null
}

export interface CreateExchangeRatePayload {
    base_currency: string
    quote_currency: string
    rate: number
    effective_date: string
    source?: string | null
    notes?: string | null
    is_active?: boolean
}

export interface ConvertResult {
    amount: number
    from: string
    to: string
    rate: number
    converted: number
    effectiveDate: string | null
    rateId: string | null
    inverse: boolean
}
