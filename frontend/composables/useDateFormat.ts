/**
 * Centralised date formatters. Standardised on a single human-readable shape
 * so timestamps look identical across every page:
 *   formatDateTime → "21, May 2026 03:45 PM"
 *   formatDate     → "21, May 2026"
 *
 * Inputs are accepted as ISO strings, Date objects, or null/undefined; bad
 * input returns the em-dash placeholder so callers don't have to guard.
 */

type DateInput = string | number | Date | null | undefined

const PLACEHOLDER = '—'

const toDate = (input: DateInput): Date | null => {
    if (input == null || input === '') return null
    const d = input instanceof Date ? input : new Date(input)
    return Number.isNaN(d.getTime()) ? null : d
}

const monthName = (d: Date) => d.toLocaleString('en-US', { month: 'long' })

const timePart = (d: Date) =>
    d.toLocaleString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true })

export const formatDateTime = (input: DateInput): string => {
    const d = toDate(input)
    if (!d) return PLACEHOLDER
    return `${d.getDate()} ${monthName(d)} ${d.getFullYear()} ${timePart(d)}`
}

export const formatDate = (input: DateInput): string => {
    const d = toDate(input)
    if (!d) return PLACEHOLDER
    return `${d.getDate()} ${monthName(d)} ${d.getFullYear()}`
}

export const useDateFormat = () => ({ formatDateTime, formatDate })
