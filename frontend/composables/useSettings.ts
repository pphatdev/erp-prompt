import { useApi } from '~/composables/useApi'

export type SettingGroup = 'branding' | 'locale' | 'notifications' | 'security' | 'numbering' | 'hrm' | 'pos' | 'calendar' | 'ecommerce' | 'fms' | 'platform' | 'general'
export type SettingType = 'string' | 'json' | 'boolean' | 'integer' | 'color' | 'url' | 'float'

export interface SettingRow {
    id: string
    key: string
    value: unknown
    group: SettingGroup
    type: SettingType
    label: string | null
    description: string | null
    isPublic: boolean
    updatedAt: string | null
}

export interface SettingPair { key: string; value: unknown }

export const useSettings = () => {
    const api = useApi()

    const list = (group?: SettingGroup) => {
        const qs = group ? `?group=${encodeURIComponent(group)}` : ''
        return api.get<{ data: SettingRow[] }>(`settings${qs}`)
    }

    const update = (pairs: SettingPair[]) =>
        api.put<{ data: SettingRow[] }>('settings', { settings: pairs })

    const publicSettings = () =>
        api.get<{ data: SettingRow[] }>('settings/public')

    /** Convenience: turn a SettingRow[] into a key->value map. */
    const toMap = (rows: SettingRow[]): Record<string, unknown> => {
        const out: Record<string, unknown> = {}
        for (const row of rows) out[row.key] = row.value
        return out
    }

    return { list, update, publicSettings, toMap }
}
