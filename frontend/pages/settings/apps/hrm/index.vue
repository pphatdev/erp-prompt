<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- ============================ Page header ========================== -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Human Resource</h1>
                    <p class="text-xs text-(--text-muted) mt-1">
                        Tenant-scoped HRM configuration for
                        <span class="text-(--color-primary) font-semibold">{{ tenantStore.activeName }}</span>.
                        Each tab persists changes independently.
                    </p>
                </div>
                <!-- Settings-tab toolbar (revert + save) only rendered while the
                     active tab is one of the 5 settings sections. Leave Types
                     and Prefix Code carry their own save controls. -->
                <div v-if="isSettingsTab" class="flex items-center gap-2">
                    <button class="btn text-xs"
                        :class="state.dirty.value ? 'text-(--text-body) border border-(--border-color) hover:bg-(--bg-muted)' : 'text-(--text-muted) cursor-not-allowed'"
                        :disabled="!state.dirty.value || state.saving.value" @click="state.reset">
                        <i class="ti ti-restore" /> Revert
                    </button>
                    <button class="btn btn-primary text-xs"
                        :disabled="!state.dirty.value || state.saving.value || !state.canSave.value"
                        @click="state.save">
                        <i :class="['ti', state.saving.value ? 'ti-loader-2 animate-spin' : 'ti-device-floppy']" />
                        {{ state.saving.value ? 'Saving...' : 'Save changes' }}
                    </button>
                </div>
            </header>

            <!-- ============================ Sidebar tabs + content ====================== -->
            <div class="flex flex-col lg:flex-row gap-4">
                <!-- Vertical rail — desktop -->
                <aside class="lg:w-60 lg:shrink-0">
                    <!-- Mobile: horizontal scroll strip -->
                    <nav class="lg:hidden glass-card rounded-xl px-2 py-1.5 flex items-center gap-1 overflow-x-auto">
                        <button v-for="t in tabs" :key="t.key" type="button" class="tab-pill"
                            :class="{ 'tab-pill-active': activeTab === t.key }" @click="setTab(t.key)">
                            <i :class="['ti', t.icon]" />
                            <span>{{ t.label }}</span>
                        </button>
                    </nav>
                    <!-- Desktop: vertical sidebar -->
                    <nav class="hidden lg:block glass-card rounded-2xl p-2 sticky top-4 space-y-1">
                        <button v-for="t in tabs" :key="t.key" type="button" class="tab-rail-item"
                            :class="{ 'tab-rail-item-active': activeTab === t.key }" @click="setTab(t.key)">
                            <i :class="['ti', t.icon, 'tab-rail-icon']" />
                            <span class="flex-1 text-left truncate">{{ t.label }}</span>
                            <i v-if="activeTab === t.key" class="ti ti-chevron-right text-base" />
                        </button>
                    </nav>
                </aside>

                <!-- Active tab pane -->
                <div class="flex-1 min-w-0 space-y-4">
                    <!-- Alert (settings tabs) -->
                    <div v-if="isSettingsTab && state.alert.msg"
                        class="px-4 py-3 rounded-lg flex items-center justify-between text-xs font-semibold"
                        :class="state.alert.type === 'success' ? 'badge-soft-success' : 'badge-soft-danger'">
                        <span class="flex items-center gap-2">
                            <i :class="['ti', state.alert.type === 'success' ? 'ti-check' : 'ti-alert-triangle']" />
                            {{ state.alert.msg }}
                        </span>
                        <button class="text-current" @click="state.alert.msg = ''"><i class="ti ti-x" /></button>
                    </div>

                    <!-- Loading (settings tabs) -->
                    <div v-if="isSettingsTab && state.loading.value" class="py-16 flex justify-center">
                        <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                    </div>

                    <template v-else>
                        <!-- Leave Types — own CRUD module -->
                        <LeaveTypesSection v-if="activeTab === 'leave-types'" />

                        <!-- Prefix Code — own save flow -->
                        <PrefixCodeMatrix v-else-if="activeTab === 'prefix-code'" heading="HRM Prefix Code"
                            :modules="prefixState.modules.value" :draft="prefixState.draft"
                            :loading="prefixState.loading.value" :saving="prefixState.saving.value"
                            :dirty="prefixState.dirty.value" :alert="prefixState.alert" :save="prefixState.save"
                            :reset="prefixState.reset" :build-example="prefixState.buildExample" />

                        <!-- Work Schedules — own snapshot + bulk-week save flow -->
                        <WorkSchedulesSection v-else-if="activeTab === 'work-schedules'" />

                        <!-- Workflow Statuses — own per-row CRUD, narrowed to hrm.* modules -->
                        <WorkflowStatusesSection v-else-if="activeTab === 'workflow-statuses'" />

                        <!-- Five settings tabs sharing useHrmSettings -->
                        <RecruitmentSection v-else-if="activeTab === 'recruitment'" :draft="state.draft" />
                        <LeaveSection v-else-if="activeTab === 'leave'" :draft="state.draft" />
                        <AttendanceSection v-else-if="activeTab === 'attendance'" :draft="state.draft" />
                        <PayrollSection v-else-if="activeTab === 'payroll'" :draft="state.draft" />
                        <PerformanceSection v-else-if="activeTab === 'performance'" :draft="state.draft" />
                    </template>
                </div>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PrefixCodeMatrix from '~/components/PrefixCodeMatrix.vue'
import AttendanceSection from '~/components/hrm-settings/AttendanceSection.vue'
import LeaveSection from '~/components/hrm-settings/LeaveSection.vue'
import LeaveTypesSection from '~/components/hrm-settings/LeaveTypesSection.vue'
import PayrollSection from '~/components/hrm-settings/PayrollSection.vue'
import PerformanceSection from '~/components/hrm-settings/PerformanceSection.vue'
import RecruitmentSection from '~/components/hrm-settings/RecruitmentSection.vue'
import WorkflowStatusesSection from '~/components/hrm-settings/WorkflowStatusesSection.vue'
import WorkSchedulesSection from '~/components/hrm-settings/WorkSchedulesSection.vue'
import { useHrmSettings } from '~/composables/useHrmSettings'
import { usePrefixCodes } from '~/composables/usePrefixCodes'
import { useTenantStore } from '~/stores/tenant'

definePageMeta({
    breadcrumb: 'Human Resource',
    // Layout renders "Home" as a static prefix to `breadcrumbItems`, so the
    // trail starts after it — final render: Home > App Management > Human Resource.
    breadcrumbTrail: [
        { label: 'App Management' },
        { label: 'Human Resource' },
    ],
})

const route = useRoute()
const router = useRouter()
const tenantStore = useTenantStore()

type TabKey = 'leave-types' | 'prefix-code' | 'work-schedules' | 'workflow-statuses' | 'recruitment' | 'leave' | 'attendance' | 'payroll' | 'performance'

const tabs: { key: TabKey; label: string; icon: string }[] = [
    { key: 'leave-types',       label: 'Leave Types',       icon: 'ti-list' },
    { key: 'prefix-code',       label: 'Prefix Code',       icon: 'ti-hash' },
    { key: 'work-schedules',    label: 'Work Schedules',    icon: 'ti-calendar-time' },
    { key: 'workflow-statuses', label: 'Workflow Statuses', icon: 'ti-list-tree' },
    { key: 'recruitment',       label: 'Recruitment',       icon: 'ti-user-plus' },
    { key: 'leave',             label: 'Leave',             icon: 'ti-calendar-event' },
    { key: 'attendance',        label: 'Attendance',        icon: 'ti-clock' },
    { key: 'payroll',           label: 'Payroll',           icon: 'ti-cash' },
    { key: 'performance',       label: 'Performance',       icon: 'ti-award' },
]

const validTabs = new Set<TabKey>(tabs.map(t => t.key))

const initialTab = (): TabKey => {
    const raw = route.query.tab
    if (typeof raw === 'string' && validTabs.has(raw as TabKey)) {
        return raw as TabKey
    }
    return 'leave-types'
}

const activeTab = ref<TabKey>(initialTab())

// Settings tabs are the five that share the hrm.* slice via useHrmSettings.
// Leave Types and Prefix Code drive their own save state.
const SETTINGS_TABS: TabKey[] = ['recruitment', 'leave', 'attendance', 'payroll', 'performance']
const isSettingsTab = computed(() => SETTINGS_TABS.includes(activeTab.value))

// One shared settings state across all five settings tabs — loaded once on
// the first time a settings tab activates, then reused across tab switches.
const state = useHrmSettings()
const settingsLoaded = ref(false)

const prefixState = usePrefixCodes(['hrm'])

const setTab = (key: TabKey) => {
    activeTab.value = key
    router.replace({ query: { ...route.query, tab: key } })
}

// Re-sync when the user navigates via browser back/forward buttons.
watch(() => route.query.tab, (q) => {
    if (typeof q === 'string' && validTabs.has(q as TabKey) && q !== activeTab.value) {
        activeTab.value = q as TabKey
    }
})

// Lazy-load the hrm.* slice the first time a settings tab activates so a
// user landing on Leave Types doesn't pay for a settings round-trip.
watch(activeTab, (tab) => {
    if (SETTINGS_TABS.includes(tab) && !settingsLoaded.value) {
        settingsLoaded.value = true
        state.load()
    }
}, { immediate: false })

onMounted(() => {
    if (SETTINGS_TABS.includes(activeTab.value)) {
        settingsLoaded.value = true
        state.load()
    }
})
</script>

<style scoped>
/* Desktop sidebar rail items. Match the main layout's rail look:
   left-aligned label, icon + active chevron, primary accent for active. */
.tab-rail-item {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    width: 100%;
    padding: 0.625rem 0.75rem;
    border-radius: 0.625rem;
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--text-muted);
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
    border: 1px solid transparent;
}

.tab-rail-item:hover {
    color: var(--text-heading);
    background: var(--bg-muted);
}

.tab-rail-item-active {
    color: var(--color-primary);
    background: var(--color-primary-subtle);
    border-color: rgba(var(--color-primary-rgb), 0.18);
}

.tab-rail-icon {
    font-size: 1rem;
    flex-shrink: 0;
}

/* Mobile horizontal pill strip (visible below `lg`). */
.tab-pill {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 0.875rem;
    border-radius: 0.625rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: var(--text-muted);
    white-space: nowrap;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}

.tab-pill:hover {
    color: var(--text-heading);
    background: var(--bg-muted);
}

.tab-pill-active {
    color: var(--color-primary);
    background: var(--color-primary-subtle);
}
</style>
