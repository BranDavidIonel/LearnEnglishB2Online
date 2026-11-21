// Short messages at the bottom of the screen, like the original toast(): each one stays
// 2.6 s, fades out and is gone after 3.1 s. ToastStack (mounted by the layouts) draws them.
import { reactive } from 'vue';

export interface Toast {
    id: number;
    message: string;
    /** Extra class of the toast ('' or 'warn', as in the original). */
    kind: string;
    /** True while it fades out. */
    out: boolean;
}

const SHOWN_FOR = 2600;
const FADE_FOR = 500;

let nextId = 1;

export const toastState = reactive<{ toasts: Toast[] }>({ toasts: [] });

export function toast(message: string, kind = ''): void {
    const id = nextId++;
    toastState.toasts.push({ id, message, kind, out: false });

    setTimeout(() => {
        const shown = toastState.toasts.find((item) => item.id === id);

        if (shown) {
            shown.out = true;
        }
    }, SHOWN_FOR);

    setTimeout(() => {
        toastState.toasts = toastState.toasts.filter((item) => item.id !== id);
    }, SHOWN_FOR + FADE_FOR);
}
