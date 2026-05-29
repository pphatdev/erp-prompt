import { useApi } from './useApi'

/**
 * Fleet domain types — mirror the camelCase contract in
 * backend/app/Tenants/Modules/Fleet/Resources/*.php. Keep these in sync when
 * the resource shape changes; the page-level types narrow off these.
 */
export type VehicleStatus = 'active' | 'maintenance' | 'retired'

export interface Vehicle {
    id: string
    registrationNumber: string
    make: string
    model: string
    year: number
    vin: string | null
    status: VehicleStatus
    currentMileage: number
    imageUrl: string | null
    maintenanceLogs?: MaintenanceLog[]
    fuelLogs?: FuelLog[]
    createdAt: string | null
    updatedAt: string | null
}

export interface MaintenanceLog {
    id: string
    vehicleId: string
    serviceType: string
    serviceDate: string | null
    mileageAtService: number
    cost: number
    notes: string | null
    createdAt: string | null
    updatedAt: string | null
}

export interface VehicleModel {
    id: string
    make: string
    model: string
    bodyType: string | null
    fuelType: string | null
    notes: string | null
    createdAt: string | null
    updatedAt: string | null
}

export interface FuelLog {
    id: string
    vehicleId: string
    fillDate: string | null
    liters: number
    cost: number
    mileageAtFill: number
    driverId: string | null
    createdAt: string | null
    updatedAt: string | null
}

interface Paginated<T> {
    data: T[]
    pagination: { page: number; limit: number; total: number; totalPages: number }
}

/**
 * Bulk operation envelope — design.md §14.5. The three buckets let the
 * frontend render a granular toast on partial success.
 */
export interface BulkResult {
    deleted: number
    skipped: string[]
    missing: string[]
}

const buildQuery = (params: Record<string, unknown>): string => {
    const entries = Object.entries(params)
        .filter(([, v]) => v !== '' && v != null)
        .map(([k, v]) => [k, String(v)] as [string, string])
    if (entries.length === 0) return ''
    return `?${new URLSearchParams(entries).toString()}`
}

export const useFleet = () => {
    const api = useApi()

    // Vehicles --------------------------------------------------------------
    const getVehicles = (params: Record<string, unknown> = {}) =>
        api.get<Paginated<Vehicle>>(`/vehicles${buildQuery(params)}`)

    const getVehicle = (id: string) =>
        api.get<{ data: Vehicle }>(`/vehicles/${id}`)

    const createVehicle = (payload: Record<string, unknown>) =>
        api.post<{ data: Vehicle }>('/vehicles', payload)

    const updateVehicle = (id: string, payload: Record<string, unknown>) =>
        api.put<{ data: Vehicle }>(`/vehicles/${id}`, payload)

    const archiveVehicle = (id: string) =>
        api.delete<void>(`/vehicles/${id}`)

    // bulk-archive returns the { deleted, skipped, missing } envelope.
    const bulkArchiveVehicles = (ids: string[]) =>
        api.post<BulkResult>('/vehicles/bulk-archive', { ids })

    const uploadVehicleImage = (id: string, file: File) => {
        const fd = new FormData()
        fd.append('image', file)
        return api.post<{ data: Vehicle }>(`/vehicles/${id}/image`, fd)
    }

    const deleteVehicleImage = (id: string) =>
        api.delete<{ data: Vehicle }>(`/vehicles/${id}/image`)

    // Maintenance logs ------------------------------------------------------
    const getMaintenanceLogs = (params: Record<string, unknown> = {}) =>
        api.get<Paginated<MaintenanceLog>>(`/maintenance-logs${buildQuery(params)}`)

    const createMaintenanceLog = (payload: Record<string, unknown>) =>
        api.post<{ data: MaintenanceLog }>('/maintenance-logs', payload)

    const updateMaintenanceLog = (id: string, payload: Record<string, unknown>) =>
        api.put<{ data: MaintenanceLog }>(`/maintenance-logs/${id}`, payload)

    const deleteMaintenanceLog = (id: string) =>
        api.delete<void>(`/maintenance-logs/${id}`)

    // Fuel logs -------------------------------------------------------------
    const getFuelLogs = (params: Record<string, unknown> = {}) =>
        api.get<Paginated<FuelLog>>(`/fuel-logs${buildQuery(params)}`)

    const createFuelLog = (payload: Record<string, unknown>) =>
        api.post<{ data: FuelLog }>('/fuel-logs', payload)

    const updateFuelLog = (id: string, payload: Record<string, unknown>) =>
        api.put<{ data: FuelLog }>(`/fuel-logs/${id}`, payload)

    const deleteFuelLog = (id: string) =>
        api.delete<void>(`/fuel-logs/${id}`)

    // Vehicle Model catalog -------------------------------------------------
    const getVehicleModels = (params: Record<string, unknown> = {}) =>
        api.get<Paginated<VehicleModel>>(`/vehicle-models${buildQuery(params)}`)

    const createVehicleModel = (payload: Record<string, unknown>) =>
        api.post<{ data: VehicleModel }>('/vehicle-models', payload)

    const updateVehicleModel = (id: string, payload: Record<string, unknown>) =>
        api.put<{ data: VehicleModel }>(`/vehicle-models/${id}`, payload)

    const deleteVehicleModel = (id: string) =>
        api.delete<void>(`/vehicle-models/${id}`)

    return {
        getVehicles,
        getVehicle,
        createVehicle,
        updateVehicle,
        archiveVehicle,
        bulkArchiveVehicles,
        uploadVehicleImage,
        deleteVehicleImage,
        getMaintenanceLogs,
        createMaintenanceLog,
        updateMaintenanceLog,
        deleteMaintenanceLog,
        getFuelLogs,
        createFuelLog,
        updateFuelLog,
        deleteFuelLog,
        getVehicleModels,
        createVehicleModel,
        updateVehicleModel,
        deleteVehicleModel,
    }
}
