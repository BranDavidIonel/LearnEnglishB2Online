import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { today } from './dates';

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
