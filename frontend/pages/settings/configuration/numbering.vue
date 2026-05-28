<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Page header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Document Numbering Prefixes</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        Customize the leading text on auto-generated codes. Include any separator
                        (e.g. <span class="font-mono">TT-</span>). Changes only affect new records;
                        existing codes are not rewritten.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button class="btn text-xs"
                        :class="dirty ? 'text-(--text-body) border border-(--border-color) hover:bg-(--bg-muted)' : 'text-(--text-muted) cursor-not-allowed'"
                        :disabled="!dirty || saving" @click="reset">
                        <i class="ti ti-restore" /> Revert
                    </button>
                    <button class="btn btn-primary text-xs" :disabled="!dirty || saving" @click="save">
                        <i :class="['ti', saving ? 'ti-loader-2 animate-spin' : 'ti-device-floppy']" />
                        {{ saving ? 'Saving...' : 'Save changes' }}
                    </button>
                </div>
            </header>

            <!-- Alert -->
            <div v-if="alert.msg" class="px-4 py-3 rounded-lg flex items-center justify-between text-xs font-semibold"
                :class="alert.type === 'success' ? 'badge-soft-success' : 'badge-soft-danger'">
                <span class="flex items-center gap-2">
                    <i :class="['ti', alert.type === 'success' ? 'ti-check' : 'ti-alert-triangle']" />
                    {{ alert.msg }}
                </span>
                <button class="text-current" @click="alert.msg = ''"><i class="ti ti-x" /></button>
            </div>

            <div class="flex-1 min-w-0">
                <!-- Loading -->
                <div v-if="loading" class="py-16 flex justify-center">
                    <span
                        class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                </div>

                <!-- Numbering -->
                <section v-else class="glass-card rounded-2xl p-6 space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
                                Employee ID
                            </label>
                            <input v-model="draft['numbering.employee_id_prefix']" type="text" maxlength="16"
                                placeholder="TT-" class="form-control font-mono" />
                            <p class="text-xxs text-(--text-muted) mt-1">
                                Sequential. Example: <span class="font-mono">{{
                                    String(draft['numbering.employee_id_prefix'] || 'TT-') }}0001</span>
                            </p>
                        </div>
                        <div>
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
                                Candidate Code
                            </label>
                            <input v-model="draft['numbering.candidate_code_prefix']" type="text" maxlength="16"
                                placeholder="CAN-" class="form-control font-mono" />
                            <p class="text-xxs text-(--text-muted) mt-1">
                                Monthly sequence. Example: <span class="font-mono">{{
                                    String(draft['numbering.candidate_code_prefix'] || 'CAN-') }}202605-001</span>
                            </p>
                        </div>
                        <div>
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
                                Quotation Number
                            </label>
                            <input v-model="draft['numbering.quotation_prefix']" type="text" maxlength="16"
                                placeholder="QT-" class="form-control font-mono" />
                            <p class="text-xxs text-(--text-muted) mt-1">
                                Example: <span class="font-mono">{{ String(draft['numbering.quotation_prefix'] ||
                                    'QT-') }}20260527-ABCDEF</span>
                            </p>
                        </div>
                        <div>
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
                                Sales Order Number
                            </label>
                            <input v-model="draft['numbering.order_prefix']" type="text" maxlength="16"
                                placeholder="SO-" class="form-control font-mono" />
                            <p class="text-xxs text-(--text-muted) mt-1">
                                Example: <span class="font-mono">{{ String(draft['numbering.order_prefix'] || 'SO-')
                                    }}20260527-ABCDEF</span>
                            </p>
                        </div>
                        <div>
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
                                Invoice Number
                            </label>
                            <input v-model="draft['numbering.invoice_prefix']" type="text" maxlength="16"
                                placeholder="INV-" class="form-control font-mono" />
                            <p class="text-xxs text-(--text-muted) mt-1">
                                Example: <span class="font-mono">{{ String(draft['numbering.invoice_prefix'] ||
                                    'INV-') }}20260527-ABCDEF</span>
                            </p>
                        </div>
                        <div>
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
                                Subscription Number
                            </label>
                            <input v-model="draft['numbering.subscription_prefix']" type="text" maxlength="16"
                                placeholder="SUB-" class="form-control font-mono" />
                            <p class="text-xxs text-(--text-muted) mt-1">
                                Example: <span class="font-mono">{{ String(draft['numbering.subscription_prefix'] ||
                                    'SUB-') }}20260527-ABCDEF</span>
                            </p>
                        </div>
                        <div>
                            <label
                                class="block text-xxs font-bold text-(--text-muted) uppercase tracking-wide mb-1.5">
                                Purchase Order Number
                            </label>
                            <input v-model="draft['numbering.po_prefix']" type="text" maxlength="16"
                                placeholder="PO-" class="form-control font-mono" />
                            <p class="text-xxs text-(--text-muted) mt-1">
                                Example: <span class="font-mono">{{ String(draft['numbering.po_prefix'] || 'PO-')
                                    }}20260527-ABCDEF</span>
                            </p>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue'
import { useSettings, type SettingRow } from '~/composables/useSettings'
import { useTenantStore } from '~/stores/tenant'

const tenantStore = useTenantStore()
const settingsApi = useSettings()

definePageMeta({
    breadcrumb: 'Numbering Prefixes'
})

const loading = ref(true)
const saving = ref(false)
const alert = reactive({ msg: '', type: 'success' as 'success' | 'danger' })

const pristine = ref<Record<string, unknown>>({})
const draft = reactive<Record<string, unknown>>({})

const valuesEqual = (a: unknown, b: unknown) => JSON.stringify(a) === JSON.stringify(b)

const dirty = computed(() =>
    Object.keys(draft).some(k => !valuesEqual(draft[k], pristine.value[k]))
)

const hydrate = (rows: SettingRow[]) => {
    const map = settingsApi.toMap(rows)
    pristine.value = {}
    for (const k of Object.keys(draft)) delete draft[k]
    
    Object.keys(map).forEach(k => {
        if (k.startsWith('numbering.')) {
            pristine.value[k] = map[k]
            draft[k] = map[k]
        }
    })
}

const load = async () => {
    loading.value = true
    try {
        const { data } = await settingsApi.list()
        hydrate(data)
    } catch (err: any) {
        alert.msg = err?.data?.message || 'Failed to load settings'
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
            alert.msg = 'Numbering prefixes saved successfully'
            alert.type = 'success'
        }
    } catch (err: any) {
        alert.msg = err?.data?.message || 'Failed to save changes'
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

onMounted(() => {
    load()
})
</script>
