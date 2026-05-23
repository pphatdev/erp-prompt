<template>
    <Teleport to="body">
        <transition name="confirm">
            <div v-if="active" class="confirm-overlay" role="dialog" aria-modal="true"
                :aria-labelledby="`confirm-title-${active.id}`"
                :aria-describedby="active.description ? `confirm-desc-${active.id}` : undefined"
                @click.self="onBackdrop">
                <div class="confirm-card" :class="`confirm-card--${active.color}`">
                    <!-- Close affordance — corner only; primary cancel lives in the footer. -->
                    <button type="button" class="confirm-close" aria-label="Cancel" @click="cancel">
                        <i class="ti ti-x" />
                    </button>

                    <!-- Hero: centered medallion with a soft, color-tinted halo. -->
                    <div class="confirm-hero">
                        <span class="confirm-halo" :class="`confirm-halo--${active.color}`" aria-hidden="true" />
                        <span class="confirm-icon" :class="`confirm-icon--${active.color}`" aria-hidden="true">
                            <i :class="['ti', active.icon]" />
                        </span>
                    </div>

                    <!-- Body — centered title + supportive description. -->
                    <div class="confirm-body">
                        <h3 :id="`confirm-title-${active.id}`" class="confirm-title">{{ active.title }}</h3>
                        <p v-if="active.description" :id="`confirm-desc-${active.id}`" class="confirm-description">
                            {{ active.description }}
                        </p>
                    </div>

                    <!-- Actions — equal-weight buttons side-by-side; primary on the right. -->
                    <div class="confirm-actions">
                        <button type="button" class="confirm-cancel" @click="cancel">
                            {{ active.cancelLabel }}
                        </button>
                        <button ref="confirmBtn" type="button" class="confirm-btn"
                            :class="`confirm-btn--${active.color}`" @click="confirm">
                            {{ active.confirmLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </transition>
    </Teleport>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useToast, type ConfirmRequest } from '~/composables/useToast'

const { confirms } = useToast()

// Render the FIRST confirm in the queue. Stacking later requests means a
// user can't be tricked into responding to a different dialog than the one
// they just dismissed.
const active = computed<ConfirmRequest | null>(() =>
    (confirms.value[0] as ConfirmRequest | undefined) ?? null
)

const confirmBtn = ref<HTMLButtonElement | null>(null)
let previouslyFocused: HTMLElement | null = null

const confirm = () => active.value?.onConfirm()
const cancel = () => active.value?.onCancel()
const onBackdrop = () => cancel()

const onKey = (ev: KeyboardEvent) => {
    if (!active.value) return
    if (ev.key === 'Escape') {
        ev.preventDefault()
        cancel()
    } else if (ev.key === 'Enter') {
        ev.preventDefault()
        confirm()
    }
}

// Lock background scroll while a dialog is open. Focus the confirm button
// on open; restore prior focus on close. Both run on `active` transitions.
watch(active, (curr, prev) => {
    if (typeof document === 'undefined') return
    if (curr && !prev) {
        previouslyFocused = (document.activeElement as HTMLElement) || null
        document.body.style.overflow = 'hidden'
        nextTick(() => confirmBtn.value?.focus())
    } else if (!curr && prev) {
        document.body.style.overflow = ''
        previouslyFocused?.focus?.()
        previouslyFocused = null
    }
}, { immediate: true })

onMounted(() => {
    if (typeof document !== 'undefined') {
        document.addEventListener('keydown', onKey)
    }
})

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.removeEventListener('keydown', onKey)
        document.body.style.overflow = ''
    }
})
</script>

<style scoped>
.confirm-overlay {
    position: fixed;
    inset: 0;
    z-index: 110;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(15, 23, 42, 0.5);
    backdrop-filter: blur(6px) saturate(120%);
    -webkit-backdrop-filter: blur(6px) saturate(120%);
}

.confirm-card {
    position: relative;
    width: 100%;
    max-width: 26rem;
    padding: 2rem 1.75rem 1.5rem 1.75rem;
    background: var(--bg-card);
    border: 1px solid var(--border-color);
    border-radius: 1.25rem;
    box-shadow: var(--shadow-lg), 0 0 0 1px rgb(255 255 255 / 0.02) inset;
    overflow: hidden;
    text-align: center;
}

/*
 * Soft color wash at the top of the card — keyed to the action's tone so a
 * delete dialog reads as serious even before the user reads the text, but
 * subtle enough that the dialog still feels welcoming. Subtle, not loud.
 */
.confirm-card::before {
    content: '';
    position: absolute;
    inset: -40% -10% auto -10%;
    height: 240px;
    z-index: 0;
    background: radial-gradient(closest-side at 50% 70%, var(--accent, transparent) 0%, transparent 75%);
    opacity: 0.55;
    pointer-events: none;
}

.confirm-card--primary {
    --accent: rgb(var(--color-primary-rgb) / 0.18);
}

.confirm-card--success {
    --accent: rgb(var(--color-success-rgb) / 0.18);
}

.confirm-card--warning {
    --accent: rgb(var(--color-warning-rgb) / 0.20);
}

.confirm-card--danger {
    --accent: rgb(var(--color-danger-rgb) / 0.18);
}

.confirm-card--info {
    --accent: rgb(var(--color-info-rgb) / 0.18);
}

.confirm-card--secondary {
    --accent: rgb(var(--color-secondary-rgb) / 0.18);
}

[data-bs-theme='dark'] .confirm-card {
    background: color-mix(in srgb, var(--bg-card) 92%, transparent);
    backdrop-filter: blur(16px) saturate(140%);
    -webkit-backdrop-filter: blur(16px) saturate(140%);
}

.confirm-close {
    position: absolute;
    top: 0.75rem;
    right: 0.75rem;
    z-index: 2;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 32px;
    height: 32px;
    border-radius: 0.625rem;
    color: var(--text-muted);
    background: transparent;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease;
}

.confirm-close:hover {
    background: var(--bg-muted);
    color: var(--text-heading);
}

/* ----- Hero ----- */
.confirm-hero {
    position: relative;
    z-index: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 80px;
    margin-bottom: 1rem;
}

.confirm-halo {
    position: absolute;
    width: 80px;
    height: 80px;
    border-radius: 9999px;
    opacity: 0.55;
    filter: blur(14px);
    animation: confirm-pulse 2.6s ease-in-out infinite;
}

.confirm-icon {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 56px;
    height: 56px;
    border-radius: 9999px;
    font-size: 1.5rem;
    border: 1px solid transparent;
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
}

.confirm-icon--primary {
    background: color-mix(in srgb, var(--color-primary-subtle) 85%, var(--bg-card));
    color: var(--color-primary);
    border-color: rgb(var(--color-primary-rgb) / 0.25);
}

.confirm-icon--success {
    background: color-mix(in srgb, var(--color-success-subtle) 85%, var(--bg-card));
    color: var(--color-success);
    border-color: rgb(var(--color-success-rgb) / 0.25);
}

.confirm-icon--warning {
    background: color-mix(in srgb, var(--color-warning-subtle) 85%, var(--bg-card));
    color: var(--color-warning);
    border-color: rgb(var(--color-warning-rgb) / 0.25);
}

.confirm-icon--danger {
    background: color-mix(in srgb, var(--color-danger-subtle) 85%, var(--bg-card));
    color: var(--color-danger);
    border-color: rgb(var(--color-danger-rgb) / 0.25);
}

.confirm-icon--info {
    background: color-mix(in srgb, var(--color-info-subtle) 85%, var(--bg-card));
    color: var(--color-info);
    border-color: rgb(var(--color-info-rgb) / 0.25);
}

.confirm-icon--secondary {
    background: color-mix(in srgb, var(--color-secondary-subtle) 85%, var(--bg-card));
    color: var(--color-secondary);
    border-color: rgb(var(--color-secondary-rgb) / 0.25);
}

/* ----- Body ----- */
.confirm-body {
    position: relative;
    z-index: 1;
    margin: 0 auto 1.5rem auto;
    max-width: 22rem;
}

.confirm-title {
    font-size: 1.0625rem;
    /* 17px — friendly heading, not page-title aggressive */
    font-weight: 600;
    color: var(--text-heading);
    letter-spacing: -0.01em;
    line-height: 1.35;
}

.confirm-description {
    margin-top: 0.5rem;
    font-size: 0.8125rem;
    color: var(--text-body);
    line-height: 1.55;
}

/* ----- Actions ----- */
.confirm-actions {
    position: relative;
    z-index: 1;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 0.625rem;
}

.confirm-cancel,
.confirm-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    height: 42px;
    padding: 0 1rem;
    border-radius: 0.625rem;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease, box-shadow 0.15s ease, transform 0.1s ease;
}

.confirm-cancel:active,
.confirm-btn:active {
    transform: scale(0.98);
}

.confirm-cancel {
    color: var(--text-body);
    background: var(--bg-card);
    border: 1px solid var(--border-color);
}

.confirm-cancel:hover {
    background: var(--bg-muted);
    color: var(--text-heading);
    border-color: var(--border-strong);
}

.confirm-cancel:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px rgb(var(--color-secondary-rgb) / 0.25);
}

.confirm-btn {
    color: #fff;
    background: var(--color-primary);
    border: 1px solid transparent;
    box-shadow: 0 6px 16px -4px rgb(var(--color-primary-rgb) / 0.4);
}

.confirm-btn:hover {
    background: rgb(var(--color-primary-rgb) / 0.9);
}

.confirm-btn:focus-visible {
    outline: none;
    box-shadow: 0 0 0 3px rgb(var(--color-primary-rgb) / 0.3), 0 6px 16px -4px rgb(var(--color-primary-rgb) / 0.4);
}

.confirm-btn--danger {
    background: var(--color-danger);
    box-shadow: 0 6px 16px -4px rgb(var(--color-danger-rgb) / 0.4);
}

.confirm-btn--danger:hover {
    background: rgb(var(--color-danger-rgb) / 0.9);
}

.confirm-btn--danger:focus-visible {
    box-shadow: 0 0 0 3px rgb(var(--color-danger-rgb) / 0.3), 0 6px 16px -4px rgb(var(--color-danger-rgb) / 0.4);
}

.confirm-btn--warning {
    background: var(--color-warning);
    color: #1f1300;
    box-shadow: 0 6px 16px -4px rgb(var(--color-warning-rgb) / 0.4);
}

.confirm-btn--warning:hover {
    background: rgb(var(--color-warning-rgb) / 0.9);
}

.confirm-btn--warning:focus-visible {
    box-shadow: 0 0 0 3px rgb(var(--color-warning-rgb) / 0.3), 0 6px 16px -4px rgb(var(--color-warning-rgb) / 0.4);
}

.confirm-btn--success {
    background: var(--color-success);
    box-shadow: 0 6px 16px -4px rgb(var(--color-success-rgb) / 0.4);
}

.confirm-btn--success:hover {
    background: rgb(var(--color-success-rgb) / 0.9);
}

.confirm-btn--success:focus-visible {
    box-shadow: 0 0 0 3px rgb(var(--color-success-rgb) / 0.3), 0 6px 16px -4px rgb(var(--color-success-rgb) / 0.4);
}

.confirm-btn--info {
    background: var(--color-info);
    box-shadow: 0 6px 16px -4px rgb(var(--color-info-rgb) / 0.4);
}

.confirm-btn--info:hover {
    background: rgb(var(--color-info-rgb) / 0.9);
}

.confirm-btn--secondary {
    background: var(--color-secondary);
    box-shadow: 0 6px 16px -4px rgb(var(--color-secondary-rgb) / 0.4);
}

.confirm-btn--secondary:hover {
    background: rgb(var(--color-secondary-rgb) / 0.9);
}

/* ----- Motion ----- */
@keyframes confirm-pulse {

    0%,
    100% {
        transform: scale(1);
        opacity: 0.55;
    }

    50% {
        transform: scale(1.08);
        opacity: 0.40;
    }
}

.confirm-enter-active,
.confirm-leave-active {
    transition: opacity 0.18s ease;
}

.confirm-enter-active .confirm-card,
.confirm-leave-active .confirm-card {
    transition: transform 0.24s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.18s ease;
}

.confirm-enter-from {
    opacity: 0;
}

.confirm-leave-to {
    opacity: 0;
}

.confirm-enter-from .confirm-card {
    transform: translateY(12px) scale(0.96);
    opacity: 0;
}

.confirm-leave-to .confirm-card {
    transform: translateY(8px) scale(0.98);
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .confirm-halo {
        animation: none;
    }

    .confirm-enter-active .confirm-card,
    .confirm-leave-active .confirm-card {
        transition: opacity 0.15s ease;
        transform: none;
    }

    .confirm-enter-from .confirm-card,
    .confirm-leave-to .confirm-card {
        transform: none;
    }
}
</style>
