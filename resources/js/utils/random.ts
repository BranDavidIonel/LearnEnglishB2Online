// Random order for exercises, options and word lists.

/** A shuffled copy of the list (Fisher–Yates); the list itself is not changed. */
export function shuffle<T>(list: readonly T[]): T[] {
    const copy = list.slice();

    for (let i = copy.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [copy[i], copy[j]] = [copy[j], copy[i]];
    }

    return copy;
}

/** `n` different items of the list, in random order. */
export function sample<T>(list: readonly T[], n: number): T[] {
    return shuffle(list).slice(0, n);
}

/** One random item of a non-empty list. */
export function pick<T>(list: readonly T[]): T {
    return list[Math.floor(Math.random() * list.length)];
}
