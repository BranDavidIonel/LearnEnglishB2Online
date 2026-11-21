// The word window of the whole app: which word is open. The layout opens it when a tappable
// word is tapped (utils/tapWords.ts) and draws the one window; other parts only read it (an
// automatic microphone waits while it is open, a word list reloads after a change).

import { reactive } from 'vue';
import type { TapTarget } from '@/utils/tapWords';

export interface OpenWord extends TapTarget {
    /** Changes with every tap, so a new word gets a fresh window. */
    id: number;
}

export const wordLookup = reactive({
    current: null as OpenWord | null,
    /** Counts the windows closed after a word's state changed in them. */
    changes: 0,
});

let taps = 0;

/** Opens the word window for a word and the sentence it was tapped in. */
export function openWord(target: TapTarget): void {
    wordLookup.current = { ...target, id: ++taps };
}

/** Closes the word window; `changed` when the learner changed the state of a word in it. */
export function closeWord(changed = false): void {
    wordLookup.current = null;

    if (changed) {
        wordLookup.changes++;
    }
}

/** True while the word window is open. */
export function wordOpen(): boolean {
    return wordLookup.current !== null;
}
