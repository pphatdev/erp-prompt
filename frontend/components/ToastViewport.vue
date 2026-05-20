<template>
  <Teleport to="body">
    <div class="toast-viewport" role="region" aria-label="Notifications">
      <TransitionGroup name="toast" tag="div" class="toast-stack">
        <article
          v-for="t in toasts"
          :key="t.id"
          class="toast"
          :class="[`toast--${t.color}`, paused.has(t.id) ? 'is-paused' : '']"
          role="status"
          aria-live="polite"
          @mouseenter="pause(t.id)"
          @mouseleave="resume(t.id)"
          @focusin="pause(t.id)"
          @focusout="resume(t.id)"
        >
          <div class="toast-body">
            <span class="toast-icon" aria-hidden="true">
              <i :class="['ti', t.icon || defaultIcon(t.color)]" />
            </span>

            <div class="toast-content">
              <p class="toast-title">{{ t.title }}</p>
              <p v-if="t.description" class="toast-description">{{ t.description }}</p>
              <button
                v-if="t.actionLabel"
                type="button"
                class="toast-action"
                :class="`toast-action--${t.color}`"
                @click="invokeAction(t)"
              >
                {{ t.actionLabel }}
              </button>
            </div>

            <button
              type="button"
              class="toast-close"
              aria-label="Dismiss notification"
              @click="dismiss(t.id)"
            >
              <i class="ti ti-x" />
            </button>
          </div>

          <div v-if="t.duration > 0" class="toast-progress">
            <span
              :key="`p-${t.id}-${remainingMs(t)}`"
              :style="{ animationDuration: `${remainingMs(t)}ms`, animationPlayState: paused.has(t.id) ? 'paused' : 'running' }"
            />
          </div>
        </article>
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { onBeforeUnmount, reactive, watch } from 'vue'
import { useToast, type Toast, type ToastColor } from '~/composables/useToast'

const { toasts, remove } = useToast()

const timers = new Map<number, ReturnType<typeof setTimeout>>()
/** When the timer for a toast last (re)started, plus the remaining ms it was scheduled for. */
const meta = new Map<number, { startedAt: number; remaining: number }>()
const paused = reactive(new Set<number>())

const startTimer = (id: number, ms: number) => {
  if (ms <= 0) return
  const handle = setTimeout(() => dismiss(id), ms)
  timers.set(id, handle)
  meta.set(id, { startedAt: Date.now(), remaining: ms })
}

const pause = (id: number) => {
  const m = meta.get(id)
  if (!m || paused.has(id)) return
  const elapsed = Date.now() - m.startedAt
  const left = Math.max(0, m.remaining - elapsed)
  clearTimeout(timers.get(id))
  timers.delete(id)
  meta.set(id, { startedAt: 0, remaining: left })
  paused.add(id)
}

const resume = (id: number) => {
  if (!paused.has(id)) return
  const m = meta.get(id)
  if (!m) return
  paused.delete(id)
  startTimer(id, m.remaining)
}

const dismiss = (id: number) => {
  clearTimeout(timers.get(id))
  timers.delete(id)
  meta.delete(id)
  paused.delete(id)
  remove(id)
}

const invokeAction = (t: Toast) => {
  try { t.onAction?.() } catch (err) { console.error('Toast action failed', err) }
  dismiss(t.id)
}

const remainingMs = (t: Toast): number => meta.get(t.id)?.remaining ?? t.duration

const defaultIcon = (color: ToastColor): string => ({
  primary: 'ti-info-circle',
  success: 'ti-circle-check',
  warning: 'ti-alert-triangle',
  danger: 'ti-alert-circle',
  info: 'ti-info-circle',
  secondary: 'ti-message-circle'
}[color])

/**
 * Reactively start/stop timers when the toast list changes. We track which IDs
 * we've already seen so a re-render doesn't double-schedule the same toast.
 */
const known = new Set<number>()
watch(toasts, (list) => {
  list.forEach(t => {
    if (!known.has(t.id)) {
      known.add(t.id)
      startTimer(t.id, t.duration)
    }
  })
  known.forEach(id => {
    if (!list.some(t => t.id === id)) {
      clearTimeout(timers.get(id))
      timers.delete(id)
      meta.delete(id)
      paused.delete(id)
      known.delete(id)
    }
  })
}, { immediate: true, deep: true })

onBeforeUnmount(() => {
  timers.forEach(clearTimeout)
  timers.clear()
  meta.clear()
  paused.clear()
  known.clear()
})
</script>

<style scoped>
.toast-viewport {
  position: fixed;
  bottom: 1rem;
  right: 1rem;
  z-index: 100;
  pointer-events: none;
}

@media (max-width: 480px) {
  .toast-viewport {
    bottom: 0.75rem;
    right: 0.75rem;
    left: 0.75rem;
  }
}

.toast-stack {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
  width: clamp(280px, 360px, calc(100vw - 1.5rem));
}

.toast {
  position: relative;
  pointer-events: auto;
  background: var(--bg-card);
  border: 1px solid var(--border-color);
  border-left: 3px solid var(--color-primary);
  border-radius: 0.75rem;
  box-shadow: var(--shadow-lg);
  overflow: hidden;
  backdrop-filter: blur(12px) saturate(140%);
  -webkit-backdrop-filter: blur(12px) saturate(140%);
}

[data-bs-theme='dark'] .toast {
  background: color-mix(in srgb, var(--bg-card) 92%, transparent);
}

.toast-body {
  display: flex;
  align-items: flex-start;
  gap: 0.75rem;
  padding: 0.75rem 0.75rem 0.625rem 0.75rem;
}

.toast-icon {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 30px;
  height: 30px;
  border-radius: 0.5rem;
  font-size: 1.05rem;
  flex-shrink: 0;
  margin-top: 1px;
}

.toast-content { min-width: 0; flex: 1; }

.toast-title {
  font-size: 0.8125rem;
  font-weight: 600;
  color: var(--text-heading);
  line-height: 1.35;
}

.toast-description {
  margin-top: 0.25rem;
  font-size: 0.75rem;
  color: var(--text-body);
  line-height: 1.4;
  word-break: break-word;
}

.toast-action {
  display: inline-block;
  margin-top: 0.5rem;
  padding: 0.25rem 0.625rem;
  border-radius: 0.375rem;
  font-size: 0.6875rem;
  font-weight: 700;
  letter-spacing: 0.04em;
  text-transform: uppercase;
  background: transparent;
  transition: background 0.15s ease, color 0.15s ease;
  cursor: pointer;
}

.toast-close {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  width: 24px;
  height: 24px;
  margin-top: -2px;
  border-radius: 0.375rem;
  color: var(--text-muted);
  cursor: pointer;
  flex-shrink: 0;
  transition: background 0.15s ease, color 0.15s ease;
}
.toast-close:hover { background: var(--bg-muted); color: var(--text-heading); }

.toast--primary { border-left-color: var(--color-primary); }
.toast--primary .toast-icon { background: var(--color-primary-subtle); color: var(--color-primary); }
.toast-action--primary { color: var(--color-primary); }
.toast-action--primary:hover { background: var(--color-primary-subtle); }

.toast--success { border-left-color: var(--color-success); }
.toast--success .toast-icon { background: var(--color-success-subtle); color: var(--color-success); }
.toast-action--success { color: var(--color-success); }
.toast-action--success:hover { background: var(--color-success-subtle); }

.toast--warning { border-left-color: var(--color-warning); }
.toast--warning .toast-icon { background: var(--color-warning-subtle); color: var(--color-warning); }
.toast-action--warning { color: var(--color-warning); }
.toast-action--warning:hover { background: var(--color-warning-subtle); }

.toast--danger { border-left-color: var(--color-danger); }
.toast--danger .toast-icon { background: var(--color-danger-subtle); color: var(--color-danger); }
.toast-action--danger { color: var(--color-danger); }
.toast-action--danger:hover { background: var(--color-danger-subtle); }

.toast--info { border-left-color: var(--color-info); }
.toast--info .toast-icon { background: var(--color-info-subtle); color: var(--color-info); }
.toast-action--info { color: var(--color-info); }
.toast-action--info:hover { background: var(--color-info-subtle); }

.toast--secondary { border-left-color: var(--color-secondary); }
.toast--secondary .toast-icon { background: var(--color-secondary-subtle); color: var(--color-secondary); }
.toast-action--secondary { color: var(--color-secondary); }
.toast-action--secondary:hover { background: var(--color-secondary-subtle); }

.toast-progress {
  height: 2px;
  background: var(--bg-muted);
  overflow: hidden;
}
.toast-progress > span {
  display: block;
  height: 100%;
  width: 100%;
  transform-origin: left;
  animation-name: toast-shrink;
  animation-timing-function: linear;
  animation-fill-mode: forwards;
}
.toast--primary  .toast-progress > span { background: var(--color-primary); }
.toast--success  .toast-progress > span { background: var(--color-success); }
.toast--warning  .toast-progress > span { background: var(--color-warning); }
.toast--danger   .toast-progress > span { background: var(--color-danger); }
.toast--info     .toast-progress > span { background: var(--color-info); }
.toast--secondary .toast-progress > span { background: var(--color-secondary); }

@keyframes toast-shrink {
  from { transform: scaleX(1); }
  to   { transform: scaleX(0); }
}

/* Slide-in from the right, slide-out the same way. */
.toast-enter-active { transition: transform 0.32s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.2s ease; }
.toast-leave-active { transition: transform 0.22s ease, opacity 0.2s ease; position: absolute; right: 0; width: 100%; }
.toast-enter-from { transform: translateX(110%); opacity: 0; }
.toast-leave-to   { transform: translateX(110%); opacity: 0; }
.toast-move       { transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1); }
</style>
