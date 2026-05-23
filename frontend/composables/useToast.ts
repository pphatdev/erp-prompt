import { readonly, ref } from 'vue'

export type ToastColor = 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'secondary'

export interface Toast {
    id: number
    title: string
    description?: string
    color: ToastColor
    icon?: string
    /** ms before auto-dismiss; 0 = sticky. */
    duration: number
    actionLabel?: string
    onAction?: () => void
}

export interface ToastInput extends Partial<Omit<Toast, 'id'>> {
    title: string
}

export interface ConfirmRequest {
    id: number
    title: string
    description?: string
    confirmLabel: string
    cancelLabel: string
    color: ToastColor
    icon: string
    onConfirm: () => void
    onCancel: () => void
}

export interface ConfirmInput {
    title: string
    description?: string
    confirmLabel?: string
    cancelLabel?: string
    /** Visual tone — `danger` for destructive prompts, `warning` for cautionary. */
    color?: ToastColor
    icon?: string
}

const toasts = ref<Toast[]>([])
// Confirm queue — one dialog at a time. Subsequent calls stack and render in
// order so a destructive action can't be hidden behind another modal.
const confirms = ref<ConfirmRequest[]>([])
let nextId = 0

const add = (input: ToastInput): number => {
    const id = ++nextId
    toasts.value.push({
        id,
        title: input.title,
        description: input.description,
        color: input.color ?? 'primary',
        icon: input.icon,
        duration: input.duration ?? 5000,
        actionLabel: input.actionLabel,
        onAction: input.onAction
    })
    return id
}

const remove = (id: number) => {
    toasts.value = toasts.value.filter(t => t.id !== id)
}

const clear = () => { toasts.value = [] }

const shortcut = (color: ToastColor, icon: string) =>
    (title: string, description?: string, opts: Partial<ToastInput> = {}) =>
        add({ title, description, color, icon, ...opts })

const removeConfirm = (id: number) => {
    confirms.value = confirms.value.filter(c => c.id !== id)
}

/**
 * Modal-based replacement for the browser-native `confirm()` dialog. Returns
 * a Promise that resolves true on confirm, false on cancel/dismiss. Rendered
 * by `ConfirmDialog.vue` at the app root — the consumer doesn't need to
 * mount anything per-page.
 */
const confirm = (input: ConfirmInput): Promise<boolean> =>
    new Promise<boolean>((resolve) => {
        const id = ++nextId
        let settled = false
        const settle = (value: boolean) => {
            if (settled) return
            settled = true
            removeConfirm(id)
            resolve(value)
        }
        confirms.value.push({
            id,
            title: input.title,
            description: input.description,
            color: input.color ?? 'warning',
            icon: input.icon ?? (input.color === 'danger' ? 'ti-alert-triangle' : 'ti-help-circle'),
            confirmLabel: input.confirmLabel ?? 'Confirm',
            cancelLabel: input.cancelLabel ?? 'Cancel',
            onConfirm: () => settle(true),
            onCancel: () => settle(false)
        })
    })

export const useToast = () => ({
    toasts: readonly(toasts),
    confirms: readonly(confirms),
    add,
    remove,
    clear,
    confirm,
    success: shortcut('success', 'ti-circle-check'),
    warning: shortcut('warning', 'ti-alert-triangle'),
    error: shortcut('danger', 'ti-alert-circle'),
    info: shortcut('info', 'ti-info-circle')
})
