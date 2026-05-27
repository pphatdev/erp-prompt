import { useApi } from '~/composables/useApi'
import type {
    Lead,
    Opportunity,
    OpportunityProductScheduleLine,
    CrmContact,
    CrmActivity,
    CrmAppointment,
    AppointmentStatus,
    CreateLeadPayload,
    QualifyLeadPayload,
    CreateOpportunityPayload,
    CreateContactPayload,
    CreateActivityPayload,
    CreateProductScheduleLinePayload,
    UpdateProductScheduleLinePayload,
    CreateAppointmentPayload,
    UpdateAppointmentPayload,
    LeadStatus,
    OpportunityStage,
    ActivityType,
    ActivityStatus,
} from '~/types/crm'
import type { PaginatedResponse } from '~/types/sales'

interface ListQuery {
    page?: number
    limit?: number
    status?: string
    stage?: string
    customer_id?: string
    opportunity_id?: string
    lead_id?: string
    from?: string
    to?: string
}

/**
 * @description Helper function to build a URL query string from a ListQuery object.
 * @param { ListQuery } [q] Optional query parameter mapping dictionary
 * @returns { String } The formatted query string starting with '?' or an empty string
 */
const buildQuery = (q: ListQuery = {}): string => {
    const params = new URLSearchParams()
    for (const [k, v] of Object.entries(q)) {
        if (v !== undefined && v !== null && v !== '') params.set(k, String(v))
    }
    const qs = params.toString()
    return qs ? `?${qs}` : ''
}


/**
 * @description Composable for managing CRM API actions including leads, opportunities, contacts, and activities.
 * @returns { Object } CRM namespaces containing standard API helpers
 */
export const useCrm = () => {
    const api = useApi()

    // Leads API helpers
    const leads = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<Lead>>(`leads${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: Lead }>(`leads/${id}`),

        create: (body: CreateLeadPayload) =>
            api.post<{ data: Lead }>('leads', body),

        update: (id: string, body: Partial<CreateLeadPayload>) =>
            api.put<{ data: Lead }>(`leads/${id}`, body),

        destroy: (id: string) =>
            api.delete(`leads/${id}`),

        qualify: (id: string, body: QualifyLeadPayload) =>
            api.post<{ data: Lead }>(`leads/${id}/qualify`, body),
    }

    // Opportunities API helpers
    const opportunities = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<Opportunity>>(`opportunities${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: Opportunity }>(`opportunities/${id}`),

        create: (body: CreateOpportunityPayload) =>
            api.post<{ data: Opportunity }>('opportunities', body),

        update: (id: string, body: Partial<CreateOpportunityPayload>) =>
            api.put<{ data: Opportunity }>(`opportunities/${id}`, body),

        updateStage: (id: string, stage: OpportunityStage, lossReason?: string) =>
            api.patch<{ data: Opportunity }>(`opportunities/${id}/stage`, { stage, loss_reason: lossReason }),

        destroy: (id: string) =>
            api.delete(`opportunities/${id}`),

        // B2B Product Schedule — nested resource under an Opportunity.
        listSchedule: (opportunityId: string) =>
            api.get<{ data: OpportunityProductScheduleLine[] }>(`opportunities/${opportunityId}/product-schedule`),

        addScheduleLine: (opportunityId: string, body: CreateProductScheduleLinePayload) =>
            api.post<{ data: OpportunityProductScheduleLine }>(`opportunities/${opportunityId}/product-schedule`, body),

        updateScheduleLine: (opportunityId: string, lineId: string, body: UpdateProductScheduleLinePayload) =>
            api.patch<{ data: OpportunityProductScheduleLine }>(`opportunities/${opportunityId}/product-schedule/${lineId}`, body),

        removeScheduleLine: (opportunityId: string, lineId: string) =>
            api.delete(`opportunities/${opportunityId}/product-schedule/${lineId}`),
    }

    // Contacts API helpers
    const contacts = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<CrmContact>>(`crm-contacts${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: CrmContact }>(`crm-contacts/${id}`),

        create: (body: CreateContactPayload) =>
            api.post<{ data: CrmContact }>('crm-contacts', body),

        update: (id: string, body: Partial<CreateContactPayload>) =>
            api.put<{ data: CrmContact }>(`crm-contacts/${id}`, body),

        destroy: (id: string) =>
            api.delete(`crm-contacts/${id}`),
    }

    // Activities API helpers
    const activities = {
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<CrmActivity>>(`crm-activities${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: CrmActivity }>(`crm-activities/${id}`),

        create: (body: CreateActivityPayload) =>
            api.post<{ data: CrmActivity }>('crm-activities', body),

        complete: (id: string) =>
            api.post<{ data: CrmActivity }>(`crm-activities/${id}/complete`),

        destroy: (id: string) =>
            api.delete(`crm-activities/${id}`),
    }

    // Appointments — calendar / timeline
    const appointments = {
        /**
         * `from`+`to` switch to a window query (returns the full array, not
         * paginated) — used by the calendar view. Without them, returns a
         * standard paginated list.
         */
        list: (q: ListQuery = {}) =>
            api.get<PaginatedResponse<CrmAppointment> | { data: CrmAppointment[] }>(`crm-appointments${buildQuery(q)}`),

        show: (id: string) =>
            api.get<{ data: CrmAppointment }>(`crm-appointments/${id}`),

        create: (body: CreateAppointmentPayload) =>
            api.post<{ data: CrmAppointment }>('crm-appointments', body),

        update: (id: string, body: UpdateAppointmentPayload) =>
            api.put<{ data: CrmAppointment }>(`crm-appointments/${id}`, body),

        complete: (id: string) =>
            api.post<{ data: CrmAppointment }>(`crm-appointments/${id}/complete`),

        cancel: (id: string, reason?: string) =>
            api.post<{ data: CrmAppointment }>(`crm-appointments/${id}/cancel`, { reason }),

        markNoShow: (id: string) =>
            api.post<{ data: CrmAppointment }>(`crm-appointments/${id}/no-show`),

        destroy: (id: string) =>
            api.delete(`crm-appointments/${id}`),
    }

    return { leads, opportunities, contacts, activities, appointments }
}

/**
 * @description Maps CRM statuses and activity types to their corresponding PrimeVue/Tailwind color variant.
 * @param { LeadStatus | OpportunityStage | ActivityType | ActivityStatus } status Current CRM entity status or type
 * @returns { String } The mapped color variant code ('primary', 'success', 'warning', etc.)
 */
export const crmBadgeVariant = (
    status: LeadStatus | OpportunityStage | ActivityType | ActivityStatus | AppointmentStatus
): 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'secondary' => {
    switch (status) {
        case 'new':         return 'info'
        case 'contacted':   return 'warning'
        case 'qualified':   return 'success'
        case 'unqualified': return 'danger'

        // Opportunity stages
        case 'proposal':    return 'warning'
        case 'negotiation': return 'primary'
        case 'won':         return 'success'
        case 'lost':        return 'danger'

        // Activities
        case 'call':        return 'primary'
        case 'email':       return 'info'
        case 'meeting':     return 'warning'
        case 'note':        return 'secondary'
        case 'task':        return 'secondary'

        case 'pending':     return 'warning'
        case 'completed':   return 'success'
        case 'cancelled':   return 'danger'

        // Appointments
        case 'scheduled':   return 'info'
        case 'no_show':     return 'warning'

        default:            return 'secondary'
    }
}
