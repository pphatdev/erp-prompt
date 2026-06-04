import { computed, reactive, ref } from 'vue'
import { useSettings, type SettingRow } from '~/composables/useSettings'

/**
 * Shared state container for every page under /settings/apps/hrm/{...}.
 *
 * Each sub-page (recruitment, leave, attendance, payroll, performance) loads
 * the full `hrm.*` slice but binds only the fields it owns. Save submits the
 * dirty diff only — untouched fields stay untouched on the server.
 *
 * Not a singleton: instances are page-scoped, so navigating away and back
 * triggers a fresh load. That keeps stale state from leaking between sections.
 */
export function useHrmSettings() {
    const settingsApi = useSettings()

    const loading = ref(true)
    const saving = ref(false)
    const canSave = ref(true)
    const alert = reactive({ msg: '', type: 'success' as 'success' | 'danger' })

    const pristine = ref<Record<string, unknown>>({})
    const draft = reactive<Record<string, unknown>>({})

    const valuesEqual = (a: unknown, b: unknown) => JSON.stringify(a) === JSON.stringify(b)

    const dirty = computed(() =>
        Object.keys(draft).some(k => !valuesEqual(draft[k], pristine.value[k]))
    )

    const HRM_KEY_PREFIX = 'hrm.'

    const hydrate = (rows: SettingRow[]) => {
        const map: Record<string, unknown> = {}
        for (const row of rows) map[row.key] = row.value

        pristine.value = {}
        for (const k of Object.keys(draft)) delete draft[k]

        Object.keys(map).forEach(k => {
            if (k.startsWith(HRM_KEY_PREFIX)) {
                pristine.value[k] = map[k]
                draft[k] = map[k]
            }
        })
    }

    const load = async () => {
        loading.value = true
        try {
            const { data } = await settingsApi.list('hrm')
            hydrate(data)
        } catch (err: any) {
            if (err?.status === 403 || err?.response?.status === 403) {
                alert.msg = 'You do not have permission to view HRM settings (settings.read required).'
                canSave.value = false
            } else {
                alert.msg = err?.data?.message || 'Failed to load HRM settings'
            }
            alert.type = 'danger'
        } finally {
            loading.value = false
        }
    }

    const save = async () => {
        if (!dirty.value) return
        saving.value = true
        try {
            const changed = Object.keys(draft)
                .filter(k => !valuesEqual(draft[k], pristine.value[k]))
                .map(k => ({ key: k, value: draft[k] }))

            if (changed.length) {
                const { data } = await settingsApi.update(changed)
                hydrate(data)
                alert.msg = 'HRM settings saved successfully'
                alert.type = 'success'
            }
        } catch (err: any) {
            const validationErrors = err?.data?.errors
            if (validationErrors && typeof validationErrors === 'object') {
                const firstField = Object.keys(validationErrors)[0]
                const firstMsg = validationErrors[firstField]?.[0]
                alert.msg = firstMsg || 'Validation failed. Please review your changes.'
            } else {
                alert.msg = err?.data?.message || 'Failed to save HRM settings'
            }
            alert.type = 'danger'
        } finally {
            saving.value = false
        }
    }

    const reset = () => {
        for (const k of Object.keys(draft)) delete draft[k]
        Object.assign(draft, JSON.parse(JSON.stringify(pristine.value)))
        alert.msg = ''
    }

    return {
        loading,
        saving,
        canSave,
        alert,
        draft,
        dirty,
        load,
        save,
        reset,
    }
}
