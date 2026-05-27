<template>
    <span>{{ rendered }}</span>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

interface Props {
    /** Target value to animate to. */
    value: number | string | null | undefined
    /** Animation duration in ms. */
    duration?: number
    /** Decimal places to show. Defaults to 0 for ints, 2 for currency-mode. */
    decimals?: number
    /** Render as USD currency (overrides decimals/format). */
    currency?: string
    /** Thousand separator + decimal precision via Intl.NumberFormat — leave currency unset to use. */
    locale?: string
    /** Optional prefix + suffix (e.g. "$", "%"). */
    prefix?: string
    suffix?: string
    /** Stop animation entirely (renders the final value immediately). */
    instant?: boolean
}

const props = withDefaults(defineProps<Props>(), {
    duration: 900,
    locale: 'en-US',
    instant: false,
})

/** Coerce strings / nulls / NaN to a finite number, fallback 0. */
const toNum = (v: unknown): number => {
    if (v === null || v === undefined) return 0
    const n = typeof v === 'number' ? v : Number(v)
    return Number.isFinite(n) ? n : 0
}

const target = computed(() => toNum(props.value))
const display = ref(0)
let rafId: number | null = null
let startedAt = 0
let from = 0

const reducedMotion = (): boolean => {
    if (typeof window === 'undefined') return true
    return window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false
}

// easeOutCubic — fast at first, settles on the target
const ease = (t: number) => 1 - Math.pow(1 - t, 3)

const cancelRaf = () => {
    if (rafId !== null && typeof cancelAnimationFrame !== 'undefined') {
        cancelAnimationFrame(rafId)
        rafId = null
    }
}

const animate = (to: number) => {
    cancelRaf()
    if (props.instant || reducedMotion() || typeof requestAnimationFrame === 'undefined') {
        display.value = to
        return
    }
    from = display.value
    startedAt = performance.now()
    const step = (now: number) => {
        const elapsed = now - startedAt
        const progress = Math.min(1, elapsed / props.duration)
        display.value = from + (to - from) * ease(progress)
        if (progress < 1) {
            rafId = requestAnimationFrame(step)
        } else {
            display.value = to
            rafId = null
        }
    }
    rafId = requestAnimationFrame(step)
}

const formatter = computed(() => {
    if (props.currency) {
        return new Intl.NumberFormat(props.locale, {
            style: 'currency',
            currency: props.currency,
            minimumFractionDigits: props.decimals ?? 2,
            maximumFractionDigits: props.decimals ?? 2,
        })
    }
    const decimals = props.decimals ?? 0
    return new Intl.NumberFormat(props.locale, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
    })
})

const rendered = computed(() => {
    const body = formatter.value.format(display.value)
    return `${props.prefix ?? ''}${body}${props.suffix ?? ''}`
})

onMounted(() => animate(target.value))
watch(target, (next) => animate(next))
onBeforeUnmount(cancelRaf)
</script>
