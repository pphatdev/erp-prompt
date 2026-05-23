import type { NitroFetchOptions, NitroFetchRequest } from 'nitropack'
import { useAuthStore } from '~/stores/auth'
import { useTenantStore } from '~/stores/tenant'

type FetchOptions = NitroFetchOptions<NitroFetchRequest>

export const useApi = () => {
    const config = useRuntimeConfig()
    const authStore = useAuthStore()
    const tenantStore = useTenantStore()

    const request = async <T = any>(
        endpoint: string,
        options: FetchOptions = {}
    ): Promise<T> => {
        const url = `${config.public.apiBase}/${endpoint.replace(/^\//, '')}`

        // Setup standard headers
        const headers: Record<string, string> = {
            'Accept': 'application/json',
            'X-Tenant-Handle': tenantStore.activeHandle,
            ...((options.headers as Record<string, string>) || {})
        }

        // Attach authorization bearer token if active
        if (authStore.accessToken) {
            headers['Authorization'] = `Bearer ${authStore.accessToken}`
        }

        try {
            return await $fetch<T>(url, {
                ...options,
                headers
            })
        } catch (err: any) {
            // Handle Token Expiry & Automatic Refresh Rotation
            if (err.status === 401 && authStore.accessToken) {
                console.warn('Unauthorized request - Attempting token rotation...')
                const refreshed = await authStore.rotateToken()
                if (refreshed) {
                    // Retry the original request with the fresh token
                    headers['Authorization'] = `Bearer ${authStore.accessToken}`
                    return await $fetch<T>(url, {
                        ...options,
                        headers
                    })
                }
            }

            // Handle Forbidden/Tenancy issues
            if (err.status === 403) {
                console.error('Access Forbidden - You do not have permission for this resource.')
            }

            throw err
        }
    }

    return {
        get: <T = any>(url: string, opts?: FetchOptions) => request<T>(url, { ...opts, method: 'GET' }),
        post: <T = any>(url: string, body?: any, opts?: FetchOptions) => request<T>(url, { ...opts, method: 'POST', body }),
        put: <T = any>(url: string, body?: any, opts?: FetchOptions) => request<T>(url, { ...opts, method: 'PUT', body }),
        patch: <T = any>(url: string, body?: any, opts?: FetchOptions) => request<T>(url, { ...opts, method: 'PATCH', body }),
        delete: <T = any>(url: string, opts?: FetchOptions) => request<T>(url, { ...opts, method: 'DELETE' }),
    }
}

