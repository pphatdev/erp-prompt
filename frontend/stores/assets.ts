import { defineStore } from 'pinia'
import type { Asset, AuditCampaign } from '~/composables/useAssets'

/**
 * Shared Pinia state for the Assets module. Holds the currently-selected asset
 * (for the asset detail drawer / QR preview) and the active audit campaign
 * (so the scan modal can be opened from any page). All data fetching goes
 * through useAssets() — the store doesn't own a cache.
 */
interface AssetsState {
    activeAssetId: string | null
    activeCampaign: AuditCampaign | null
    scanQueue: Asset[]
}

export const useAssetsStore = defineStore('assets', {
    state: (): AssetsState => ({
        activeAssetId: null,
        activeCampaign: null,
        scanQueue: [],
    }),

    actions: {
        setActiveAsset(id: string | null) {
            this.activeAssetId = id
        },
        setActiveCampaign(campaign: AuditCampaign | null) {
            this.activeCampaign = campaign
        },
        enqueueScan(asset: Asset) {
            if (!this.scanQueue.some(a => a.id === asset.id)) {
                this.scanQueue.push(asset)
            }
        },
        clearScanQueue() {
            this.scanQueue = []
        },
    },
})
