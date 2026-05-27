import { useApi } from '~/composables/useApi'
import type {
    ExchangeRate,
    CreateExchangeRatePayload,
    ConvertResult,
    PaginatedResponse,
} from '~/types/finance'

interface ListQuery {
    page?: number
    limit?: number
    base_currency?: string
    quote_currency?: string
    from?: string
    to?: string
    is_active?: boolean | string
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

export const useFinance = () => {
    const api = useApi()

    const exchangeRates = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<ExchangeRate>>(`exchange-rates${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: ExchangeRate }>(`exchange-rates/${id}`),

        create: (body: CreateExchangeRatePayload) =>
            api.post<{ data: ExchangeRate }>('exchange-rates', body),

        update: (id: string, body: Partial<CreateExchangeRatePayload>) =>
            api.put<{ data: ExchangeRate }>(`exchange-rates/${id}`, body),

        destroy: (id: string) =>
            api.delete(`exchange-rates/${id}`),

        latest: (base: string, quote: string, on?: string) =>
            api.get<{ data: ExchangeRate }>(
                `exchange-rates/latest?base_currency=${base}&quote_currency=${quote}${on ? `&on=${on}` : ''}`,
            ),

        convert: (amount: number, from: string, to: string, on?: string) =>
            api.get<{ data: ConvertResult }>(
                `exchange-rates/convert?amount=${amount}&from=${from}&to=${to}${on ? `&on=${on}` : ''}`,
            ),
    }

    return { exchangeRates }
}

// Display constants reused by the Exchange Rates UI.
export const COMMON_CURRENCIES: { code: string; label: string }[] = [
    { code: 'USD', label: 'US Dollar' },
    { code: 'KHR', label: 'Cambodian Riel' },
    { code: 'EUR', label: 'Euro' },
    { code: 'GBP', label: 'British Pound' },
    { code: 'JPY', label: 'Japanese Yen' },
    { code: 'CNY', label: 'Chinese Yuan' },
    { code: 'THB', label: 'Thai Baht' },
    { code: 'VND', label: 'Vietnamese Dong' },
    { code: 'SGD', label: 'Singapore Dollar' },
    { code: 'AUD', label: 'Australian Dollar' },
]
