// Calendar days in the learner's own time zone.

/** The local date as "YYYY-MM-DD". */
export function today(d: Date = new Date()): string {
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');

    return `${d.getFullYear()}-${month}-${day}`;
}

/**
 * Number of the local day since 1970-01-01. Local midnight is a few hours off a UTC day boundary
 * (time zone, summer time): rounding to the nearest whole day absorbs that, so two consecutive
 * dates always differ by exactly 1.
 */
export function dayNum(d: Date = new Date()): number {
    const midnight = new Date(d.getFullYear(), d.getMonth(), d.getDate());

    return Math.floor(midnight.getTime() / 864e5 + 0.5);
}

/** The moment `n` days from now (negative for the past). */
export function addDays(n: number): Date {
    const d = new Date();
    d.setDate(d.getDate() + n);

    return d;
}
