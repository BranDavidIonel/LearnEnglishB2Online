import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { addDays, dayNum, today } from './dates';

afterEach(() => {
    vi.useRealTimers();
});

describe('today', () => {
    it('writes the local date as YYYY-MM-DD', () => {
        expect(today(new Date(2026, 0, 5, 23, 59))).toBe('2026-01-05');
        expect(today(new Date(2026, 10, 21, 0, 0))).toBe('2026-11-21');
    });

    it('uses the current day by default', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date(2026, 2, 9, 12));

        expect(today()).toBe('2026-03-09');
    });
});

describe('dayNum', () => {
    it('is the same for every moment of a day', () => {
        expect(dayNum(new Date(2026, 4, 1, 0, 0))).toBe(
            dayNum(new Date(2026, 4, 1, 23, 59)),
        );
    });

    it('grows by exactly one per day, across summer time changes too', () => {
        for (const [y, m, d] of [
            [2026, 2, 28],
            [2026, 2, 29],
            [2026, 9, 24],
            [2026, 9, 25],
            [2026, 11, 31],
        ]) {
            expect(
                dayNum(new Date(y, m, d + 1)) - dayNum(new Date(y, m, d)),
            ).toBe(1);
        }
    });

    it('counts days since 1970-01-01', () => {
        expect(dayNum(new Date(1970, 0, 2))).toBe(1);
    });
});

describe('addDays', () => {
    it('moves the current moment by whole days', () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date(2026, 1, 27, 10));

        expect(today(addDays(2))).toBe('2026-03-01');
        expect(today(addDays(-27))).toBe('2026-01-31');
    });
});
