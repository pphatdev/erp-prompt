import { defineStore } from 'pinia'

/**
 * Single telemetry sample for a vehicle. The Reverb broadcaster will push one
 * of these per heartbeat. The store keeps only the latest per vehicle —
 * historical playback lives in a separate query, not in client state.
 */
export interface TelemetryPoint {
    vehicleId: string
    lat: number
    lng: number
    speedKph: number
    headingDegrees: number | null
    capturedAt: string
}

interface FleetState {
    activeVehicleId: string | null
    telemetry: Record<string, TelemetryPoint>
}

/**
 * Lightweight Pinia store for shared Fleet UI state.
 *
 * Today it just tracks which vehicle the user has selected (for the map
 * overlay) and the most recent telemetry frame per vehicle. The real-time
 * subscription that fills `telemetry` is wired in a later phase — the shape
 * is fixed here so the Vehicles page and any map component can subscribe
 * without a refactor.
 */
export const useFleetStore = defineStore('fleet', {
    state: (): FleetState => ({
        activeVehicleId: null,
        telemetry: {},
    }),

    getters: {
        activeTelemetry: (state): TelemetryPoint | null => {
            if (!state.activeVehicleId) return null
            return state.telemetry[state.activeVehicleId] ?? null
        },
    },

    actions: {
        setActiveVehicle(id: string | null) {
            this.activeVehicleId = id
        },
        recordTelemetry(point: TelemetryPoint) {
            this.telemetry[point.vehicleId] = point
        },
        clearTelemetry(vehicleId?: string) {
            if (vehicleId) {
                delete this.telemetry[vehicleId]
            } else {
                this.telemetry = {}
            }
        },
    },
})
