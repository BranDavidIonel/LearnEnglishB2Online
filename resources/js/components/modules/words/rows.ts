// Small helpers of the word lists: they only read the states the server sent.
import { sample } from '@/utils/random';
import type { WordCounts, WordListRow, WordStates } from './types';

/** Puts the new states of an answer of the progress API into the rows of a list (in place). */
export function applyStates(rows: WordListRow[], words: WordStates): void {
    const fresh = new Map(words.map((word) => [word.en, word]));

    for (const row of rows) {
        const word = fresh.get(row.en);

        if (word) {
            row.status = word.status;
            row.box = word.box;
            row.known = word.known;
        }
    }
}

/** How many rows are learned, being learned and still to learn. */
export function countRows(rows: WordListRow[]): WordCounts {
    const counts: WordCounts = { learned: 0, learning: 0, new: 0, total: 0 };

    for (const row of rows) {
        counts[row.status]++;
        counts.total++;
    }

    return counts;
}

/** Three meanings of other words of the same level, for the mini lesson of `word`. */
export function lessonChoices(
    word: Pick<WordListRow, 'ro' | 'levelId'>,
    rows: WordListRow[],
): string[] {
    const others = rows.filter(
        (row) => row.levelId === word.levelId && row.ro !== word.ro,
    );

    return sample(others, 3).map((row) => row.ro);
}
