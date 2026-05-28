<template>
    <div class="approval-timeline relative py-3">
        <!-- Connecting Line with Gradient/Dashed Glow -->
        <div class="timeline-line absolute left-[15px] top-6 bottom-6 w-[2px]"></div>

        <div class="space-y-6">
            <!-- Empty state -->
            <div v-if="!history || history.length === 0" class="text-sm text-(--text-muted) pl-8 py-4 italic flex items-center gap-2">
                <i class="ti ti-info-circle text-base text-(--text-muted)/80" />
                No history available.
            </div>

            <!-- Sorted History Steps -->
            <div v-for="(item, index) in sortedHistory" :key="item.id || index" 
                class="timeline-item relative pl-11 group transition-all"
                :class="`timeline-item--${item.action}`">
                
                <!-- Dot Indicator with pulsing concentric rings -->
                <div class="timeline-dot absolute left-0 top-1 w-8 h-8 rounded-full flex items-center justify-center border-2 shadow-sm transition-all"
                    :class="dotClass(item.action)">
                    <span class="dot-glow absolute inset-0 rounded-full opacity-0 group-hover:opacity-100 transition-opacity"></span>
                    <i class="ti text-sm z-10" :class="iconClass(item.action)"></i>
                </div>
                
                <!-- Content Card -->
                <div class="timeline-card rounded-xl p-4 transition-all" :class="cardClass(item.action)">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="badge-pill px-2.5 py-0.5 rounded-full text-xxs font-bold uppercase tracking-wider" 
                                :class="badgeClass(item.action)">
                                {{ item.action.replace('_', ' ') }}
                            </span>
                            <span class="text-xs text-(--text-muted)">by</span>
                            <span class="text-xs font-semibold text-(--text-heading)">{{ item.approver?.name || 'System' }}</span>
                        </div>
                        <span class="text-[10px] font-medium font-mono text-(--text-muted) uppercase tracking-wide">
                            {{ formatDateTime(item.created_at) }}
                        </span>
                    </div>
                    
                    <!-- Comment Section -->
                    <div v-if="item.comment" class="comment-bubble mt-3 relative p-3 rounded-lg text-xs leading-relaxed">
                        <i class="ti ti-quote absolute right-3 top-2 text-lg text-(--text-muted)/20 pointer-events-none" />
                        <span class="text-(--text-body) font-medium">"{{ item.comment }}"</span>
                    </div>
                </div>
            </div>
            
            <!-- Pending Active Level State -->
            <div v-if="currentLevel" class="timeline-item timeline-item--pending relative pl-11 group">
                <!-- Dot Indicator (Active Pulse) -->
                <div class="timeline-dot absolute left-0 top-1 w-8 h-8 rounded-full flex items-center justify-center border-2 border-amber-500/30 bg-amber-500/10 text-amber-500 shadow-[0_0_12px_rgba(245,158,11,0.2)]">
                    <span class="dot-pulse absolute inset-[-3px] rounded-full border border-amber-500/40 animate-ping opacity-60"></span>
                    <i class="ti ti-loader-2 animate-spin text-sm"></i>
                </div>
                
                <!-- Content Card -->
                <div class="timeline-card timeline-card--pending rounded-xl p-4 border border-dashed border-amber-500/30 bg-amber-500/[0.02]">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2">
                        <div class="flex items-center gap-2">
                            <span class="badge-pill px-2.5 py-0.5 rounded-full text-xxs font-bold uppercase tracking-wider bg-amber-500/10 text-amber-500">
                                Pending
                            </span>
                            <span class="text-xs font-semibold text-amber-500">Awaiting Approval</span>
                        </div>
                        <span class="text-[10px] font-semibold font-mono text-amber-500/80 uppercase tracking-wider animate-pulse">
                            Level {{ currentLevel.sequence }} Active
                        </span>
                    </div>
                    <p class="text-xs text-(--text-muted) mt-2 leading-relaxed">
                        Waiting for review and authorization from designated <span class="font-semibold text-(--text-body)">{{ currentLevel.approver_role || 'approver' }}</span>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import type { ApprovalHistory, ApprovalLevel } from '~/composables/useApprovals'
import { useDateFormat } from '~/composables/useDateFormat'

const props = defineProps<{
    history: ApprovalHistory[],
    currentLevel?: ApprovalLevel | null
}>()

const { formatDateTime } = useDateFormat()

const sortedHistory = computed(() => {
    if (!props.history) return []
    // Sort chronologically ascending
    return [...props.history].sort((a, b) => new Date(a.created_at).getTime() - new Date(b.created_at).getTime())
})

const dotClass = (action: string) => {
    switch (action) {
        case 'approved':
            return 'bg-green-500/10 text-green-500 border-green-500/30 shadow-[0_0_10px_rgba(34,197,94,0.1)]'
        case 'rejected':
            return 'bg-red-500/10 text-red-500 border-red-500/30 shadow-[0_0_10px_rgba(239,68,68,0.1)]'
        case 'sent_back':
            return 'bg-orange-500/10 text-orange-500 border-orange-500/30 shadow-[0_0_10px_rgba(249,115,22,0.1)]'
        case 'delegated':
            return 'bg-purple-500/10 text-purple-500 border-purple-500/30 shadow-[0_0_10px_rgba(168,85,247,0.1)]'
        default:
            return 'bg-(--bg-muted) text-(--text-muted) border-(--border-color)'
    }
}

const iconClass = (action: string) => {
    switch (action) {
        case 'approved': return 'ti-check'
        case 'rejected': return 'ti-x'
        case 'sent_back': return 'ti-arrow-back-up'
        case 'delegated': return 'ti-user-share'
        default: return 'ti-clock'
    }
}

const badgeClass = (action: string) => {
    switch (action) {
        case 'approved': return 'bg-green-500/10 text-green-500'
        case 'rejected': return 'bg-red-500/10 text-red-500'
        case 'sent_back': return 'bg-orange-500/10 text-orange-500'
        case 'delegated': return 'bg-purple-500/10 text-purple-500'
        default: return 'bg-(--bg-muted) text-(--text-muted)'
    }
}

const cardClass = (action: string) => {
    switch (action) {
        case 'approved': return 'border border-green-500/10 bg-green-500/[0.01]'
        case 'rejected': return 'border border-red-500/10 bg-red-500/[0.01]'
        case 'sent_back': return 'border border-orange-500/10 bg-orange-500/[0.01]'
        case 'delegated': return 'border border-purple-500/10 bg-purple-500/[0.01]'
        default: return 'border border-(--border-color) bg-(--bg-card)'
    }
}
</script>

<style scoped>
.timeline-line {
    background: linear-gradient(to bottom, 
        rgba(34, 197, 94, 0.4) 0%, 
        rgba(168, 85, 247, 0.3) 50%, 
        rgba(245, 158, 11, 0.2) 100%
    );
    box-shadow: 0 0 4px rgba(168, 85, 247, 0.1);
}

.timeline-item:hover .timeline-dot {
    transform: scale(1.08);
}

.timeline-card {
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow-sm);
}

.timeline-card:hover {
    transform: translateY(-1px);
    box-shadow: var(--shadow-md);
    border-color: var(--border-strong);
}

/* Comment bubble accent line left */
.comment-bubble {
    background: var(--bg-muted);
    border-left: 3px solid var(--border-color);
}

.timeline-item--approved .comment-bubble {
    border-left-color: rgb(34 197 94 / 0.5);
    background: rgb(34 197 94 / 0.03);
}

.timeline-item--rejected .comment-bubble {
    border-left-color: rgb(239 68 68 / 0.5);
    background: rgb(239 68 68 / 0.03);
}

.timeline-item--sent_back .comment-bubble {
    border-left-color: rgb(249 115 22 / 0.5);
    background: rgb(249 115 22 / 0.03);
}

.timeline-item--delegated .comment-bubble {
    border-left-color: rgb(168 85 247 / 0.5);
    background: rgb(168 85 247 / 0.03);
}

.dot-glow {
    background: currentColor;
    filter: blur(8px);
}
</style>
