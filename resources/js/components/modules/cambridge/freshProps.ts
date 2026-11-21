// The browser's back and forward buttons bring a page back with the props it had then. A
// Cambridge page shows results and drafts that may have changed since, so it asks for them again.
import { router } from '@inertiajs/vue3';

/**
 * Reloads `only` whenever the page `component` is brought back from the history.
 * Returns the function that stops listening.
 */
export function reloadOnHistory(component: string, only: string[]): () => void {
    return router.on('navigate', (event) => {
        if (
            event.detail.type === 'history' &&
            event.detail.page.component === component
        ) {
            router.reload({ only });
        }
    });
}
