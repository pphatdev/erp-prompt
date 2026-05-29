import { onBeforeUnmount, ref, watch } from 'vue'

export interface CountUpOptions {
    /** Animation length in ms. Default 800. */
    duration?: number
    /** Number of decimal places to preserve while animating. Default 0 (round). */
    decimals?: number
}

/**
 * Smoothly animate a numeric KPI from its previous value to its new value
 * using requestAnimationFrame + ease-out cubic. Returns a ref the template
 * binds to.
 *
 *   const total = useCountUp(() => kpis.value.total)
 *   const cost  = useCountUp(() => kpis.value.totalCost, { decimals: 2 })
 *
 * Notes:
 *  - Honors `prefers-reduced-motion: reduce` — snaps directly to the target.
 *  - First emission animates from 0 → target (the watcher's `oldValue` is
 *    `undefined` on the immediate run).
 *  - Cancels its RAF on every retarget and on unmount, so rapid KPI churn
 *    (e.g. a typed filter) collapses cleanly to the latest value.
 */
export const useCountUp = (source: () => number, options: CountUpOptions = {}) => {
    const { duration = 800, decimals = 0 } = options
    const displayed = ref<number>(0)
    let rafId: number | null = null

    const reducedMotion = typeof window !== 'undefined'
        && typeof window.matchMedia === 'function'
        && window.matchMedia('(prefers-reduced-motion: reduce)').matches

    const snap = (val: number) => {
        displayed.value = decimals > 0 ? Number(val.toFixed(decimals)) : Math.round(val)
    }

    const cancel = () => {
        if (rafId !== null) {
            cancelAnimationFrame(rafId)
            rafId = null
        }
    }

    const run = (from: number, to: number) => {
        cancel()
        if (reducedMotion || from === to) {
            snap(to)
            return
        }
        const start = performance.now()
        const delta = to - from
        const step = (now: number) => {
            const elapsed = now - start
            const t = Math.min(1, elapsed / duration)
            // ease-out cubic — quick burst at start, gentle landing.
            const eased = 1 - Math.pow(1 - t, 3)
            snap(from + delta * eased)
            if (t < 1) {
                rafId = requestAnimationFrame(step)
            } else {
                rafId = null
                snap(to)
            }
        }
        rafId = requestAnimationFrame(step)
    }

    watch(
        source,
        (next, prev) => run(typeof prev === 'number' ? prev : 0, Number.isFinite(next) ? next : 0),
        { immediate: true },
    )

    onBeforeUnmount(cancel)

    return displayed
}
