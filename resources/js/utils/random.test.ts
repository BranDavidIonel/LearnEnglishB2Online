import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { pick, sample, shuffle } from './random';

afterEach(() => {
    vi.restoreAllMocks();
});

describe('shuffle', () => {
    it('returns a new list with the same items', () => {
        const list = [1, 2, 3, 4, 5, 6];
        const shuffled = shuffle(list);

        expect(shuffled).not.toBe(list);
        expect([...shuffled].sort((a, b) => a - b)).toEqual(list);
        expect(list).toEqual([1, 2, 3, 4, 5, 6]);
    });

    it('swaps from the end, like Fisher–Yates', () => {
        // Math.random() = 0 always swaps item i with the first one
        vi.spyOn(Math, 'random').mockReturnValue(0);

        expect(shuffle(['a', 'b', 'c'])).toEqual(['b', 'c', 'a']);
    });

    it('handles empty and single lists', () => {
        expect(shuffle([])).toEqual([]);
        expect(shuffle(['x'])).toEqual(['x']);
    });
});

describe('sample', () => {
    it('takes n different items', () => {
        const picked = sample([1, 2, 3, 4, 5], 3);

        expect(picked).toHaveLength(3);
        expect(new Set(picked).size).toBe(3);
    });

    it('returns everything when n is larger than the list', () => {
        expect(sample([1, 2], 5)).toHaveLength(2);
    });
});

describe('pick', () => {
    it('returns an item of the list', () => {
        vi.spyOn(Math, 'random').mockReturnValue(0.99);

        expect(pick(['a', 'b', 'c'])).toBe('c');
    });
});
