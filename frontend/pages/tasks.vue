<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Tasks canvas</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Premium GPU-accelerated visualisations — design.md §9.
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <button class="btn btn-ghost text-xs"><i class="ti ti-filter" />Filter</button>
                    <button class="btn btn-primary text-xs"><i class="ti ti-plus" />New task</button>
                </div>
            </header>

            <!-- §9.2 — Premium meteor sprint cards -->
            <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <MeteorCard v-for="sprint in sprints" :key="sprint.title" :variant="sprint.variant">
                    <div class="flex items-start justify-between gap-3 relative z-10">
                        <div>
                            <p class="text-xxs font-bold uppercase tracking-widest" :class="toneText[sprint.variant]">{{
                                sprint.tag }}</p>
                            <h3 class="mt-1 text-sm text-(--text-heading)">{{ sprint.title }}</h3>
                            <p class="text-xxs text-(--text-muted) mt-1 font-mono">{{ sprint.due }}</p>
                        </div>
                        <OrbitLoader :percent="sprint.percent" :size="64" />
                    </div>
                    <footer
                        class="mt-4 pt-3 border-t border-(--border-color) flex items-center justify-between text-xxs text-(--text-muted) relative z-10">
                        <span><i class="ti ti-users-group mr-1" />{{ sprint.team }}</span>
                        <span class="font-mono">{{ sprint.tasksDone }} / {{ sprint.tasksTotal }}</span>
                    </footer>
                </MeteorCard>
            </section>

            <!-- Body grid -->
            <section class="grid grid-cols-1 xl:grid-cols-3 gap-6">
                <!-- Priority list -->
                <div class="xl:col-span-2 glass-card rounded-2xl">
                    <header class="p-5 border-b border-(--border-color) flex items-center justify-between">
                        <h3 class="flex items-center gap-2">
                            <span class="w-1.5 h-4 rounded-sm bg-(--color-primary)" />
                            Active priority queue
                        </h3>
                        <Badge variant="danger">{{ overdueCount }} overdue</Badge>
                    </header>
                    <ul class="divide-y divide-(--border-color)">
                        <li v-for="t in tasks" :key="t.id"
                            class="px-5 py-4 hover:bg-(--bg-muted)/60 transition-colors flex items-center gap-4">
                            <RippleIndicator
                                :variant="t.priority === 'high' ? 'danger' : t.priority === 'medium' ? 'warning' : 'success'" />
                            <div class="flex-1 min-w-0">
                                <p class="text-xs font-semibold text-(--text-heading) truncate">{{ t.title }}</p>
                                <p class="text-xxs text-(--text-muted) truncate mt-0.5">
                                    <i class="ti ti-clock text-[10px] mr-1" />{{ t.due }} ·
                                    <i class="ti ti-user text-[10px] mx-1" />{{ t.assignee }}
                                </p>
                            </div>
                            <Badge :variant="t.statusVariant">{{ t.status }}</Badge>
                            <div class="hidden sm:flex w-28 bg-(--bg-muted) rounded-full overflow-hidden h-2">
                                <div class="h-full bg-linear-to-r from-(--color-primary) to-(--color-info)"
                                    :style="{ width: `${t.progress}%` }" />
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Kanban summary -->
                <div class="space-y-4">
                    <div v-for="col in board" :key="col.title" class="glass-card rounded-2xl p-5">
                        <header class="flex items-center justify-between mb-4">
                            <h4 class="flex items-center gap-2">
                                <span class="w-1.5 h-4 rounded-sm" :class="toneBg[col.tone]" />
                                {{ col.title }}
                            </h4>
                            <Badge :variant="col.tone">{{ col.items.length }}</Badge>
                        </header>
                        <ul class="space-y-2">
                            <li v-for="i in col.items" :key="i.title"
                                class="p-3 rounded-lg bg-(--bg-muted)/60 border border-(--border-color)">
                                <p class="text-xs font-semibold text-(--text-heading)">{{ i.title }}</p>
                                <p class="text-xxs text-(--text-muted) mt-1 flex items-center gap-1.5">
                                    <i class="ti ti-flag-3" />{{ i.tag }}
                                </p>
                            </li>
                        </ul>
                    </div>
                </div>
            </section>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue'

const toneText: Record<string, string> = {
    primary: 'text-(--color-primary)',
    success: 'text-(--color-success)',
    warning: 'text-(--color-warning)',
    danger: 'text-(--color-danger)',
    info: 'text-(--color-info)'
}
const toneBg: Record<string, string> = {
    primary: 'bg-(--color-primary)',
    success: 'bg-(--color-success)',
    warning: 'bg-(--color-warning)',
    danger: 'bg-(--color-danger)',
    info: 'bg-(--color-info)'
}

const sprints = [
    { title: 'Critical Release Sprint', tag: 'Engineering', due: 'Due today at 18:00', variant: 'danger', percent: 84, team: 'Core', tasksDone: 14, tasksTotal: 16 },
    { title: 'Tenant Migration Wave 3', tag: 'Platform', due: 'Due Friday 22:00', variant: 'primary', percent: 62, team: 'Platform', tasksDone: 9, tasksTotal: 14 },
    { title: 'Quarterly Audit Review', tag: 'Compliance', due: 'Closes in 12 days', variant: 'success', percent: 41, team: 'Audit', tasksDone: 7, tasksTotal: 17 }
] as const

interface Task {
    id: string
    title: string
    due: string
    assignee: string
    priority: 'high' | 'medium' | 'low'
    status: string
    statusVariant: 'primary' | 'success' | 'warning' | 'danger' | 'info'
    progress: number
}

const tasks = ref<Task[]>([
    { id: '1', title: 'Patch tenant scoping in Audit endpoint', due: 'Due today 18:00', assignee: 'Maya Chen', priority: 'high', status: 'In Review', statusVariant: 'warning', progress: 80 },
    { id: '2', title: 'Wire Tabler icon set across modules', due: 'Due tomorrow', assignee: 'Robert Vega', priority: 'medium', status: 'In Progress', statusVariant: 'primary', progress: 60 },
    { id: '3', title: 'Refactor Customer service to atomic txn', due: 'Due in 3 days', assignee: 'Lina Park', priority: 'high', status: 'Blocked', statusVariant: 'danger', progress: 35 },
    { id: '4', title: 'Publish v1.2 release notes', due: 'Due in 5 days', assignee: 'Sam Okafor', priority: 'low', status: 'Drafting', statusVariant: 'info', progress: 20 },
    { id: '5', title: 'Validate cross-tenant isolation suite', due: 'Due Friday', assignee: 'Aria Singh', priority: 'medium', status: 'In Progress', statusVariant: 'primary', progress: 50 },
    { id: '6', title: 'Confirm vendor SLA renewal', due: 'Completed', assignee: 'Henry Park', priority: 'low', status: 'Completed', statusVariant: 'success', progress: 100 }
])

const overdueCount = computed(() => tasks.value.filter(t => t.priority === 'high' && t.progress < 100).length)

const board = [
    {
        title: 'To do', tone: 'primary',
        items: [
            { title: 'Draft monthly audit report', tag: 'Compliance' },
            { title: 'Schedule client onboarding call', tag: 'Sales' }
        ]
    },
    {
        title: 'In progress', tone: 'warning',
        items: [
            { title: 'Multi-tenant migration', tag: 'Platform' },
            { title: 'Token rotation policy', tag: 'Security' }
        ]
    },
    {
        title: 'Completed', tone: 'success',
        items: [
            { title: 'Q3 finance close', tag: 'Finance' },
            { title: 'Vendor reconciliation', tag: 'Procurement' }
        ]
    }
] as const
</script>
