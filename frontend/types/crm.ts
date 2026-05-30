/**
 * CRM module TypeScript definitions.
 * Synchronized with the backend Eloquent resource formats.
 */

export type LeadStatus = 'new' | 'contacted' | 'qualified' | 'unqualified'
export type OpportunityStage =
    | 'new' | 'schedules' | 'contacted'
    | 'qualified' | 'proposal' | 'negotiation' // legacy intermediate stages
    | 'won' | 'lost'
export type ScheduleCadence = 'one_time' | 'monthly' | 'annual'
export type ActivityType = 'call' | 'email' | 'meeting' | 'note' | 'task'
export type ActivityStatus = 'pending' | 'completed' | 'cancelled'

export interface CustomerRef {
    id: string
    name: string
    email?: string
}

export type CustomerType = 'individual' | 'business' | 'tenant'

export interface Lead {
    id: string
    title: string | null
    firstName: string
    lastName: string
    fullName?: string
    email: string
    phone: string
    customerType: CustomerType
    address: string | null
    customerId: string | null
    customer?: CustomerRef | null
    estimatedValue: number | null
    status: LeadStatus
    source: string | null
    createdAt: string
    updatedAt: string
}

export interface Opportunity {
    id: string
    leadId: string | null
    customerId: string | null
    customer?: CustomerRef | null
    lead?: { id: string; title: string | null } | null
    title: string
    estimatedValue: number
    probability: number
    stage: OpportunityStage
    projectedCloseDate: string | null
    lossReason: string | null
    notes?: string | null
    createdAt: string
    updatedAt: string
}

export interface CrmContact {
    id: string
    customerId: string
    customer?: CustomerRef | null
    firstName: string
    lastName: string | null
    /** Server-side composed "First Last" (or fallback). Always present. */
    fullName?: string
    email: string | null
    phone: string | null
    jobTitle: string | null
    /** Primary contact for the linked Customer account. */
    isPrimary?: boolean
    createdAt: string
    updatedAt: string
}

export interface CrmActivity {
    id: string
    activityType: ActivityType
    subject: string
    description: string | null
    dueDate: string | null
    status: ActivityStatus
    actorId: string | null
    /** Populated when the eager-loaded `actor` relation is present (default on index). */
    actor?: { id: string; name: string } | null
    trackableType: string
    trackableId: string
    createdAt: string
    updatedAt: string
}

// ───── Payloads ──────────────────────────────────────────────────────────────

export interface CreateLeadPayload {
    first_name: string
    last_name: string
    email: string
    phone: string
    customer_type: CustomerType
    address: string
    title?: string | null
    customer_id?: string | null
    estimated_value?: number | null
    source?: string | null
}

/**
 * Customer creation is deferred to QuotationService::win on the Sales side,
 * so qualification just promotes the Lead to an Opportunity. Pass an
 * existing customer_id to link one, or leave it blank to create the
 * Opportunity without a Customer yet.
 */
export interface QualifyLeadPayload {
    customer_id?: string | null
    opportunity_title?: string
    estimated_value?: number
    probability?: number
    close_date?: string | null
    notes?: string | null
}

// ───── B2B Product Schedule ──────────────────────────────────────────────────

export interface OpportunityProductScheduleLine {
    id: string
    opportunityId: string
    productId: string
    variantId: string | null
    productName?: string
    variantSku?: string | null
    quantity: number
    estimatedUnitPrice: number
    cadence: ScheduleCadence
    notes: string | null
    createdAt: string | null
    updatedAt: string | null
}

export interface CreateProductScheduleLinePayload {
    product_id: string
    variant_id?: string | null
    quantity?: number
    estimated_unit_price?: number | null
    cadence?: ScheduleCadence
    notes?: string | null
}

// ───── Appointments (calendar / timeline) ────────────────────────────────────

export type AppointmentStatus = 'scheduled' | 'completed' | 'cancelled' | 'no_show'

export interface AppointmentAttendee {
    name: string
    email?: string | null
    role?: string | null
}

export interface CrmAppointment {
    id: string
    subject: string
    startsAt: string
    endsAt: string
    location: string | null
    attendees: AppointmentAttendee[]
    notes: string | null
    status: AppointmentStatus
    opportunityId: string | null
    leadId: string | null
    actorId: string | null
    opportunity?: { id: string; title: string } | null
    lead?: { id: string; title: string } | null
    actor?: { id: string; name: string } | null
    cancelReason: string | null
    completedAt: string | null
    cancelledAt: string | null
    createdAt: string | null
    updatedAt: string | null
}

export interface CreateAppointmentPayload {
    subject: string
    starts_at: string
    ends_at: string
    location?: string | null
    attendees?: AppointmentAttendee[]
    notes?: string | null
    opportunity_id?: string | null
    lead_id?: string | null
    actor_id?: string | null
}

export interface UpdateAppointmentPayload {
    subject?: string
    starts_at?: string
    ends_at?: string
    location?: string | null
    attendees?: AppointmentAttendee[]
    notes?: string | null
}

export interface UpdateProductScheduleLinePayload {
    variant_id?: string | null
    quantity?: number
    estimated_unit_price?: number | null
    cadence?: ScheduleCadence
    notes?: string | null
}

export interface CreateOpportunityPayload {
    title: string
    /** Optional — created at QuotationService::win if absent. */
    customerId?: string | null
    leadId?: string | null
    estimatedValue?: number | null
    probability?: number
    stage?: OpportunityStage
    projectedCloseDate?: string | null
}

export interface CreateContactPayload {
    customer_id: string
    first_name: string
    last_name?: string | null
    email?: string | null
    phone?: string | null
    job_title?: string | null
    is_primary?: boolean
}

export interface CreateActivityPayload {
    activity_type: ActivityType
    subject: string
    description?: string | null
    due_date?: string | null
    trackable_type: string
    trackable_id: string
}
