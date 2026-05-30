import { computed, onMounted, reactive, ref } from 'vue'
import { useSettings, type SettingRow } from '~/composables/useSettings'

/**
 * Per-module prefix-code configuration shared across the Apps Management
 * settings pages (HRM, Sales, Inventory, Fixed Assets). Each module page
 * spins this composable with the keys it owns; the load/save/dirty pipeline
 * is identical across all four.
 *
 * Setting keys here must mirror what consumer services read on the backend:
 *   - numbering.employee_id_prefix     → RecruitmentService
 *   - numbering.candidate_code_prefix  → Application model boot
 *   - numbering.quotation_prefix       → QuotationService
 *   - numbering.order_prefix           → OrderService
 *   - numbering.invoice_prefix         → InvoiceService / SubscriptionService
 *   - numbering.subscription_prefix    → SubscriptionService
 *   - numbering.po_prefix              → ProcurementService
 *   - numbering.asset_code_prefix      → AssetService::nextAssetCode
 */
export type PrefixShape = 'datestamp' | 'monthly' | 'padded5' | 'padded4'

export interface PrefixEntry {
    key: string
    label: string
    placeholder: string
    fallback: string
    icon: string
    note: string
    shape: PrefixShape
}

export interface PrefixModule {
    id: string
    label: string
    entries: PrefixEntry[]
}

/**
 * Five logical subgroups under Apps Management, matching the user-facing layout:
 *   Human Resource · Sales · Inventory · Finance · System
 *
 * Invoice + Subscription live under Finance (they're financial documents even
 * though the backend service code lives under Modules\Sales). Asset Code lives
 * under System because Fixed Asset Management is system infrastructure.
 */
export const PREFIX_MODULES: Record<string, PrefixModule> = {
    hrm: {
        id: 'hrm',
        label: 'Human Resource',
        entries: [
            { key: 'numbering.employee_id_prefix',    label: 'Employee ID',    placeholder: 'TT-',  fallback: 'TT-',  icon: 'ti-user',       shape: 'padded4',   note: 'Sequential.' },
            { key: 'numbering.candidate_code_prefix', label: 'Candidate Code', placeholder: 'CAN-', fallback: 'CAN-', icon: 'ti-id-badge-2', shape: 'monthly',   note: 'Monthly sequence (YYYYMM-NNN).' },
        ],
    },
    sales: {
        id: 'sales',
        label: 'Sales',
        entries: [
            { key: 'numbering.quotation_prefix',      label: 'Quotation Number',   placeholder: 'QT-', fallback: 'QT-', icon: 'ti-file-text',     shape: 'datestamp', note: 'Date-stamped + random suffix.' },
            { key: 'numbering.order_prefix',          label: 'Sales Order Number', placeholder: 'SO-', fallback: 'SO-', icon: 'ti-shopping-cart', shape: 'datestamp', note: 'Date-stamped + random suffix.' },
        ],
    },
    inventory: {
        id: 'inventory',
        label: 'Inventory',
        entries: [
            { key: 'numbering.po_prefix',             label: 'Purchase Order',     placeholder: 'PO-', fallback: 'PO-', icon: 'ti-shopping-bag',  shape: 'datestamp', note: 'Date-stamped + random suffix.' },
        ],
    },
    finance: {
        id: 'finance',
        label: 'Finance',
        entries: [
            { key: 'numbering.invoice_prefix',        label: 'Invoice Number',      placeholder: 'INV-', fallback: 'INV-', icon: 'ti-receipt', shape: 'datestamp', note: 'Date-stamped + random suffix.' },
            { key: 'numbering.subscription_prefix',   label: 'Subscription Number', placeholder: 'SUB-', fallback: 'SUB-', icon: 'ti-cloud',   shape: 'datestamp', note: 'Date-stamped + random suffix.' },
        ],
    },
    system: {
        id: 'system',
        label: 'System',
        entries: [
            { key: 'numbering.asset_code_prefix',     label: 'Asset Code',          placeholder: 'AST-', fallback: 'AST-', icon: 'ti-cube',    shape: 'padded5',   note: 'Sequential, 5-digit zero-padded.' },
        ],
    },
}

const now = new Date()
const yyyymmdd = `${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}${String(now.getDate()).padStart(2, '0')}`
const yyyymm = `${now.getFullYear()}${String(now.getMonth() + 1).padStart(2, '0')}`

const valuesEqual = (a: unknown, b: unknown) => JSON.stringify(a) === JSON.stringify(b)

/**
 * Bind one or more PREFIX_MODULES into a reactive matrix + load/save pipeline.
 *
 * Each consuming page passes the ids it owns (`['hrm']`, `['sales']`, …) and
 * gets back a ready-to-render module list plus the standard
 * dirty/save/revert/alert state used elsewhere in settings pages.
 */
export const usePrefixCodes = (moduleIds: string[]) => {
    const settingsApi = useSettings()

    const modules = computed<PrefixModule[]>(() =>
        moduleIds
            .map(id => PREFIX_MODULES[id])
            .filter((m): m is PrefixModule => !!m),
    )

    const flatEntries = computed<PrefixEntry[]>(() =>
        modules.value.flatMap(m => m.entries),
    )

    const loading = ref(true)
    const saving = ref(false)
    const alert = reactive({ msg: '', type: 'success' as 'success' | 'danger' })

    const pristine = ref<Record<string, unknown>>({})
    const draft = reactive<Record<string, unknown>>({})

    const dirty = computed(() =>
        Object.keys(draft).some(k => !valuesEqual(draft[k], pristine.value[k])),
    )

    const buildExample = (entry: PrefixEntry): string => {
        const prefix = String(draft[entry.key] ?? entry.fallback)
        switch (entry.shape) {
            case 'datestamp': return `${prefix}${yyyymmdd}-ABCDEF`
            case 'monthly':   return `${prefix}${yyyymm}-001`
            case 'padded5':   return `${prefix}00001`
            case 'padded4':   return `${prefix}0001`
        }
    }

    const hydrate = (rows: SettingRow[]) => {
        const map = settingsApi.toMap(rows)
        pristine.value = {}
        for (const k of Object.keys(draft)) delete draft[k]

        for (const entry of flatEntries.value) {
            const value = map[entry.key] ?? entry.fallback
            pristine.value[entry.key] = value
            draft[entry.key] = value
        }
    }

    const load = async () => {
        loading.value = true
        try {
            const { data } = await settingsApi.list()
            hydrate(data)
        } catch (err: any) {
            alert.msg = err?.data?.message || 'Failed to load prefix code settings.'
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
                alert.msg = `Saved ${changed.length} prefix${changed.length === 1 ? '' : 'es'}.`
                alert.type = 'success'
            }
        } catch (err: any) {
            alert.msg = err?.data?.message || 'Failed to save changes.'
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

    onMounted(() => { load() })

    return {
        modules,
        draft,
        loading,
        saving,
        dirty,
        alert,
        save,
        reset,
        buildExample,
    }
}
