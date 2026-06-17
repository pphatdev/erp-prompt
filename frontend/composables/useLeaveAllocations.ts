import { useApi } from '~/composables/useApi'

export interface LeaveAllocation {
    id: string
    employeeId: string
    leaveTypeId: string
    year: number
    allocatedDays: number
    usedDays: number
    pendingDays: number
    remainingDays: number
    note: string | null
    employee?: {
        id: string
        employeeId: string | null
        firstName: string | null
        lastName: string | null
    }
    leaveType?: {
        id: string
        name: string
        code: string | null
        annualAllowance: number
        isPaid: boolean
        isAccrued: boolean
    }
    createdAt: string | null
    updatedAt: string | null
}

export interface BalanceRow {
    leaveTypeId: string
    name: string
    code: string | null
    isPaid: boolean
    isAccrued: boolean
    annualAllowance: number
    allocated: number
    used: number
    pending: number
    remaining: number
    allocationId: string | null
}

interface Paginated<T> {
    data: T[]
    pagination: { page: number; limit: number; total: number; totalPages: number }
}

export interface AllocationUpsertPayload {
    employeeId: string
    leaveTypeId: string
    year: number
    allocatedDays: number
    note?: string | null
}

export interface AllocationAdjustPayload {
    allocatedDays: number
    note?: string | null
}

export const useLeaveAllocations = () => {
    const api = useApi()

    /**
     * List raw allocation rows. Use this for the bulk admin grid.
     */
    const list = (params: { employeeId?: string; leaveTypeId?: string; year?: number; limit?: number } = {}) => {
        const qs = new URLSearchParams()
        if (params.employeeId)  qs.set('employeeId',  params.employeeId)
        if (params.leaveTypeId) qs.set('leaveTypeId', params.leaveTypeId)
        if (params.year)        qs.set('year',        String(params.year))
        qs.set('limit', String(params.limit ?? 100))
        return api.get<Paginated<LeaveAllocation>>(`leave-allocations?${qs.toString()}`)
    }

    /**
     * Per-employee aggregate balance sheet. Returns one row per leave
     * type (even types with no allocation row) so the right pane of
     * the master/detail page always renders every type.
     */
    const balanceSheet = (employeeId: string, year?: number) => {
        const qs = year ? `?year=${year}` : ''
        return api.get<{ data: BalanceRow[]; meta: { year: number; employeeId: string } }>(
            `employees/${employeeId}/leave-allocations${qs}`
        )
    }

    const create = (payload: AllocationUpsertPayload) =>
        api.post<{ data: LeaveAllocation }>('leave-allocations', {
            employee_id:    payload.employeeId,
            leave_type_id:  payload.leaveTypeId,
            year:           payload.year,
            allocated_days: payload.allocatedDays,
            note:           payload.note ?? null,
        })

    const adjust = (id: string, payload: AllocationAdjustPayload) =>
        api.put<{ data: LeaveAllocation }>(`leave-allocations/${id}`, {
            allocated_days: payload.allocatedDays,
            note:           payload.note ?? null,
        })

    const remove = (id: string) =>
        api.delete<{ message: string }>(`leave-allocations/${id}`)

    return { list, balanceSheet, create, adjust, remove }
}
