import { useApi } from './useApi'

export interface ApprovalLevel {
    id: string
    sequence: number
    approver_id: string | null
    approver_role: string | null
}

export interface ApprovalWorkflow {
    id: string
    name: string
    module: string
    is_active: boolean
    levels?: ApprovalLevel[]
}

export interface ApprovalHistory {
    id: string
    action: string
    comment: string | null
    created_at: string
    approver?: any // Expand based on User model
}

export interface ApprovalRequest {
    id: string
    workflow_id: string
    requester_id: string
    current_level_id: string | null
    requestable_type: string
    requestable_id: string
    status: 'pending' | 'approved' | 'rejected' | 'sent_back'
    created_at: string
    updated_at: string
    workflow?: ApprovalWorkflow
    requester?: any
    history?: ApprovalHistory[]
    requestable?: any
}

export const useApprovals = () => {
    const api = useApi()

    const getWorkflows = async (page = 1, limit = 15) => {
        return await api.get<{ data: ApprovalWorkflow[], pagination: any }>(`/approval-workflows?page=${page}&limit=${limit}`)
    }

    const getRequests = async (page = 1, limit = 15, asApprover = false) => {
        // If asApprover is true, fetch requests waiting for this user's approval
        // The backend would handle this via a query param like ?role=approver or just infer from auth
        // Assuming there is a query param role=approver
        const query = `page=${page}&limit=${limit}${asApprover ? '&role=approver' : ''}`
        return await api.get<{ data: ApprovalRequest[], pagination: any }>(`/approval-requests?${query}`)
    }

    const getRequest = async (id: string) => {
        return await api.get<{ data: ApprovalRequest }>(`/approval-requests/${id}`)
    }

    const processAction = async (requestId: string, action: 'approved' | 'rejected' | 'sent_back', comment?: string) => {
        return await api.post<{ data: ApprovalRequest }>(`/approval-requests/${requestId}/process`, {
            action,
            comment
        })
    }

    return {
        getWorkflows,
        getRequests,
        getRequest,
        processAction
    }
}
