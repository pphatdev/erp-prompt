/**
 * Portable UUID v4 generator.
 *
 * Why this exists: `crypto.randomUUID()` is only available in **secure
 * contexts** (HTTPS or localhost). Dev servers reached via a LAN IP
 * (e.g. `http://192.168.1.42:3000`) hit a `TypeError: crypto.randomUUID
 * is not a function` because the Web Crypto API surface is gated behind
 * the secure-context check.
 *
 * Resolution order (most preferred first):
 *   1. `crypto.randomUUID()`         - native, when present.
 *   2. `crypto.getRandomValues()`    - widely available even on insecure
 *                                       LAN dev origins. RFC 4122 v4 shape.
 *   3. `Math.random()` fallback      - last resort. Not cryptographically
 *                                       strong but never crashes; fine
 *                                       for cart/checkout idempotency
 *                                       client UUIDs.
 *
 * Auto-imported by Nuxt as `randomUUID()` everywhere - no `import`
 * statement needed at call sites.
 */
export function randomUUID(): string {
    const g: any = globalThis as any
    const c = g.crypto ?? g.msCrypto

    if (c && typeof c.randomUUID === 'function') {
        try {
            return c.randomUUID()
        } catch {
            // Fall through to the next strategy.
        }
    }

    if (c && typeof c.getRandomValues === 'function') {
        // RFC 4122 v4: xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx
        const bytes = new Uint8Array(16)
        c.getRandomValues(bytes)
        bytes[6] = (bytes[6] & 0x0f) | 0x40 // version 4
        bytes[8] = (bytes[8] & 0x3f) | 0x80 // variant 1 (10xx)
        const hex: string[] = []
        for (let i = 0; i < 16; i++) hex.push(bytes[i].toString(16).padStart(2, '0'))
        return `${hex.slice(0, 4).join('')}-${hex.slice(4, 6).join('')}-${hex.slice(6, 8).join('')}-${hex.slice(8, 10).join('')}-${hex.slice(10, 16).join('')}`
    }

    // Last resort - non-crypto-strong but always works.
    const r = () => Math.floor(Math.random() * 16).toString(16)
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (c) => {
        const n = Math.floor(Math.random() * 16)
        return (c === 'x' ? n : (n & 0x3) | 0x8).toString(16)
    })
}
