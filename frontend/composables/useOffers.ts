import { useApi } from '~/composables/useApi'

export type OfferStatus = 'draft' | 'sent' | 'accepted' | 'declined' | 'expired'

export interface OfferApplicationLite {
    id: string
    applicantName?: string | null
    applicantEmail?: string | null
    candidateCode?: string | null
    status?: string | null
    vacancy?: { id: string; title: string } | null
}

export interface OfferEmployeeLite {
    id: string
    employeeCode?: string | null
    fullName?: string | null
}

export interface OnboardingChecklistLite {
    id: string
    name: string
    status: string
    totalTasks: number
    completedTasks: number
    progressPercent: number
    targetCompletionDate: string | null
    completedAt: string | null
}

export interface Offer {
    id: string
    applicationId: string
    employeeId: string | null
    referenceNumber: string
    title: string
    effectiveDate: string | null
    expiresAt: string | null
    baseSalary: number | null
    signingBonus: number | null
    currency: string | null
    probationMonths: number | null
    status: OfferStatus
    esignProvider: string | null
    esignEnvelopeId: string | null
    sentAt: string | null
    signedAt: string | null
    declinedAt: string | null
    declineReason: string | null
    notes: string | null
    application?: OfferApplicationLite | null
    employee?: OfferEmployeeLite | null
    onboardingChecklist?: OnboardingChecklistLite | null
    createdAt: string | null
    updatedAt: string | null
}

export interface OfferPayload {
    applicationId?: string
    title?: string
    effectiveDate?: string | null
    expiresAt?: string | null
    baseSalary?: number | null
    signingBonus?: number | null
    currency?: string | null
    probationMonths?: number | null
    notes?: string | null
}

export interface OfferListQuery {
    page?: number
    limit?: number
    status?: OfferStatus | ''
    applicationId?: string
    employeeId?: string
}

interface PaginatedResponse<T> {
    data: T[]
    pagination?: { page: number; limit: number; total: number; totalPages: number }
}

const buildQuery = (q: Record<string, unknown> = {}): string => {
    const params = new URLSearchParams()
    for (const [k, v] of Object.entries(q)) {
        if (v === undefined || v === null || v === '') continue
        params.set(k, String(v))
    }
    const qs = params.toString()
    return qs ? `?${qs}` : ''
}

export const useOffers = () => {
    const api = useApi()

    return {
        list: (q: OfferListQuery = {}) =>
            api.get<PaginatedResponse<Offer>>(`offers${buildQuery(q as Record<string, unknown>)}`),

        show: (id: string) =>
            api.get<{ data: Offer }>(`offers/${id}`),

        create: (body: OfferPayload) =>
            api.post<{ data: Offer }>('offers', body),

        update: (id: string, body: OfferPayload) =>
            api.patch<{ data: Offer }>(`offers/${id}`, body),

        destroy: (id: string) =>
            api.delete(`offers/${id}`),

        send: (id: string, provider?: 'mock' | 'docusign') =>
            api.post<{ data: Offer }>(`offers/${id}/send`, provider ? { provider } : {}),

        accept: (id: string) =>
            api.post<{ data: Offer }>(`offers/${id}/accept`, {}),

        decline: (id: string, reason?: string) =>
            api.post<{ data: Offer }>(`offers/${id}/decline`, reason ? { reason } : {}),
    }
}

export const OFFER_STATUS_META: Record<OfferStatus, { label: string; variant: 'primary' | 'secondary' | 'success' | 'warning' | 'danger' | 'info'; icon: string }> = {
    draft:    { label: 'Draft',    variant: 'secondary', icon: 'ti-edit' },
    sent:     { label: 'Sent',     variant: 'info',      icon: 'ti-send' },
    accepted: { label: 'Accepted', variant: 'success',   icon: 'ti-check' },
    declined: { label: 'Declined', variant: 'danger',    icon: 'ti-x' },
    expired:  { label: 'Expired',  variant: 'warning',   icon: 'ti-clock-off' },
}
