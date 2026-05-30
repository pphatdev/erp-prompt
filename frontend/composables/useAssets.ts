import { useApi } from './useApi'

/**
 * Domain types — mirror the camelCase contract on
 * backend/app/Tenants/Modules/Assets/Resources/*.php. Keep these in sync
 * with the PHP resources; pages narrow off these.
 */
export type AssetStatus = 'draft' | 'active' | 'retired'
export type AssetCondition = 'Excellent' | 'Good' | 'Fair' | 'Poor' | 'Damaged'
export type DepreciationMethod = 'straight_line' | 'declining_balance' | 'sum_of_years_digits'
export type AdjustmentType = 'surplus' | 'loss'
export type DisposalType = 'sale' | 'scrap' | 'writeoff'
export type GainLossType = 'gain' | 'loss' | 'none'
export type CampaignFrequency = 'annual' | 'biannual' | 'quarterly' | 'adhoc'
export type CampaignStatus = 'draft' | 'active' | 'completed' | 'cancelled'
export type ReconciliationStatus = 'matched' | 'moved' | 'damaged' | 'missing' | 'transferred'

export interface Asset {
    id: string
    assetCode: string
    serialNumber: string | null
    name: string
    description: string | null
    category: string | null
    vendorName: string | null
    purchaseDate: string | null
    purchasePrice: number
    salvageValue: number
    accumulatedDepreciation: number
    netBookValue: number
    usefulLifeMonths: number
    depreciationMethod: DepreciationMethod
    status: AssetStatus
    condition: AssetCondition
    qrCodeUrl: string | null
    notes: string | null
    custodianEmployeeId: string | null
    locationId: string | null
    depreciationLogs?: DepreciationLog[]
    revaluations?: RevaluationLog[]
    disposals?: DisposalLog[]
    createdAt: string | null
    updatedAt: string | null
}

export interface DepreciationLog {
    id: string
    assetId: string
    periodDate: string | null
    depreciationAmount: number
    accumulatedDepreciation: number
    bookValue: number
    method: DepreciationMethod | null
    journalEntryId: string | null
    createdAt: string | null
}

export interface DepreciationPreview {
    assetId: string
    amount: number
    method: DepreciationMethod
    accumulatedAfter: number
    netBookValueAfter: number
}

export interface RevaluationLog {
    id: string
    assetId: string
    appraisalDate: string | null
    previousValue: number
    appraisalValue: number
    adjustmentAmount: number
    adjustmentType: AdjustmentType
    appraiser: string | null
    notes: string | null
    journalEntryId: string | null
    createdAt: string | null
}

export interface DisposalLog {
    id: string
    assetId: string
    disposalDate: string | null
    disposalType: DisposalType
    salePrice: number
    finalNbv: number
    gainLoss: number
    gainLossType: GainLossType
    journalEntryId: string | null
    notes: string | null
    createdAt: string | null
}

export interface AuditCampaign {
    id: string
    name: string
    description: string | null
    frequency: CampaignFrequency
    startsAt: string | null
    endsAt: string | null
    status: CampaignStatus
    assignedTo: string | null
    expectedAssetCount: number | null
    startedAt: string | null
    completedAt: string | null
    reconciliation?: Reconciliation
    verifications?: VerificationLog[]
    createdAt: string | null
    updatedAt: string | null
}

export interface VerificationLog {
    id: string
    campaignId: string | null
    assetId: string
    verifiedBy: string | null
    verifiedAt: string | null
    previousCondition: AssetCondition | null
    newCondition: AssetCondition | null
    previousLocationId: string | null
    newLocationId: string | null
    reconciliationStatus: ReconciliationStatus
    notes: string | null
    createdAt: string | null
}

export interface Reconciliation {
    expected: number
    scanned: number
    matched: number
    moved: number
    damaged: number
    transferred: number
    missing: number
    remaining: number
    progress: number
}

export interface ScanProfile {
    asset: Asset
    activeCampaign: AuditCampaign | null
    alreadyScanned: boolean
    lastVerifiedAt: string | null
}

interface Paginated<T> {
    data: T[]
    pagination: { page: number; limit: number; total: number; totalPages: number }
}

const buildQuery = (params: Record<string, unknown>): string => {
    const entries = Object.entries(params)
        .filter(([, v]) => v !== '' && v != null)
        .map(([k, v]) => [k, String(v)] as [string, string])
    if (entries.length === 0) return ''
    return `?${new URLSearchParams(entries).toString()}`
}

export const useAssets = () => {
    const api = useApi()

    // Assets ----------------------------------------------------------------
    const getAssets = (params: Record<string, unknown> = {}) =>
        api.get<Paginated<Asset>>(`/assets${buildQuery(params)}`)

    const getAsset = (id: string) =>
        api.get<{ data: Asset }>(`/assets/${id}`)

    const createAsset = (payload: Record<string, unknown>) =>
        api.post<{ data: Asset }>('/assets', payload)

    const updateAsset = (id: string, payload: Record<string, unknown>) =>
        api.put<{ data: Asset }>(`/assets/${id}`, payload)

    const archiveAsset = (id: string) =>
        api.delete<void>(`/assets/${id}`)

    // Depreciation ----------------------------------------------------------
    const getDepreciationLogs = (params: Record<string, unknown> = {}) =>
        api.get<Paginated<DepreciationLog>>(`/assets/depreciation${buildQuery(params)}`)

    const previewDepreciation = (assetId: string) =>
        api.get<{ data: DepreciationPreview }>(`/assets/${assetId}/depreciation/preview`)

    const runDepreciation = (assetId: string, periodDate?: string) =>
        api.post<{ data: DepreciationLog }>(`/assets/${assetId}/depreciate`,
            periodDate ? { periodDate } : {})

    // Revaluation -----------------------------------------------------------
    const getRevaluations = (params: Record<string, unknown> = {}) =>
        api.get<Paginated<RevaluationLog>>(`/assets/revaluations${buildQuery(params)}`)

    const createRevaluation = (assetId: string, payload: Record<string, unknown>) =>
        api.post<{ data: RevaluationLog }>(`/assets/${assetId}/revaluations`, payload)

    // Disposal --------------------------------------------------------------
    const getDisposals = (params: Record<string, unknown> = {}) =>
        api.get<Paginated<DisposalLog>>(`/assets/disposals${buildQuery(params)}`)

    const createDisposal = (assetId: string, payload: Record<string, unknown>) =>
        api.post<{ data: DisposalLog }>(`/assets/${assetId}/disposals`, payload)

    // Audit Campaigns -------------------------------------------------------
    const getCampaigns = (params: Record<string, unknown> = {}) =>
        api.get<Paginated<AuditCampaign>>(`/asset-audit-campaigns${buildQuery(params)}`)

    const getCampaign = (id: string) =>
        api.get<{ data: AuditCampaign }>(`/asset-audit-campaigns/${id}`)

    const createCampaign = (payload: Record<string, unknown>) =>
        api.post<{ data: AuditCampaign }>('/asset-audit-campaigns', payload)

    const updateCampaign = (id: string, payload: Record<string, unknown>) =>
        api.put<{ data: AuditCampaign }>(`/asset-audit-campaigns/${id}`, payload)

    const deleteCampaign = (id: string) =>
        api.delete<void>(`/asset-audit-campaigns/${id}`)

    const startCampaign = (id: string) =>
        api.post<{ data: AuditCampaign }>(`/asset-audit-campaigns/${id}/start`, {})

    const completeCampaign = (id: string) =>
        api.post<{ data: AuditCampaign }>(`/asset-audit-campaigns/${id}/complete`, {})

    const getReconciliation = (id: string) =>
        api.get<{ data: Reconciliation }>(`/asset-audit-campaigns/${id}/reconciliation`)

    // Verifications ---------------------------------------------------------
    const getVerifications = (params: Record<string, unknown> = {}) =>
        api.get<Paginated<VerificationLog>>(`/assets/verifications${buildQuery(params)}`)

    const getScanProfile = (assetId: string) =>
        api.get<{ data: ScanProfile }>(`/assets/${assetId}/profile`)

    const recordVerification = (assetId: string, payload: Record<string, unknown>) =>
        api.post<{ data: VerificationLog }>(`/assets/${assetId}/verifications`, payload)

    return {
        // assets
        getAssets, getAsset, createAsset, updateAsset, archiveAsset,
        // depreciation
        getDepreciationLogs, previewDepreciation, runDepreciation,
        // revaluation
        getRevaluations, createRevaluation,
        // disposal
        getDisposals, createDisposal,
        // audit campaigns
        getCampaigns, getCampaign, createCampaign, updateCampaign, deleteCampaign,
        startCampaign, completeCampaign, getReconciliation,
        // verifications
        getVerifications, getScanProfile, recordVerification,
    }
}
