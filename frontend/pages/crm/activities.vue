<template>
    <NuxtLayout name="default">
        <div class="space-y-6">
            <!-- Header -->
            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Interaction Timeline</h1>
                    <p class="text-xs text-(--text-muted) mt-1">Polymorphic log of sales activities, phone calls, meetings, emails, and follow-up tasks.</p>
                </div>
                <button type="button" class="btn btn-primary text-xs" @click="openCreateModal">
                    <i class="ti ti-plus" />Log Activity
                </button>
            </header>

            <!-- Filters -->
            <section class="glass-card rounded-xl p-4 flex flex-col md:flex-row gap-3 items-center justify-between">
                <div class="flex flex-wrap gap-2 items-center">
                    <span class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider mr-2">Filter Type:</span>
                    <div class="flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1 shrink-0">
                        <button v-for="t in (['all', 'call', 'email', 'meeting', 'note', 'task'] as const)" :key="t" type="button"
                            class="px-2.5 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
                            :class="filterType === t ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                            @click="filterType = t">{{ t }}</button>
                    </div>
                </div>
                <div class="flex items-center border border-(--border-color) rounded-lg bg-(--bg-muted) p-1 shrink-0">
                    <button v-for="s in (['all', 'pending', 'completed', 'cancelled'] as const)" :key="s" type="button"
                        class="px-3 py-1 rounded text-xxs uppercase tracking-widest font-bold transition-colors"
                        :class="filterStatus === s ? 'bg-(--bg-card) text-(--color-primary) shadow-(--shadow-sm)' : 'text-(--text-muted) hover:text-(--text-heading)'"
                        @click="filterStatus = s">{{ s }}</button>
                </div>
            </section>

            <!-- Loading -->
            <div v-if="loading" class="py-24 flex flex-col items-center gap-3">
                <span class="w-8 h-8 rounded-full border-2 border-(--color-primary)/20 border-t-(--color-primary) animate-spin" />
                <span class="text-xs text-(--text-muted)">Loading activities timeline...</span>
            </div>

            <!-- Empty -->
            <div v-else-if="filteredActivities.length === 0" class="glass-card rounded-2xl py-20 text-center">
                <i class="ti ti-notes-off text-4xl text-(--text-muted)" />
                <h4 class="text-sm font-semibold text-(--text-heading) mt-3">No activities recorded</h4>
                <p class="text-xs text-(--text-muted) mt-1">Add activities or log phone calls/meetings to see your timeline.</p>
            </div>

            <!-- Timeline -->
            <section v-else class="glass-card rounded-2xl p-6 relative max-w-4xl mx-auto">
                <div class="absolute left-9 top-6 bottom-6 w-0.5 bg-(--border-color)" />
                
                <div class="space-y-8 relative">
                    <article v-for="act in filteredActivities" :key="act.id" class="flex gap-6 items-start group">
                        
                        <!-- Polymorphic Icon -->
                        <div class="w-7 h-7 rounded-full flex items-center justify-center text-white z-10 shrink-0 shadow-(--shadow-sm) transition-transform duration-200 group-hover:scale-110"
                            :class="activityColorClass(act.activityType)">
                            <i :class="['ti', activityIconClass(act.activityType), 'text-sm']" />
                        </div>

                        <!-- Content Card -->
                        <div class="flex-1 glass-card rounded-xl p-4 border border-(--border-color) hover:border-(--color-primary)/30 transition-all duration-200 relative"
                            :class="{ 'opacity-60': act.status === 'cancelled' }">
                            <header class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 pb-2 border-b border-(--border-color) mb-2">
                                <div class="min-w-0 flex-1">
                                    <h3 class="text-sm font-semibold text-(--text-heading) flex items-center gap-2 flex-wrap max-sm:justify-center"
                                        :class="{ 'line-through': act.status === 'cancelled' }">
                                        <span class="truncate">{{ act.subject }}</span>
                                        <Badge :variant="crmBadgeVariant(act.status)">{{ act.status }}</Badge>
                                        <span class="text-xxs font-mono font-normal uppercase tracking-widest px-1.5 py-0.5 rounded badge-soft-secondary capitalize">
                                            {{ act.activityType }}
                                        </span>
                                    </h3>
                                    <p class="text-xxs text-(--text-muted) mt-1">
                                        Linked to <span class="font-mono">{{ act.trackableType.split('\\').pop() }}</span>
                                        · <span class="font-mono">{{ act.trackableId.substring(0, 8) }}</span>
                                    </p>
                                </div>
                                <span class="text-xxs font-mono text-(--text-muted) bg-(--bg-muted) px-2 py-0.5 rounded whitespace-nowrap">
                                    <i class="ti ti-calendar-event" />
                                    {{ act.dueDate ? formatDate(act.dueDate) : 'No due date' }}
                                </span>
                            </header>

                            <p v-if="act.description" class="text-xs text-(--text-body) leading-relaxed whitespace-pre-line">
                                {{ act.description }}
                            </p>
                            <p v-else class="text-xxs text-(--text-muted) italic">No description provided.</p>

                            <footer class="mt-3 pt-2 border-t border-dashed border-(--border-color) flex items-center justify-between text-xxs text-(--text-muted) gap-3 flex-wrap">
                                <span class="inline-flex items-center gap-1.5 min-w-0">
                                    <i class="ti ti-user-circle" />
                                    <span class="truncate">{{ act.actor?.name || 'Unknown actor' }}</span>
                                    <span v-if="act.createdAt" class="text-(--text-muted)">·</span>
                                    <span v-if="act.createdAt" class="font-mono">{{ formatRelative(act.createdAt) }}</span>
                                </span>
                                <div class="flex gap-2 shrink-0">
                                    <button v-if="act.status === 'pending'" type="button"
                                        class="btn btn-ghost text-xxs text-(--color-success) py-0.5 px-2 border border-(--color-success)/30 hover:bg-(--color-success-subtle)"
                                        @click="completeActivity(act)">
                                        <i class="ti ti-check" /> Mark Complete
                                    </button>
                                    <button type="button" class="action-btn-mini hover:text-(--color-danger)" title="Archive" @click="confirmDelete(act)">
                                        <i class="ti ti-trash text-xs" />
                                    </button>
                                </div>
                            </footer>
                        </div>
                    </article>
                </div>
            </section>
        </div>

        <!-- Log Activity Form Modal -->
        <div v-if="showFormModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3 class="font-semibold text-sm">Log Interaction Activity</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="showFormModal = false">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <form @submit.prevent="saveActivity">
                    <div class="p-5 space-y-4">
                        <div class="grid grid-cols-2 gap-3">
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Activity Type</label>
                                <select v-model="form.activity_type" class="form-control text-xs" required>
                                    <option value="call">Phone Call</option>
                                    <option value="email">Email Sent</option>
                                    <option value="meeting">Meeting Held</option>
                                    <option value="note">Note</option>
                                    <option value="task">Operational Task</option>
                                </select>
                            </div>
                            <div class="space-y-1">
                                <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Due / Scheduled Date</label>
                                <input v-model="form.due_date" type="date" class="form-control text-xs" />
                            </div>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Subject / Summary</label>
                            <input v-model="form.subject" type="text" required placeholder="e.g. Call regarding quotation setup" class="form-control text-xs" />
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Polymorphic Target Model</label>
                            <select v-model="form.trackable_type" class="form-control text-xs" required @change="onTrackableTypeChange">
                                <option value="App\Models\Tenant\Lead">Lead Inquiry</option>
                                <option value="App\Models\Tenant\Opportunity">Opportunity Deal</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Select Specific Target</label>
                            <select v-model="form.trackable_id" class="form-control text-xs" required>
                                <option value="" disabled>-- Choose target entity --</option>
                                <option v-for="t in targetOptions" :key="t.id" :value="t.id">{{ t.title }}</option>
                            </select>
                        </div>

                        <div class="space-y-1">
                            <label class="text-xxs font-bold text-(--text-muted) uppercase tracking-wider">Detailed Description</label>
                            <textarea v-model="form.description" rows="3" placeholder="Capture meeting notes or discussion points..." class="form-control text-xs resize-none"></textarea>
                        </div>
                    </div>
                    <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                        <button type="button" class="btn btn-ghost text-xs" @click="showFormModal = false">Cancel</button>
                        <button type="submit" class="btn btn-primary text-xs" :disabled="submitting">
                            <i v-if="submitting" class="ti ti-loader-2 animate-spin" />
                            Log Activity
                        </button>
                    </footer>
                </form>
            </div>
        </div>

        <!-- Delete Modal -->
        <div v-if="deleteTarget" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4">
            <div class="glass-card rounded-2xl w-full max-w-md bg-(--bg-card) shadow-(--shadow-lg)">
                <header class="flex items-center justify-between p-5 border-b border-(--border-color)">
                    <h3>Archive Activity</h3>
                    <button type="button" class="w-8 h-8 rounded-full hover:bg-(--bg-muted) flex items-center justify-center" @click="deleteTarget = null">
                        <i class="ti ti-x" />
                    </button>
                </header>
                <div class="p-5">
                    <p class="text-xs text-(--text-body)">Archive this logged activity?</p>
                </div>
                <footer class="p-5 border-t border-(--border-color) flex justify-end gap-2">
                    <button type="button" class="btn btn-ghost text-xs" @click="deleteTarget = null">Cancel</button>
                    <button type="button" class="btn btn-danger text-xs" :disabled="deleting" @click="onConfirmDelete">
                        <i v-if="deleting" class="ti ti-loader-2 animate-spin" />
                        Archive
                    </button>
                </footer>
            </div>
        </div>
    </NuxtLayout>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, reactive } from 'vue'
import { useCrm, crmBadgeVariant } from '~/composables/useCrm'
import { useToast } from '~/composables/useToast'
import type { CrmActivity, CreateActivityPayload, ActivityType } from '~/types/crm'

const crm = useCrm()
const toast = useToast()

const loading = ref(false)
const submitting = ref(false)
const deleting = ref(false)

const activitiesList = ref<CrmActivity[]>([])
const filterType = ref<'all' | ActivityType>('all')
const filterStatus = ref<'all' | 'pending' | 'completed'>('all')

const filteredActivities = computed(() => activitiesList.value.filter(a => {
    const matchType = filterType.value === 'all' || a.activityType === filterType.value
    const matchStatus = filterStatus.value === 'all' || a.status === filterStatus.value
    return matchType && matchStatus
}))

// Polymorphic Form Lists
const leads = ref<{ id: string; title: string }[]>([])
const opportunities = ref<{ id: string; title: string }[]>([])
const targetOptions = computed(() => {
    if (form.trackable_type === 'App\\Models\\Tenant\\Lead') return leads.value
    return opportunities.value
})

/**
 * @description Reset trackable_id when changing trackable_type to ensure form integrity.
 * @returns { void }
 */
const onTrackableTypeChange = () => {
    form.trackable_id = ''
}

// Form Actions
const showFormModal = ref(false)
const form = reactive<CreateActivityPayload>({
    activity_type: 'call',
    subject: '',
    description: '',
    due_date: null,
    trackable_type: 'App\\Models\\Tenant\\Lead',
    trackable_id: ''
})

/**
 * @description Opens the log activity form modal in creation mode.
 * @returns { void }
 */
const openCreateModal = () => {
    form.activity_type = 'call'
    form.subject = ''
    form.description = ''
    form.due_date = null
    form.trackable_type = 'App\\Models\\Tenant\\Lead'
    form.trackable_id = ''
    showFormModal.value = true
}

/**
 * @description Log a new polymorphic activity (call, email, meeting, task) linked to Lead or Opportunity
 * @method POST
 * @returns { Promise<void> } Resolves on success, shows toast and appends activity to timeline
 */
const saveActivity = async () => {
    submitting.value = true
    try {
        const res = await crm.activities.create(form)
        activitiesList.value.unshift(res.data)
        toast.success('Activity logged', form.subject)
        showFormModal.value = false
    } catch (err: any) {
        toast.error('Logging failed', err?.data?.message)
    } finally {
        submitting.value = false
    }
}

/**
 * @description Transition a logged pending activity to completed state
 * @method POST
 * @param { CrmActivity } act Activity instance to transition
 * @returns { Promise<void> } Resolves on successful status change
 */
const completeActivity = async (act: CrmActivity) => {
    try {
        const res = await crm.activities.complete(act.id)
        const idx = activitiesList.value.findIndex(a => a.id === act.id)
        if (idx !== -1) activitiesList.value[idx] = res.data
        toast.success('Activity Completed', act.subject)
    } catch (err: any) {
        toast.error('Completion failed', err?.data?.message)
    }
}

// Delete Actions
const deleteTarget = ref<CrmActivity | null>(null)
/**
 * @description Prompts confirmation overlay before archiving an activity.
 * @param { CrmActivity } a Activity model instance to delete
 * @returns { void }
 */
const confirmDelete = (a: CrmActivity) => { deleteTarget.value = a }

/**
 * @description Submits archiving request for the designated target activity.
 * @method DELETE
 * @returns { Promise<void> } Resolves on success, shows toast and updates list
 */
const onConfirmDelete = async () => {
    if (!deleteTarget.value) return
    deleting.value = true
    try {
        await crm.activities.destroy(deleteTarget.value.id)
        activitiesList.value = activitiesList.value.filter(a => a.id !== deleteTarget.value!.id)
        toast.success('Activity archived', 'Log removed')
        deleteTarget.value = null
    } catch (err: any) {
        toast.error('Delete failed', err?.data?.message)
    } finally {
        deleting.value = false
    }
}

// Styling helpers
/**
 * @description Maps activity types to their corresponding Tabler icon class.
 * @param { ActivityType } type Activity type identifier
 * @returns { String } The mapped icon class name
 */
const activityIconClass = (type: ActivityType) => {
    switch (type) {
        case 'call':    return 'ti-phone'
        case 'email':   return 'ti-mail'
        case 'meeting': return 'ti-calendar'
        case 'note':    return 'ti-notes'
        case 'task':    return 'ti-checkbox'
    }
}

/**
 * @description Maps activity types to their corresponding Tailwind background color class.
 * @param { ActivityType } type Activity type identifier
 * @returns { String } The mapped Tailwind color class
 */
const activityColorClass = (type: ActivityType) => {
    switch (type) {
        case 'call':    return 'bg-blue-500'
        case 'email':   return 'bg-cyan-500'
        case 'meeting': return 'bg-amber-500'
        case 'note':    return 'bg-violet-500'
        case 'task':    return 'bg-slate-500'
    }
}

/**
 * @description Formats a date string into human-readable format.
 * @param { String } d ISO Date string
 * @returns { String } The formatted date string (e.g. "May 25, 2026")
 */
const formatDate = (d: string) => new Date(d).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })

/**
 * @description Relative time string ("3h ago", "yesterday", "2 days ago") for the
 * activity timestamp shown in the card footer.
 */
const formatRelative = (iso: string): string => {
    const then = new Date(iso).getTime()
    if (Number.isNaN(then)) return ''
    const diff = Math.floor((Date.now() - then) / 1000)
    if (diff < 60) return 'just now'
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`
    const days = Math.floor(diff / 86400)
    if (days === 1) return 'yesterday'
    if (days < 30) return `${days}d ago`
    return new Date(iso).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

// Boot Data
/**
 * @description Loads logged polymorphic activities, leads list, and opportunities list.
 * @method GET
 * @returns { Promise<void> } Resolves on success
 */
const load = async () => {
    loading.value = true
    try {
        const [aRes, lRes, oRes] = await Promise.all([
            crm.activities.list({ limit: 150 }),
            crm.leads.list({ limit: 100 }),
            crm.opportunities.list({ limit: 100 })
        ])
        activitiesList.value = aRes.data
        leads.value = lRes.data.map(l => ({ id: l.id, title: l.title }))
        opportunities.value = oRes.data.map(o => ({ id: o.id, title: o.title }))
    } catch (err: any) {
        toast.error('Failed to load activity log', err?.data?.message)
    } finally {
        loading.value = false
    }
}

onMounted(load)
</script>

<style scoped>
.action-btn-mini {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 22px;
    height: 22px;
    border-radius: 4px;
    background: transparent;
    border: none;
    cursor: pointer;
    color: var(--text-muted);
    transition: color 0.15s ease, background 0.15s ease;
}

.action-btn-mini:hover {
    color: var(--color-primary);
    background: var(--bg-muted);
}
</style>
