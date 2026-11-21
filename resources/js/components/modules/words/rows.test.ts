import { describe, expect, it } from 'vite-plus/test';
import { applyStates, countRows, lessonChoices } from './rows';
import type { WordListRow } from './types';

function row(
    en: string,
    ro: string,
    extra: Partial<WordListRow> = {},
): WordListRow {
    return {
        en,
        ro,
        ipa: '',
        emoji: '',
        levelId: 'a1',
        status: 'new',
        box: null,
        known: false,
        ...extra,
    };
}

describe('word rows', () => {
    it('takes the new states of an answer', () => {
        const rows = [row('dog', 'câine'), row('cat', 'pisică')];

        applyStates(rows, [
            { en: 'cat', status: 'learned', box: 6, known: true },
        ]);

        expect(rows[0].status).toBe('new');
        expect(rows[1]).toMatchObject({
            status: 'learned',
            box: 6,
            known: true,
        });
    });

    it('counts the rows by status', () => {
        const rows = [
            row('a', 'a', { status: 'learned' }),
            row('b', 'b', { status: 'learning' }),
            row('c', 'c'),
            row('d', 'd'),
        ];

        expect(countRows(rows)).toEqual({
            learned: 1,
            learning: 1,
            new: 2,
            total: 4,
        });
    });

    it('picks three other meanings of the same level for the mini lesson', () => {
        const rows = [
            row('dog', 'câine'),
            row('hound', 'câine'),
            row('cat', 'pisică'),
            row('house', 'casă'),
            row('tree', 'copac'),
            row('car', 'mașină', { levelId: 'a2' }),
        ];

        const choices = lessonChoices(rows[0], rows);

        expect(choices).toHaveLength(3);
        expect([...choices].sort()).toEqual(['casă', 'copac', 'pisică']);
    });
});
